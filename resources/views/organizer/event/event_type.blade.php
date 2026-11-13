@extends('organizer.layout')

@section('content')
  @include('shared.event-type-selector', [
    'venueUrl' => route('organizer.add.event.event', ['type' => 'venue']),
    'onlineUrl' => route('organizer.add.event.event', ['type' => 'online']),
  ])
@endsection
