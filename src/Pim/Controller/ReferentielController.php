<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Service\CurrentActorProvider;
use App\Pim\Entity\ReferentielExport;
use App\Pim\Entity\SavedView;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\ExportAttenteType;
use App\Pim\Form\ReferentielFiltres;
use App\Pim\Form\SavedViewType;
use App\Pim\Message\GenererReferentielExport;
use App\Pim\MessageHandler\GenererReferentielExportHandler;
use App\Pim\ReadModel\ReferentielCursor;
use App\Pim\Repository\ReferentielExportRepository;
use App\Pim\Repository\SavedViewRepository;
use App\Pim\Service\RechercheSuggestions;
use App\Pim\Service\ReferentielActionGroupee;
use App\Pim\Service\ReferentielEcran;
use App\Pim\Service\ReferentielListeProvider;
use App\Pim\Service\SavedViewManager;
use App\Shared\Service\PrivateObjectStorageInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Liste du référentiel aux emplacements de la maquette front : facettes,
 * vues enregistrées et actions groupées sur données réelles. Le gabarit est
 * volontairement sobre — le style viendra du front.
 */
#[Route('/referentiel', name: 'app_mdm_')]
#[IsGranted('ROLE_BP_EDITOR')]
final class ReferentielController extends AbstractController
{
    private const PAR_PAGE = 14;

    #[Route('', name: 'referentiel_general', methods: ['GET'])]
    public function general(Request $request, CurrentActorProvider $actor, ReferentielEcran $ecran): Response
    {
        $filtres = ReferentielFiltres::fromArray($request->query->all('f'));

        return $this->render(
            'mdm/referentiel.html.twig',
            $ecran->variables($filtres, self::curseur($request, $filtres), null, $actor->id(), self::PAR_PAGE),
        );
    }

    /** Suggestions de noms de fiches pour l'autocomplétion des champs de recherche. */
    #[Route('/suggestions', name: 'referentiel_suggestions', methods: ['GET'])]
    public function suggestions(Request $request, RechercheSuggestions $recherche): Response
    {
        return $this->json($recherche->pour($request->query->getString('q')));
    }

    #[Route('/lieux', name: 'lieux', methods: ['GET'])]
    public function lieux(Request $request, CurrentActorProvider $actor, ReferentielEcran $ecran): Response
    {
        $filtres = ReferentielFiltres::fromArray($request->query->all('f'));
        $filtres->gammes = [TypeFiche::Lieu];

        return $this->render(
            'mdm/referentiel.html.twig',
            $ecran->variables($filtres, self::curseur($request, $filtres), TypeFiche::Lieu, $actor->id(), self::PAR_PAGE),
        );
    }

    /** Les autres gammes de cette version : mêmes listes filtrées que les lieux. */
    #[Route('/{gamme}', name: 'referentiel_gamme', requirements: ['gamme' => 'restaurants|activites|services'], methods: ['GET'])]
    public function gamme(Request $request, string $gamme, CurrentActorProvider $actor, ReferentielEcran $ecran): Response
    {
        $type = match ($gamme) {
            'restaurants' => TypeFiche::Restaurant,
            'activites' => TypeFiche::Activite,
            default => TypeFiche::ServiceEvenementiel,
        };
        $filtres = ReferentielFiltres::fromArray($request->query->all('f'));
        $filtres->gammes = [$type];

        return $this->render(
            'mdm/referentiel.html.twig',
            $ecran->variables($filtres, self::curseur($request, $filtres), $type, $actor->id(), self::PAR_PAGE),
        );
    }

    /**
     * Curseur de pagination de la requête. Un curseur forgé sous un autre tri
     * comparerait des clés hétérogènes : il est rejeté comme un curseur
     * invalide.
     */
    private static function curseur(Request $request, ReferentielFiltres $filtres): ?ReferentielCursor
    {
        try {
            $cursor = ReferentielCursor::decode($request->query->getString('cursor') ?: null);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('Curseur de pagination invalide.');
        }
        if (null !== $cursor && $cursor->tri !== $filtres->tri) {
            throw new NotFoundHttpException('Curseur incohérent avec le tri demandé.');
        }

        return $cursor;
    }

