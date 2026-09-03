<?php

declare(strict_types=1);

namespace App\Pim\Service;

/**
 * Issue d'une soumission de section de l'éditeur de fiche : enregistrée
 * (éventuellement avec dépublication), invalide (erreurs de formulaire),
 * non soumise (aucune donnée), ou en attente de confirmation — une fiche
 * publiée dont un champ obligatoire vient d'être vidé n'est enregistrée
 * qu'après l'accord explicite de l'utilisateur (modale de l'éditeur).
 */
final readonly class SoumissionSection
{
    /**
     * @param list<string> $champsVides Libellés des champs obligatoires désormais vides
     */
    private function __construct(
        private bool $enregistree,
        private bool $confirmationRequise,
        public bool $depubliee,
        public array $champsVides,
    ) {
    }

    public static function nonSoumise(): self
    {
        return new self(false, false, false, []);
    }

    public static function invalide(): self
    {
        return new self(false, false, false, []);
    }

    /** @param list<string> $champsVides */
    public static function confirmationRequise(array $champsVides): self
    {
        return new self(false, true, false, $champsVides);
    }

    public static function enregistree(): self
    {
        return new self(true, false, false, []);
    }

    /** @param list<string> $champsVides Champs dont le vidage a été confirmé : enregistrée et dépubliée */
    public static function depubliee(array $champsVides): self
    {
        return new self(true, false, true, $champsVides);
    }

    public function estEnregistree(): bool
    {
        return $this->enregistree;
    }

    public function attendConfirmation(): bool
    {
        return $this->confirmationRequise;
    }
}
