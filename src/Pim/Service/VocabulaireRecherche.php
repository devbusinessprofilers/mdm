<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Repository\RechercheRepository;
use App\Shared\Search\BooleanQueryFactory;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Vocabulaire tiré des libellés de fiches et des villes — les deux champs que
 * le placeholder de recherche promet (« nom · ville · identifiant »). Une
 * correction proposée est donc toujours un mot réellement cherchable. Les mots
 * sous la taille minimale d'indexation FULLTEXT sont écartés : les corriger ne
 * ramènerait aucun résultat.
 */
final readonly class VocabulaireRecherche implements VocabulaireRechercheInterface
{
    private const CACHE_KEY = 'vocabulaire-recherche';
    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private RechercheRepository $repository,
        private TextFingerprintCalculator $fingerprint,
        #[Autowire(service: 'cache.recherche')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function motsParLongueur(): array
    {
        $item = $this->cache->getItem(self::CACHE_KEY);
        $cached = $item->isHit() ? $item->get() : null;
        if (is_array($cached)) {
            /** @var array<int, array<string, int>> $cached */
            return $cached;
        }

        $vocabulaire = $this->construire();
        $item->set($vocabulaire);
        $item->expiresAfter(self::CACHE_TTL_SECONDS);
        $this->cache->save($item);

        return $vocabulaire;
    }

    public function motsAuContexte(array $tokens): array
    {
        $vocabulaire = [];
        foreach ($this->repository->labelsContenant($tokens, implode(' ', $tokens), 100) as $label) {
            $normalise = $this->fingerprint->normalize($label);
            foreach ('' === $normalise ? [] : explode(' ', $normalise) as $mot) {
                if (strlen($mot) < BooleanQueryFactory::MIN_TOKEN_SIZE) {
                    continue;
                }
                $vocabulaire[$mot] = ($vocabulaire[$mot] ?? 0) + 1;
            }
        }

        return $vocabulaire;
    }

    public function invalider(): void
    {
        $this->cache->deleteItem(self::CACHE_KEY);
    }

    /** @return array<int, array<string, int>> */
    private function construire(): array
    {
        $vocabulaire = [];
        foreach ($this->repository->textesVocabulaire() as $texte) {
            $normalise = $this->fingerprint->normalize($texte);
            foreach ('' === $normalise ? [] : explode(' ', $normalise) as $mot) {
                $longueur = strlen($mot);
                if ($longueur < BooleanQueryFactory::MIN_TOKEN_SIZE) {
                    continue;
                }
                $vocabulaire[$longueur][$mot] = ($vocabulaire[$longueur][$mot] ?? 0) + 1;
            }
        }
        ksort($vocabulaire);

        return $vocabulaire;
    }
}
