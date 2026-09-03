<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Pim\Api\Dto\RestaurantPatchInput;
use App\Pim\Api\Dto\RestaurantResource;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Api\ExternalScopeGuard;
use App\Pim\Api\RestaurantApiMapper;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Form\RestaurantType;
use App\Pim\Lov\RestaurantLovCatalog;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/** @implements ProcessorInterface<RestaurantPatchInput, RestaurantResource> */
final readonly class RestaurantPatchProcessor implements ProcessorInterface
{
    public function __construct(
        private RestaurantApiState $state,
        private RestaurantApiMapper $mapper,
        private FormFactoryInterface $forms,
        private ExternalScopeGuard $scopes,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): RestaurantResource {
        $this->scopes->requireScope(ExternalScopeGuard::FICHES_WRITE);
        $restaurant = $this->state->restaurant(
            (string) ($uriVariables['id'] ?? ''),
        );
        $this->state->assertVersion($restaurant);
        $form = $this->forms->create(RestaurantType::class, $restaurant, [
            'csrf_protection' => false,
        ]);

        $payload = self::traduireHorairesLegacy($data->payload(), $restaurant);

        try {
            return $restaurant->fiche()->preserveWorkflowDuring(
                function () use ($payload, $form, $restaurant): RestaurantResource {
                    $form->submit($payload, false);
                    if (!$form->isValid()) {
                        throw new ApiProblemException(422, 'validation_failed', 'La fiche contient des données invalides.', ['violations' => $this->errors($form)]);
                    }

                    $restaurant->fiche()->markSystemChanged();
                    $this->state->flushAndIndex($restaurant);

                    return $this->mapper->restaurant($restaurant);
                },
            );
        } catch (ApiProblemException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ApiProblemException(422, 'invalid_payload', $exception->getMessage());
        }
    }

    /**
     * Rétrocompat portail (accepter-et-traduire) : une amplitude PATCHée via
     * les clés historiques heureOuverture/heureFermeture est traduite en
     * horaires par jour — appliquée aux jours d'ouverture (ceux du PATCH,
     * sinon ceux de la fiche), les autres jours étant vidés. Un
     * `horairesJours` natif dans le même PATCH prime.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private static function traduireHorairesLegacy(array $payload, Restaurant $restaurant): array
    {
        if (!array_key_exists('heureOuverture', $payload) && !array_key_exists('heureFermeture', $payload)) {
            return $payload;
        }
        $ouverture = is_string($payload['heureOuverture'] ?? null) ? $payload['heureOuverture'] : null;
        $fermeture = is_string($payload['heureFermeture'] ?? null) ? $payload['heureFermeture'] : null;
        unset($payload['heureOuverture'], $payload['heureFermeture']);
        if (array_key_exists('horairesJours', $payload)) {
            return $payload;
        }
        $jours = is_array($payload['joursOuverture'] ?? null)
            ? $payload['joursOuverture']
            : $restaurant->joursOuverture();
        $horaires = [];
        foreach (array_keys(RestaurantLovCatalog::values('DISPO_JOUR_OUVERTURE')) as $code) {
            $ouvert = in_array($code, $jours, true);
            $horaires[$code] = [
                'ouverture' => $ouvert ? $ouverture : null,
                'fermeture' => $ouvert ? $fermeture : null,
            ];
        }
        $payload['horairesJours'] = $horaires;

        return $payload;
    }

    /**
     * @param FormInterface<mixed> $form
     *
     * @return list<array{propertyPath: string, message: string}>
     */
    private function errors(FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(true, true) as $error) {
            $origin = $error->getOrigin();
            $path = [];
            while (null !== $origin && $origin !== $form) {
                array_unshift($path, $origin->getName());
                $origin = $origin->getParent();
            }
            $errors[] = [
                'propertyPath' => implode('.', $path),
                'message' => $error->getMessage(),
            ];
        }

        return $errors;
    }
}
