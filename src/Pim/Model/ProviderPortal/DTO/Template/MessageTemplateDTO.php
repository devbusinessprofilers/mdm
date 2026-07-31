<?php

namespace App\Pim\Model\ProviderPortal\DTO\Template;

/**
 * Common DTO for message templates.
 */
class MessageTemplateDTO
{
    public ?string $name = null;

    public ?string $content = null;

    public static function mock(): self
    {
        $data = new self();

        $data->name = 'Template 1';
        $data->content = 'Lorem ipsum dolor sit amet consectetur. Nunc aenean aliquam commodo viverra. Dictum a vel pharetra arcu pulvinar duis tortor. At scelerisque vel fusce tempor. Aliquam ultrices molestie egestas turpis pharetra. Quis nisl neque magna fringilla orci curabitur. Urna blandit dolor erat gravida sed. Faucibus posuere ipsum vel porta dictum tristique velit.';

        return $data;
    }
}
