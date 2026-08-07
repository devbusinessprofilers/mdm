<?php

declare(strict_types=1);

namespace App\Audit;

final readonly class ValueNormalizer
{
    public function normalize(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }
        if ($value instanceof \Stringable) {
            return (string) $value;
        }
        if (is_object($value)) {
            foreach (['id', 'idString'] as $method) {
                if (method_exists($value, $method)) {
                    return (string) $value->{$method}();
                }
            }

            return $value::class;
        }
        if (is_array($value)) {
            return array_map(
                fn (mixed $item): mixed => $this->normalize($item),
                $value,
            );
        }

        return $value;
    }

    public function same(mixed $old, mixed $new): bool
    {
        return $this->normalize($old) === $this->normalize($new);
    }
}
