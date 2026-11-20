<?php

namespace Tests\Feature;

use App\Events\PnpUserRegistered;
use App\Http\Resources\UserResource;
use App\Http\Middleware\CapturePnpContext;
use App\Models\Api\User as ApiUser;
use App\Models\UserAcquisition;
use App\Services\PnpAcquisitionService;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Str;
use Tests\TestCase;

class PnpRegistrationIdentityTest extends TestCase
{
    use DatabaseTransactions;

    private function createUser(): User
    {
        return User::create([
            'full_name' => 'PNP Test User',
            'role_name' => 'user',
            'role_id' => 1,
            'email' => 'pnp-test-' . Str::uuid() . '@example.test',
            'password' => bcrypt(Str::random(24)),
            'status' => User::$active,
            'created_at' => time(),
        ]);
    }

    public function test_new_users_get_unique_server_generated_uuids(): void
    {
        $first = User::create([
            'full_name' => 'PNP First Test User',
            'role_name' => 'user',
            'role_id' => 1,
            'email' => 'pnp-first-' . Str::uuid() . '@example.test',
            'password' => bcrypt(Str::random(24)),
            'status' => User::$active,
            'created_at' => time(),
            'pnp_user_uuid' => 'caller-supplied-uuid-is-ignored',
        ]);
        $second = $this->createUser();

        $this->assertTrue(Str::isUuid($first->pnp_user_uuid));
        $this->assertTrue(Str::isUuid($second->pnp_user_uuid));
        $this->assertNotSame($first->pnp_user_uuid, $second->pnp_user_uuid);
        $this->assertDatabaseHas('users', [
            'id' => $first->id,
            'pnp_user_uuid' => $first->pnp_user_uuid,
        ]);
    }

    public function test_existing_user_uuid_is_stable_and_cannot_be_reassigned(): void
    {
        $user = $this->createUser();
        $uuid = $user->pnp_user_uuid;

        $user->pnp_user_uuid = (string) Str::uuid();
        $user->full_name = 'Updated PNP Test User';
        $user->save();

        $this->assertSame($uuid, $user->fresh()->pnp_user_uuid);
    }

    public function test_database_rejects_null_pnp_user_uuid(): void
    {
        $user = $this->createUser();

        try {
            DB::table('users')->where('id', $user->id)->update(['pnp_user_uuid' => null]);
            $this->fail('The users table accepted a NULL PNP UUID.');
        } catch (QueryException $exception) {
            $this->assertSame($user->pnp_user_uuid, $user->fresh()->pnp_user_uuid);
        }
    }

    public function test_database_rejects_duplicate_pnp_user_uuid(): void
    {
        $first = $this->createUser();
        $second = $this->createUser();
        $secondUuid = $second->pnp_user_uuid;

        try {
            DB::table('users')->where('id', $second->id)->update([
                'pnp_user_uuid' => $first->pnp_user_uuid,
            ]);
            $this->fail('The users table accepted a duplicate PNP UUID.');
        } catch (QueryException $exception) {
            $this->assertSame($secondUuid, $second->fresh()->pnp_user_uuid);
        }
    }

    public function test_first_touch_attribution_persists_and_is_not_overwritten(): void
    {
        $user = $this->createUser();
        $service = app(PnpAcquisitionService::class);
        $firstTouch = $service->capture([
            'src' => 'newsletter',
            'ref' => 'partner-17',
            'campaign' => 'fall-launch',
            'target' => 'course-42',
            'untrusted' => 'discard-me',
        ]);

        $this->assertArrayNotHasKey('untrusted', $firstTouch);
        $service->storeFirstTouch($user, $firstTouch);
        $service->storeFirstTouch($user, $service->capture([
            'src' => 'later-ad',
            'campaign' => 'retargeting',
        ]));

        $record = UserAcquisition::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('newsletter', $record->acquisition_source);
        $this->assertSame('partner-17', $record->external_referral_key);
        $this->assertSame('fall-launch', $record->campaign_key);
        $this->assertSame('course-42', $record->target_reference);
        $this->assertNotNull($record->captured_at);
        $this->assertSame('newsletter', $service->forUser($user)['pnp_acquisition_source']);
    }

    public function test_registration_event_contains_user_and_first_touch_payload(): void
    {
        $user = $this->createUser();
        app(PnpAcquisitionService::class)->storeFirstTouch($user, [
            'acquisition_source' => 'newsletter',
            'external_referral_key' => 'partner-17',
            'campaign_key' => 'fall-launch',
            'target_reference' => 'course-42',
        ]);

        $event = new PnpUserRegistered($user->fresh());

        $this->assertSame($user->id, $event->userId);
        $this->assertSame($user->pnp_user_uuid, $event->pnpUserUuid);
        $this->assertSame($user->email, $event->email);
        $this->assertSame('newsletter', $event->acquisitionSource);
        $this->assertSame('partner-17', $event->externalReferralKey);
        $this->assertSame('fall-launch', $event->campaignKey);
        $this->assertSame('course-42', $event->targetReference);
        $this->assertSame($user->created_at, $event->registeredAt);
    }

