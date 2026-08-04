<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Form\ActiviteSearchType;
use App\Pim\Form\LieuSearchType;
use App\Pim\Form\RestaurantSearchType;
use App\Pim\Form\ServiceSearchType;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormTypeInterface;

final class SearchFormCursorTest extends KernelTestCase
{
    /** @return iterable<string, array{class-string<FormTypeInterface<mixed>>}> */
    public static function searchForms(): iterable
    {
        yield 'lieu' => [LieuSearchType::class];
        yield 'activite' => [ActiviteSearchType::class];
        yield 'restaurant' => [RestaurantSearchType::class];
        yield 'service' => [ServiceSearchType::class];
    }

    /** @param class-string<FormTypeInterface<mixed>> $formType */
    #[DataProvider('searchForms')]
    public function testPaginationCursorIsAcceptedAsAnExtraGetParameter(string $formType): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);
        $form = $factory->createNamed('', $formType, null, ['csrf_protection' => false]);
        $form->submit(['q' => 'paris', 'limit' => '50', 'cursor' => 'opaque-cursor']);

        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('paris', $form->get('q')->getData());
    }
}
