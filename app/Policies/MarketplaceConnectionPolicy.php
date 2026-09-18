<?php

namespace App\Policies;

use App\Models\MarketplaceConnection;
use App\Models\User;

class MarketplaceConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MarketplaceConnection $connection): bool
    {
        return $user->id === $connection->consignor_user_id
            || $user->id === $connection->transporter_user_id
            || (bool) $user->is_superadmin;
    }

    public function update(User $user, MarketplaceConnection $connection): bool
    {
        return $user->id === $connection->consignor_user_id
            || $user->id === $connection->transporter_user_id;
    }
}