    public function test_user_model_hides_credentials_and_internal_provider_ids(): void
    {
        $user = $this->createUser();
        app(PnpAcquisitionService::class)->storeFirstTouch($user, [
            'external_referral_key' => 'private-partner-key',
        ]);

        $hidden = (new User())->getHidden();

        $this->assertContains('password', $hidden);
        $this->assertContains('remember_token', $hidden);
        $this->assertContains('google_id', $hidden);
        $this->assertContains('facebook_id', $hidden);
        $this->assertContains('role_id', $hidden);

        // Referral values live in a separate private table, not serialized user data.
        $this->assertArrayNotHasKey('external_referral_key', $user->fresh()->toArray());
        $this->assertDatabaseHas('user_acquisitions', [
            'user_id' => $user->id,
            'external_referral_key' => 'private-partner-key',
        ]);
    }

    public function test_pnp_uuid_is_only_included_in_authenticated_own_user_resource(): void
    {
        $user = $this->createUser();
        $request = Request::create('/api/users/' . $user->id . '/profile', 'GET');

        $publicData = (new UserResource($user))->toArray($request);
        $this->assertArrayNotHasKey('pnp_user_uuid', $publicData);

        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        $ownData = (new UserResource($user))->toArray($request);
        $this->assertSame($user->pnp_user_uuid, $ownData['pnp_user_uuid']);

        $otherUser = $this->createUser();
        $otherData = (new UserResource($otherUser))->toArray($request);
        $this->assertArrayNotHasKey('pnp_user_uuid', $otherData);
    }

    public function test_emergency_database_update_route_is_disabled(): void
    {
        $this->get('/emergencyDatabaseUpdate')->assertNotFound();
    }

    public function test_test_auth_id_does_not_bypass_api_authentication(): void
    {
        $request = Request::create('/api/development/panel?test_auth_id=1', 'GET');
        $nextWasCalled = false;
        $response = (new \App\Http\Middleware\Api\Authenticate(app('auth')))->handle(
            $request,
            function () use (&$nextWasCalled) {
                $nextWasCalled = true;
                return response('authenticated');
            }
        );

        $this->assertFalse($nextWasCalled);
        $this->assertSame('unauthorized', $response->getData()->status);

        request()->merge(['test_auth_id' => 1]);
        $this->assertNull(apiAuth());
    }

    public function test_public_api_profile_uses_a_private_field_free_allowlist(): void
    {
        $user = $this->createUser();
        $profile = (new ApiUser())->forceFill([
            'id' => $user->id,
            'username' => $user->username,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'mobile' => '123456789',
            'iban' => 'private-iban',
            'account_id' => 'private-account',
            'identity_scan' => 'private-document.jpg',
            'certificate' => 'private-certificate.jpg',
        ]);

        $data = $profile->public_profile;

        $this->assertSame(['id', 'username', 'full_name', 'role_name', 'bio', 'headline', 'about', 'avatar', 'verified', 'offline', 'offline_message', 'public_message', 'meeting_status', 'followers_count', 'following_count', 'rate'], array_keys($data));
        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('mobile', $data);
        $this->assertArrayNotHasKey('iban', $data);
        $this->assertArrayNotHasKey('account_id', $data);
        $this->assertArrayNotHasKey('identity_scan', $data);
        $this->assertArrayNotHasKey('certificate', $data);

        $brief = $profile->brief;
        $this->assertArrayNotHasKey('email', $brief);
        $this->assertArrayNotHasKey('mobile', $brief);
        $this->assertArrayNotHasKey('address', $brief);
    }

    public function test_referral_session_is_cleared_after_registration_and_not_reused(): void
    {
        $session = new Store('pnp-session-test', new ArraySessionHandler(120));
        $session->start();
        $session->put('pnp_acquisition', app(PnpAcquisitionService::class)->capture([
            'src' => 'first-source',
            'ref' => 'first-partner',
        ]));

        $firstRequest = Request::create('/register');
        $firstRequest->setLaravelSession($session);
        app()->instance('request', $firstRequest);
        $firstUser = $this->createUser();

        $this->assertFalse($session->has('pnp_acquisition'));
        $this->assertSame('first-source', UserAcquisition::where('user_id', $firstUser->id)->value('acquisition_source'));

        $secondRequest = Request::create('/register?src=second-source&ref=second-partner');
        $secondRequest->setLaravelSession($session);
        app(CapturePnpContext::class)->handle($secondRequest, function () {
            return response('ok');
        });
        app()->instance('request', $secondRequest);
        $secondUser = $this->createUser();

        $this->assertSame('second-source', UserAcquisition::where('user_id', $secondUser->id)->value('acquisition_source'));
        $this->assertSame('second-partner', UserAcquisition::where('user_id', $secondUser->id)->value('external_referral_key'));
        $this->assertSame('first-source', UserAcquisition::where('user_id', $firstUser->id)->value('acquisition_source'));
        $this->assertFalse($session->has('pnp_acquisition'));
    }
}
