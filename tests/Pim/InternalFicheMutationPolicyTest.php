<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Fiche;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Service\InternalFicheMutationPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class InternalFicheMutationPolicyTest extends TestCase
{
    #[DataProvider('workflowStates')]
    public function testValidatorPreservesEveryWorkflowState(string $state): void
    {
        $fiche = $this->ficheIn($state);
        $workflow = $this->workflow($fiche);
        $policy = $this->policy(true);

        $result = $policy->execute($fiche, function () use ($fiche): string {
            $fiche->changeLabel('Libellé validateur');

            return 'saved';
        });

        self::assertSame('saved', $result);
        self::assertSame($workflow, $this->workflow($fiche));
        self::assertSame('Libellé validateur', $fiche->label());
    }

    public function testEditorKeepsTheImplicitReturnToDraft(): void
    {
        $fiche = $this->ficheIn('published');

        $this->policy(false)->execute($fiche, static function () use ($fiche): void {
            $fiche->changeLabel('Libellé éditeur');
        });

        self::assertSame(StatutFiche::EnCours, $fiche->status());
        self::assertNull($fiche->validationRequestedAt());
        self::assertNull($fiche->validationRequestedBy());
        self::assertNull($fiche->validationReviewedAt());
        self::assertNull($fiche->validationReviewedBy());
    }

    public function testValidatorWorkflowIsRestoredWhenTheMutationFails(): void
    {
        $fiche = $this->ficheIn('archived');
        $workflow = $this->workflow($fiche);

        $caught = null;
        try {
            $this->policy(true)->execute($fiche, function () use ($fiche): void {
                $fiche->changeLabel('Modification interrompue');

                throw new \RuntimeException('Echec attendu');
            });
        } catch (\RuntimeException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(\RuntimeException::class, $caught);
        self::assertSame('Echec attendu', $caught->getMessage());
        self::assertSame($workflow, $this->workflow($fiche));
    }

    /** @return iterable<string, array{string}> */
    public static function workflowStates(): iterable
    {
        yield 'en cours' => ['draft'];
        yield 'en attente de validation' => ['pending'];
        yield 'validée' => ['validated'];
        yield 'publiée' => ['published'];
        yield 'archivée' => ['archived'];
    }

    private function policy(bool $validator): InternalFicheMutationPolicy
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->expects(self::once())
            ->method('isGranted')
            ->with('ROLE_BP_VALIDATOR')
            ->willReturn($validator);

        return new InternalFicheMutationPolicy($authorizationChecker);
    }

    private function ficheIn(string $state): Fiche
    {
        $fiche = new Fiche(TypeFiche::Lieu);
        if ('draft' === $state) {
            return $fiche;
        }

        $fiche->submitForValidation('editor');
        if ('pending' === $state) {
            return $fiche;
        }

        $fiche->validate('validator');
        if ('validated' === $state) {
            return $fiche;
        }

        $fiche->publish();
        if ('published' === $state) {
            return $fiche;
        }

        $fiche->archive('validator');

        return $fiche;
    }

    /** @return array<int, mixed> */
    private function workflow(Fiche $fiche): array
    {
        return [
            $fiche->status(),
            $fiche->publishedAt(),
            $fiche->archivedAt(),
            $fiche->validationRequestedAt(),
            $fiche->validationRequestedBy(),
            $fiche->validationReviewedAt(),
            $fiche->validationReviewedBy(),
            $fiche->validationFeedback(),
        ];
    }
}
