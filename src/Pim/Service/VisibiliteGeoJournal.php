<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\VisibiliteGeoRun;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Historique des attributions géographiques dans le journal des traitements
 * (/outils, famille « Visibilité géographique ») : chaque traitement laisse sa
 * trace — rattrapage global, attribution à la création, clic « Appliquer les
 * sites automatiques ». Le flush reste à la charge de l'appelant, la trace
 * partant avec la transaction du traitement qu'elle décrit.
 */
final readonly class VisibiliteGeoJournal
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function traceFiche(string $declencheur, Fiche $fiche, int $ajoutes): void
    {
        $this->entityManager->persist(new VisibiliteGeoRun($declencheur, $fiche, 1, $ajoutes));
    }

    /** @param array<string, int> $parSite Attributions par code de site. */
    public function traceCommande(int $nbFiches, int $nbAttributions, array $parSite): void
    {
        $this->entityManager->persist(new VisibiliteGeoRun(
            VisibiliteGeoRun::DECLENCHEUR_COMMANDE,
            null,
            $nbFiches,
            $nbAttributions,
            $parSite,
        ));
    }
}
