<?php

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Omnifexit\OmniCore\Jobs\Auth\LogOutUserJob;
use Omnifexit\OmniCore\Jobs\Auth\SyncPermissionsJob;
use Omnifexit\OmniCore\Jobs\Auth\SyncRoleChangeJob;
use Omnifexit\OmniCore\Jobs\Auth\SyncUsersJob;
use Omnifexit\OmniCore\Sso\CacheKeys;
use Omnifexit\OmniCore\Sso\Http\EnsureUserNotRevoked;
use Omnifexit\OmniCore\Sso\Http\LauncherController;
use Omnifexit\OmniCore\Sso\Http\SsoController;
use Omnifexit\OmniCore\Sso\JobHandlers\LogOutUser;
use Omnifexit\OmniCore\Sso\JobHandlers\SyncRoleChange;
use Omnifexit\OmniCore\Sso\JobHandlers\SyncUsers;
use Omnifexit\OmniCore\Sso\RevokedUsers;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

class SsoTestUser extends Authenticatable
{
    use HasApiTokens, HasRoles, SoftDeletes;

    protected $table = 'users';

    protected $guarded = [];

    protected string $guard_name = 'web';
}

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('omniauth_id')->nullable();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('phone_number')->nullable();
        $table->string('discord_id')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password')->nullable();
        $table->rememberToken();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('personal_access_tokens', function (Blueprint $table) {
        $table->id();
        $table->morphs('tokenable');
        $table->string('name');
        $table->string('token', 64)->unique();
        $table->text('abilities')->nullable();
        $table->timestamp('last_used_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
    });

    Schema::create('permissions', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
    });

    Schema::create('roles', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
    });

    Schema::create('model_has_permissions', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->primary(['permission_id', 'model_id', 'model_type']);
    });

    Schema::create('model_has_roles', function (Blueprint $table) {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->primary(['role_id', 'model_id', 'model_type']);
    });

    Schema::create('role_has_permissions', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
        $table->primary(['permission_id', 'role_id']);
    });

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    config()->set('app.key', queueKey());
    config()->set('omni-core.service', 'omnidesk');
    config()->set('omni-core.handlers', [
        SyncUsersJob::class => SyncUsers::class,
        SyncRoleChangeJob::class => SyncRoleChange::class,
        LogOutUserJob::class => LogOutUser::class,
    ]);
    config()->set('auth.providers.users.model', SsoTestUser::class);
    config()->set('services.omniauth', [
        'client_id' => 'client-desk',
        'client_secret' => 'secret',
        'redirect' => 'https://desk.omnifexit.test/sso/callback',
        'host' => 'https://auth.admin.omnifexit.test',
        'internal_host' => 'http://omnifexit-auth:80',
    ]);
    config()->set('app.frontend_url', 'https://desk.omnifexit.test');
    config()->set('app.internal_api_key', 'internal-secret');

    Route::middleware('web')->group(function () {
        Route::get('/sso/redirect', [SsoController::class, 'redirect']);
        Route::get('/sso/callback', [SsoController::class, 'callback']);
        Route::post('/logout', [SsoController::class, 'logout']);
        Route::middleware(EnsureUserNotRevoked::class)->get('/api/user', fn () => response()->json(['id' => Auth::id()]));
        Route::middleware(EnsureUserNotRevoked::class)->get('/api/launcher', [LauncherController::class, 'index']);
    });
});

it('derives the cache prefix from omni-core.service and lets omni-core.sso.cache_prefix override it', function () {
    expect(CacheKeys::prefix())->toBe('desk')
        ->and(CacheKeys::revokedUser(7))->toBe('desk:revoke_user:7')
        ->and(CacheKeys::launcher('abc'))->toBe('desk:launcher:abc');

    config()->set('omni-core.sso.cache_prefix', 'custom');
    expect(CacheKeys::prefix())->toBe('custom');
});

