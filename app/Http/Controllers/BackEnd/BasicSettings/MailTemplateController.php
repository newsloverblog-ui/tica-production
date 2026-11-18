<?php

namespace App\Http\Controllers\BackEnd\BasicSettings;

use App\Http\Controllers\Controller;
use App\Models\BasicSettings\MailTemplate;
use App\Support\MailTemplateCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;

class MailTemplateController extends Controller
{
  public function index()
  {
    $templates = MailTemplate::all();

    return view('backend.basic-settings.email.templates', compact('templates'));
  }

  public function edit($id)
  {
    $templateInfo = MailTemplate::where('id', $id)->firstOrFail();
    $placeholderLabels = MailTemplateCatalog::labels();
    $availablePlaceholders = MailTemplateCatalog::placeholders((string) $templateInfo->mail_type);

    return view('backend.basic-settings.email.edit-template', compact('templateInfo', 'placeholderLabels', 'availablePlaceholders'));
  }

  public function update(Request $request, $id)
  {
    $rules = [
      'mail_subject' => 'required',
      'mail_body' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    MailTemplate::where('id', $id)->first()->update($request->except('mail_type', 'mail_body') + [
      'mail_body' => Purifier::clean($request->mail_body, 'youtube')
    ]);

    $request->session()->flash('success', 'Updated Successfully');

    return redirect()->back();
  }
}
