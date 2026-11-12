@extends('frontend.layout')
@section('pageHeading', __('Create an organiser account'))
@section('minimal-layout', 'true')
@section('react-only', 'true')
@php
  $authProps=['mode'=>'signup','account'=>'organiser','action'=>route('organizer.create'),'csrf'=>csrf_token(),'fields'=>[
    ['name'=>'name','label'=>__('Organisation name'),'autocomplete'=>'organization','value'=>old('name'),'error'=>$errors->first('name')],
    ['name'=>'username','label'=>__('Username'),'autocomplete'=>'username','value'=>old('username'),'error'=>$errors->first('username')],
    ['name'=>'email','label'=>__('Email address'),'type'=>'email','autocomplete'=>'email','value'=>old('email'),'error'=>$errors->first('email')],
    ['name'=>'password','label'=>__('Password'),'type'=>'password','autocomplete'=>'new-password','error'=>$errors->first('password')],
    ['name'=>'password_confirmation','label'=>__('Confirm password'),'type'=>'password','autocomplete'=>'new-password'],
  ],'loginUrl'=>route('organizer.login'),'signupUrl'=>route('organizer.signup'),'forgotUrl'=>route('organizer.forget.password'),'homeUrl'=>route('index'),'captchaHtml'=>$basicInfo->google_recaptcha_status==1?NoCaptcha::display():null];
@endphp
@section('content') @include('frontend.partials.auth-react') @endsection
@if($basicInfo->google_recaptcha_status==1) @section('script') {!! NoCaptcha::renderJs() !!} @endsection @endif
