<?php

namespace App\Console\Commands;

use App\Models\Package;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('demo:assign-licensed-covers {--operator=1 : Tour operator ID that owns the 12 demo packages} {--force : Replace existing cover_photo values} {--dry-run : Preview only}')]
#[Description('Assign verified licensed destination covers to existing demo packages (cover_photo only)')]
class AssignLicensedDemoCoversCommand extends Command
{
    /**
     * @return array<string, array{file: string, attribution: string}>
     */
    private function assignments(): array
    {
        return [
            'Siargao Four-Island Adventure' => [
                'file' => '01_siargao_four_island.jpg',
                'attribution' => 'Photo: Michael Angelo Luna, Wikimedia Commons, CC BY-SA 4.0',
            ],
            'Corregidor Island Nature Retreat' => [
                'file' => '03_corregidor.jpg',
                'attribution' => 'Photo: Michael Angelo Luna, Wikimedia Commons, CC BY-SA 4.0',
            ],
            'Kawhagan and Pamomoan Beach Discovery' => [
                'file' => '04_kawhagan.jpg',
                'attribution' => 'Photo: Alejandro Luengo / Unsplash',
            ],
            'Daku Island Tropical Picnic' => [
                'file' => '06_daku.jpg',
                'attribution' => 'Photo: ChaasPrime, Wikimedia Commons, CC BY-SA 4.0',
            ],
            'Guyam and Naked Island Getaway' => [
                'file' => '07_naked.jpg',
                'attribution' => 'Photo: Lucky Ambago Purok Otso, Wikimedia Commons, CC BY-SA 4.0',
            ],
            'Sohoton Cave and Lagoon Expedition' => [
                'file' => '12_sohoton.jpg',
                'attribution' => 'Photo: WayPH.com, CC BY 2.0 — wayph.com',
            ],
        ];
    }

    public function handle(): int
    {
        $operatorId = (int) $this->option('operator');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        if ($operatorId <= 0) {
            $this->error('Pass a valid --operator=ID.');

            return self::FAILURE;
        }

        $rows = [];
        $disk = Storage::disk('public');

        foreach ($this->assignments() as $packageName => $meta) {
            $package = Package::query()
                ->where('tour_operator_id', $operatorId)
                ->where('package_name', $packageName)
                ->first();

            if (! $package) {
                $rows[] = [$packageName, 'missing', 'Package not found for operator', ''];

                continue;
            }

            $source = resource_path('demo-covers/'.$meta['file']);

            if (! is_file($source) || filesize($source) < 1000) {
                $rows[] = [$packageName, 'error', 'Source image missing: '.$meta['file'], ''];

                continue;
            }

            $hasCover = is_string($package->cover_photo) && trim($package->cover_photo) !== '';

            if ($hasCover && ! $force) {
                $rows[] = [$packageName, 'skipped', 'Already has cover_photo (use --force)', (string) $package->cover_photo];

                continue;
            }

            $relative = 'packages/'.$package->id.'/cover_licensed_'.basename($meta['file']);

            if ($dryRun) {
                $rows[] = [$packageName, 'would-assign', $meta['attribution'], $relative];

                continue;
            }

            try {
                if ($hasCover && is_string($package->cover_photo) && $disk->exists($package->cover_photo)) {
                    $disk->delete($package->cover_photo);
                }

                $disk->put($relative, file_get_contents($source));
                $package->forceFill(['cover_photo' => $relative])->save();

                $rows[] = [$packageName, 'assigned', $meta['attribution'], $relative];
            } catch (Throwable $e) {
                $rows[] = [$packageName, 'error', $e->getMessage(), ''];
            }
        }

        $this->table(['Package', 'Action', 'Notes', 'Path'], $rows);

        $assigned = collect($rows)->where('1', 'assigned')->count();
        $this->info($dryRun
            ? 'Dry-run complete — no files or DB writes.'
            : "Assigned {$assigned} licensed cover(s). Placeholders remain for packages without verified photos.");

        return self::SUCCESS;
    }
}
