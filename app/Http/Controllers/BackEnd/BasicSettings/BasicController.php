<?php

namespace App\Http\Controllers\BackEnd\BasicSettings;

use App\Http\Controllers\Controller;
use App\Http\Helpers\UploadFile;
use App\Http\Requests\CurrencyRequest;
use App\Http\Requests\MailFromAdminRequest;
use App\Models\BasicSettings\Basic;
use App\Models\Timezone;
use App\Rules\ImageMimeTypeRule;
use App\Services\RuntimeDocumentStore;
use App\Support\TicaCurrency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;

class BasicController extends Controller
{

  public function information()
  {
    $information['data'] = DB::table('basic_settings')
      ->select('website_title', 'email_address', 'contact_number', 'address', 'latitude', 'longitude', 'timezone')
      ->first();

    $information['timezones'] = Timezone::get();

    return view('backend.basic-settings.contact', $information);
  }

  public function updateInfo(Request $request)
  {
    try {
      $rules = [
        'email_address' => 'required',
        'contact_number' => 'required',
        'address' => 'required',
        'latitude' => 'required|numeric',
        'longitude' => 'required|numeric',
      ];

      $validator = Validator::make($request->all(), $rules);

      if ($validator->fails()) {
        return redirect()->back()->withErrors($validator->errors());
      }

      DB::table('basic_settings')->updateOrInsert(
        ['uniqid' => 12345],
        [
          'email_address' => $request->email_address,
          'contact_number' => $request->contact_number,
          'address' => $request->address,
          'latitude' => $request->latitude,
          'longitude' => $request->longitude,
          'timezone' => $request->timezone
        ]
      );
      Session::flash('success', 'Updated Successfully');
      return back();
    } catch (\Exception $th) {
    }
  }


  public function themeAndHome()
  {
    $data = DB::table('basic_settings')->select('theme_version')->first();

    return view('backend.basic-settings.theme-&-home', ['data' => $data]);
  }

