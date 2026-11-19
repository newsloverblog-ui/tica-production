@extends('frontend.layout')
@section('pageHeading', __('Conversation'))
@section('react-only', 'true')
@section('content')
@php
  $organizerName = $ticket->recipientOrganizer?->verified_legal_name ?: $ticket->recipientOrganizer?->username ?: __('Event organizer');
  $messages = $ticket->messages->map(function ($message) use ($organizerName) {
    $fromCustomer = (int) $message->type === 1;
    return [
      'id' => (int) $message->id,
      'author' => $fromCustomer ? __('You') : ((int) $message->type === 3 ? $organizerName : __('TICA support')),
      'body' => trim(strip_tags((string) $message->reply)),
      'time' => optional($message->created_at)->format('j M Y, g:i a'),
      'fromCustomer' => $fromCustomer,
      'attachmentUrl' => $message->file ? route('customer.support_ticket.reply_attachment', $message->id) : null,
    ];
  })->values();
  $destination = $ticket->recipient_type === 'organizer'
    ? (($ticket->event?->title ?: __('Event')).' · '.$organizerName)
    : __('TICA support');
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
      'destination' => $destination,
      'status' => (int) $ticket->status === 3 ? __('Closed') : __('Open'),
      'initialMessage' => trim(strip_tags((string) $ticket->description)),
      'createdAt' => optional($ticket->created_at)->format('j M Y, g:i a'),
      'messages' => $messages,
    ],
  ];
@endphp
<div data-tica-react="support" data-props='@json($props)'></div>
@endsection
