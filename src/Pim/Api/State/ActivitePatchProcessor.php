<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Pim\Api\ActiviteApiMapper;
use App\Pim\Api\Dto\ActivitePatchInput;
use App\Pim\Api\Dto\ActiviteResource;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Entity\ValeurAttribut;
use App\Pim\Form\ActiviteType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/** @implements ProcessorInterface<ActivitePatchInput,ActiviteResource> */
final readonly class ActivitePatchProcessor implements ProcessorInterface
{
    public function __construct(
        private ActiviteApiState $state,
        private ActiviteApiMapper $mapper,
        private FormFactoryInterface $forms,
        private EntityManagerInterface $em,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ActiviteResource {
        $a = $this->state->activite((string) ($uriVariables['id'] ?? ''));
        $this->state->assertVersion($a);
        $payload = $data->payload();
        $providerDefined = array_key_exists('prestataireCode', $payload);
        $providerCode = $payload['prestataireCode'] ?? null;
        unset($payload['prestataireCode']);
        $form = $this->forms->create(ActiviteType::class, $a, [
            'csrf_protection' => false,
        ]);
        try {
            return $a
                ->fiche()
                ->preserveWorkflowDuring(function () use (
                    $a,
                    $payload,
                    $form,
                    $providerDefined,
                    $providerCode,
                ): ActiviteResource {
                    if ($providerDefined) {
                        $provider = null;
                        if (
                            null !== $providerCode
                            && '' !== trim((string) $providerCode)
                        ) {
                            $provider = $this->em
                                ->createQueryBuilder()
                                ->select('v')
                                ->from(ValeurAttribut::class, 'v')
                                ->join('v.attribute', 'd')
                                ->where('d.code=:attribute')
                                ->andWhere('v.code=:code')
                                ->setParameter('attribute', 'PRESTATAIRE')
                                ->setParameter('code', $providerCode)
                                ->getQuery()
                                ->getOneOrNullResult();
                            if (!($provider instanceof ValeurAttribut)) {
                                throw new ApiProblemException(422, 'validation_failed', 'Le prestataire est inconnu.');
                            }
                        }
                        $a->changePrestataire($provider);
                    }
                    $form->submit($payload, false);
                    if (!$form->isValid()) {
                        throw new ApiProblemException(422, 'validation_failed', 'La fiche contient des données invalides.', ['violations' => $this->errors($form)]);
                    }
                    $a->fiche()->markSystemChanged();
                    $this->state->flushAndIndex($a);

                    return $this->mapper->activite($a);
                });
        } catch (ApiProblemException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ApiProblemException(422, 'invalid_payload', $e->getMessage());
        }
    }

    /**
     * @param FormInterface<mixed> $form
     *
     * @return list<array{propertyPath:string,message:string}>
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
