<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\Localisation;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Arbitrage humain d'une suggestion d'adresse BAN (bloc « Suggestions en
 * attente » de la fiche et écran Qualité) : Accepter applique la proposition,
 * Ignorer la solde sans rien écrire sur l'adresse. Dans les deux cas l'écart
 * disparaît des deux écrans et ne revient que si l'adresse change.
 */
final readonly class AdresseSuggestionArbitre
{
    /** Niveaux BAN où la voie proposée est fiable ; en dessous (locality, municipality), la rue saisie n'est pas touchée. */
    private const NIVEAUX_RUE = ['housenumber', 'street'];

    public function __construct(
        private InternalFicheMutationPolicy $policy,
        private FicheWorkflowManager $workflow,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Applique la proposition BAN : la rue seulement quand le résultat est au
     * niveau rue/numéro, puis CP, ville et GPS. L'adresse modifiée repasse par
     * IndexFiche, donc par une nouvelle vérification qui reviendra conforme.
     *
     * @throws \DomainException quand rien n'est applicable (pas d'écart, pas de
     *                          proposition, ou adresse modifiée depuis la vérification)
     */
    public function accepter(Fiche $fiche): void
    {
        $localisation = $this->suggestionEnAttente($fiche);
        $proposition = $localisation->banProposition()
            ?? throw new \DomainException('La BAN n\'a proposé aucune adresse fiable : corrigez l\'adresse depuis la section Localisation.');
        if ($localisation->addressFingerprint() !== $localisation->banFingerprint()) {
            throw new \DomainException('L\'adresse a changé depuis la vérification : une nouvelle vérification est en cours, rechargez la page.');
        }
        $this->policy->execute($fiche, static function () use ($localisation, $proposition): void {
            $voie = $proposition['name'] ?? null;
            if (null !== $voie && '' !== $voie && in_array($proposition['type'] ?? null, self::NIVEAUX_RUE, true)) {
                $localisation->changeRuePostale($voie);
            }
            if (null !== ($proposition['codePostal'] ?? null)) {
                $localisation->changeCodePostal($proposition['codePostal']);
            }
            if (null !== ($proposition['ville'] ?? null)) {
                $localisation->changeVille($proposition['ville']);
            }
            if (null !== ($proposition['latitude'] ?? null) && null !== ($proposition['longitude'] ?? null)) {
                try {
                    $localisation->changeLatitude($proposition['latitude']);
                    $localisation->changeLongitude($proposition['longitude']);
                } catch (\InvalidArgumentException) {
                    // Coordonnée BAN inattendue : l'adresse postale suffit.
                }
            }
            $localisation->arbitrerBanSuggestion();
        });
        $this->workflow->indexAndFlush($fiche);
    }

    /**
     * Solde l'écart sans toucher l'adresse : l'utilisateur garde sa saisie.
     *
     * @throws \DomainException quand aucune suggestion n'est en attente
     */
    public function ignorer(Fiche $fiche): void
    {
        $this->suggestionEnAttente($fiche)->arbitrerBanSuggestion();
        $this->entityManager->flush();
    }

    /** @throws \DomainException */
    private function suggestionEnAttente(Fiche $fiche): Localisation
    {
        $localisation = $fiche->localisation();
        if (null === $localisation || !$localisation->banEcart()) {
            throw new \DomainException('Aucune suggestion d\'adresse en attente sur cette fiche.');
        }

        return $localisation;
    }
}
