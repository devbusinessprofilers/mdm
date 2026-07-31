<?php

namespace App\Pim\Model\ProviderPortal\DTO\Template;

class TemplateListDTO
{
    /**
     * @var array<MessageTemplateDTO>
     */
    public array $messageTemplates = [];

    public static function mock(): self
    {
        $data = new self();

        $data->messageTemplates = [
            MessageTemplateDTO::mock(),
        ];

        return $data;
    }
}
