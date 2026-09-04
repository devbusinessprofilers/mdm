<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Pim\Api\Dto\ServiceEvenementielPatchInput;
use App\Pim\Api\Dto\ServiceEvenementielResource;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Api\ExternalScopeGuard;
use App\Pim\Api\ServiceEvenementielApiMapper;
use App\Pim\Form\ServiceEvenementielType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/** @implements ProcessorInterface<ServiceEvenementielPatchInput, ServiceEvenementielResource> */
final readonly class ServiceEvenementielPatchProcessor implements ProcessorInterface
{
    public function __construct(
        private FicheApiState $state,
        private ServiceEvenementielApiMapper $mapper,
        private FormFactoryInterface $forms,
        private ExternalScopeGuard $scopes,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ServiceEvenementielResource {
        $this->scopes->requireScope(ExternalScopeGuard::FICHES_WRITE);
        $service = $this->state->service((string) ($uriVariables['id'] ?? ''));
        $this->state->assertVersion($service);

        $form = $this->forms->create(ServiceEvenementielType::class, $service, [
            'csrf_protection' => false,
        ]);

        try {
            return $service
                ->fiche()
                ->preserveWorkflowDuring(function () use (
                    $data,
                    $form,
                    $service,
                ): ServiceEvenementielResource {
                    $form->submit($data->payload(), false);

                    if (!$form->isValid()) {
                        throw new ApiProblemException(422, 'validation_failed', 'La fiche contient des données invalides.', ['violations' => $this->errors($form)]);
                    }

                    $service->fiche()->markSystemChanged();
                    $this->state->flushAndIndex($service);

                    return $this->mapper->service($service);
                });
        } catch (ApiProblemException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ApiProblemException(422, 'invalid_payload', $exception->getMessage());
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
