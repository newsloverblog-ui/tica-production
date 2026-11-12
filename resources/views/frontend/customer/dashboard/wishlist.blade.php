@extends('frontend.layout')
@section('pageHeading', __('My Wishlist'))
@section('react-only', 'true')
@section('content')
@php
  $savedEvents = [];
  foreach ($wishlist as $item) {
    $content = DB::table('event_contents')->where('event_id', $item->event_id)->select('title','slug')->first();
    if ($content) $savedEvents[] = ['id'=>$item->event_id,'title'=>$content->title,'eventUrl'=>route('event.details',[$content->slug,$item->event_id]),'removeUrl'=>route('remove.wishlist',$item->event_id)];
  }
  $nav = [
    ['label'=>__('Overview'),'url'=>route('customer.dashboard')],['label'=>__('My bookings'),'url'=>route('customer.booking.my_booking')],['label'=>__('Profile'),'url'=>route('customer.edit.profile')],['label'=>__('Password'),'url'=>route('customer.change.password')],['label'=>__('Wishlist'),'url'=>route('customer.wishlist'),'active'=>true],
  ];
  $props = ['eyebrow'=>__('Saved for later'),'title'=>__('My wishlist'),'intro'=>__('A simple place for the events you want to come back to.'),'nav'=>$nav,'csrf'=>csrf_token(),'variant'=>'wishlist','wishlist'=>$savedEvents];
@endphp
<div data-tica-react="account-settings" data-props='@json($props)'></div>
@endsection
