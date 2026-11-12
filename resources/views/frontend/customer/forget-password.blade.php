@extends('frontend.layout')
@section('pageHeading', __('Reset your password'))
@section('minimal-layout', 'true')
@section('react-only', 'true')
@php
  $authProps=['mode'=>'forgot','account'=>'customer','action'=>route('customer.forget.mail'),'csrf'=>csrf_token(),'fields'=>[
    ['name'=>'email','label'=>__('Email address'),'type'=>'email','autocomplete'=>'email','value'=>old('email'),'error'=>$errors->first('email')],
  ],'loginUrl'=>route('customer.login'),'signupUrl'=>route('customer.signup'),'forgotUrl'=>route('customer.forget.password'),'homeUrl'=>route('index'),'status'=>Session::get('success'),'alert'=>Session::get('alert')];
@endphp
@section('content') @include('frontend.partials.auth-react') @endsection
