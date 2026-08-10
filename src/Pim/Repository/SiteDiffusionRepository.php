<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\SiteDiffusion;
use App\Pim\Enum\TypeFiche;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SiteDiffusion> */
class SiteDiffusionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteDiffusion::class);
    }

    /** @return list<SiteDiffusion> Sites actifs dans l'ordre d'affichage, groupes contigus. */
    public function findActifsOrdonnes(): array
    {
        return $this->findBy(['actif' => true], ['position' => 'ASC', 'id' => 'ASC']);
    }

    /** @return list<SiteDiffusion> Sites présélectionnés à la création d'une fiche de cette gamme. */
    public function findParDefautPourGamme(TypeFiche $type): array
    {
        return array_values(array_filter(
            $this->findActifsOrdonnes(),
            static fn (SiteDiffusion $site): bool => $site->obligatoire() || $site->estParDefautPour($type),
        ));
    }

    public function findOneByCode(string $code): ?SiteDiffusion
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * Présélection du formulaire de création avant le choix de la gamme :
     * sites obligatoires et sites par défaut d'au moins une gamme.
     *
     * @return list<int>
     */
    public function idsPreselectionInitiale(): array
    {
        return array_values(array_filter(array_map(
            static fn (SiteDiffusion $site): ?int => $site->obligatoire() || [] !== $site->gammesParDefaut()
                ? $site->id()
                : null,
            $this->findActifsOrdonnes(),
        )));
    }

    /** @return array<string, array<string, int>> Choix groupés pour un champ de formulaire, mentions comprises. */
    public function choicesGroupees(): array
    {
        $choices = [];
        foreach ($this->findActifsOrdonnes() as $site) {
            $mention = $site->obligatoire() ? ' (obligatoire)' : ($site->payant() ? ' (payant)' : '');
            $choices[$site->groupe()][$site->label().$mention] = (int) $site->id();
        }

        return $choices;
    }
}
