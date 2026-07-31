<?php

namespace App\Pim\Model\ProviderPortal\Mock\Collaborator;

class RoleChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(bool $withAdmin = true): array
    {
        $roles = [
            'Manager' => 'manager',
            'Utilisateur' => 'utilisateur',
        ];

        if ($withAdmin) {
            $roles['Administrateur'] = self::getAdminValue();
        }

        return $roles;
    }

    public static function getAdminValue(): string
    {
        return 'administrateur';
    }
}
