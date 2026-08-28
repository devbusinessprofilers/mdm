<?php

declare(strict_types=1);

namespace App\Pim\Validation;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class LienVideoValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!($constraint instanceof LienVideo)) {
            throw new UnexpectedTypeException($constraint, LienVideo::class);
        }
        if (null === $value || '' === $value) {
            return;
        }
        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }
        if (self::estHebergeurAutorise($value)) {
            return;
        }
        $this->context
            ->buildViolation($constraint->message)
            ->setParameter(
                '{{ hebergeurs }}',
                implode(', ', array_keys(LienVideo::HEBERGEURS)),
            )
            ->addViolation();
    }

    public static function estHebergeurAutorise(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || '' === $host) {
            return false;
        }
        $host = strtolower($host);
        foreach (LienVideo::HEBERGEURS as $domaines) {
            foreach ($domaines as $domaine) {
                if ($host === $domaine || str_ends_with($host, '.'.$domaine)) {
                    return true;
                }
            }
        }

        return false;
    }
}
