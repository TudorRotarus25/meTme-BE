<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use DB;
use GuzzleHttp\Client;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'testCommand';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'test cron';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        file_put_contents('/srv/users/serverpilot/apps/metme/public/api/v2/cron-log.txt', "CRON TEST\n");
        // $oldMeetings = DB::table('meetings')->where
    }
}