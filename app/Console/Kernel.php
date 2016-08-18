<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use DB;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\Inspire::class,
        Commands\UpdateMeetings::class,
        Commands\InProgressLocationUpdate::class,
        Commands\PendingLocationUpdate::class,
        Commands\HourlyLocationUpdate::class,
        Commands\DailyLocationUpdate::class,
        Commands\UpcomingEvent::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //          ->hourly();


        $schedule->command('archiveMeetings')->hourly();

        $schedule->command('locationUpdate:daily')->daily();
        $schedule->command('locationUpdate:hourly')->hourly();
        $schedule->command('locationUpdate:pending')->everyFiveMinutes();
        $schedule->command('locationUpdate:inProgress')->everyMinute();

        $schedule->command('metme:UpcomingEvent')->everyMinute();
    }
}
