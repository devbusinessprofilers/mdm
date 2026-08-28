<?php

namespace App\Pim\Twig\Components\Media;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class CropModal
{
    public string $identifier;

    /** Route de redirection vers l'original présigné, chargée à l'ouverture. */
    public string $originalUrl;

    /** Nom du formulaire de métadonnées dont les champs cachés reçoivent le crop. */
    public string $formName;

    /** Dimensions minimales de la zone recadrée, en pixels réels de l'image. */
    public int $minWidth;

    public int $minHeight;

    public float $scaleStep = 0.1;

    /** 1 = pas de dézoom sous le cadrage initial « contain ». */
    public float $minScale = 1;

    public float $maxScale = 3;
}
