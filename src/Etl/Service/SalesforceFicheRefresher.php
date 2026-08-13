<?php

declare(strict_types=1);

namespace App\Etl\Service;

use App\Etl\Entity\FicheSalesforce;
use App\Etl\Repository\FicheSalesforceRepository;
use App\Pim\Entity\Fiche;
use App\Pim\Repository\FicheRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Rafraîchit les données Salesforce des fiches (notes RSE, évaluation
 * client, procédure judiciaire, contrats avec comptes, statut partenaire).
 * Match par code fiche = Product2.ID__c (= bp_produit.syspad_id). Écriture
 * uniquement en cas de changement ; toute fiche modifiée est replanifiée
 * vers la marketplace (outbox, même transaction que le flush du lot).
 *
 * Salesforce est la source de vérité du statut partenaire : partenaireBp
 * est écrasé (mise à jour technique, sans transition de workflow) pour
 * toute fiche connue de Salesforce.
 */
final class SalesforceFicheRefresher
{
    private const CONTRACT_FIELDS = [
        'Contrat_avec_comptes_Achat_normal__c',
        'Contrat_avec_comptes_Mise_en_relation__c',
        'Contrat_avec_comptes_Ni_com_ni_frais__c',
    ];

    private const FIELDS = [
        ...self::CONTRACT_FIELDS,
        'RSE_Achats_responsables__c',
        'RSE_Impact_environnemental__c',
        'RSE_Impact_social__c',
        'RSE_Mobilite__c',
        'RSE_Labels_et_demarche_qualite__c',
        'RSE_Note_globale__c',
        'Type_de_procedure_judiciaire__c',
        'Evaluation_client__c',
        'commission_standard__c',
    ];

    private const BATCH_SIZE = 50;

    public function __construct(
        private readonly SalesforceClientInterface $salesforce,
        private readonly FicheRepository $fiches,
        private readonly FicheSalesforceRepository $donneesSalesforce,
        private readonly MarketplaceSyncScheduler $marketplaceScheduler,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{recus: int, matches: int, modifies: int, inconnus: int}
     *
     * @throws SalesforceApiException
     */
    public function refresh(?int $code = null): array
    {
        $soql = sprintf(
            'SELECT Id, ID__c, %s FROM Product2 WHERE ID__c != NULL',
            implode(', ', self::FIELDS),
        );
        if (null !== $code) {
            $soql .= sprintf(" AND ID__c = '%d'", $code);
        }
        $records = $this->salesforce->query($soql);
        $stats = ['recus' => count($records), 'matches' => 0, 'modifies' => 0, 'inconnus' => 0];
        $lot = 0;
        foreach ($records as $record) {
            $codeFiche = (int) (is_scalar($record['ID__c'] ?? null) ? $record['ID__c'] : 0);
            if ($codeFiche < 1) {
                ++$stats['inconnus'];
                continue;
            }
            $fiche = $this->fiches->findOneBy(['code' => $codeFiche]);
            if (!$fiche instanceof Fiche) {
                ++$stats['inconnus'];
                continue;
            }
            ++$stats['matches'];
            if ($this->appliquer($fiche, $codeFiche, $record)) {
                ++$stats['modifies'];
                $this->marketplaceScheduler->schedule($fiche);
            }
            if (++$lot >= self::BATCH_SIZE) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $lot = 0;
            }
        }
        $this->entityManager->flush();
        $this->entityManager->clear();
        $this->logger->info('Refresh Salesforce des fiches terminé.', $stats);

        return $stats;
    }

    /** @param array<string, mixed> $record */
    private function appliquer(Fiche $fiche, int $code, array $record): bool
    {
        $donnees = $this->donneesSalesforce->forFiche($fiche->id());
        if (null === $donnees) {
            $donnees = new FicheSalesforce($fiche->id(), $code);
            $this->entityManager->persist($donnees);
        }
        // Règle legacy (app:update-product-from-salesforce) : une évaluation
        // client à zéro signifie « pas de note ».
        $evaluation = self::floatOrNull($record['Evaluation_client__c'] ?? null);
        $modifie = $donnees->mettreAJour(
            self::floatOrNull($record['RSE_Achats_responsables__c'] ?? null),
            self::floatOrNull($record['RSE_Impact_environnemental__c'] ?? null),
            self::floatOrNull($record['RSE_Impact_social__c'] ?? null),
            self::floatOrNull($record['RSE_Mobilite__c'] ?? null),
            self::floatOrNull($record['RSE_Labels_et_demarche_qualite__c'] ?? null),
            self::floatOrNull($record['RSE_Note_globale__c'] ?? null),
            0.0 === $evaluation ? null : $evaluation,
            self::stringOrNull($record['Type_de_procedure_judiciaire__c'] ?? null),
            $this->contrats($record),
        );
        // Règle legacy (app:update-sf-ispartner) : seule une commission
        // standard explicitement nulle retire le statut partenaire.
        $commission = $record['commission_standard__c'] ?? null;
        $partenaire = null === $commission || !is_numeric($commission) || (float) $commission !== 0.0;
        if ($partenaire !== $fiche->partenaireBp()) {
            $fiche->preserveWorkflowDuring(
                static fn () => $fiche->changePartenaireBp($partenaire),
            );
            $modifie = true;
        }

        return $modifie;
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return list<string>
     */
    private function contrats(array $record): array
    {
        $contrats = [];
        foreach (self::CONTRACT_FIELDS as $field) {
            $valeur = $record[$field] ?? null;
            if (!is_string($valeur)) {
                continue;
            }
            foreach (explode(';', $valeur) as $contrat) {
                $contrat = trim($contrat);
                if ('' !== $contrat && !in_array($contrat, $contrats, true)) {
                    $contrats[] = $contrat;
                }
            }
        }

        return $contrats;
    }

    private static function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }
}
