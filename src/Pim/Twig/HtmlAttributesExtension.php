<?php

namespace App\Pim\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class HtmlAttributesExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('renderHtmlAttributes', [$this, 'renderHtmlAttributes'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * @param array<string, string> $attributes
     */
    public function renderHtmlAttributes(array $attributes): string
    {
        $htmlAttributes = '';
        foreach ($attributes as $key => $value) {
            $htmlAttributes .= sprintf('%s="%s" ', $key, $value);
        }

        return $htmlAttributes;
    }
}
