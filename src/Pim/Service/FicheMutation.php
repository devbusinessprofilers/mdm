<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Message\IndexFiche;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Les écritures de fiche qui ne doivent pas faire bouger un workflow : le
 * point d'entrée unique pour flusher « techniquement », là où chaque
 * service réécrivait la même séquence drain → IndexFiche → flush sous
 * preserveWorkflowsDuring.
 */
final readonly class FicheMutation
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
    ) {
    }

    /**
     * Flush après une modification d'une fiche dont la liaison Lieu ↔
     * Restaurant a pu changer : les fiches liées détachées ou attachées sont
     * réindexées (leur payload marketplace change) sans transition de
     * workflow. Les autres gammes n'ont pas de liaison : simple flush.
     */
    public function enregistrerAvecLiees(Lieu|Restaurant|Activite|ServiceEvenementiel $entite): void
    {
        $liees = $entite instanceof Lieu || $entite instanceof Restaurant ? $entite->drainFichesLieesAResynchroniser() : [];
        foreach ($liees as $fiche) {
            $this->outbox->enqueue(new IndexFiche($fiche->idString()));
        }
        $this->techniquement($liees, fn () => $this->entityManager->flush());
    }

    /**
     * Exécute une mutation puis flush sans que le statut des fiches données
     * ne change (mise à jour technique).
     *
     * @template T
     *
     * @param list<Fiche>   $fiches
     * @param callable(): T $mutation
     *
     * @return T
     */
    public function techniquement(array $fiches, callable $mutation): mixed
    {
        return Fiche::preserveWorkflowsDuring($fiches, function () use ($mutation): mixed {
            $resultat = $mutation();
            $this->entityManager->flush();

            return $resultat;
        });
    }
}
