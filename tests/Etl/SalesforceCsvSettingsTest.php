<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Etl\Service\SalesforceCsvSettings;
use App\Tests\Support\ParametresFixes;
use PHPUnit\Framework\TestCase;

final class SalesforceCsvSettingsTest extends TestCase
{
    public function testNotConfiguredWhenDisabled(): void
    {
        $settings = $this->settings(['salesforce.csv_actif' => '0', 'salesforce.csv_destinataire' => 'sf@x.fr']);

        self::assertFalse($settings->isConfigured());
    }

    public function testNotConfiguredWhenRecipientEmpty(): void
    {
        $settings = $this->settings(['salesforce.csv_actif' => '1', 'salesforce.csv_destinataire' => '']);

        self::assertFalse($settings->isConfigured());
    }

    public function testConfiguredWhenEnabledWithRecipient(): void
    {
        $settings = $this->settings(['salesforce.csv_actif' => '1', 'salesforce.csv_destinataire' => 'sf@x.fr']);

        self::assertTrue($settings->isConfigured());
        self::assertSame('sf@x.fr', $settings->destinataire());
        self::assertSame('a0qw0000004TJbX', $settings->jetonIntegration());
    }

    public function testExpediteurFallsBackToDefaultWhenParamEmpty(): void
    {
        $settings = $this->settings(['salesforce.csv_expediteur' => '']);

        self::assertSame('defaut@bp.fr', $settings->expediteur());
    }

    public function testExpediteurOverridesDefault(): void
    {
        $settings = $this->settings(['salesforce.csv_expediteur' => 'custom@bp.fr']);

        self::assertSame('custom@bp.fr', $settings->expediteur());
    }

    /** @param array<string, string> $surcharges */
    private function settings(array $surcharges): SalesforceCsvSettings
    {
        return new SalesforceCsvSettings(
            new ParametresFixes($surcharges + [
                'salesforce.csv_actif' => '0',
                'salesforce.csv_destinataire' => '',
                'salesforce.csv_expediteur' => '',
                'salesforce.csv_token' => 'a0qw0000004TJbX',
            ]),
            'defaut@bp.fr',
        );
    }
}
