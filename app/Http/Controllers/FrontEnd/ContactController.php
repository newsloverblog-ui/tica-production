<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\BasicSettings\Basic;
use App\Models\ContactPage;
use App\Services\ApplicationMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
  public function contact()
  {
    $language = $this->getLanguage();

    $queryResult['seoInfo'] = $language->seoInfo()->select('meta_keyword_contact', 'meta_description_contact')->first();

    $queryResult['pageHeading'] = $this->getPageHeading($language);

    $queryResult['bgImg'] = $this->getBreadcrumb();

    $queryResult['info'] = ContactPage::where('language_id', $language->id)->first();
    $queryResult['contact_info'] = Basic::select('latitude', 'longitude')->first();

    return view('frontend.contact', $queryResult);
  }

  public function sendMail(Request $request)
  {

    $info = DB::table('basic_settings')
      ->select('google_recaptcha_status', 'to_mail')
      ->first();

    $rules = [
      'name' => 'required|string|max:100',
      'email' => 'required|string|email:rfc,dns|max:254',
      'subject' => 'required|string|max:200',
      'message' => 'required|string|max:5000'
    ];

    if ($info->google_recaptcha_status == 1) {
      $rules['g-recaptcha-response'] = 'required|captcha';
    }

    $msgs = [];

    if ($info->google_recaptcha_status == 1) {
      $msgs['g-recaptcha-response.required'] = 'Please verify that you are not a robot.';
      $msgs['g-recaptcha-response.captcha'] = 'Captcha error! try again later or contact site admin.';
    }

    $validator = Validator::make($request->all(), $rules, $msgs);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $name = $request->name;
    $to = $info->to_mail;
    $subject = $request->subject;

    $message = '<p>Message: ' . nl2br(e((string) $request->message)) . '</p>'
      . '<p><strong>Enquirer Name:</strong> ' . e((string) $name)
      . '<br><strong>Enquirer Email:</strong> ' . e((string) $request->email) . '</p>';

    try {
      app(ApplicationMailService::class)->queueHtml(
        'contact_enquiry', $to, $subject, $message, [], $request->email
      );

      Session::flash('success', 'Your message has been accepted for delivery.');
    } catch (\Throwable $e) {
      report($e);
      Session::flash('error', 'Your message could not be queued.');
    }

    return redirect()->back();
  }
}
