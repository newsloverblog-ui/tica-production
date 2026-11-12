<?php

namespace App\Http\Controllers\BackEnd\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\UploadFile;
use App\Models\PushNotificationCampaign;
use App\Rules\ImageMimeTypeRule;
use App\Services\PushNotificationCampaignService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class PushNotificationController extends Controller
{
  public function settings()
  {
    $data = DB::table('basic_settings')->select('notification_image')->first();
    $vapidConfigured = filled(config('webpush.vapid.public_key'))
      && filled(config('webpush.vapid.private_key'));

    return view('backend.end-user.push-notification.settings', compact('data', 'vapidConfigured'));
  }

  public function updateSettings(Request $request)
  {
    $data = DB::table('basic_settings')->select('notification_image')->first();

    $rules = [];

    if (is_null($data->notification_image)) {
      $rules['notification_image'] = 'required';
    }
    if ($request->hasFile('notification_image')) {
      $rules['notification_image'] = new ImageMimeTypeRule();
    }

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator);
    }

    if ($request->hasFile('notification_image')) {
      $imageName = UploadFile::update(public_path('assets/admin/img/'), $request->file('notification_image'), $data->notification_image);

      DB::table('basic_settings')->updateOrInsert(
        ['uniqid' => 12345],
        ['notification_image' => $imageName]
      );
    }

    Session::flash('success', 'Updated Successfully');

    return redirect()->back();
  }

  public function writeNotification()
  {
    $campaigns = PushNotificationCampaign::query()->latest('id')->limit(10)->get();

    return view('backend.end-user.push-notification.write-notification', compact('campaigns'));
  }

  public function sendNotification(Request $request, PushNotificationCampaignService $campaigns)
  {
    $validated = $request->validate([
      'title' => ['required', 'string', 'max:255'],
      'message' => ['nullable', 'string', 'max:1000'],
      'button_name' => ['required', 'string', 'max:80'],
      'button_url' => ['required', 'url:http,https', 'max:2048'],
    ]);

    try {
      $campaign = $campaigns->create(
        $validated['title'],
        $validated['message'] ?? null,
        $validated['button_name'],
        $validated['button_url'],
        Auth::guard('admin')->id()
      );
    } catch (DomainException $exception) {
      return redirect()->back()->withInput()->with('warning', $exception->getMessage());
    }

    Session::flash(
      'success',
      'Notification queued for '.$campaign->total_count.' subscribed browser'.($campaign->total_count === 1 ? '' : 's').'.'
    );

    return redirect()->back();
  }
}
