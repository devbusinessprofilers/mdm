<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\FicheRelancePlanifiee;
use App\Pim\Repository\FicheAffiliationRepository;
use App\Pim\Repository\FicheRelancePlanifieeRepository;
use App\Pim\Repository\FicheRelanceRepository;
use App\Pim\Repository\FicheRepository;
use App\Shared\Service\ParametreProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Préparation du lot hebdomadaire de relances de complétude : sélectionne les
 * fiches éligibles et snapshote score et destinataires dans
 * pim_fiche_relance_planifiee pour vérification dans /admin/relances-completude
 * avant l'envoi. Chaque préparation annule les lignes encore planifiées du lot
 * précédent : il n'y a jamais deux lots actifs.
 */
final readonly class RelanceCompletudePlanificateur
{
    public function __construct(
        private FicheRelanceRepository $relances,
        private FicheRelancePlanifieeRepository $planifiees,
        private FicheRepository $fiches,
        private FicheAffiliationRepository $affiliations,
        private EntityManagerInterface $entityManager,
        private ParametreProviderInterface $parametres,
    ) {
    }

    /** @return int nombre de relances planifiées */
    public function preparer(): int
    {
        $threshold = $this->parametres->int('completude.seuil_rappel');
        if ($threshold <= 0) {
            return 0;
        }
        $preparedAt = new \DateTimeImmutable();
        $this->planifiees->annulerPlanifiees(
            $preparedAt,
            sprintf('Remplacée par la préparation du %s', $preparedAt->format('d/m/Y H:i')),
        );

        $cooldownDays = $this->parametres->int('completude.delai_rappel_jours');
        $cooldownDate = new \DateTimeImmutable(sprintf('-%d days', max(1, $cooldownDays)));
        $count = 0;
        foreach ($this->relances->findFichesNeedingReminder($threshold, $cooldownDate) as $row) {
            $fiche = $this->fiches->find(Ulid::fromString($row['id']));
            if (!$fiche instanceof Fiche) {
                continue;
            }
            $emails = $this->emailsDestinataires($fiche);
            if ([] === $emails) {
                continue;
            }
            $this->entityManager->persist(new FicheRelancePlanifiee($fiche, $preparedAt, $row['completeness'], $emails));
            ++$count;
        }
        $this->entityManager->flush();

        return $count;
    }

    /** @return list<string> */
    private function emailsDestinataires(Fiche $fiche): array
    {
        $actifs = array_filter(
            $this->affiliations->findBy(['fiche' => $fiche, 'receivesRequests' => true]),
            static fn (FicheAffiliation $affiliation): bool => $affiliation->collaborateur()->isActive(),
        );

        return array_values(array_map(
            static fn (FicheAffiliation $affiliation): string => $affiliation->collaborateur()->email(),
            $actifs,
        ));
    }
}
