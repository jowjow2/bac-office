<?php

namespace App\Support;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;

class LoginAudit
{
    public static function record(Request $request, ?User $user, string $method, string $status, ?string $failureReason = null): void
    {
        LoginLog::create([
            'user_id' => $user?->id,
            'login_method' => $method,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 65535),
            'status' => $status,
            'failure_reason' => $failureReason,
            'created_at' => now(),
        ]);
    }
}
