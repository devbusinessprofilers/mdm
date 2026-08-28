<?php

declare(strict_types=1);

namespace App\Pim\Validation;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class LienVideo extends Constraint
{
    /**
     * Les dix hébergeurs vidéo autorisés et leurs domaines
     * (sous-domaines acceptés : www.youtube.com, player.vimeo.com…).
     *
     * @var array<string, list<string>>
     */
    public const HEBERGEURS = [
        'YouTube' => ['youtube.com', 'youtu.be'],
        'Vimeo' => ['vimeo.com'],
        'Dailymotion' => ['dailymotion.com', 'dai.ly'],
        'TikTok' => ['tiktok.com'],
        'Instagram' => ['instagram.com'],
        'Facebook' => ['facebook.com', 'fb.watch'],
        'Twitch' => ['twitch.tv'],
        'Wistia' => ['wistia.com'],
        'Vidyard' => ['vidyard.com'],
        'Loom' => ['loom.com'],
    ];

    public string $message = 'Le lien vidéo doit pointer vers un hébergeur vidéo reconnu : {{ hebergeurs }}.';
}
