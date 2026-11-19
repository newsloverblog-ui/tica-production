@extends('frontend.layout')
@section('pageHeading', __('Create a support ticket'))
@section('react-only', 'true')
@section('content')
@php
  $bookingOptions = $bookings->map(fn ($booking) => ['id'=>(int)$booking->id,'eventTitle'=>$booking->event?->title ?: __('Event'),'reference'=>$booking->order_reference])->values();
  $props = ['mode'=>'create','csrf'=>csrf_token(),'listUrl'=>route('customer.support_tickert'),'createUrl'=>route('customer.support_tickert.create'),'storeUrl'=>route('customer.support_ticket.store'),'email'=>old('email',Auth::guard('customer')->user()->email),'success'=>Session::get('success'),'errors'=>$errors->toArray(),'bookings'=>$bookingOptions,'initialRecipient'=>request('recipient') === 'tica' ? 'tica' : 'organizer'];
@endphp
<div data-tica-react="support" data-props='@json($props)'></div>
@endsection
