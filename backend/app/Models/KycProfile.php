<?php

namespace App\Models;

use App\Models\States\Approved;
use App\Models\States\KycStatus;
use App\Models\States\PendingSubmission;
use App\Models\States\Rejected;
use App\Models\States\UnderReview;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\ModelStates\HasStates;

#[Fillable([
    'user_id', 'status', 'id_document_s3_key', 'id_document_back_s3_key',
    'selfie_s3_key', 'document_type', 'rejection_reason', 'reviewed_by', 'reviewed_at',
])]
class KycProfile extends Model
{
    use HasStates;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status'      => KycStatus::class,
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

    public function isApproved(): bool
    {
        return $this->status instanceof Approved;
    }

    public function isPendingSubmission(): bool
    {
        return $this->status instanceof PendingSubmission;
    }

    public function isUnderReview(): bool
    {
        return $this->status instanceof UnderReview;
    }

    public function isRejected(): bool
    {
        return $this->status instanceof Rejected;
    }
}
