<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourOperator extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUSPENDED = 'suspended';

    /**
     * Database table.
     */
    protected $table = 'tour_operators';

    /**
     * Fields that can be assigned.
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'contact_email',
        'contact_phone',
        'is_active',
        'approval_status',
        'rejection_reason',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Accounts belonging to this operator.
     *
     * Primarily intended for accounts with the Admin role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(
            User::class,
            'tour_operator_id'
        );
    }

    /**
     * Packages belonging to this operator.
     */
    public function packages(): HasMany
    {
        return $this->hasMany(
            Package::class,
            'tour_operator_id'
        );
    }

    /**
     * An operator can receive tourist bookings only with an assigned Admin.
     */
    public function hasAuthorizedAdmin(): bool
    {
        return $this->users()
            ->whereHas('role', function ($query): void {
                $query->where('name', 'Admin');
            })
            ->exists();
    }

    public function statusLabel(): string
    {
        return match ($this->approval_status) {
            self::STATUS_PENDING => 'Pending Approval',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_SUSPENDED => 'Suspended',
            default => 'Unassigned',
        };
    }

    /**
     * Pending and rejected businesses stay on My Business.
     * Active and suspended businesses can open their existing records.
     */
    public function canManageRecords(): bool
    {
        return in_array($this->approval_status, [
            self::STATUS_ACTIVE,
            self::STATUS_SUSPENDED,
        ], true);
    }

    /**
     * Only an approved business with an Operator Admin can take new bookings.
     */
    public function acceptsBookings(): bool
    {
        return $this->approval_status === self::STATUS_ACTIVE
            && $this->is_active
            && $this->hasAuthorizedAdmin();
    }
}
