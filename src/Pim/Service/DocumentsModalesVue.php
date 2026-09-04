<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\NatureRessource;
use App\Pim\Form\LieuDocumentReplaceType;
use App\Shared\Form\ActionType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Documents d'une fiche et les quatre formulaires de leur modale (métadonnées,
 * remplacement, publication, suppression), toutes gammes : mêmes noms de
 * formulaires et jetons que FicheDocumentController, qui les soumet. Rendus
 * avec le bloc médias (gammes) ou avec les modales préchargées (Lieu).
 */
final readonly class DocumentsModalesVue
{
    public function __construct(private FormFactoryInterface $forms, private UrlGeneratorInterface $urls)
    {
    }

    /** @return list<array{resource: RessourceLieu, metadata_form: mixed, replace_form: mixed, publication_form: mixed, delete_form: mixed}> */
    public function documents(Lieu|Restaurant|Activite|ServiceEvenementiel $entite): array
    {
        $type = $entite->fiche()->type();
        $profil = ProfilDocumentsGamme::pour($type);
        $documents = [];
        foreach ($entite->fiche()->resources() as $resource) {
            if (NatureRessource::Document !== $resource->nature() || null === $resource->documentUsage()) {
                continue;
            }
            if (null !== $profil->usageImpose && $profil->usageImpose !== $resource->documentUsage()) {
                continue;
            }
            $params = ['gamme' => $type->slug(), 'id' => $entite->id(), 'resourceId' => $resource->id()];
            $documents[] = [
                'resource' => $resource,
                'metadata_form' => $this->forms->createNamed($profil->nomFormulaire('metadata', $resource->id()), $profil->typeMetadata, $profil->donneesMetadata($resource), [
                    'action' => $this->urls->generate('app_pim_fiche_document_update', $params), 'method' => 'POST',
                ] + $profil->optionsMetadata($entite))->createView(),
                'replace_form' => $this->forms->createNamed($profil->nomFormulaire('replace', $resource->id()), LieuDocumentReplaceType::class, null, [
                    'action' => $this->urls->generate('app_pim_fiche_document_replace', $params), 'method' => 'POST',
                ])->createView(),
                'publication_form' => $this->forms->createNamed($profil->nomFormulaire('publication', $resource->id()), ActionType::class, null, [
                    'action' => $this->urls->generate('app_pim_fiche_document_publication', $params),
                    'button_label' => 'published' === $resource->publicationStatus()?->value ? 'Dépublier' : 'Publier',
                    'csrf_token_id' => $profil->jetonCsrf('publication', $resource->id()),
                ])->createView(),
                'delete_form' => $this->forms->createNamed($profil->nomFormulaire('delete', $resource->id()), ActionType::class, null, [
                    'action' => $this->urls->generate('app_pim_fiche_document_delete', $params),
                    'button_label' => 'Supprimer', 'csrf_token_id' => $profil->jetonCsrf('delete', $resource->id()),
                    'attr' => ['data-controller' => 'confirm', 'data-confirm-message-value' => 'Supprimer ce document ?', 'data-action' => 'submit->confirm#submit'],
                ])->createView(),
            ];
        }

        return $documents;
    }
}
