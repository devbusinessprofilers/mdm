<?php

declare(strict_types=1);

namespace App\Etl\Enum;

/**
 * Les deux interfaces de l'intégration Salesforce historique, chacune avec son
 * fichier CSV et le suffixe d'objet d'e-mail attendu par Salesforce
 * (`integration=<jeton>;interface=<valeur>`).
 */
enum SalesforceCsvInterface: string
{
    case Produits = 'Produits';
    case Salles = 'Salles';

    /** Nom exact de la pièce jointe attendu par l'intégration Salesforce. */
    public function nomFichier(): string
    {
        return match ($this) {
            self::Produits => 'export_sales_force_products.csv',
            self::Salles => 'export_sales_force_salles.csv',
        };
    }
}
