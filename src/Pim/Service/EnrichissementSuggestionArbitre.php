<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\FicheSuggestion;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\SuggestionAction;
use App\Pim\Repository\ActiviteRepository;
use App\Pim\Repository\LieuRepository;
use App\Pim\Repository\RestaurantRepository;
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
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @throws \DomainException */
    public function accepter(FicheSuggestion $suggestion, string $actor): void
    {
        $this->assertEnAttente($suggestion);
        $suggestion->accepter($actor);
        if (SuggestionAction::Archiver === $suggestion->action()) {
            // Archive et flush (fiche + décision) en une passe.
            $this->workflow->archive($suggestion->fiche(), $actor);

            return;
        }
        $this->remplirChamp($suggestion);
        $this->entityManager->flush();
    }

    /** @throws \DomainException */
    public function ignorer(FicheSuggestion $suggestion, string $actor): void
    {
        $this->assertEnAttente($suggestion);
        $suggestion->ignorer($actor);
        $this->entityManager->flush();
    }

    private function remplirChamp(FicheSuggestion $suggestion): void
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
        $lieu = $this->lieux->findOneByFiche($fiche)
            ?? throw new \DomainException('Fiche sans bloc administratif : impossible d\'appliquer la valeur.');
        $this->policy->execute($fiche, fn () => $this->appliquerLieu($lieu, $suggestion));
    }

    private function appliquerLieu(Lieu $lieu, FicheSuggestion $suggestion): void
    {
        $champ = $suggestion->champ();
        $payload = $suggestion->payload() ?? [];
        if (str_starts_with($champ, 'lieu_lov_')) {
            $codes = self::fusion(
                'BIEN_ETRE' === ($payload['attribut'] ?? null) ? $lieu->bienEtre() : $lieu->installation(),
                $payload,
            );
            match ($payload['attribut'] ?? null) {
                'BIEN_ETRE' => $lieu->changeBienEtre($codes),
                'INSTALLATION' => $lieu->changeInstallation($codes),
                default => throw new \DomainException('Attribut LOV Lieu non applicable.'),
            };

            return;
        }
        match ($champ) {
            'info_legale_siret' => $lieu->administratif()->changeInfoLegaleSiret($suggestion->valeurProposee()),
            'info_legale_num_tva' => $lieu->administratif()->changeInfoLegaleNumTva($suggestion->valeurProposee()),
            'lieu_desc_generale' => $lieu->changeDescGenerale(self::texte($payload)),
            'lieu_chaine' => $lieu->changeChaineHoteliere($suggestion->valeurProposee()),
            default => throw new \DomainException(sprintf('Champ « %s » non applicable.', $champ)),
        };
    }

    private function appliquerActivite(Activite $activite, FicheSuggestion $suggestion): void
    {
        match ($suggestion->champ()) {
            'activite_desc_generale' => $activite->changeDescriptionGenerale(self::texte($suggestion->payload() ?? [])),
            default => throw new \DomainException(sprintf('Champ « %s » non applicable.', $suggestion->champ())),
        };
    }

    /** @param array<string, mixed> $payload */
    private static function texte(array $payload): ?string
    {
        return is_string($payload['text'] ?? null) ? $payload['text'] : null;
    }

    private function appliquerRestaurant(Restaurant $restaurant, FicheSuggestion $suggestion): void
    {
        $payload = $suggestion->payload() ?? [];
        match ($suggestion->champ()) {
            'restaurant_types_cuisine' => $restaurant->changeTypesCuisine(self::fusion($restaurant->typesCuisine(), $payload)),
            'restaurant_specificites' => $restaurant->changeSpecificitesAlimentaires(self::fusion($restaurant->specificitesAlimentaires(), $payload)),
            'restaurant_equipements' => $restaurant->changeEquipements(self::fusion($restaurant->equipements(), $payload)),
            'restaurant_acces_pmr' => $restaurant->changeAccesPmr((bool) ($payload['bool'] ?? false)),
            'restaurant_toilettes_pmr' => $restaurant->changeToilettesPmr((bool) ($payload['bool'] ?? false)),
            'restaurant_site_officiel' => $restaurant->changeSiteOfficiel($suggestion->valeurProposee()),
            default => throw new \DomainException(sprintf('Champ « %s » non applicable.', $suggestion->champ())),
        };
    }

    /**
     * Union des codes LOV existants et proposés (le backfill ajoute, n'écrase pas).
     *
     * @param list<string>         $actuels
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private static function fusion(array $actuels, array $payload): array
    {
        $ajouts = is_array($payload['codes'] ?? null) ? $payload['codes'] : [];

        return array_values(array_unique([...$actuels, ...array_map('strval', $ajouts)]));
    }

    /** @throws \DomainException */
    private function assertEnAttente(FicheSuggestion $suggestion): void
    {
        if (!$suggestion->isPending()) {
            throw new \DomainException('Cette suggestion a déjà été arbitrée.');
        }
    }
}
