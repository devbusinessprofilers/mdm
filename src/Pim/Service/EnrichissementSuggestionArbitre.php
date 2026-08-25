<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\FicheSuggestion;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\SuggestionAction;
use App\Pim\Repository\ActiviteRepository;
use App\Pim\Repository\LieuRepository;
use App\Pim\Repository\RestaurantRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Arbitrage humain d'une suggestion d'enrichissement générique (bloc
 * « Suggestions en attente ») : Accepter applique la décision, Ignorer la solde.
 * Un backfill de champ passe par la politique de mutation interne (pas de
 * transition de workflow) ; une proposition d'archivage emprunte le workflow
 * habituel (dépublication marketplace en aval).
 */
final readonly class EnrichissementSuggestionArbitre
{
    public function __construct(
        private InternalFicheMutationPolicy $policy,
        private FicheWorkflowManager $workflow,
        private LieuRepository $lieux,
        private RestaurantRepository $restaurants,
        private ActiviteRepository $activites,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @throws \DomainException */
    public function accepter(FicheSuggestion $suggestion, string $actor): void
    {
        $this->assertEnAttente($suggestion);
        // Applique avant de décider : en arbitrage groupé, une application qui
        // échoue ne doit pas laisser un statut « acceptée » dans l'unit of work,
        // que le flush d'une ligne suivante persisterait sans effet sur la fiche.
        if (SuggestionAction::Archiver === $suggestion->action()) {
            $this->workflow->archive($suggestion->fiche(), $actor);
            $suggestion->accepter($actor);
            $this->entityManager->flush();

            return;
        }
        $this->remplirChamp($suggestion);
        $suggestion->accepter($actor);
        // La valeur appliquée doit repasser par IndexFiche (complétude, resync
        // marketplace), comme tout autre chemin d'écriture.
        $this->workflow->indexAndFlush($suggestion->fiche());
    }

    /** @throws \DomainException */
    public function ignorer(FicheSuggestion $suggestion, string $actor): void
    {
        $this->assertEnAttente($suggestion);
        $suggestion->ignorer($actor);
        $this->entityManager->flush();
    }

    private function remplirChamp(FicheSuggestion $suggestion): void
    {
        $champ = $suggestion->champ();
        $fiche = $suggestion->fiche();
        if (str_starts_with($champ, 'restaurant_')) {
            $restaurant = $this->restaurants->findOneByFiche($fiche)
                ?? throw new \DomainException('Restaurant introuvable : impossible d\'appliquer la valeur.');
            $this->policy->execute($fiche, fn () => $this->appliquerRestaurant($restaurant, $suggestion));

            return;
        }
        if (str_starts_with($champ, 'activite_')) {
            $activite = $this->activites->findOneByFiche($fiche)
                ?? throw new \DomainException('Activité introuvable : impossible d\'appliquer la valeur.');
            $this->policy->execute($fiche, fn () => $this->appliquerActivite($activite, $suggestion));

            return;
        }
        $lieu = $this->lieux->findOneByFiche($fiche)
            ?? throw new \DomainException('Fiche sans bloc administratif : impossible d\'appliquer la valeur.');
        $this->policy->execute($fiche, fn () => $this->appliquerLieu($lieu, $suggestion));
    }

    private function appliquerLieu(Lieu $lieu, FicheSuggestion $suggestion): void
    {
        $champ = $suggestion->champ();
        $payload = $suggestion->payload() ?? [];
        if (str_starts_with($champ, 'lieu_lov_')) {
            $codes = self::fusion(
                'BIEN_ETRE' === ($payload['attribut'] ?? null) ? $lieu->bienEtre() : $lieu->installation(),
                $payload,
            );
            match ($payload['attribut'] ?? null) {
                'BIEN_ETRE' => $lieu->changeBienEtre($codes),
                'INSTALLATION' => $lieu->changeInstallation($codes),
                default => throw new \DomainException('Attribut LOV Lieu non applicable.'),
            };

            return;
        }
        [$courante, $appliquer] = match ($champ) {
            'info_legale_siret' => [$lieu->administratif()->infoLegaleSiret(), fn () => $lieu->administratif()->changeInfoLegaleSiret($suggestion->valeurProposee())],
            'info_legale_num_tva' => [$lieu->administratif()->infoLegaleNumTva(), fn () => $lieu->administratif()->changeInfoLegaleNumTva($suggestion->valeurProposee())],
            'lieu_desc_generale' => [$lieu->descGenerale(), fn () => $lieu->changeDescGenerale(self::texte($payload))],
            'lieu_chaine' => [$lieu->chaineHoteliere(), fn () => $lieu->changeChaineHoteliere($suggestion->valeurProposee())],
            default => throw new \DomainException(sprintf('Champ « %s » non applicable.', $champ)),
        };
        $this->assertFraicheur($courante, $suggestion);
        $appliquer();
    }

    private function appliquerActivite(Activite $activite, FicheSuggestion $suggestion): void
    {
        if ('activite_desc_generale' !== $suggestion->champ()) {
            throw new \DomainException(sprintf('Champ « %s » non applicable.', $suggestion->champ()));
        }
        $this->assertFraicheur($activite->descriptionGenerale(), $suggestion);
        $activite->changeDescriptionGenerale(self::texte($suggestion->payload() ?? []));
    }

    /** @param array<string, mixed> $payload */
    private static function texte(array $payload): ?string
    {
        return is_string($payload['text'] ?? null) ? $payload['text'] : null;
    }

    private function appliquerRestaurant(Restaurant $restaurant, FicheSuggestion $suggestion): void
    {
        $payload = $suggestion->payload() ?? [];
        if ('restaurant_site_officiel' === $suggestion->champ()) {
            $this->assertFraicheur($restaurant->siteOfficiel(), $suggestion);
            $restaurant->changeSiteOfficiel($suggestion->valeurProposee());

            return;
        }
        match ($suggestion->champ()) {
            'restaurant_types_cuisine' => $restaurant->changeTypesCuisine(self::fusion($restaurant->typesCuisine(), $payload)),
            'restaurant_specificites' => $restaurant->changeSpecificitesAlimentaires(self::fusion($restaurant->specificitesAlimentaires(), $payload)),
            'restaurant_equipements' => $restaurant->changeEquipements(self::fusion($restaurant->equipements(), $payload)),
            'restaurant_acces_pmr' => $restaurant->changeAccesPmr((bool) ($payload['bool'] ?? false)),
            'restaurant_toilettes_pmr' => $restaurant->changeToilettesPmr((bool) ($payload['bool'] ?? false)),
            default => throw new \DomainException(sprintf('Champ « %s » non applicable.', $suggestion->champ())),
        };
    }

    /**
     * Union des codes LOV existants et proposés (le backfill ajoute, n'écrase pas).
     *
     * @param list<string>         $actuels
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private static function fusion(array $actuels, array $payload): array
    {
        $ajouts = is_array($payload['codes'] ?? null) ? $payload['codes'] : [];

        return array_values(array_unique([...$actuels, ...array_map('strval', $ajouts)]));
    }

    /**
     * La colonne « Actuel » de l'écran reflète la valeur au moment du scan : si
     * le champ a changé depuis (saisie manuelle), accepter écraserait cette
     * saisie — la suggestion reste en attente jusqu'au prochain scan.
     *
     * @throws \DomainException
     */
    private function assertFraicheur(?string $courante, FicheSuggestion $suggestion): void
    {
        if (self::normalise($courante) !== $suggestion->valeurActuelle()) {
            throw new \DomainException('La valeur du champ a changé depuis le scan : suggestion périmée.');
        }
    }

    /** Même normalisation que le stockage de valeurActuelle (trim, vide = null, 500 caractères). */
    private static function normalise(?string $valeur): ?string
    {
        $valeur = trim((string) $valeur);

        return '' === $valeur ? null : mb_substr($valeur, 0, 500);
    }

    /** @throws \DomainException */
    private function assertEnAttente(FicheSuggestion $suggestion): void
    {
        if (!$suggestion->isPending()) {
            throw new \DomainException('Cette suggestion a déjà été arbitrée.');
        }
    }
}
