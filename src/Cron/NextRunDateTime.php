<?php
declare(strict_types=1);

namespace Cron;

use DateTime;
use DateTimeZone;

class NextRunDateTime
{

    /**
     * @var DateTime
     */
    protected $dt;

    /**
     * @var null|int The offset change triggered by the last modification in seconds
     *  Relative to the direction of movement, so a DST change of +1 hour is 3600 moving forwards and -3600 moving backwards
     */
    protected $lastChangeOffsetChange = null;

    /**
     * @var bool
     */
    protected $moveBackwards;

    /**
     * @var DateTimeZone Cached timezone (for timezoneSafeModify())
     */
    protected $timezone;

    /**
     * @var DateTimeZone (Always UTC)
     */
    protected $tzUtc;


    public function __construct(\DateTimeInterface $dateTime, bool $moveBackwards)
    {
        $this->timezone = $dateTime->getTimezone();
        // Clone the date/time value, but zero-out fields we don't care about
        // Don't use setTime because it can cause an offset change: https://bugs.php.net/bug.php?id=81074
        $this->dt = DateTime::createFromFormat("!Y-m-d H:iO", $dateTime->format("Y-m-d H:iP"), $this->timezone);
        $this->dt->setTimezone($this->timezone);
        $this->tzUtc = new DateTimeZone("UTC");
        $this->moveBackwards = $moveBackwards;
    }


    public function getLastChangeOffsetChange(): ?int
    {
        return $this->lastChangeOffsetChange;
    }


    public function isMovingBackwards(): bool
    {
        return $this->moveBackwards;
    }


    public function getDateTime(): DateTime
    {
        return clone $this->dt;
    }


    protected function updateLastChangeOffsetChange(int $previousOffset): void
    {
        $this->lastChangeOffsetChange = ($this->dt->getOffset() - $previousOffset);
    }


    protected function timezoneSafeModify(string $modification): void
    {
        $this->dt->setTimezone($this->tzUtc);
        $this->dt->modify($modification);
        $this->dt->setTimezone($this->timezone);
    }


    public function modify(string $modification): void
    {
        $previousOffset = $this->dt->getOffset();
        $this->timezoneSafeModify($modification);
        $this->updateLastChangeOffsetChange($previousOffset);
    }


    protected function log(string $function, string $message)
    {
        $function = str_replace(__CLASS__ ."::", "", $function);
        print $this->dt->format(\DateTime::RFC3339 ." e") ." :: {$function} :: {$message}\n";
    }


    public function setMinutes(int $target): void
    {
        $this->log(__METHOD__, "target: {$target}");
        $originalMinute = (int)$this->dt->format("i");

        $originalTimestamp = $this->getTimestamp();
        $originalHour = (int)$this->dt->format('H');
        $previousOffset = $this->dt->getOffset();

        if (! $this->moveBackwards) {
            if ($originalMinute >= $target) {
                $distance = 60 - $originalMinute;
                $this->log(__METHOD__, "Hour change needed :: +{$distance}");
                $this->timezoneSafeModify("+{$distance} minutes");

                /*
                $actualHour = (int)$this->dt->format('H');
                $actualMinute = (int)$this->dt->format('i');
                if (($actualHour !== ($originalHour + 1)) || ($actualMinute !== 0)) {
                    $this->log(__METHOD__, "DST fix needed :: {$distance}");
                    $offsetChange = ($previousOffset - $this->dt->getOffset());
                    $this->timezoneSafeModify("+{$offsetChange} seconds");

                    // $this->handleDaylightSavingsChange($originalTimestamp, "minute", $distance);
                    // $this->updateLastChangeOffsetChange($previousOffset);
                    // return;
                }
                */

                $originalHour = (int)$this->dt->format('H');
                $originalMinute = (int)$this->dt->format("i");
            }

            $distance = $target - $originalMinute;
            $this->log(__METHOD__, "Modify {$distance}");
            $this->timezoneSafeModify("+{$distance} minutes");
        } else {
            if ($originalMinute <= $target) {
                $this->log(__METHOD__, "Hour change needed (b) :: {$originalMinute} :: {$target}");
                $distance = ($originalMinute + 1);
                $this->timezoneSafeModify("-{$distance} minutes");
                /*
                $actualHour = (int)$this->dt->format('H');
                $actualMinute = (int)$this->dt->format('i');
                if (($actualHour !== ($originalHour - 1)) || ($actualMinute !== $target)) {
                    $this->log(__METHOD__, "DST fix needed: {$distance}");
                    $offsetChange = ($previousOffset - $this->dt->getOffset());
                    $this->timezoneSafeModify("+{$offsetChange} seconds");
                }
                */
                $originalHour = (int)$this->dt->format('H');
                $originalMinute = (int)$this->dt->format("i");
            }

            $distance = $originalMinute - $target;
            $this->log(__METHOD__, "Modify (b) {$distance}");
            $this->timezoneSafeModify("-{$distance} minutes");
        }

        $actualHour = (int)$this->dt->format('H');
        $actualMinute = (int)$this->dt->format('i');
        $this->log(__METHOD__, "target {$target} :: actual {$actualHour}:{$actualMinute} :: original {$originalHour}:{$originalMinute}");
        /*
        if (($actualHour !== $originalHour) || ($actualMinute !== $target)) {
            $this->handleDaylightSavingsChange($originalTimestamp, "minute", $distance);
        }
        */

        $this->updateLastChangeOffsetChange($previousOffset);
    }


