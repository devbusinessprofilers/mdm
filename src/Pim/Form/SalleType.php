<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Lieu\Salle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Salle> */
final class SalleType extends AbstractType
{
    /** @param FormBuilderInterface<Salle|null> $builder */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->field($builder, 'nom', TextType::class, 'Nom', 'nom', 'changeNom', true);
        foreach ([
            'superficie' => 'Superficie (m²)', 'capaciteReunion' => 'Capacité réunion',
            'capaciteU' => 'Capacité en U', 'capaciteClasse' => 'Capacité classe',
            'capaciteTheatre' => 'Capacité théâtre', 'capaciteCabaret' => 'Capacité cabaret',
            'capaciteBanquet' => 'Capacité banquet', 'capaciteCocktail' => 'Capacité cocktail',
            'capaciteAuditorium' => 'Capacité auditorium', 'position' => 'Position',
        ] as $name => $label) {
            $this->field($builder, $name, IntegerType::class, $label, $name, 'change'.ucfirst($name));
        }
        foreach (['lumiereJour' => 'Lumière du jour', 'accesPmr' => 'Accès PMR', 'espaceDansant' => 'Espace dansant', 'climatisee' => 'Climatisée'] as $name => $label) {
            $this->field($builder, $name, CheckboxType::class, $label, $name, 'change'.ucfirst($name));
        }
    }

    /**
     * @param FormBuilderInterface<Salle|null>       $builder
     * @param class-string<FormTypeInterface<mixed>> $type
     */
    private function field(FormBuilderInterface $builder, string $name, string $type, string $label, string $getter, string $setter, bool $required = false): void
    {
        $builder->add($name, $type, [
            'label' => $label, 'required' => $required,
            'getter' => static fn (Salle $salle): mixed => $salle->{$getter}(),
            'setter' => static function (Salle &$salle, mixed $value) use ($setter): void { $salle->{$setter}($value); },
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Salle::class]);
    }
}
