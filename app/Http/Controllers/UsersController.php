<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use DB;
use GuzzleHttp\Client;
use Twilio;

class UsersController extends Controller {

	private static function generateApiKey() {
        return md5(uniqid(rand(), true));
    }

    private static function generateRefreshToken() {
    	return md5(uniqid(rand(), true)) . md5(uniqid(rand(), true));
    }

    private static function generateSmsCode() {
        return mt_rand(100000,999999);
    }

	public function createUser(Request $request) {
		
		$firstName = $request->input('first_name');   	
		$lastName = $request->input('last_name');      
		$phoneNumber = $request->input('phone_number');	

		if (!$firstName || !$lastName || !$phoneNumber) {
			App::abort(400, 'Required parameters are missinng');
		}

		$smsCode = $this->generateSmsCode();

		$oldUser = DB::table('users')->where('phone_number', $phoneNumber)->get();
		if (count($oldUser) > 0) {
			//user already existed
			$oldUserId = $oldUser[0]->id;
			
			DB::transaction(function() use($firstName, $lastName, $oldUserId, $smsCode) {

				

				DB::table('users')->where('id', $oldUserId)->update([
					'first_name' => $firstName,
					'last_name' => $lastName,
					'status' => 0
				]);

				DB::table('tokens')->where('user_id', $oldUserId)->delete();

				DB::table('tokens')->insert([
					'user_id' => $oldUserId,
					'token' => $this->generateApiKey(),
					'refresh_token' => $this->generateRefreshToken(),
					'expiration_date' => 'DATE_ADD(NOW(), INTERVAL 1 HOUR)'
				]);	

				DB::table('sms_codes')->where('user_id', $oldUserId)->delete();

				DB::table('sms_codes')->insert([
					'user_id' => $oldUserId,
					'code' => $smsCode,
					'status' => 0
				]);

			});

			$result = array('id' => $oldUserId, 'firstName' => $firstName, 'lastName' => $lastName, 'phoneNumber' => $phoneNumber);
			$this->sendSms($phoneNumber, $smsCode);

			return json_encode($result);


		} else {
			//new user
			DB::transaction(function() use($firstName, $lastName, $phoneNumber, &$newUserId, $smsCode) {

				$newUserId = DB::table('users')->insertGetId([
					'phone_number' => $phoneNumber,
					'first_name' => $firstName,
					'last_name' => $lastName,
					'status' => 0
				]);

				DB::table('sms_codes')->where('user_id', $newUserId)->delete();

				DB::table('sms_codes')->insert([
					'user_id' => $newUserId,
					'code' => $smsCode,
					'status' => 0
				]);

				DB::table('tokens')->insert([
					'user_id' => $newUserId,
					'token' => $this->generateApiKey(),
					'refresh_token' => $this->generateRefreshToken(),
					'expiration_date' => 'DATE_ADD(NOW(), INTERVAL 1 HOUR)'
				]);	

			});

			$result = array('id' => $newUserId, 'firstName' => $firstName, 'lastName' => $lastName, 'phoneNumber' => $phoneNumber);
			$this->sendSms($phoneNumber, $smsCode);

			return json_encode($result);
		}

	}

	public function editUser(Request $request) {

		$userId = $request->input('user_id');
		$firstName = $request->input('first_name');   	
		$lastName = $request->input('last_name'); 

		if (is_null($userId) || is_null($firstName) || is_null($lastName)) {
			return (new Response('Some required parameters are missing', 400));
		}

		DB::table('users')->where('id', $userId)->update([
			'first_name' => $firstName,
			'last_name' => $lastName
		]);

		return "Success";

	}

	public function getProfile($userId) {

		$user = DB::table('users')->where('id', $userId)->select('id', 'first_name as firstName', 'last_name as lastName', 'phone_number as phoneNumber', 'email', 'status')->first();
		return json_encode($user);

	}

