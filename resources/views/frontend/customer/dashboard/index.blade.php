@extends('frontend.layout')
@section('pageHeading', __('My TICA account'))
@section('react-only', 'true')
@section('content')
@php
  $customer=Auth::guard('customer')->user();
  $bookingCards=$bookings->map(function($item) use($currentLanguageInfo){$event=$item->event()->where('language_id',$currentLanguageInfo->id)->select('title','slug','event_id')->first();if(!$event){$language=App\Models\Language::where('is_default',1)->first();$event=$item->event()->where('language_id',$language->id)->select('title','slug','event_id')->first();}if(!$event)return null;return ['id'=>(int)$item->id,'title'=>$event->title,'eventUrl'=>route('event.details',['slug'=>$event->slug,'id'=>$event->event_id]),'detailsUrl'=>route('customer.booking_details',$item->id),'reference'=>$item->booking_id,'eventDate'=>\Carbon\Carbon::parse($item->event_date)->translatedFormat('D, M d, Y · h:i a'),'bookedAt'=>\Carbon\Carbon::parse($item->created_at)->diffForHumans()];})->filter()->values();
  $accountProps=['name'=>$customer->fname ?: $customer->username ?: __('there'),'email'=>$customer->email,'phone'=>$customer->phone,'location'=>collect([$customer->city,$customer->state,$customer->country])->filter()->join(', '),'bookings'=>$bookingCards,'bookingsUrl'=>route('customer.booking.my_booking'),'editUrl'=>route('customer.edit.profile')];
@endphp
<div data-tica-react="account" data-props='@json($accountProps, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'><main class="min-h-[70vh] bg-tint"></main></div>
@endsection
