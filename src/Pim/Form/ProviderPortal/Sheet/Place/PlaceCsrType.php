<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Place;

use App\Pim\Enum\ProviderPortal\Twig\Component\Form\Dropzone\AcceptedTypeEnum;
use App\Pim\Form\ProviderPortal\DropzoneType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceCsrDTO;
use App\Pim\Model\ProviderPortal\Form\Dropzone\Document;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\DistinctionChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\EnvironmentalImpactChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\MobilityChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\PurchaseCategoryChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\PurchaseChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\SocialImpactChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlaceCsrType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('purchase', ChoiceType::class, [
                'multiple' => true,
                'choices' => PurchaseChoices::getChoices(),
            ])
            ->add('environmentalImpact', ChoiceType::class, [
                'multiple' => true,
                'choices' => EnvironmentalImpactChoices::getChoices(),
            ])
            ->add('socialImpact', ChoiceType::class, [
                'multiple' => true,
                'choices' => SocialImpactChoices::getChoices(),
            ])
            ->add('purchaseCategory', ChoiceType::class, [
                'multiple' => true,
                'choices' => PurchaseCategoryChoices::getChoices(),
            ])
            ->add('mobility', ChoiceType::class, [
                'multiple' => true,
                'choices' => MobilityChoices::getChoices(),
            ])
            ->add('distinction', ChoiceType::class, [
                'multiple' => true,
                'choices' => DistinctionChoices::getChoices(),
            ])
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var PlaceCsrDTO|null $invoicingData */
            $data = $event->getData();

            $commitmentDocuments = [];
            if (!empty($data?->commitmentUrl)) {
                $commitmentDocuments[] = Document::fromPath($invoicingData->commitmentUrl);
            }

            $event->getForm()
                ->add('commitmentFile', DropzoneType::class, [
                    'multiple' => false,
                    'accepted_type' => AcceptedTypeEnum::DOCUMENTS,
                    'max_file_count' => 1,
                    'documents' => $commitmentDocuments,
                ])
            ;
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlaceCsrDTO::class,
            'label_format' => 'form.sheet.place.csr.%name%.label',
        ]);
    }
}
