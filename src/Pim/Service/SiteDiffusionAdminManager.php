<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\CritereGeo;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Repository\SiteDiffusionRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class SiteDiffusionAdminManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SiteDiffusionRepository $sites,
    ) {
    }

    /** @param array<string, mixed> $data Données validées du formulaire SiteDiffusionType. */
    public function creer(array $data): SiteDiffusion
    {
        $code = (string) $data['code'];
        if (null !== $this->sites->findOneByCode($code)) {
            throw new \DomainException(sprintf('Le code "%s" est déjà utilisé.', $code));
        }
        $site = new SiteDiffusion(
            $code,
            (string) $data['label'],
            (string) $data['groupe'],
            (bool) $data['obligatoire'],
            (bool) $data['payant'],
            (int) $data['position'],
            $data['gammesParDefaut'],
            self::criteresGeo($data),
        );
        if (!$data['actif']) {
            $site->deactivate();
        }
        $this->entityManager->persist($site);
        $this->entityManager->flush();

        return $site;
    }

    /** @param array<string, mixed> $data Données validées du formulaire SiteDiffusionType. */
    public function modifier(SiteDiffusion $site, array $data): void
    {
        $site->changeLabel((string) $data['label']);
        $site->changeGroupe((string) $data['groupe']);
        $site->changePosition((int) $data['position']);
        $site->changeObligatoire((bool) $data['obligatoire']);
        $site->changePayant((bool) $data['payant']);
        $site->changeGammesParDefaut($data['gammesParDefaut']);
        $site->changeCriteresGeo(self::criteresGeo($data));
        $data['actif'] ? $site->activate() : $site->deactivate();
        $this->entityManager->flush();
    }

    /** @return array<string, mixed> Données initiales du formulaire pour un site existant. */
    public static function donnees(SiteDiffusion $site): array
    {
        return [
            'code' => $site->code(),
            'label' => $site->label(),
            'groupe' => $site->groupe(),
            'position' => $site->position(),
            'obligatoire' => $site->obligatoire(),
            'payant' => $site->payant(),
            'actif' => $site->actif(),
            'gammesParDefaut' => $site->gammesParDefaut(),
            'criteresGeo' => array_map(static fn (CritereGeo $critere): array => [
                'type' => $critere->type,
                'villePays' => 'FR',
                'ville' => $critere->ville,
                'latitude' => $critere->latitude,
                'longitude' => $critere->longitude,
                'rayonKm' => $critere->rayonKm,
                'departement' => $critere->departement,
                'region' => $critere->region,
                'countryCode' => $critere->countryCode,
            ], $site->criteresGeo()),
        ];
    }

    /**
     * Lignes validées du formulaire → critères. La validation par ligne
     * (CritereGeoType) garantit la complétude ; une ligne malgré tout
     * inconstructible est écartée plutôt que de bloquer l'écran.
     *
     * @param array<string, mixed> $data
     *
     * @return list<CritereGeo>
     */
    private static function criteresGeo(array $data): array
    {
        $criteres = [];
        foreach (is_array($data['criteresGeo'] ?? null) ? $data['criteresGeo'] : [] as $ligne) {
            if (!is_array($ligne)) {
                continue;
            }
            $critere = CritereGeo::fromArray($ligne);
            if (null !== $critere) {
                $criteres[] = $critere;
            }
        }

        return $criteres;
    }
}
