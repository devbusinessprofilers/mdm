<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Etl\Service\ImportPublicationPolicy;
use App\Pim\Enum\TypeFiche;
use App\Pim\Import\Legacy\LegacyPhotoCatalog;
use App\Pim\Service\PhotoObligations;
use App\Tests\Support\ParametresFixes;
use PHPUnit\Framework\TestCase;

final class ImportPublicationPolicyTest extends TestCase
{
    private ImportPublicationPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new ImportPublicationPolicy(new LegacyPhotoCatalog(), new PhotoObligations(new ParametresFixes([
            'photos.min_lieu' => '4',
            'photos.max_lieu' => '25',
            'photos.min_autres' => '1',
            'photos.max_autres' => '10',
        ])));
    }

    public function testLieuNeedsFourPhotosAndAPrincipale(): void
    {
        $quatre = '{"master":["a.jpg"],"chambre":["b.jpg","c.jpg","d.jpg"]}';
        self::assertTrue($this->policy->allowsPublication(TypeFiche::Lieu, 'Hôtel', $quatre));

        $trois = '{"master":["a.jpg"],"chambre":["b.jpg","c.jpg"]}';
        self::assertFalse($this->policy->allowsPublication(TypeFiche::Lieu, 'Hôtel', $trois));

        $sansPrincipale = '{"chambre":["a.jpg","b.jpg","c.jpg","d.jpg"]}';
        self::assertFalse($this->policy->allowsPublication(TypeFiche::Lieu, 'Hôtel', $sansPrincipale));
    }

    public function testEmptyPhotosJsonBlocksPublication(): void
    {
        self::assertFalse($this->policy->allowsPublication(TypeFiche::Lieu, 'Lieu', null));
        self::assertFalse($this->policy->allowsPublication(TypeFiche::Restaurant, 'Restaurant', ''));
    }

    public function testFirstPhotoCountsAsPrincipaleForFicheGammes(): void
    {
        // Pas de catégorie master : la première photo est promue principale.
        self::assertTrue($this->policy->allowsPublication(TypeFiche::Activite, 'Idée', '{"divers":["a.jpg"]}'));
        self::assertTrue($this->policy->allowsPublication(TypeFiche::ServiceEvenementiel, 'Prestataires de service', '{"divers":["a.jpg"]}'));
        self::assertTrue($this->policy->allowsPublication(TypeFiche::Restaurant, 'Restaurant', '{"divers":["a.jpg"]}'));
        self::assertTrue($this->policy->allowsPublication(TypeFiche::Restaurant, 'Restaurant', '{"master":["a.jpg"]}'));
    }
}
