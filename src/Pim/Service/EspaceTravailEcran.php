<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\EspaceTravailRepository;
use App\Pim\Repository\SavedViewRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Assemble l'espace de travail personnel (vue Supply de la maquette) :
 * compteurs, priorités, raccourcis et activité, tous rapportés à
 * l'utilisateur connecté via l'assignation des fiches.
 */
final readonly class EspaceTravailEcran
{
    public function __construct(
        private EspaceTravailRepository $repository,
        private SavedViewRepository $vues,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /** @return array<string, mixed> Variables du gabarit mdm/espace_travail.html.twig. */
    public function variables(string $userId): array
    {
        $priorites = [];
        foreach ($this->repository->priorites($userId) as $priorite) {
            $type = TypeFiche::tryFrom($priorite['type']);
            $priorites[] = $priorite + ['url' => match ($type) {
                TypeFiche::Lieu => $this->urls->generate('app_mdm_fiche_lieu', ['id' => $priorite['id']]),
                TypeFiche::Restaurant, TypeFiche::Activite, TypeFiche::ServiceEvenementiel => $this->urls->generate('app_mdm_fiche_gamme', [
                    'gamme' => FicheEditeurEcran::slug($type),
                    'id' => $priorite['id'],
                ]),
                default => null,
            }];
        }
        $vuesEnregistrees = [];
        foreach ($this->vues->findVisiblesPour($userId) as $vue) {
            $vuesEnregistrees[] = [
                'vue' => $vue,
                'url' => $this->urls->generate('app_mdm_referentiel_general', ['f' => $vue->filters()]),
            ];
        }

        return [
            'compteurs' => $this->repository->compteurs($userId),
            'priorites' => $priorites,
            'activite' => $this->repository->activite($userId, new \DateTimeImmutable('-7 days')),
            'vues_enregistrees' => $vuesEnregistrees,
            'url_mes_fiches' => $this->urls->generate('app_mdm_referentiel_general', [
                'f' => ['contributeurs' => [$userId]],
            ]),
        ];
    }
}
