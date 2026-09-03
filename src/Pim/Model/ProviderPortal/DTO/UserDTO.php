<?php

namespace App\Pim\Model\ProviderPortal\DTO;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class UserDTO
{
    public ?string $firstName = null;

    public ?string $lastName = null;

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $job = null;

    public ?string $password = null;

    public ?string $pictureUrl = null;

    public ?UploadedFile $pictureFile = null;

    public function getFullName(): string
    {
        return sprintf('%s %s', $this->firstName ?? '', $this->lastName ?? '');
    }

    public function getInitials(): string
    {
        return substr($this->firstName ?? '', 0, 1).substr($this->lastName ?? '', 0, 1);
    }
}
