<?php

declare(strict_types=1);

namespace App\Tests\GoLive;

use App\GoLive\Etape;
use App\GoLive\EtapeEtat;
use App\GoLive\EtapeStatut;
use PHPUnit\Framework\TestCase;

final class EtapeTest extends TestCase
{
    public function testUneEtapeSansExecutionEstManuelle(): void
    {
        $etape = new Etape('id', 'Label', static fn (): EtapeEtat => new EtapeEtat(EtapeStatut::AFaire));

        self::assertTrue($etape->manuelle());
        self::assertFalse($etape->executer(new RecordingSousCommandeRunner()));
    }

    public function testVerifierDelegueALaVerification(): void
    {
        $etape = new Etape('id', 'Label', static fn (): EtapeEtat => new EtapeEtat(EtapeStatut::Fait, '42 lignes'));

        $etat = $etape->verifier();

        self::assertSame(EtapeStatut::Fait, $etat->statut);
        self::assertSame('42 lignes', $etat->detail);
    }

    public function testExecuterPropageLeResultatEtLeRunner(): void
    {
        $runner = new RecordingSousCommandeRunner();
        $etape = new Etape(
            'id',
            'Label',
            static fn (): EtapeEtat => new EtapeEtat(EtapeStatut::AFaire),
            static fn ($r): bool => 0 === $r->run('app:demo', ['--x' => true]),
        );

        self::assertFalse($etape->manuelle());
        self::assertTrue($etape->executer($runner));
        self::assertSame([['commande' => 'app:demo', 'parametres' => ['--x' => true]]], $runner->appels);

        $runner->codesRetour['app:demo'] = 1;
        self::assertFalse($etape->executer($runner));
    }

    public function testToujoursExecuterEstFauxParDefaut(): void
    {
        $etape = new Etape('id', 'Label', static fn (): EtapeEtat => new EtapeEtat(EtapeStatut::AFaire));

        self::assertFalse($etape->toujoursExecuter);
    }
}
