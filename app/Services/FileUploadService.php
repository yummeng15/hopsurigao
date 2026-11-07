<?php

namespace App\Services;

use App\Models\Package;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FileUploadService
{
    /**
     * Payment files are private.
     * Other application uploads remain public.
     */
    private function diskFor(string $path): string
    {
        $path = trim(
            str_replace('\\', '/', $path),
            '/'
        );

        if (
            $path === 'payments' ||
            str_starts_with($path, 'payments/')
        ) {
            return 'local';
        }

        return 'public';
    }

    /**
     * Store one uploaded file.
     */
    public function store(
        UploadedFile $file,
        string $directory
    ): string {
        $directory = trim($directory, '/');

        $storedPath = $file->store(
            $directory,
            $this->diskFor($directory)
        );

        if ($storedPath === false) {
            throw new RuntimeException(
                'Unable to store the uploaded file.'
            );
        }

        return $storedPath;
    }

    /**
     * Store multiple files.
     */
    public function storeMany(
        array $files,
        string $directory
    ): array {
        $paths = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $paths[] = $this->store(
                $file,
                $directory
            );
        }

        return $paths;
    }

    /**
     * Replace an uploaded file.
     */
    public function replace(
        ?string $oldPath,
        UploadedFile $file,
        string $directory
    ): string {
        if ($oldPath) {
            $this->delete($oldPath);
        }

        return $this->store(
            $file,
            $directory
        );
    }

    /**
     * Delete one file.
     */
    public function delete(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        return Storage::disk(
            $this->diskFor($path)
        )->delete($path);
    }

    /**
     * Delete multiple files.
     */
    public function deleteMany(array $paths): void
    {
        foreach ($paths as $path) {
            if (
                ! is_string($path) ||
                $path === ''
            ) {
                continue;
            }

            $this->delete($path);
        }
    }

    /**
     * Delete a directory.
     */
    public function deleteDirectory(
        string $directory
    ): void {
        $directory = trim($directory, '/');

        Storage::disk(
            $this->diskFor($directory)
        )->deleteDirectory($directory);
    }

    /**
     * Check whether a file exists.
     */
    public function exists(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        return Storage::disk(
            $this->diskFor($path)
        )->exists($path);
    }

    /**
     * Generate a file URL.
     *
     * Public files use /storage/...
     * Private payment proofs use an authorized Laravel route.
     */
    public function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $cleanPath = ltrim(
            str_replace('\\', '/', $path),
            '/'
        );

        if (
            str_starts_with($cleanPath, 'http://') ||
            str_starts_with($cleanPath, 'https://')
        ) {
            return $cleanPath;
        }

        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr(
                $cleanPath,
                strlen('storage/')
            );
        }

        /*
        |----------------------------------------------------------
        | Private Payment Proofs
        |----------------------------------------------------------
        */

        if ($this->diskFor($cleanPath) === 'local') {
            if (
                ! preg_match(
                    '~^payments/([1-9][0-9]*)/([^/]+)$~D',
                    $cleanPath,
                    $matches
                )
            ) {
                return null;
            }

            return route(
                'payment-proofs.show',
                [
                    'booking' => (int) $matches[1],
                    'filename' => $matches[2],
                ]
            );
        }

        /*
        |----------------------------------------------------------
        | Public Files
        |----------------------------------------------------------
        */

        return '/storage/'.$cleanPath;
    }

    /**
     * Get a file's physical path.
     */
    public function path(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk(
            $this->diskFor($path)
        )->path($path);
    }

    /**
     * List files inside a directory.
     */
    public function files(string $directory): array
    {
        $directory = trim($directory, '/');

        return Storage::disk(
            $this->diskFor($directory)
        )->files($directory);
    }

    /**
     * List image files with their URLs.
     *
     * @return list<array{name: string, path: string, url: string|null}>
     */
    public function images(string $directory): array
    {
        return collect(
            $this->files($directory)
        )
            ->filter(function (string $path): bool {
                $extension = strtolower(
                    pathinfo(
                        $path,
                        PATHINFO_EXTENSION
                    )
                );

                return in_array(
                    $extension,
                    [
                        'jpg',
                        'jpeg',
                        'png',
                        'webp',
                        'gif',
                    ],
                    true
                );
            })
            ->map(function (string $path): array {
                return [
                    'name' => basename($path),
                    'path' => $path,
                    'url' => $this->url($path),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Package gallery with the designated cover photo first.
     *
     * @return list<array{name: string, path: string, url: string|null}>
     */
    public function packageImages(Package $package): array
    {
        $images = $this->images(
            UploadPath::package((int) $package->id)
        );

        $coverPath = is_string($package->cover_photo)
            ? trim(str_replace('\\', '/', $package->cover_photo), '/')
            : '';

        if ($coverPath === '' || ! $this->exists($coverPath)) {
            return $images;
        }

        $cover = [
            'name' => basename($coverPath),
            'path' => $coverPath,
            'url' => $this->url($coverPath),
        ];

        $remainder = array_values(array_filter(
            $images,
            fn (array $image): bool => $image['path'] !== $coverPath
        ));

        return [$cover, ...$remainder];
    }

    /**
     * Store or replace a package cover photo and return its stored path.
     */
    public function storePackageCover(
        Package $package,
        UploadedFile $file,
        ?string $previousPath = null
    ): string {
        $directory = UploadPath::package((int) $package->id);

        if (
            is_string($previousPath)
            && $previousPath !== ''
            && $this->exists($previousPath)
        ) {
            $this->delete($previousPath);
        }

        return $this->store($file, $directory);
    }

    /**
     * List files with metadata.
     */
    public function filesWithMeta(
        string $directory
    ): array {
        return collect(
            $this->files($directory)
        )
            ->map(function (string $path): array {
                $extension = strtolower(
                    pathinfo(
                        $path,
                        PATHINFO_EXTENSION
                    )
                );

                return [
                    'name' => basename($path),

                    'path' => $path,

                    'url' => $this->url($path),

                    'extension' => $extension,

                    'is_image' => in_array(
                        $extension,
                        [
                            'jpg',
                            'jpeg',
                            'png',
                            'webp',
                            'gif',
                        ],
                        true
                    ),
                ];
            })
            ->values()
            ->all();
    }
}
