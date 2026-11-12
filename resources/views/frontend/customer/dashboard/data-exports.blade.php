@extends('frontend.layout')
@section('pageHeading', __('My Data'))
@section('react-only', 'true')
@section('content')
@php
  $openPrivacyRequest = $privacyRequests->first(fn ($item) => in_array($item->status, ['pending', 'under_review'], true));
  $exportItems = $exports->map(function ($export) {
    return [
      'id'=>$export->id,
      'requested'=>$export->created_at?->translatedFormat('M d, Y · h:i a'),
      'status'=>$export->status,
      'expires'=>$export->expires_at?->translatedFormat('M d, Y'),
      'downloadUrl'=>$export->isDownloadable() ? URL::temporarySignedRoute('customer.data-export.download', now()->addMinutes(config('exports.download_link_minutes',15)), ['export'=>$export]) : null,
      'failed'=>$export->status === 'failed',
    ];
  })->values();
  $requestItems = $privacyRequests->map(fn ($item) => ['id'=>$item->id,'publicId'=>$item->public_id,'status'=>$item->status])->values();
  $props = [
    'csrf'=>csrf_token(),'exportUrl'=>route('customer.data-export.store'),'deletionUrl'=>route('customer.privacy-request.store'),
    'cancelUrl'=>$openPrivacyRequest && $openPrivacyRequest->status === 'pending' ? route('customer.privacy-request.cancel',$openPrivacyRequest) : null,
    'openRequest'=>$openPrivacyRequest ? ['publicId'=>$openPrivacyRequest->public_id,'status'=>$openPrivacyRequest->status] : null,
    'exports'=>$exportItems,'requests'=>$requestItems,'errors'=>$errors->toArray(),
  ];
@endphp
<div data-tica-react="privacy" data-props='@json($props)'></div>
@endsection
