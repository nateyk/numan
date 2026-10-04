<?php

namespace App\Services;

/**
 * Strips contact data out of an activity-log properties payload.
 *
 * Two separate problems, two separate defences:
 *
 *  1. Future writes. Profile and TransporterProfile use an explicit logOnly()
 *     allowlist that omits the contact columns, so nothing new is recorded.
 *
 *  2. History. Rows written before that fix still hold plaintext phone,
 *     whatsapp, email and legal_name, because the models used logFillable(),
 *     which logs the value of every fillable attribute. This class removes them
 *     from the stored JSON without deleting the row, so the audit trail still
 *     records that a profile was updated and when, just not what it contained.
 *
 * The audit trail is permanent and exportable, so "we will stop writing it" is
 * not the same as "it is not there".
 */
class ActivityPropertyScrubber
{
    /**
     * Column names whose values must never be retained.
     *
     * Matches Profile and TransporterProfile fillables plus the legacy
     * per-interest contact columns, which were also fillable.
     *
     * @var list<string>
     */
    public const SENSITIVE_KEYS = [
        'phone',
        'whatsapp',
        'email',
        'legal_name',
        'contact_name',
        'contact_phone',
        'contact_email',

        // User is also logged with logFillable() and its password column is
        // fillable, so 63 bcrypt hashes were written into the audit trail. A
        // hash is not plaintext, but it is offline-crackable material and it
        // has no business in a table that gets rendered to workspace members
        // and exported to CSV.
        'password',
    ];

    /**
     * Any key beginning with this is removed. NumanFreight handles no payments,
     * so bank details on a profile or an interest have no consumer at all.
     */
    public const SENSITIVE_PREFIX = 'bank_';

    /**
     * The sub-bags of a properties payload that hold attribute values.
     *
     * @var list<string>
     */
    public const VALUE_BAGS = ['attributes', 'old'];

    /**
     * The subject types whose rows are scrubbed.
     *
     * User is here because it carries the same logFillable() defect as the two
     * profile models and, unlike them, was writing password hashes.
     *
     * @var list<string>
     */
    public const SUBJECT_TYPES = ['Profile', 'TransporterProfile', 'FreightInterest', 'User'];

    /**
     * Is this attribute name one we must not keep?
     */
    public function isSensitive(string $key): bool
    {
        return in_array($key, self::SENSITIVE_KEYS, true)
            || str_starts_with($key, self::SENSITIVE_PREFIX);
    }

    /**
     * Remove sensitive keys from a decoded properties payload.
     *
     * @param  array<string, mixed>  $properties
     * @return array{0: array<string, mixed>, 1: int} the cleaned payload and how many keys were removed
     */
    public function scrub(array $properties): array
    {
        $removed = 0;

        foreach (self::VALUE_BAGS as $bag) {
            if (! isset($properties[$bag]) || ! is_array($properties[$bag])) {
                continue;
            }

            foreach (array_keys($properties[$bag]) as $key) {
                if (! $this->isSensitive((string) $key)) {
                    continue;
                }

                unset($properties[$bag][$key]);
                $removed++;
            }
        }

        return [$properties, $removed];
    }

    /**
     * Scrub a raw properties JSON string.
     *
     * @return array{0: string|null, 1: int} null when the row needs no change
     */
    public function scrubJson(?string $json): array
    {
        if ($json === null || $json === '') {
            return [null, 0];
        }

        $decoded = json_decode($json, true);

        // Not valid JSON, or not an object. Leave it alone rather than
        // destroying an audit row we do not understand.
        if (! is_array($decoded)) {
            return [null, 0];
        }

        [$cleaned, $removed] = $this->scrub($decoded);

        if ($removed === 0) {
            return [null, 0];
        }

        return [json_encode($cleaned), $removed];
    }
}
