<?php

declare(strict_types=1);

namespace App\Shared\Text;

/**
 * Conversion entre le texte brut stocké dans les fiches et le HTML manipulé par
 * l'éditeur TinyMCE. Le PIM ne conserve que du texte : tous les consommateurs
 * (marketplace en nl2br, CSV Salesforce, traductions, compteurs de complétude,
 * détection de doublons, index de recherche) raisonnent sur des caractères, pas
 * sur des balises. Convention de mise en forme du texte brut :
 * - paragraphes séparés par une ligne vide, retour à la ligne simple à
 *   l'intérieur d'un paragraphe ;
 * - puces = lignes commençant par « - ».
 */
final class TexteBrut
{
    private const PUCE = '- ';

    /**
     * Texte brut depuis un HTML d'éditeur ou un collage sauvage (balises,
     * entités nommées, commentaires, caractères invisibles). Un texte sans
     * balise ni entité est rendu tel quel, seulement épuré de ses blancs.
     */
    public static function depuisHtml(string $html): string
    {
        if (self::ressembleAuHtml($html)) {
            $html = (string) preg_replace('/<!--.*?-->/s', '', $html);
            $html = (string) preg_replace('/<(script|style)\b[^>]*>.*?<\/\1\s*>/is', '', $html);
            // Les blancs du source autour des balises de bloc ne sont pas du texte.
            $html = (string) preg_replace('/\s*(<\/?(?:p|div|ul|ol|li|table|tbody|thead|tr|td|th|dl|dd|dt|blockquote|h[1-6]|br)\b[^>]*>)\s*/i', '$1', $html);
            $html = (string) preg_replace('/<br\s*\/?>/i', "\n", $html);
            $html = (string) preg_replace('/<(?:p|div|table|tr|ul|ol|dl|blockquote|h[1-6])\b[^>]*>/i', "\n", $html);
            $html = (string) preg_replace('/<(?:td|th)\b[^>]*>/i', ' ', $html);
            $html = (string) preg_replace('/<li\b[^>]*>/i', "\n".self::PUCE, $html);
            $html = (string) preg_replace('/<\/(p|div|ul|ol|table|blockquote|h[1-6])\s*>/i', "\n\n", $html);
            $html = (string) preg_replace('/<\/(tr|dd|dt)\s*>/i', "\n", $html);
            $html = (string) preg_replace('/<\/?[a-zA-Z][^>]*>/', '', $html);
            // Balise finale jamais fermée (collage tronqué par la taille du champ).
            $html = (string) preg_replace('/<\/?[a-zA-Z][^<>]*$/', '', $html);
            $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return self::epurer($html);
    }

    /** HTML de paragraphes et de listes pour l'éditeur, depuis le texte brut. */
    public static function versHtml(string $texte): string
    {
        $texte = self::epurer($texte);
        if ('' === $texte) {
            return '';
        }

        $blocs = [];
        foreach (preg_split('/\n{2,}/', $texte) ?: [] as $paragraphe) {
            $courant = [];
            $liste = [];
            foreach (explode("\n", $paragraphe) as $ligne) {
                if (str_starts_with($ligne, self::PUCE)) {
                    self::viderParagraphe($blocs, $courant);
                    $liste[] = self::echapper(mb_substr($ligne, mb_strlen(self::PUCE)));
                    continue;
                }
                self::viderListe($blocs, $liste);
                $courant[] = self::echapper($ligne);
            }
            self::viderParagraphe($blocs, $courant);
            self::viderListe($blocs, $liste);
        }

        return implode('', $blocs);
    }

    /**
     * @param list<string> $blocs
     * @param list<string> $lignes
     */
    private static function viderParagraphe(array &$blocs, array &$lignes): void
    {
        if ([] !== $lignes) {
            $blocs[] = '<p>'.implode('<br>', $lignes).'</p>';
            $lignes = [];
        }
    }

    /**
     * @param list<string> $blocs
     * @param list<string> $items
     */
    private static function viderListe(array &$blocs, array &$items): void
    {
        if ([] !== $items) {
            $blocs[] = '<ul>'.implode('', array_map(static fn (string $l): string => '<li>'.$l.'</li>', $items)).'</ul>';
            $items = [];
        }
    }

    private static function echapper(string $texte): string
    {
        return htmlspecialchars($texte, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function ressembleAuHtml(string $texte): bool
    {
        return 1 === preg_match('/<\/?[a-zA-Z][^>]*>|<!--|&(#\d+|#x[0-9a-fA-F]+|[a-zA-Z][a-zA-Z0-9]*);/', $texte);
    }

    /** Blancs normalisés : lignes rognées, espaces multiples fusionnés, au plus une ligne vide. */
    private static function epurer(string $texte): string
    {
        $texte = str_replace(["\r\n", "\r"], "\n", $texte);
        // Caractères invisibles (espaces sans chasse, joints, BOM) et espaces insécables.
        $texte = (string) preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $texte);
        $texte = (string) preg_replace('/[\x{00A0}\x{202F}\t]/u', ' ', $texte);
        $texte = (string) preg_replace('/ {2,}/', ' ', $texte);
        $lignes = array_map(static fn (string $l): string => trim($l), explode("\n", $texte));
        $texte = implode("\n", $lignes);
        $texte = (string) preg_replace('/\n{3,}/', "\n\n", $texte);

        return trim($texte);
    }
}
