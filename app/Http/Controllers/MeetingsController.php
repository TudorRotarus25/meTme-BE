<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use DB;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as Req;

class MeetingsController extends Controller {

	public function testCron() {
		DB::table('notifications')->insert([
                'title' => 'From cron',
                'body' => 'This is a notification from cron',
                'user_id' => 3,
                'meeting_id' => 27,
                'type' => 2,
                'was_read' => 0
            ]);
		// DB::transaction(function() {

  //           $oldMeetings = DB::table('meetings')->select('id')->where('to_time', '<', 'DATE_ADD(CURDATE(), INTERVAL -1 DAY)')->get();

  //           DB::insert('insert into meetings_history (select * from meetings where to_time < DATE_ADD(CURDATE(), INTERVAL -1 DAY))');
  //           DB::table('meetings')->where('to_time', '<', 'DATE_ADD(CURDATE(), INTERVAL -1 DAY)')->delete();

  //           foreach ($oldMeetings as $value) {

  //               DB::insert('insert into users_in_meetings_history (select * from users_in_meetings where meeting_id = ?)', [$value->id]);
  //               // DB::table('users_in_meetings')->where('meeting_id', $value->id)->delete();
  //           }

  //       });
	}

	public function getAllMeetings($userId) {

		$meetings = DB::table('meetings')
				->join('users_in_meetings', 'meetings.id', '=', 'users_in_meetings.meeting_id')
				->where([['users_in_meetings.user_id', $userId], ['users_in_meetings.confirmed', 1]])
				->select('meetings.id', 'meetings.name', 'meetings.from_time as fromTime', 'meetings.to_time as toTime', 'meetings.place_name as placeName', 'users_in_meetings.transportation_method as transportationMethod', 'meetings.type')
				->orderBy('fromTime')
				->get();

		$response = array('meetings' => $meetings);
		return json_encode($response);

	}

	public function getNextMeeting($userId) {

		$meeting = DB::table('meetings')
				->join('users_in_meetings', 'meetings.id', '=', 'users_in_meetings.meeting_id')
				->where([['users_in_meetings.user_id', $userId], ['users_in_meetings.confirmed', 1]])
				->select('meetings.id', 'meetings.name', 'meetings.from_time as fromTime', 'meetings.to_time as toTime', 'meetings.place_name as placeName', 'users_in_meetings.transportation_method as transportationMethod', 'meetings.type', 'users_in_meetings.eta', 'meetings.author_id as authorId')
				->orderBy('fromTime')
				->first();

		if (is_null($meeting)) {
			return (new Response('No meetings set for this user', 204));
		}

		$participants = DB::table('users')
				->join('users_in_meetings', 'users.id', '=', 'users_in_meetings.user_id')
				->where([['users_in_meetings.meeting_id', $meeting->id], ['users_in_meetings.user_id', '!=', $userId], ['confirmed', 1]])
				->select('users.id', 'users.first_name', 'users.last_name', 'users.phone_number' , 'users_in_meetings.eta', 'users_in_meetings.confirmed')
				->get();	

		$formattedParticipants = array();

		foreach ($participants as $key => $value) {

			$newParticipant = array(
				'id' => $value->id,
				'name' => $value->first_name . ' ' . $value->last_name,
				'eta' => $value->eta,
				'initials' => strtoupper($value->first_name[0] . $value->last_name[0]),
				'phoneNumber' => $value->phone_number	
			 );

			array_push($formattedParticipants, $newParticipant);
			
		}

		$meeting->participants = $formattedParticipants;

		return json_encode($meeting);

	}

	//TODO: add user id as parameter, change notify_time and transportation_method
	public function getMeeting($userId, $meetingId) {

		$meetingDetails = DB::table('meetings')
				->join('users_in_meetings', 'meetings.id', '=', 'users_in_meetings.meeting_id')
				->where([['meetings.id', $meetingId], ['users_in_meetings.user_id', $userId]])
				->select('meetings.id', 'meetings.name', 'meetings.from_time as fromTime', 'meetings.to_time as toTime', 'users_in_meetings.notify_time as notifyTime', 'meetings.place_lat as placeLat', 'meetings.place_lon as placeLon', 'meetings.place_name as placeName', 'meetings.place_address as placeAddress', 'users_in_meetings.transportation_method as transportationMethod', 'meetings.author_id', 'meetings.type')
				->first();
		
		return json_encode($meetingDetails);

	}

