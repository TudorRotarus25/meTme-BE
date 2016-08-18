<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use DB;
// use GuzzleHttp\Client;

class UpdateMeetings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'archiveMeetings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archives old meetings';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        DB::transaction(function() {

            $oldMeetings = DB::select('select id from meetings where TIMESTAMPDIFF(MINUTE, NOW(), to_time) < -120');

            file_put_contents('/srv/users/serverpilot/apps/metme/public/api/v2/cron-log.txt', json_encode($oldMeetings));

            foreach ($oldMeetings as $value) {

                DB::insert('insert into users_in_meetings_history (select * from users_in_meetings where meeting_id = ?)', [$value->id]);
                // DB::table('users_in_meetings')->where('meeting_id', $value->id)->delete();
            }

            DB::insert('insert into meetings_history (select * from meetings where TIMESTAMPDIFF(MINUTE, NOW(), to_time) < -120)');
            DB::delete('delete from meetings where TIMESTAMPDIFF(MINUTE, NOW(), to_time) < -120');

        });
    }
}