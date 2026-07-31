<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Pim\Api\Dto\RestaurantPatchInput;
use App\Pim\Api\Dto\RestaurantResource;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Api\RestaurantApiMapper;
use App\Pim\Form\RestaurantType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/** @implements ProcessorInterface<RestaurantPatchInput, RestaurantResource> */
final readonly class RestaurantPatchProcessor implements ProcessorInterface
{
    public function __construct(
        private RestaurantApiState $state,
        private RestaurantApiMapper $mapper,
        private FormFactoryInterface $forms,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): RestaurantResource {
        $restaurant = $this->state->restaurant(
            (string) ($uriVariables['id'] ?? ''),
        );
        $this->state->assertVersion($restaurant);
        $form = $this->forms->create(RestaurantType::class, $restaurant, [
            'csrf_protection' => false,
        ]);

        try {
            return $restaurant->fiche()->preserveWorkflowDuring(
                function () use ($data, $form, $restaurant): RestaurantResource {
                    $form->submit($data->payload(), false);
                    if (!$form->isValid()) {
                        throw new ApiProblemException(
                            422,
                            'validation_failed',
                            'La fiche contient des données invalides.',
                            ['violations' => $this->errors($form)],
                        );
                    }

                    $restaurant->fiche()->markSystemChanged();
                    $this->state->flushAndIndex($restaurant);

                    return $this->mapper->restaurant($restaurant);
                },
            );
        } catch (ApiProblemException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ApiProblemException(
                422,
                'invalid_payload',
                $exception->getMessage(),
            );
        }
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
