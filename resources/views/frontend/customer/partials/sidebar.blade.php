<div class="col-lg-3">
  <div class="user-sidebar">
    <ul class="links">
      <li><a href="{{ url('/account') }}">{{ __('Account overview') }}</a></li>
      <li><a href="{{ url('/account#orders') }}" class="@if (request()->routeIs('customer.booking.my_booking') || request()->routeIs('customer.booking_details')) active @endif">
          {{ __('Event Bookings') }} </a></li>
      <li><a href="{{ route('customer.my_orders') }}"
          class="
            @if (request()->routeIs('customer.my_orders')) active
            @elseif (request()->routeIs('customer.order_details')) active @endif
            ">
          {{ __('Product Orders') }} </a></li>

      <li><a href="{{ url('/account#saved') }}">{{ __('Saved events') }}</a></li>

      <li><a href="{{ route('customer.support_tickert') }}"
          class="@if (request()->routeIs('customer.support_tickert')) active
            @elseif(request()->routeIs('customer.support_tickert.create')) active
            @elseif(request()->routeIs('customer.support_ticket.message')) active @endif">
          {{ __('Support Tickets') }}</a></li>
      <li><a href="{{ url('/account#profile') }}">{{ __('Profile and security') }}</a></li>
      <li><a href="{{ route('customer.data-export.index') }}"
          class="@if (request()->routeIs('customer.data-export.*')) active @endif">{{ __('My Data') }}</a></li>
      <li>
        <form method="POST" action="{{ route('customer.logout') }}">
          @csrf
          <button type="submit" class="customer-sidebar-action">{{ __('Logout') }}</button>
        </form>
      </li>
    </ul>
  </div>
</div>
