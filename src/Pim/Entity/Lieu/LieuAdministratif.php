<?php

declare(strict_types=1);

namespace App\Pim\Entity\Lieu;

use App\Pim\Lov\LieuLovCatalog;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'pim_lieu_administratif')]
class LieuAdministratif
{
    #[ORM\Id]
    #[ORM\OneToOne(inversedBy: 'administratif')]
    #[ORM\JoinColumn(name: 'lieu_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Lieu $lieu;

    #[ORM\Column(name: 'info_legale_nom', length: 255, nullable: true)] private ?string $infoLegaleNom = null;
    #[ORM\Column(name: 'info_legale_forme_juridique', length: 255, nullable: true)] private ?string $infoLegaleFormeJuridique = null;
    #[ORM\Column(name: 'info_legale_rue_postal', length: 255, nullable: true)] private ?string $infoLegaleRuePostal = null;
    #[ORM\Column(name: 'info_legale_adresse_2', length: 255, nullable: true)] private ?string $infoLegaleAdresse2 = null;
    #[ORM\Column(name: 'info_legale_code_postal', length: 255, nullable: true)] private ?string $infoLegaleCodePostal = null;
    #[ORM\Column(name: 'info_legale_ville', length: 255, nullable: true)] private ?string $infoLegaleVille = null;
    #[ORM\Column(name: 'infor_legale_pays', length: 255, nullable: true)] private ?string $inforLegalePays = null;
    #[ORM\Column(name: 'info_legale_siret', length: 255, nullable: true)] private ?string $infoLegaleSiret = null;
    #[ORM\Column(name: 'info_legale_num_tva', length: 255, nullable: true)] private ?string $infoLegaleNumTva = null;
    #[ORM\Column(name: 'info_legale_assujetti_tva', nullable: true)] private ?bool $infoLegaleAssujettiTva = null;
    #[ORM\Column(name: 'info_legale_tva', length: 255, nullable: true)] private ?string $infoLegaleTva = null;
    #[ORM\Column(name: 'info_legale_type_de_procedure_judiciaire', length: 255, nullable: true)] private ?string $infoLegaleTypeDeProcedureJudiciaire = null;
    #[ORM\Column(name: 'adresse_facturation_nom', length: 255, nullable: true)] private ?string $adresseFacturationNom = null;
    #[ORM\Column(name: 'adresse_facturation_rue_postal', length: 255, nullable: true)] private ?string $adresseFacturationRuePostal = null;
    #[ORM\Column(name: 'adresse_facturation_code_postal', length: 255, nullable: true)] private ?string $adresseFacturationCodePostal = null;
    #[ORM\Column(name: 'adresse_facturation_ville', length: 255, nullable: true)] private ?string $adresseFacturationVille = null;
    #[ORM\Column(name: 'adresse_facturation_pays', length: 255, nullable: true)] private ?string $adresseFacturationPays = null;
    #[ORM\Column(name: 'adresse_facturation_num_tva', length: 255, nullable: true)] private ?string $adresseFacturationNumTva = null;
    #[ORM\Column(name: 'contact_facturation_nom', length: 255, nullable: true)] private ?string $contactFacturationNom = null;
    #[ORM\Column(name: 'contact_facturation_prenom', length: 255, nullable: true)] private ?string $contactFacturationPrenom = null;
    #[ORM\Column(name: 'contact_facturation_email', length: 255, nullable: true)] private ?string $contactFacturationEmail = null;
    #[ORM\Column(name: 'contact_facturation_telephone', length: 255, nullable: true)] private ?string $contactFacturationTelephone = null;
    #[ORM\Column(name: 'mode_paiement_bic', length: 255, nullable: true)] private ?string $modePaiementBic = null;
    #[ORM\Column(name: 'mode_paiement_iban', length: 255, nullable: true)] private ?string $modePaiementIban = null;
    #[ORM\Column(name: 'mode_paiement_accept_deduction_com', nullable: true)] private ?bool $modePaiementAcceptDeductionCom = null;
    #[ORM\Column(name: 'mode_paiement_affacturage', nullable: true)] private ?bool $modePaiementAffacturage = null;
    #[ORM\Column(name: 'affacturage_bic', length: 255, nullable: true)] private ?string $affacturageBic = null;
    #[ORM\Column(name: 'affacturage_iban', length: 255, nullable: true)] private ?string $affacturageIban = null;
    #[ORM\Column(name: 'cond_paie_acc_signature', length: 255, nullable: true)] private ?string $condPaieAccSignature = null;
    #[ORM\Column(name: 'cond_paie_ann_signature', length: 255, nullable: true)] private ?string $condPaieAnnSignature = null;
    #[ORM\Column(name: 'commission_applicable', length: 255, nullable: true)] private ?string $commissionApplicable = null;
    #[ORM\Column(name: 'date_paiement_sold', length: 255, nullable: true)] private ?string $datePaiementSold = null;
    #[ORM\Column(name: 'conv_part_signee_le', length: 255, nullable: true)] private ?string $convPartSigneeLe = null;
    #[ORM\Column(name: 'conv_part_taux', length: 255, nullable: true)] private ?string $convPartTaux = null;
    #[ORM\Column(name: 'signataire_email', length: 255, nullable: true)] private ?string $signataireEmail = null;
    #[ORM\Column(name: 'signataire_prenom', length: 255, nullable: true)] private ?string $signatairePrenom = null;
    #[ORM\Column(name: 'signataire_nom', length: 255, nullable: true)] private ?string $signataireNom = null;
    public function __construct(Lieu $lieu)
    {
        $this->lieu = $lieu;
    }

