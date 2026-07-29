<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<RessourceLieu> */
final class RessourceLieuType extends AbstractType
{
    /** @param FormBuilderInterface<RessourceLieu|null> $builder */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->field($builder, 'damAssetId', TextType::class, 'Identifiant DAM');
        $this->field($builder, 'nature', ChoiceType::class, 'Nature', [
            'choices' => ['Photo' => NatureRessource::Photo, 'Document' => NatureRessource::Document, 'Vidéo' => NatureRessource::Video],
            'choice_value' => static fn (?NatureRessource $nature): ?string => $nature?->value,
        ]);
        $this->field($builder, 'usage', TextType::class, 'Usage', ['help' => 'Ex. galerie, plan_general, rse, photo_salle']);
        $this->field($builder, 'legende', TextType::class, 'Légende', ['required' => false]);
        $this->field($builder, 'position', IntegerType::class, 'Position', ['required' => false]);
    }

    /**
     * @param FormBuilderInterface<RessourceLieu|null> $builder
     * @param class-string<FormTypeInterface<mixed>> $type
     * @param array<string, mixed> $options
     */
    private function field(FormBuilderInterface $builder, string $name, string $type, string $label, array $options = []): void
    {
        $setter = 'change'.ucfirst($name);
        $builder->add($name, $type, $options + [
            'label' => $label,
            'getter' => static fn (RessourceLieu $ressource): mixed => $ressource->{$name}(),
            'setter' => static function (RessourceLieu &$ressource, mixed $value) use ($setter): void { $ressource->{$setter}($value); },
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => RessourceLieu::class]);
    }
}
