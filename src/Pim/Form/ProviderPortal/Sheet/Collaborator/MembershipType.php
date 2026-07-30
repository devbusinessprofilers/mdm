<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Collaborator;

use App\Pim\Form\ProviderPortal\SwitchButtonType;
use App\Pim\Model\ProviderPortal\DTO\Collaborator\MembershipDTO;
use App\Pim\Model\ProviderPortal\DTO\SheetDTO;
use App\Pim\Model\ProviderPortal\Mock\Collaborator\RoleChoices;
use App\Pim\Model\ProviderPortal\Mock\Provider\SheetProvider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MembershipType extends AbstractType
{
    /**
     * Mode used to edit membership on a given collaborator (i.e. as a collection item).
     *
     * @see CollaboratorType > memberships collection
     */
    public const MODE_COLLABORATOR = 'collaborator';

    /**
     * Mode used to edit membership on a given sheet (i.e. including collaborator data).
     */
    public const MODE_MEMBERSHIP = 'membership';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mainContact', SwitchButtonType::class)
            ->add('withContent', CheckboxType::class, ['required' => false])
            ->add('withRequest', CheckboxType::class, ['required' => false])
            ->add('withPayment', CheckboxType::class, ['required' => false])
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var MembershipDTO|null $membership */
            $membership = $event->getData();
            $isAdmin = (null !== $membership) && $membership->isAdmin();

            $event->getForm()
                ->add('role', ChoiceType::class, [
                    'choices' => RoleChoices::getChoices($isAdmin),
                ])
            ;
        });

        if (self::MODE_MEMBERSHIP === $options['mode']) {
            $builder->add('collaborator', CollaboratorType::class, [
                'for_desktop' => $options['for_desktop'],
            ]);
        }

        if (self::MODE_COLLABORATOR === $options['mode']) {
            $builder
                ->add('sheet', ChoiceType::class, [
                    'choices' => SheetProvider::findAll(),
                    'choice_label' => fn (SheetDTO $sheet) => $sheet->name,
                    'choice_value' => fn (?SheetDTO $sheet) => $sheet ? $sheet->uniqueId : '',
                ])
            ;
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('mode');
        $resolver->setAllowedTypes('mode', 'string');
        $resolver->setAllowedValues('mode', [self::MODE_COLLABORATOR, self::MODE_MEMBERSHIP]);

        $resolver->setDefined('for_desktop');
        $resolver->setAllowedTypes('for_desktop', 'bool');

        $resolver->setDefaults([
            'data_class' => MembershipDTO::class,
            'label_format' => 'form.membership.%name%.label',
            'for_desktop' => true,
        ]);
    }
}
