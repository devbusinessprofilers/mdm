<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use App\Shared\Form\ActionType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class FormExtension extends AbstractExtension
{
    public function __construct(
        private readonly FormFactoryInterface $forms,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [new TwigFunction('logout_form', [$this, 'logoutForm'])];
    }

    public function logoutForm(): FormView
    {
        return $this->forms->createNamed('', ActionType::class, null, [
            'action' => $this->urls->generate('app_logout'),
            'button_label' => 'Se déconnecter',
            'button_attr' => ['class' => 'logout-button'],
            'csrf_field_name' => '_csrf_token',
            'csrf_token_id' => 'logout',
        ])->createView();
    }
}
