<?php

/**
 * Cleanup demo/test data from production
 * Run with: php cleanup-demo.php [--dry-run]
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$dryRun = in_array('--dry-run', $argv);

echo "=== Cleanup Demo Data ===\n";
echo "Mode: " . ($dryRun ? "DRY RUN" : "LIVE") . "\n\n";

$deleted = [
    'users' => 0,
    'workspaces' => 0,
    'freight_posts' => 0,
    'locations' => 0,
    'transporter_profiles' => 0,
    'freight_interests' => 0,
    'marketplace_connections' => 0,
];

// 1. Demo users (email containing demo/test)
$demoUsers = \App\Models\User::where(function ($q) {
    $q->where('email', 'like', '%demo%')
      ->orWhere('email', 'like', '%test%')
      ->orWhere('name', 'like', '%demo%')
      ->orWhere('name', 'like', '%test%');
})->get();

echo "Found {$demoUsers->count()} demo/test users\n";
foreach ($demoUsers as $user) {
    echo "  - User #{$user->id}: {$user->email} ({$user->name})\n";
    if (!$dryRun) {
        // Delete related data first
        $user->freightPosts()->delete();
        $user->freightInterests()->delete();
        $user->transporterProfile()->delete();
        $user->workspaces()->detach();
        $user->delete();
        $deleted['users']++;
    }
}

// 2. Demo workspaces
$demoWorkspaces = \App\Models\Workspace::where(function ($q) {
    $q->where('name', 'like', '%demo%')
      ->orWhere('name', 'like', '%test%')
      ->orWhere('slug', 'like', '%demo%')
      ->orWhere('slug', 'like', '%test%');
})->get();

echo "Found {$demoWorkspaces->count()} demo/test workspaces\n";
foreach ($demoWorkspaces as $ws) {
    echo "  - Workspace #{$ws->id}: {$ws->name} ({$ws->slug})\n";
    if (!$dryRun) {
        // Delete workspace data
        \App\Models\FreightPost::where('workspace_id', $ws->id)->delete();
        \App\Models\WorkspaceInvitation::where('workspace_id', $ws->id)->delete();
        \App\Models\WorkspaceApiKey::where('workspace_id', $ws->id)->delete();
        \App\Models\WebhookEndpoint::where('workspace_id', $ws->id)->delete();
        \App\Models\WorkspaceInviteLink::where('workspace_id', $ws->id)->delete();
        \App\Models\Announcement::where('workspace_id', $ws->id)->delete();
        \App\Models\WorkspaceComment::where('workspace_id', $ws->id)->delete();
        \App\Models\WorkspaceActivity::where('workspace_id', $ws->id)->delete();
        $ws->users()->detach();
        $ws->delete();
        $deleted['workspaces']++;
    }
}

// 3. Demo freight posts
$demoPosts = \App\Models\FreightPost::where(function ($q) {
    $q->where('cargo_description', 'like', '%demo%')
      ->orWhere('cargo_description', 'like', '%test%')
      ->orWhere('item_name', 'like', '%demo%')
      ->orWhere('item_name', 'like', '%test%');
})->get();

echo "Found {$demoPosts->count()} demo/test freight posts\n";
foreach ($demoPosts as $post) {
    echo "  - Post {$post->ulid}: {$post->cargo_description}\n";
    if (!$dryRun) {
        \App\Models\FreightInterest::where('freight_post_id', $post->id)->delete();
        $post->delete();
        $deleted['freight_posts']++;
    }
}

// 4. Demo locations (if any created for testing)
$demoLocations = \App\Models\Location::where(function ($q) {
    $q->where('name', 'like', '%demo%')
      ->orWhere('name', 'like', '%test%');
})->get();

echo "Found {$demoLocations->count()} demo/test locations\n";
foreach ($demoLocations as $loc) {
    echo "  - Location #{$loc->id}: {$loc->name}\n";
    if (!$dryRun) {
        // Check if used by any freight posts
        $used = \App\Models\FreightPost::where('origin_location_id', $loc->id)
            ->orWhere('destination_location_id', $loc->id)
            ->exists();
        if (!$used) {
            $loc->delete();
            $deleted['locations']++;
        } else {
            echo "    (skipped - in use by freight posts)\n";
        }
    }
}

// 5. Demo transporter profiles
$demoTransporters = \App\Models\TransporterProfile::where(function ($q) {
    $q->where('display_name', 'like', '%demo%')
      ->orWhere('display_name', 'like', '%test%')
      ->orWhere('company_name', 'like', '%demo%')
      ->orWhere('company_name', 'like', '%test%');
})->get();

echo "Found {$demoTransporters->count()} demo/test transporter profiles\n";
foreach ($demoTransporters as $tp) {
    echo "  - Transporter #{$tp->id}: {$tp->display_name}\n";
    if (!$dryRun) {
        $tp->documents()->delete();
        $tp->routes()->delete();
        $tp->truckTypes()->detach();
        $tp->delete();
        $deleted['transporter_profiles']++;
    }
}

// 6. Demo freight interests
$demoInterests = \App\Models\FreightInterest::where(function ($q) {
    $q->where('message', 'like', '%demo%')
      ->orWhere('message', 'like', '%test%')
      ->orWhere('contact_name', 'like', '%demo%')
      ->orWhere('contact_name', 'like', '%test%');
})->get();

echo "Found {$demoInterests->count()} demo/test freight interests\n";
foreach ($demoInterests as $interest) {
    echo "  - Interest #{$interest->id}\n";
    if (!$dryRun) {
        $interest->delete();
        $deleted['freight_interests']++;
    }
}

// 6. Demo marketplace connections
$demoConnections = \App\Models\MarketplaceConnection::where(function ($q) {
    $q->where('notes', 'like', '%demo%')
      ->orWhere('notes', 'like', '%test%');
})->get();

echo "Found {$demoConnections->count()} demo/test marketplace connections\n";
foreach ($demoConnections as $conn) {
    echo "  - Connection #{$conn->id}\n";
    if (!$dryRun) {
        \App\Models\MarketplaceConnectionOutcome::where('connection_id', $conn->id)->delete();
        $conn->delete();
        $deleted['marketplace_connections']++;
    }
}

echo "\n=== Summary ===\n";
foreach ($deleted as $type => $count) {
    if ($count > 0) {
        echo "  {$type}: {$count} deleted\n";
    }
}

if ($dryRun) {
    echo "\nRun without --dry-run to actually delete.\n";
} else {
    echo "\nCleanup complete!\n";
}