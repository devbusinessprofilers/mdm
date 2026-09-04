<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dam\Service\LieuDocumentPresenter;
use App\Pim\Api\Dto\FicheDocumentResource;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Api\ExternalDocumentAccess;
use App\Pim\Api\ProfilApiGamme;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Repository\RessourceLieuRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documents d'une fiche dans l'API externe, toutes gammes : la liste des
 * documents lisibles selon les scopes, ou un document avec son URL de
 * téléchargement. Les usages hors de la gamme sont invisibles.
 *
 * @implements ProviderInterface<FicheDocumentResource>
 */
final readonly class FicheDocumentProvider implements ProviderInterface
{
    public function __construct(
        private FicheApiState $state,
        private RessourceLieuRepository $resources,
        private ExternalDocumentAccess $access,
        private LieuDocumentPresenter $presenter,
    ) {
    }

    /** @return FicheDocumentResource|list<FicheDocumentResource> */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): FicheDocumentResource|array
    {
        $profil = ProfilApiGamme::depuisUriVariables($uriVariables);
        $entite = $this->state->entite($profil->type, $profil->id($uriVariables));
        if (isset($uriVariables['documentId'])) {
            $document = $this->resources->find((string) $uriVariables['documentId']);
            if (!$document instanceof RessourceLieu || !self::estDocument($profil, $entite->fiche(), $document) || !$this->access->canRead($document)) {
                throw new ApiProblemException(Response::HTTP_NOT_FOUND, 'not_found', 'Document introuvable.');
            }

            return $this->presenter->resource($document, $this->access->canDownloadOriginal($document));
        }
        $documents = [];
        foreach ($entite->fiche()->resources() as $document) {
            if (self::estDocument($profil, $entite->fiche(), $document) && $this->access->canRead($document)) {
                $documents[] = $this->presenter->resource($document);
            }
        }

        return $documents;
    }

    public static function estDocument(ProfilApiGamme $profil, Fiche $fiche, RessourceLieu $document): bool
    {
        $usage = $document->documentUsage();

        return $document->fiche() === $fiche
            && NatureRessource::Document === $document->nature()
            && null !== $usage
            && $profil->documentAutorise($usage);
    }
}