	public function acceptMeeting(Request $request) {

		$userId = $request->input("user_id");
		$meetingId = $request->input("meeting_id");
		$transportationMethod = $request->input("transportation_method");
		$notifyTime = $request->input("notify_time");

		if (is_null($userId) || is_null($meetingId) || is_null($transportationMethod) || is_null($notifyTime)) {
			return (new Response('Required parameters are missing', 400));
		}

		$authorQuery = DB::table('users')->where('id', $userId)->first();
		$authorName = $authorQuery->first_name . ' ' . $authorQuery->last_name;

		DB::table('users_in_meetings')
			->where([['user_id', $userId], ['meeting_id', $meetingId]])
			->update([
				'transportation_method' => $transportationMethod,
				'notify_time' => $notifyTime,
				'confirmed' => 1
			]);

		$meetingsQuery = DB::table('meetings')->where('id', $meetingId)->first();

		$notificationTitle = $meetingsQuery->name . ' update';
		$notificationBody = $authorName . ' is attending your meeting';

		$this->notifyUser($meetingsQuery->author_id, $meetingId, $notificationTitle, $notificationBody, 4);

	}

	public function notifyUser($userId, $meetingId, $title, $message, $notificationType) {

		$queryResult = DB::table('users')->where('id', $userId)->select('gcm_token', 'first_name', 'last_name')->first();
		$gcmToken = $queryResult->gcm_token;

		$notificationId = DB::table('notifications')->insertGetId([
			'title' => $title,
			'body' => $message,
			'user_id' => $userId,
			'meeting_id' => $meetingId,
			'type' => $notificationType,
			'was_read' => 0
		]);

		$headers = [
        	// 'Content-Type' => 'application/json',
        	'Authorization'     => 'key=AIzaSyBhjcebuzIt7sE4_wUmKLDE0k4j1y4c6ic'
    	];

		$body = [
        	'to' => $gcmToken,
        	'data' => '{"notificationId": ' . $notificationId . ', "type": ' . $notificationType . ', "title": "' . $title . '", "body": "' . $message . '", "meetingId": ' . $meetingId . '}'
    	];

    	$client = new Client([
    		'base_uri' => 'https://gcm-http.googleapis.com/',
		    'timeout'  => 2.0
		]);

		$response = $client->request('POST', 'gcm/send', ['form_params' => $body, 'headers' => $headers]);

		return json_encode($response->getBody());

	}

	public function createMeeting(Request $request) {

		$name = $request->input('name'); 
		$fromTime = $request->input('from_time'); 
		$toTime = $request->input('to_time');
		$notifyTime = $request->input('notify_time'); 
		$placeLat = $request->input('place_lat'); 
		$placeLon = $request->input('place_lon'); 
		$placeName = $request->input('place_name'); 
		$placeAddress = $request->input('place_address'); 
		$transportationMethod = $request->input('transportation_method'); 
		$authorId = $request->input('author_id'); 
		$type = $request->input('type'); 
		$members = json_decode($request->input('members'));

		if (!$name || !$fromTime || !$toTime || is_null($notifyTime) || is_null($placeLat) || is_null($placeLon) || !$placeName || is_null($transportationMethod) || !$authorId || is_null($type)) {
			return (new Response('Required parameters are missing', 400));
		}

		$meetingId = DB::table('meetings')->insertGetId([
			'name' => $name,
			'from_time' => $fromTime,
			'to_time' => $toTime,
			'place_lat' => $placeLat,
			'place_lon' => $placeLon,
			'place_name' => $placeName,
			'place_address' => $placeAddress,
			'author_id' => $authorId,
			'type' => $type
		]);

		DB::table('users_in_meetings')->insert([
			'user_id' => $authorId,
			'meeting_id' => $meetingId,
			'notify_time' => $notifyTime,
			'transportation_method' => $transportationMethod,
			'confirmed' => true
		]);

		$authorQuery = DB::table('users')->where('id', $authorId)->first();
		$authorName = $authorQuery->first_name . ' ' . $authorQuery->last_name;

		if ($members) {

			foreach ($members as $key => $value) {

				$this->notifyUser($value, $meetingId, 'New meeting', $authorName . ' wants to add you to a meeting', 2);
					
				DB::table('users_in_meetings')->insert([
					'user_id' => $value,
					'meeting_id' => $meetingId
				]);

			}

		}

		return 'Success';

	}

