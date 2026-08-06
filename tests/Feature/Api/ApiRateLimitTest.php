<?php

use App\Enums\Ability;
use App\Models\DatabaseServer;
use App\Models\ScheduledRestore;
use App\Models\Snapshot;
use App\Models\User;
use App\Support\BouncerScope;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/**
 * Exercises the real rate-limit middleware end to end (not the RateLimiter
 * facade directly), so a regression in how the limiters are wired into
 * routes/api.php would be caught here.
 */
test('general api routes are throttled to 120 requests per minute', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    BouncerScope::apply(null);

    for ($i = 0; $i < 120; $i++) {
        $this->withToken($token)->getJson('/api/v1/user/organizations')->assertOk();
    }

    $this->withToken($token)
        ->getJson('/api/v1/user/organizations')
        ->assertStatus(429);

    // A second token for the same user has its own, independent bucket — the
    // limit is per credential, not per user. RequestGuard caches the resolved
    // user for the lifetime of the test's Auth manager, so it must be cleared
    // or every subsequent request would keep resolving the first token.
    $secondToken = $user->createToken('api-2')->plainTextToken;
    Auth::forgetGuards();

    for ($i = 0; $i < 120; $i++) {
        $this->withToken($secondToken)->getJson('/api/v1/user/organizations')->assertOk();
    }

    $this->withToken($secondToken)
        ->getJson('/api/v1/user/organizations')
        ->assertStatus(429);
});

test('requests with no distinguishable access-token id fall back to IP-based throttling', function () {
    // Sanctum::actingAs() mocks a PersonalAccessToken with no persisted `id`,
    // mirroring the real fallback case in production: a first-party/session
    // request whose currentAccessToken() carries no distinguishing identity.
    // The limiter key falls through to $request->ip(), so a shared IP is
    // throttled as a single bucket rather than bypassing the limit entirely.
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    BouncerScope::apply(null);

    for ($i = 0; $i < 120; $i++) {
        $this->getJson('/api/v1/user/organizations')->assertOk();
    }

    $this->getJson('/api/v1/user/organizations')->assertStatus(429);
});

test('the backup trigger route is throttled to 10 requests per minute', function () {
    Queue::fake();

    $user = User::factory()->withAbilities([Ability::RunBackups->value])->create();
    $server = createDatabaseServer(['database_names' => ['testdb']]);
    $token = $user->createToken('api')->plainTextToken;

    BouncerScope::apply(null);

    for ($i = 0; $i < 10; $i++) {
        $this->withToken($token)
            ->postJson("/api/v1/database-servers/{$server->id}/backup")
            ->assertStatus(202);
    }

    $this->withToken($token)
        ->postJson("/api/v1/database-servers/{$server->id}/backup")
        ->assertStatus(429);
});

test('the restore trigger route is throttled to 10 requests per minute', function () {
    Queue::fake();

    $user = User::factory()->withAbilities([Ability::OperateRestores->value])->create();
    $server = DatabaseServer::factory()->create(['database_type' => 'mysql']);
    $snapshot = Snapshot::factory()->forServer($server)->create();
    $token = $user->createToken('api')->plainTextToken;

    BouncerScope::apply(null);

    $payload = ['snapshot_id' => $snapshot->id, 'schema_name' => 'target_db'];

    for ($i = 0; $i < 10; $i++) {
        $this->withToken($token)
            ->postJson("/api/v1/database-servers/{$server->id}/restore", $payload)
            ->assertStatus(202);
    }

    $this->withToken($token)
        ->postJson("/api/v1/database-servers/{$server->id}/restore", $payload)
        ->assertStatus(429);
});

test('the scheduled-restore run trigger route is throttled to 10 requests per minute', function () {
    Artisan::spy();

    $user = User::factory()->withAbilities([Ability::OperateRestores->value])->create();
    [$source, $target] = createRestoreServerPair();
    $scheduled = ScheduledRestore::factory()->create([
        'source_server_id' => $source->id,
        'target_server_id' => $target->id,
    ]);
    $token = $user->createToken('api')->plainTextToken;

    BouncerScope::apply(null);

    for ($i = 0; $i < 10; $i++) {
        $this->withToken($token)
            ->postJson("/api/v1/scheduled-restores/{$scheduled->id}/run")
            ->assertStatus(202);
    }

    $this->withToken($token)
        ->postJson("/api/v1/scheduled-restores/{$scheduled->id}/run")
        ->assertStatus(429);
});
