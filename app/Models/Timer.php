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
        'animation_frame_limit',
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
        'animation_frame_limit' => 'integer',
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
        // Use timer_type to determine which field to use
        // For reload_based: use duration_seconds
        // For fixed_date: use target_datetime
        if ($this->isReloadBased() && $this->duration_seconds) {
            // For reload-based timers, always return the full duration
            // The GIF will show the countdown from this duration
            return max(0, (int) $this->duration_seconds);
        }

        if ($this->isFixedDate() && $this->target_datetime instanceof CarbonInterface) {
            $diff = now()->diffInSeconds($this->target_datetime, false);
            return max(0, $diff);
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

    /**
     * Determine if this timer's GIF is missing or out-of-date compared to the saved settings.
     */
    public function needsGifRegeneration(): bool
    {
        // No GIF at all
        if (empty($this->gif_url)) {
            return true;
        }

        // If we don't have a gif_* cache key, assume legacy behavior and force a regeneration once.
        // This ensures updated settings (like final screen toggles) are reflected and that we
        // migrate the record to a deterministic gif_{id}_{timestamp} cache key on next generate.
        $cacheKey = (string) ($this->cache_key ?? '');
        if (!str_starts_with($cacheKey, 'gif_')) {
            return true;
        }

        // cache_key is like gif_{id}_{timestamp}
        $parts = explode('_', $cacheKey);
        $ts = (int) end($parts);
        if (!$ts || !$this->updated_at) {
            return false;
        }

        // If timer was updated after GIF generation, it needs regeneration
        return $this->updated_at->getTimestamp() > $ts;
    }
}


