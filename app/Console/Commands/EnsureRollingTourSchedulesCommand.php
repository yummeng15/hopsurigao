<?php

namespace App\Console\Commands;

use App\Services\RollingTourScheduleService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tours:ensure-rolling-schedules {--days=365 : Rolling window length in days} {--package= : Limit to one package ID} {--operator= : Limit to one tour operator ID} {--dry-run : Preview without writing}')]
#[Description('Idempotently ensure daily schedules for eligible active packages across approved operators')]
class EnsureRollingTourSchedulesCommand extends Command
{
    public function handle(RollingTourScheduleService $schedules): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $days = (int) $this->option('days');
        $packageId = $this->option('package') !== null && $this->option('package') !== ''
            ? (int) $this->option('package')
            : null;
        $operatorId = $this->option('operator') !== null && $this->option('operator') !== ''
            ? (int) $this->option('operator')
            : null;

        $this->line($dryRun
            ? 'Dry-run only — no database writes.'
            : 'Writing missing daily schedules (idempotent).');

        $report = $schedules->ensure(
            days: $days,
            dryRun: $dryRun,
            packageId: $packageId,
            operatorId: $operatorId,
        );

        $this->table(
            [
                'ID',
                'Package',
                'Op',
                'Cap',
                'Have',
                'Create',
                'Status',
                'Notes',
            ],
            collect($report)->map(fn (array $row): array => [
                (string) $row['package_id'],
                (string) $row['package_name'],
                (string) ($row['operator_id'] ?? ''),
                (string) ($row['capacity'] ?? '—'),
                (string) $row['existing_complete'],
                (string) $row['create_dates'],
                (string) $row['status'],
                trim(($row['capacity_flag'] ? '[FLAG] '.$row['capacity_flag'].' ' : '').($row['notes'] ?? '')),
            ])->all()
        );

        $totals = $schedules->totals($report);

        $this->newLine();
        $this->info(sprintf(
            'Packages: %d | Would/will create dates: %d | times: %d | pickups: %d | capacity flags: %d',
            $totals['packages'],
            $totals['create_dates'],
            $totals['create_times'],
            $totals['create_pickups'],
            $totals['flagged'],
        ));

        if ($dryRun) {
            $this->comment('Re-run without --dry-run after backup approval to apply inserts.');
        }

        return self::SUCCESS;
    }
}
