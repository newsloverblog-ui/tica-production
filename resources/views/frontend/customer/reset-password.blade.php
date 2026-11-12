@extends('frontend.layout')
@section('pageHeading', __('Reset Password'))
@section('minimal-layout', 'true')
@section('react-only', 'true')
@section('content')
@php
  $authProps=['mode'=>'reset','account'=>'customer','action'=>route('customer.update-forget-password'),'csrf'=>csrf_token(),'fields'=>[
    ['name'=>'password','label'=>__('New password'),'type'=>'password','autocomplete'=>'new-password','error'=>$errors->first('password')],
    ['name'=>'password_confirmation','label'=>__('Confirm password'),'type'=>'password','autocomplete'=>'new-password'],
  ],'hiddenFields'=>['token'=>(string) request()->input('token'),'email'=>(string) request()->input('email')],'loginUrl'=>route('customer.login'),'signupUrl'=>route('customer.signup'),'forgotUrl'=>route('customer.forget.password'),'homeUrl'=>route('index'),'status'=>Session::get('success'),'alert'=>Session::get('alert') ?: $errors->first('token')];
@endphp
@include('frontend.partials.auth-react',['authProps'=>$authProps])
@endsection
