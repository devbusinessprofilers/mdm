<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Enum\TypeFiche;
use Symfony\Component\Form\FormInterface;

/**
 * Complète la requête d'une soumission partielle (clearMissing = false) avec
 * les champs qu'un navigateur n'envoie jamais quand ils sont vides : case
 * décochée, liste multiple sans sélection, collection sans ligne. Laissés
 * absents, ces champs seraient ignorés par le formulaire et la suppression
 * perdue en silence (« Fiche enregistrée » sans effet).
 *
 * Seuls les champs que l'écran rend réellement — ceux du catalogue des
 * sections, feuilles pointées comprises — sont complétés : un champ du
 * formulaire hors écran (ressources, sites de diffusion…) reste intact.
 */
final class ChampsOmisCompleteur
{
    /** Marque « clé absente de la requête », distincte d'une valeur null soumise. */
    private const ABSENT = "\0absent";

    /**
     * @param FormInterface<mixed>  $form
     * @param array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function completer(FormInterface $form, array $data, TypeFiche $type): array
    {
        foreach (FicheSectionsCatalogue::pour($type) as $section) {
            foreach ($section['champs'] as $chemin) {
                $data = self::completerChemin($form, explode('.', $chemin), $data);
            }
        }

        return $data;
    }

    /**
     * Descend un chemin pointé (`groupe.champ`) jusqu'au champ rendu, puis
     * complète ce champ et, en remontant, réinjecte le résultat dans les
     * tableaux intermédiaires (créés s'ils manquaient).
     *
     * @param FormInterface<mixed> $parent
     * @param list<string>         $segments
     * @param array<string, mixed> $donnees
     * @return array<string, mixed>
     */
    private static function completerChemin(FormInterface $parent, array $segments, array $donnees): array
    {
        $nom = array_shift($segments);
        if (null === $nom || !$parent->has($nom)) {
            return $donnees;
        }
        $champ = $parent->get($nom);
        $recu = array_key_exists($nom, $donnees) ? $donnees[$nom] : self::ABSENT;

        if ([] === $segments) {
            $valeur = self::completerChamp($champ, $recu);
        } else {
            $valeur = self::completerChemin($champ, $segments, is_array($recu) ? $recu : []);
            // Rien injecté dans un groupe absent : le laisser absent.
            if ([] === $valeur && self::ABSENT === $recu) {
                $valeur = self::ABSENT;
            }
        }
        if (self::ABSENT !== $valeur) {
            $donnees[$nom] = $valeur;
        }

        return $donnees;
    }

    /**
     * Valeur à soumettre pour un champ : null explicite pour un champ
     * omissible absent, récursion dans les groupes et les lignes de
     * collection, la valeur reçue sinon (ABSENT si rien à ajouter).
     *
     * @param FormInterface<mixed> $champ
     */
    private static function completerChamp(FormInterface $champ, mixed $recu): mixed
    {
        $config = $champ->getConfig();
        $prefixe = self::prefixeRacine($champ);
        // Un choix étendu simple (radios Oui/Non) sans bouton coché n'est pas
        // envoyé non plus : soumis null, il retombe sur sa valeur par défaut.
        $omissible = 'checkbox' === $prefixe
            || ('choice' === $prefixe && (true === $config->getOption('multiple') || true === $config->getOption('expanded')))
            || 'collection' === $prefixe;
        if (self::ABSENT === $recu) {
            return $omissible ? null : self::completerGroupe($champ, self::ABSENT);
        }
        if ('collection' === $prefixe) {
            // Les lignes ajoutées côté client n'existent pas encore dans le
            // formulaire (ResizeFormListener les crée à la soumission) : le
            // prototype porte la forme commune de toutes les lignes.
            $prototype = $config->getAttribute('prototype');
            if (!is_array($recu) || !$prototype instanceof FormInterface) {
                return $recu;
            }
            foreach ($recu as $cle => $ligne) {
                if (is_array($ligne)) {
                    $recu[$cle] = self::completerGroupe($prototype, $ligne);
                }
            }

            return $recu;
        }

        return self::completerGroupe($champ, $recu);
    }

    /**
     * Préfixe de bloc du type de base (checkbox, choice, collection…) : un
     * type dérivé (OuiNonType → ChoiceType) est traité comme son parent.
     *
     * @param FormInterface<mixed> $champ
     */
    private static function prefixeRacine(FormInterface $champ): string
    {
        $type = $champ->getConfig()->getType();
        $prefixe = $type->getBlockPrefix();
        while (null !== $type) {
            if (in_array($type->getBlockPrefix(), ['checkbox', 'choice', 'collection'], true)) {
                return $type->getBlockPrefix();
            }
            $type = $type->getParent();
        }

        return $prefixe;
    }

    /**
     * Complète les enfants d'un champ composé (groupe inherit_data, ligne de
     * collection, sous-formulaire) ; un champ simple rend sa valeur telle quelle.
     *
     * @param FormInterface<mixed> $groupe
     */
    private static function completerGroupe(FormInterface $groupe, mixed $recu): mixed
    {
        $config = $groupe->getConfig();
        if (!$config->getCompound() || 'choice' === self::prefixeRacine($groupe)) {
            return $recu;
        }
        $donnees = is_array($recu) ? $recu : [];
        $ajoute = false;
        foreach ($groupe as $nom => $enfant) {
            $valeurRecue = array_key_exists($nom, $donnees) ? $donnees[$nom] : self::ABSENT;
            $valeur = self::completerChamp($enfant, $valeurRecue);
            if (self::ABSENT === $valeur) {
                continue;
            }
            $ajoute = $ajoute || self::ABSENT === $valeurRecue;
            $donnees[$nom] = $valeur;
        }
        if (!is_array($recu) && !$ajoute) {
            return $recu;
        }

        return $donnees;
    }
}
