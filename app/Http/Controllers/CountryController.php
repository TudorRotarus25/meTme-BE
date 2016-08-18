<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use DB;

class CountryController extends Controller {

	public function getPrefix($iso) {

		$countryCode = DB::table('countries')->where('iso', $iso)->select('nicename as name', 'phonecode as phoneCode')->first();

		if ($countryCode) {

			$countryCode->phoneCode = "+" . $countryCode->phoneCode;
			return json_encode($countryCode);

		} else {
			App::abort(400, 'Country not found');
		}
	}

}