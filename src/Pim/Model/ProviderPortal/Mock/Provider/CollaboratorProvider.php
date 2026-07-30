<?php

namespace App\Pim\Model\ProviderPortal\Mock\Provider;

use App\Pim\Model\ProviderPortal\DTO\Collaborator\CollaboratorDTO;
use App\Pim\Model\ProviderPortal\DTO\SheetDTO;

class CollaboratorProvider
{
    /**
     * @return array<CollaboratorDTO>
     */
    public static function findAll(): array
    {
        $sheets = SheetProvider::findAll();

        return [
            CollaboratorDTO::mock('Gaspard', 'SCHMITT', [$sheets[0], $sheets[1], $sheets[2]], true),
            CollaboratorDTO::mock('Noah', 'DUMAS', [$sheets[0], $sheets[1], $sheets[2]]),
            CollaboratorDTO::mock('Milo', 'LEFEBVRE', [$sheets[0], $sheets[1], $sheets[2]]),
            CollaboratorDTO::mock('Loan', 'BOURGEOIS', [$sheets[0], $sheets[1], $sheets[2]]),
            CollaboratorDTO::mock('Ayden', 'MORIN', [$sheets[0], $sheets[1], $sheets[2]]),
            CollaboratorDTO::mock('Adam', 'MARTINEZ', [$sheets[3]], true),
            CollaboratorDTO::mock('Aïcha', 'GAUTIER', [$sheets[3]]),
            CollaboratorDTO::mock('Sofia', 'NICOLAS', [$sheets[3]]),
            CollaboratorDTO::mock('Sohan', 'DUPONT', [$sheets[4], $sheets[5]], true),
            CollaboratorDTO::mock('Alba', 'RIVIERE', [$sheets[4], $sheets[5]]),
            CollaboratorDTO::mock('Elena', 'ARNAUD', [$sheets[6], $sheets[7], $sheets[8]], true),
            CollaboratorDTO::mock('Camille', 'AUBERT', [$sheets[6], $sheets[7], $sheets[8]]),
            CollaboratorDTO::mock('Isaac', 'PERRIN', [$sheets[6], $sheets[7], $sheets[8]]),
            CollaboratorDTO::mock('Olivia', 'BLANCHARD', [$sheets[9], $sheets[10]], true),
            CollaboratorDTO::mock('Alix', 'BRUN', [$sheets[9], $sheets[10]]),
            CollaboratorDTO::mock('Adèle', 'CLEMENT', [$sheets[9], $sheets[10]]),
            CollaboratorDTO::mock('Adèle', 'LEROUX', [$sheets[11], $sheets[12]], true),
            CollaboratorDTO::mock('Alya', 'MERCIER', [$sheets[11], $sheets[12]]),
            CollaboratorDTO::mock('Louis', 'MARIE', [$sheets[11], $sheets[12]]),
            CollaboratorDTO::mock('Samuel', 'BONNET', [$sheets[13]], true),
            CollaboratorDTO::mock('Alma', 'LOPEZ', [$sheets[13]]),
            CollaboratorDTO::mock('Côme', 'JOLY', [$sheets[13]]),
            CollaboratorDTO::mock('Apolline', 'DURAND', [$sheets[13]]),
            CollaboratorDTO::mock('Mariam', 'BOURGEOIS', [$sheets[14], $sheets[15]], true),
            CollaboratorDTO::mock('Camille', 'BRUNET', [$sheets[14], $sheets[15]]),
            CollaboratorDTO::mock('Charlie', 'DENIS', [$sheets[14], $sheets[15]]),
            CollaboratorDTO::mock('Fatima', 'DURAND', [$sheets[16], $sheets[17]], true),
            CollaboratorDTO::mock('Rose', 'SANCHEZ', [$sheets[16], $sheets[17]]),
            CollaboratorDTO::mock('Noa', 'DUMAS', [$sheets[16], $sheets[17]]),
            CollaboratorDTO::mock('Nina', 'OLIVIER', [$sheets[16], $sheets[17]]),
        ];
    }

    public static function count(): int
    {
        return count(self::findAll());
    }

    /**
     * @return array<CollaboratorDTO>
     */
    public static function findForSheet(SheetDTO $sheet): array
    {
        return array_values(array_filter(self::findAll(), fn (CollaboratorDTO $collaborator) => $collaborator->hasSheet($sheet)));
    }

    public static function search(string $email): ?CollaboratorDTO
    {
        foreach (self::findAll() as $collaborator) {
            if (0 === strcasecmp($collaborator->email, $email)) {
                return $collaborator;
            }
        }

        return null;
    }
}
