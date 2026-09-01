<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\FicheSuggestion;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\SuggestionAction;
use App\Pim\Lov\LieuLovCatalog;
use App\Pim\Lov\RestaurantLovCatalog;
use App\Pim\Repository\ActiviteRepository;
use App\Pim\Repository\AttributDefinitionRepository;
use App\Pim\Repository\LieuRepository;
use App\Pim\Repository\RestaurantRepository;
use App\Pim\Repository\ServiceEvenementielRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Arbitrage humain d'une suggestion d'enrichissement générique (bloc
 * « Suggestions en attente ») : Accepter applique la décision, Ignorer la solde.
 * Un backfill de champ passe par la politique de mutation interne (pas de
 * transition de workflow) ; une proposition d'archivage emprunte le workflow
 * habituel (dépublication marketplace en aval).
 */
final readonly class EnrichissementSuggestionArbitre
{
    public function __construct(
        private InternalFicheMutationPolicy $policy,
        private FicheWorkflowManager $workflow,
        private LieuRepository $lieux,
        private RestaurantRepository $restaurants,
        private ActiviteRepository $activites,
        private ServiceEvenementielRepository $services,
        private AttributDefinitionRepository $attributs,
        private LovAdminManager $lovAdmin,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @throws \DomainException */
    public function accepter(FicheSuggestion $suggestion, string $actor): void
    {
        $this->assertEnAttente($suggestion);
        // Applique avant de décider : en arbitrage groupé, une application qui
        // échoue ne doit pas laisser un statut « acceptée » dans l'unit of work,
        // que le flush d'une ligne suivante persisterait sans effet sur la fiche.
        if (SuggestionAction::Archiver === $suggestion->action()) {
            $this->workflow->archive($suggestion->fiche(), $actor);
            $suggestion->accepter($actor);
            $this->entityManager->flush();

            return;
        }
        $this->remplirChamp($suggestion, $actor);
        $suggestion->accepter($actor);
        // La valeur appliquée doit repasser par IndexFiche (complétude, resync
        // marketplace), comme tout autre chemin d'écriture.
        $this->workflow->indexAndFlush($suggestion->fiche());
    }

    /** @throws \DomainException */
    public function ignorer(FicheSuggestion $suggestion, string $actor): void
    {
        $this->assertEnAttente($suggestion);
        $suggestion->ignorer($actor);
        $this->entityManager->flush();
    }

    private function remplirChamp(FicheSuggestion $suggestion, string $actor): void
    {
        $champ = $suggestion->champ();
        $fiche = $suggestion->fiche();
        if (str_starts_with($champ, 'restaurant_')) {
            $restaurant = $this->restaurants->findOneByFiche($fiche)
                ?? throw new \DomainException('Restaurant introuvable : impossible d\'appliquer la valeur.');
            $this->policy->execute($fiche, fn () => $this->appliquerRestaurant($restaurant, $suggestion));

            return;
        }
        if (str_starts_with($champ, 'activite_')) {
            $activite = $this->activites->findOneByFiche($fiche)
                ?? throw new \DomainException('Activité introuvable : impossible d\'appliquer la valeur.');
            $this->policy->execute($fiche, fn () => $this->appliquerActivite($activite, $suggestion));

            return;
        }
        if (str_starts_with($champ, 'service_')) {
            $service = $this->services->findOneByFiche($fiche)
                ?? throw new \DomainException('Service introuvable : impossible d\'appliquer la valeur.');
            $this->policy->execute($fiche, fn () => $this->appliquerService($service, $suggestion));

            return;
        }
        $lieu = $this->lieux->findOneByFiche($fiche)
            ?? throw new \DomainException('Fiche sans bloc administratif : impossible d\'appliquer la valeur.');
        $this->policy->execute($fiche, fn () => $this->appliquerLieu($lieu, $suggestion, $actor));
    }

    private function appliquerLieu(Lieu $lieu, FicheSuggestion $suggestion, string $actor): void
    {
        $champ = $suggestion->champ();
        $payload = $suggestion->payload() ?? [];
        if ('lieu_chaine' === $champ) {
            $this->appliquerChaine($lieu, $suggestion->valeurProposee(), $actor);

            return;
        }
        if (str_starts_with($champ, 'lieu_lov_')) {
            $attribut = $payload['attribut'] ?? null;
            $codes = self::fusion(match ($attribut) {
                'BIEN_ETRE' => $lieu->bienEtre(),
                'INSTALLATION' => $lieu->installation(),
                'GENERALE_TYPOLOGIE' => $lieu->generaleTypologie(),
                default => throw new \DomainException('Attribut LOV Lieu non applicable.'),
            }, $payload, LieuLovCatalog::choicesFor((string) $attribut));
            match ($attribut) {
                'BIEN_ETRE' => $lieu->changeBienEtre($codes),
                'INSTALLATION' => $lieu->changeInstallation($codes),
                default => $lieu->changeGeneraleTypologie($codes),
            };

            return;
        }
        // Champs non textuels : la garde de fraîcheur par chaîne ne s'applique
        // pas, une garde métier « encore vide » la remplace.
        if ('lieu_pmr_acces' === $champ) {
            if ($lieu->pmrAcces()) {
                throw new \DomainException('La case Accès PMR est déjà cochée : suggestion périmée.');
            }
            if (true !== ($payload['bool'] ?? null)) {
                throw new \DomainException('Suggestion PMR sans valeur exploitable.');
            }
            $lieu->changePmrAcces(true);

            return;
        }
        if ('lieu_chambre_nb_total' === $champ) {
            if (null !== $lieu->chambreNbTotal()) {
                throw new \DomainException('Le nombre de chambres a été saisi depuis le scan : suggestion périmée.');
            }
            if (!is_numeric($payload['int'] ?? null)) {
                throw new \DomainException('Suggestion sans nombre de chambres exploitable.');
            }
            $lieu->changeChambreNbTotal((int) $payload['int']);

            return;
        }
        [$courante, $appliquer] = match ($champ) {
            'info_legale_siret' => [$lieu->administratif()->infoLegaleSiret(), fn () => $lieu->administratif()->changeInfoLegaleSiret($suggestion->valeurProposee())],
            'info_legale_num_tva' => [$lieu->administratif()->infoLegaleNumTva(), fn () => $lieu->administratif()->changeInfoLegaleNumTva($suggestion->valeurProposee())],
            'info_legale_forme_juridique' => [$lieu->administratif()->infoLegaleFormeJuridique(), fn () => $lieu->administratif()->changeInfoLegaleFormeJuridique($suggestion->valeurProposee())],
            'info_legale_nom' => [$lieu->administratif()->infoLegaleNom(), fn () => $lieu->administratif()->changeInfoLegaleNom($suggestion->valeurProposee())],
            'lieu_desc_generale' => [$lieu->descGenerale(), fn () => $lieu->changeDescGenerale(self::texte($payload))],
            'lieu_website' => [$lieu->generaleWebsiteUrl(), fn () => $lieu->changeGeneraleWebsiteUrl($suggestion->valeurProposee())],
            default => throw new \DomainException(sprintf('Champ « %s » non applicable.', $champ)),
        };
        $this->assertFraicheur($courante, $suggestion);
        $appliquer();
    }

    /**
     * La chaîne détectée alimente le sélecteur LOV « Groupe et chaîne
     * hôtelière » — l'unique champ chaîne de la fiche. Sémantique d'union
     * (multi-select : on ajoute une enseigne, on n'écrase rien), donc pas de
     * garde de fraîcheur. Un libellé absent de la liste crée la valeur LOV à
     * la volée (visible dans /admin/listes-de-valeurs, dictionnaire
     * marketplace resynchronisé).
     */
    private function appliquerChaine(Lieu $lieu, ?string $chaine, string $actor): void
    {
        $chaine = trim((string) $chaine);
        if ('' === $chaine) {
            throw new \DomainException('Suggestion sans proposition de chaîne.');
        }
        $code = ChaineLovResolution::codePour($chaine) ?? $this->creerValeurChaine($chaine, $actor);
        if (!in_array($code, $lieu->generaleChainesGroupeHot(), true)) {
            $lieu->changeGeneraleChainesGroupeHot([...$lieu->generaleChainesGroupeHot(), $code]);
        }
    }

    /** Crée la valeur LOV manquante et retourne son code (réutilise le code en cas de collision de slug). */
    private function creerValeurChaine(string $chaine, string $actor): string
    {
        $attribut = $this->attributs->findOneByCode('GENERALE_CHAINES_GROUPE_HOT')
            ?? throw new \DomainException('Attribut « Groupe et chaîne hôtelière » introuvable.');
        $choix = LieuLovCatalog::choicesFor('GENERALE_CHAINES_GROUPE_HOT');
        $code = self::codeDepuisLibelle($chaine);
        if (array_key_exists($code, $choix)) {
            return $code;
        }
        $this->lovAdmin->create($attribut, [
            'code' => $code,
            'label' => $chaine,
            'position' => count($choix) + 1,
            'active' => true,
        ], $actor);

        return $code;
    }

    /** Code LOV dérivé du libellé, au format de l'admin des listes de valeurs. */
    private static function codeDepuisLibelle(string $chaine): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $chaine);
        $slug = strtoupper(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', false === $ascii ? $chaine : $ascii), '_'));

        return mb_substr('GENERALE_CHAINES_GROUPE_HOT_'.('' === $slug ? 'AUTRE' : $slug), 0, 96);
    }

    private function appliquerActivite(Activite $activite, FicheSuggestion $suggestion): void
    {
        if ('activite_desc_generale' !== $suggestion->champ()) {
            throw new \DomainException(sprintf('Champ « %s » non applicable.', $suggestion->champ()));
        }
        $this->assertFraicheur($activite->descriptionGenerale(), $suggestion);
        $activite->changeDescriptionGenerale(self::texte($suggestion->payload() ?? []));
    }

    private function appliquerService(ServiceEvenementiel $service, FicheSuggestion $suggestion): void
    {
        if ('service_desc_generale' !== $suggestion->champ()) {
            throw new \DomainException(sprintf('Champ « %s » non applicable.', $suggestion->champ()));
        }
        $this->assertFraicheur($service->descriptionGenerale(), $suggestion);
        $service->changeDescriptionGenerale(self::texte($suggestion->payload() ?? []));
    }

    /** @param array<string, mixed> $payload */
    private static function texte(array $payload): ?string
    {
        return is_string($payload['text'] ?? null) ? $payload['text'] : null;
    }

    private function appliquerRestaurant(Restaurant $restaurant, FicheSuggestion $suggestion): void
    {
        $payload = $suggestion->payload() ?? [];
        if ('restaurant_site_officiel' === $suggestion->champ()) {
            $this->assertFraicheur($restaurant->siteOfficiel(), $suggestion);
            $restaurant->changeSiteOfficiel($suggestion->valeurProposee());

            return;
        }
        if ('restaurant_desc_generale' === $suggestion->champ()) {
            $this->assertFraicheur($restaurant->descriptionGenerale(), $suggestion);
            $restaurant->changeDescriptionGenerale(self::texte($payload));

            return;
        }
        match ($suggestion->champ()) {
            'restaurant_types_cuisine' => $restaurant->changeTypesCuisine(self::fusion($restaurant->typesCuisine(), $payload, RestaurantLovCatalog::values('TYPE_CUISINE'))),
            'restaurant_specificites' => $restaurant->changeSpecificitesAlimentaires(self::fusion($restaurant->specificitesAlimentaires(), $payload, RestaurantLovCatalog::values('SPECIFICITE_ALIMENTAIRE'))),
            'restaurant_equipements' => $restaurant->changeEquipements(self::fusion($restaurant->equipements(), $payload, RestaurantLovCatalog::values('EQUIPEMENT_RESTAURANT'))),
            'restaurant_acces_pmr' => $restaurant->changeAccesPmr((bool) ($payload['bool'] ?? false)),
            'restaurant_toilettes_pmr' => $restaurant->changeToilettesPmr((bool) ($payload['bool'] ?? false)),
            default => throw new \DomainException(sprintf('Champ « %s » non applicable.', $suggestion->champ())),
        };
    }

    /**
     * Union des codes LOV existants et proposés (le backfill ajoute, n'écrase
     * pas). Chaque code proposé est résolu contre le catalogue EFFECTIF au
     * moment de l'accept (le référentiel a pu évoluer depuis le scan) : un code
     * d'un autre schéma ou en forme libellé est rattrapé par libellé, et si
     * plus rien ne se résout la suggestion est périmée.
     *
     * @param list<string>          $actuels
     * @param array<string, mixed>  $payload
     * @param array<string, string> $choices code → libellé du catalogue effectif
     *
     * @return list<string>
     */
    private static function fusion(array $actuels, array $payload, array $choices): array
    {
        $ajouts = is_array($payload['codes'] ?? null) ? $payload['codes'] : [];
        $resolus = [];
        foreach ($ajouts as $candidat) {
            $code = LovValeurResolution::codePour($choices, (string) $candidat);
            if (null !== $code) {
                $resolus[] = $code;
            }
        }
        if ([] !== $ajouts && [] === $resolus) {
            throw new \DomainException('Les valeurs proposées ne correspondent plus à la liste : suggestion périmée.');
        }

        return array_values(array_unique([...$actuels, ...$resolus]));
    }

    /**
     * La colonne « Actuel » de l'écran reflète la valeur au moment du scan : si
     * le champ a changé depuis (saisie manuelle), accepter écraserait cette
     * saisie — la suggestion reste en attente jusqu'au prochain scan.
     *
     * @throws \DomainException
     */
    private function assertFraicheur(?string $courante, FicheSuggestion $suggestion): void
    {
        if (self::normalise($courante) !== $suggestion->valeurActuelle()) {
            throw new \DomainException('La valeur du champ a changé depuis le scan : suggestion périmée.');
        }
    }

    /** Même normalisation que le stockage de valeurActuelle (trim, vide = null, 500 caractères). */
    private static function normalise(?string $valeur): ?string
    {
        $valeur = trim((string) $valeur);

        return '' === $valeur ? null : mb_substr($valeur, 0, 500);
    }

    /** @throws \DomainException */
    private function assertEnAttente(FicheSuggestion $suggestion): void
    {
        if (!$suggestion->isPending()) {
            throw new \DomainException('Cette suggestion a déjà été arbitrée.');
        }
    }
}
