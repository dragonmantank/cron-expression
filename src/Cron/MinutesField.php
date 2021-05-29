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
    public function isSatisfiedBy(DateTimeInterface $date, $value, $invert):bool
    {
        if ($value == '?') {
            return true;
        }

        return $this->isSatisfied((int)$date->format('i'), $value);
    }

    /**
     * {@inheritdoc}
     * {@inheritDoc}
     *
     * @param \DateTime|\DateTimeImmutable $date
     * @param string|null                  $parts
     */
    public function increment(DateTimeInterface &$date, $invert = false, $parts = null): FieldInterface
    {
        $offset = $date->getOffset();
        $current_minute = (int) $date->format('i');
        $current_hour = (int) $date->format('H');
        $current_ts = $date->getTimestamp();

        if (is_null($parts) || ($parts === '*')) {
            $timezone = $date->getTimezone();
            $date = $date->setTimezone(new \DateTimeZone('UTC'));
            $date = $date->modify(($invert ? '-' : '+') . '1 minute');
            $date = $date->setTimezone($timezone);

            return $this;
        }

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

        if ((!$invert && $current_minute >= $minutes[$position]) || ($invert && $current_minute <= $minutes[$position])) {
            $date = $date->modify(($invert ? '-' : '+') . '1 hour');
            $date = $date->setTime((int) $date->format('H'), $invert ? 59 : 0);
        } else {
            $date = $date->setTime((int) $date->format('H'), (int) $minutes[$position]);
        }

        $newTimestamp = $date->getTimestamp();
        if (($invert && ($newTimestamp > $current_ts)) || ((! $invert) && ($newTimestamp < $current_ts))) {
            // Workaround for setTime causing an offset change: https://bugs.php.net/bug.php?id=81074
            $timezone = $date->getTimezone();
            $date = $date->setTimezone(new \DateTimeZone('UTC'));
            $date = $date->modify(($invert ? '-' : '+') . '120 minutes');
            $date = $date->setTimezone($timezone);
        }

        $actualMinute = (int) $date->format('i');
        $actualHour = (int) $date->format('H');
        if ($invert && ($actualHour >= $current_hour) && ($actualMinute > $current_minute)) {
            // Reset to original value and let the DST change code handle it
            $date = $date->setTime($current_hour, $current_minute);
        }

        $this->handleDaylightSavingsChange($date, $invert, $offset, $current_minute, $current_hour);

        return $this;
    }


    /**
     * @param \DateTime|\DateTimeImmutable $date
     * @param bool $invert
     * @param int $offset Original offset before change in seconds from UTC
     * @param int $currentMinute Original minute value before change
     */
    protected function handleDaylightSavingsChange(&$date, $invert, $offset, $currentMinute, $currentHour): void
    {
        $newOffset = $date->getOffset();
        $offsetChange = ($offset - $newOffset);
        $actualMinute = (int) $date->format('i');

        if (! (($actualMinute === $currentMinute) && ($offsetChange !== 0))) {
            return;
        }

        $timezone = $date->getTimezone();
        $date = $date->setTimezone(new \DateTimeZone('UTC'));
        $date = $date->modify(($invert ? '-' : '+') . '1 minute');
        $date = $date->setTimezone($timezone);
        /*
        $currentTimestamp = $date->getTimestamp();
        $transitions = $date->getTimezone()->getTransitions(($currentTimestamp - 1), ($currentTimestamp + 1));
        if (count($transitions) === 1) {
            // False positive due to setting time to what it already is
            return;
        }

        $nextOffset = null;
        foreach ($transitions as $transition) {
            if (($invert) && ($transition['ts'] >= $currentTimestamp)) {
                continue;
            } elseif ((! $invert) && ($transition['ts'] <= $currentTimestamp)) {
                continue;
            }

            $nextOffset = $transition['offset'];
        }
        $offsetChange = ($offset - $nextOffset);
        $date->modify(($invert ? "-" : "+"). ($offsetChange + 1) ." seconds");
        $date->setTime((int) $date->format('H'), $currentMinute);
        */
    }
}
