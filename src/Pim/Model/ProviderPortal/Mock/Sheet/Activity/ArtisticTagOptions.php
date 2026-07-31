<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Activity;

use App\Pim\Model\ProviderPortal\Form\Tag\TagOption;

class ArtisticTagOptions
{
    /**
     * @return array<TagOption>
     */
    public static function getTagOptions(): array
    {
        return [
            new TagOption('atelier-peinture-sculpture', 'Atelier peinture & sculpture'),
            new TagOption('atelier-mosaique-ceramique', 'Atelier mosaïque / céramique'),
            new TagOption('atelier-parfum-cosmetique', 'Atelier parfum / cosmétique'),
            new TagOption('theatre-improvisation-prise-de-parole', 'Théâtre, improvisation & prise de parole'),
            new TagOption('musique-danse', 'Musique & danse'),
            new TagOption('atelier-ecriture-storytelling', 'Atelier écriture / storytelling'),
            new TagOption('cinema', 'Cinéma'),
            new TagOption('atelier-artisanal', 'Atelier artisanal'),
            new TagOption('atelier-bijoux', 'Atelier bijoux'),
            new TagOption('photo', 'Photo'),
        ];
    }
}