  public function updateThemeAndHome(Request $request)
  {
    $rules = [
      'theme_version' => 'required|numeric'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    DB::table('basic_settings')->updateOrInsert(
      ['uniqid' => 12345],
      ['theme_version' => $request->theme_version]
    );

    Session::flash('success', 'Updated Successfully');

    return redirect()->back();
  }


  public function currency()
  {
    $data = DB::table('basic_settings')
      ->select('base_currency_symbol', 'base_currency_symbol_position', 'base_currency_text', 'base_currency_text_position', 'base_currency_rate')
      ->first();

    return view('backend.basic-settings.currency', ['data' => $data]);
  }

  public function updateCurrency(CurrencyRequest $request)
  {
    DB::table('basic_settings')->updateOrInsert(
      ['uniqid' => 12345],
      [
        'base_currency_symbol' => $request->base_currency_symbol,
        'base_currency_symbol_position' => $request->base_currency_symbol_position,
        'base_currency_text' => $request->base_currency_text,
        'base_currency_text_position' => $request->base_currency_text_position,
        'base_currency_rate' => $request->base_currency_rate
      ]
    );
    TicaCurrency::reset();

    Session::flash('success', 'Updated Successfully');

    return redirect()->back();
  }


  // Colours are edited in General Settings; this old address points there.
  public function appearance()
  {
    return redirect()->route('admin.basic_settings.general_settings');
  }

  public function updateAppearance(Request $request)
  {
    $rules = [
      'primary_color' => 'required',
      'breadcrumb_overlay_color' => 'required',
      'breadcrumb_overlay_opacity' => 'required|numeric|min:0|max:1'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    DB::table('basic_settings')->updateOrInsert(
      ['uniqid' => 12345],
      [
        'primary_color' => $request->primary_color,
        'breadcrumb_overlay_color' => $request->breadcrumb_overlay_color,
        'breadcrumb_overlay_opacity' => $request->breadcrumb_overlay_opacity
      ]
    );

    Session::flash('success', 'Updated Successfully');

    return redirect()->back();
  }


  public function mailFromAdmin()
  {
    $data = Basic::query()
      ->select('smtp_status', 'smtp_host', 'smtp_port', 'encryption', 'smtp_username', 'smtp_password', 'from_mail', 'from_name')
      ->first();

    return view('backend.basic-settings.email.mail-from-admin', ['data' => $data]);
  }

  public function updateMailFromAdmin(MailFromAdminRequest $request)
  {
    $basic = Basic::query()->firstOrNew(['uniqid' => 12345]);
    $basic->uniqid = 12345;
    $basic->fill([
        'smtp_status' => $request->smtp_status,
        'smtp_host' => $request->smtp_host,
        'smtp_port' => $request->smtp_port,
        'encryption' => $request->encryption,
        'smtp_username' => $request->smtp_username,
        'smtp_password' => $request->smtp_password,
        'from_mail' => $request->from_mail,
        'from_name' => $request->from_name
      ])->save();

    Session::flash('success', 'Updated Successfully');

    return redirect()->back();
  }

  public function mailToAdmin()
  {
    $data = DB::table('basic_settings')->select('to_mail')->first();

    return view('backend.basic-settings.email.mail-to-admin', ['data' => $data]);
  }

  public function updateMailToAdmin(Request $request)
  {
    $rule = [
      'to_mail' => 'required'
    ];

    $message = [
      'to_mail.required' => 'The mail address field is required.'
    ];

    $validator = Validator::make($request->all(), $rule, $message);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    DB::table('basic_settings')->updateOrInsert(
      ['uniqid' => 12345],
      ['to_mail' => $request->to_mail]
    );

    Session::flash('success', 'Updated Successfully');

    return redirect()->back();
  }


  public function breadcrumb()
  {
    $data = DB::table('basic_settings')->select('breadcrumb')->first();

    return view('backend.basic-settings.breadcrumb', ['data' => $data]);
  }

  public function updateBreadcrumb(Request $request)
  {
    $data = DB::table('basic_settings')->select('breadcrumb')->first();

    $rules = [];

    if (!$request->filled('breadcrumb') && is_null($data->breadcrumb)) {
      $rules['breadcrumb'] = 'required';
    }
    if ($request->hasFile('breadcrumb')) {
      $rules['breadcrumb'] = new ImageMimeTypeRule();
    }

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    if ($request->hasFile('breadcrumb')) {
      $breadcrumbName = UploadFile::update(public_path('assets/admin/img/'), $request->file('breadcrumb'), $data->breadcrumb);

      // finally, store the breadcrumb into db
      DB::table('basic_settings')->updateOrInsert(
        ['uniqid' => 12345],
        ['breadcrumb' => $breadcrumbName]
      );

      Session::flash('success', 'Updated Successfully');
    }

    return redirect()->back();
  }


  public function plugins()
  {
    $data = Basic::query()
      ->select('disqus_status', 'disqus_short_name', 'google_recaptcha_status', 'google_recaptcha_site_key', 'google_recaptcha_secret_key', 'whatsapp_status', 'whatsapp_number', 'whatsapp_header_title', 'whatsapp_popup_status', 'whatsapp_popup_message', 'facebook_login_status', 'facebook_app_id', 'facebook_app_secret', 'google_login_status', 'google_client_id', 'google_client_secret')
      ->first();

    return view('backend.basic-settings.plugins', ['data' => $data]);
  }

  public function updateRecaptcha(Request $request)
  {
    $basic = $this->basicWithPreservedSecret(
      $request,
      'google_recaptcha_secret_key',
      'google_recaptcha_secret_key'
    );
    $rules = [
      'google_recaptcha_status' => 'required',
      'google_recaptcha_site_key' => 'required',
      'google_recaptcha_secret_key' => 'required'
    ];

    $messages = [
      'google_recaptcha_status.required' => 'The recaptcha status field is required.',
      'google_recaptcha_site_key.required' => 'The recaptcha site key field is required.',
      'google_recaptcha_secret_key.required' => 'The recaptcha secret key field is required.'
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator);
    }

    $basic->fill([
        'google_recaptcha_status' => $request->google_recaptcha_status,
        'google_recaptcha_site_key' => $request->google_recaptcha_site_key,
        'google_recaptcha_secret_key' => $request->google_recaptcha_secret_key
      ])->save();

    Session::flash('success', 'Updated Successfully');

    return redirect()->back();
  }

  public function updateDisqus(Request $request)
  {
    $rules = [
      'disqus_status' => 'required',
      'disqus_short_name' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator);
    }

    DB::table('basic_settings')->updateOrInsert(
      ['uniqid' => 12345],
      [
        'disqus_status' => $request->disqus_status,
        'disqus_short_name' => $request->disqus_short_name
      ]
    );

    Session::flash('success', 'Updated Successfully');

    return redirect()->back();
  }

