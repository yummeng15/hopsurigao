<?php

namespace App\Models;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    /**
     * Fields that can be mass assigned.
     *
     * tour_operator_id is deliberately excluded.
     * Operator ownership must be controlled
     * by the backend.
     */
    protected $fillable = [
        'package_name',
        'boat_name',
        'destination',
        'latitude',
        'longitude',
        'description',
        'cover_photo',
        'price',
        'status',
        'under_maintenance',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'under_maintenance' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * Register model-level tenant protection.
     */
    protected static function booted(): void
    {
        /*
        |--------------------------------------------------------------------------
        | GLOBAL OPERATOR SCOPE
        |--------------------------------------------------------------------------
        |
        | Superadmin:
        | Can retrieve packages across all operators.
        |
        | Tourist:
        | Not restricted to one operator.
        |
        | Admin:
        | Can retrieve only packages owned by
        | their assigned active operator.
        |
        */

        static::addGlobalScope(
            'tour_operator',
            function (Builder $builder): void {

                $user = auth()->user();

                /*
                 * This restriction applies only
                 * to Tour Operator Admins.
                 */
                if (
                    $user?->role?->name !== 'Admin'
                ) {
                    return;
                }

                $operator = $user->tourOperator;

                /*
                 * Fail closed if the Admin does
                 * not have an active operator.
                 */
                if (
                    ! $operator ||
                    ! $operator->is_active
                ) {
                    $builder->whereRaw('1 = 0');

                    return;
                }

                /*
                 * Restrict package queries to
                 * the Admin's tour operator.
                 */
                $builder->where(
                    $builder->getModel()
                        ->qualifyColumn('tour_operator_id'),
                    $operator->id
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | AUTOMATIC OWNERSHIP ON SAVE
        |--------------------------------------------------------------------------
        |
        | If an Admin creates a package, assign
        | that package to their operator.
        |
        | If an Admin updates an existing package,
        | ensure it belongs to their operator.
        |
        */

        static::saving(
            function (Package $package): void {

                $user = auth()->user();

                if (
                    $user?->role?->name !== 'Admin'
                ) {
                    return;
                }

                $operator = $user->tourOperator;

                if (
                    ! $operator ||
                    ! $operator->is_active
                ) {
                    throw new AuthorizationException(
                        'Your tour operator is not active '
                        .'or has not been assigned.'
                        .'or has not been assigned.'
                    );
                }

                /*
                 * Protect existing packages
                 * from unauthorized modifications.
                 */
                if ($package->exists) {

                    $originalOperatorId = $package
                        ->getOriginal('tour_operator_id');

                    if (
                        (int) $originalOperatorId !==
                        (int) $operator->id
                    ) {
                        throw new AuthorizationException(
                            'You cannot modify a package '
                            .'owned by another operator.'
                        );
                    }
                }

                /*
                 * Force correct ownership for
                 * Admin-created packages.
                 */
                $package->tour_operator_id = $operator->id;
            }
        );

        /*
        |--------------------------------------------------------------------------
        | DELETE PROTECTION
        |--------------------------------------------------------------------------
        */

        static::deleting(
            function (Package $package): void {

                $user = auth()->user();

                if (
                    $user?->role?->name !== 'Admin'
                ) {
                    return;
                }

                $operator = $user->tourOperator;

                if (
                    ! $operator ||
                    ! $operator->is_active
                ) {
                    throw new AuthorizationException(
                        'Your tour operator is not active '
                        .'or has not been assigned.'
                    );
                }

                if (
                    (int) $package->tour_operator_id !==
                    (int) $operator->id
                ) {
                    throw new AuthorizationException(
                        'You cannot delete a package '
                        .'owned by another operator.'
                    );
                }
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TOUR OPERATOR RELATIONSHIP
    |--------------------------------------------------------------------------
    */

    public function tourOperator(): BelongsTo
    {
        return $this->belongsTo(
            TourOperator::class,
            'tour_operator_id'
        );
    }

    /**
     * Operator business and Admin shown on package details.
     *
     * @return array{operator_name: string|null, admin_name: string|null, assignment_status: string}
     */
    public function assignmentSummary(): array
    {
        $this->loadMissing('tourOperator.users.role');

        $operator = $this->tourOperator;
        $adminNames = $operator?->users
            ->filter(fn (User $user): bool => $user->role?->name === 'Admin')
            ->pluck('name')
            ->filter()
            ->values();

        return [
            'operator_name' => $operator?->name,
            'admin_name' => $adminNames !== null && $adminNames->isNotEmpty()
                ? $adminNames->join(', ')
                : null,
            'assignment_status' => $operator === null ? 'Unassigned' : 'Assigned',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | PACKAGE DESTINATIONS
    |--------------------------------------------------------------------------
    */

    public function destinations(): HasMany
    {
        return $this->hasMany(
            PackageDestination::class
        )->orderBy('position');
    }

    /*
    |--------------------------------------------------------------------------
    | INCLUSIONS
    |--------------------------------------------------------------------------
    */

    public function inclusions(): HasMany
    {
        return $this->hasMany(
            Inclusion::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVITIES
    |--------------------------------------------------------------------------
    */

    public function activities(): HasMany
    {
        return $this->hasMany(
            Activity::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TOUR DATES
    |--------------------------------------------------------------------------
    */

    public function tourDates(): HasMany
    {
        return $this->hasMany(
            TourDate::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | REMINDERS
    |--------------------------------------------------------------------------
    */

    public function reminders(): HasMany
    {
        return $this->hasMany(
            Reminder::class
        );
    }

    /**
     * Ordered stops for the guest,
     * plus how many of those stops are islands.
     *
     * @return array{
     *     stops: list<string>,
     *     island_count: int
     * }
     */
    public function routeSummary(): array
    {
        $this->loadMissing('destinations');

        $stops = [];

        if ($this->destinations->isNotEmpty()) {

            foreach ($this->destinations as $destination) {

                array_push(
                    $stops,
                    ...$this->stopNames(
                        $destination->name
                    )
                );
            }

        } elseif (filled($this->destination)) {

            $stops = $this->stopNames(
                $this->destination
            );
        }

        $islandCount = count(array_filter(
            $stops,
            fn (string $stop): bool => str_contains(
                mb_strtolower($stop),
                'island'
            ),
        ));

        return [
            'stops' => $stops,
            'island_count' => $islandCount,
        ];
    }

    /**
     * Convert destination names into individual stops.
     *
     * @return list<string>
     */
    private function stopNames(string $name): array
    {
        $parts = PackageDestination::parts($name);

        if (count($parts) > 1) {
            return $parts;
        }

        $name = trim($name);

        return $name === '' ? [] : [$name];
    }
}
