<?php

namespace App\Console\Commands;

use App\Services\WeatherService;
use Illuminate\Console\Command;

class UpdateWeatherAdvisories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'weather:update {--days=14 : Number of upcoming days to refresh}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and persist external weather forecasts and calculate advisories for upcoming tour dates';

    /**
     * Execute the console command.
     */
    public function handle(WeatherService $weatherService): int
    {
        $days = (int) $this->option('days');
        $this->info("Scanning upcoming tour dates for the next {$days} day(s)..");

        $result = $weatherService->updateUpcomingTourDates($days);

        $this->info("Weather update complete: {$result['updated']} of {$result['total']} tour date(s) updated successfully.");

        if ($result['failed'] > 0) {
            $this->warn("{$result['failed']} tour date(s) could not be updated and are using fallback cached data.");
        }

        return Command::SUCCESS;
    }
}

