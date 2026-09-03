<?php

declare(strict_types=1);

namespace App\Dam\Service;

use App\Dam\Entity\MediaAsset;
use App\Dam\Entity\MediaDuplicateAlert;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Shared\Form\ActionType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class DamDashboardFormFactory
{
    public function __construct(
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    public function addActions(array $items, string $filter, ?string $type, int $page): array
    {
        foreach ($items as &$item) {
            $query = ['filter' => $filter, 'page' => $page];
            if (null !== $type) {
                $query['type'] = $type;
            }
            if (DamDashboardProvider::FILTER_DUPLICATES === $filter) {
                $alert = $item['alert'];
                if (!$alert instanceof MediaDuplicateAlert) {
                    throw new \LogicException('Alerte DAM attendue pour le formulaire de doublon.');
                }
                $item['accept_form'] = $this->action(
                    'app_dam_duplicate_accept',
                    ['id' => $alert->id()] + $query,
                    'dam-duplicate-accept-'.$alert->id(),
                    'Accepter le doublon',
                    ['class' => 'btn btn-secondary'],
                );
                $item['delete_form'] = $this->action(
                    'app_dam_duplicate_delete',
                    ['id' => $alert->id()] + $query,
                    'dam-duplicate-delete-'.$alert->id(),
                    'Supprimer l’image',
                    ['class' => 'btn danger'],
                    [
                        'data-controller' => 'confirm',
                        'data-confirm-message-value' => 'Supprimer l’image signalée comme doublon ?',
                        'data-action' => 'submit->confirm#submit',
                    ],
                );
            } elseif (str_starts_with($filter, 'rights_')) {
                $resource = $item['resource'];
                if (!$resource instanceof RessourceLieu) {
                    throw new \LogicException('Ressource DAM attendue pour le formulaire de droits.');
                }
                if (!$resource->rightsGranted() && null !== $resource->rightsExpiresAt() && $resource->rightsExpiresAt() < new \DateTimeImmutable('today')) {
                    continue;
                }
                $action = $resource->rightsGranted() ? 'revoke' : 'grant';
                $item['rights_form'] = $this->action(
                    'app_dam_rights',
                    ['id' => $resource->id(), 'action' => $action] + $query,
                    'dam-rights-'.$action.'-'.$resource->id(),
                    $resource->rightsGranted() ? 'Révoquer les droits' : 'Valider les droits',
                    ['class' => 'btn'],
                );
            } elseif (DamDashboardProvider::FILTER_FAILED === $filter) {
                $asset = $item['asset'];
                if (!$asset instanceof MediaAsset) {
                    throw new \LogicException('Média DAM attendu pour le formulaire de relance.');
                }
                $item['retry_form'] = $this->action(
                    'app_dam_retry',
                    ['id' => $asset->id()] + $query,
                    'dam-retry-'.$asset->id(),
                    'Relancer le traitement',
                    ['class' => 'btn'],
                );
            }
        }
        unset($item);

        return $items;
    }

    /**
     * @param array<string, string|int> $parameters
     * @param array<string, mixed>      $buttonAttributes
     * @param array<string, mixed>      $formAttributes
     */
    private function action(string $route, array $parameters, string $csrfTokenId, string $label, array $buttonAttributes, array $formAttributes = []): FormView
    {
        return $this->forms->createNamed('', ActionType::class, null, [
            'action' => $this->urls->generate($route, $parameters),
            'button_label' => $label,
            'button_attr' => $buttonAttributes,
            'attr' => $formAttributes,
            'csrf_token_id' => $csrfTokenId,
        ])->createView();
    }
}
