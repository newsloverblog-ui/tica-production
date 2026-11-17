@extends('backend.layout')

@section('content')
  @php
    $isSuperAdmin = is_null($roleInfo ?? null);
    $permissions = $isSuperAdmin ? [] : (array) json_decode($roleInfo->permissions ?? '[]', true);
    $can = fn (string $permission) => $isSuperAdmin || in_array($permission, $permissions, true);
  @endphp

  <div class="as-page">
    <header class="as-head">
      <div>
        <p>{{ __('Settings') }}</p>
        <h1>{{ __('Control center') }}</h1>
        <span>{{ __('Configure the platform, connected services, communication and admin access from one place.') }}</span>
      </div>
      <a class="as-site" href="{{ route('index') }}" target="_blank" rel="noopener">
        {{ __('View website') }} <i class="fal fa-external-link" aria-hidden="true"></i>
      </a>
    </header>

    <section class="as-section" aria-labelledby="as-platform">
      <div class="as-section-head">
        <h2 id="as-platform">{{ __('Platform and website') }}</h2>
        <p>{{ __('How TICA works and what visitors see.') }}</p>
      </div>
      <div class="as-grid">
        <a class="as-card" href="{{ route('admin.tica.platform') }}">
          <i class="fal fa-sliders-h" aria-hidden="true"></i>
          <span><b>{{ __('Platform settings') }}</b><small>{{ __('Fees, checkout, event features and organizer sign-up') }}</small></span>
          <i class="fal fa-chevron-right" aria-hidden="true"></i>
        </a>
        <a class="as-card" href="{{ route('admin.basic_settings.general_settings') }}">
          <i class="fal fa-palette" aria-hidden="true"></i>
          <span><b>{{ __('Brand and contact') }}</b><small>{{ __('Logo, site name, contact details, time zone and currency') }}</small></span>
          <i class="fal fa-chevron-right" aria-hidden="true"></i>
        </a>
        <a class="as-card" href="{{ route('admin.tica.website', ['tab' => 'home']) }}">
          <i class="fal fa-globe" aria-hidden="true"></i>
          <span><b>{{ __('Website content') }}</b><small>{{ __('Home page, navigation, footer, pages, blog and FAQs') }}</small></span>
          <i class="fal fa-chevron-right" aria-hidden="true"></i>
        </a>
        <a class="as-card" href="{{ route('admin.basic_settings.seo', ['language' => $defaultLang->code]) }}">
          <i class="fal fa-search" aria-hidden="true"></i>
          <span><b>{{ __('Search engines') }}</b><small>{{ __('Descriptions and metadata used by search engines') }}</small></span>
          <i class="fal fa-chevron-right" aria-hidden="true"></i>
        </a>
      </div>
    </section>

    <section class="as-section" aria-labelledby="as-services">
      <div class="as-section-head">
        <h2 id="as-services">{{ __('Services and communication') }}</h2>
        <p>{{ __('Payments, email, sign-in and customer messages.') }}</p>
      </div>
      <div class="as-grid">
        @if ($isSuperAdmin)
          <a class="as-card" href="{{ route('admin.integrations.index') }}">
            <i class="fal fa-plug" aria-hidden="true"></i>
            <span><b>{{ __('Integrations') }}</b><small>{{ __('Paystack, payouts, email, SMS, sign-in and storage') }}</small></span>
            <i class="fal fa-chevron-right" aria-hidden="true"></i>
          </a>
        @elseif ($can('Payment Gateways'))
          <a class="as-card" href="{{ route('admin.payment_gateways.online_gateways') }}">
            <i class="fal fa-credit-card" aria-hidden="true"></i>
            <span><b>{{ __('Payment methods') }}</b><small>{{ __('Payment providers available at checkout') }}</small></span>
            <i class="fal fa-chevron-right" aria-hidden="true"></i>
          </a>
        @endif
        <a class="as-card" href="{{ route('admin.basic_settings.mail_templates') }}">
          <i class="fal fa-envelope-open-text" aria-hidden="true"></i>
          <span><b>{{ __('Email templates') }}</b><small>{{ __('Tickets, receipts, reminders and account messages') }}</small></span>
          <i class="fal fa-chevron-right" aria-hidden="true"></i>
        </a>
        @if ($can('Push Notification'))
          <a class="as-card" href="{{ route('admin.user_management.push_notification.settings') }}">
            <i class="fal fa-bell" aria-hidden="true"></i>
            <span><b>{{ __('Push notifications') }}</b><small>{{ __('Browser notification setup and campaigns') }}</small></span>
            <i class="fal fa-chevron-right" aria-hidden="true"></i>
          </a>
        @endif
        <a class="as-card" href="{{ route('admin.basic_settings.maintenance_mode') }}">
          <i class="fal fa-tools" aria-hidden="true"></i>
          <span><b>{{ __('Maintenance mode') }}</b><small>{{ __('Temporarily pause the public website while work is completed') }}</small></span>
          <i class="fal fa-chevron-right" aria-hidden="true"></i>
        </a>
      </div>
    </section>

    <section class="as-section" aria-labelledby="as-access">
      <div class="as-section-head">
        <h2 id="as-access">{{ __('Access and operations') }}</h2>
        <p>{{ __('Admin permissions, language and operational tools.') }}</p>
      </div>
      <div class="as-grid">
        @if ($can('Admin Management'))
          <a class="as-card" href="{{ route('admin.admin_management.registered_admins') }}">
            <i class="fal fa-user-shield" aria-hidden="true"></i>
            <span><b>{{ __('Admins and roles') }}</b><small>{{ __('Admin accounts, roles and permission boundaries') }}</small></span>
            <i class="fal fa-chevron-right" aria-hidden="true"></i>
          </a>
        @endif
        @if ($can('Language Management'))
          <a class="as-card" href="{{ route('admin.language_management') }}">
            <i class="fal fa-language" aria-hidden="true"></i>
            <span><b>{{ __('Languages') }}</b><small>{{ __('Public and admin interface translations') }}</small></span>
            <i class="fal fa-chevron-right" aria-hidden="true"></i>
          </a>
        @endif
        @if ($can('PWA Settings'))
          <a class="as-card" href="{{ route('admin.pwa.scanner') }}">
            <i class="fal fa-mobile-alt" aria-hidden="true"></i>
            <span><b>{{ __('Gate scanner app') }}</b><small>{{ __('Name and installable app icons for gate teams') }}</small></span>
            <i class="fal fa-chevron-right" aria-hidden="true"></i>
          </a>
        @endif
        @if ($isSuperAdmin)
          <a class="as-card as-card--attention" href="{{ route('admin.database.index') }}">
            <i class="fal fa-server" aria-hidden="true"></i>
            <span><b>{{ __('System updates') }}</b><small>{{ __('Temporary cPanel database recovery until deploy automation replaces it') }}</small></span>
            <i class="fal fa-chevron-right" aria-hidden="true"></i>
          </a>
        @endif
      </div>
    </section>
  </div>
@endsection
