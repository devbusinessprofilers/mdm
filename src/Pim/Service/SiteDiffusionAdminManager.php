<?php

declare(strict_types=1);

namespace App\Pim\Service;

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
        ];
    }
}
