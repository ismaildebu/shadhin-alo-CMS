<?php

namespace App\Models;

use App\Support\PollMath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poll extends Model
{
    protected $fillable = ['question', 'is_active', 'published_at'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('serial')->orderBy('id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    /** Already published (its publish time has arrived), open or closed. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    /** Published and still taking votes. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->published()->where('is_active', true);
    }

    public function isPublished(): bool
    {
        return $this->published_at === null || $this->published_at->lte(now());
    }

    public function isOpen(): bool
    {
        return $this->is_active && $this->isPublished();
    }

    /** The poll shown in the sidebar: the newest open one. */
    public static function latestOpen(): ?self
    {
        return static::open()->with('options')->orderByDesc('published_at')->orderByDesc('id')->first();
    }

    public function totalVotes(): int
    {
        return (int) $this->options->sum('votes_count');
    }

    /** @return array<int, int> option id => whole-number percent */
    public function percentages(): array
    {
        return PollMath::percentages($this->options->pluck('votes_count', 'id')->map(fn ($n) => (int) $n)->all());
    }

    public function shownDate(): \DateTimeInterface
    {
        return $this->published_at ?? $this->created_at ?? now();
    }
}
