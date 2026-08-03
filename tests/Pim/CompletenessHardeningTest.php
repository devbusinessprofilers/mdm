<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Completeness\CompletenessMediaAssetLookupInterface;
use App\Pim\Completeness\CompletenessPhotoEligibilityResolver;
use App\Pim\Entity\CompletenessConfigurationAudit;
use App\Pim\Entity\CompletenessFieldConfiguration;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\CompletenessFormula;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\CompletenessConfigurationType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Uid\Ulid;

final class CompletenessHardeningTest extends KernelTestCase
{
    public function testOnlyAPhotoWithRightsAndAProcessedImageAssetIsEligible(): void
    {
        $eligibleId = (string) new Ulid();
        $documentId = (string) new Ulid();
        $withoutRightsId = (string) new Ulid();
        $lieu = new Lieu();
        $lieu->addRessource($this->resource($eligibleId, NatureRessource::Photo, true));
        $lieu->addRessource($this->resource($documentId, NatureRessource::Document, true));
        $lieu->addRessource($this->resource($withoutRightsId, NatureRessource::Photo, false));
        $lookup = new class([$eligibleId]) implements CompletenessMediaAssetLookupInterface {
            /** @param list<string> $eligible */
            public function __construct(private array $eligible) {}
            /** @var list<string> */
            public array $requested = [];
            public function processedImageIds(array $assetIds): array
            {
                $this->requested = $assetIds;

                return array_values(array_intersect($assetIds, $this->eligible));
            }
        };
        $resolver = new CompletenessPhotoEligibilityResolver($lookup);

        self::assertSame([], $lookup->requested);
        $result = $resolver->resolve([$lieu->fiche()->idString() => $lieu]);

        self::assertSame([$eligibleId], $lookup->requested);
        self::assertTrue($result[$lieu->fiche()->idString()]);
    }

    public function testTargetOverrideCannotExceedEntityLimit(): void
    {
        self::bootKernel();
        $form = self::getContainer()->get(FormFactoryInterface::class)->create(CompletenessConfigurationType::class, null, [
            'csrf_protection' => false,
            'default_target' => 100,
        ]);
        $form->submit([
            'formula' => CompletenessFormula::LengthRatio->value,
            'weight' => '1',
            'targetLengthOverride' => '101',
            'active' => '1',
            'marketplace' => '1',
            'thematicSites' => '1',
            'salesforce' => '1',
            'providerPortal' => '1',
        ]);

        self::assertFalse($form->isValid());
        self::assertStringContainsString('ne peut pas dépasser', (string) $form->getErrors(true));
    }

    public function testConfigurationAuditKeepsBeforeAfterActorAndRevision(): void
    {
        $configuration = new CompletenessFieldConfiguration(TypeFiche::Lieu, 'LABEL', 'Libellé');
        $before = $configuration->snapshot();
        $configuration->configure(CompletenessFormula::LengthRatio, 2.5, 60, true, true, false, true, false);
        $audit = new CompletenessConfigurationAudit(TypeFiche::Lieu, 'LABEL', 7, 'admin@example.test', 'admin', $before, $configuration->snapshot());

        self::assertSame(7, $audit->revision());
        self::assertSame('admin@example.test', $audit->actor());
        self::assertSame(1.0, $audit->before()['weight'] ?? null);
        self::assertSame(2.5, $audit->after()['weight'] ?? null);
        self::assertFalse($audit->after()['thematicSites'] ?? true);
    }

    private function resource(string $assetId, NatureRessource $nature, bool $rights): RessourceLieu
    {
        $resource = new RessourceLieu();
        $resource->changeDamAssetId($assetId);
        $resource->changeNature($nature);
        if ($rights) {
            $resource->grantRights((string) new Ulid());
        }

        return $resource;
    }
}
