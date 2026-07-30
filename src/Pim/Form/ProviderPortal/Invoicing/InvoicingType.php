<?php

namespace App\Pim\Form\ProviderPortal\Invoicing;

use App\Pim\Enum\ProviderPortal\Twig\Component\Form\Dropzone\AcceptedTypeEnum;
use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyVariantEnum;
use App\Pim\Form\ProviderPortal\CalendarType;
use App\Pim\Form\ProviderPortal\CollectionType;
use App\Pim\Form\ProviderPortal\DropzoneType;
use App\Pim\Form\ProviderPortal\NumberType;
use App\Pim\Form\ProviderPortal\YesNoType;
use App\Pim\Model\ProviderPortal\DTO\Invoicing\InvoicingDTO;
use App\Pim\Model\ProviderPortal\Form\Dropzone\Document;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Invoicing\DueDateChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Invoicing\LegalFormChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Invoicing\VatModeChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

class InvoicingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder = new DynamicFormBuilder($builder);

        $builder
            ->add('businessName', TextType::class)
            ->add('legalForm', ChoiceType::class, [
                'choices' => LegalFormChoices::getChoices(),
            ])
            ->add('address', AddressType::class, [
                'with_street2' => true,
            ])
            ->add('siret', TextType::class)
            ->add('withVat', YesNoType::class)
            ->add('invoicingName', TextType::class)
            ->add('invoicingTaxIdNumber', TextType::class)
            ->add('invoicingAddress', AddressType::class)
            ->add('contactFirstName', TextType::class)
            ->add('contactLastName', TextType::class)
            ->add('contactEmail', TextType::class)
            ->add('contactPhone', TextType::class)
            ->add('bti', TextType::class)
            ->add('iban', TextType::class)
            ->add('factoring', YesNoType::class)
            ->add('depositList', CollectionType::class, [
                'entry_type' => DepositType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'label_typography_variant' => TypographyVariantEnum::HEADING_3,
                'add_button_label' => 'form.invoicing.depositList.actions.add',
                'information_text' => 'form.invoicing.depositList.description',
            ])
            ->add('cancellation', CancellationType::class, [
                'label' => false,
            ])
            ->add('dueDateDelay', ChoiceType::class, [
                'choices' => DueDateChoices::getChoices(),
            ])
            ->add('commissionPercent', NumberType::class, [
                'min_value' => 0,
                'max_value' => 100,
            ])
            ->add('commissionPayment', TextType::class)
            ->add('partnershipAgreementDate', CalendarType::class)
            ->add('partnershipAgreementSignatory', TextType::class)
        ;

        $builder->addDependent('vatMode', 'withVat', $this->withVatMode(...));
        $builder->addDependent('taxIdNumber', 'withVat', $this->withTaxIdNumber(...));

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var InvoicingDTO|null $invoicingData */
            $invoicingData = $event->getData();

            $ribDocuments = $cgvDocuments = $agreementDocuments = [];

            if (!empty($invoicingData?->ribUrl)) {
                $ribDocuments[] = Document::fromPath($invoicingData->ribUrl);
            }

            if (!empty($invoicingData?->cgvUrl)) {
                $cgvDocuments[] = Document::fromPath($invoicingData->cgvUrl);
            }

            if (!empty($invoicingData?->partnershipAgreementUrl)) {
                $agreementDocuments[] = Document::fromPath($invoicingData->partnershipAgreementUrl);
            }

            $event->getForm()
                ->add('ribFile', DropzoneType::class, [
                    'multiple' => false,
                    'accepted_type' => AcceptedTypeEnum::DOCUMENTS,
                    'max_file_count' => 1,
                    'documents' => $ribDocuments,
                ])
                ->add('cgvFile', DropzoneType::class, [
                    'multiple' => false,
                    'accepted_type' => AcceptedTypeEnum::DOCUMENTS,
                    'max_file_count' => 1,
                    'documents' => $cgvDocuments,
                ])
                ->add('partnershipAgreementFile', DropzoneType::class, [
                    'multiple' => false,
                    'accepted_type' => AcceptedTypeEnum::DOCUMENTS,
                    'max_file_count' => 1,
                    'documents' => $agreementDocuments,
                ])
            ;
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InvoicingDTO::class,
            'label_format' => 'form.invoicing.%name%.label',
        ]);
    }

    private function withVatMode(DependentField $field, ?bool $withVat): void
    {
        if (!$withVat) {
            return;
        }

        $field->add(ChoiceType::class, [
            'choices' => VatModeChoices::getChoices(),
        ]);
    }

    private function withTaxIdNumber(DependentField $field, ?bool $withVat): void
    {
        if (!$withVat) {
            return;
        }

        $field->add(TextType::class);
    }
}
