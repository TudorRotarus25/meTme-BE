<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('countries/{iso}', 'CountryController@getPrefix');

Route::post('users/create', 'UsersController@createUser');

Route::post('users/edit', 'UsersController@editUser')->middleware('token');

Route::get('users/profile/{userId}', 'UsersController@getProfile')->middleware('token');

Route::post('users/activate', 'UsersController@activateUser');

Route::post('users/friends', 'UsersController@getFriends')->middleware('token');

Route::get('users/notifications/{userId}', 'UsersController@getNotifications')->middleware('token');

Route::post('gcm/refresh', 'GcmController@refreshToken');

Route::post('meetings/create', 'MeetingsController@createMeeting')->middleware('token');

Route::post('meetings/create_pickup', 'MeetingsController@createPickup')->middleware('token');

Route::post('meetings/delete', 'MeetingsController@deleteMeeting')->middleware('token');

Route::post('meetings/cancel', 'MeetingsController@cancelMeeting')->middleware('token');

Route::post('meetings/finish', 'MeetingsController@finishMeeting')->middleware('token');

Route::post('meetings/postpone', 'MeetingsController@postponeMeeting')->middleware('token');

Route::post('meetings/update/transportation', 'MeetingsController@updateTransportationMethod')->middleware('token');

Route::post('meetings/accept', 'MeetingsController@acceptMeeting')->middleware('token');

Route::get('meetings/all/{userId}', 'MeetingsController@getAllMeetings')->middleware('token');

Route::get('meetings/next/{userId}', 'MeetingsController@getNextMeeting')->middleware('token');

Route::get('meetings/details/{userId}/{meetingId}', 'MeetingsController@getMeeting')->middleware('token');

Route::post('location/refresh', 'UsersController@refreshLocation')->middleware('token');

Route::post('sms/receive', 'UsersController@onReceiveMessage');
