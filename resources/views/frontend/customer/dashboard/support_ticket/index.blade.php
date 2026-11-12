@extends('frontend.layout')
@section('pageHeading', __('Support Tickets'))
@section('react-only', 'true')
@section('content')
@php
  $messagingEnabled = optional(App\Models\SupportTicketStatus::find(1))->support_ticket_status === 'active';
  $tickets = $collection->map(function ($item) use ($messagingEnabled) {
    return ['id'=>$item->id,'subject'=>$item->subject,'status'=>$item->status == 1 ? __('Pending') : ($item->status == 2 ? __('Open') : __('Closed')),'statusTone'=>$item->status == 1 ? 'orange' : ($item->status == 2 ? 'blue' : 'green'),'messageUrl'=>$messagingEnabled ? route('customer.support_ticket.message',$item->id) : null];
  })->values();
  $props = ['mode'=>'list','csrf'=>csrf_token(),'listUrl'=>route('customer.support_tickert'),'createUrl'=>route('customer.support_tickert.create'),'tickets'=>$tickets];
@endphp
<div data-tica-react="support" data-props='@json($props)'></div>
@endsection
