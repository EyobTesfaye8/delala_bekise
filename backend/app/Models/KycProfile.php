<?php

namespace App\Models;

use App\States\Kyc\KycState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\ModelStates\HasStates;

#[Fillable([
    'user_id',
    'status',
    'id_document_s3_key',
    'id_document_back_s3_key',
    'selfie_s3_key',
    'document_type',
    'rejection_reason',
    'reviewed_by',
    'reviewed_at',
])]
class KycProfile extends Model
{
    use HasStates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => KycState::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
