<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use Illuminate\Http\Request;

class PushNotificationController extends Controller
{
  public function store(Request $request)
  {
    $request->validate([
      'endpoint' => 'required|string|url|starts_with:https://|max:2048',
      'keys.p256dh' => 'required|string|max:512',
      'keys.auth' => 'required|string|max:256'
    ]);

    $endpoint = $request->endpoint;
    $key = $request->keys['p256dh'];
    $token = $request->keys['auth'];

    $guest = Guest::firstOrCreate([
      'endpoint' => $endpoint
    ]);

    $guest->updatePushSubscription($endpoint, $key, $token);

    return response()->json(['status' => 'Success'], 200);
  }
}
