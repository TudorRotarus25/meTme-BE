<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as Req;

class HourlyLocationUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locationUpdate:hourly';

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

    public function notifyUser($userId, $meetingId) {

        $queryResult = DB::table('users')->where('id', $userId)->select('gcm_token')->first();
        $gcmToken = $queryResult->gcm_token;

        $headers = [
            'Authorization'     => 'key=AIzaSyBhjcebuzIt7sE4_wUmKLDE0k4j1y4c6ic'
        ];

        $body = [
            'to' => $gcmToken,
            'data' => '{"notificationId": ' . 1234 . ', "type": ' . 3 . ', "title": "' . 'No title' . '", "body": "' . 'No body' . '", "meetingId": ' . $meetingId . '}'
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
        $usersQuery = DB::select('select u.user_id, m.id from meetings m join users_in_meetings u where eta IS NULL or TIMESTAMPDIFF(MINUTE, NOW(), from_time) - eta BETWEEN 60 AND 1440');

        foreach ($usersQuery as $key => $value) {
            $this->notifyUser($value->user_id, $value->id);
        }
    }
}
