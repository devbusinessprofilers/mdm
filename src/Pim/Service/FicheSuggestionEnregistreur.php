<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheSuggestion;
use App\Pim\Enum\SuggestionSource;
use App\Pim\Repository\FicheSuggestionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Réconcilie les suggestions en attente d'une source pour une fiche : crée les
 * nouvelles, rafraîchit celles qui existent déjà (même champ) pour garder
 * l'écart à jour sans doublonner. Châssis partagé par les sources
 * d'enrichissement (Sirene, puis Geoapify, DATAtourisme, Wikidata).
 */
final readonly class FicheSuggestionEnregistreur
{
    public function __construct(
        private FicheSuggestionRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<SuggestionProposee> $propositions
     *
     * @return int nombre de suggestions nouvellement créées
     */
    public function enregistrer(Fiche $fiche, SuggestionSource $source, array $propositions): int
    {
        $crees = 0;
        foreach ($propositions as $proposition) {
            $existante = $this->repository->findPourCle($fiche, $source, $proposition->champ);
            if (null !== $existante) {
                // Déjà arbitrée (acceptée ou ignorée) : on respecte la décision
                // et on ne recrée rien (la contrainte unique l'interdirait de
                // toute façon). Encore en attente : on rafraîchit l'écart.
                if ($existante->isPending()) {
                    $existante->rafraichir($proposition->label, $proposition->valeurActuelle, $proposition->valeurProposee, $proposition->score, $proposition->payload);
                }

                continue;
            }
            $this->entityManager->persist(new FicheSuggestion(
                $fiche,
                $source,
                $proposition->action,
                $proposition->champ,
                $proposition->label,
                $proposition->valeurActuelle,
                $proposition->valeurProposee,
                $proposition->score,
                $proposition->payload,
            ));
            ++$crees;
        }

        return $crees;
    }
}
