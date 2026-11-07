<?php

namespace App\Console\Commands;

use App\Models\TourOperator;
use Database\Seeders\DemoTourPackagesSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('demo:seed-tour-packages {--operator= : Approved tour operator ID (required; never auto-selected)} {--dry-run : Preview actions without writing} {--list-operators : List approved operators and exit}')]
#[Description('Idempotently seed 12 capstone demo tour packages for an explicit approved operator')]
class SeedDemoTourPackagesCommand extends Command
{
    public function handle(): int
    {
        if ($this->option('list-operators')) {
            return $this->listOperators();
        }

        $operatorId = (int) ($this->option('operator') ?: env('DEMO_TOUR_OPERATOR_ID', 0));
        $dryRun = (bool) $this->option('dry-run');

        if ($operatorId <= 0) {
            $this->error('Pass --operator=ID (or set DEMO_TOUR_OPERATOR_ID). Refusing to auto-select.');
            $this->line('Tip: php artisan demo:seed-tour-packages --list-operators');

            return self::FAILURE;
        }

        $this->warn('Backup your database before running without --dry-run.');
        $this->line($dryRun
            ? 'Dry-run only — no database writes.'
            : "Seeding demo packages for operator #{$operatorId}...");

        try {
            $seeder = new DemoTourPackagesSeeder($operatorId, $dryRun);
            $seeder->setCommand($this);
            $seeder->run();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Summary');
        $this->table(
            ['Package', 'Action', 'Status', 'Dates', 'Notes'],
            collect($seeder->report)->map(fn (array $row): array => [
                $row['package'],
                $row['action'],
                $row['status'],
                (string) $row['dates'],
                $row['reason'] ?? '',
            ])->all()
        );

        $published = collect($seeder->report)->where('status', 'Published')->count();
        $blocked = collect($seeder->report)->where('status', 'Blocked')->count();

        $this->line("Published: {$published} | Blocked/Draft issues: {$blocked}");

        if (! $dryRun) {
            $this->comment('Tourists see packages with status=active and future open schedules.');
            $this->comment('This command is NOT registered in DatabaseSeeder and was not run on Railway automatically.');
        }

        return self::SUCCESS;
    }

    private function listOperators(): int
    {
        $rows = TourOperator::query()
            ->orderBy('id')
            ->get(['id', 'name', 'approval_status', 'is_active'])
            ->map(fn (TourOperator $operator): array => [
                $operator->id,
                $operator->name,
                $operator->approval_status,
                $operator->is_active ? 'yes' : 'no',
                $operator->hasAuthorizedAdmin() ? 'yes' : 'no',
            ]);

        if ($rows->isEmpty()) {
            $this->warn('No tour operators found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Approval', 'Active', 'Has Admin'],
            $rows->all()
        );

        $this->line('Use an approved + active operator with an Admin, e.g.:');
        $this->line('  php artisan demo:seed-tour-packages --operator=ID --dry-run');

        return self::SUCCESS;
    }
}
