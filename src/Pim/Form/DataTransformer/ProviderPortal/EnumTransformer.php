<?php
namespace App\Pim\Form\DataTransformer\ProviderPortal;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<\BackedEnum|null, string|null>
 */
class EnumTransformer implements DataTransformerInterface
{
    /**
     * @param class-string<\BackedEnum> $enum
     * @param bool $useName
     */
    public function __construct(
        private string $enum,
        private bool $useName = false,
    ) {
    }

    /**
     * @param \BackedEnum|null $value
     */
    public function transform($value = null): ?string
    {
        if (null === $value) {
            return null;
        }

        return $this->useName ? $value->name : $value->value;
    }

    /**
     * @param string|null $value
     */
    public function reverseTransform($value): ?\BackedEnum
    {
        if (empty($value)) {
            return null;
        }

        if ($this->useName) {
            foreach ($this->enum::cases() as $enum) {
                if ($value === $enum->name) {
                    return $enum;
                }
            }

            return null;
        }

        return $this->enum::tryFrom($value);
    }
}
