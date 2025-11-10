<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Timer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'timer_type',
        'target_datetime',
        'duration_seconds',
        'width',
        'height',
        'skin',
        'text_position',
        'custom_text',
        'gif_url',
        'cache_key',
        'status',
        'skin_overrides',
        'skin_resolved_config',
    ];

    protected $appends = [
        'gif_url_with_cache_bust',
    ];

    protected $casts = [
        'target_datetime' => 'datetime',
        'width' => 'integer',
        'height' => 'integer',
        'skin_overrides' => 'array',
        'skin_resolved_config' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFixedDate(): bool
    {
        return $this->timer_type === 'fixed_date';
    }

    public function isReloadBased(): bool
    {
        return $this->timer_type === 'reload_based';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function remainingSecondsFromNow(): int
    {
        if ($this->isFixedDate() && $this->target_datetime instanceof CarbonInterface) {
            $diff = now()->diffInSeconds($this->target_datetime, false);
            return max(0, $diff);
        }

        if ($this->isReloadBased() && $this->duration_seconds) {
            // For reload-based timers, always return the full duration
            // The GIF will show the countdown from this duration
            return max(0, (int) $this->duration_seconds);
        }

        return 0;
    }

    public function getGifUrlWithCacheBustAttribute(): ?string
    {
        if (empty($this->gif_url)) {
            return null;
        }
        $cacheToken = $this->cache_key;
        if (!$cacheToken && $this->updated_at) {
            $cacheToken = (string) $this->updated_at->getTimestamp();
        }
        if (!$cacheToken) {
            return $this->gif_url;
        }
        $separator = str_contains($this->gif_url, '?') ? '&' : '?';
        return $this->gif_url . $separator . 'v=' . urlencode($cacheToken);
    }
}


