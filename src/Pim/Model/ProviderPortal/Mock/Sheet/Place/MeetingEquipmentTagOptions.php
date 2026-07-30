<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Place;

use App\Pim\Model\ProviderPortal\Form\Tag\TagOption;

class MeetingEquipmentTagOptions
{
    /**
     * @return array<TagOption>
     */
    public static function getTagOptions(): array
    {
        return [
            new TagOption('wi-fi-gratuit', 'Wi-fi Gratuit', 'wifi'),
            new TagOption('connexion-internet-filaire', 'Connexion internet filaire', 'git-branch'),
            new TagOption('paper-board', 'Paper Board', 'presentation'),
            new TagOption('video-projecteur-tv', 'Vidéo-projecteur / TV', 'television'),
            new TagOption('technicien-av-sur-place', 'Technicien AV sur place', 'technicien'),
            new TagOption('ecran-lcd', 'Ecran LCD', 'desktop'),
            new TagOption('micro', 'Micro', 'microphone'),
            new TagOption('sonorisation', 'Sonorisation', 'speaker'),
            new TagOption('blocs-notes-stylo', 'Blocs-notes & stylo', 'clipboard'),
            new TagOption('climatisation-salles', 'Climatisation Salles', 'wind'),
            new TagOption('cabine-de-traduction', 'Cabine de traduction', 'trad'),
            new TagOption('visio-conference', 'Visio conférence', 'video-conference'),
            new TagOption('click-share', 'Click share', 'airplay'),
            new TagOption('fontaines-a-eau', 'Fontaines à eau', 'drop'),
        ];
    }
}
