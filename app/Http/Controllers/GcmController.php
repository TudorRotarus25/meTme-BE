<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use DB;

class GcmController extends Controller {

	public function refreshToken(Request $request) {

		$userId = $request->input('user_id');
		$gcmToken = $request->input('gcm_token');

		if(is_null($userId) || is_null($gcmToken)) {
			App::abort(400, 'Required fields are missing');
		}

		DB::table('users')->where('id', $userId)->update(['gcm_token' => $gcmToken]);

		return 'Success';

	}

}