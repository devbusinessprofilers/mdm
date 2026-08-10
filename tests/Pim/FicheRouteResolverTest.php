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
    public function testResolvesShowAndEditRoutesForSupportedTypes(): void
    {
        $resolver = new FicheRouteResolver($this->urlGenerator());
        // L'éditeur MDM est la vue unique : « voir » et « modifier » y mènent.
        $expected = [
            TypeFiche::Lieu->value => 'app_mdm_fiche_lieu',
            TypeFiche::Activite->value => 'app_mdm_fiche_gamme',
            TypeFiche::Restaurant->value => 'app_mdm_fiche_gamme',
            TypeFiche::ServiceEvenementiel->value => 'app_mdm_fiche_gamme',
        ];

        foreach ($expected as $type => $route) {
            self::assertSame('/'.$route.'/01ARZ', $resolver->showUrl(TypeFiche::from($type), '01ARZ'));
            self::assertSame($resolver->showUrl(TypeFiche::from($type), '01ARZ'), $resolver->editUrl(TypeFiche::from($type), '01ARZ'));
        }
    }

    public function testTraiteurHasNoRoutes(): void
    {
        $resolver = new FicheRouteResolver($this->urlGenerator());

        $this->expectException(\InvalidArgumentException::class);
        $resolver->showUrl(TypeFiche::Traiteur, '01ARZ');
    }

    private function urlGenerator(): UrlGeneratorInterface
    {
        return new class implements UrlGeneratorInterface {
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return '/'.$name.'/'.($parameters['id'] ?? '');
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
