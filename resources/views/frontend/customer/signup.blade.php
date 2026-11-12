@extends('frontend.layout')
@section('pageHeading', __('Create your TICA account'))
@section('minimal-layout', 'true')
@section('react-only', 'true')
@php
  $authProps=['mode'=>'signup','account'=>'customer','action'=>route('customer.create'),'csrf'=>csrf_token(),'fields'=>[
    ['name'=>'fname','label'=>__('First name'),'autocomplete'=>'given-name','value'=>old('fname'),'error'=>$errors->first('fname')],
    ['name'=>'lname','label'=>__('Last name'),'autocomplete'=>'family-name','value'=>old('lname'),'error'=>$errors->first('lname')],
    ['name'=>'username','label'=>__('Username'),'autocomplete'=>'username','value'=>old('username'),'error'=>$errors->first('username')],
    ['name'=>'email','label'=>__('Email address'),'type'=>'email','autocomplete'=>'email','value'=>old('email'),'error'=>$errors->first('email')],
    ['name'=>'password','label'=>__('Password'),'type'=>'password','autocomplete'=>'new-password','error'=>$errors->first('password')],
    ['name'=>'password_confirmation','label'=>__('Confirm password'),'type'=>'password','autocomplete'=>'new-password'],
  ],'loginUrl'=>route('customer.login'),'signupUrl'=>route('customer.signup'),'forgotUrl'=>route('customer.forget.password'),'homeUrl'=>route('index'),'captchaHtml'=>$basicInfo->google_recaptcha_status==1?NoCaptcha::display():null];
@endphp
@section('content') @include('frontend.partials.auth-react') @endsection
@if($basicInfo->google_recaptcha_status==1) @section('script') {!! NoCaptcha::renderJs() !!} @endsection @endif
