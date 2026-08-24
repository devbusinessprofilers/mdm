<?php

declare(strict_types=1);

namespace App\Etl\Service;

/**
 * Écrit un CSV au format de l'ancienne intégration Salesforce : séparateur
 * virgule, tous les champs entourés de guillemets doubles (guillemets internes
 * échappés en `""`), UTF-8, enregistrements séparés par CRLF. Les sauts de
 * ligne à l'intérieur d'un champ (descriptions) restent protégés par les
 * guillemets, conformément à RFC 4180.
 */
final class SalesforceCsvBuilder
{
    /**
     * @param list<string>       $entetes
     * @param iterable<list<string>> $lignes chaque ligne alignée sur $entetes
     */
    public static function build(array $entetes, iterable $lignes): string
    {
        $csv = self::ligne($entetes);
        foreach ($lignes as $ligne) {
            $csv .= self::ligne($ligne);
        }

        return $csv;
    }

    /** @param list<string> $champs */
    private static function ligne(array $champs): string
    {
        $quotes = array_map(
            static fn (string $champ): string => '"'.str_replace('"', '""', $champ).'"',
            $champs,
        );

        return implode(',', $quotes)."\r\n";
    }
}
