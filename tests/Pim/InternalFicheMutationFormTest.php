<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\StatutFiche;
use App\Pim\Form\ActiviteType;
use App\Pim\Form\LieuType;
use App\Pim\Form\RestaurantType;
use App\Pim\Form\ServiceEvenementielType;
use App\Pim\Service\InternalFicheMutationPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class InternalFicheMutationFormTest extends KernelTestCase
{
    /**
     * @param class-string<ActiviteType|LieuType|RestaurantType|ServiceEvenementielType> $formType
     * @param class-string<Activite|Lieu|Restaurant|ServiceEvenementiel>                 $entityType
     */
    #[DataProvider('ficheForms')]
    public function testEveryMainFormUsesTheSameRoleDependentRule(
        string $formType,
        string $entityType,
    ): void {
        self::bootKernel();
        $forms = self::getContainer()->get(FormFactoryInterface::class);
        self::assertInstanceOf(FormFactoryInterface::class, $forms);

        $validatorFiche = new $entityType();
        $validatorFiche->fiche()->publishForImport();
        $validatorForm = $this->form($forms, $formType, $validatorFiche);
        $this->policy(true)->execute($validatorFiche->fiche(), static function () use ($validatorForm): void {
            $validatorForm->submit(['label' => 'Modification validateur']);
        });

        self::assertSame(StatutFiche::Publiee, $validatorFiche->fiche()->status());

        $editorFiche = new $entityType();
        $editorFiche->fiche()->publishForImport();
        $editorForm = $this->form($forms, $formType, $editorFiche);
        $this->policy(false)->execute($editorFiche->fiche(), static function () use ($editorForm): void {
            $editorForm->submit(['label' => 'Modification éditeur']);
        });

        self::assertSame(StatutFiche::EnCours, $editorFiche->fiche()->status());
    }

    /** @return iterable<string, array{class-string, class-string}> */
    public static function ficheForms(): iterable
    {
        yield 'lieu' => [LieuType::class, Lieu::class];
        yield 'activité' => [ActiviteType::class, Activite::class];
        yield 'restaurant' => [RestaurantType::class, Restaurant::class];
        yield 'service' => [ServiceEvenementielType::class, ServiceEvenementiel::class];
    }

    private function policy(bool $validator): InternalFicheMutationPolicy
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($validator);

        return new InternalFicheMutationPolicy($authorizationChecker);
    }

    /** @return FormInterface<mixed> */
    private function form(
        FormFactoryInterface $forms,
        string $formType,
        object $entity,
    ): FormInterface {
        return match ($formType) {
            LieuType::class => $forms->create(
                LieuType::class,
                $entity instanceof Lieu ? $entity : throw new \LogicException(),
                ['csrf_protection' => false],
            ),
            ActiviteType::class => $forms->create(
                ActiviteType::class,
                $entity instanceof Activite ? $entity : throw new \LogicException(),
                ['csrf_protection' => false],
            ),
            RestaurantType::class => $forms->create(
                RestaurantType::class,
                $entity instanceof Restaurant ? $entity : throw new \LogicException(),
                ['csrf_protection' => false],
            ),
            ServiceEvenementielType::class => $forms->create(
                ServiceEvenementielType::class,
                $entity instanceof ServiceEvenementiel ? $entity : throw new \LogicException(),
                ['csrf_protection' => false],
            ),
            default => throw new \LogicException('Type de formulaire non couvert.'),
        };
    }
}
