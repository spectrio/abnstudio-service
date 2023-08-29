<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
	    $schedule->exec('curl http://127.0.0.1/WeVidAPI/modified/refresh')->everyMinute();
	    $schedule->exec('curl http://127.0.0.1/WeVidAPI/accounts/cache')->daily();
        $schedule->exec('curl http://127.0.0.1/WeVidAPI/refresh')->everyMinute();
    }
}
