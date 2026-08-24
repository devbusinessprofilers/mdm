<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Etl\Enum\SalesforceCsvInterface;
use App\Etl\Service\SalesforceCsvMailer;
use App\Etl\Service\SalesforceCsvSettings;
use App\Tests\Support\ParametresFixes;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class SalesforceCsvMailerTest extends TestCase
{
    public function testDisabledSyncSendsNothing(): void
    {
        $mailer = $this->capturingMailer();
        $sfMailer = new SalesforceCsvMailer($mailer, $this->settings(actif: false), new NullLogger());

        $sfMailer->envoyer(SalesforceCsvInterface::Produits, "\"A\"\r\n");

        self::assertNull($mailer->dernier);
    }

    public function testProduitsEmailUsesExactSubjectRecipientAndAttachment(): void
    {
        $mailer = $this->capturingMailer();
        $sfMailer = new SalesforceCsvMailer($mailer, $this->settings(actif: true), new NullLogger());

        $sfMailer->envoyer(SalesforceCsvInterface::Produits, "\"ID_PRODUCT\"\r\n\"207\"\r\n");

        $email = $mailer->dernier;
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('integration=a0qw0000004TJbX;interface=Produits', $email->getSubject());
        self::assertSame('sf@salesforce.example', $email->getTo()[0]->getAddress());
        self::assertSame('expediteur@bp.fr', $email->getFrom()[0]->getAddress());
        $attachment = $email->getAttachments()[0];
        self::assertSame("\"ID_PRODUCT\"\r\n\"207\"\r\n", $attachment->getBody());
    }

    public function testSallesSubjectMatchesInterface(): void
    {
        $mailer = $this->capturingMailer();
        $sfMailer = new SalesforceCsvMailer($mailer, $this->settings(actif: true), new NullLogger());

        $sfMailer->envoyer(SalesforceCsvInterface::Salles, "\"ID_SALLE\"\r\n");

        $email = $mailer->dernier;
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('integration=a0qw0000004TJbX;interface=Salles', $email->getSubject());
    }

    private function settings(bool $actif): SalesforceCsvSettings
    {
        return new SalesforceCsvSettings(
            new ParametresFixes([
                'salesforce.csv_actif' => $actif ? '1' : '0',
                'salesforce.csv_destinataire' => 'sf@salesforce.example',
                'salesforce.csv_expediteur' => 'expediteur@bp.fr',
                'salesforce.csv_token' => 'a0qw0000004TJbX',
            ]),
            'defaut@bp.fr',
        );
    }

    private function capturingMailer(): CapturingMailer
    {
        return new CapturingMailer();
    }
}

/** Double de test capturant le dernier message envoyé. */
final class CapturingMailer implements MailerInterface
{
    public ?RawMessage $dernier = null;

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        $this->dernier = $message;
    }
}
