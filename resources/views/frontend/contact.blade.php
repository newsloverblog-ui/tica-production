@extends('frontend.layout')
@section('pageHeading', __('Contact TICA'))
@section('react-only', 'true')
@section('meta-keywords', !empty($seo->meta_keyword_contact) ? $seo->meta_keyword_contact : '')
@section('meta-description', !empty($seo->meta_description_contact) ? $seo->meta_description_contact : '')
@php
  $contactProps = [
    'page' => 'contact',
    'contact' => [
      'action' => route('contact.send_mail'),
      'csrf' => csrf_token(),
      'address' => (string) ($info->contact_addresses ?? 'Owerri, Nigeria'),
      'email' => (string) ($info->contact_mails ?? 'hello@tica.ng'),
      'phone' => (string) ($info->contact_numbers ?? ''),
      'success' => Session::get('success'),
      'error' => Session::get('error'),
      'errors' => $errors->toArray(),
      'old' => old(),
      'captchaHtml' => $basicInfo->google_recaptcha_status == 1 ? NoCaptcha::display() : null,
    ],
  ];
@endphp
@section('content')
  <div data-tica-react="content" data-props='@json($contactProps, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'>
    <main class="min-h-[60vh] bg-navy"></main>
  </div>
@endsection
@if($basicInfo->google_recaptcha_status == 1)
  @section('script') {!! NoCaptcha::renderJs() !!} @endsection
@endif
