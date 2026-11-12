@extends('frontend.layout')
@section('pageHeading', __('Organiser Login'))
@section('minimal-layout', 'true')
@section('react-only', 'true')
@php
  $authProps=['mode'=>'login','account'=>'organiser','action'=>route('organizer.authentication'),'csrf'=>csrf_token(),'fields'=>[
    ['name'=>'username','label'=>__('Username'),'autocomplete'=>'username','value'=>old('username'),'error'=>$errors->first('username')],
    ['name'=>'password','label'=>__('Password'),'type'=>'password','autocomplete'=>'current-password','error'=>$errors->first('password')],
  ],'loginUrl'=>route('organizer.login'),'signupUrl'=>route('organizer.signup'),'forgotUrl'=>route('organizer.forget.password'),'homeUrl'=>route('index'),'status'=>Session::get('success'),'alert'=>Session::get('alert'),'captchaHtml'=>$basicInfo->google_recaptcha_status==1?NoCaptcha::display():null];
@endphp
@section('content') @include('frontend.partials.auth-react') @endsection
@if($basicInfo->google_recaptcha_status==1) @section('script') {!! NoCaptcha::renderJs() !!} @endsection @endif
