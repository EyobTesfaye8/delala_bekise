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
    'type', 'status', 'guest_uuid', 'avatar_s3_key',
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
            'password' => 'hashed',
        ];
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
     * Average star rating for a Delala.
     */
    public function averageRating(): float
    {
        return (float) $this->ratingsReceived()->avg('stars');
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
