<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

/** A signed reference to a bidder/project pair, never a proof of payment. No personal details or amounts are encoded. */
final class BiddingFeeQr
{
    public const PREFIX = 'SJBAC-PAY:';

    public static function encode(User $bidder, Project $project): string
    {
        $payload = pack('J2', $bidder->id, $project->id);

        return self::PREFIX.rtrim(strtr(base64_encode($payload.self::signature($payload)), '+/', '-_'), '=');
    }

    /** @return array{bidder: int, project: int}|null */
    public static function decode(string $token): ?array
    {
        if (! str_starts_with($token, self::PREFIX)) return null;
        $encoded = substr($token, strlen(self::PREFIX));
        if (! preg_match('/^[A-Za-z0-9_-]{64}$/D', $encoded)) return null;
        $bytes = base64_decode(strtr($encoded, '-_', '+/'), true);
        if ($bytes === false || strlen($bytes) !== 48) return null;
        $payload = substr($bytes, 0, 16);
        if (! hash_equals(self::signature($payload), substr($bytes, 16))) return null;
        $pair = unpack('Jbidder/Jproject', $payload);
        if (! is_int($pair['bidder']) || $pair['bidder'] <= 0 || ! is_int($pair['project']) || $pair['project'] <= 0) return null;

        return $pair;
    }

    private static function signature(string $payload): string
    {
        return hash_hmac('sha256', "SJBAC bidding fee QR v1\0".$payload, Crypt::getKey(), true);
    }
}
