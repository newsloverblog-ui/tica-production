@extends('frontend.layout')
@section('pageHeading', __('Reset organiser password'))
@section('minimal-layout', 'true')
@section('react-only', 'true')
@php
  $authProps=['mode'=>'forgot','account'=>'organiser','action'=>route('organizer.forget.mail'),'csrf'=>csrf_token(),'fields'=>[
    ['name'=>'email','label'=>__('Email address'),'type'=>'email','autocomplete'=>'email','value'=>old('email'),'error'=>$errors->first('email')],
  ],'loginUrl'=>route('organizer.login'),'signupUrl'=>route('organizer.signup'),'forgotUrl'=>route('organizer.forget.password'),'homeUrl'=>route('index'),'status'=>Session::get('success'),'alert'=>Session::get('alert')];
@endphp
@section('content') @include('frontend.partials.auth-react') @endsection
