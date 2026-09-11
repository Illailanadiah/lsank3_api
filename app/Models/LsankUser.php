<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

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

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    /*
    |--------------------------------------------------------------------------
    | Notification Routing
    |--------------------------------------------------------------------------
    */

    public function routeNotificationForMail(
        mixed $notification = null
    ): ?string {
        $email = trim((string) $this->email);

        return $email !== '' ? $email : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Role Relationships
    |--------------------------------------------------------------------------
    |
    | The existing system currently identifies a user's operational role
    | through lsank_users.user_type.
    |
    | The roles() relationship is provided for lsank_user_roles when the
    | normalized role tables are used later.
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
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeWithUserTypes(
        Builder $query,
        array $userTypes
    ): Builder {
        $normalizedTypes = collect($userTypes)
            ->filter()
            ->map(
                fn ($type): string =>
                    strtolower(trim((string) $type))
            )
            ->unique()
            ->values()
            ->all();

        return $query->whereIn('user_type', $normalizedTypes);
    }

    /*
    |--------------------------------------------------------------------------
    | Role Helpers
    |--------------------------------------------------------------------------
    */

    public function normalizedUserType(): string
    {
        return strtolower(
            trim((string) $this->user_type)
        );
    }

    public function hasUserType(string $userType): bool
    {
        return $this->normalizedUserType()
            === strtolower(trim($userType));
    }

    public function hasAnyUserType(array $userTypes): bool
    {
        $normalizedUserTypes = array_map(
            static fn ($type): string =>
                strtolower(trim((string) $type)),
            $userTypes
        );

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

    public function isDirector(): bool
    {
        return $this->hasUserType('ketua_pengarah');
    }
}