it('only ends sessions older than the flag, so logging back in elsewhere saves nothing', function () {
    $this->travelTo(now());
    $user = SsoTestUser::create(['name' => 'Jane', 'email' => 'jane@example.test']);
    $before = now()->getTimestamp();

    $this->travel(10)->seconds();
    RevokedUsers::flag($user->id);

    expect(RevokedUsers::isFlagged($user->id))->toBeTrue()
        ->and(RevokedUsers::isFlagged($user->id, $before))->toBeTrue();

    $this->travel(10)->seconds();
    Auth::login($user);

    expect(session(RevokedUsers::SESSION_KEY))->toBe(now()->getTimestamp())
        ->and(RevokedUsers::isFlagged($user->id, session(RevokedUsers::SESSION_KEY)))->toBeFalse()
        ->and(RevokedUsers::isFlagged($user->id, $before))->toBeTrue();

    RevokedUsers::clear($user->id);
    expect(RevokedUsers::isFlagged($user->id))->toBeFalse();
});

it('redirects to the OmniAuth authorize URL via the registered Socialite driver', function () {
    $response = $this->get('/sso/redirect');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('https://auth.admin.omnifexit.test/oauth/authorize');
});

it('logs the user in on callback and assigns roles for this client, without sending anything to the queue', function () {
    Queue::fake();
    config()->set('omni-core.sso.permissions', ['view tickets', 'create tickets']);

    Role::create(['name' => 'admin', 'guard_name' => 'web']);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('999');
    $socialiteUser->shouldReceive('getName')->andReturn('Test User');
    $socialiteUser->shouldReceive('getEmail')->andReturn('test@example.test');
    $socialiteUser->user = [
        'phone_number' => '+31600000000',
        'roles' => [
            ['name' => 'admin', 'team_id' => 'client-desk'],
            ['name' => 'sales', 'team_id' => 'client-sales'],
        ],
    ];

    Socialite::shouldReceive('driver')->with('omniauth')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn($socialiteUser);

    $response = $this->get('/sso/callback');

    $response->assertRedirect('https://desk.omnifexit.test');
    $this->assertAuthenticated();

    $user = SsoTestUser::where('email', 'test@example.test')->firstOrFail();
    expect($user->omniauth_id)->toBe('999')
        ->and($user->phone_number)->toBe('+31600000000')
        ->and($user->getRoleNames()->all())->toBe(['admin']);

    Queue::assertNothingPushed();
});

it('sends the permission list and current role permissions to OmniAuth with the sync command', function () {
    Queue::fake();
    config()->set('omni-core.sso.permissions', ['view tickets', 'create tickets']);

    $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::create(['name' => 'view tickets', 'guard_name' => 'web']));

    $this->artisan('omni-core:sync-permissions')->assertSuccessful();

    Queue::assertPushedOn(SyncPermissionsJob::queueName(), SyncPermissionsJob::class, function (SyncPermissionsJob $job) {
        return $job->clientId === 'client-desk'
            && $job->permissions === ['view tickets', 'create tickets']
            && $job->roleAssignments === ['admin' => ['view tickets']];
    });
});

it('sends every run of the sync command, so a second deploy right after the first still counts', function () {
    Queue::fake();
    config()->set('omni-core.sso.permissions', ['view tickets']);

    $this->artisan('omni-core:sync-permissions')->assertSuccessful();
    $this->artisan('omni-core:sync-permissions')->assertSuccessful();

    Queue::assertPushed(SyncPermissionsJob::class, 2);
});

it('fails the sync command without sending anything when the list or the client is missing', function (array $config) {
    Queue::fake();
    config()->set('omni-core.sso.permissions', ['view tickets']);
    config()->set($config);

    $this->artisan('omni-core:sync-permissions')->assertFailed();

    Queue::assertNothingPushed();
})->with([
    'empty list' => [['omni-core.sso.permissions' => []]],
    'no client' => [['services.omniauth.client_id' => null]],
]);

it('denies access on callback when the user has no roles for this client', function () {
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('998');
    $socialiteUser->shouldReceive('getName')->andReturn('No Roles');
    $socialiteUser->shouldReceive('getEmail')->andReturn('noroles@example.test');
    $socialiteUser->user = ['roles' => []];

    Socialite::shouldReceive('driver')->with('omniauth')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn($socialiteUser);

    $this->get('/sso/callback')
        ->assertRedirect('https://desk.omnifexit.test/login?error=access_denied');

    $this->assertGuest();
});

it('blocks revoked and roleless users in EnsureUserNotRevoked', function () {
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $user = SsoTestUser::create(['name' => 'Jane', 'email' => 'jane@example.test']);

    $this->actingAs($user)->getJson('/api/user')->assertStatus(403);

    $user->assignRole('admin');
    $this->actingAs($user)->getJson('/api/user')->assertStatus(200);

    RevokedUsers::flag($user->id);
    $this->actingAs($user)->getJson('/api/user')->assertStatus(401);
});

