<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Helpers\UploadFile;
use App\Models\Admin;
use App\Models\BasicSettings\Basic;
use App\Models\BasicSettings\MailTemplate;
use App\Models\Event;
use App\Models\Language;
use App\Models\Organizer;
use App\Models\OrganizerInfo;
use App\Services\OrganizerBalanceAdjustmentService;
use App\Services\OperationalArchiveService;
use App\Services\OperationalStatusService;
use App\Services\AuditLogger;
use App\Services\PasswordPolicy;
use App\Services\ApplicationMailService;
use App\Rules\ImageMimeTypeRule;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class OrganizerManagementController extends Controller
{
  private $admin_user_name;
  public function __construct()
  {
    $this->middleware(function ($request, $next) {
      $this->admin_user_name = optional(Admin::select('username')->first())->username;
      return $next($request);
    });
  }

  public function settings()
  {
    $setting = DB::table('basic_settings')->where('uniqid', 12345)->select('organizer_email_verification', 'organizer_admin_approval', 'admin_approval_notice')->first();
    return view('backend.end-user.organizer.settings', compact('setting'));
  }
  //update_setting
  public function update_setting(Request $request)
  {
    if ($request->organizer_email_verification) {
      $organizer_email_verification = 1;
    } else {
      $organizer_email_verification = 0;
    }
    if ($request->organizer_admin_approval) {
      $organizer_admin_approval = 1;
    } else {
      $organizer_admin_approval = 0;
    }
    // finally, store the favicon into db
    DB::table('basic_settings')->updateOrInsert(
      ['uniqid' => 12345],
      [
        'organizer_email_verification' => $organizer_email_verification,
        'organizer_admin_approval' => $organizer_admin_approval,
        'admin_approval_notice' => $request->admin_approval_notice,
      ]
    );

    Session::flash('success', 'Updated Successfully');
    return back();
  }

  public function index(Request $request)
  {
    $searchKey = null;

    if ($request->filled('info')) {
      $searchKey = $request['info'];
    }

    $organizers = Organizer::when($searchKey, function ($query, $searchKey) {
      return $query->where('username', 'like', '%' . $searchKey . '%')
        ->orWhere('email', 'like', '%' . $searchKey . '%');
    })
      ->orderBy('id', 'desc')
      ->paginate(10);

    return view('backend.end-user.organizer.index', compact('organizers'));
  }

  //add
  public function add()
  {
    $languages = Language::get();
    return view('backend.end-user.organizer.create', compact('languages'));
  }
  public function create(Request $request)
  {
    $rules = [
      'email' => [
        'required',
        'email',
        Rule::unique('organizers', 'email')
      ],
      'password' => ['required', 'confirmed', PasswordPolicy::rule()],
      'username' => [
        'required',
        'alpha_dash',
        "not_in:$this->admin_user_name",
        Rule::unique('organizers', 'username')
      ],
    ];

    $languages = Language::get();

    $messages = [];

    foreach ($languages as $language) {
      $rules[$language->code . '_name'] = 'required';
      $messages[$language->code . '_name'] = 'The name field is required for ' . $language->name . ' language.';
    }

    $request->validate($rules, $messages);

    $in = $request->all();
    $in['password'] = Hash::make($request->password);

    $file = $request->file('photo');
    if ($file) {
      $extension = ImageMimeTypeRule::extension($file);
      $directory = public_path('assets/admin/img/organizer-photo/');
      $fileName = uniqid() . '.' . $extension;
      UploadFile::storeAs($directory, $file, $fileName);
      $in['photo'] = $fileName;
    }

    $in['status'] = 1;
    $in['email_verified_at'] = now();

    $organizer = Organizer::create($in);

    $languages = Language::get();
    foreach ($languages as $language) {
      $organizer_info = OrganizerInfo::where('organizer_id', $organizer->id)->where('language_id', $language->id)->first();
      if (!$organizer_info) {
        $organizer_info = new OrganizerInfo();
        $organizer_info->language_id = $language->id;
        $organizer_info->organizer_id = $organizer->id;
      }
      $organizer_info->name = $request[$language->code . '_name'];
      $organizer_info->country = $request[$language->code . '_country'];
      $organizer_info->city = $request[$language->code . '_city'];
      $organizer_info->state = $request[$language->code . '_state'];
      $organizer_info->zip_code = $request[$language->code . '_zip_code'];
      $organizer_info->address = $request[$language->code . '_address'];
      $organizer_info->details = $request[$language->code . '_details'];
      $organizer_info->save();
    }
    Session::flash('success', 'Added Successfully!');
    return Response::json(['status' => 'success'], 200);
  }

  public function updateEmailStatus(Request $request, $id)
  {
    $status = (int) $request->validate([
      'email_status' => ['required', Rule::in([0, 1])],
    ])['email_status'];
    (new OperationalStatusService())->setOrganizerEmailVerification((int) $id, $status);
    Session::flash('success', 'Update Email Verification Status Successfully!');

    return redirect()->back();
  }

  public function show($id)
  {

    $information['langs'] = Language::all();

    $language = Language::where('code', request()->input('language'))->firstOrFail();
    $information['language'] = $language;

    $event_type = request()->input('event_type');


    $events = Event::join('event_contents', 'event_contents.event_id', '=', 'events.id')
      ->join('event_categories', 'event_categories.id', '=', 'event_contents.event_category_id')
      ->where('event_contents.language_id', '=', $language->id)
      ->where('events.organizer_id', '=', $id)
      ->when($event_type, function ($query, $event_type) {
        return $query->where('events.event_type', $event_type);
      })
      ->select('events.*', 'event_contents.id as eventInfoId', 'event_contents.title', 'event_categories.name as category', 'event_contents.slug')
      ->orderByDesc('events.id')
      ->get();

    $information['events'] = $events;

    $organizer = Organizer::findOrFail($id);
    $information['organizer'] = $organizer;

    return view('backend.end-user.organizer.details', $information);
  }
  public function updateAccountStatus(Request $request, $id)
  {
    $status = (int) $request->validate([
      'account_status' => ['required', Rule::in([0, 1])],
    ])['account_status'];
    (new OperationalStatusService())->setOrganizerStatus((int) $id, $status);
    Session::flash('success', 'Updated Successfully');

    return redirect()->back();
  }
  public function changePassword($id)
  {
    $userInfo = Organizer::findOrFail($id);

    return view('backend.end-user.organizer.change-password', compact('userInfo'));
  }
  public function updatePassword(Request $request, $id)
  {
    $rules = [
      'new_password' => ['required', 'confirmed', PasswordPolicy::rule()],
      'new_password_confirmation' => 'required'
    ];

    $messages = [
      'new_password.confirmed' => 'Password confirmation does not match.',
      'new_password_confirmation.required' => 'The confirm new password field is required.'
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      return Response::json([
        'errors' => $validator->getMessageBag()->toArray()
      ], 400);
    }

    $user = Organizer::findOrFail($id);

    $user->update([
      'password' => Hash::make($request->new_password)
    ]);
    AuditLogger::record('organizer.password_changed_by_admin', $user);

    Session::flash('success', 'Updated Successfully');

    return Response::json(['status' => 'success'], 200);
  }

  public function edit($id)
  {
    $information = [];
    $languages = Language::get();
    $organizer = Organizer::findOrFail($id);
    $information['organizer'] = $organizer;
    $information['currencyInfo'] = $this->getCurrencyInfo();
    $information['languages'] = $languages;
    return view('backend.end-user.organizer.edit', $information);
  }

  //update
  public function update(Request $request, $id, Organizer $organizer)
  {
    try {
      $rules = [
        'email' => [
          'required',
          Rule::unique('organizers', 'username')->ignore($id)
        ],
        'username' => [
          'required',
          'alpha_dash',
          "not_in:$this->admin_user_name",
          Rule::unique('organizers', 'username')->ignore($id)
        ],
      ];

      $languages = Language::get();

      $messages = [];
      foreach ($languages as $language) {
        $rules[$language->code . '_name'] = 'required';
        $messages[$language->code . '_name'] = 'The name field is required for ' . $language->name . ' language.';
      }

      if ($request->hasFile('photo')) {
        $rules['photo']  = 'dimensions:width=300,height=300';
      }

      $validator = Validator::make($request->all(), $rules, $messages);
      if ($validator->fails()) {
        return Response::json(
          [
            'errors' => $validator->getMessageBag()
          ],
          400
        );
      }


      $in = $request->all();
      $organizer  = Organizer::where('id', $id)->first();
      $file = $request->file('photo');
      if ($file) {
        $extension = ImageMimeTypeRule::extension($file);
        $directory = public_path('assets/admin/img/organizer-photo/');
        $fileName = uniqid() . '.' . $extension;
        UploadFile::storeAs($directory, $file, $fileName);
        UploadFile::delete($directory, (string) $organizer->photo);
        $in['photo'] = $fileName;
      }
      $organizer->update($in);

      $languages = Language::get();
      foreach ($languages as $language) {
        $organizer_info = OrganizerInfo::where('organizer_id', $organizer->id)->where('language_id', $language->id)->first();
        if (!$organizer_info) {
          $organizer_info = new OrganizerInfo();
          $organizer_info->language_id = $language->id;
          $organizer_info->organizer_id = $organizer->id;
        }
        $organizer_info->name = $request[$language->code . '_name'];
        $organizer_info->country = $request[$language->code . '_country'];
        $organizer_info->city = $request[$language->code . '_city'];
        $organizer_info->state = $request[$language->code . '_state'];
        $organizer_info->zip_code = $request[$language->code . '_zip_code'];
        $organizer_info->address = $request[$language->code . '_address'];
        $organizer_info->details = $request[$language->code . '_details'];
        $organizer_info->save();
      }
    } catch (\Exception $th) {
    }
    Session::flash('success', 'Updated Successfully');

    return Response::json(['status' => 'success'], 200);
  }
  //update_organizer_balance
  public function update_organizer_balance(Request $request, $id)
  {
    $validated = $request->validate([
      'amount_status' => ['required', Rule::in(['0', '1'])],
      'amount' => ['required', 'numeric', 'min:0.01'],
      'reason' => ['required', 'string', 'max:1000'],
    ]);
    $currency_info = Basic::select('base_currency_symbol_position', 'base_currency_symbol')
      ->first();
    [$organizer, $transcation] = (new OrganizerBalanceAdjustmentService())->adjust(
      (int) $id,
      $validated['amount_status'] === '1' ? 'credit' : 'debit',
      (float) $validated['amount'],
      $validated['reason'],
      (int) Auth::guard('admin')->id(),
      (string) $currency_info->base_currency_symbol,
      (string) $currency_info->base_currency_symbol_position
    );
    $amount = (float) $organizer->amount;

    //send mail
    if ($request->amount_status == 1 || $request->amount_status == 0) {
      if ($request->amount_status == 1) {
        $template_type = 'balance_add';

        $organizer_alert_msg = "Balance added to organizer account succefully.!";
      } else {
        $template_type = 'balance_subtract';
        $organizer_alert_msg = "Balance Subtract from organizer account succefully.!";
      }
      //mail sending
      // get the website title & mail's smtp information from db
      $info = Basic::select('website_title', 'base_currency_symbol_position', 'base_currency_symbol')
        ->first();

      //preparing mail info
      // get the mail template info from db
      $mailTemplate = MailTemplate::query()->where('mail_type', '=', $template_type)->first();
      $mailData['subject'] = $mailTemplate->mail_subject;
      $mailBody = $mailTemplate->mail_body;

      // get the website title info from db
      $website_info = Basic::select('website_title')->first();

      // preparing dynamic data
      $organizerName = $organizer->username;
      $organizerEmail = $organizer->email;
      $organizer_amount = $amount;

      $websiteTitle = $website_info->website_title;

      // replacing with actual data
      $mailBody = str_replace('{transaction_id}', $transcation->transcation_id, $mailBody);
      $mailBody = str_replace('{username}', $organizerName, $mailBody);
      $mailBody = str_replace('{amount}', $info->base_currency_symbol . $request->amount, $mailBody);

      $mailBody = str_replace('{current_balance}', $info->base_currency_symbol . $organizer_amount, $mailBody);
      $mailBody = str_replace('{website_title}', $websiteTitle, $mailBody);

      $mailData['body'] = $mailBody;

      $mailData['recipient'] = $organizerEmail;
      //preparing mail info end

      try {
        app(ApplicationMailService::class)->queueHtml(
          'organizer_balance_adjustment', $mailData['recipient'], $mailData['subject'], $mailData['body'], [], null, $organizer
        );
        Session::flash('success', $organizer_alert_msg);
      } catch (\Throwable $e) {
        report($e);
        Session::flash('warning', 'The balance was updated, but its email could not be queued.');
      }
      //mail sending end
    }
    return Response::json(['status' => 'success'], 200);
  }

  public function destroy($id)
  {
    (new OperationalArchiveService())->archiveOrganizers([(int) $id]);

    return redirect()->back()->with('success', 'Organizer archived. Financial and event history was preserved.');
  }

  public function bulkDestroy(Request $request)
  {
    $ids = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']])['ids'];
    (new OperationalArchiveService())->archiveOrganizers($ids);

    Session::flash('success', 'Organizers archived. Financial and event history was preserved.');

    return Response::json(['status' => 'success'], 200);
  }

  //secrtet login
  public function secret_login(Request $request, $id)
  {
    $organizer = Organizer::findOrFail($id);
    $adminId = (int) Auth::guard('admin')->id();
    AuditLogger::record('organizer.impersonation_started', $organizer, [], [], [
      'admin_id' => $adminId,
      'target_email' => $organizer->email,
    ]);
    Session::put([
      'secret_login' => 1,
      'impersonator_admin_id' => $adminId,
      'impersonation_started_at' => now()->toIso8601String(),
    ]);
    Auth::guard('organizer')->login($organizer);
    return redirect()->route('organizer.dashboard');
  }

}
