<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Activity;

use App\Pim\Model\ProviderPortal\Form\Tag\TagOption;

class CulturalTagOptions
{
    /**
     * @return array<TagOption>
     */
    public static function getTagOptions(): array
    {
        return [
            new TagOption('visite-culturelle-musee', 'Visite culturelle / musée'),
            new TagOption('jeu-de-piste-rallye-urbain', 'Jeu de piste / rallye urbain'),
            new TagOption('escape-game', 'Escape game'),
            new TagOption('murder-party', 'Murder party'),
            new TagOption('quiz-jeux-de-role', 'Quiz & jeux de rôle'),
            new TagOption('atelier-ecriture-storytelling', 'Atelier écriture / storytelling'),
            new TagOption('karaoke', 'Karaoké'),
            new TagOption('casino', 'Casino'),
            new TagOption('magie-mentaliste-hypnose', 'Magie / Mentaliste / hypnose'),
            new TagOption('action-game', 'Action game'),
            new TagOption('simulateurs', 'Simulateurs'),
            new TagOption('visites-guidees', 'Visites guidées'),
        ];
    }
}