	public function activateUser(Request $request) {

		$userId = $request->input('user_id');  		//Input::get('user_id');
		$smsCode = $request->input('sms_code');  	//Input::get('sms_code');

		if(!$userId || !$smsCode) {
			App::abort(400, "Some required parameters are missing");
		}

		$sms = DB::table('sms_codes')->where('user_id', $userId)->select('code')->first();

		if($smsCode ==  $sms->code) {

			DB::transaction(function() use($userId) {

				DB::table('sms_codes')->where('user_id', $userId)->update(['status' => 1]);
				DB::table('users')->where('id', $userId)->update(['status' => 1]);

			});

		} else {
			App::abort(403, "Wrong sms code");
		}

		$updatedUser = DB::table('users')
				->leftJoin('tokens', 'users.id', '=', 'tokens.user_id')
				->where('users.id', $userId)
				->select('users.id', 'users.first_name as firstName', 'users.last_name as lastName', 'users.phone_number as phoneNumber', 'users.status', 'tokens.token as token', 'tokens.refresh_token as refreshToken')
				->first();

		return json_encode($updatedUser);
	}

	private function isFriendInArray($haystack, $wantedFriend) {

		foreach ($haystack as $friend) {
			if ($friend['id'] == $wantedFriend['id']) {
				return true;
			}
		}

		return false;

	}

	public function getFriends(Request $request) {

		$contacts = $request->input('contacts');
		$friends = array();

		foreach ($contacts as $contact) {
			foreach ($contact['phone_numbers'] as $phoneNumber) {

				$friend = DB::table('users')->where('phone_number', 'like' , '%' . $phoneNumber)
						->select('id', 'first_name', 'last_name', 'phone_number')->first();

				if($friend) {
					$formattedFriend = array('id' => $friend->id, 'name' => $contact['name'], 'phoneNumber' => $friend->phone_number, 'initials' => strtoupper($friend->first_name[0] . $friend->last_name[0]));
					if(!$this->isFriendInArray($friends, $formattedFriend)) {
						array_push($friends, $formattedFriend);
					}
				}
			}
		}

		$result = array('friends' => $friends);

		return json_encode($result);

	}

	public function getNotifications($userId) {

		if(is_null($userId)) {
			return (new Response('Null user id', 400));
		}

		$usersQuery = DB::table('notifications')->where('user_id', $userId)->select('id', 'title', 'body', 'meeting_id as meetingId', 'type')->get();

		return json_encode($usersQuery);

	}

	public function refreshLocation(Request $request) {

		$userId = $request->input('user_id');
		$meetingId = $request->input('meeting_id');
		$lat = $request->input('lat');
		$lon = $request->input('lon');

		if (is_null($userId) || is_null($meetingId) || is_null($lat) || is_null($lon)) {
			return (new Response('Required parameters are missing', 400));
		}

		$placeQuery = DB::select('select place_lat, place_lon, UNIX_TIMESTAMP(from_time) frm, transportation_method from meetings m join users_in_meetings um on(m.id = um.meeting_id) where m.id = ? and um.user_id = ?', [$meetingId, $userId]);
		// $placeQuery = DB::table('meetings')->join('users_in_meetings', 'id', '=', 'meeting_id')->where([['id', $meetingId], ['user_id', $userId]])->select('place_lat', 'place_lon', 'from_time', 'transportation_method')->first();
		$meetingLat = $placeQuery[0]->place_lat;
		$meetingLon = $placeQuery[0]->place_lon;

		$transMet = 'driving';

		switch ($placeQuery[0]->transportation_method) {
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
				'origins' => $lat . ',' . $lon,
				'destinations' => $meetingLat . ',' . $meetingLon,
				'mode' => $transMet,
				'arival_time' => $placeQuery[0]->frm,
				'key' => 'AIzaSyBhjcebuzIt7sE4_wUmKLDE0k4j1y4c6ic'
			]]);

		$responseBody = json_decode($response->getBody());
		$eta = round($responseBody->rows[0]->elements[0]->duration->value / 60);


		DB::table('users_in_meetings')->where([['user_id', $userId], ['meeting_id', $meetingId]])->update(['eta' => $eta]);

		return json_encode($responseBody);

	}

	public function onReceiveMessage(Request $request) {

		return "Success";

	}

	public function sendSms($phoneNumber, $smsCode) {

		$messageBody = 'Welcome to meTme! Your activation code is ' . $smsCode;

		Twilio::message($phoneNumber, $messageBody);

	}

}