<?php

namespace App\Support;

use App\Models\QrLoginToken;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class QrLoginService
{
    public function issueForUser(User $user, bool $revokeExisting = true): array
    {
        if ($revokeExisting) {
            $this->revokeAllForUser($user);
        }

        $plainToken = bin2hex(random_bytes(32));
        $qrToken = QrLoginToken::create([
            'user_id' => $user->id,
            'token_hash' => $this->hashToken($plainToken),
            'token_ciphertext' => Crypt::encryptString($plainToken),
            'is_active' => true,
            'expires_at' => now()->addHours($this->tokenTtlHours()),
        ]);

        $qrPayload = $this->buildPayload($plainToken);
        $qrCodes = app(QrCodeService::class);

        return [
            'token' => $plainToken,
            'payload' => $qrPayload,
            'token_record' => $qrToken,
            'svg' => $qrCodes->toSvg($qrPayload, 260),
            'data_uri' => $qrCodes->toDataUri($qrPayload, 260),
            'login_url' => route('login.page', ['auth_tab' => 'qr']),
        ];
    }

    public function revokeAllForUser(User $user): void
    {
        $user->qrLoginTokens()
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'revoked_at' => now(),
            ]);
    }

    public function hashToken(string $plainToken): string
    {
        return hash_hmac('sha256', $plainToken, (string) config('app.key'));
    }

    public function findActiveToken(string $plainToken): ?QrLoginToken
    {
        return QrLoginToken::query()
            ->with('user.bidderProfile')
            ->active()
            ->where('token_hash', $this->hashToken($plainToken))
            ->first();
    }

    public function findToken(string $plainToken): ?QrLoginToken
    {
        return QrLoginToken::query()
            ->with('user.bidderProfile')
            ->where('token_hash', $this->hashToken($plainToken))
            ->first();
    }

    public function activateAndMarkUsed(QrLoginToken $token): void
    {
        $token->forceFill([
            'activated_at' => $token->activated_at ?? now(),
            'last_used_at' => now(),
        ])->save();
    }

    public function extractPlainToken(string $payload): ?string
    {
        $payload = trim($payload);

        if ($payload === '') {
            return null;
        }

        if (filter_var($payload, FILTER_VALIDATE_URL)) {
            $parsedQuery = parse_url($payload, PHP_URL_QUERY);

            if (is_string($parsedQuery) && $parsedQuery !== '') {
                parse_str($parsedQuery, $query);

                $token = trim((string) ($query['qr_token'] ?? $query['token'] ?? ''));

                return $token !== '' ? $token : null;
            }
        }

        if (str_starts_with($payload, 'bac-office-qr:')) {
            $payload = substr($payload, strlen('bac-office-qr:'));
        }

        return preg_match('/^[A-Fa-f0-9]{64}$/', $payload) ? strtolower($payload) : null;
    }

    public function buildArtifactsForToken(QrLoginToken $token, int $size = 260): ?array
    {
        $plainToken = $this->decryptStoredToken($token);

        if ($plainToken === null) {
            return null;
        }

        $payload = $this->buildPayload($plainToken);
        $qrCodes = app(QrCodeService::class);

        return [
            'token' => $plainToken,
            'payload' => $payload,
            'token_record' => $token,
            'svg' => $qrCodes->toSvg($payload, $size),
            'data_uri' => $qrCodes->toDataUri($payload, $size),
            'login_url' => route('login.page', ['auth_tab' => 'qr']),
        ];
    }

    protected function buildPayload(string $plainToken): string
    {
        return 'bac-office-qr:' . $plainToken;
    }

    protected function decryptStoredToken(QrLoginToken $token): ?string
    {
        $ciphertext = trim((string) $token->token_ciphertext);

        if ($ciphertext === '') {
            return null;
        }

        try {
            $plainToken = trim(Crypt::decryptString($ciphertext));
        } catch (DecryptException) {
            return null;
        }

        return preg_match('/^[A-Fa-f0-9]{64}$/', $plainToken) ? strtolower($plainToken) : null;
    }

    protected function tokenTtlHours(): int
    {
        return max(1, (int) config('bac-office.qr_login.token_ttl_hours', 720));
    }
}
