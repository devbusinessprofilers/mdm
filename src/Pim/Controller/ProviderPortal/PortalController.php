<?php

namespace App\Pim\Controller\ProviderPortal;

use App\Pim\Enum\ProviderPortal\Twig\Component\Form\Dropzone\AcceptedTypeEnum;
use App\Pim\Form\ProviderPortal\AvatarType;
use App\Pim\Form\ProviderPortal\CalendarRangeType;
use App\Pim\Form\ProviderPortal\CalendarType;
use App\Pim\Form\ProviderPortal\DocumentFileType;
use App\Pim\Form\ProviderPortal\DropzoneType;
use App\Pim\Form\ProviderPortal\NumberType;
use App\Pim\Form\ProviderPortal\PictureFileType;
use App\Pim\Form\ProviderPortal\SwitchButtonType;
use App\Pim\Form\ProviderPortal\TagSelectType;
use App\Pim\Form\ProviderPortal\WysiwygType;
use App\Pim\Model\ProviderPortal\DTO\Date\DateRangeDTO;
use App\Pim\Model\ProviderPortal\Form\Tag\TagOption;
use App\Pim\Model\ProviderPortal\Mock\Provider\SheetProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PortalController extends AbstractController
{
    #[Route(path: '/portal', name: 'provider_portal_index')]
    public function index(): Response
    {
        return $this->render('provider_portal/index.html.twig');
    }

    #[Route(path: '/portal/routes', name: 'provider_portal_routes')]
    public function routes(): Response
    {
        return $this->render('provider_portal/pages/mock/routes.html.twig', [
            'placeSheet' => SheetProvider::findPlaceSheets()[0],
            'activitySheet' => SheetProvider::findActivitySheets()[0],
            'serviceSheet' => SheetProvider::findServiceSheets()[0],
            'restaurantSheet' => SheetProvider::findRestaurantSheets()[0],
            'mealTraySheet' => SheetProvider::findMealTraySheets()[0],
        ]);
    }

    #[Route(path: '/portal/ui', name: 'provider_portal_ui')]
    public function ui(): Response
    {
        return $this->render('provider_portal/pages/mock/ui.html.twig');
    }

    #[Route(path: '/portal/icons', name: 'provider_portal_icons')]
    public function icon(): Response
    {
        return $this->render('provider_portal/pages/mock/icons.html.twig');
    }

    #[Route(path: '/portal/form', name: 'provider_portal_form_ui')]
    public function form(Request $request): Response
    {
        $defaultData = [
            'text' => 'Texte par défaut',
            'number' => 1200,
            'checkbox' => true,
            'switch' => true,
            'radios' => true,
            'select' => 'option_2',
            'tag_select' => ['tag_2', 'tag_3'],
            'select_multiple' => ['option_1', 'option_3'],
            'calendar' => new \DateTime('now'),
            'calendar_range' => DateRangeDTO::mock(),
        ];

        $formBuilder = $this->createFormBuilder($defaultData)
            ->add('avatar', AvatarType::class, ['initials' => 'NC'])
            ->add('text', TextType::class, ['label' => 'Champ texte'])
            ->add('number', NumberType::class, ['label' => 'Champ numérique'])
            ->add('password', PasswordType::class, ['label' => 'Champ mot de passe'])
            ->add('repeated', RepeatedType::class, [
                'label' => false,
                'type' => PasswordType::class,
                'first_name' => 'password',
                'second_name' => 'confirmPassword',
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => ['withControl' => true],
                ],
                'second_options' => [
                    'label' => 'Confirmation de mot de passe',
                ],
            ])
            ->add('checkbox', CheckboxType::class, ['label' => 'Case à cocher'])
            ->add('switch', SwitchButtonType::class, ['label' => 'Switch'])
            ->add('radios', ChoiceType::class, [
                'label' => 'Radio boutons',
                'choices' => ['Oui' => true, 'Non' => false],
                'expanded' => true,
            ])
            ->add('select', ChoiceType::class, [
                'label' => 'Liste déroulante',
                'choices' => ['Option 1' => 'option_1', 'Option 2' => 'option_2', 'Option 3' => 'option_3'],
                'placeholder' => 'Sélectionnez une option',
            ])
            ->add('tag_select', TagSelectType::class, [
                'label' => 'Liste de tags',
                'tag_options' => [
                    new TagOption('tag_1', 'Tag 1', 'bed'),
                    new TagOption('tag_2', 'Tag 2', 'biking'),
                    new TagOption('tag_3', 'Tag 3', 'building'),
                    new TagOption('tag_4', 'Tag 4', 'call-bell'),
                    new TagOption('tag_5', 'Tag 5', 'cookie'),
                ],
            ])
            ->add('select_multiple', ChoiceType::class, [
                'label' => 'Liste déroulante multiple',
                'multiple' => true,
                'choices' => ['Option 1' => 'option_1', 'Option 2' => 'option_2', 'Option 3' => 'option_3'],
                'placeholder' => 'Sélectionnez une option',
            ])
            ->add('calendar', CalendarType::class, [
                'label' => 'Calendrier',
                'attr' => [
                    'placeholder' => 'Sélectionnez une date',
                ],
            ])
            ->add('calendar_range', CalendarRangeType::class, [
                'label' => 'Calendrier avec plage de dates',
                'attr' => [
                    'placeholder' => 'Sélectionnez une plage de dates',
                ],
            ])
            ->add('textarea', TextareaType::class, ['label' => 'Champs textarea'])
            ->add('wysiwyg', WysiwygType::class, ['label' => 'Champs wysiwyg'])
            ->add('picture', PictureFileType::class, ['label' => 'Upload image'])
            ->add('document', DocumentFileType::class)
            ->add('dropzone_documents', DropzoneType::class, [
                'label' => 'Dropzone (documents)',
                'accepted_type' => AcceptedTypeEnum::DOCUMENTS,
                'max_file_count' => 10,
            ])
            ->add('dropzone_images', DropzoneType::class, [
                'label' => 'Dropzone (images)',
                'accepted_type' => AcceptedTypeEnum::IMAGES,
                'file_max_size' => '10M',
                'image_min_width' => 800,
                'image_min_height' => 600,
            ])
            ->add('submit', SubmitType::class, ['label' => 'Enregistrer']);

        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/mock/form.html.twig', ['form' => $form]);
    }
}
