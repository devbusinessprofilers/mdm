<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Activity;

use App\Pim\Model\ProviderPortal\Form\Tag\TagOption;

class FunTagOptions
{
    /**
     * @return array<TagOption>
     */
    public static function getTagOptions(): array
    {
        return [
            new TagOption('olympiades', 'Olympiades'),
            new TagOption('parc-aventure-accrobranche', 'Parc aventure / Accrobranche'),
            new TagOption('escalade-interieure-ou-exterieure', 'Escalade (intérieure ou extérieure)'),
            new TagOption('laser-game-paintball', 'Laser game / Paintball'),
            new TagOption('sports-collectifs-foot-volley-etc', 'Sports collectifs (foot, volley, etc.)'),
            new TagOption('equitation', 'Équitation'),
            new TagOption('survie-bootcamp', 'Survie & Bootcamp'),
            new TagOption('jeu-d-adresse', 'Jeu d\'adresse'),
            new TagOption('montagne-neige', 'Montagne & neige'),
            new TagOption('chasse-au-tresor', 'Chasse au trésor'),
        ];
    }
}
