<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;

/**
 * Who hears about a workspace's subscription.
 *
 * Everyone who can manage billing, deduplicated by user id. Billing is a
 * workspace's business and not one person's job, so this is deliberately not
 * "the owner": a workspace where the owner has left and an admin handles the
 * money should still get the reminder. The permission check is the same
 * WorkspacePolicy::manageBilling the whole billing UI already uses, so there is
 * no second definition of who is allowed to act on billing here.
 *
 * Deduplicated because hasPermission() is a per-user check and a user can hold
 * more than one role on a workspace. Sending two identical emails because
 * somebody is both an admin and has the explicit permission would be its own bug.
 */
class ReminderRecipients
{
    /**
     * @return Collection<int, User>
     */
    public static function forWorkspace(Workspace $workspace): Collection
    {
        $recipients = [];

        // The owner always hears about it, unconditionally.
        //
        // Not via hasPermission(): that reads the owner's role from the
        // workspace_user pivot, so a workspace whose owner_id points at somebody
        // who was never attached fails the check -- and then the person who owns
        // the place gets no reminder about its money. owner_id is the authority on
        // ownership, so it is checked directly rather than inferred.
        if ($workspace->owner !== null) {
            $recipients[$workspace->owner->id] = $workspace->owner;
        }

        // Plus everybody else the workspace has explicitly given billing to.
        // Admins are not included by this: manage_billing is deliberately absent
        // from what an admin gets implicitly, and the reminder follows exactly
        // the same rule as the button that would spend the money.
        $members = User::query()
            ->whereIn('id', $workspace->users()->pluck('users.id'))
            ->get();

        foreach ($members as $member) {
            if ($workspace->hasPermission($member, 'manage_billing')) {
                $recipients[$member->id] = $member;
            }
        }

        // Keyed by id, so the owner who also holds the permission is one entry.
        return collect($recipients)->sortKeys()->values();
    }
}
