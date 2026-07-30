<?php

namespace App\Pim\Model\ProviderPortal\DTO\Invoicing;

use App\Pim\Model\ProviderPortal\Mock\Sheet\Invoicing\DueDateChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Invoicing\LegalFormChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Invoicing\VatModeChoices;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class InvoicingDTO
{
    public ?string $businessName = null;

    public ?string $legalForm = null;

    public ?AddressDTO $address = null;

    public ?string $siret = null;

    public ?bool $withVat = null;

    public ?string $vatMode = null;

    public ?string $taxIdNumber = null;

    public ?string $invoicingName = null;

    public ?string $invoicingTaxIdNumber = null;

    public ?AddressDTO $invoicingAddress = null;

    public ?string $contactFirstName = null;

    public ?string $contactLastName = null;

    public ?string $contactEmail = null;

    public ?string $contactPhone = null;

    public ?string $bti = null;

    public ?string $iban = null;

    public ?UploadedFile $ribFile = null;

    public ?string $ribUrl = null;

    public ?bool $factoring = null;

    /**
     * @var array<DepositDTO>
     */
    public array $depositList = [];

    public ?CancellationDTO $cancellation = null;

    public ?string $dueDateDelay = null;

    public ?int $commissionPercent = null;

    public ?string $commissionPayment = null;

    public ?UploadedFile $cgvFile = null;

    public ?string $cgvUrl = null;

    public ?\DateTime $partnershipAgreementDate = null;

    public ?string $partnershipAgreementSignatory = null;

    public ?UploadedFile $partnershipAgreementFile = null;

    public ?string $partnershipAgreementUrl = null;

    public static function mock(): self
    {
        $data = new self();

        $data->businessName = 'Nodevo';
        $data->legalForm = array_rand(array_flip(LegalFormChoices::getChoices()));
        $data->address = AddressDTO::mock();
        $data->siret = '16721215873616';
        $data->withVat = true;
        $data->vatMode = array_rand(array_flip(VatModeChoices::getChoices()));
        $data->taxIdNumber = 'FR77167212158';
        $data->invoicingName = 'Nodevo';
        $data->invoicingTaxIdNumber = 'FR77167212158';
        $data->invoicingAddress = AddressDTO::mock();
        $data->contactFirstName = 'Nicolas';
        $data->contactLastName = 'DUPONT';
        $data->contactEmail = 'nicolas.dupont@yopmail.com';
        $data->contactPhone = '0122334455';
        $data->bti = 'BIC';
        $data->iban = 'FR6717569000709694663795B76';
        $data->ribUrl = 'provider_portal/img/mock/document.pdf';
        $data->factoring = false;
        $data->depositList = [
            DepositDTO::mock(),
            DepositDTO::mock(),
        ];
        $data->cancellation = CancellationDTO::mock();
        $data->dueDateDelay = array_rand(array_flip(DueDateChoices::getChoices()));
        $data->cgvUrl = 'provider_portal/img/mock/company-logo.png';
        $data->partnershipAgreementDate = new \DateTime('-2 months');
        $data->partnershipAgreementSignatory = 'Nicolas DUPONT - DG';
        $data->partnershipAgreementUrl = 'provider_portal/img/mock/document.pdf';

        return $data;
    }
}
