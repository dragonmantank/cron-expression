<?php

declare(strict_types=1);

namespace Cron;

use DateTimeInterface;

/**
 * Minutes field.  Allows: * , / -.
 */
class MinutesField extends AbstractField
{
    /**
     * {@inheritdoc}
     */
    protected $rangeStart = 0;

    /**
     * {@inheritdoc}
     */
    protected $rangeEnd = 59;

    /**
     * {@inheritdoc}
     */
    public function isSatisfiedBy(NextRunDateTime $date, $value):bool
    {
        if ($value === '?') {
            return true;
        }

        // FIXME Some zones change offset by minutes
        return $this->isSatisfied((int)$date->format('i'), $value);
    }

    /**
     * {@inheritdoc}
     * {@inheritDoc}
     *
     * @param string|null                  $parts
     */
    public function increment(NextRunDateTime $date, $invert = false, $parts = null): FieldInterface
    {
        if (is_null($parts) || ($parts === '*')) {
            $date->modify(($invert ? '-' : '+'). '1 minute');
            return $this;
        }

        $current_minute = (int) $date->format('i');

        $parts = false !== strpos($parts, ',') ? explode(',', $parts) : [$parts];
        $minutes = [];
        foreach ($parts as $part) {
            $minutes = array_merge($minutes, $this->getRangeForExpression($part, 59));
        }

        $position = $invert ? \count($minutes) - 1 : 0;
        if (\count($minutes) > 1) {
            for ($i = 0; $i < \count($minutes) - 1; ++$i) {
                if ((!$invert && $current_minute >= $minutes[$i] && $current_minute < $minutes[$i + 1]) ||
                    ($invert && $current_minute > $minutes[$i] && $current_minute <= $minutes[$i + 1])) {
                    $position = $invert ? $i : $i + 1;

                    break;
                }
            }
        }

        $target = (int) $minutes[$position];
        $date->setMinutes($target);

        return $this;
    }

}
