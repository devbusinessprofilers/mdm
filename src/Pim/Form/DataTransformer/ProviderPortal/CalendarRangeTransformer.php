<?php
namespace App\Pim\Form\DataTransformer\ProviderPortal;

use App\Pim\Model\ProviderPortal\DTO\Date\DateRangeDTO;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<DateRangeDTO|null, string|null>
 */
class CalendarRangeTransformer implements DataTransformerInterface
{
    public function __construct(
        private readonly string $format,
        private readonly string $separator,
    ) {
        if (str_contains($format, $separator)) {
            throw new \LogicException('Invalid given parameters: separator can not be included in format!');
        }
    }

    public function transform($value = null): string
    {
        if (!$value instanceof DateRangeDTO) {
            return '';
        }

        $from = $value->from ? $value->from->format($this->format) : '';
        $to = $value->to ? $value->to->format($this->format) : '';

        return sprintf('%s%s%s', $from, $this->separator, $to);
    }

    public function reverseTransform($value): ?DateRangeDTO
    {
        if (empty($value) || !\is_string($value)) {
            return null;
        }

        $data = explode(sprintf('%s', $this->separator), $value);
        $from = $data[0] ?? null;
        $to = $data[1] ?? null;

        if (empty($from) || empty($to)) {
            return null;
        }

        $dateRange = new DateRangeDTO();

        $from = \DateTime::createFromFormat($this->format, $from);
        if (false !== $from) {
            $dateRange->from = $from;
        }

        $to = \DateTime::createFromFormat($this->format, $to);
        if (false !== $to) {
            $dateRange->to = $to;
        }

        return $dateRange;
    }
}