    #[Route('/actions', name: 'referentiel_actions', methods: ['POST'])]
    public function actions(
        Request $request,
        CurrentActorProvider $actor,
        ReferentielEcran $ecran,
        ReferentielListeProvider $provider,
        ReferentielActionGroupee $actionneur,
        \App\Etl\Service\SalesforceSelectionSender $salesforce,
    ): Response {
        $filtres = ReferentielFiltres::fromArray($request->query->all('f'));
        $retour = $this->redirectToRoute('app_mdm_referentiel_general', ['f' => $request->query->all('f')]);
        $form = $ecran->formSelectionSoumise($request->request->all('selection'));
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('warning', 'Sélection invalide, aucune action appliquée.');

            return $retour;
        }
        /** @var array{ids: list<string>, tout: bool, action: ?string, contributeur: ?\App\Account\Entity\User, sites: list<int>, colonnes: list<string>} $data */
        $data = $form->getData();
        // Le placeholder du choix d'action est soumis comme valeur nulle valide.
        if (null === $data['action']) {
            $this->addFlash('warning', 'Choisissez une action.');

            return $retour;
        }
        $action = $data['action'];
        $plafond = ReferentielActionGroupee::plafond($action);
        $ids = $data['tout']
            ? $provider->idsPourFiltre($filtres, $plafond + 1)
            : $data['ids'];
        if ([] === $ids) {
            $this->addFlash('warning', 'Aucune fiche sélectionnée.');

            return $retour;
        }
        if (count($ids) > $plafond) {
            $this->addFlash('warning', sprintf(
                'L\'action « %s » est plafonnée à %d fiches ; la sélection en compte davantage. Réduisez le filtre.',
                $action,
                $plafond,
            ));

            return $retour;
        }
        // Envoi manuel groupé vers Salesforce : un CSV Produits (et un CSV
        // Salles) pour toute la sélection, tous types et statuts.
        if ('salesforce' === $action) {
            try {
                $envoyees = $salesforce->envoyer(array_slice($ids, 0, $plafond));
            } catch (\DomainException $exception) {
                $this->addFlash('warning', $exception->getMessage());

                return $retour;
            }
            $this->addFlash('success', sprintf('Envoi à Salesforce : %d fiche(s) transmise(s).', $envoyees));

            return $retour;
        }
        // « Valider » du bloc Attribuer : applique contributeur et/ou
        // visibilité selon les champs remplis.
        if ('attribuer' === $action) {
            $sousActions = [];
            if (null !== ($data['contributeur'] ?? null)) {
                $sousActions[] = 'contributeur';
            }
            if ([] !== $data['sites']) {
                $sousActions[] = 'visibilite';
            }
            if ([] === $sousActions) {
                $this->addFlash('warning', 'Choisissez un contributeur à assigner ou des sites à attribuer.');

                return $retour;
            }
            $totaux = ['appliquees' => 0, 'ignorees' => 0];
            try {
                foreach ($sousActions as $sousAction) {
                    $resultat = $actionneur->appliquer($sousAction, $ids, $actor->id(), $data['contributeur'] ?? null, $data['sites']);
                    $totaux['appliquees'] += $resultat['appliquees'];
                    $totaux['ignorees'] += $resultat['ignorees'];
                }
            } catch (\DomainException $exception) {
                $this->addFlash('warning', $exception->getMessage());

                return $retour;
            }
            $this->addFlash('success', sprintf(
                'Attribution : %d élément(s) traité(s), %d ignoré(s) (état, droits ou doublon).',
                $totaux['appliquees'],
                $totaux['ignorees'],
            ));

            return $retour;
        }
        try {
            $resultat = $actionneur->appliquer($action, $ids, $actor->id(), $data['contributeur'] ?? null, $data['sites']);
        } catch (\DomainException $exception) {
            $this->addFlash('warning', $exception->getMessage());

            return $retour;
        }
        $this->addFlash('success', sprintf(
            'Action « %s » : %d élément(s) traité(s), %d ignoré(s) (état, droits ou doublon).',
            $action,
            $resultat['appliquees'],
            $resultat['ignorees'],
        ));

