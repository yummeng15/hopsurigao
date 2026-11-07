<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class PackageDestination extends Model
{
    protected $fillable = [
        'package_id',
        'name',
        'position',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Names staff can pick, including the separate places inside a saved label.
     *
     * @return list<string>
     */
    public static function pickerOptions(): array
    {
        /** @var Collection<int, string> $names */
        $names = static::query()
            ->orderBy('name')
            ->pluck('name')
            ->flatMap(fn (string $name): array => [$name, ...static::parts($name)]);

        return $names
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $names
     * @return list<string>
     */
    public static function normalizeNames(array $names): array
    {
        $unique = [];

        foreach ($names as $name) {
            $name = trim((string) $name);

            if ($name === '') {
                continue;
            }

            $key = mb_strtolower($name);

            if (isset($unique[$key])) {
                continue;
            }

            $unique[$key] = $name;
        }

        return array_values($unique);
    }

    /**
     * @return list<string>
     */
    public static function parts(string $name): array
    {
        $parts = preg_split('/\s*(?:,|&)\s*/', $name) ?: [];

        return array_values(array_filter(array_map(
            fn (string $part): string => trim($part),
            $parts,
        )));
    }
}
