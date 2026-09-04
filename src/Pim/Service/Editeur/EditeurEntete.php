<?php

declare(strict_types=1);

namespace App\Pim\Service\Editeur;

use App\Audit\Repository\AuditRevisionRepository;
use App\Etl\Repository\FicheSalesforceRepository;
use App\Etl\Service\SalesforceCsvSettings;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\FicheActionFormFactory;
use App\Pim\Repository\FicheRepository;
use App\Pim\Service\FicheRouteResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * En-tête de l'éditeur : référence, statut, complétude, bandeau de fusion,
 * liens (extraction, traductions, historique), boutons de transition selon
 * le statut, menu « Autres actions » (Salesforce, suppression), bouton
 * « Enrichir », données Salesforce et dernières révisions d'audit.
 */
final readonly class EditeurEntete
{
    /** Boutons d'en-tête : même gabarit que les liens Extraire/Traductions/Historique. */
    // md : aligné sur les boutons d'en-tête et de barre d'actions de la fiche.
    private const BOUTON_SOBRE = ['data-variant' => 'outline', 'data-size' => 'md', 'data-full' => '0'];

    /** Bouton « Enrichir ce qui manque » : primary + icône IA, comme la maquette. */
    private const BOUTON_ENRICHIR = ['data-variant' => 'primary', 'data-size' => 'md', 'data-full' => '0', 'data-icon' => 'ai'];

    /** Items du menu « Autres actions » : texte pleine largeur, alignés à gauche par le conteneur. */
    private const BOUTON_MENU = ['data-variant' => 'text', 'data-size' => 'md', 'data-full' => '1'];

    private const PREFIXES = [
        'lieu' => 'LIE',
        'restaurant' => 'RES',
        'activite' => 'ACT',
        'service_evenementiel' => 'SER',
    ];

    public function __construct(
        private FicheActionFormFactory $actions,
        private FicheRouteResolver $routes,
        private EditeurNavigation $navigation,
        private UrlGeneratorInterface $urls,
        private SalesforceCsvSettings $salesforceCsv,
        private FicheSalesforceRepository $salesforce,
        private FicheRepository $fiches,
        private AuditRevisionRepository $revisions,
    ) {
    }

    /** @return array<string, mixed> */
    public function variables(Lieu|Restaurant|Activite|ServiceEvenementiel $entite): array
    {
        $fiche = $entite->fiche();
        $type = $fiche->type();
        $id = $fiche->idString();
        $statut = $fiche->status()->value;
        $domaine = $type->domaine();

        return [
            'entete' => [
                'reference' => sprintf('%s-%06d', self::PREFIXES[$type->value] ?? 'FIC', $fiche->code()),
                'version' => $fiche->version(),
                'statut' => $statut,
                'completude' => $entite->completeness(),
                'completude_canaux' => $entite->completenessByChannel(),
                'message_refus' => $fiche->validationFeedback(),
                // Fiche absorbée par une fusion : bandeau avec le lien vers la survivante.
                'fusion' => $this->fusion($fiche),
            ],
            'liens' => [
                // Le dépôt et la revue vivent dans la section extraction de l'éditeur.
                'ocr' => $this->navigation->urlExtraction($type, $id),
                'ocr_admin' => $this->urls->generate('app_ocr_index', ['id' => $id]),
                'traductions' => $this->urls->generate('app_enrichment_fiche_translation_show', ['id' => $id]),
                'historique' => $this->routes->historyUrl($type, $id),
            ],
            'actions' => array_filter([
                'submit' => 'en_cours' === $statut ? $this->actions->action($domaine, $id, 'submit', 'Soumettre à validation', buttonAttr: self::BOUTON_SOBRE)->createView() : null,
                'validate' => 'en_attente_validation' === $statut ? $this->actions->action($domaine, $id, 'validate', 'Valider', buttonAttr: self::BOUTON_SOBRE)->createView() : null,
                // Raccourci validateur : les deux transitions en un clic (la
                // publication reste retenue si les photos ne sont pas conformes).
                'validate_and_publish' => 'en_attente_validation' === $statut ? $this->actions->validerPublier($id, self::BOUTON_SOBRE)->createView() : null,
                'reject' => 'en_attente_validation' === $statut ? $this->actions->reject($domaine, $id)->createView() : null,
                'publish' => 'validee' === $statut ? $this->actions->action($domaine, $id, 'publish', 'Publier', buttonAttr: self::BOUTON_SOBRE)->createView() : null,
                'archive' => 'publiee' === $statut ? $this->actions->action($domaine, $id, 'archive', 'Archiver', buttonAttr: self::BOUTON_SOBRE)->createView() : null,
                // Depuis « archivée » : remise en cours ou republication directe.
                'unarchive' => 'archivee' === $statut ? $this->actions->action($domaine, $id, 'unarchive', 'Désarchiver', buttonAttr: self::BOUTON_SOBRE)->createView() : null,
                'republish' => 'archivee' === $statut ? $this->actions->action($domaine, $id, 'republish', 'Republier', buttonAttr: self::BOUTON_SOBRE)->createView() : null,
            ]),
            // Bouton « Envoyer à Salesforce » (système de transition CSV
            // e-mail) : présent seulement quand la synchro est configurée.
            'salesforce_envoi' => $this->salesforceCsv->isConfigured()
                ? $this->actions->salesforce($id, self::BOUTON_MENU)->createView()
                : null,
            // Bouton « Enrichir ce qui manque » : toujours affiché, les gates
            // par source s'appliquent au traitement (une source inactive est
            // simplement sautée par le scan).
            'action_enrichir' => $this->actions->enrichir($id, self::BOUTON_ENRICHIR)->createView(),
            'action_suppression' => $this->actions->action($domaine, $id, 'delete', 'Supprimer', true, match ($type) {
                TypeFiche::Lieu => 'Supprimer ce lieu ?',
                TypeFiche::Activite => 'Supprimer cette activité ?',
                TypeFiche::Restaurant => 'Supprimer ce restaurant ?',
                default => 'Supprimer ce service ?',
            }, self::BOUTON_MENU)->createView(),
            'historique' => $this->revisions->history($id, null, 3),
            // Données Salesforce en lecture seule (refresh quotidien) ;
            // false = fiche inconnue de Salesforce.
            'salesforce' => $this->salesforce->forFiche($fiche->id()) ?? false,
        ];
    }

    /**
     * Bandeau « Fusionnée » d'une fiche absorbée : libellé et lien vers la
     * fiche survivante (lien absent si elle a disparu depuis).
     *
     * @return array{label: string, url: ?string}|null
     */
    private function fusion(Fiche $fiche): ?array
    {
        $survivantId = $fiche->mergedIntoId();
        if (null === $survivantId) {
            return null;
        }
        $survivante = $this->fiches->find($survivantId);
        if (!$survivante instanceof Fiche) {
            return ['label' => (string) $survivantId, 'url' => null];
        }

        return [
            'label' => $survivante->label() ?? sprintf('fiche %d', $survivante->code()),
            'url' => $this->routes->editUrl($survivante->type(), $survivante->idString()),
        ];
    }
}
