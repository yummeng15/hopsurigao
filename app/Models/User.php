<?php

namespace App\Models;

use Database\Factories\UserFactory;
// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property int|null $role_id
 * @property int|null $tour_operator_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $contact_number
 * @property array<string, bool>|null $notification_preferences
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'email',
    'password',
    'role_id',
    'is_protected',
    'notification_preferences',
    'contact_number',
])]
#[Hidden([
    'password',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'remember_token',
])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory,
        Notifiable,
        PasskeyAuthenticatable,
        TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_protected' => 'boolean',
            'notification_preferences' => 'array',
        ];
    }

    /**
     * Get the user's assigned role.
     *
     * Roles:
     * - Superadmin
     * - Admin
     * - User
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the tour operator associated with this account.
     *
     * Admin:
     * Belongs to a specific tour operator.
     *
     * Superadmin:
     * Does not belong to an individual tour operator.
     *
     * User:
     * A tourist who can browse different operators.
     */
    public function tourOperator(): BelongsTo
    {
        return $this->belongsTo(
            TourOperator::class,
            'tour_operator_id'
        );
    }

    /**
     * Check whether the user has a permission
     * through their assigned role.
     */
    public function hasPermission(string $name): bool
    {
        return $this->role?->hasPermission($name) ?? false;
    }

    /**
     * Optional in-app alerts stay on unless this account turns that category off.
     */
    public function allowsOptionalNotification(string $category): bool
    {
        $preferences = $this->notification_preferences;

        if (! is_array($preferences) || ! array_key_exists($category, $preferences)) {
            return true;
        }

        return (bool) $preferences[$category];
    }
}
