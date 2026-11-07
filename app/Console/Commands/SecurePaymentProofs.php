<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SecurePaymentProofs extends Command
{
    protected $signature = 'payment-proofs:secure
                            {--apply : Secure existing proofs and remove their public copies}';

    protected $description = 'Move verified payment proof files from public storage to private storage.';

    public function handle(): int
    {
        $public = Storage::disk('public');

        $private = Storage::disk('local');

        $files = $public->allFiles('payments');

        $total = count($files);

        if ($total === 0) {
            $this->info(
                'No public payment proof files found.'
            );

            return self::SUCCESS;
        }

        /*
        |----------------------------------------------------------
        | Dry Run
        |----------------------------------------------------------
        */

        if (! $this->option('apply')) {
            $this->info(
                "Found {$total} public payment proof file(s)."
            );

            foreach ($files as $path) {
                $this->line(
                    "Would secure: {$path}"
                );
            }

            $this->warn(
                'Dry run only. No files were changed.'
            );

            $this->info(
                'Back up the payment files before running with --apply.'
            );

            return self::SUCCESS;
        }

        /*
        |----------------------------------------------------------
        | Secure Existing Files
        |----------------------------------------------------------
        */

        $secured = 0;

        $failed = 0;

        foreach ($files as $path) {
            /*
            | Only accept files directly inside
            | payments/{bookingId}/
            */

            if (
                ! preg_match(
                    '~^payments/[1-9][0-9]*/[^/]+$~D',
                    $path
                )
            ) {
                $this->warn(
                    "Skipped unexpected path: {$path}"
                );

                $failed++;

                continue;
            }

            try {
                /*
                | Copy to private storage if necessary.
                */

                if (! $private->exists($path)) {
                    $stream = $public->readStream($path);

                    if (! is_resource($stream)) {
                        throw new RuntimeException(
                            'Could not read the public file.'
                        );
                    }

                    try {
                        $copied = $private->put(
                            $path,
                            $stream
                        );

                        if (! $copied) {
                            throw new RuntimeException(
                                'Could not write the private file.'
                            );
                        }
                    } finally {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }
                }

                /*
                | Verify both files have the same size.
                */

                if (
                    $public->size($path) !==
                    $private->size($path)
                ) {
                    throw new RuntimeException(
                        'File size verification failed.'
                    );
                }

                /*
                | Verify file contents using SHA-256.
                */

                $publicHash = hash_file(
                    'sha256',
                    $public->path($path)
                );

                $privateHash = hash_file(
                    'sha256',
                    $private->path($path)
                );

                if (
                    ! $publicHash ||
                    ! $privateHash ||
                    ! hash_equals(
                        $publicHash,
                        $privateHash
                    )
                ) {
                    throw new RuntimeException(
                        'File checksum verification failed.'
                    );
                }

                /*
                | Remove the public copy only after
                | successful verification.
                */

                if (! $public->delete($path)) {
                    throw new RuntimeException(
                        'Could not remove the public copy.'
                    );
                }

                $secured++;

                $this->info(
                    "Secured: {$path}"
                );
            } catch (Throwable $exception) {
                $failed++;

                $this->error(
                    "Failed: {$path}"
                );

                $this->error(
                    $exception->getMessage()
                );
            }
        }

        /*
        |----------------------------------------------------------
        | Summary
        |----------------------------------------------------------
        */

        $this->newLine();

        $this->info(
            "Successfully secured: {$secured}"
        );

        if ($failed > 0) {
            $this->error(
                "Files requiring attention: {$failed}"
            );

            return self::FAILURE;
        }

        $this->info(
            'All discovered payment proofs were secured successfully.'
        );

        return self::SUCCESS;
    }
}