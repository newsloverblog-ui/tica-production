@extends('frontend.layout')
@section('pageHeading', __('Verify Email'))
@section('minimal-layout', 'true')
@section('react-only', 'true')
@section('content')
@php
  $authProps=['mode'=>'verify','account'=>'customer','action'=>route('customer.send.verify.link'),'csrf'=>csrf_token(),'fields'=>[
    ['name'=>'email','label'=>__('Email address'),'type'=>'email','autocomplete'=>'email','value'=>old('email'),'error'=>$errors->first('email')],
  ],'loginUrl'=>route('customer.login'),'signupUrl'=>route('customer.signup'),'forgotUrl'=>route('customer.forget.password'),'homeUrl'=>route('index'),'status'=>Session::get('success')];
@endphp
@include('frontend.partials.auth-react',['authProps'=>$authProps])
@endsection
