<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\LsankDeviceToken;

class LsankUser extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;

    protected $table = 'lsank_users';

    protected $primaryKey = 'user_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'ic_no',
        'name',
        'email',
        'phone',
        'password',
        'user_type',
        'status',
        'email_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Notification relationships
    |--------------------------------------------------------------------------
    */

    public function notifications(): HasMany
    {
        return $this->hasMany(
            LsankNotification::class,
            'user_id',
            'user_id'
        );
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(
LsankDeviceToken::class,
            'user_id',
            'user_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Notification routing
    |--------------------------------------------------------------------------
    */

    public function routeNotificationForMail(
        mixed $notification = null
    ): ?string {
        $email = trim((string) $this->email);

        return filter_var($email, FILTER_VALIDATE_EMAIL)
            ? $email
            : null;
    }

    /**
     * Return every active FCM token owned by the user.
     *
     * @return array<int, string>
     */
    public function routeNotificationForFirebase(
        mixed $notification = null
    ): array {
        return $this->deviceTokens()
            ->pluck('token')
            ->map(static fn ($token): string => trim((string) $token))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Return a Malaysian phone number in E.164 format for WhatsApp.
     */
    public function routeNotificationForWhatsApp(
        mixed $notification = null
    ): ?string {
        $digits = preg_replace('/\D+/', '', (string) $this->phone);

        if (! is_string($digits) || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '60'.substr($digits, 1);
        }

        if (! str_starts_with($digits, '60')) {
            return null;
        }

        return '+'.$digits;
    }

    /*
    |--------------------------------------------------------------------------
    | Role relationships
    |--------------------------------------------------------------------------
    */

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            LsankRole::class,
            'lsank_user_roles',
            'user_id',
            'role_id',
            'user_id',
            'role_id'
        )->withPivot([
            'user_role_id',
            'assigned_by',
            'assigned_at',
        ])->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereRaw('LOWER(TRIM(status)) = ?', ['active']);
    }

    public function scopeWithUserTypes(
        Builder $query,
        array $userTypes
    ): Builder {
        $normalizedTypes = collect($userTypes)
            ->map(static fn ($type): string => strtolower(trim((string) $type)))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedTypes->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $placeholders = $normalizedTypes
            ->map(static fn (): string => '?')
            ->implode(', ');

        return $query->whereRaw(
            "LOWER(TRIM(user_type)) IN ({$placeholders})",
            $normalizedTypes->all()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Role helpers
    |--------------------------------------------------------------------------
    */

    public function normalizedUserType(): string
    {
        return strtolower(trim((string) $this->user_type));
    }

    public function hasUserType(string $userType): bool
    {
        return $this->normalizedUserType()
            === strtolower(trim($userType));
    }

    public function hasAnyUserType(array $userTypes): bool
    {
        $normalizedUserTypes = collect($userTypes)
            ->map(static fn ($type): string => strtolower(trim((string) $type)))
            ->filter()
            ->unique()
            ->all();

        return in_array(
            $this->normalizedUserType(),
            $normalizedUserTypes,
            true
        );
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyUserType([
            'admin',
            'administrator',
        ]);
    }

    public function isApplicant(): bool
    {
        return $this->hasAnyUserType([
            'pengguna',
            'user',
            'applicant',
        ]);
    }

    public function isWaterBodyTeam(): bool
    {
        return $this->hasAnyUserType([
            'ketua_unit_badan_perairan',
            'ketua_bahagian_badan_perairan',
            'teknikal_badan_perairan',
        ]);
    }

    public function isEffluentTeam(): bool
    {
        return $this->hasAnyUserType([
            'ketua_unit_efluen',
            'ketua_bahagian_efluen',
            'teknikal_efluen',
        ]);
    }

    public function isEnforcementTeam(): bool
    {
        return $this->hasAnyUserType([
            'penguatkuasa',
            'penguatkuasa_perundangan',
        ]);
    }

    public function isLegalTeam(): bool
    {
        return $this->hasAnyUserType([
            'pegawai_undang_undang',
            'penolong_pegawai_undang_undang',
        ]);
    }

    public function isFinanceTeam(): bool
    {
        return $this->hasAnyUserType([
            'kewangan',
            'pegawai_kewangan',
        ]);
    }

    public function isDirector(): bool
    {
        return $this->hasAnyUserType([
            'ketua_pengarah',
            'pengarah',
        ]);
    }


}