    public function incrementHour(): void
    {
        $previousOffset = $this->dt->getOffset();
        $originalTimestamp = $this->getTimestamp();

        $this->timezoneSafeModify(($this->isMovingBackwards() ? "-" : "+") ."1 hour");
        $return = $this->setTimeHour($originalTimestamp);
        if ($return) {
            return;
        }

        $this->updateLastChangeOffsetChange($previousOffset);
    }


    protected function setTimeHour(int $originalTimestamp): bool
    {
        $this->log(__METHOD__, "Before time modify");
        $this->dt->setTime((int)$this->dt->format('H'), ($this->moveBackwards ? 59 : 0));
        $this->log(__METHOD__, "After time modify");

        // setTime caused the offset to change, moving time in the wrong direction
        // FIXME Minutes may change here due to DST change
        $actualTimestamp = $this->getTimestamp();
        if ((! $this->moveBackwards) && ($actualTimestamp <= $originalTimestamp)) {
            $this->log(__METHOD__, "Time moved in wrong direction");
            $this->timezoneSafeModify("+1 hour");
            return true;
        } elseif ($this->moveBackwards && ($actualTimestamp >= $originalTimestamp)) {
            $this->log(__METHOD__, "Time moved in wrong direction");
            $this->timezoneSafeModify("-1 hour");
            return true;
        }

        return false;
    }


