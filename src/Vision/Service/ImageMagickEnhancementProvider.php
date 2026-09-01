<?php

declare(strict_types=1);

namespace App\Vision\Service;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Retouche locale déterministe : correction d'exposition, de contraste, de
 * saturation et de netteté par ImageMagick. Contrairement au moteur
 * génératif, aucun pixel n'est inventé — visages et textes restent
 * strictement ceux de l'original. Gratuit, sans dépendance réseau.
 */
final readonly class ImageMagickEnhancementProvider implements ImageEnhancementProviderInterface
{
    /** Description figée sur chaque retouche lancée, à la place d'un prompt. */
    public const DESCRIPTION = 'Correction automatique : orientation, contraste (étirement de l’histogramme + courbe en S), saturation ravivée (+13 %), netteté.';
    public const MODEL = 'imagemagick';

    public function enhance(string $imagePath, string $mimeType, string $prompt, string $model): EnhancedImageResult
    {
        if (!is_file($imagePath)) {
            throw new \InvalidArgumentException('La copie locale de l’image à retoucher est introuvable.');
        }
        // La sortie garde le format de l'original : convertir une photo JPEG
        // en PNG triplerait son poids sans gain.
        [$outputMime, $writer] = match ($mimeType) {
            'image/png' => ['image/png', 'png:'],
            'image/webp' => ['image/webp', 'webp:'],
            default => ['image/jpeg', 'jpg:'],
        };
        $output = tempnam(sys_get_temp_dir(), 'mdm-vision-magick-');
        if (false === $output) {
            throw new \RuntimeException('Impossible de créer le fichier temporaire de retouche.');
        }
        // Les opérations couleur sont synchronisées sur les trois canaux (un
        // étirement par canal décalerait les teintes) et il n'y a volontairement
        // pas d'auto-gamma : recentrer les tons moyens éteint les photos déjà
        // travaillées (rendu terne et froid constaté sur les photos pro).
        // Dosage arbitré sur photos réelles (pro déjà étalonnées + chaînes
        // hôtelières ternes) : un cran au-dessus a été jugé trop appuyé, un
        // cran en dessous invisible en avant/après.
        $operations = [
            '-auto-orient',
            '-channel', 'RGB,sync',
            '-contrast-stretch', '0.05%x0.05%',
            '+channel',
            '-sigmoidal-contrast', '3x50%',
            '-modulate', '101,113',
            '-unsharp', '0x0.9+0.7+0.008',
            '-quality', '92',
        ];
        try {
            // Mémoire ImageMagick bornée : un original démesuré ne doit pas
            // pouvoir mettre le worker en OOM.
            $process = new Process([
                'convert',
                '-limit', 'memory', '512MiB',
                '-limit', 'map', '1GiB',
                $imagePath,
                ...$operations,
                $writer.$output,
            ]);
            $process->setTimeout(120);
            try {
                $process->mustRun();
            } catch (ProcessFailedException $error) {
                $stderr = trim($process->getErrorOutput());
                throw new \DomainException('ImageMagick a échoué : '.mb_substr('' !== $stderr ? $stderr : $error->getMessage(), 0, 500), 0, $error);
            }
            $bytes = file_get_contents($output);
            if (false === $bytes || '' === $bytes) {
                throw new \DomainException('ImageMagick a produit une image vide.');
            }
        } finally {
            @unlink($output);
        }

        return new EnhancedImageResult($bytes, $outputMime, [
            'provider' => self::MODEL,
            'operations' => implode(' ', $operations),
        ]);
    }
}
