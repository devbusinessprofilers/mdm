<?php

declare(strict_types=1);

namespace App\Vision\Service;

use App\Dam\Entity\MediaAsset;
use App\Dam\Service\DamFicheLinkResolver;
use App\Dam\Service\PublicMediaUrlGenerator;
use App\Pim\Entity\Fiche;
use App\Vision\Enum\EnhancementStatus;
use App\Vision\Repository\ImageEnhancementRepository;
use App\Vision\Repository\ImageRecognitionRepository;

/** Alimente les onglets « Import & retouche » et « Reconnaissance IA » de /medias. */
final readonly class VisionDashboardProvider
{
    private const PER_PAGE = 20;

    public function __construct(
        private ImageEnhancementRepository $enhancements,
        private ImageRecognitionRepository $recognitions,
        private DamFicheLinkResolver $ficheLinks,
        private PublicMediaUrlGenerator $urlGenerator,
    ) {
    }

    /** @return array{items: list<array<string, mixed>>, total: int, page: int, pages: int} */
    public function retouchePage(int $page): array
    {
        $page = max(1, $page);
        $items = [];
        foreach ($this->enhancements->recent($page, self::PER_PAGE) as $enhancement) {
            $items[] = [
                'enhancement' => $enhancement,
                'fiche_label' => $this->ficheLabel($enhancement->fiche()),
                'fiche_url' => $this->ficheLinks->editUrl($enhancement->fiche()),
                'avant_url' => $this->apercuUrl($enhancement->media()),
            ];
        }
        $total = $this->enhancements->countAll();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / self::PER_PAGE)];
    }

    /** @return array{items: list<array<string, mixed>>, total: int, page: int, pages: int} */
    public function recoPage(int $page): array
    {
        $page = max(1, $page);
        $items = [];
        foreach ($this->recognitions->recent($page, self::PER_PAGE) as $recognition) {
            $items[] = [
                'recognition' => $recognition,
                'fiche_label' => $this->ficheLabel($recognition->fiche()),
                'fiche_url' => $this->ficheLinks->editUrl($recognition->fiche()),
                'apercu_url' => $this->apercuUrl($recognition->media()),
            ];
        }
        $total = $this->recognitions->countAll();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / self::PER_PAGE)];
    }

    /**
     * Badges du rail : ce qui attend une décision humaine.
     *
     * @return array<string, int>
     */
    public function badges(): array
    {
        return [
            'import' => $this->enhancements->countByStatus(EnhancementStatus::Ready),
            'ia' => $this->recognitions->countAwaitingReview(),
        ];
    }

    private function ficheLabel(Fiche $fiche): string
    {
        return sprintf('%d — %s', $fiche->code(), $fiche->label() ?? 'Sans libellé');
    }

    private function apercuUrl(MediaAsset $media): ?string
    {
        $rendition = $media->rendition('small') ?? $media->rendition('medium');

        return null === $rendition ? null : $this->urlGenerator->url($rendition->storageKey());
    }
}
