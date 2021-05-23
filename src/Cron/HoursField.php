<?php

declare(strict_types=1);

namespace Cron;

use DateTimeInterface;
use DateTimeZone;

/**
 * Hours field.  Allows: * , / -.
 */
class HoursField extends AbstractField
{
    /**
     * {@inheritdoc}
     */
    protected $rangeStart = 0;

    /**
     * {@inheritdoc}
     */
    protected $rangeEnd = 23;

    protected $lastInvert = false;

    protected $offsetChange = 0;

    /**
     * {@inheritdoc}
     */
    public function isSatisfiedBy(DateTimeInterface $date, $value): bool
    {
        $checkValue = (int) $date->format('H');
        $retval = $this->isSatisfied($checkValue, $value);
        if ((! $retval) && ($this->offsetChange !== 0)) {
            $change = floor(($this->lastInvert ? -$this->offsetChange : $this->offsetChange) / 3600);
            $checkValue += (int) $change;
            $retval = $this->isSatisfied($checkValue, $value);
        }
        $this->offsetChange = 0;
        return $retval;
    }

    /**
     * {@inheritdoc}
     *
     * @param \DateTime|\DateTimeImmutable $date
     * @param string|null                  $parts
     */
    public function increment(DateTimeInterface &$date, $invert = false, $parts = null): FieldInterface
    {
        $date = $date->setTime((int) $date->format('H'), 0);
        $this->lastInvert = $invert;
        $this->offsetChange = 0;
        $offset = $date->getOffset();

        // Change timezone to UTC temporarily. This will
        // allow us to go back or forwards and hour even
        // if DST will be changed between the hours.
        if (null === $parts || '*' === $parts) {
            $timezone = $date->getTimezone();
            $date = $date->setTimezone(new DateTimeZone('UTC'));
            $date = $date->modify(($invert ? '-' : '+') . '1 hour');
            $date = $date->setTimezone($timezone);

            $date = $date->setTime((int) $date->format('H'), 0);
            return $this;
        }

        $parts = false !== strpos($parts, ',') ? explode(',', $parts) : [$parts];
        $hours = [];
        foreach ($parts as $part) {
            $hours = array_merge($hours, $this->getRangeForExpression($part, 23));
        }

        $current_hour = (int) $date->format('H');
        $position = $invert ? \count($hours) - 1 : 0;
        $countHours = \count($hours);
        if ($countHours > 1) {
            for ($i = 0; $i < $countHours - 1; ++$i) {
                if ((!$invert && $current_hour >= $hours[$i] && $current_hour < $hours[$i + 1]) ||
                    ($invert && $current_hour > $hours[$i] && $current_hour <= $hours[$i + 1])) {
                    $position = $invert ? $i : $i + 1;

                    break;
                }
            }
        }

        $hour = (int) $hours[$position];
        // Target hour causes a day change
        if ((!$invert && $current_hour >= $hour) || ($invert && $current_hour <= $hour)) {
            $date = $date->modify(($invert ? '-' : '+') . '1 day');
            $date = $date->setTime(($invert ? 23 : 0), 0);
            return $this;
        }

        $date = $date->setTime($hour, 0);

        $newOffset = $date->getOffset();
        $this->offsetChange = ($offset - $newOffset);

        $actualHour = (int) $date->format('H');
        // DST caused a roll-over - we're about to change zones
        if (($current_hour !== $actualHour) && ($this->offsetChange === 0)) {
            $nextValue = ($hour + ($invert ? -2 : 2));
            $dstCheck = clone $date;
            $dstCheck = $dstCheck->setTime($nextValue, 0);
            $this->offsetChange = ($offset - $dstCheck->getOffset());
        }

        return $this;
    }
}
