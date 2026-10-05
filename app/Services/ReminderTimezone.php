<?php

namespace App\Services;

use Carbon\CarbonInterface;
use DateTimeZone;

/**
 * Renders a reminder date in the reader's own timezone.
 *
 * A reminder that says "ends 2026-10-05 02:14" when the reader is in Addis is
 * technically correct and practically useless. So each recipient is rendered in
 * their own zone, and where they have not chosen one, in Africa/Addis_Ababa --
 * the fallback is the business's, not the server's, because the server's is
 * whatever UTC means and this is an Ethiopian company.
 *
 * Deliberately not the browser's guess either. There is no way to know the
 * recipient's zone at the moment the command runs, and a stored preference that
 * silently defaults to UTC is how the "expires tomorrow" email arrives on the
 * wrong morning.
 */
class ReminderTimezone
{
    /**
     * The zone to render for one recipient.
     */
    public static function for(?string $userTimezone): DateTimeZone
    {
        $fallback = (string) config('marketplace.reminder_fallback_timezone', 'Africa/Addis_Ababa');

        if ($userTimezone === null || trim($userTimezone) === '') {
            return new DateTimeZone(self::isValid($fallback) ? $fallback : 'UTC');
        }

        return new DateTimeZone(self::isValid($userTimezone) ? $userTimezone : $fallback);
    }

    /**
     * The end date, formatted for one recipient.
     */
    public static function format(CarbonInterface $when, ?string $userTimezone): string
    {
        return $when->clone()->setTimezone(self::for($userTimezone))->format('j M Y');
    }

    /**
     * Whether a string is a timezone identifier PHP recognises.
     *
     * Checked rather than caught: an invalid zone throws, and a throw per
     * recipient in a loop over a workspace is a bad way to find out somebody set
     * their timezone to "Africa/Addis".
     */
    private static function isValid(string $timezone): bool
    {
        return in_array($timezone, timezone_identifiers_list(), true);
    }
}
