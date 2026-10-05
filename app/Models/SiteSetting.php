<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * One setting the BAC edits from the admin portal, stored as JSON by key.
 * Reads fall back to the default while the table's migration is pending.
 */
class SiteSetting extends Model
{
    protected $fillable = ['key', 'value', 'updated_by'];

    protected $casts = ['value' => 'array'];

    public static function available(): bool
    {
        $app = app();
        if (! $app->bound('site-settings.available')) {
            $app->instance('site-settings.available', Schema::hasTable('site_settings'));
        }

        return $app->make('site-settings.available');
    }

    public static function read(string $key, array $default = []): array
    {
        if (! self::available()) {
            return $default;
        }

        $value = self::query()->where('key', $key)->value('value');
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : $default;
    }

    public static function write(string $key, array $value, ?int $userId = null): self
    {
        return self::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $userId]);
    }
}