  public function updateWhatsApp(Request $request)
  {
    $rules = [
      'whatsapp_status' => 'required',
      'whatsapp_number' => 'required',
      'whatsapp_header_title' => 'required',
      'whatsapp_popup_status' => 'required',
      'whatsapp_popup_message' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator);
    }

    DB::table('basic_settings')->updateOrInsert(
      ['uniqid' => 12345],
      [
        'whatsapp_status' => $request->whatsapp_status,
        'whatsapp_number' => $request->whatsapp_number,
        'whatsapp_header_title' => $request->whatsapp_header_title,
        'whatsapp_popup_status' => $request->whatsapp_popup_status,
        'whatsapp_popup_message' => $request->whatsapp_popup_message
      ]
    );

    Session::flash('success', 'Updated Successfully');

    return redirect()->back();
  }

  public function updateFacebook(Request $request)
  {
    $basic = $this->basicWithPreservedSecret($request, 'facebook_app_secret', 'facebook_app_secret');
    $rules = [
      'facebook_login_status' => 'required',
      'facebook_app_id' => 'required',
      'facebook_app_secret' => 'required'
    ];

    $messages = [
      'facebook_login_status.required' => 'The login status field is required.',
      'facebook_app_id.required' => 'The app id field is required.',
      'facebook_app_secret.required' => 'The app secret field is required.'
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $basic->fill([
        'facebook_login_status' => $request->facebook_login_status,
        'facebook_app_id' => $request->facebook_app_id,
        'facebook_app_secret' => $request->facebook_app_secret
      ])->save();

    $request->session()->flash('success', 'Updated Successfully');

    return redirect()->back();
  }

  public function updateGoogle(Request $request)
  {
    $basic = $this->basicWithPreservedSecret($request, 'google_client_secret', 'google_client_secret');
    $rules = [
      'google_login_status' => 'required',
      'google_client_id' => 'required',
      'google_client_secret' => 'required'
    ];

    $messages = [
      'google_login_status.required' => 'The login status field is required.',
      'google_client_id.required' => 'The client id field is required.',
      'google_client_secret.required' => 'The client secret field is required.'
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $basic->fill([
        'google_login_status' => $request->google_login_status,
        'google_client_id' => $request->google_client_id,
        'google_client_secret' => $request->google_client_secret
      ])->save();

    $request->session()->flash('success', 'Updated Successfully');

    return redirect()->back();
  }


  public function maintenance()
  {
    $data = Basic::query()
      ->select('id', 'maintenance_img', 'maintenance_status', 'maintenance_msg')
      ->first();

    return view('backend.basic-settings.maintenance', ['data' => $data]);
  }

  public function updateMaintenance(Request $request)
  {
    $data = Basic::query()->firstOrNew(['uniqid' => 12345]);

    $rules = $messages = [];

    if (!$request->filled('maintenance_img') && is_null($data->maintenance_img)) {
      $rules['maintenance_img'] = 'required';

      $messages['maintenance_img.required'] = 'The maintenance image field is required.';
    }
    if ($request->hasFile('maintenance_img')) {
      $rules['maintenance_img'] = new ImageMimeTypeRule();
    }

    $rules['maintenance_status'] = 'required|integer|in:0,1';
    $rules['maintenance_msg'] = 'required|string|max:5000';

    $messages['maintenance_msg.required'] = 'The maintenance message field is required.';

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    if ($request->hasFile('maintenance_img')) {
      $imageName = UploadFile::update(public_path('assets/admin/img/'), $request->file('maintenance_img'), $data->maintenance_img);
    }

    $activate = (int) $request->maintenance_status === 1;
    $wasActive = (int) ($data->maintenance_status ?? 0) === 1;
    $command = $activate ? 'down' : 'up';
    $parameters = $activate ? ['--redirect' => '/service-unavailable'] : [];

    if (Artisan::call($command, $parameters) !== 0) {
      return redirect()->back()->withErrors([
        'maintenance_status' => __('The maintenance state could not be changed. Review the application log and try again.'),
      ]);
    }

    try {
      $data->fill([
        'maintenance_img' => $request->hasFile('maintenance_img') ? $imageName : $data->maintenance_img,
        'maintenance_status' => $activate ? 1 : 0,
        'maintenance_msg' => Purifier::clean($request->maintenance_msg, 'youtube'),
      ]);
      // Public bypass URLs are intentionally unsupported. Administrators remain
      // reachable through the maintenance middleware's explicit admin exception.
      $data->bypass_token = null;
      $data->save();
    } catch (\Throwable $exception) {
      Artisan::call($wasActive ? 'down' : 'up', $wasActive ? ['--redirect' => '/service-unavailable'] : []);
      throw $exception;
    }

    Session::flash('success', 'Updated Successfully');

    return redirect()->back();
  }


  public function footerLogo()
  {
    $data = DB::table('basic_settings')->select('footer_logo')->first();

    return view('backend.basic-settings.footer-logo', ['data' => $data]);
  }

  public function updateFooterLogo(Request $request)
  {
    $data = DB::table('basic_settings')->select('footer_logo')->first();

    $rules = [];

    if (!$request->filled('footer_logo') && is_null($data->footer_logo)) {
      $rules['footer_logo'] = 'required';
    }
    if ($request->hasFile('footer_logo')) {
      $rules['footer_logo'] = new ImageMimeTypeRule();
    }

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    if ($request->hasFile('footer_logo')) {
      $logoName = UploadFile::update(public_path('assets/admin/img/'), $request->file('footer_logo'), $data->footer_logo);

      // finally, store the footer-logo into db
      DB::table('basic_settings')->updateOrInsert(
        ['uniqid' => 12345],
        ['footer_logo' => $logoName]
      );

      Session::flash('success', 'Updated Successfully');
    }

    return redirect()->back();
  }


  public function advertiseSettings()
  {
    $data = DB::table('basic_settings')->select('google_adsense_publisher_id')->first();

    return view('backend.advertisement.settings', ['data' => $data]);
  }

  public function updateAdvertiseSettings(Request $request)
  {
    $rule = [
      'google_adsense_publisher_id' => 'required'
    ];

    $validator = Validator::make($request->all(), $rule);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    DB::table('basic_settings')->updateOrInsert(
      ['uniqid' => 12345],
      ['google_adsense_publisher_id' => $request->google_adsense_publisher_id]
    );

    Session::flash('success', 'Advertise settings updated successfully!');

    return redirect()->back();
  }

  public function pwa(Request $request, RuntimeDocumentStore $documents)
  {
    $data['pwa'] = $documents->get(
      RuntimeDocumentStore::WEB_MANIFEST,
      $documents->webManifestDefaults()
    );

    return view('backend.basic-settings.pwa', $data);
  }


  public function updatePwa(Request $request, RuntimeDocumentStore $documents)
  {

    $allowedExts = array('jpg', 'png', 'jpeg');
    $icon128 = $request->file('icon_128');

    $icon256 = $request->file('icon_256');

    $icon512 = $request->file('icon_512');

    $rules = [
      'short_name' => 'required',
      'name' => 'required',
      'theme_color' => 'required',
      'background_color' => 'required',
      'icon_128' => [
        function ($attribute, $value, $fail) use ($icon128, $allowedExts) {
          if (!empty($icon128)) {
            $ext = $icon128->getClientOriginalExtension();
            if (!in_array($ext, $allowedExts)) {
              return $fail("Only png, jpg, jpeg image is allowed");
            }
          }
        },
        'dimensions:width=128,height=128'
      ],
      'icon_256' => [
        function ($attribute, $value, $fail) use ($icon256, $allowedExts) {
          if (!empty($icon256)) {
            $ext = $icon256->getClientOriginalExtension();
            if (!in_array($ext, $allowedExts)) {
              return $fail("Only png, jpg, jpeg image is allowed");
            }
          }
        },
        'dimensions:width=256,height=256'
      ],
      'icon_512' => [
        function ($attribute, $value, $fail) use ($icon512, $allowedExts) {
          if (!empty($icon512)) {
            $ext = $icon512->getClientOriginalExtension();
            if (!in_array($ext, $allowedExts)) {
              return $fail("Only png, jpg, jpeg image is allowed");
            }
          }
        },
        'dimensions:width=512,height=512'
      ]
    ];

    $request->validate($rules);

    $content = $request->except('_token', 'icon_128', 'icon_256', 'icon_512', 'pwa_offline_img', 'start_url');
    $content['start_url'] = '/';
    $content['scope'] = '/';
    $content['display'] = 'standalone';
    $content['theme_color'] = '#' . $request->theme_color;
    $content['background_color'] = '#' . $request->background_color;


    $preManifest = $documents->get(
      RuntimeDocumentStore::WEB_MANIFEST,
      $documents->webManifestDefaults()
    );

    if ($request->hasFile('icon_128')) {
      $ext = ImageMimeTypeRule::extension($icon128);
      $filename = uniqid() . '.' . $ext;
      UploadFile::storeAs(public_path('assets/front/images/'), $icon128, $filename);

      $content['icons'][0] = [
        "src" => 'assets/front/images/' . $filename,
        "type" => "image/" . $ext,
        "sizes" => "128X128"
      ];
    } else {
      $content['icons'][0] = [
        "src" => $preManifest['icons'][0]['src'],
        "type" => $preManifest['icons'][0]['type'],
        "sizes" => $preManifest['icons'][0]['sizes']
      ];
    }

    if ($request->hasFile('icon_256')) {
      $ext = ImageMimeTypeRule::extension($icon256);
      $filename = uniqid() . '.' . $ext;
      UploadFile::storeAs(public_path('assets/front/images/'), $icon256, $filename);

      $content['icons'][1] = [
        "src" => 'assets/front/images/' . $filename,
        "type" => "image/" . $ext,
        "sizes" => "256X256"
      ];
    } else {
      $content['icons'][1] = [
        "src" => $preManifest['icons'][1]['src'],
        "type" => $preManifest['icons'][1]['type'],
        "sizes" => $preManifest['icons'][1]['sizes']
      ];
    }

    if ($request->hasFile('icon_512')) {
      $ext = ImageMimeTypeRule::extension($icon512);
      $filename = uniqid() . '.' . $ext;
      UploadFile::storeAs(public_path('assets/front/images/'), $icon512, $filename);

      $content['icons'][2] = [
        "src" => 'assets/front/images/' . $filename,
        "type" => "image/" . $ext,
        "sizes" => "512X512"
      ];
    } else {
      $content['icons'][2] = [
        "src" => $preManifest['icons'][2]['src'],
        "type" => $preManifest['icons'][2]['type'],
        "sizes" => $preManifest['icons'][2]['sizes']
      ];
    }

    $documents->put(RuntimeDocumentStore::WEB_MANIFEST, $content);

    return back()->with('success', 'Updated Successfully');
  }
  public function pwa_scanner(Request $request, RuntimeDocumentStore $documents)
  {
    $data['pwa'] = $documents->get(
      RuntimeDocumentStore::SCANNER_MANIFEST,
      $documents->scannerManifestDefaults()
    );

    return view('backend.basic-settings.pwa_scanner', $data);
  }


  public function updatepwaScanner(Request $request, RuntimeDocumentStore $documents)
  {
    $allowedExts = array('jpg', 'png', 'jpeg');
    $icon128 = $request->file('icon_128');

    $icon256 = $request->file('icon_256');
    $icon512 = $request->file('icon_512');

    $rules = [
      'short_name' => 'required',
      'name' => 'required',
      'theme_color' => 'required',
      'background_color' => 'required',
      'icon_128' => [
        function ($attribute, $value, $fail) use ($icon128, $allowedExts) {
          if (!empty($icon128)) {
            $ext = $icon128->getClientOriginalExtension();
            if (!in_array($ext, $allowedExts)) {
              return $fail("Only png, jpg, jpeg image is allowed");
            }
          }
        },
        'dimensions:width=128,height=128'
      ],
      'icon_256' => [
        function ($attribute, $value, $fail) use ($icon256, $allowedExts) {
          if (!empty($icon256)) {
            $ext = $icon256->getClientOriginalExtension();
            if (!in_array($ext, $allowedExts)) {
              return $fail("Only png, jpg, jpeg image is allowed");
            }
          }
        },
        'dimensions:width=256,height=256'
      ],
      'icon_512' => [
        function ($attribute, $value, $fail) use ($icon512, $allowedExts) {
          if (!empty($icon512)) {
            $ext = $icon512->getClientOriginalExtension();
            if (!in_array($ext, $allowedExts)) {
              return $fail("Only png, jpg, jpeg image is allowed");
            }
          }
        },
        'dimensions:width=512,height=512'
      ]
    ];

    $request->validate($rules);

    $content = $request->except('_token', 'icon_128', 'icon_256', 'icon_512', 'pwa_offline_img', 'start_url');
    $content['start_url'] = '/scanner/workspace';
    $content['scope'] = '/';
    $content['display'] = 'standalone';
    $content['theme_color'] = '#' . $request->theme_color;
    $content['background_color'] = '#' . $request->background_color;

    $preManifest = $documents->get(
      RuntimeDocumentStore::SCANNER_MANIFEST,
      $documents->scannerManifestDefaults()
    );

    if ($request->hasFile('icon_128')) {
      $ext = ImageMimeTypeRule::extension($icon128);
      $filename = uniqid() . '.' . $ext;
      UploadFile::storeAs(public_path('assets/pwa_scanner/'), $icon128, $filename);

      $content['icons'][0] = [
        "src" => 'assets/pwa_scanner/' . $filename,
        "type" => "image/" . $ext,
        "sizes" => "128X128"
      ];
    } else {
      $content['icons'][0] = [
        "src" => $preManifest['icons'][0]['src'],
        "type" => $preManifest['icons'][0]['type'],
        "sizes" => $preManifest['icons'][0]['sizes']
      ];
    }

    if ($request->hasFile('icon_256')) {
      $ext = ImageMimeTypeRule::extension($icon256);
      $filename = uniqid() . '.' . $ext;
      UploadFile::storeAs(public_path('assets/pwa_scanner/'), $icon256, $filename);

      $content['icons'][1] = [
        "src" => 'assets/pwa_scanner/' . $filename,
        "type" => "image/" . $ext,
        "sizes" => "256X256"
      ];
    } else {
      $content['icons'][1] = [
        "src" => $preManifest['icons'][1]['src'],
        "type" => $preManifest['icons'][1]['type'],
        "sizes" => $preManifest['icons'][1]['sizes']
      ];
    }

    if ($request->hasFile('icon_512')) {
      $ext = ImageMimeTypeRule::extension($icon512);
      $filename = uniqid() . '.' . $ext;
      UploadFile::storeAs(public_path('assets/pwa_scanner/'), $icon512, $filename);

      $content['icons'][2] = [
        "src" => 'assets/pwa_scanner/' . $filename,
        "type" => "image/" . $ext,
        "sizes" => "512X512"
      ];
    } else {
      $content['icons'][2] = [
        "src" => $preManifest['icons'][2]['src'],
        "type" => $preManifest['icons'][2]['type'],
        "sizes" => $preManifest['icons'][2]['sizes']
      ];
    }

    $documents->put(RuntimeDocumentStore::SCANNER_MANIFEST, $content);

    return back()->with('success', 'Updated Successfully');
  }

  // /taxCommission
  public function guestCheckout()
  {
    // Guest checkout now lives with the booking fee on Platform settings.
    return redirect()->to(route('admin.tica.platform') . '#ta-fees');
  }

  public function updateGuestCheckout(Request $request)
  {
    $rules = [
      'event_guest_checkout_status' => 'required|numeric'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    DB::table('basic_settings')->updateOrInsert(
      ['uniqid' => 12345],
      [
        'event_guest_checkout_status' => $request->event_guest_checkout_status,
      ]
    );
    $request->session()->flash('success', 'Updated Successfully');

    return redirect()->back();
  }
  // /taxCommission
  public function taxCommission()
  {
    return redirect()->to(route('admin.tica.platform') . '#ta-fees');
  }

  public function updateEventTaxCommission(Request $request)
  {
    $rules = [
      'booking_fee_percentage' => 'required|numeric|min:0|max:100',
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    DB::table('basic_settings')->updateOrInsert(
      ['uniqid' => 12345],
      [
        'booking_fee_percentage' => round((float) $request->booking_fee_percentage, 2),
      ]
    );
    $request->session()->flash('success', 'Updated Successfully');

    return redirect()->back();
  }

  //general_settings
  public function general_settings()
  {
    $data = [];

    $data['data'] = DB::table('basic_settings')->first();

    $data['time_zones'] = Timezone::orderBy('country_code', 'asc')->get();

    return view('backend.basic-settings.general-settings', $data);
  }
  //update general settings
  public function update_general_setting(Request $request)
  {
    $data = DB::table('basic_settings')->first();
    $rules = [];

    $rules = [
      'website_title' => 'required',
      'timezone' => 'required',
      'base_currency_symbol' => 'required',
      'base_currency_symbol_position' => 'required',
      'base_currency_text' => 'required',
      'base_currency_text_position' => 'required',
      'base_currency_rate' => 'required|numeric',
      'primary_color' => 'required',
      'breadcrumb_overlay_color' => 'required',
      'breadcrumb_overlay_opacity' => 'required|numeric|min:0|max:1'
    ];



    if (!$request->filled('logo') && is_null($data->logo)) {
      $rules['logo'] = 'required';
    }
    if (!$request->filled('preloader') && is_null($data->preloader)) {
      $rules['preloader'] = 'required';
    }
    if ($request->hasFile('preloader')) {
      $rules['preloader'] = new ImageMimeTypeRule();
    }

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    if ($request->hasFile('logo')) {
      $logoName = UploadFile::update(public_path('assets/admin/img/'), $request->file('logo'), $data->logo);
    } else {
      $logoName = $data->logo;
    }
    if ($request->hasFile('preloader')) {
      $preloaderName = UploadFile::update(public_path('assets/admin/img/'), $request->file('preloader'), $data->preloader);
    } else {
      $preloaderName = $data->preloader;
    }

    if ($request->hasFile('favicon')) {
      $iconName = UploadFile::update(public_path('assets/admin/img/'), $request->file('favicon'), $data->favicon);
    } else {
      $iconName = $data->favicon;
    }

    //update or insert data to basic settigs table 
    DB::table('basic_settings')->updateOrInsert(
      ['uniqid' => 12345],
      [
        'website_title' => $request->website_title,
        'logo' => $logoName,
        'preloader' => $preloaderName,
        'favicon' => $iconName,
        'timezone' => $request->timezone,
        'primary_color' => $request->primary_color,
        'breadcrumb_overlay_color' => $request->breadcrumb_overlay_color,
        'breadcrumb_overlay_opacity' => $request->breadcrumb_overlay_opacity,
        'base_currency_symbol' => $request->base_currency_symbol,
        'base_currency_symbol_position' => $request->base_currency_symbol_position,
        'base_currency_text' => $request->base_currency_text,
        'base_currency_text_position' => $request->base_currency_text_position,
        'base_currency_rate' => $request->base_currency_rate,

      ]
    );
    TicaCurrency::reset();

    Session::flash('success', 'Updated Successfully!');

    return redirect()->back();
  }

  private function basicWithPreservedSecret(
    Request $request,
    string $requestKey,
    string $attribute
  ): Basic {
    $basic = Basic::query()->firstOrNew(['uniqid' => 12345]);
    $basic->uniqid = 12345;
    if (trim((string) $request->input($requestKey)) === '') {
      $existing = (string) ($basic->{$attribute} ?? '');
      if ($existing !== '') {
        $request->merge([$requestKey => $existing]);
      }
    }

    return $basic;
  }
}
