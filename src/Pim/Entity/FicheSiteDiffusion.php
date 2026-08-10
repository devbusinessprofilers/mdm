<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'pim_fiche_site_diffusion')]
#[ORM\Index(name: 'IDX_PIM_FSD_SITE_FICHE', columns: ['site_id', 'fiche_id'])]
class FicheSiteDiffusion
{
    public function __construct(
        #[ORM\Id]
        #[ORM\ManyToOne(inversedBy: 'siteSelections')]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private Fiche $fiche,
        #[ORM\Id]
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'site_id', onDelete: 'CASCADE')]
        private SiteDiffusion $site,
    ) {
    }

    public function fiche(): Fiche
    {
        return $this->fiche;
    }

    public function site(): SiteDiffusion
    {
        return $this->site;
    }

    public function siteId(): int
    {
        return $this->site->id() ?? throw new \LogicException('Le site de diffusion n\'est pas encore enregistré.');
    }
}
