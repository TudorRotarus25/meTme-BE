<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as Req;

class UpcomingEvent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'metme:upcomingEvent';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function notifyUser($userId, $meetingId, $title, $message, $notificationType) {

        $queryResult = DB::table('users')->where('id', $userId)->select('gcm_token')->first();
        $gcmToken = $queryResult->gcm_token;

        $headers = [
            // 'Content-Type' => 'application/json',
            'Authorization'     => 'key=AIzaSyBhjcebuzIt7sE4_wUmKLDE0k4j1y4c6ic'
        ];

        $body = [
            'to' => $gcmToken,
            'data' => '{"notificationId": ' . 1234 . ', "type": ' . $notificationType . ', "title": "' . $title . '", "body": "' . $message . '", "meetingId": ' . $meetingId . '}'
        ];

        $client = new Client([
            'base_uri' => 'https://gcm-http.googleapis.com/',
            'timeout'  => 2.0
        ]);

        $response = $client->request('POST', 'gcm/send', ['form_params' => $body, 'headers' => $headers]);

        return json_encode($response->getBody());

    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $usersQuery = DB::select('select u.user_id, m.id, m.name, u.notify_time from meetings m join users_in_meetings u where notified IS NULL and TIMESTAMPDIFF(MINUTE, NOW(), from_time) - eta - notify_time BETWEEN -1 and 1');

        foreach ($usersQuery as $key => $value) {
            DB::table('users_in_meetings')->where('user_id', $value->user_id)->update(['notified' => 1]);

            $this->notifyUser($value->user_id, $value->id, $value->name . ' coming up', 'You should leave in ' . $value->notify_time . ' minutes', 2);
        }
    }
}