	public function createPickup(Request $request) {

		$name = $request->input('name'); 
		$notifyTime = 0;
		$placeLat = $request->input('place_lat'); 
		$placeLon = $request->input('place_lon'); 
		$placeName = $request->input('place_name'); 
		$placeAddress = $request->input('place_address'); 
		$transportationMethod = $request->input('transportation_method'); 
		$authorId = $request->input('author_id'); 
		$type = $request->input('type'); 
		$members = json_decode($request->input('members'));
		$authorLat = $request->input('author_lat');
		$authorLon = $request->input('author_lon');

		if (!$name || is_null($placeLat) || is_null($placeLon) || !$placeName || is_null($transportationMethod) || !$authorId || is_null($type) || is_null($authorLat) || is_null($authorLon)) {
			return (new Response('Required parameters are missing', 400));
		}

		$transMet = 'driving';

		switch ($transportationMethod) {
			case 1:
				$transMet = 'transit';
				break;
			case 2:
				$transMet = 'walking';
				break;
		}

		$client = new Client([
    		'base_uri' => 'https://maps.googleapis.com',
		    'timeout'  => 2.0
		]);

		$response = $client->request('GET', '/maps/api/distancematrix/json', ['query' => [
				'origins' => $authorLat . ',' . $authorLon,
				'destinations' => $placeLat . ',' . $placeLon,
				'mode' => $transMet,
				'key' => 'AIzaSyBhjcebuzIt7sE4_wUmKLDE0k4j1y4c6ic'
			]]);

		$responseBody = json_decode($response->getBody());
		$eta = round($responseBody->rows[0]->elements[0]->duration->value / 60);

		$fromQuery = DB::select('select TIMESTAMPADD(MINUTE, ?, NOW()) frm from Dual', [$eta]);
		$toQuery = DB::select('select TIMESTAMPADD(MINUTE, ?, NOW()) t from Dual', [$eta + 60]);

		$meetingId = DB::table('meetings')->insertGetId([
			'name' => $name,
			'from_time' => $fromQuery[0]->frm,
			'to_time' => $toQuery[0]->t,
			'place_lat' => $placeLat,
			'place_lon' => $placeLon,
			'place_name' => $placeName,
			'place_address' => $placeAddress,
			'author_id' => $authorId,
			'type' => $type
		]);

		DB::table('users_in_meetings')->insert([
			'user_id' => $authorId,
			'meeting_id' => $meetingId,
			'notify_time' => $notifyTime,
			'transportation_method' => $transportationMethod,
			'confirmed' => true
		]);

		$authorQuery = DB::table('users')->where('id', $authorId)->first();
		$authorName = $authorQuery->first_name . ' ' . $authorQuery->last_name;

		if ($members) {

			foreach ($members as $key => $value) {

				$this->notifyUser($value, $meetingId, 'New meeting', $authorName . ' wants to add you to a meeting', 2);
					
				DB::table('users_in_meetings')->insert([
					'user_id' => $value,
					'meeting_id' => $meetingId
				]);

			}

		}

		return 'Success';

	}

