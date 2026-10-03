<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Validates and normalises Ethiopian mobile numbers.
 *
 * Every number is stored in E.164 form (+251XXXXXXXXX) so a single phone
 * column can be compared, displayed and dialled without guessing what format a
 * particular user typed.
 *
 * Accepted inputs:
 *   +251911234567   251911234567   00251911234567   +251 91 123 4567
 *   0911234567      0711234567     091 123 4567
 *
 * Ethiopian mobile numbers begin 9 (Ethio Telecom) or 7 (Safaricom). Anything
 * else is rejected rather than normalised into something we did not expect.
 */
class PhoneNumberService
{
    /**
     * Mobile prefixes valid for Ethiopia, without the country code.
     *
     * @var list<string>
     */
    public const VALID_PREFIXES = ['9', '7'];

    public const COUNTRY_CODE = '251';

    /**
     * The canonical error message, so every entry point says the same thing.
     */
    public const INVALID_MESSAGE = 'Enter an Ethiopian mobile number, for example +251911234567 or 0911234567.';

    /**
     * Normalise a user-typed number, or return null if it is not a valid
     * Ethiopian mobile number.
     */
    public function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        // Keep only digits and a leading plus.
        $trimmed = trim($input);

        if ($trimmed === '') {
            return null;
        }

        $hasPlus = Str::startsWith($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return null;
        }

        // 00251... international prefix
        if (Str::startsWith($digits, '00'.self::COUNTRY_CODE)) {
            $digits = Str::after($digits, '00'.self::COUNTRY_CODE);
        } elseif (Str::startsWith($digits, self::COUNTRY_CODE) && strlen($digits) === 12) {
            // Bare 251...
            $digits = Str::after($digits, self::COUNTRY_CODE);
        } elseif (Str::startsWith($digits, '0') && strlen($digits) === 10) {
            // Local 09... / 07...
            $digits = Str::after($digits, '0');
        }

        // Must now be exactly nine digits starting with a mobile prefix.
        if (strlen($digits) !== 9) {
            return null;
        }

        if (! in_array(Str::substr($digits, 0, 1), self::VALID_PREFIXES, true)) {
            return null;
        }

        return '+'.self::COUNTRY_CODE.$digits;
    }

    public function isValid(?string $input): bool
    {
        return $this->normalize($input) !== null;
    }

    /**
     * The validation rules for a phone field.
     *
     * The format check is permissive on input (digits, spaces, dashes, a leading
     * plus) because the real format check is normalize(), which the caller runs
     * to produce the stored value.
     *
     * @return list<mixed>
     */
    public function rules(bool $required = false): array
    {
        return array_values(array_filter([
            $required ? 'required' : 'nullable',
            'string',
            'max:30',
            'regex:/^\+?[0-9\s\-\(\)\.]+$/',
        ]));
    }
}
