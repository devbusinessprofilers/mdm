<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Place;

use App\Pim\Enum\ProviderPortal\Form\Twig\Attributes\SwitchButtonTypeAttributeEnum;
use App\Pim\Form\ProviderPortal\CollectionType;
use App\Pim\Form\ProviderPortal\MeetingRoomType;
use App\Pim\Form\ProviderPortal\NumberType;
use App\Pim\Form\ProviderPortal\SwitchButtonType;
use App\Pim\Form\ProviderPortal\WysiwygType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceMeetingDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

class PlaceMeetingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder = new DynamicFormBuilder($builder);

        $builder
            ->add('hasMeetingRooms', SwitchButtonType::class, [
                'required' => false,
                SwitchButtonTypeAttributeEnum::INVERTED->value => true,
            ])
        ;

        $builder->addDependent('meetingRoomNumber', 'hasMeetingRooms', $this->shouldAddNumberType(...));
        $builder->addDependent('cocktailConfigurationCapacity', 'hasMeetingRooms', $this->shouldAddNumberType(...));
        $builder->addDependent('theatreConfigurationCapacity', 'hasMeetingRooms', $this->shouldAddNumberType(...));
        $builder->addDependent('minRoomArea', 'hasMeetingRooms', $this->shouldAddNumberType(...));
        $builder->addDependent('maxRoomArea', 'hasMeetingRooms', $this->shouldAddNumberType(...));
        $builder->addDependent('description', 'hasMeetingRooms', $this->shouldAddWysiwygType(...));
        $builder->addDependent('meetingRooms', 'hasMeetingRooms', $this->shouldAddRoomsType(...));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlaceMeetingDTO::class,
            'label_format' => 'form.sheet.place.meeting.%name%.label',
        ]);
    }

    private function shouldAddNumberType(DependentField $field, bool $hasMeetingRooms): void
    {
        if (!$hasMeetingRooms) {
            return;
        }

        $field->add(NumberType::class);
    }

    private function shouldAddWysiwygType(DependentField $field, bool $hasMeetingRooms): void
    {
        if (!$hasMeetingRooms) {
            return;
        }

        $field->add(WysiwygType::class);
    }

    private function shouldAddRoomsType(DependentField $field, bool $hasMeetingRooms): void
    {
        if (!$hasMeetingRooms) {
            return;
        }

        $field->add(CollectionType::class, [
            'label' => false,
            'entry_type' => MeetingRoomType::class,
            'entry_options' => ['label' => false],
            'allow_add' => true,
            'allow_delete' => true,
            'sortable' => true,
            'by_reference' => false,
            'required' => false,
            'add_button_label' => 'form.sheet.place.meeting.rooms.actions.add',
        ]);
    }
}
