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

    /** Valeur initiale du formulaire d'édition : la valeur effective, castée selon le type. */
    public function donnees(Parametre $parametre): bool|int|string
    {
        $effective = $parametre->valeur() ?? $this->provider->defaut($parametre->nom()) ?? '';

        return match ($parametre->type()) {
            TypeParametre::Booleen => filter_var($effective, FILTER_VALIDATE_BOOL),
            TypeParametre::Entier => (int) $effective,
            TypeParametre::Texte => $effective,
        };
    }

    public function surcharger(Parametre $parametre, mixed $valeur): void
    {
        $parametre->surcharger(match ($parametre->type()) {
            TypeParametre::Booleen => true === $valeur ? '1' : '0',
            TypeParametre::Entier => (string) (int) $valeur,
            TypeParametre::Texte => trim((string) $valeur),
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

    private function affichage(TypeParametre $type, ?string $valeur): string
    {
        if (null === $valeur || '' === $valeur) {
            return TypeParametre::Booleen === $type ? 'Désactivé' : '—';
        }

        return match ($type) {
            TypeParametre::Booleen => filter_var($valeur, FILTER_VALIDATE_BOOL) ? 'Activé' : 'Désactivé',
            TypeParametre::Entier, TypeParametre::Texte => $valeur,
        };
    }
}