    public function infoLegaleNom(): ?string
    {
        return $this->infoLegaleNom;
    }

    public function changeInfoLegaleNom(?string $value): void
    {
        $this->infoLegaleNom = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function infoLegaleFormeJuridique(): ?string
    {
        return $this->infoLegaleFormeJuridique;
    }

    public function changeInfoLegaleFormeJuridique(?string $value): void
    {
        $this->infoLegaleFormeJuridique = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function infoLegaleRuePostal(): ?string
    {
        return $this->infoLegaleRuePostal;
    }

    public function changeInfoLegaleRuePostal(?string $value): void
    {
        $this->infoLegaleRuePostal = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function infoLegaleAdresse2(): ?string
    {
        return $this->infoLegaleAdresse2;
    }

    public function changeInfoLegaleAdresse2(?string $value): void
    {
        $this->infoLegaleAdresse2 = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function infoLegaleCodePostal(): ?string
    {
        return $this->infoLegaleCodePostal;
    }

    public function changeInfoLegaleCodePostal(?string $value): void
    {
        $this->infoLegaleCodePostal = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function infoLegaleVille(): ?string
    {
        return $this->infoLegaleVille;
    }

    public function changeInfoLegaleVille(?string $value): void
    {
        $this->infoLegaleVille = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function inforLegalePays(): ?string
    {
        return $this->inforLegalePays;
    }

    public function changeInforLegalePays(?string $value): void
    {
        $this->inforLegalePays = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function infoLegaleSiret(): ?string
    {
        return $this->infoLegaleSiret;
    }

    public function changeInfoLegaleSiret(?string $value): void
    {
        $this->infoLegaleSiret = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function infoLegaleNumTva(): ?string
    {
        return $this->infoLegaleNumTva;
    }

    public function changeInfoLegaleNumTva(?string $value): void
    {
        $this->infoLegaleNumTva = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function infoLegaleAssujettiTva(): ?bool
    {
        return $this->infoLegaleAssujettiTva;
    }

    public function changeInfoLegaleAssujettiTva(?bool $value): void
    {
        $this->infoLegaleAssujettiTva = $value;
        $this->lieu->markChanged();
    }

    public function infoLegaleTva(): ?string
    {
        return $this->infoLegaleTva;
    }

    public function changeInfoLegaleTva(?string $value): void
    {
        LieuLovCatalog::assertValid('INFO_LEGALE_TVA', $value);
        $this->infoLegaleTva = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function infoLegaleTypeDeProcedureJudiciaire(): ?string
    {
        return $this->infoLegaleTypeDeProcedureJudiciaire;
    }

    public function changeInfoLegaleTypeDeProcedureJudiciaire(?string $value): void
    {
        $this->infoLegaleTypeDeProcedureJudiciaire = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function adresseFacturationNom(): ?string
    {
        return $this->adresseFacturationNom;
    }

    public function changeAdresseFacturationNom(?string $value): void
    {
        $this->adresseFacturationNom = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function adresseFacturationRuePostal(): ?string
    {
        return $this->adresseFacturationRuePostal;
    }

    public function changeAdresseFacturationRuePostal(?string $value): void
    {
        $this->adresseFacturationRuePostal = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function adresseFacturationCodePostal(): ?string
    {
        return $this->adresseFacturationCodePostal;
    }

    public function changeAdresseFacturationCodePostal(?string $value): void
    {
        $this->adresseFacturationCodePostal = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function adresseFacturationVille(): ?string
    {
        return $this->adresseFacturationVille;
    }

    public function changeAdresseFacturationVille(?string $value): void
    {
        $this->adresseFacturationVille = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function adresseFacturationPays(): ?string
    {
        return $this->adresseFacturationPays;
    }

    public function changeAdresseFacturationPays(?string $value): void
    {
        $this->adresseFacturationPays = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function adresseFacturationNumTva(): ?string
    {
        return $this->adresseFacturationNumTva;
    }

    public function changeAdresseFacturationNumTva(?string $value): void
    {
        $this->adresseFacturationNumTva = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function contactFacturationNom(): ?string
    {
        return $this->contactFacturationNom;
    }

    public function changeContactFacturationNom(?string $value): void
    {
        $this->contactFacturationNom = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function contactFacturationPrenom(): ?string
    {
        return $this->contactFacturationPrenom;
    }

    public function changeContactFacturationPrenom(?string $value): void
    {
        $this->contactFacturationPrenom = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function contactFacturationEmail(): ?string
    {
        return $this->contactFacturationEmail;
    }

    public function changeContactFacturationEmail(?string $value): void
    {
        $this->contactFacturationEmail = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function contactFacturationTelephone(): ?string
    {
        return $this->contactFacturationTelephone;
    }

    public function changeContactFacturationTelephone(?string $value): void
    {
        $this->contactFacturationTelephone = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function modePaiementBic(): ?string
    {
        return $this->modePaiementBic;
    }

    public function changeModePaiementBic(?string $value): void
    {
        $this->modePaiementBic = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function modePaiementIban(): ?string
    {
        return $this->modePaiementIban;
    }

    public function changeModePaiementIban(?string $value): void
    {
        $this->modePaiementIban = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function modePaiementAcceptDeductionCom(): ?bool
    {
        return $this->modePaiementAcceptDeductionCom;
    }

    public function changeModePaiementAcceptDeductionCom(?bool $value): void
    {
        $this->modePaiementAcceptDeductionCom = $value;
        $this->lieu->markChanged();
    }

    public function modePaiementAffacturage(): ?bool
    {
        return $this->modePaiementAffacturage;
    }

    public function changeModePaiementAffacturage(?bool $value): void
    {
        $this->modePaiementAffacturage = $value;
        $this->lieu->markChanged();
    }

    public function affacturageBic(): ?string
    {
        return $this->affacturageBic;
    }

    public function changeAffacturageBic(?string $value): void
    {
        $this->affacturageBic = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function affacturageIban(): ?string
    {
        return $this->affacturageIban;
    }

    public function changeAffacturageIban(?string $value): void
    {
        $this->affacturageIban = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function condPaieAccSignature(): ?string
    {
        return $this->condPaieAccSignature;
    }

    public function changeCondPaieAccSignature(?string $value): void
    {
        LieuLovCatalog::assertValid('COND_PAIE_ACC_SIGNATURE', $value);
        $this->condPaieAccSignature = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function condPaieAnnSignature(): ?string
    {
        return $this->condPaieAnnSignature;
    }

    public function changeCondPaieAnnSignature(?string $value): void
    {
        LieuLovCatalog::assertValid('COND_PAIE_ANN_SIGNATURE', $value);
        $this->condPaieAnnSignature = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function commissionApplicable(): ?string
    {
        return $this->commissionApplicable;
    }

    public function changeCommissionApplicable(?string $value): void
    {
        $this->commissionApplicable = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function datePaiementSold(): ?string
    {
        return $this->datePaiementSold;
    }

    public function changeDatePaiementSold(?string $value): void
    {
        LieuLovCatalog::assertValid('DATE_PAIEMENT_SOLD', $value);
        $this->datePaiementSold = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function convPartSigneeLe(): ?string
    {
        return $this->convPartSigneeLe;
    }

    public function changeConvPartSigneeLe(?string $value): void
    {
        $this->convPartSigneeLe = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function convPartTaux(): ?string
    {
        return $this->convPartTaux;
    }

    public function changeConvPartTaux(?string $value): void
    {
        $this->convPartTaux = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function signataireEmail(): ?string
    {
        return $this->signataireEmail;
    }

    public function changeSignataireEmail(?string $value): void
    {
        $this->signataireEmail = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function signatairePrenom(): ?string
    {
        return $this->signatairePrenom;
    }

    public function changeSignatairePrenom(?string $value): void
    {
        $this->signatairePrenom = self::normalize($value);
        $this->lieu->markChanged();
    }

    public function signataireNom(): ?string
    {
        return $this->signataireNom;
    }

    public function changeSignataireNom(?string $value): void
    {
        $this->signataireNom = self::normalize($value);
        $this->lieu->markChanged();
    }

    private static function normalize(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