        return $retour;
    }

    /**
     * Demande d'export Excel depuis la modale (nouvel onglet) : la demande
     * est tracée avec un code unique, la génération part en tâche de fond,
     * et l'onglet atterrit sur la page de suivi — partageable, refermable.
     */
    #[Route('/exporter', name: 'referentiel_exporter', methods: ['POST'])]
    public function exporter(
        Request $request,
        CurrentActorProvider $actor,
        ReferentielEcran $ecran,
        ReferentielListeProvider $provider,
        ReferentielExportRepository $exports,
        MessageBusInterface $bus,
    ): Response {
        $filtres = ReferentielFiltres::fromArray($request->query->all('f'));
        $form = $ecran->formSelectionSoumise($request->request->all('selection'));
        $form->handleRequest($request);
        $retour = $this->redirectToRoute('app_mdm_referentiel_general', ['f' => $request->query->all('f')]);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('warning', 'Sélection invalide, export non lancé.');

            return $retour;
        }
        /** @var array{ids: list<string>, tout: bool, colonnes: list<string>} $data */
        $data = $form->getData();
        // « Tout le résultat filtré » ne transporte ni ne stocke ses milliers
        // d'ids : l'entité garde le drapeau (ids null) et les filtres, le
        // worker re-résout la sélection. Pas de plafond.
        $tout = $data['tout'];
        $nb = $tout ? count($provider->idsPourFiltre($filtres, PHP_INT_MAX)) : count($data['ids']);
        if (0 === $nb) {
            $this->addFlash('warning', 'Aucune fiche sélectionnée.');

            return $retour;
        }
        if ([] === $data['colonnes']) {
            $this->addFlash('warning', 'Choisissez au moins une colonne à exporter.');

            return $retour;
        }

        $export = $exports->demarrer(
            $this->getUser()?->getUserIdentifier() ?? $actor->id(),
            $data['colonnes'],
            $tout ? null : $data['ids'],
            $filtres->toArray(),
            $nb,
        );
        $bus->dispatch(new GenererReferentielExport($export->idString()));

        return $this->redirectToRoute('app_mdm_referentiel_export_suivi', ['id' => $export->idString()], Response::HTTP_SEE_OTHER);
    }

    /** Page de suivi d'un export : son URL est le code unique, elle se partage et se rouvre. */
    #[Route('/exports/{id}', name: 'referentiel_export_suivi', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET'])]
    public function exportSuivi(string $id, ReferentielExportRepository $exports): Response
    {
        $export = $exports->find(Ulid::fromString($id));
        if (!$export instanceof ReferentielExport) {
            throw $this->createNotFoundException('Export introuvable.');
        }

        return $this->render('mdm/referentiel_export.html.twig', [
            'export' => $export,
            'url_statut' => $this->generateUrl('app_mdm_referentiel_export_statut', ['id' => $id]),
            'url_fichier' => $this->generateUrl('app_mdm_referentiel_export_fichier', ['id' => $id]),
            'form_auto' => $this->createForm(ExportAttenteType::class)->createView(),
        ]);
    }

    /** Statut JSON, sondé par la page de suivi. */
    #[Route('/exports/{id}/statut', name: 'referentiel_export_statut', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET'])]
    public function exportStatut(string $id, ReferentielExportRepository $exports): Response
    {
        $export = $exports->find(Ulid::fromString($id));
        if (!$export instanceof ReferentielExport) {
            throw $this->createNotFoundException('Export introuvable.');
        }
        $response = $this->json(['statut' => $export->statut()]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    /** Le classeur généré, streamé depuis le bucket privé jusqu'à son expiration (30 jours). */
    #[Route('/exports/{id}/fichier', name: 'referentiel_export_fichier', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET'])]
    public function exportFichier(
        string $id,
        ReferentielExportRepository $exports,
        PrivateObjectStorageInterface $storage,
        #[Autowire('%env(S3_PREFIX)%')] string $storagePrefix,
    ): Response {
        $export = $exports->find(Ulid::fromString($id));
        if (!$export instanceof ReferentielExport || !$export->telechargeable()) {
            throw $this->createNotFoundException('Export introuvable, pas encore généré, ou expiré (30 jours).');
        }
        $stream = $storage->readStream(GenererReferentielExportHandler::cle($storagePrefix, $id));
        $response = new StreamedResponse(static function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        });
        $response->headers->set('Content-Type', GenererReferentielExportHandler::CONTENT_TYPE);
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $export->filename()),
        );

        return $response;
    }

    #[Route('/vues', name: 'referentiel_vue_enregistrer', methods: ['POST'])]
    public function enregistrerVue(
        Request $request,
        CurrentActorProvider $actor,
        SavedViewManager $manager,
    ): Response {
        $filtres = ReferentielFiltres::fromArray($request->query->all('f'));
        $form = $this->createForm(SavedViewType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{name: string, shared: bool} $data */
            $data = $form->getData();
            $manager->enregistrer(
                $data['name'],
                $actor->id(),
                $this->getUser()?->getUserIdentifier() ?? $actor->id(),
                $filtres->toArray(),
                $data['shared'],
            );
            $this->addFlash('success', sprintf('Vue « %s » enregistrée.', $data['name']));
        } else {
            $this->addFlash('warning', 'La vue n\'a pas pu être enregistrée : nom manquant.');
        }

        return $this->redirectToRoute('app_mdm_referentiel_general', ['f' => $request->query->all('f')]);
    }

    #[Route('/vues/{id}/supprimer', name: 'referentiel_vue_supprimer', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function supprimerVue(
        Request $request,
        int $id,
        CurrentActorProvider $actor,
        SavedViewRepository $vues,
        SavedViewManager $manager,
        ReferentielEcran $ecran,
    ): Response {
        $vue = $vues->find($id);
        if (!$vue instanceof SavedView) {
            throw $this->createNotFoundException('Vue introuvable.');
        }
        if (!$vue->belongsTo($actor->id())) {
            throw $this->createAccessDeniedException('Seul l\'auteur d\'une vue peut la supprimer.');
        }
        $form = $ecran->formSuppressionVue($vue);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $manager->supprimer($vue);
            $this->addFlash('success', sprintf('Vue « %s » supprimée.', $vue->name()));
        }

        return $this->redirectToRoute('app_mdm_referentiel_general');
    }

}
