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
        $expected = [
            TypeFiche::Lieu->value => ['app_pim_lieu_show', 'app_pim_lieu_edit'],
            TypeFiche::Activite->value => ['app_pim_activite_show', 'app_pim_activite_edit'],
            TypeFiche::Restaurant->value => ['app_pim_restaurant_show', 'app_pim_restaurant_edit'],
            TypeFiche::ServiceEvenementiel->value => ['app_pim_service_show', 'app_pim_service_edit'],
        ];

        foreach ($expected as $type => [$showRoute, $editRoute]) {
            self::assertSame('/'.$showRoute.'/01ARZ', $resolver->showUrl(TypeFiche::from($type), '01ARZ'));
            self::assertSame('/'.$editRoute.'/01ARZ', $resolver->editUrl(TypeFiche::from($type), '01ARZ'));
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
