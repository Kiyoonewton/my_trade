<?php

use App\Http\Controllers\MovieController;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // return view('welcome');
});

// Route::get('/browse_movies/', [MovieController::class, 'show']);

Route::get('/user', function (Request $request) {
    $msg = 'MOngoDB is accessible';
    $connection = DB::connection('mongodb');
    try {
        $connection->command(['ping' => 1]);
    } catch (\Exception $e) {
        $msg = 'mongodb is not accessible' . $e->getMessage();
    }
    // echo $user::all();
    return ['$msg' => $msg];
});
