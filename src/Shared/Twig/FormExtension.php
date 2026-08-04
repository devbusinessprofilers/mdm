<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use App\Shared\Form\ActionType;
use App\Shared\Form\HeaderSearchType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class FormExtension extends AbstractExtension
{
    public function __construct(
        private readonly FormFactoryInterface $forms,
        private readonly UrlGeneratorInterface $urls,
        private readonly RequestStack $requestStack,
    ) {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('logout_form', [$this, 'logoutForm']),
            new TwigFunction('global_search_form', [$this, 'globalSearchForm']),
        ];
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

    public function globalSearchForm(): FormView
    {
        $request = $this->requestStack->getCurrentRequest();
        $query = 'app_pim_global_search' === $request?->attributes->get('_route')
            ? $request->query->getString('q')
            : '';

        return $this->forms->createNamed('', HeaderSearchType::class, ['q' => $query], [
            'action' => $this->urls->generate('app_pim_global_search'),
            'attr' => ['class' => 'header-search', 'role' => 'search'],
        ])->createView();
    }
}
