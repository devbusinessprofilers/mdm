<?php

declare(strict_types=1);

namespace App\Etl\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\LieuRepository;

/**
 * Génère le CSV « Salles » de l'intégration Salesforce (une ligne par salle de
 * réunion d'un lieu). En-tête et format repris à l'identique de l'ancien
 * export extranet.
 *
 * ⚠️ ID_SALLE : Salesforce apparie les salles sur cet identifiant. Le PIM ne
 * conserve PAS l'ancien identifiant numérique de salle (les Salle sont créées
 * avec un ULID à l'import legacy). En attendant la reprise des « id de
 * liaison » (à arbitrer avant la migration prod), on émet l'ULID de la salle.
 * AUCUN envoi Salles réel ne doit partir avant que ce mapping soit tranché
 * (la synchro est de toute façon désactivée par défaut).
 */
final readonly class SalesforceSallesCsvExporter
{
    /** @var list<string> En-tête exact du fichier export_sales_force_salles.csv */
    public const ENTETES = [
        'ID_SALLE', 'ID_PRODUCT', 'S_SALLE_NAME', 'S_SALLE_PAVE', 'S_SALLE_U', 'S_SALLE_OVALE',
        'S_SALLE_ECOLE', 'S_SALLE_THEATRE', 'S_SALLE_CABARET', 'S_SALLE_COCKTAIL', 'S_SALLE_LONGUEUR',
        'S_SALLE_LARGEUR', 'S_SALLE_HAUTEUR', 'S_SALLE_HAUTEUR_SOUS_PLAFOND', 'B_SALLE_LUMIERE_JOUR',
        'B_SALLE_TERRASSE', 'S_SALLE_M2_CAPACITE', 'S_SALLE_COCKTAIL_DINATOIRE', 'S_SALLE_BUFFET_DINATOIRE',
        'S_SALLE_DINER_ASSIS', 'S_SALLE_SOIREE_DANSANTE', 'S_SALLE_CONFERENCE',
    ];

    public function __construct(private LieuRepository $lieux)
    {
    }

    /**
     * @param iterable<Fiche> $fiches
     */
    public function csv(iterable $fiches): string
    {
        return SalesforceCsvBuilder::build(self::ENTETES, $this->lignes($fiches));
    }

    public function possedeDesSalles(Fiche $fiche): bool
    {
        if (TypeFiche::Lieu !== $fiche->type()) {
            return false;
        }
        $lieu = $this->lieux->find($fiche->id());

        return null !== $lieu && !$lieu->salles()->isEmpty();
    }

    /**
     * @param iterable<Fiche> $fiches
     *
     * @return iterable<list<string>>
     */
    private function lignes(iterable $fiches): iterable
    {
        foreach ($fiches as $fiche) {
            if (TypeFiche::Lieu !== $fiche->type()) {
                continue;
            }
            $lieu = $this->lieux->find($fiche->id());
            if (null === $lieu) {
                continue;
            }
            $code = (string) $fiche->code();
            foreach ($lieu->salles() as $salle) {
                yield $this->ligne($code, $salle);
            }
        }
    }

    /** @return list<string> */
    private function ligne(string $code, Salle $salle): array
    {
        return [
            $salle->id(),                       // ID_SALLE (ULID intérimaire, voir docblock)
            $code,                              // ID_PRODUCT
            $salle->nom(),                      // S_SALLE_NAME
            self::i($salle->capaciteReunion()), // S_SALLE_PAVE (réunion/table)
            self::i($salle->capaciteU()),       // S_SALLE_U
            '',                                 // S_SALLE_OVALE (pas d'équivalent PIM)
            self::i($salle->capaciteClasse()),  // S_SALLE_ECOLE (classe)
            self::i($salle->capaciteTheatre()), // S_SALLE_THEATRE
            self::i($salle->capaciteCabaret()), // S_SALLE_CABARET
            self::i($salle->capaciteCocktail()), // S_SALLE_COCKTAIL
            '',                                 // S_SALLE_LONGUEUR (pas d'équivalent PIM)
            '',                                 // S_SALLE_LARGEUR
            '',                                 // S_SALLE_HAUTEUR
            '',                                 // S_SALLE_HAUTEUR_SOUS_PLAFOND
            $salle->lumiereJour() ? '1' : '0',  // B_SALLE_LUMIERE_JOUR
            '0',                                // B_SALLE_TERRASSE (pas d'équivalent PIM)
            self::i($salle->superficie()),      // S_SALLE_M2_CAPACITE
            '',                                 // S_SALLE_COCKTAIL_DINATOIRE
            '',                                 // S_SALLE_BUFFET_DINATOIRE
            '',                                 // S_SALLE_DINER_ASSIS
            '',                                 // S_SALLE_SOIREE_DANSANTE
            '',                                 // S_SALLE_CONFERENCE
        ];
    }

    private static function i(?int $value): string
    {
        return null === $value ? '' : (string) $value;
    }
}
