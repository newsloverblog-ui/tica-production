@extends('frontend.layout')
@section('pageHeading', __('Verify organiser email'))
@section('minimal-layout', 'true')
@section('react-only', 'true')
@section('content')
@php
  $authProps=['mode'=>'verify','account'=>'organiser','action'=>route('organizer.send.verify.link'),'csrf'=>csrf_token(),'fields'=>[
    ['name'=>'email','label'=>__('Organiser email address'),'type'=>'email','autocomplete'=>'email','value'=>old('email'),'error'=>$errors->first('email')],
  ],'loginUrl'=>route('organizer.login'),'signupUrl'=>route('organizer.signup'),'forgotUrl'=>route('organizer.forget.password'),'homeUrl'=>route('index'),'status'=>Session::get('success'),'alert'=>Session::get('warning')];
@endphp
@include('frontend.partials.auth-react',['authProps'=>$authProps])
@endsection
