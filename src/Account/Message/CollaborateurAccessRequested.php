<?php

declare(strict_types=1);

namespace App\Account\Message;

final readonly class CollaborateurAccessRequested
{
    public function __construct(
        public string $collaborateurId,
        public string $ficheId,
        public string $emailBody = '',
    ) {
    }
}
