<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\FicheTransition;

/**
 * Exécute une transition de workflow sur une fiche, toutes gammes, et rend
 * les messages à afficher : succès, refus métier (DomainException) ou
 * violations de validation à la soumission. Le contrôleur n'a plus qu'à
 * vérifier le droit, le formulaire, et rediriger.
 */
final readonly class FicheTransitionExecutor
{
    public function __construct(private FicheWorkflowManager $workflow)
    {
    }

    /**
     * @param string|null $motif motif du refus (transition Refuser)
     *
     * @return list<array{string, string}> messages flash (type, texte)
     */
    public function executer(FicheTransition $transition, Lieu|Restaurant|Activite|ServiceEvenementiel $entite, string $actor, ?string $motif = null): array
    {
        $fiche = $entite->fiche();
        try {
            switch ($transition) {
                case FicheTransition::Soumettre:
                    $violations = $this->workflow->submit($entite, $fiche, $actor);
                    if (count($violations) > 0) {
                        $messages = [];
                        foreach ($violations as $violation) {
                            $messages[] = ['error', $violation->getPropertyPath().' : '.$violation->getMessage()];
                        }

                        return $messages;
                    }
                    break;
                case FicheTransition::Valider:
                    $this->workflow->validate($fiche, $actor);
                    break;
                case FicheTransition::Publier:
                    $this->workflow->publish($fiche);
                    break;
                case FicheTransition::Refuser:
                    $this->workflow->reject($fiche, $actor, (string) $motif);
                    break;
                case FicheTransition::Archiver:
                    $this->workflow->archive($fiche, $actor);
                    break;
                case FicheTransition::Desarchiver:
                    $this->workflow->unarchive($fiche, $actor);
                    break;
                case FicheTransition::Republier:
                    $this->workflow->republish($fiche, $actor);
                    break;
                case FicheTransition::Supprimer:
                    $this->workflow->delete($entite);
                    break;
            }
        } catch (\DomainException $exception) {
            return [['warning', $exception->getMessage()]];
        }

        return [['success', $transition->succes()]];
    }
}
