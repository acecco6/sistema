<?php

namespace App\Application\Reservations\Support;

use App\Domain\Branches\Entities\Branch;
use DateInterval;
use DateTimeImmutable;

final readonly class BranchOperatingWindow
{
    public function __construct(
        public DateTimeImmutable $opening,
        public DateTimeImmutable $closing,
    ) {}

    public static function forBusinessDate(
        Branch $branch,
        DateTimeImmutable $businessDate,
    ): self {
        $day = $businessDate->format('Y-m-d');

        $opening = new DateTimeImmutable(
            $day . ' ' . $branch->getOpeningTime()
        );

        $closing = new DateTimeImmutable(
            $day . ' ' . $branch->getClosingTime()
        );

        if ($closing <= $opening) {
            $closing = $closing->modify('+1 day');
        }

        return new self($opening, $closing);
    }

    public static function containing(
        Branch $branch,
        DateTimeImmutable $moment,
    ): self {
        $todayWindow = self::forBusinessDate($branch, $moment);

        if ($moment >= $todayWindow->opening) {
            return $todayWindow;
        }

        $previousDay = $moment->modify('-1 day');
        $previousWindow = self::forBusinessDate($branch, $previousDay);

        if (
            $moment >= $previousWindow->opening
            && $moment <= $previousWindow->closing
        ) {
            return $previousWindow;
        }

        return $todayWindow;
    }

    public function crossesMidnight(): bool
    {
        return $this->opening->format('Y-m-d') !== $this->closing->format('Y-m-d');
    }

    public function dateTimeForClock(string $time): DateTimeImmutable
    {
        $dateTime = new DateTimeImmutable(
            $this->opening->format('Y-m-d') . ' ' . $time
        );

        if ($this->crossesMidnight() && $dateTime < $this->opening) {
            return $dateTime->modify('+1 day');
        }

        return $dateTime;
    }

    public function containsRange(
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
    ): bool {
        return $startsAt >= $this->opening
            && $endsAt <= $this->closing;
    }

    public function alignToNextSlot(
        DateTimeImmutable $minimumStart,
        int $intervalMinutes,
    ): DateTimeImmutable {
        if ($minimumStart <= $this->opening) {
            return $this->opening;
        }

        $secondsFromOpening =
            $minimumStart->getTimestamp()
            - $this->opening->getTimestamp();

        $intervalSeconds = $intervalMinutes * 60;

        $slotsFromOpening = (int) ceil(
            $secondsFromOpening / $intervalSeconds
        );

        return $this->opening->add(
            new DateInterval(
                'PT' . ($slotsFromOpening * $intervalMinutes) . 'M'
            )
        );
    }
}
