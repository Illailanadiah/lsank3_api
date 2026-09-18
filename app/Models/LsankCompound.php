<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LsankCompound extends Model
{
    use HasFactory;

    protected $table = 'lsank_compounds';

    protected $primaryKey = 'compound_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_ESCALATED = 'escalated';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'compound_no',

        'inspection_report_id',
        'notice_id',
        'invoice_id',
        'legal_referral_id',

        'license_id',
        'application_id',
        'user_id',

        'file_no',
        'license_no',
        'party_name',
        'register_no',
        'compound_type',
        'district',
        'location',
        'section_regulation',

        'offence_type_id',
        'compound_date',
        'amount',
        'compound_status_id',
        'description',

        'workflow_status',

        'issued_at',
        'due_at',
        'paid_at',
        'overdue_at',
        'escalated_at',

        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'compound_id' => 'integer',

        'inspection_report_id' => 'integer',
        'notice_id' => 'integer',
        'invoice_id' => 'integer',
        'legal_referral_id' => 'integer',

        'license_id' => 'integer',
        'application_id' => 'integer',
        'user_id' => 'integer',

        'offence_type_id' => 'integer',
        'compound_status_id' => 'integer',

        'compound_date' => 'date',

        'amount' => 'decimal:2',

        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'overdue_at' => 'datetime',
        'escalated_at' => 'datetime',

        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function license(): BelongsTo
    {
        return $this->belongsTo(
            LsankLicense::class,
            'license_id',
            'license_id'
        );
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(
            LsankApplication::class,
            'application_id',
            'application_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            LsankUser::class,
            'user_id',
            'user_id'
        );
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(
            LsankInvoice::class,
            'invoice_id',
            'invoice_id'
        );
    }

    public function notice(): BelongsTo
    {
        return $this->belongsTo(
            LsankNotice::class,
            'notice_id',
            'notice_id'
        );
    }

    public function legalReferral(): BelongsTo
    {
        return $this->belongsTo(
            LsankLegalReferral::class,
            'legal_referral_id',
            'legal_referral_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            LsankUser::class,
            'created_by',
            'user_id'
        );
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            LsankUser::class,
            'updated_by',
            'user_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS HELPERS
    |--------------------------------------------------------------------------
    */

    public function isDraft(): bool
    {
        return $this->workflow_status === self::STATUS_DRAFT;
    }

    public function isIssued(): bool
    {
        return $this->workflow_status === self::STATUS_ISSUED;
    }

    public function isPaid(): bool
    {
        return $this->workflow_status === self::STATUS_PAID
            || $this->paid_at !== null;
    }

    public function isOverdue(): bool
    {
        if ($this->isPaid()) {
            return false;
        }

        if (
            $this->workflow_status === self::STATUS_OVERDUE
            || $this->workflow_status === self::STATUS_ESCALATED
        ) {
            return true;
        }

        if ($this->due_at === null) {
            return false;
        }

        return $this->due_at->isPast();
    }

    public function isEscalated(): bool
    {
        return $this->workflow_status === self::STATUS_ESCALATED
            || $this->legal_referral_id !== null
            || $this->escalated_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->workflow_status === self::STATUS_CANCELLED;
    }

    public function canBePaid(): bool
    {
        return ! $this->isPaid()
            && ! $this->isCancelled();
    }

    public function canBeMarkedOverdue(): bool
    {
        if ($this->isPaid()) {
            return false;
        }

        if ($this->isCancelled()) {
            return false;
        }

        if ($this->isEscalated()) {
            return false;
        }

        if ($this->due_at === null) {
            return false;
        }

        return $this->due_at->isPast();
    }

    public function canBeEscalated(): bool
    {
        if ($this->isPaid()) {
            return false;
        }

        if ($this->isCancelled()) {
            return false;
        }

        if ($this->isEscalated()) {
            return false;
        }

        if ($this->legal_referral_id !== null) {
            return false;
        }

        if ($this->due_at === null) {
            return false;
        }

        return $this->due_at->isPast();
    }

    /*
    |--------------------------------------------------------------------------
    | DISPLAY HELPERS
    |--------------------------------------------------------------------------
    */

    public function getStatusLabelAttribute(): string
    {
        return match ($this->workflow_status) {
            self::STATUS_DRAFT => 'Draf',
            self::STATUS_ISSUED => 'Belum Bayar',
            self::STATUS_PAID => 'Telah Bayar',
            self::STATUS_OVERDUE => 'Tamat 14 Hari',
            self::STATUS_ESCALATED => 'Naik Kes Mahkamah',
            self::STATUS_CANCELLED => 'Dibatalkan',
            default => 'Tidak Diketahui',
        };
    }

    public function getAmountDisplayAttribute(): string
    {
        return 'RM ' . number_format(
            (float) $this->amount,
            2,
            '.',
            ','
        );
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if ($this->due_at === null) {
            return null;
        }

        if ($this->isPaid()) {
            return 0;
        }

        $now = now();

        if ($this->due_at->lessThanOrEqualTo($now)) {
            return 0;
        }

        return (int) ceil(
            $now->diffInHours(
                $this->due_at
            ) / 24
        );
    }
}