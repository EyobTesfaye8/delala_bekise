<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'full_name', 'phone', 'email', 'password',
    'type', 'status', 'guest_uuid', 'avatar_s3_key', 'phone_verified_at',
    'circumvention_strike_count', 'login_attempt_count', 'locked_until',
    'totp_secret', 'totp_enabled_at', 'fcm_token',
])]
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'locked_until' => 'datetime',
            'totp_enabled_at' => 'datetime',
            'login_attempt_count' => 'integer',
            'circumvention_strike_count' => 'integer',
            'password' => 'hashed',
        ];
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function isGuest(): bool
    {
        return $this->type === 'guest';
    }

    public function isBuyer(): bool
    {
        return in_array($this->type, ['guest', 'buyer']);
    }

    public function isDelala(): bool
    {
        return $this->type === 'delala';
    }

    public function isAdmin(): bool
    {
        return $this->type === 'admin';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function kycProfile(): HasOne
    {
        return $this->hasOne(KycProfile::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(DelalaJob::class, 'buyer_id');
    }

    public function buyerEscrows(): HasMany
    {
        return $this->hasMany(EscrowTransaction::class, 'buyer_id');
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'sender_id');
    }

    /**
     * Determine if the user has any ongoing deals.
     * Ongoing deals include:
     * - Active jobs not completed or canceled (e.g. open, negotiating, contracted, draft)
     * - Active escrow transactions not in terminal states (released, force_released, force_refunded)
     */
    public function hasOngoingDeals(): bool
    {
        $hasOngoingJobs = $this->jobs()
            ->whereNotIn('status', ['completed', 'canceled'])
            ->exists();

        if ($hasOngoingJobs) {
            return true;
        }

        return $this->buyerEscrows()
            ->whereNotIn('status', ['released', 'force_released', 'force_refunded'])
            ->exists();
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class, 'delala_id');
    }

    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class, 'delala_id');
    }

    public function ratingsReceived(): HasMany
    {
        return $this->hasMany(Rating::class, 'delala_id');
    }

    /**
     * Average star rating for a Delala (revealed reviews only).
     */
    public function averageRating(): float
    {
        return round((float) ($this->ratingsReceived()->where('is_revealed', true)->avg('stars') ?? 0.0), 1);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // For security, only admins can access the filament panel
        return $this->isAdmin();
    }

    public function getFilamentName(): string
    {
        return $this->full_name ?? 'User';
    }
}
