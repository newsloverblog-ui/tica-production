@extends('frontend.layout')
@section('pageHeading', __('Your tickets and bookings'))
@section('react-only', 'true')
@section('content')
@php
  $bookingCards=collect($bookings->items())->map(function($item) use($currentLanguageInfo){
    $event=$item->event()->where('language_id',$currentLanguageInfo->id)->select('title','slug','event_id')->first();
    if(!$event){$language=App\Models\Language::where('is_default',1)->first();$event=$item->event()->where('language_id',$language->id)->select('title','slug','event_id')->first();}
    if(!$event)return null;
    return ['id'=>(int)$item->id,'title'=>$event->title,'eventUrl'=>route('event.details',['slug'=>$event->slug,'id'=>$event->event_id]),'detailsUrl'=>route('customer.booking_details',$item->id),'reference'=>$item->booking_id,'eventDate'=>\Carbon\Carbon::parse($item->event_date)->translatedFormat('D, M d, Y · h:i a'),'bookedAt'=>\Carbon\Carbon::parse($item->created_at)->diffForHumans()];
  })->filter()->values();
  $heldCards=$heldTickets->map(function($ticketUnit){$event=optional($ticketUnit->booking)->event;$pending=$ticketUnit->pendingTransfer;return ['id'=>(int)$ticketUnit->id,'title'=>$event->title??__('Event ticket'),'sequence'=>(int)$ticketUnit->sequence,'holder'=>$ticketUnit->holder_name,'variation'=>$ticketUnit->variation_name,'qrUrl'=>route('customer.booking.ticket.qr',$ticketUnit->id),'pendingEmail'=>$pending?->recipient_email,'cancelUrl'=>$pending?route('customer.booking.ticket.transfer.cancel',$pending->id):null,'transferUrl'=>$pending?null:route('customer.booking.ticket.transfer',$ticketUnit->id)];})->values();
  $bookingsProps=['bookings'=>$bookingCards,'heldTickets'=>$heldCards,'csrf'=>csrf_token(),'paginationHtml'=>$bookings->links()->render(),'dashboardUrl'=>route('customer.dashboard')];
@endphp
<div data-tica-react="bookings" data-props='@json($bookingsProps, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'><main class="min-h-[70vh] bg-tint"></main></div>
@endsection
