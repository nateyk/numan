<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\User;

/**
 * What a user can do in the marketplace, and what is standing in the way.
 *
 * This is the single place that answers that question. It existed as three
 * separate copies of the same judgement — the interest controller, the policy,
 * and inline logic in a settings controller and a checklist controller — which is
 * how the pages could disagree with each other and with the gate. A user shown as
 * ready on one screen and refused on another is the bug this replaces.
 *
 * The two capabilities are independent. A person can post freight and carry
 * freight, can hold either one, and can change their mind at any time. Nothing
 * here assumes the pair is exclusive.
 *
 * The requirements differ per capability and that difference is the point:
 * posting a load needs identity, offering to carry one also needs a base.
 * Collapsing them into one number is how somebody looks ready and is then turned
 * away.
 *
 * Every field here lives on the single public profile. There is no second
 * carrier record to consult and no onboarding state to satisfy: a complete
 * profile is what makes a capability usable.
 */
class MarketplaceReadiness
{
    /**
     * Identity every user needs before anybody can transact with them.
     *
     * A name and a way to reach them. Not a legal name, not a company
     * registration — those are what a good profile has, not what makes the first
     * conversation possible.
     *
     * @var list<string>
     */
    public const IDENTITY_FIELDS = ['display_name', 'phone'];

    /**
     * A carrier additionally needs somewhere to start from.
     *
     * @var list<string>
     */
    public const TRANSPORT_FIELDS = ['primary_location_id'];

    /**
     * @return array{
     *     profile_exists: bool,
     *     capabilities: array{posts_freight: bool, provides_transport: bool},
     *     acts_in_marketplace: bool,
     *     missing: array{posts_freight: list<string>, provides_transport: list<string>},
     *     ready: array{posts_freight: bool, provides_transport: bool},
     *     status: ?string,
     *     verification_status: string
     * }
     */
    public function for(User $user): array
    {
        $profile = $user->profile()->first();

        $postsFreight = (bool) $profile?->posts_freight;
        $providesTransport = (bool) $profile?->provides_transport;

        $identity = $this->missingIdentityFields($profile);
        $base = $this->missingTransportFields($profile);

        return [
            'profile_exists' => $profile !== null,
            'capabilities' => [
                'posts_freight' => $postsFreight,
                'provides_transport' => $providesTransport,
            ],
            'acts_in_marketplace' => $postsFreight || $providesTransport,
            'missing' => [
                // Only meaningful for somebody who has the capability. Reporting
                // gaps for a capability they do not hold would put "add your base
                // location" in front of somebody who only ever posts freight.
                'posts_freight' => $postsFreight ? $identity : [],
                'provides_transport' => $providesTransport
                    ? $this->dedupe([...$identity, ...$base])
                    : [],
            ],
            'ready' => [
                'posts_freight' => $postsFreight && $identity === [],
                'provides_transport' => $providesTransport
                    && $identity === []
                    && $base === [],
            ],
            'status' => $profile?->status,
            'verification_status' => $profile?->verification_status ?: 'unverified',
        ];
    }

    /**
     * @return list<string>
     */
    private function missingIdentityFields(?Profile $profile): array
    {
        return array_values(array_filter(
            self::IDENTITY_FIELDS,
            fn (string $field): bool => trim((string) $profile?->{$field}) === '',
        ));
    }

    /**
     * @return list<string>
     */
    private function missingTransportFields(?Profile $profile): array
    {
        return array_values(array_filter(
            self::TRANSPORT_FIELDS,
            fn (string $field): bool => blank($profile?->{$field}),
        ));
    }

    /**
     * Identity and transport requirements overlap, and a user missing both a
     * phone and a base should be told once.
     *
     * @param  list<string>  $fields
     * @return list<string>
     */
    private function dedupe(array $fields): array
    {
        return array_values(array_unique($fields));
    }
}
