<?php

namespace App\Policies;

use App\Enums\FreightInterestStatus;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\User;

class FreightInterestPolicy
{
    public function create(User $user, FreightPost $post): bool
    {
        if ($user->id === $post->user_id) {
            return false;
        }

        if (! $user->isProfileOnboarded()) {
            return false;
        }

        $profile = $user->profile()->first();
        if (! $profile || ! $profile->provides_transport) {
            return false;
        }

        $transporter = $user->transporterProfile()->first();
        if (! $transporter || $transporter->status !== 'approved') {
            return false;
        }

        if ($post->status !== \App\Enums\FreightPostStatus::Active->value) {
            return false;
        }

        $exists = FreightInterest::where('freight_post_id', $post->id)
            ->where('user_id', $user->id)
            ->whereNotIn('status', [FreightInterestStatus::Rejected->value, FreightInterestStatus::Withdrawn->value])
            ->exists();

        return ! $exists;
    }

    public function withdraw(User $user, FreightInterest $interest): bool
    {
        if ($user->id !== $interest->user_id) {
            return false;
        }

        return in_array($interest->status, [FreightInterestStatus::Submitted->value, FreightInterestStatus::Shortlisted->value], true);
    }

    public function updateStatus(User $user, FreightInterest $interest): bool
    {
        return $user->id === $interest->freightPost->user_id;
    }

    public function view(User $user, FreightInterest $interest): bool
    {
        return $user->id === $interest->user_id || $user->id === $interest->freightPost->user_id || (bool) $user->is_superadmin;
    }
}
