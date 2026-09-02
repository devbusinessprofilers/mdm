<?php

declare(strict_types=1);

namespace App\GoLive;

enum EtapeStatut: string
{
    case Fait = 'fait';
    case AFaire = 'à faire';
    case Bloquee = 'bloqué';
    case NonConfiguree = 'non configuré';
    case Manuelle = 'manuel';
}
