<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Service\LieuPhotoManager;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Le rognage ne doit pas produire une image sous les minima d'upload
 * (dam.image_largeur_min × dam.image_hauteur_min) : la modale de recadrage
 * l'empêche côté client, le manager le garantit côté serveur.
 */
#[Group('database')]
final class LieuPhotoManagerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
    }

    public function testUnCropSousLePlancherEstRefuse(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get(LieuPhotoManager::class);

        $lieu = new Lieu();
        $lieu->changeLabel('Manoir du plancher');
        $resource = new RessourceLieu();
        $resource->changeDamAssetId('asset-plancher');
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage('PHOTO_DIVERSE');
        $lieu->addRessource($resource);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/au moins \d+ px/');
        // Les minima admissibles vont de 100×50 px vers le haut : 10×10 est
        // toujours sous le plancher, quelle que soit la configuration.
        $manager->update($resource, $lieu, [
            'usage' => 'PHOTO_DIVERSE',
            'crop_x' => '0', 'crop_y' => '0', 'crop_width' => '10', 'crop_height' => '10',
            'rotation' => '0',
        ], 'test-actor');
    }
}
