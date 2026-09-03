<?php

declare(strict_types=1);

namespace App\Ocr\Service;

use App\Pim\Entity\Fiche;

final class OcrValueAccessor
{
    public function read(Fiche $fiche, object $aggregate, string $path): mixed
    {
        if ('fiche.label' === $path) {
            return $fiche->label();
        }
        if (str_starts_with($path, 'collections.')) {
            $getter = $this->collectionGetter(substr($path, 12));

            return method_exists($aggregate, $getter) ? $this->normalize($aggregate->{$getter}()) : null;
        }
        $parts = explode('.', $path);
        $target = $aggregate;
        if ('localisation' === $parts[0]) {
            $target = $fiche->localisation();
            array_shift($parts);
        } elseif (count($parts) > 1) {
            $getter = array_shift($parts);
            $target = method_exists($target, $getter) ? $target->{$getter}() : null;
        }
        foreach ($parts as $part) {
            if (!is_object($target) || !method_exists($target, $part)) {
                return null;
            }
            $target = $target->{$part}();
        }

        return $this->normalize($target);
    }

    private function collectionGetter(string $prefix): string
    {
        return match ($prefix) {
            'salle' => 'salles', 'periode_fermeture' => 'periodesFermeture', 'acces' => 'acces', 'offre' => 'offres',
            default => $prefix,
        };
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if ($value instanceof \Doctrine\Common\Collections\Collection) {
            return array_map(fn (mixed $item): mixed => $this->normalizeObject($item), $value->toArray());
        }
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        return $value;
    }

    /** @return array<string, mixed>|mixed */
    private function normalizeObject(mixed $value): mixed
    {
        if (!is_object($value)) {
            return $this->normalize($value);
        }
        $result = [];
        foreach ((new \ReflectionClass($value))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (0 !== $method->getNumberOfRequiredParameters() || in_array($method->getName(), ['fiche', 'lieu', 'id'], true)) {
                continue;
            }
            if (!preg_match('/^(nom|type|date|distance|duree|mode|capacite|superficie|surface|prix|description|label|position)/i', $method->getName())) {
                continue;
            }
            try {
                $result[$method->getName()] = $this->normalize($method->invoke($value));
            } catch (\Throwable) {
            }
        }

        return $result;
    }
}
