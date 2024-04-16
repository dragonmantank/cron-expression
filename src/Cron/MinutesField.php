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
    public function isSatisfiedBy(DateTimeInterface $date, $value, bool $invert):bool
    {
        if ($value === '?') {
            return true;
        }

        return $this->isSatisfied((int)$date->format('i'), $value);
    }

    /**
     * {@inheritdoc}
     * {@inheritDoc}
     *
     * @param string|null                  $parts
     */
    public function increment(DateTimeInterface &$date, $invert = false, $parts = null): FieldInterface
    {
        if (is_null($parts)) {
            $interval = new \DateInterval('PT1M');
            $date = $invert ? $date->sub($interval) : $date->add($interval);
            return $this;
        }

        $current_minute = (int) $date->format('i');

        $parts = false !== strpos($parts, ',') ? explode(',', $parts) : [$parts];
        sort($parts);
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
        $originalMinute = (int) $date->format("i");

        if (! $invert) {
            if ($originalMinute >= $target) {
                $distance = 60 - $originalMinute;
                $date = $date->add(new \DateInterval("PT{$distance}M"));

                $originalMinute = (int) $date->format("i");
            }

            $distance = $target - $originalMinute;
            $date = $date->add(new \DateInterval("PT{$distance}M"));
        } else {
            if ($originalMinute <= $target) {
                $distance = ($originalMinute + 1);
                $date = $date->sub(new \DateInterval("PT{$distance}M"));

                $originalMinute = (int) $date->format("i");
            }

            $distance = $originalMinute - $target;
            $date = $date->sub(new \DateInterval("PT{$distance}M"));
        }

        return $this;
    }
}
