<?php

declare(strict_types=1);

namespace App\Vision\Message;

/**
 * Lancement en masse de la reconnaissance IA sur les photos sans mots-clés.
 * Le handler traite une vague puis se re-poste tant qu'il reste des photos :
 * un seul clic couvre tout le stock, en tâche de fond.
 */
final readonly class LancerRecoEnMasse
{
    public function __construct(public string $actor)
    {
    }
}
