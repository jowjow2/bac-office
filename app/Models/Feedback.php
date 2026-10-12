<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/** A suggestion, a problem report or a word of praise sent through the portal's feedback form. */
class Feedback extends Model
{
    protected $table = 'feedback';

    public const CATEGORIES = [
        'suggestion' => ['label' => 'Suggestion', 'icon' => 'fa-lightbulb'],
        'problem' => ['label' => 'Problem', 'icon' => 'fa-triangle-exclamation'],
        'praise' => ['label' => 'Praise', 'icon' => 'fa-heart'],
    ];

    protected $fillable = ['category', 'rating', 'message', 'name', 'email', 'page_url', 'user_id', 'reviewed_at', 'reviewed_by'];

    protected $casts = ['rating' => 'integer', 'reviewed_at' => 'datetime'];

    /** False until the database update that creates the table has been applied. */
    public static function available(): bool
    {
        static $available = null;

        return $available ??= Schema::hasTable('feedback');
    }

    public function scopeUnreviewed(Builder $query): Builder
    {
        return $query->whereNull('reviewed_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category]['label'] ?? ucfirst((string) $this->category);
    }
}
