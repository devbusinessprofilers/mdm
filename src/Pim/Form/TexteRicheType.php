<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Shared\Text\TexteBrut;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Zone de texte montée sur TinyMCE (attr `data-wysiwyg`) dont le modèle reste
 * du texte brut : l'éditeur reçoit des paragraphes HTML et renvoie du HTML,
 * converti dans les deux sens par TexteBrut. Rien de HTML n'atteint l'entité.
 *
 * @extends AbstractType<string|null>
 */
final class TexteRicheType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            static fn (?string $texte): string => null === $texte ? '' : TexteBrut::versHtml($texte),
            static function (?string $html): ?string {
                $texte = TexteBrut::depuisHtml((string) $html);

                return '' === $texte ? null : $texte;
            },
        ));
    }

    public function getParent(): string
    {
        return TextareaType::class;
    }
}
