<?php

namespace App\Pim\Model\ProviderPortal\DTO\Collaborator;

use App\Pim\Model\ProviderPortal\DTO\SheetDTO;
use App\Pim\Model\ProviderPortal\Mock\Collaborator\RoleChoices;

class MembershipDTO
{
    public ?CollaboratorDTO $collaborator = null;

    public ?string $role = null;

    public bool $mainContact = false;

    public bool $withContent = false;

    public bool $withRequest = false;

    public bool $withPayment = false;

    public ?SheetDTO $sheet = null;

    public function isAdmin(): bool
    {
        return $this->role === RoleChoices::getAdminValue();
    }

    public static function mock(CollaboratorDTO $collaborator, SheetDTO $sheet, bool $isAdmin = false): self
    {
        $data = new self();

        $data->collaborator = $collaborator;
        $data->mainContact = $isAdmin;
        $data->sheet = $sheet;

        // HACK: use email length to resolve properties and keep same values when testing...
        $even = (0 === strlen($collaborator->email) % 2);
        $data->withContent = $isAdmin || $even;
        $data->withRequest = $isAdmin || (1 < strlen($collaborator->email) % 3);
        $data->withPayment = $isAdmin || (2 < strlen($collaborator->email) % 5);
        $data->role = $isAdmin
            ? RoleChoices::getAdminValue()
            : array_values(RoleChoices::getChoices(false))[$even ? 0 : 1];

        return $data;
    }
}
