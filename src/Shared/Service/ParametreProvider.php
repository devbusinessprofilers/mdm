<?php

declare(strict_types=1);

namespace App\Shared\Service;

use App\Shared\Repository\ParametreRepository;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Résout un paramètre applicatif : surcharge en base (table `parametre`) si
 * présente, sinon défaut issu de la variable d'environnement (map $defauts,
 * câblée dans services.yaml). Les surcharges sont chargées en une requête et
 * mises en cache ; le TTL borne la latence de propagation vers les workers,
 * dont le cache (/tmp) n'est pas invalidable depuis le conteneur web.
 */
final readonly class ParametreProvider implements ParametreProviderInterface
{
    private const CACHE_KEY = 'parametres_surcharges';
    private const CACHE_TTL_SECONDS = 300;

    /** @param array<string, string> $defauts défauts env, indexés par nom de paramètre */
    public function __construct(
        private ParametreRepository $parametres,
        #[Autowire(service: 'cache.parametres')]
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
        private array $defauts,
    ) {
    }

    public function bool(string $nom): bool
    {
        return filter_var($this->valeur($nom), FILTER_VALIDATE_BOOL);
    }

    public function int(string $nom): int
    {
        return (int) $this->valeur($nom);
    }

    public function string(string $nom): string
    {
        return $this->valeur($nom);
    }

    public function defaut(string $nom): ?string
    {
        return $this->defauts[$nom] ?? null;
    }

    public function invalider(): void
    {
        $this->cache->deleteItem(self::CACHE_KEY);
    }

    private function valeur(string $nom): string
    {
        return $this->surcharges()[$nom]
            ?? $this->defauts[$nom]
            ?? throw new \InvalidArgumentException(sprintf('Paramètre applicatif inconnu « %s ».', $nom));
    }

    /** @return array<string, string> */
    private function surcharges(): array
    {
        $item = $this->cache->getItem(self::CACHE_KEY);
        $cached = $item->isHit() ? $item->get() : null;
        if (is_array($cached)) {
            /** @var array<string, string> $cached */
            return $cached;
        }
        try {
            $surcharges = $this->parametres->surchargesIndexeesParNom();
        } catch (\Throwable $exception) {
            // La lecture d'un paramètre ne doit jamais faire tomber l'appelant
            // (alerting, contrôleurs) : sans base, les défauts env s'appliquent.
            $this->logger->error('parametres.load_failed', ['exception' => $exception->getMessage()]);

            return [];
        }
        $item->set($surcharges);
        $item->expiresAfter(self::CACHE_TTL_SECONDS);
        $this->cache->save($item);

        return $surcharges;
    }
}