it('fetches and caches the launcher payload per user', function () {
    Http::fake([
        '*/api/internal/users/u-1/launcher' => Http::response([
            'apps' => [['name' => 'OmniDesk']],
            'tools' => [],
        ]),
    ]);

    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $user = SsoTestUser::create(['name' => 'Jane', 'email' => 'jane@example.test', 'omniauth_id' => 'u-1']);
    $user->assignRole('admin');

    $this->actingAs($user)->getJson('/api/launcher')
        ->assertOk()
        ->assertExactJson(['apps' => [['name' => 'OmniDesk']], 'tools' => []]);

    expect(Cache::get('desk:launcher:u-1'))->toBe(['apps' => [['name' => 'OmniDesk']], 'tools' => []]);
});

it('syncs users, copies configured user_fields, and flags removed or role-changed users', function () {
    config()->set('omni-core.sso.user_fields', ['discord_id']);
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'staff', 'guard_name' => 'web']);

    $stale = SsoTestUser::create(['name' => 'Stale', 'email' => 'stale@example.test', 'omniauth_id' => 'u-stale']);
    $changed = SsoTestUser::create(['name' => 'Changed', 'email' => 'changed@example.test', 'omniauth_id' => 'u-changed']);
    $changed->assignRole('staff');

    $job = new SyncUsersJob(
        clientId: 'client-desk',
        usersData: [
            [
                'id' => 'u-changed',
                'email' => 'changed@example.test',
                'name' => 'Changed',
                'email_verified_at' => ['date' => now()->toIso8601String()],
                'discord_id' => 'discord-42',
                'roles' => [['name' => 'admin']],
            ],
        ],
    );

    $job->handle(app());

    expect($changed->fresh()->discord_id)->toBe('discord-42')
        ->and($changed->fresh()->hasRole('admin'))->toBeTrue()
        ->and($stale->fresh()->trashed())->toBeTrue()
        ->and(RevokedUsers::isFlagged($changed->id))->toBeTrue()
        ->and(RevokedUsers::isFlagged($stale->id))->toBeTrue();
});

it('syncs and deletes roles for this client in SyncRoleChange', function () {
    (new SyncRoleChangeJob(
        clientId: 'client-desk',
        permissions: ['view tickets', 'edit tickets'],
        roleName: 'support',
    ))->handle(app());

    $role = Role::where('name', 'support')->firstOrFail();
    expect($role->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['edit tickets', 'view tickets']);

    (new SyncRoleChangeJob(
        clientId: 'client-desk',
        permissions: [],
        roleName: 'support',
        isDeleted: true,
    ))->handle(app());

    expect(Role::where('name', 'support')->exists())->toBeFalse();
});

it('logs a user out on their next request when OmniAuth says they logged out', function () {
    $user = SsoTestUser::forceCreate(['name' => 'Logged Out', 'email' => 'out@example.com', 'omniauth_id' => 4242]);

    (new LogOutUser)->handle(new LogOutUserJob('client-desk', 4242));

    expect(RevokedUsers::isFlagged($user->id))->toBeTrue()
        ->and($user->fresh()->remember_token)->not->toBeNull();
});

it('ignores a logout about another client or an unknown user', function () {
    $user = SsoTestUser::forceCreate(['name' => 'Stays', 'email' => 'stays@example.com', 'omniauth_id' => 4343]);

    (new LogOutUser)->handle(new LogOutUserJob('client-sales', 4343));
    (new LogOutUser)->handle(new LogOutUserJob('client-desk', 9999));

    expect(RevokedUsers::isFlagged($user->id))->toBeFalse();
});

it('tells OmniAuth who logged out, as their OmniAuth session may have expired', function () {
    Queue::fake();
    $user = SsoTestUser::create(['name' => 'Jane', 'email' => 'jane@example.test', 'omniauth_id' => '77']);

    $this->actingAs($user)->post('/logout')->assertNoContent();

    Queue::assertPushedOn('omni-sync-request', LogOutUserJob::class, fn (LogOutUserJob $job) => $job->omniAuthUserId === 77 && $job->clientId === 'client-desk');
});
