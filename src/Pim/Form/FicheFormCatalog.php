<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Dam\Enum\DocumentUsage;
use App\Pim\Entity\FicheAdministratif;
use App\Pim\Lov\LieuLovCatalog;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;

/**
 * Champs communs à toutes les gammes : le bloc « Facturation & partenariat »
 * de la maquette portail (FicheAdministratif, rendu par MethodMappedFieldsType)
 * et ses six pièces jointes déposées en dropzone dans l'onglet.
 */
final class FicheFormCatalog
{
    /** Pièce jointe déposable dans l'onglet : nom de champ => [libellé, usage documentaire]. */
    public const FICHIERS = [
        'urssafFichier' => ['Attestation de vigilance URSSAF', DocumentUsage::Urssaf],
        'rcProFichier' => ['Assurance RC professionnelle', DocumentUsage::LiabilityInsurance],
        'ribFichier' => ['RIB', DocumentUsage::BankDetails],
        'affacturageRibFichier' => ['RIB d’affacturage', DocumentUsage::FactoringBankDetails],
        'conventionFichier' => ['PDF de la convention', DocumentUsage::Convention],
        'cgvFichier' => ['Conditions générales de vente', DocumentUsage::Terms],
    ];

    public const FICHIER_TAILLE_MAX = '5M';

    /** @return array<string, array<string, mixed>> */
    public static function administrative(): array
    {
        $fields = [];
        $texte = static function (string $name, string $label, ?string $completude = null) use (&$fields): void {
            $fields[$name] = ['label' => $label] + (null === $completude ? [] : ['libelle_completude' => $completude]);
        };
        $ouiNon = static function (string $name, string $label) use (&$fields): void {
            $fields[$name] = ['label' => $label, 'type' => OuiNonType::class];
        };
        $entier = static function (string $name, string $label, ?string $completude = null) use (&$fields): void {
            $fields[$name] = ['label' => $label, 'type' => IntegerType::class, 'options' => ['attr' => ['min' => 0, 'max' => 100]]] + (null === $completude ? [] : ['libelle_completude' => $completude]);
        };

        // Informations légales
        $texte('infoLegaleNom', 'Raison sociale');
        $texte('infoLegaleFormeJuridique', 'Forme juridique');
        $texte('infoLegaleRuePostal', 'Rue postale');
        $texte('infoLegaleAdresse2', "Complément d'adresse");
        $texte('infoLegaleCodePostal', 'Code postal');
        $texte('infoLegaleVille', 'Ville');
        $texte('inforLegalePays', 'Pays');
        $texte('infoLegaleSiret', 'N° de SIRET');
        $texte('infoLegaleNumTva', 'N° de TVA');
        $ouiNon('infoLegaleAssujettiTva', 'Assujetti à la TVA');
        $fields['infoLegaleTva'] = self::choice('INFO_LEGALE_TVA', 'TVA au débit ou à l’encaissement');
        $texte('infoLegaleTypeDeProcedureJudiciaire', 'Procédure judiciaire');
        // Adresse de facturation
        $texte('adresseFacturationNom', 'Nom', 'Nom de facturation');
        $texte('adresseFacturationNumTva', 'N° de TVA', 'TVA de facturation');
        $texte('adresseFacturationRuePostal', 'Rue postale', 'Rue de facturation');
        $texte('adresseFacturationCodePostal', 'Code postal', 'Code postal de facturation');
        $texte('adresseFacturationVille', 'Ville', 'Ville de facturation');
        $texte('adresseFacturationPays', 'Pays', 'Pays de facturation');
        // Contact de facturation
        $texte('contactFacturationNom', 'Nom', 'Nom du contact de facturation');
        $texte('contactFacturationPrenom', 'Prénom', 'Prénom du contact de facturation');
        $texte('contactFacturationEmail', 'Email', 'Email de facturation');
        $texte('contactFacturationTelephone', 'N° de téléphone', 'Téléphone de facturation');
        // Mode de paiements acceptés
        $texte('modePaiementBic', 'BIC');
        $texte('modePaiementIban', 'IBAN');
        $ouiNon('modePaiementAffacturage', 'Affacturage');
        $texte('affacturageBic', 'BIC', 'BIC d’affacturage');
        $texte('affacturageIban', 'IBAN', 'IBAN d’affacturage');
        $ouiNon('modePaiementCarte', 'Paiement par carte bancaire');
        $fields['modePaiementCarteListe'] = self::choice('MODE_PAIEMENT_CARTE_LISTE', 'Cartes bancaires acceptées', true);
        $fields['modePaiementAcceptDeductionCom'] = ['label' => 'Accepte la déduction de commission'];
        // Conditions de paiement de l'acompte (3 emplacements)
        for ($i = 1; $i <= FicheAdministratif::ACOMPTES; ++$i) {
            $fields['condPaieAccDate'.$i] = self::choice('COND_PAIE_ACC_SIGNATURE', 'Date d’acompte', false, sprintf('Date d’acompte %d', $i));
            $entier('condPaieAccPourcentage'.$i, '% d’acompte', sprintf('%% d’acompte %d', $i));
        }
        // Conditions de paiement annulation : un pourcentage par tranche LOV
        $tranches = array_values(LieuLovCatalog::choicesFor('COND_PAIE_ANN_SIGNATURE'));
        for ($i = 1; $i <= FicheAdministratif::TRANCHES_ANNULATION; ++$i) {
            $tranche = $tranches[$i - 1] ?? sprintf('Tranche %d', $i);
            $entier('condPaieAnnPourcentage'.$i, $tranche, sprintf('Annulation : %s (%%)', $tranche));
        }
        // Paiement des soldes
        $fields['datePaiementSold'] = self::choice('DATE_PAIEMENT_SOLD', 'Date du paiement');
        // Commission : lecture seule (convention) + champ d'application
        $fields['commissionTaux'] = ['label' => '% de commission', 'lecture_seule' => true, 'options' => [
            'disabled' => true,
            'getter' => static fn (FicheAdministratif $a): ?string => $a->convPartTaux(),
            'setter' => static function (FicheAdministratif &$a, mixed $v): void {},
        ]];
        $fields['commissionPaiement'] = ['label' => 'Paiement', 'lecture_seule' => true, 'options' => [
            'disabled' => true,
            'getter' => static fn (FicheAdministratif $a): string => 'Paiement à réception du solde client',
            'setter' => static function (FicheAdministratif &$a, mixed $v): void {},
        ]];
        $texte('commissionApplicable', 'Champ d’application de la commission');
        // Convention de partenariat
        $texte('convPartSigneeLe', 'Signé le', 'Convention signée le');
        $texte('convPartTaux', 'Taux de commission');
        $texte('signataireEmail', 'Email du signataire');
        $texte('signataireNom', 'Nom du signataire');
        $texte('signatairePrenom', 'Prénom du signataire');

        return $fields;
    }

