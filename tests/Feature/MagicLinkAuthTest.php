<?php

namespace Tests\Feature;

use App\Events\AccountCredentialsEvent;
use App\Mail\DynamicEmail;
use App\Models\Account;
use App\Models\Application;
use App\Models\User;
use App\Services\MagicLinkService;
use App\Support\LastGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MagicLinkAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_magic_link_logs_the_merchant_in(): void
    {
        $account = Account::factory()->create();
        $application = Application::factory()->create(['account_id' => $account->id]);

        $link = MagicLinkService::for($account, $application);

        $response = $this->get($link);

        $response->assertRedirect(route('applications.status', $application));
        $this->assertTrue(auth('account')->check());
        $this->assertTrue(auth('account')->id() === $account->id);
        $this->assertNotNull($account->fresh()->first_login_at);
    }

    public function test_merchant_with_no_applications_lands_on_empty_applications_list(): void
    {
        $account = Account::factory()->create();

        $link = MagicLinkService::for($account);

        $response = $this->get($link);

        $response->assertRedirect(route('applications'));
    }

    public function test_expired_link_mails_a_fresh_one_instead_of_rejecting(): void
    {
        Mail::fake();

        $account = Account::factory()->create();

        $link = URL::temporarySignedRoute(
            'account.magic-link',
            now()->subMinute(),
            ['account' => $account->id]
        );

        $response = $this->get($link);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/LinkSent')
            ->where('email', $account->email)
        );

        $this->assertFalse(auth('account')->check());
        Mail::assertQueued(DynamicEmail::class);
    }

    public function test_tampered_signature_is_rejected_and_sends_no_mail(): void
    {
        Mail::fake();

        $account = Account::factory()->create();

        $link = MagicLinkService::for($account);
        $tampered = preg_replace('/signature=.*/', 'signature=deadbeef', $link);

        $response = $this->get($tampered);

        $response->assertForbidden();
        $this->assertFalse(auth('account')->check());
        Mail::assertNothingQueued();
    }

    public function test_tampering_does_not_disturb_an_existing_staff_session(): void
    {
        Mail::fake();

        $staff = User::factory()->create();
        $account = Account::factory()->create();

        $this->actingAs($staff, 'web');

        $link = MagicLinkService::for($account);
        $tampered = preg_replace('/signature=.*/', 'signature=deadbeef', $link);

        $this->get($tampered)->assertForbidden();

        $this->assertTrue(auth('web')->check());
        $this->assertFalse(auth('account')->check());
    }

    public function test_request_link_form_replies_identically_for_real_and_unknown_email(): void
    {
        Mail::fake();

        $account = Account::factory()->create();

        $realResponse = $this->post('/account/login', ['email' => $account->email]);
        $fakeResponse = $this->post('/account/login', ['email' => 'nobody-here@example.com']);

        $this->assertSame(
            $realResponse->getSession()->get('success'),
            $fakeResponse->getSession()->get('success')
        );
        $realResponse->assertRedirect();
        $fakeResponse->assertRedirect();

        Mail::assertQueued(DynamicEmail::class, 1);
    }

    public function test_clicking_a_merchant_link_ends_an_existing_staff_session(): void
    {
        $staff = User::factory()->create();
        $account = Account::factory()->create();

        $this->actingAs($staff, 'web');
        $this->assertTrue(auth('web')->check());

        $link = MagicLinkService::for($account);

        $this->get($link);

        $this->assertFalse(auth('web')->check());
        $this->assertTrue(auth('account')->check());
    }

    public function test_a_lapsed_merchant_session_is_offered_the_merchant_login_form(): void
    {
        $account = Account::factory()->create();

        $link = MagicLinkService::for($account);
        $loginResponse = $this->get($link);

        // The test client doesn't carry cookies between calls like a real browser
        // does, so the "portal_guard" cookie set on login has to be forwarded by hand.
        $guardCookieValue = $loginResponse->getCookie(LastGuard::COOKIE)->getValue();

        $this->withCookie(LastGuard::COOKIE, $guardCookieValue)
            ->delete('/account/logout');

        $response = $this->withCookie(LastGuard::COOKIE, $guardCookieValue)
            ->get('/dashboard');

        $response->assertRedirect(route('account.login'));
    }

    public function test_a_lapsed_staff_session_is_offered_the_staff_login_form(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff, 'web');
        LastGuard::remember('web');
        $this->delete('/logout');

        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
    }
}
