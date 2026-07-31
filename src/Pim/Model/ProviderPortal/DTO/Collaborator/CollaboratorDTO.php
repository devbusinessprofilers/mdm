<?php

namespace App\Pim\Model\ProviderPortal\DTO\Collaborator;

use App\Pim\Model\ProviderPortal\DTO\SheetDTO;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CollaboratorDTO
{
    public ?string $firstName = null;

    public ?string $lastName = null;

    public ?string $email = null;

    public ?string $phone = null;

    /**
     * @var array<MembershipDTO>
     */
    public array $memberships = [];

    public ?string $pictureUrl = null;

    public ?UploadedFile $pictureFile = null;

    /**
     * @param array<SheetDTO> $sheets
     */
    public static function mock(
        ?string $firstName = null,
        ?string $lastName = null,
        array $sheets = [],
        ?bool $isAdmin = false,
    ): self {
        $data = new self();

        $data->pictureUrl = '/img/mock/avatar.png';
        $data->firstName = $firstName ?? 'Jon';
        $data->lastName = $lastName ?? 'DOE';
        $data->email = strtolower(sprintf('%s.%s@yopmail.com', $data->firstName, $data->lastName));
        $data->phone = '+33611223344';

        if (!empty($sheets)) {
            foreach ($sheets as $sheet) {
                $data->memberships[] = MembershipDTO::mock($data, $sheet, $isAdmin);
            }
        }

        return $data;
    }

    public function hasSheet(SheetDTO $sheet): bool
    {
        foreach ($this->memberships as $membership) {
            if ($membership->sheet->uniqueId === $sheet->uniqueId) {
                return true;
            }
        }

        return false;
    }
}
