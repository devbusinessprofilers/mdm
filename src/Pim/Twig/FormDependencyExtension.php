<?php

namespace App\Pim\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FormDependencyExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('getFormDependencyAttributes', [$this, 'getFormDependencyAttributes']),
            new TwigFunction('getFormDependencyTriggerAttributes', [$this, 'getFormDependencyTriggerAttributes']),
            new TwigFunction('getFormDependencyWrapperAttributes', [$this, 'getFormDependencyWrapperAttributes']),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getFormDependencyAttributes(): array
    {
        return [
            'data-controller' => 'provider-portal--form-dependency',
            'data-provider-portal--form-dependency-target' => 'form',
        ];
    }

    /**
     * @param string $action default action can be overridden depending on input used as a trigger
     *
     * @return array<string, string>
     */
    public function getFormDependencyTriggerAttributes(string $dependencyIdentifier, string $action = 'change'): array
    {
        return [
            'data-action' => sprintf('%s->provider-portal--form-dependency#refreshData', $action),
            'data-provider-portal--form-dependency-wrapper-identifier-param' => $dependencyIdentifier,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getFormDependencyWrapperAttributes(string $dependencyIdentifier): array
    {
        return [
            'data-dependency-target' => $dependencyIdentifier,
        ];
    }
}
