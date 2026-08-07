<?php

declare(strict_types=1);

namespace App\Audit\Form;

use App\Audit\Entity\AuditRevision;
use App\Audit\Restore\RestorableFieldCatalog;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class RestoreFormFactory
{
    public function __construct(
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private RestorableFieldCatalog $catalog,
    ) {
    }

    /** @return FormInterface<mixed> */
    public function restoreChange(
        string $changeId,
        int $expectedVersion,
    ): FormInterface {
        return $this->action(
            'restore_change_'.$changeId,
            'restore-change-'.$changeId,
            $this->urls->generate('app_audit_restore_change', [
                'changeId' => $changeId,
            ]),
            'Restaurer',
            $expectedVersion,
        );
    }

    /** @return FormInterface<mixed> */
    public function restoreRevision(
        string $revisionId,
        int $expectedVersion,
    ): FormInterface {
        return $this->action(
            'restore_revision',
            'restore-revision-'.$revisionId,
            $this->urls->generate('app_audit_restore_revision', [
                'revisionId' => $revisionId,
            ]),
            'Restaurer cette révision',
            $expectedVersion,
        );
    }

    /**
     * Vues de formulaire « Restaurer » indexées par id de change, pour les
     * seuls chemins restaurables des révisions affichées.
     *
     * @param iterable<AuditRevision> $revisions
     *
     * @return array<string, FormView>
     */
    public function changeFormViews(iterable $revisions, int $expectedVersion): array
    {
        $views = [];
        foreach ($revisions as $revision) {
            foreach ($revision->changes() as $change) {
                if (!$this->catalog->isRestorable($change->path())) {
                    continue;
                }
                $views[$change->id()] = $this
                    ->restoreChange($change->id(), $expectedVersion)
                    ->createView();
            }
        }

        return $views;
    }

    /** @return FormInterface<mixed> */
    private function action(
        string $name,
        string $csrfTokenId,
        string $actionUrl,
        string $label,
        int $expectedVersion,
    ): FormInterface {
        return $this->forms
            ->createNamedBuilder($name, options: [
                'csrf_token_id' => $csrfTokenId,
            ])
            ->setAction($actionUrl)
            ->setMethod('POST')
            ->add('expectedVersion', HiddenType::class, [
                'data' => (string) $expectedVersion,
            ])
            ->add('submit', SubmitType::class, ['label' => $label])
            ->getForm();
    }
}