	public function deleteMeeting(Request $request) {

		$userId = $request->input('user_id');
		$meetingId = $request->input('meeting_id');

		if (is_null($userId) || is_null($meetingId)) { 
			return (new Response('Required parameters are missing', 400));
		}

		$meetingAuthor = DB::table('meetings')->where('id', $meetingId)->select('author_id')->first();

		if ($userId != $meetingAuthor->author_id) {
			return (new Response('You are not the owner of the meeting', 403));
		}

		DB::table('meetings')->where('id', $meetingId)->delete();

		return 'Success';
	}

	public function cancelMeeting(Request $request) {

		$userId = $request->input('user_id');
		$meetingId = $request->input('meeting_id');

		if (is_null($userId) || is_null($meetingId)) { 
			return (new Response('Required parameters are missing', 400));
		}

		$authorQuery = DB::table('users')->where('id', $userId)->first();
		$authorName = $authorQuery->first_name . ' ' . $authorQuery->last_name;

		$meetingQuery = DB::table('meetings')->where('id', $meetingId)->first();
		$meetingName = $meetingQuery->name;

		DB::table('users_in_meetings')->where([['user_id', $userId], ['meeting_id', $meetingId]])->update([
			'confirmed' => 0
		]);

		$participantsQuery = DB::table('users_in_meetings')->where([['meeting_id', $meetingId], ['user_id', '!=', $userId], ['confirmed', 1]])->get();

		foreach ($participantsQuery as $key => $value) {
			$this->notifyUser($value->user_id, $meetingId, $meetingName . ' update', $authorName . ' canceled the meeting', 4);
		}

		return "Success";

	}

	public function finishMeeting(Request $request) {

		$userId = $request->input('user_id');
		$meetingId = $request->input('meeting_id');

		if (is_null($userId) || is_null($meetingId)) {
			return (new Response('Some required parameters are missing', 400));
		}

		$existingMeetingInHistory = DB::table('meetings_history')->where('id', $meetingId)->get();

		if (count($existingMeetingInHistory) == 0) {
			DB::insert('insert into meetings_history (select * from meetings where id = ' . $meetingId . ')');
		}
		

		DB::insert('insert into users_in_meetings_history (select * from users_in_meetings where user_id = ' . $userId . ' and meeting_id = ' . $meetingId . ')');
		DB::table('users_in_meetings')->where([['user_id', $userId], ['meeting_id', $meetingId]])->delete();

		$remainingParticipants = DB::table('users_in_meetings')->where('meeting_id', $meetingId)->get();

		if (count($remainingParticipants) == 0) {

			DB::table('meetings')->where('id', $meetingId)->delete();

		}

		return  "Success";

	}

	public function postponeMeeting(Request $request) {

		$userId = $request->input('user_id');
		$meetingId = $request->input('meeting_id');
		$time = $request->input('time');

		if(is_null($userId) || is_null($meetingId) || is_null($time)) {
			return (new Response('Some required parameters are missing', 400));
		}

		$authorQuery = DB::table('users')->where('id', $userId)->first();
		$authorName = $authorQuery->first_name . ' ' . $authorQuery->last_name;

		$affected = DB::update('update meetings set from_time = TIMESTAMPADD(MINUTE, ?, from_time) where id = ?', [$time, $meetingId]);

		$affectedUsersQuery = DB::table('users_in_meetings')->join('users', 'user_id', '=', 'users.id')->join('meetings', 'meeting_id', '=', 'meetings.id')->where([['meeting_id', $meetingId], ['confirmed', 1], ['user_id', '!=', $userId]])->get();

		foreach ($affectedUsersQuery as $key => $value) {
			$this->notifyUser($value->user_id, $meetingId, $value->name . ' update', $authorName . ' postponed with ' . $time . ' minutes', 4);
		}

		return "Success";

	}

	public function updateTransportationMethod(Request $request) {

		$userId = $request->input('user_id');
		$meetingId = $request->input('meeting_id');
		$transportationMethod = $request->input('transportation_method');

		if (is_null($meetingId) || is_null($transportationMethod)) {
			return (new Response('Required parameters are missing', 400));
		}

		DB::table('users_in_meetings')->where([['user_id', $userId], ['meeting_id', $meetingId]])->update(['transportation_method' => $transportationMethod]);

		return 'Success';

	}

}