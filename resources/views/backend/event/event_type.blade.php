@extends('backend.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Create Event') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home"><a href="{{ route('admin.dashboard') }}"><i class="flaticon-home"></i></a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="{{ route('admin.event_management.event', ['language' => $defaultLang->code]) }}">{{ __('Event Management') }}</a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="#">{{ __('Choose Event Type') }}</a></li>
    </ul>
  </div>

  @include('shared.event-type-selector', [
    'venueUrl' => route('add.event.event', ['type' => 'venue']),
    'onlineUrl' => route('add.event.event', ['type' => 'online']),
  ])
@endsection
