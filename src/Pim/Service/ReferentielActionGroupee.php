<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Account\Entity\User;
use App\Account\Message\CollaborateurAccessRequested;
use App\Account\Security\FicheVoter;
use App\Enrichment\Service\FicheTranslationScheduler;
use App\Etl\Service\PhotoPublicationGuard;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\FicheAffiliationRepository;
use App\Pim\Repository\FicheRepository;
use App\Pim\Repository\SiteDiffusionRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Ulid;

/**
 * Applique une action à une sélection de fiches. Chaque fiche est traitée par
 * les mécanismes unitaires existants : une fiche qui n'est pas dans l'état
 * requis, ou que l'utilisateur n'a pas le droit de toucher, est ignorée et
 * comptée — jamais forcée.
 */
final readonly class ReferentielActionGroupee
{
    /** Plafonds par action, ceux de la maquette. */
    public const PLAFONDS = [
        'soumettre' => 5000,
        'valider' => 5000,
        'publier' => 5000,
        'archiver' => 5000,
        'desarchiver' => 5000,
        'republier' => 5000,
        'exporter' => 5000,
        // Envoi manuel groupé vers Salesforce : borné pour garder l'e-mail et
        // le CSV en mémoire raisonnables (le reste passe par la synchro auto).
        'salesforce' => 2000,
        'acces' => 500,
        'contributeur' => 5000,
        'visibilite' => 5000,
    ];

    private const LOT = 100;

    public function __construct(
        private FicheRepository $fiches,
        private FicheAffiliationRepository $affiliations,
        private SiteDiffusionRepository $sites,
        private EntityManagerInterface $entityManager,
        private FicheTranslationScheduler $translations,
        private OutboxPublisherInterface $outbox,
        private Security $security,
        private PhotoPublicationGuard $photoGuard,
    ) {
    }

    public static function plafond(string $action): int
    {
        return self::PLAFONDS[$action] ?? 5000;
    }

    /**
     * @param list<string> $ids     ULID texte
     * @param list<int>    $siteIds Sites à attribuer (action « visibilite »)
     *
     * @return array{appliquees: int, ignorees: int}
     */
    public function appliquer(string $action, array $ids, string $actorId, ?User $contributeur = null, array $siteIds = []): array
    {
        if (!in_array($action, ['soumettre', 'valider', 'publier', 'archiver', 'desarchiver', 'republier', 'acces', 'contributeur', 'visibilite'], true)) {
            throw new \InvalidArgumentException(sprintf('Action groupée inconnue : "%s".', $action));
        }
        if (count($ids) > self::plafond($action)) {
            throw new \DomainException(sprintf('L\'action dépasse son plafond de %d fiches.', self::plafond($action)));
        }
        if ('contributeur' === $action && null === $contributeur) {
            throw new \DomainException('Choisissez le contributeur à assigner.');
        }
        $sitesRetenus = 'visibilite' === $action ? $this->sitesRetenus($siteIds) : [];
        if ('visibilite' === $action && [] === $sitesRetenus) {
            throw new \DomainException('Choisissez au moins un site de diffusion à attribuer.');
        }
        $attribut = match ($action) {
            'soumettre' => FicheVoter::SUBMIT,
            'valider' => FicheVoter::VALIDATE,
            'publier', 'republier' => FicheVoter::PUBLISH,
            'archiver', 'desarchiver' => FicheVoter::ARCHIVE,
            // Accès extranet et assignation relèvent de la gestion des contacts.
            'acces', 'contributeur' => FicheVoter::MANAGE_AFFILIATIONS,
            'visibilite' => FicheVoter::EDIT,
        };
        $appliquees = 0;
        $ignorees = 0;
        $emailsServis = [];
        foreach (array_chunk($ids, self::LOT) as $lot) {
            $ulids = array_map(static fn (string $id): Ulid => Ulid::fromString($id), $lot);
            // Visibilité : les collections de sites sont chargées avec le lot,
            // pour éviter une requête par fiche dans ajouterSitesDiffusion.
            $fiches = 'visibilite' === $action
                ? $this->fiches->findByIdsAvecSiteSelections($ulids)
                : $this->fiches->findBy(['id' => $ulids]);
            $affiliationsParFiche = 'acces' === $action ? $this->affiliationsParFiche($fiches) : [];
            $ignorees += count($lot) - count($fiches);
            foreach ($fiches as $fiche) {
                if (!$this->security->isGranted($attribut, $fiche)) {
                    ++$ignorees;
                    continue;
                }
                if ('acces' === $action) {
                    [$envoyes, $sautes] = $this->envoyerAcces($fiche, $affiliationsParFiche[$fiche->idString()] ?? [], $emailsServis);
                    $appliquees += $envoyes;
                    $ignorees += $sautes;
                    continue;
                }
                if ('contributeur' === $action) {
                    $fiche->changeAssignee($contributeur);
                    ++$appliquees;
                    continue;
                }
                if ('visibilite' === $action) {
                    // Une fiche qui a déjà tous les sites demandés est comptée ignorée.
                    $fiche->ajouterSitesDiffusion($sitesRetenus) > 0 ? ++$appliquees : ++$ignorees;
                    continue;
                }
                // Publication de masse (publier une fiche validée, republier une
                // fiche archivée) : une fiche qui ne satisfait pas les obligations
                // photos (minimum du type + photo principale) n'est pas publiée,
                // comme à l'import et au fil de l'eau (garde photos).
                if (in_array($action, ['publier', 'republier'], true) && !$this->photoGuard->compliant($fiche)) {
                    ++$ignorees;
                    continue;
                }
                try {
                    $this->transition($action, $fiche, $actorId);
                } catch (\DomainException) {
                    ++$ignorees;
                    continue;
                }
                if (in_array($action, ['publier', 'republier'], true)) {
                    $this->translations->schedule($fiche);
                }
                $this->outbox->enqueue(new IndexFiche($fiche->idString()));
                ++$appliquees;
            }
            $this->entityManager->flush();
        }

        return ['appliquees' => $appliquees, 'ignorees' => $ignorees];
    }

    /**
     * Les affiliations de tout le lot en une requête, indexées par fiche.
     *
     * @param list<Fiche> $fiches
     *
     * @return array<string, list<FicheAffiliation>>
     */
    private function affiliationsParFiche(array $fiches): array
    {
        if ([] === $fiches) {
            return [];
        }
        $parFiche = [];
        foreach ($this->affiliations->findBy(['fiche' => $fiches]) as $affiliation) {
            $parFiche[$affiliation->fiche()->idString()][] = $affiliation;
        }

        return $parFiche;
    }

    /**
     * Un message marketplace par collaborateur actif, hors contact de repli,
     * chaque email n'étant servi qu'une fois sur l'ensemble de la sélection.
     * C'est la marketplace qui crée le compte et envoie le mail.
     *
     * @param list<FicheAffiliation> $affiliations
     * @param array<string, true>    $emailsServis
     *
     * @return array{int, int} [envoyés, ignorés]
     */
    private function envoyerAcces(Fiche $fiche, array $affiliations, array &$emailsServis): array
    {
        $envoyes = 0;
        $ignores = 0;
        foreach ($affiliations as $affiliation) {
            $collaborateur = $affiliation->collaborateur();
            if ($affiliation->repli() || !$collaborateur->isActive() || isset($emailsServis[$collaborateur->email()])) {
                ++$ignores;
                continue;
            }
            $emailsServis[$collaborateur->email()] = true;
            $this->outbox->enqueue(new CollaborateurAccessRequested($collaborateur->id(), $fiche->idString()));
            ++$envoyes;
        }

        return [$envoyes, $ignores];
    }

    /**
     * @param list<int> $siteIds
     *
     * @return list<SiteDiffusion> Les sites actifs demandés, dans l'ordre d'affichage
     */
    private function sitesRetenus(array $siteIds): array
    {
        $demandes = array_fill_keys($siteIds, true);

        return array_values(array_filter(
            $this->sites->findActifsOrdonnes(),
            static fn (SiteDiffusion $site): bool => null !== $site->id() && isset($demandes[$site->id()]),
        ));
    }

    private function transition(string $action, Fiche $fiche, string $actorId): void
    {
        match ($action) {
            'soumettre' => $fiche->submitForValidation($actorId),
            'valider' => $fiche->validate($actorId),
            'publier' => $fiche->publish(),
            'archiver' => $fiche->archive($actorId),
            'desarchiver' => $fiche->unarchive($actorId),
            'republier' => $fiche->republish($actorId),
            default => throw new \InvalidArgumentException(sprintf('Action groupée inconnue : "%s".', $action)),
        };
    }
}
