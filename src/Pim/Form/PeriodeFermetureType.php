<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Lieu\PeriodeFermeture;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<PeriodeFermeture> */
final class PeriodeFermetureType extends AbstractType
{
    /** @param FormBuilderInterface<PeriodeFermeture|null> $builder */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (['nom' => [TextType::class, 'Nom'], 'dateDebut' => [DateType::class, 'Début'], 'dateFin' => [DateType::class, 'Fin']] as $name => [$type, $label]) {
            $setter = 'change'.ucfirst($name);
            $fieldOptions = [
                'label' => $label,
                'getter' => static fn (PeriodeFermeture $periode): mixed => $periode->{$name}(),
                'setter' => static function (PeriodeFermeture &$periode, mixed $value) use ($setter): void { $periode->{$setter}($value); },
            ];
            if (DateType::class === $type) {
                $fieldOptions['widget'] = 'single_text';
                $fieldOptions['input'] = 'datetime_immutable';
            }
            $builder->add($name, $type, $fieldOptions);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PeriodeFermeture::class]);
    }
}
