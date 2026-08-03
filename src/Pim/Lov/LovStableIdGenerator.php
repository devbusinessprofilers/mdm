<?php

declare(strict_types=1);

namespace App\Pim\Lov;

use Doctrine\DBAL\Connection;

final readonly class LovStableIdGenerator
{
    public function __construct(private Connection $connection) {}

    public function valueId(string $attributeCode, string $valueCode): int
    {
        $unpacked = unpack('Nid', substr(hash('sha256', 'value:'.$attributeCode.':'.$valueCode, true), 0, 4));
        if (false === $unpacked) { throw new \LogicException('Impossible de générer l’identifiant LOV.'); }
        $id = $unpacked['id'] & 0x7FFFFFFF;
        $existing = $this->connection->fetchAssociative('SELECT a.code attribute_code, v.code FROM pim_attribute_value v INNER JOIN pim_attribute_definition a ON a.id = v.attribute_id WHERE v.id = ?', [$id]);
        if (false !== $existing && ($existing['attribute_code'] !== $attributeCode || $existing['code'] !== $valueCode)) {
            throw new \DomainException('Collision globale d’identifiant LOV.');
        }

        return $id;
    }
}
