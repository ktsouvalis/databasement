<?php

use App\Enums\Ability;
use App\Models\User;
use App\Support\BouncerScope;
use Illuminate\Support\Facades\Queue;

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
});

test('trigger-action api routes are throttled to 10 requests per minute', function () {
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