    public function setHour(int $target): void
    {
        $this->log(__METHOD__, "{$target}");
        $originalHour = (int)$this->dt->format('H');

        $originalDay = (int)$this->dt->format('d');
        $originalTimestamp = $this->getTimestamp();
        $previousOffset = $this->dt->getOffset();

        if (! $this->moveBackwards) {
            if ($originalHour >= $target) {
                $this->log(__METHOD__, "Day change required (f)");
                $distance = 24 - $originalHour;
                $this->timezoneSafeModify("+{$distance} hours");

                $actualDay = (int)$this->dt->format('d');
                $actualHour = (int)$this->dt->format('H');
                if (($actualDay !== ($originalDay + 1)) && ($actualHour !== 0)) {
                    $this->log(__METHOD__, "DST fix needed :: {$distance}");
                    $offsetChange = ($previousOffset - $this->dt->getOffset());
                    $this->timezoneSafeModify("+{$offsetChange} seconds");
                }

                $originalDay = (int)$this->dt->format('d');
                $originalHour = (int)$this->dt->format('H');
            }

            $distance = $target - $originalHour;
            $this->log(__METHOD__, "Modify +{$distance}");
            $this->timezoneSafeModify("+{$distance} hours");
        } else {
            if ($originalHour <= $target) {
                $this->log(__METHOD__, "Day change required (b)");
                $distance = ($originalHour + 1);
                $this->timezoneSafeModify("-" . $distance . " hours");

                $actualDay = (int)$this->dt->format('d');
                $actualHour = (int)$this->dt->format('H');
                if (($actualDay !== ($originalDay - 1)) && ($actualHour !== 23)) {
                    $this->log(__METHOD__,"DST fix needed :: {$distance}");
                    $offsetChange = ($previousOffset - $this->dt->getOffset());
                    $this->timezoneSafeModify("+{$offsetChange} seconds");
                }

                $originalDay = (int)$this->dt->format('d');
                $originalHour = (int)$this->dt->format('H');
            }

            $distance = $originalHour - $target;
            $this->log(__METHOD__, "Modify (b) -{$distance}");
            $this->timezoneSafeModify("-{$distance} hours");
            $distance = 0 - $distance;
        }

        $this->setTimeHour($originalTimestamp);

        $actualDay = (int)$this->dt->format('d');
        $actualHour = (int)$this->dt->format('H');
        if (($actualDay !== $originalDay) || ($actualHour !== $target)) {
            if (! $this->moveBackwards) {
                /*
                if ($actualHour === ($target + 1)) {
                    $this->log(__METHOD__, "DST fix forwards, target + 1");
                    $this->timezoneSafeModify("-1 hour");
                }
                */
            } else {
                if ($actualHour === ($target - 1) || (($actualHour === 23) && ($target === 0))) {
                    $this->log(__METHOD__, "DST fix backwards, target -1");
                    $this->timezoneSafeModify("+1 hour");
                }
            }
        }

        $this->updateLastChangeOffsetChange($previousOffset);
    }


    public function incrementDay(): void
    {
        $previousOffset = $this->dt->getOffset();

        if (! $this->moveBackwards) {
            $this->timezoneSafeModify('+1 day');
            // FIXME setTime may cause an offset change: https://bugs.php.net/bug.php?id=81074
            $this->dt->setTime(0, 0);
        } else {
            $this->timezoneSafeModify('-1 day');
            // FIXME setTime may cause an offset change: https://bugs.php.net/bug.php?id=81074
            $this->dt->setTime(23, 59);
        }

        $this->updateLastChangeOffsetChange($previousOffset);
    }


    public function incrementMonth(): void
    {
        $previousOffset = $this->dt->getOffset();

        if (! $this->moveBackwards) {
            $this->dt->modify('first day of next month');
            // FIXME setTime may cause an offset change: https://bugs.php.net/bug.php?id=81074
            $this->dt->setTime(0, 0);
        } else {
            $this->dt->modify('last day of previous month');
            // FIXME setTime may cause an offset change: https://bugs.php.net/bug.php?id=81074
            $this->dt->setTime(23, 59);
        }

        $this->updateLastChangeOffsetChange($previousOffset);
    }


    public function getRemainingDaysInMonth(): int
    {
        $dtLastDay = clone $this->dt;
        $dtLastDay->modify("last day of this month");
        $daysInMonth = (int)$dtLastDay->format('d');
        return $daysInMonth - (int)$this->dt->format('d');
    }


    /**
     * @inheritDoc
     */
    public function diff($targetObject, $absolute = false)
    {
        return $this->dt->diff($targetObject, $absolute);
    }


    /**
     * @inheritDoc
     */
    public function format($format)
    {
        return $this->dt->format($format);
    }


    /**
     * @inheritDoc
     */
    public function getOffset()
    {
        return $this->dt->getOffset();
    }


    /**
     * Note: Prior to PHP 8.1, ->getTimestamp may modify the offset (and thus time) stored in the object
     * even for a DateTimeImmutable.
     *
     * @return int
     */
    public function getTimestamp()
    {
        return (int) $this->dt->format('U');
    }


    /**
     * @inheritDoc
     */
    public function getTimezone()
    {
        return $this->dt->getTimezone();
    }


    public function __clone()
    {
        $this->dt = clone $this->dt;
    }

}
