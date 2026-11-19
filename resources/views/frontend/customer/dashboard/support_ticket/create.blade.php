@extends('frontend.layout')
@section('pageHeading', __('Create a support ticket'))
@section('react-only', 'true')
@section('content')
@php
  $props = ['mode'=>'create','csrf'=>csrf_token(),'listUrl'=>route('customer.support_tickert'),'createUrl'=>route('customer.support_tickert.create'),'storeUrl'=>route('customer.support_ticket.store'),'email'=>old('email',Auth::guard('customer')->user()->email),'success'=>Session::get('success'),'errors'=>$errors->toArray()];
@endphp
<div data-tica-react="support" data-props='@json($props)'></div>
@endsection
