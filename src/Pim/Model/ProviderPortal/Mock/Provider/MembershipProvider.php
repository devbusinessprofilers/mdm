<?php

namespace App\Pim\Model\ProviderPortal\Mock\Provider;

use App\Pim\Model\ProviderPortal\DTO\Collaborator\MembershipDTO;
use App\Pim\Model\ProviderPortal\DTO\SheetDTO;

class MembershipProvider
{
    /**
     * @return array<MembershipDTO>
     */
    public static function findAll(SheetDTO $sheet): array
    {
        $result = [];

        foreach (CollaboratorProvider::findForSheet($sheet) as $collaborator) {
            foreach ($collaborator->memberships as $membership) {
                if ($membership->sheet->uniqueId === $sheet->uniqueId) {
                    $result[] = $membership;
                }
            }
        }

        return $result;
    }
}
