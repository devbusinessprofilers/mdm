<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Wysiwyg
{
    public string $id;

    public string $name;

    public bool $disabled = false;

    public bool $readonly = false;

    /**
     * Allows to set max height of WYSIWYG (0 to ignore).
     */
    public int $maxHeight = 0;

    /**
     * Allows to set max character length of WYSIWYG (0 to ignore).
     */
    public int $maxLength = 0;

    /**
     * Longueur de référence du compteur « x / N » affiché sous l'éditeur
     * (0 = pas de compteur). Indicatif : ne bloque pas la saisie.
     */
    public int $compteur = 0;
}
