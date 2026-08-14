<?php

namespace App\Pim\Model\ProviderPortal\DTO\Collaborator;

use Symfony\Component\Validator\Constraints as Assert;

class CreateCollaboratorDTO
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public ?string $email = null;

    public static function mock(?string $email = null): self
    {
        $data = new self();

        $data->email = $email ?? 'new.collaborator@yopmail.com';

        return $data;
    }
}
