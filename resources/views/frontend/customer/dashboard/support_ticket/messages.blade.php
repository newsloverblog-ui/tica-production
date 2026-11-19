@extends('frontend.layout')
@section('pageHeading', __('Conversation'))
@section('react-only', 'true')
@section('content')
@php
  $messages = $ticket->messages->map(function ($message) {
    $fromCustomer = (int) $message->type === 1;
    return [
      'id' => (int) $message->id,
      'author' => $fromCustomer ? __('You') : __('TICA support'),
      'body' => trim(strip_tags((string) $message->reply)),
      'time' => optional($message->created_at)->format('j M Y, g:i a'),
      'fromCustomer' => $fromCustomer,
      'attachmentUrl' => $message->file ? route('customer.support_ticket.reply_attachment', $message->id) : null,
    ];
  })->values();
  $props = [
    'mode' => 'conversation',
    'csrf' => csrf_token(),
    'listUrl' => route('customer.support_tickert'),
    'createUrl' => route('customer.support_tickert.create'),
    'replyUrl' => route('customer-reply', $ticket->id),
    'success' => Session::get('success'),
    'errors' => $errors->toArray(),
    'canReply' => (int) $ticket->status !== 3,
    'conversation' => [
      'subject' => $ticket->subject,
      'destination' => __('TICA support'),
      'status' => (int) $ticket->status === 3 ? __('Closed') : __('Open'),
      'initialMessage' => trim(strip_tags((string) $ticket->description)),
      'createdAt' => optional($ticket->created_at)->format('j M Y, g:i a'),
      'messages' => $messages,
    ],
  ];
@endphp
<div data-tica-react="support" data-props='@json($props)'></div>
@endsection
