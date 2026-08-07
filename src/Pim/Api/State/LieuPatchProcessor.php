<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Pim\Api\Dto\LieuPatchInput;
use App\Pim\Api\Dto\LieuResource;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Api\ExternalScopeGuard;
use App\Pim\Api\LieuApiMapper;
use App\Pim\Form\LieuType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;

/** @implements ProcessorInterface<LieuPatchInput, LieuResource> */
final readonly class LieuPatchProcessor implements ProcessorInterface
{
    public function __construct(
        private LieuApiState $state,
        private LieuApiMapper $mapper,
        private FormFactoryInterface $forms,
        private ExternalScopeGuard $scopes,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): LieuResource {
        $this->scopes->requireScope(ExternalScopeGuard::FICHES_WRITE);
        $lieu = $this->state->lieu((string) ($uriVariables['id'] ?? ''));
        $this->state->assertVersion($lieu);
        $form = $this->forms->create(LieuType::class, $lieu, [
            'csrf_protection' => false,
        ]);
        try {
            return $lieu
                ->fiche()
                ->preserveWorkflowDuring(function () use (
                    $data,
                    $form,
                    $lieu,
                ): LieuResource {
                    $form->submit($data->payload(), false);
                    if (!$form->isValid()) {
                        throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'validation_failed', 'La fiche contient des données invalides.', ['violations' => $this->errors($form)]);
                    }
                    $lieu->fiche()->markSystemChanged();
                    $this->state->flushAndIndex($lieu);

                    return $this->mapper->lieu($lieu);
                });
        } catch (ApiProblemException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_payload', $exception->getMessage());
        }
    }

    /** @param FormInterface<mixed> $form
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
