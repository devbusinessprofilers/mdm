<?php

declare(strict_types=1);

namespace App\Pim\Completeness;

use BackedEnum;
use Doctrine\Common\Collections\Collection;

final class CompletenessValueAccessor
{
    public function read(object $source, string $path): mixed
    {
        return $this->walk($source, explode('.', $path));
    }

    /** @param list<string> $segments */
    private function walk(mixed $value, array $segments): mixed
    {
        if ([] === $segments) {
            return $value;
        }

        $segment = array_shift($segments);
        if ('*' === $segment) {
            $items = $value instanceof Collection ? $value->toArray() : (is_iterable($value) ? iterator_to_array($value) : []);

            return array_map(fn (mixed $item): mixed => $this->walk($item, $segments), array_values($items));
        }

        if (!is_object($value) || !method_exists($value, $segment)) {
            return null;
        }

        try {
            $next = $value->{$segment}();
        } catch (\UnexpectedValueException) {
            // Une LOV devenue inconnue est une donnée incomplète, pas une panne du worker.
            return null;
        }

        return $this->walk($next, $segments);
    }

    public function comparable(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
