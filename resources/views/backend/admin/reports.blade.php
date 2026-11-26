@extends('backend.layout')

@php
  $admin = Auth::guard('admin')->user();
  $can = fn (string ...$permissions) => $admin?->hasPermission(...$permissions) ?? false;
@endphp

@section('content')
  <div class="cd">
    <header class="cd-top"><h1>{{ __('Reports') }}</h1></header>
    @include('backend.admin.dashboard-reports')
  </div>
@endsection
