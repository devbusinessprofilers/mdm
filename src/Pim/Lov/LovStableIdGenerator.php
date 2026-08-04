<?php

declare(strict_types=1);

namespace App\Pim\Lov;

use App\Pim\Repository\ValeurAttributRepository;

final readonly class LovStableIdGenerator
{
    public function __construct(private ValeurAttributRepository $values) {}

    public function valueId(string $attributeCode, string $valueCode): int
    {
        $unpacked = unpack('Nid', substr(hash('sha256', 'value:'.$attributeCode.':'.$valueCode, true), 0, 4));
        if (false === $unpacked) { throw new \LogicException('Impossible de générer l’identifiant LOV.'); }
        $id = $unpacked['id'] & 0x7FFFFFFF;
        $existing = $this->values->findIdentityAt($id);
        if (null !== $existing && ($existing['attribute_code'] !== $attributeCode || $existing['code'] !== $valueCode)) {
            throw new \DomainException('Collision globale d’identifiant LOV.');
        }

        return $id;
    }
}
