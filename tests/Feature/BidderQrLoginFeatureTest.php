<?php

use App\Mail\BidderApprovedQrMail;
use App\Mail\BidderRejectedMail;
use App\Models\BidderDocument;
use App\Models\User;
use App\Support\QrLoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
});

function createApprovedBidderForQr(array $overrides = []): User
{
    $user = User::create(array_merge([
        'name' => 'QR Approved Bidder',
        'email' => 'qr-bidder@example.com',
        'username' => 'qr-bidder',
        'password' => Hash::make('secret123'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'QR Builders Inc.',
    ], $overrides));

    $user->bidderProfile()->create([
        'company_name' => $user->company ?: 'QR Builders Inc.',
        'contact_number' => '09171230000',
        'business_address' => 'San Jose, Occidental Mindoro',
        'approval_status' => 'approved',
        'approved_at' => now(),
    ]);

    return $user;
}

it('approves a bidder, issues a hashed qr token, and sends the approval email', function () {
    Mail::fake();

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => Hash::make('password123'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $bidder = User::create([
        'name' => 'Pending Bidder',
        'email' => 'pending-bidder@example.com',
        'username' => 'pending-bidder',
        'password' => Hash::make('secret123'),
        'role' => 'bidder',
        'status' => 'pending',
        'company' => 'Pending Builders Inc.',
    ]);

    $bidder->bidderProfile()->create([
        'company_name' => 'Pending Builders Inc.',
        'contact_number' => '09171111111',
        'business_address' => 'Pending Address',
        'approval_status' => 'pending',
    ]);

    $response = testCase()
        ->actingAs($admin)
        ->patch(route('admin.users.approve', $bidder));

    $response->assertRedirect(route('admin.users.review', $bidder));

    $bidder->refresh();
    $token = $bidder->qrLoginTokens()->latest()->first();

    expect($bidder->status)->toBe('active');
    expect($bidder->bidderProfile->approval_status)->toBe('approved');
    expect($token)->not->toBeNull();
    expect($token->token_hash)->toHaveLength(64);
    expect($token->token_hash)->not->toBe('');
    expect($token->token_ciphertext)->not->toBeNull();

    Mail::assertSent(BidderApprovedQrMail::class, function (BidderApprovedQrMail $mail) use ($bidder) {
        return $mail->hasTo($bidder->email)
            && $mail->user->is($bidder)
            && str_contains($mail->qrDataUri, 'data:image/svg+xml;base64,');
    });
});

it('shows the admin bidder review page with registration documents and qr security details', function () {
    $admin = User::create([
        'name' => 'Admin Reviewer',
        'email' => 'review-admin@example.com',
        'password' => Hash::make('password123'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $bidder = createApprovedBidderForQr([
        'email' => 'review-bidder@example.com',
        'username' => 'review-bidder',
        'company' => 'Review Builders Inc.',
    ]);

    BidderDocument::create([
        'user_id' => $bidder->id,
        'document_type' => 'Registration Requirement 1',
        'original_name' => 'business-permit.pdf',
        'file_path' => 'bidder-registration-documents/business-permit.pdf',
        'status' => 'submitted',
        'uploaded_at' => now(),
    ]);

    app(QrLoginService::class)->issueForUser($bidder);

    $response = testCase()
        ->actingAs($admin)
        ->get(route('admin.users.review', $bidder));

    $response->assertOk();
    $response->assertSee('Bidder Review');
    $response->assertSee('Review Builders Inc.');
    $response->assertSee('Registration Requirement 1');
    $response->assertSee('QR Login Security');
    $response->assertSee('View QR');
    $response->assertSee('Download QR');
    $response->assertSee('Resend QR Email');
});

it('lets admins preview and download the latest bidder qr code directly', function () {
    $admin = User::create([
        'name' => 'Admin QR Viewer',
        'email' => 'admin-qr-viewer@example.com',
        'password' => Hash::make('password123'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $bidder = createApprovedBidderForQr([
        'email' => 'downloadable-bidder@example.com',
        'username' => 'downloadable-bidder',
        'company' => 'Downloadable Builders Inc.',
    ]);

    app(QrLoginService::class)->issueForUser($bidder);

    $previewResponse = testCase()
        ->actingAs($admin)
        ->get(route('admin.users.qr.preview', $bidder));

    $previewResponse->assertOk();
    $previewResponse->assertHeader('Content-Type', 'image/svg+xml');
    $previewResponse->assertHeader('Content-Disposition', 'inline; filename="bac-office-bidder-' . $bidder->id . '-qr.svg"');
    $previewResponse->assertSee('<svg', false);
    $previewResponse->assertSee('data-bac-office-qr-format="bidder-login"', false);
    $previewResponse->assertSee('data-bac-office-qr-payload="bac-office-qr:', false);

    $downloadResponse = testCase()
        ->actingAs($admin)
        ->get(route('admin.users.qr.download', $bidder));

    $downloadResponse->assertOk();
    $downloadResponse->assertHeader('Content-Type', 'image/svg+xml');
    $downloadResponse->assertHeader('Content-Disposition', 'attachment; filename="bac-office-bidder-' . $bidder->id . '-qr.svg"');
    $downloadResponse->assertSee('<svg', false);
    $downloadResponse->assertSee('data-bac-office-qr-format="bidder-login"', false);
    $downloadResponse->assertSee('data-bac-office-qr-payload="bac-office-qr:', false);
});

it('regenerates a bidder qr code for admin preview when the old token cannot be reconstructed', function () {
    $admin = User::create([
        'name' => 'Admin QR Recovery',
        'email' => 'admin-qr-recovery@example.com',
        'password' => Hash::make('password123'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $bidder = createApprovedBidderForQr([
        'email' => 'legacy-bidder@example.com',
        'username' => 'legacy-bidder',
    ]);

    $legacyToken = app(QrLoginService::class)->issueForUser($bidder)['token_record'];
    $legacyToken->forceFill(['token_ciphertext' => null])->save();

    $response = testCase()
        ->actingAs($admin)
        ->get(route('admin.users.qr.preview', $bidder));

    $response->assertOk();
    $response->assertSee('<svg', false);

    expect($bidder->qrLoginTokens()->active()->count())->toBe(1);
    expect($bidder->qrLoginTokens()->active()->latest()->first()?->token_ciphertext)->not->toBeNull();
});

it('lets admins resend a fresh qr email for an approved bidder', function () {
    Mail::fake();

    $admin = User::create([
        'name' => 'Admin Resend QR',
        'email' => 'admin-resend-qr@example.com',
        'password' => Hash::make('password123'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $bidder = createApprovedBidderForQr([
        'email' => 'resend-qr-bidder@example.com',
        'username' => 'resend-qr-bidder',
        'company' => 'Resend QR Builders Inc.',
    ]);

    app(QrLoginService::class)->issueForUser($bidder);

    $response = testCase()
        ->actingAs($admin)
        ->post(route('admin.users.qr.resend', $bidder));

    $response->assertRedirect(route('admin.users.review', $bidder));
    $response->assertSessionHas('success', 'QR email resent successfully.');

    Mail::assertSent(BidderApprovedQrMail::class, function (BidderApprovedQrMail $mail) use ($bidder) {
        return $mail->hasTo($bidder->email)
            && $mail->user->is($bidder)
            && str_contains($mail->loginUrl, '/login?auth_tab=qr');
    });

    expect($bidder->qrLoginTokens()->active()->count())->toBe(1);
});

it('rejects a bidder, revokes qr access, and sends the rejection email', function () {
    Mail::fake();

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => Hash::make('password123'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $bidder = createApprovedBidderForQr([
        'email' => 'rejected-bidder@example.com',
        'username' => 'rejected-bidder',
        'company' => 'Rejected Builders Inc.',
    ]);

    app(QrLoginService::class)->issueForUser($bidder);

    $response = testCase()
        ->actingAs($admin)
        ->patch(route('admin.users.reject', $bidder), [
            'rejection_reason' => 'Submitted documents were incomplete.',
        ]);

    $response->assertRedirect(route('admin.users.review', $bidder));

    $bidder->refresh();
    expect($bidder->status)->toBe('rejected');
    expect($bidder->bidderProfile->approval_status)->toBe('rejected');
    expect($bidder->qrLoginTokens()->where('is_active', true)->count())->toBe(0);

    Mail::assertSent(BidderRejectedMail::class, function (BidderRejectedMail $mail) use ($bidder) {
        return $mail->hasTo($bidder->email)
            && $mail->user->is($bidder)
            && $mail->reason === 'Submitted documents were incomplete.';
    });
});

it('signs the bidder in immediately on the first qr login scan by default', function () {
    $bidder = createApprovedBidderForQr();
    $qrIssue = app(QrLoginService::class)->issueForUser($bidder);

    $response = testCase()->postJson(route('login.qr'), [
        'qr_payload' => $qrIssue['payload'],
    ]);

    $response->assertOk();
    $response->assertJsonPath('ok', true);
    $response->assertJsonPath('redirect', route('bidder.dashboard'));

    testCase()->assertAuthenticatedAs($bidder);
    testCase()->assertDatabaseHas('login_logs', [
        'user_id' => $bidder->id,
        'login_method' => 'qr',
        'status' => 'success',
    ]);

    expect($bidder->qrLoginTokens()->latest()->first()?->activated_at)->not->toBeNull();
});

it('can still require password confirmation on first qr login when configured', function () {
    config()->set('bac-office.qr_login.require_password_on_first_use', true);

    $bidder = createApprovedBidderForQr([
        'email' => 'confirmed-qr-bidder@example.com',
        'username' => 'confirmed-qr-bidder',
    ]);
    $qrIssue = app(QrLoginService::class)->issueForUser($bidder);

    $firstAttempt = testCase()->postJson(route('login.qr'), [
        'qr_payload' => $qrIssue['payload'],
    ]);

    $firstAttempt->assertStatus(422);
    $firstAttempt->assertJsonPath('ok', false);
    $firstAttempt->assertJsonPath('tab', 'qr');
    $firstAttempt->assertJsonPath('requires_password_confirmation', true);
    $firstAttempt->assertJsonPath('message', 'Please confirm your password to activate this QR login code for the first time.');

    $secondAttempt = testCase()->postJson(route('login.qr'), [
        'qr_payload' => $qrIssue['payload'],
        'password' => 'secret123',
    ]);

    $secondAttempt->assertOk();
    $secondAttempt->assertJsonPath('ok', true);
    $secondAttempt->assertJsonPath('redirect', route('bidder.dashboard'));
});

it('blocks qr login for revoked tokens and keeps the bidder logged out', function () {
    $bidder = createApprovedBidderForQr([
        'email' => 'revoked-bidder@example.com',
        'username' => 'revoked-bidder',
    ]);

    $service = app(QrLoginService::class);
    $qrIssue = $service->issueForUser($bidder);
    $service->revokeAllForUser($bidder);

    $response = testCase()->postJson(route('login.qr'), [
        'qr_payload' => $qrIssue['payload'],
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('ok', false);
    $response->assertJsonPath('message', 'This QR login code has been revoked. Please contact the BAC Office for a new one.');

    testCase()->assertGuest();
    testCase()->assertDatabaseHas('login_logs', [
        'user_id' => $bidder->id,
        'login_method' => 'qr',
        'status' => 'failed',
        'failure_reason' => 'token_revoked',
    ]);
});

it('protects bidder dashboard routes with the approved bidder middleware', function () {
    $pendingBidder = User::create([
        'name' => 'Pending Middleware Bidder',
        'email' => 'pending-middleware@example.com',
        'username' => 'pending-middleware',
        'password' => Hash::make('secret123'),
        'role' => 'bidder',
        'status' => 'pending',
        'company' => 'Pending Middleware Inc.',
    ]);

    $pendingBidder->bidderProfile()->create([
        'company_name' => 'Pending Middleware Inc.',
        'contact_number' => '09179998888',
        'business_address' => 'Middleware Address',
        'approval_status' => 'pending',
    ]);

    testCase()
        ->actingAs($pendingBidder)
        ->get(route('bidder.dashboard'))
        ->assertForbidden();
});
