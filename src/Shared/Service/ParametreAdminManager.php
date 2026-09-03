<?php

declare(strict_types=1);

namespace App\Shared\Service;

use App\Shared\Entity\Parametre;
use App\Shared\Enum\TypeParametre;
use App\Shared\Repository\ParametreRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Orchestration des écrans d'administration des paramètres : présentation des
 * valeurs effectives, cast des saisies, persistence et invalidation du cache
 * de lecture.
 */
final readonly class ParametreAdminManager
{
    /**
     * Bornes de saisie des paramètres entiers : garde-fous contre les valeurs
     * absurdes (0 photo, purge immédiate…), pas des règles métier.
     *
     * @var array<string, array{min: int, max: int}>
     */
    private const BORNES = [
        'photos.min_lieu' => ['min' => 1, 'max' => 100],
        'photos.max_lieu' => ['min' => 1, 'max' => 100],
        'photos.min_autres' => ['min' => 1, 'max' => 100],
        'photos.max_autres' => ['min' => 1, 'max' => 100],
        'dam.image_poids_max_mo' => ['min' => 1, 'max' => 100],
        'dam.image_largeur_min' => ['min' => 100, 'max' => 4000],
        'dam.image_hauteur_min' => ['min' => 50, 'max' => 4000],
        'dam.document_poids_max_mo' => ['min' => 1, 'max' => 500],
        'dam.support_commercial_poids_max_mo' => ['min' => 1, 'max' => 500],
        'dam.delai_alerte_droits_jours' => ['min' => 1, 'max' => 365],
        'ocr.pdf_poids_max_mo' => ['min' => 1, 'max' => 200],
        'ocr.pdf_pages_max' => ['min' => 1, 'max' => 500],
        'ocr.seuil_application_auto' => ['min' => 0, 'max' => 100],
        'pim.longueur_min_texte_doublon' => ['min' => 20, 'max' => 2000],
        'pim.seuil_distance_simhash' => ['min' => 0, 'max' => 24],
        'compte.invitation_validite_heures' => ['min' => 1, 'max' => 720],
        'compte.reset_validite_heures' => ['min' => 1, 'max' => 72],
        'compte.purge_jetons_jours' => ['min' => 1, 'max' => 365],
        'compte.mot_de_passe_longueur_min' => ['min' => 8, 'max' => 64],
        'compte.max_destinataires_demandes' => ['min' => 1, 'max' => 20],
        'sirene.rescan_apres_jours' => ['min' => 1, 'max' => 365],
        'geoapify.rescan_apres_jours' => ['min' => 1, 'max' => 365],
        'datatourisme.rescan_apres_jours' => ['min' => 1, 'max' => 365],
        'wikidata.rescan_apres_jours' => ['min' => 1, 'max' => 365],
    ];

    /** Paires minimum → maximum dont la cohérence est vérifiée à la saisie. */
    private const PAIRES_MIN_MAX = [
        'photos.min_lieu' => 'photos.max_lieu',
        'photos.min_autres' => 'photos.max_autres',
    ];

    public function __construct(
        private ParametreRepository $parametres,
        private ParametreProvider $provider,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @return list<array{parametre: Parametre, affichage: string}> */
    public function lignes(): array
    {
        $lignes = [];
        foreach ($this->parametres->tousTriesParNom() as $parametre) {
            $effective = $parametre->valeur() ?? $this->provider->defaut($parametre->nom());
            $lignes[] = ['parametre' => $parametre, 'affichage' => $this->affichage($parametre->type(), $effective)];
        }

        return $lignes;
    }

    public function defaut(string $nom): ?string
    {
        return $this->provider->defaut($nom);
    }

    /** @return array{min: int, max: int}|null */
    public function bornes(string $nom): ?array
    {
        return self::BORNES[$nom] ?? null;
    }

    /** Valeur initiale du formulaire d'édition : la valeur effective, castée selon le type. */
    public function donnees(Parametre $parametre): bool|int|string
    {
        $effective = $parametre->valeur() ?? $this->provider->defaut($parametre->nom()) ?? '';

        return match ($parametre->type()) {
            TypeParametre::Booleen => filter_var($effective, FILTER_VALIDATE_BOOL),
            TypeParametre::Entier => (int) $effective,
            TypeParametre::Texte, TypeParametre::TexteLong => $effective,
        };
    }

    /** @throws \DomainException quand une paire minimum/maximum devient incohérente */
    public function surcharger(Parametre $parametre, mixed $valeur): void
    {
        if (TypeParametre::Entier === $parametre->type()) {
            $this->verifierCoherence($parametre->nom(), (int) $valeur);
        }
        $parametre->surcharger(match ($parametre->type()) {
            TypeParametre::Booleen => true === $valeur ? '1' : '0',
            TypeParametre::Entier => (string) (int) $valeur,
            TypeParametre::Texte, TypeParametre::TexteLong => trim((string) $valeur),
        });
        $this->entityManager->flush();
        $this->provider->invalider();
    }

    public function revenirAuDefaut(Parametre $parametre): void
    {
        $parametre->revenirAuDefaut();
        $this->entityManager->flush();
        $this->provider->invalider();
    }

    private function verifierCoherence(string $nom, int $valeur): void
    {
        $maximum = self::PAIRES_MIN_MAX[$nom] ?? null;
        if (null !== $maximum && $valeur > $this->provider->int($maximum)) {
            throw new \DomainException(sprintf('Le minimum ne peut pas dépasser le maximum actuel (%d, paramètre « %s »).', $this->provider->int($maximum), $maximum));
        }
        $minimum = array_search($nom, self::PAIRES_MIN_MAX, true);
        if (false !== $minimum && $valeur < $this->provider->int($minimum)) {
            throw new \DomainException(sprintf('Le maximum ne peut pas être inférieur au minimum actuel (%d, paramètre « %s »).', $this->provider->int($minimum), $minimum));
        }
    }

    private function affichage(TypeParametre $type, ?string $valeur): string
    {
        if (null === $valeur || '' === $valeur) {
            return TypeParametre::Booleen === $type ? 'Désactivé' : '—';
        }

        return match ($type) {
            TypeParametre::Booleen => filter_var($valeur, FILTER_VALIDATE_BOOL) ? 'Activé' : 'Désactivé',
            TypeParametre::Entier, TypeParametre::Texte => $valeur,
            // Les prompts se lisent dans l'écran d'édition : le tableau n'en
            // montre que le début.
            TypeParametre::TexteLong => mb_strlen($valeur) <= 120 ? $valeur : mb_substr($valeur, 0, 119).'…',
        };
    }
}
