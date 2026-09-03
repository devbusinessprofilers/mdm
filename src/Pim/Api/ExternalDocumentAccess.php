<?php

declare(strict_types=1);

namespace App\Pim\Api;

use App\Account\Security\ExternalSitePrincipal;
use App\Dam\Enum\DocumentAccess;
use App\Dam\Enum\DocumentPublicationStatus;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Entity\Lieu\RessourceLieu;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;

final readonly class ExternalDocumentAccess
{
    public function __construct(private Security $security)
    {
    }

    public function canRead(RessourceLieu $document): bool
    {
        $principal = $this->principal();
        if (DocumentAccess::Private === $document->documentAccess()) {
            return $principal->hasScope('documents:private');
        }

        return DocumentPublicationStatus::Published ===
            $document->publicationStatus()
            || $principal->hasScope('documents:read');
    }

    public function canDownloadOriginal(RessourceLieu $document): bool
    {
        return DocumentAccess::Private === $document->documentAccess()
            ? $this->principal()->hasScope('documents:private')
            : $this->principal()->hasScope('documents:read');
    }

    public function requireWrite(RessourceLieu $document): void
    {
        $scope =
            DocumentAccess::Private === $document->documentAccess()
                ? 'documents:private'
                : 'documents:write';
        $this->requireScope($scope);
    }

    public function requireCreate(DocumentAccess $access): void
    {
        $this->requireScope(
            DocumentAccess::Private === $access
                ? 'documents:private'
                : 'documents:write',
        );
    }

    public function requirePublish(): void
    {
        $this->requireScope('documents:publish');
    }

    private function requireScope(string $scope): void
    {
        if (!$this->principal()->hasScope($scope)) {
            throw new ApiProblemException(Response::HTTP_FORBIDDEN, 'insufficient_scope', 'Le scope '.$scope.' est requis.');
        }
    }

    private function principal(): ExternalSitePrincipal
    {
        $user = $this->security->getUser();
        if (!$user instanceof ExternalSitePrincipal) {
            throw new ApiProblemException(Response::HTTP_FORBIDDEN, 'insufficient_scope', 'Jeton externe requis.');
        }

        return $user;
    }
}