    /**
     * Les six dropzones de l'onglet (1 fichier, 5 Mo, PDF/JPEG/PNG), hors
     * mapping : DocumentsAdministratifsDepot les range en documents DAM.
     *
     * @param FormBuilderInterface<mixed> $builder
     */
    public static function ajouterFichiers(FormBuilderInterface $builder): void
    {
        foreach (self::FICHIERS as $name => [$label]) {
            $builder->add($name, FileType::class, [
                'label' => $label,
                'help' => 'Un fichier PDF, JPEG ou PNG de 5 Mo maximum — remplace le fichier actuel.',
                'mapped' => false,
                'required' => false,
                'attr' => ['data-dropzone' => true, 'accept' => '.pdf,.jpg,.jpeg,.png', 'data-max-files' => 1, 'data-max-size' => self::FICHIER_TAILLE_MAX],
                'constraints' => [new File(maxSize: self::FICHIER_TAILLE_MAX, mimeTypes: ['application/pdf', 'image/jpeg', 'image/png'])],
            ]);
        }
    }

    /** @return array<string, mixed> */
    private static function choice(string $code, string $label, bool $multiple = false, ?string $completude = null): array
    {
        return [
            'label' => $label,
            'type' => ChoiceType::class,
            'options' => [
                'choices' => array_flip(LieuLovCatalog::choicesFor($code)),
                'multiple' => $multiple,
                'placeholder' => $multiple ? null : 'Non renseigné',
            ],
        ] + (null === $completude ? [] : ['libelle_completude' => $completude]);
    }
}
