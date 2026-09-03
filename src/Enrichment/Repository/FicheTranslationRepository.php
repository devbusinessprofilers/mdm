<?php

declare(strict_types=1);

namespace App\Enrichment\Repository;

use App\Enrichment\Entity\FicheTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Pim\Entity\Fiche;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<FicheTranslation> */
final class FicheTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheTranslation::class);
    }

    /** @return list<FicheTranslation> */
    public function forFiche(Fiche $fiche): array
    {
        return $this->findBy(['fiche' => $fiche], ['fieldPath' => 'ASC', 'locale' => 'ASC']);
    }

    public function findOne(Fiche $fiche, string $path, SupportedLocale $locale): ?FicheTranslation
    {
        return $this->findOneBy(['fiche' => $fiche, 'fieldPath' => $path, 'locale' => $locale]);
    }

    /** @return list<FicheTranslation> */
    public function requested(Fiche $fiche, SupportedLocale $locale, string $token): array
    {
        return $this->findBy(['fiche' => $fiche, 'locale' => $locale, 'requestToken' => $token], ['fieldPath' => 'ASC']);
    }
}
