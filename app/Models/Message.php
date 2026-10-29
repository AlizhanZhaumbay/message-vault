<?php

namespace App\Models;

use App\Enums\DestroyMode;
use App\Enums\DestroyReason;
use App\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

#[Fillable([
    'title', 'teaser', 'body', 'unlock_at', 'destroy_mode', 'max_views', 'destroy_at',
    'views_count', 'first_viewed_at', 'password_hash', 'owner_token_hash',
    'destroyed_at', 'destroyed_reason',
])]
#[Hidden(['body', 'password_hash', 'owner_token_hash'])]
class Message extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'unlock_at' => 'datetime',
            'destroy_mode' => DestroyMode::class,
            'max_views' => 'integer',
            'destroy_at' => 'datetime',
            'views_count' => 'integer',
            'first_viewed_at' => 'datetime',
            'destroyed_at' => 'datetime',
            'destroyed_reason' => DestroyReason::class,
        ];
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function status(): MessageStatus
    {
        return match (true) {
            $this->destroyed_at !== null, $this->isPastDestroyDate() => MessageStatus::Destroyed,
            $this->unlock_at?->isFuture() === true => MessageStatus::Sealed,
            default => MessageStatus::Available,
        };
    }

    public function isPastDestroyDate(): bool
    {
        return $this->destroyed_at === null
            && $this->destroy_mode === DestroyMode::AtDate
            && $this->destroy_at?->isPast() === true;
    }

    public function viewLimit(): ?int
    {
        return $this->destroy_mode->viewLimit($this->max_views);
    }

    public function remainingViews(): ?int
    {
        $limit = $this->viewLimit();

        return $limit === null ? null : max(0, $limit - $this->views_count);
    }

    public function secondsUntilUnlock(): int
    {
        return $this->unlock_at?->isFuture() ? (int) now()->diffInSeconds($this->unlock_at) : 0;
    }

    public function requiresPassword(): bool
    {
        return $this->password_hash !== null;
    }

    public function checkPassword(?string $password): bool
    {
        return ! $this->requiresPassword()
            || ($password !== null && Hash::check($password, $this->password_hash));
    }

    public function isOwnedBy(?string $ownerToken): bool
    {
        return $ownerToken !== null && hash_equals($this->owner_token_hash, hash('sha256', $ownerToken));
    }

    public function wasViewed(): bool
    {
        return $this->first_viewed_at !== null;
    }

    #[Scope]
    protected function whereStatus(Builder $query, MessageStatus $status): void
    {
        $now = now();
        $pastDestroyDate = fn (Builder $q) => $q
            ->where('destroy_mode', DestroyMode::AtDate)
            ->where('destroy_at', '<=', $now);

        match ($status) {
            MessageStatus::Destroyed => $query->where(fn (Builder $q) => $q
                ->whereNotNull('destroyed_at')
                ->orWhere($pastDestroyDate)),
            MessageStatus::Sealed => $query->whereNull('destroyed_at')
                ->whereNot($pastDestroyDate)
                ->where('unlock_at', '>', $now),
            MessageStatus::Available => $query->whereNull('destroyed_at')
                ->whereNot($pastDestroyDate)
                ->where(fn (Builder $q) => $q->whereNull('unlock_at')->orWhere('unlock_at', '<=', $now)),
        };
    }
}
