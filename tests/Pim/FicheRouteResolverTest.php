<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Enum\TypeFiche;
use App\Pim\Service\FicheRouteResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

final class FicheRouteResolverTest extends TestCase
{
    public function testLeLieuEtLesAutresGammesOntChacunLeurRoute(): void
    {
        $routes = new FicheRouteResolver($this->generateur());

        self::assertSame('app_mdm_fiche_lieu?id=01ABC', $routes->editUrl(TypeFiche::Lieu, '01ABC'));
        self::assertSame('app_mdm_fiche_lieu?id=01ABC&section=2', $routes->editUrl(TypeFiche::Lieu, '01ABC', 2));
        self::assertSame('app_mdm_fiche_gamme?gamme=restaurants&id=01ABC&section=1', $routes->editUrl(TypeFiche::Restaurant, '01ABC', 1));
        self::assertSame($routes->editUrl(TypeFiche::Activite, '01ABC'), $routes->showUrl(TypeFiche::Activite, '01ABC'));
    }

    public function testUneGammeHorsPerimetreEstRefusee(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new FicheRouteResolver($this->generateur()))->editUrl(TypeFiche::Traiteur, '01ABC');
    }

    private function generateur(): UrlGeneratorInterface
    {
        return new class implements UrlGeneratorInterface {
            /** @param array<string, mixed> $parameters */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return $name.'?'.http_build_query($parameters);
            }

            public function setContext(RequestContext $context): void
            {
            }

            public function getContext(): RequestContext
            {
                return new RequestContext();
            }
        };
    }
}
