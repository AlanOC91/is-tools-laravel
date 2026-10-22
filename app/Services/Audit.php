<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Audit
{
    public static function record(string $action, string|int|null $subject = null, array $details = []): void
    {
        DB::table('is_tools_audit')->insert(['actor' => Auth::user()?->badge_id, 'action' => $action,
            'subject' => $subject, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
