<div class="sidebar sidebar-style-2"
    data-background-color="white">
    <div class="sidebar-wrapper scrollbar scrollbar-inner">
        <div class="sidebar-content">
            @php
                $adminUser = Auth::guard('admin')->user();
                $adminDisplayName = trim(($adminUser->first_name ?? '') . ' ' . ($adminUser->last_name ?? ''));
                $adminDisplayName = $adminDisplayName !== '' ? $adminDisplayName : ($adminUser->username ?? __('Administrator'));
                $adminNameParts = preg_split('/\s+/', $adminDisplayName, -1, PREG_SPLIT_NO_EMPTY);
                $adminInitials = '';
                foreach (array_slice($adminNameParts ?: [], 0, 2) as $adminNamePart) {
                    $adminInitials .= mb_strtoupper(mb_substr($adminNamePart, 0, 1));
                }
                $adminRoleLabel = is_null($roleInfo) ? __('Super Admin') : $roleInfo->name;
                $adminAvatarPath = !empty($adminUser->image)
                    ? 'assets/admin/img/admins/' . $adminUser->image
                    : null;
            @endphp

            <details class="tica-admin-account{{ request()->routeIs('admin.edit_profile', 'admin.change_password') ? ' is-current' : '' }}">
                <summary aria-label="{{ __('Open admin account menu') }}">
                    <span class="tica-admin-account-avatar" aria-hidden="true">
                        <span>{{ $adminInitials ?: 'TA' }}</span>
                        @if ($adminAvatarPath)
                            <img src="{{ asset($adminAvatarPath) }}" alt=""
                                onerror="this.remove()">
                        @endif
                    </span>
                    <span class="tica-admin-account-copy">
                        <strong>{{ $adminDisplayName }}</strong>
                        <small>{{ $adminRoleLabel }}</small>
                    </span>
                    <svg class="tica-admin-account-chevron" viewBox="0 0 20 20" aria-hidden="true">
                        <path d="m6 8 4 4 4-4"></path>
                    </svg>
                </summary>

                <div class="tica-admin-account-menu">
                    <a href="{{ route('admin.edit_profile') }}">
                        <i class="fal fa-user-edit" aria-hidden="true"></i>
                        <span>{{ __('Edit profile') }}</span>
                    </a>
                    <a href="{{ route('admin.change_password') }}">
                        <i class="fal fa-lock" aria-hidden="true"></i>
                        <span>{{ __('Change password') }}</span>
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit">
                            <i class="fal fa-sign-out-alt" aria-hidden="true"></i>
                            <span>{{ __('Log out') }}</span>
                        </button>
                    </form>
                </div>
            </details>

            <form class="tica-admin-sidebar-search" action="" onsubmit="return false" role="search">
                <label class="sr-only" for="tica-admin-section-search">{{ __('Find an admin section') }}</label>
                <i class="fal fa-search" aria-hidden="true"></i>
                <input id="tica-admin-section-search" name="term" type="search" class="form-control sidebar-search ltr"
                    placeholder="{{ __('Find a section') }}" autocomplete="off">
            </form>

            @php
                if (!is_null($roleInfo)) {
                    $rolePermissions = json_decode($roleInfo->permissions);
                }
            @endphp

            <ul class="nav nav-primary">
                <li class="nav-section"><span class="text-section">{{ __('General') }}</span></li>
                {{-- dashboard --}}
                <li class="nav-item @if (request()->routeIs('admin.dashboard')) active @endif">
                    <a href="{{ route('admin.dashboard') }}">
                        <i class="la flaticon-paint-palette"></i>
                        <p>{{ __('Dashboard') }}</p>
                    </a>
                </li>

                @if (is_null($roleInfo) || (!empty($rolePermissions) && in_array('Advertise', $rolePermissions)))
                    <li class="nav-item @if (request()->routeIs('admin.advertise.tica_promotions*')) active @endif">
                        <a href="{{ route('admin.advertise.tica_promotions') }}">
                            <i class="fal fa-bullhorn"></i>
                            <p>{{ __('Promotions') }}</p>
                        </a>
                    </li>
                @endif

                <li class="nav-section"><span class="text-section">{{ __('Events and sales') }}</span></li>
                {{-- event --}}
                @if (is_null($roleInfo) || (!empty($rolePermissions) && in_array('Event Management', $rolePermissions)))
                    <li
                        class="nav-item @if (request()->routeIs('admin.event_management.categories')) active
            @elseif (request()->routeIs('add.event.event')) active
            @elseif (request()->routeIs('admin.choose-event-type')) active
            @elseif (request()->routeIs('admin.event_management.event')) active
            @elseif (request()->routeIs('admin.event_management.edit_event')) active
            @elseif (request()->routeIs('admin.event.ticket')) active
            @elseif (request()->routeIs('admin.event.add.ticket')) active
            @elseif (request()->routeIs('admin.event.edit.ticket')) active @endif">
                        <a data-toggle="collapse" href="#event">
                            <i class="fal fa-book"></i>
                            <p>{{ __('Event Management') }}</p>
                            <span class="caret"></span>
                        </a>

                        <div id="event"
                            class="collapse
              @if (request()->routeIs('admin.event_management.categories')) show
              @elseif (request()->routeIs('admin.choose-event-type')) show
              @elseif (request()->routeIs('add.event.event')) show
              @elseif (request()->routeIs('admin.event_management.event')) show
              @elseif (request()->routeIs('admin.event_management.edit_event')) show
              @elseif (request()->routeIs('admin.event.ticket')) show
              @elseif (request()->routeIs('admin.event.add.ticket')) show
              @elseif (request()->routeIs('admin.event.edit.ticket')) show @endif">
                            <ul class="nav nav-collapse">
                                <li
                                    class="{{ request()->routeIs('admin.event_management.categories') ? 'active' : '' }}">
                                    <a
                                        href="{{ route('admin.event_management.categories', ['language' => $defaultLang->code]) }}">
                                        <span class="sub-item">{{ __('Event Categories') }}</span>
                                    </a>
                                </li>

                                <li class="submenu">
                                    <a data-toggle="collapse" href="#EventsManagement"
                                        aria-expanded="{{ request()->routeIs('admin.choose-event-type') ||
                                        request()->routeIs('add.event.event') ||
                                        request()->routeIs('admin.event_management.event') ||
                                        request()->routeIs('admin.event_management.edit_event') ||
                                        request()->routeIs('admin.event.ticket') ||
                                        request()->routeIs('admin.event.add.ticket') ||
                                        request()->routeIs('admin.event.edit.ticket')
                                            ? 'true'
                                            : 'false' }}">
                                        <span class="sub-item">{{ __('Event Management') }}</span>
                                        <span class="caret"></span>
                                    </a>
                                    <div class="collapse
                    @if (request()->routeIs('admin.choose-event-type')) show
                    @elseif(request()->routeIs('add.event.event')) show
                    @elseif(request()->routeIs('admin.event_management.event')) show
                    @elseif(request()->routeIs('admin.event_management.edit_event')) show
                    @elseif(request()->routeIs('admin.event.ticket')) show
                    @elseif(request()->routeIs('admin.event.add.ticket')) show
                    @elseif(request()->routeIs('admin.event.edit.ticket')) show @endif"
                                        id="EventsManagement">
                                        <ul class="nav nav-collapse subnav">
                                            <li
                                                class=" @if (request()->routeIs('admin.choose-event-type')) active
                        @elseif (request()->routeIs('add.event.event')) active @endif ">
                                                <a
                                                    href="{{ route('admin.choose-event-type', ['language' => $defaultLang->code]) }}">
                                                    <span class="sub-item">{{ __('Add Event') }}</span>
                                                </a>
                                            </li>

                                            <li
                                                class="@if (request()->routeIs('admin.event_management.event') && request()->input('event_type') == '') active
                        @elseif (request()->routeIs('admin.event_management.edit_event') && request()->input('event_type') == '') active 
                        @elseif (request()->routeIs('admin.event.ticket') && request()->input('event_type') == '') active
                        @elseif (request()->routeIs('admin.event.add.ticket') && request()->input('event_type') == '') active
                        @elseif (request()->routeIs('admin.event.edit.ticket') && request()->input('event_type') == '') active @endif">
                                                <a
                                                    href="{{ route('admin.event_management.event', ['language' => $defaultLang->code]) }}">
                                                    <span class="sub-item">{{ __('All Events') }}</span>
                                                </a>
                                            </li>

                                            <li
                                                class="@if (request()->routeIs('admin.event_management.event') && request()->input('event_type') == 'venue') active 
                        @elseif (request()->routeIs('admin.add.event.event') && request()->input('type') == 'venue') active
                        @elseif (request()->routeIs('admin.event.ticket') && request()->input('event_type') == 'venue') active 
                        @elseif (request()->routeIs('admin.event.add.ticket') && request()->input('event_type') == 'venue') active
                        @elseif (request()->routeIs('admin.event.edit.ticket') && request()->input('event_type') == 'venue') active @endif">
                                                <a
                                                    href="{{ route('admin.event_management.event', ['language' => $defaultLang->code, 'event_type' => 'venue']) }}">
                                                    <span class="sub-item">{{ __('Venue Events') }}</span>
                                                </a>
                                            </li>

                                            <li
                                                class="@if (request()->routeIs('admin.event_management.event') && request()->input('event_type') == 'online') active 
                        @elseif (request()->routeIs('admin.add.event.event') && request()->input('type') == 'online') active @endif ">
                                                <a
                                                    href="{{ route('admin.event_management.event', ['language' => $defaultLang->code, 'event_type' => 'online']) }}">
                                                    <span class="sub-item">{{ __('Online Events') }}</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </li>
                @endif

                {{-- event booking --}}
                @if (is_null($roleInfo) || (!empty($rolePermissions) && in_array('Event Bookings', $rolePermissions)))
                    <li
                        class="nav-item
          @if (request()->routeIs('admin.event.booking')) active
          @elseif (request()->routeIs('admin.event_booking.details')) active  
          @elseif (request()->routeIs('admin.event_management.coupons')) active  
          @elseif (request()->routeIs('admin.event_booking.settings.guest_checkout')) active  
          @elseif (request()->routeIs('admin.event_booking.settings.tax_commission')) active  
          @elseif (request()->routeIs('admin.event_booking.report')) active @endif">
                        <a data-toggle="collapse" href="#event_bookings">
                            <i class="fal fa-users-class"></i>
                            <p>{{ __('Event Bookings') }}</p>
                            <span class="caret"></span>
                        </a>

                        <div id="event_bookings"
                            class="collapse
            @if (request()->routeIs('admin.event_management.coupons')) show 
            @elseif (request()->routeIs('admin.event_booking.settings.guest_checkout')) show 
            @elseif (request()->routeIs('admin.event.booking')) show 
            @elseif (request()->routeIs('admin.event_booking.details')) show 
            @elseif (request()->routeIs('admin.event_booking.report')) show 
            @elseif (request()->routeIs('admin.event_booking.settings.tax_commission')) show @endif">
                            <ul class="nav nav-collapse">

                                <li class="submenu">
                                    <a data-toggle="collapse" href="#EventsSettings"
                                        aria-expanded="{{ request()->routeIs('admin.event_management.coupons') || request()->routeIs('admin.event_booking.settings.tax_commission') || request()->routeIs('admin.event_booking.settings.guest_checkout') ? 'true' : 'false' }}">
                                        <span class="sub-item">{{ __('Settings') }}</span>
                                        <span class="caret"></span>
                                    </a>
                                    <div class="collapse
                    @if (request()->routeIs('admin.event_management.coupons')) show 
                    @elseif (request()->routeIs('admin.event_booking.settings.guest_checkout')) show 
                    @elseif (request()->routeIs('admin.event_booking.settings.tax_commission')) show @endif"
                                        id="EventsSettings">
                                        <ul class="nav nav-collapse subnav">
                                            <li
                                                class="{{ request()->routeIs('admin.event_booking.settings.guest_checkout') ? 'active' : '' }}">
                                                <a href="{{ route('admin.tica.platform') }}#ta-fees">
                                                    <span class="sub-item">{{ __('Guest Checkout') }}</span>
                                                </a>
                                            </li>
                                            <li
                                                class="{{ request()->routeIs('admin.event_management.coupons') ? 'active' : '' }}">
                                                <a href="{{ route('admin.event_management.coupons') }}">
                                                    <span class="sub-item">{{ __('Coupons') }}</span>
                                                </a>
                                            </li>
                                            <li
                                                class="{{ request()->routeIs('admin.event_booking.settings.tax_commission') ? 'active' : '' }}">
                                                <a href="{{ route('admin.tica.platform') }}#ta-fees">
                                                    <span class="sub-item">{{ __('Booking Fee') }}</span>
                                                </a>
                                            </li>

                                        </ul>
                                    </div>
                                </li>

                                <li
                                    class="
                  @if (request()->routeIs('admin.event.booking') && empty(request()->input('status'))) active
                  @elseif (request()->routeIs('admin.event_booking.details')) active @endif">
                                    <a href="{{ route('admin.event.booking') }}">
                                        <span class="sub-item">{{ __('All Bookings') }}</span>
                                    </a>
                                </li>

                                <li
                                    class="{{ request()->routeIs('admin.event.booking') && request()->input('status') == 'completed' ? 'active' : '' }}">
                                    <a href="{{ route('admin.event.booking', ['status' => 'completed']) }}">
                                        <span class="sub-item">{{ __('Completed Bookings') }}</span>
                                    </a>
                                </li>

                                <li
                                    class="{{ request()->routeIs('admin.event.booking') && request()->input('status') == 'pending' ? 'active' : '' }}">
                                    <a href="{{ route('admin.event.booking', ['status' => 'pending']) }}">
                                        <span class="sub-item">{{ __('Pending Bookings') }}</span>
                                    </a>
                                </li>

                                <li
                                    class="{{ request()->routeIs('admin.event.booking') && request()->input('status') == 'rejected' ? 'active' : '' }}">
                                    <a href="{{ route('admin.event.booking', ['status' => 'rejected']) }}">
                                        <span class="sub-item">{{ __('Rejected Bookings') }}</span>
                                    </a>
                                </li>

                                <li class="{{ request()->routeIs('admin.event_booking.report') ? 'active' : '' }}">
                                    <a href="{{ route('admin.event_booking.report') }}">
                                        <span class="sub-item">{{ __('Report') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                @endif

                <li class="nav-section"><span class="text-section">{{ __('Money') }}</span></li>
                {{-- Withdraw Method --}}
                @if (is_null($roleInfo) || (!empty($rolePermissions) && in_array('Withdraw Method', $rolePermissions)))
                    <li
                        class="nav-item
          @if (request()->routeIs('admin.withdraw.payment_method')) active
          @elseif (request()->routeIs('admin.withdraw.payment_method')) active
          @elseif (request()->routeIs('admin.withdraw_payment_method.mange_input')) active
          @elseif (request()->routeIs('admin.withdraw_payment_method.edit_input')) active
          @elseif (request()->routeIs('admin.withdraw.withdraw_request')) active @endif">
                        <a data-toggle="collapse" href="#withdraw_method">
                            <i class="fas fa-credit-card"></i>
                            <p>{{ __('Withdraw Method') }}</p>
                            <span class="caret"></span>
                        </a>

                        <div id="withdraw_method"
                            class="collapse
            @if (request()->routeIs('admin.withdraw.payment_method')) show
            @elseif (request()->routeIs('admin.withdraw.payment_method')) show
            @elseif (request()->routeIs('admin.withdraw_payment_method.mange_input')) show 
            @elseif (request()->routeIs('admin.withdraw_payment_method.edit_input')) show 
            @elseif (request()->routeIs('admin.withdraw.withdraw_request')) show @endif">
                            <ul class="nav nav-collapse">
                                <li
                                    class="@if (request()->routeIs('admin.withdraw.payment_method')) active 
                  @elseif (request()->routeIs('admin.withdraw_payment_method.mange_input')) active 
                  @elseif (request()->routeIs('admin.withdraw_payment_method.edit_input')) active @endif">
                                    <a
                                        href="{{ route('admin.withdraw.payment_method', ['language' => $defaultLang->code]) }}">
                                        <span class="sub-item">{{ __('Payment Methods') }}</span>
                                    </a>
                                </li>

                                <li
                                    class="{{ request()->routeIs('admin.withdraw.withdraw_request') && empty(request()->input('status')) ? 'active' : '' }}">
                                    <a
                                        href="{{ route('admin.withdraw.withdraw_request', ['language' => $defaultLang->code]) }}">
                                        <span class="sub-item">{{ __('Withdraw Requests') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                @endif

                {{-- Transaction --}}
                @if (is_null($roleInfo) || (!empty($rolePermissions) && in_array('Transaction', $rolePermissions)))
                    <li class="nav-item @if (request()->routeIs('admin.transcation') || request()->routeIs('admin.funnel_analytics.*') || request()->routeIs('admin.payment_reconciliation.*') || request()->routeIs('admin.settlement_reconciliation.*') || request()->routeIs('admin.reconciliation_exports.*') || request()->routeIs('admin.operational_incidents.*') || request()->routeIs('admin.webhook_operations.*') || request()->routeIs('admin.operations_exports.*') || request()->routeIs('admin.audit_logs.*')) active @endif">
                        <a data-toggle="collapse" href="#platformOperations">
                            <i class="fal fa-chart-pie"></i>
                            <p>{{ __('Operations & Finance') }}</p>
                            <span class="caret"></span>
                        </a>
                        <div id="platformOperations" class="collapse @if (request()->routeIs('admin.transcation') || request()->routeIs('admin.funnel_analytics.*') || request()->routeIs('admin.payment_reconciliation.*') || request()->routeIs('admin.settlement_reconciliation.*') || request()->routeIs('admin.reconciliation_exports.*') || request()->routeIs('admin.operational_incidents.*') || request()->routeIs('admin.webhook_operations.*') || request()->routeIs('admin.operations_exports.*') || request()->routeIs('admin.audit_logs.*')) show @endif">
                            <ul class="nav nav-collapse">
                                <li class="{{ request()->routeIs('admin.transcation') ? 'active' : '' }}"><a href="{{ route('admin.transcation') }}"><span class="sub-item">{{ __('Transactions') }}</span></a></li>
                                <li class="{{ request()->routeIs('admin.funnel_analytics.*') ? 'active' : '' }}"><a href="{{ route('admin.funnel_analytics.index') }}"><span class="sub-item">{{ __('Checkout analytics') }}</span></a></li>
                                <li class="{{ request()->routeIs('admin.payment_reconciliation.*') ? 'active' : '' }}"><a href="{{ route('admin.payment_reconciliation.index') }}"><span class="sub-item">{{ __('Payment reconciliation') }}</span></a></li>
                                <li class="{{ request()->routeIs('admin.settlement_reconciliation.*') ? 'active' : '' }}"><a href="{{ route('admin.settlement_reconciliation.index') }}"><span class="sub-item">{{ __('Settlement reconciliation') }}</span></a></li>
                                <li class="{{ request()->routeIs('admin.reconciliation_exports.*') ? 'active' : '' }}"><a href="{{ route('admin.reconciliation_exports.index') }}"><span class="sub-item">{{ __('Reconciliation exports') }}</span></a></li>
                                <li class="{{ request()->routeIs('admin.operational_incidents.*') ? 'active' : '' }}"><a href="{{ route('admin.operational_incidents.index') }}"><span class="sub-item">{{ __('Incidents') }}</span></a></li>
                                <li class="{{ request()->routeIs('admin.webhook_operations.*') ? 'active' : '' }}"><a href="{{ route('admin.webhook_operations.index') }}"><span class="sub-item">{{ __('Webhook delivery') }}</span></a></li>
                                <li class="{{ request()->routeIs('admin.audit_logs.*') ? 'active' : '' }}"><a href="{{ route('admin.audit_logs.index') }}"><span class="sub-item">{{ __('Audit trail') }}</span></a></li>
                                <li class="{{ request()->routeIs('admin.operations_exports.*') ? 'active' : '' }}"><a href="{{ route('admin.operations_exports.index') }}"><span class="sub-item">{{ __('Operations exports') }}</span></a></li>
                            </ul>
                        </div>
                    </li>
                @endif

                <li class="nav-section"><span class="text-section">{{ __('People') }}</span></li>
                {{-- organizer --}}
                @if (is_null($roleInfo) || (!empty($rolePermissions) && in_array('Organizer Mangement', $rolePermissions)))
                    <li
                        class="nav-item @if (request()->routeIs('admin.organizer_management.registered_organizer')) active
            @elseif (request()->routeIs('admin.organizer_management.add_organizer')) active
            @elseif (request()->routeIs('admin.organizer_management.organizer_details')) active
            @elseif (request()->routeIs('admin.edit_management.organizer_edit')) active
            @elseif (request()->routeIs('admin.organizer_management.organizer.change_password')) active 
            @elseif (request()->routeIs('admin.organizer_management.settings')) active @endif">
                        <a data-toggle="collapse" href="#organizer">
                            <i class="la flaticon-users"></i>
                            <p>{{ __('Organizers Management') }}</p>
                            <span class="caret"></span>
                        </a>

                        <div id="organizer"
                            class="collapse
              @if (request()->routeIs('admin.organizer_management.registered_organizer')) show
              @elseif (request()->routeIs('admin.organizer_management.organizer_details')) show
              @elseif (request()->routeIs('admin.edit_management.organizer_edit')) show
              @elseif (request()->routeIs('admin.organizer_management.add_organizer')) show
              @elseif (request()->routeIs('admin.organizer_management.organizer.change_password')) show 
              @elseif (request()->routeIs('admin.organizer_management.settings')) show @endif">
                            <ul class="nav nav-collapse">
                                <li class="@if (request()->routeIs('admin.organizer_management.settings')) active @endif">
                                    <a href="{{ route('admin.organizer_management.settings') }}">
                                        <span class="sub-item">{{ __('Settings') }}</span>
                                    </a>
                                </li>
                                <li
                                    class="@if (request()->routeIs('admin.organizer_management.registered_organizer')) active
                  @elseif (request()->routeIs('admin.organizer_management.organizer_details')) active
                  @elseif (request()->routeIs('admin.edit_management.organizer_edit')) active
                  @elseif (request()->routeIs('admin.organizer_management.organizer.change_password')) active @endif">
                                    <a href="{{ route('admin.organizer_management.registered_organizer') }}">
                                        <span class="sub-item">{{ __('Registered Organizers') }}</span>
                                    </a>
                                </li>
                                <li class="@if (request()->routeIs('admin.organizer_management.add_organizer')) active @endif">
                                    <a href="{{ route('admin.organizer_management.add_organizer') }}">
                                        <span class="sub-item">{{ __('Add Organizer') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                @endif

                {{-- customer --}}
                @if (is_null($roleInfo) || (!empty($rolePermissions) && in_array('Customer Management', $rolePermissions)))
                    <li
                        class="nav-item @if (request()->routeIs('admin.organizer_management.registered_customer')) active
            @elseif (request()->routeIs('admin.customer_management.customer_edit')) active
            @elseif (request()->routeIs('admin.customer_management.customer_details')) active
            @elseif (request()->routeIs('admin.customer_management.customer.change_password')) active 
            @elseif (request()->routeIs('admin.organizer_management.add_customer')) active @endif">
                        <a data-toggle="collapse" href="#customer">
                            <i class="fas fa-users"></i>
                            <p>{{ __('Customers Management') }}</p>
                            <span class="caret"></span>
                        </a>
                        <div id="customer"
                            class="collapse
              @if (request()->routeIs('admin.organizer_management.registered_customer')) show
              @elseif (request()->routeIs('admin.customer_management.customer_details')) show
              @elseif (request()->routeIs('admin.customer_management.customer_edit')) show
              @elseif (request()->routeIs('admin.customer_management.customer.change_password')) show 
              @elseif (request()->routeIs('admin.organizer_management.add_customer')) show @endif">
                            <ul class="nav nav-collapse">
                                <li
                                    class="@if (request()->routeIs('admin.organizer_management.registered_customer')) active
                  @elseif (request()->routeIs('admin.customer_management.customer_details')) active
                  @elseif (request()->routeIs('admin.customer_management.customer_edit')) active
                  @elseif (request()->routeIs('admin.customer_management.customer.change_password')) active @endif">
                                    <a href="{{ route('admin.organizer_management.registered_customer') }}">
                                        <span class="sub-item">{{ __('Registered Customers') }}</span>
                                    </a>
                                </li>
                                <li class="@if (request()->routeIs('admin.organizer_management.add_customer')) active @endif">
                                    <a href="{{ route('admin.organizer_management.add_customer') }}">
                                        <span class="sub-item">{{ __('Add Customer') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                @endif

                {{-- customer --}}
                @if (is_null($roleInfo) || (!empty($rolePermissions) && in_array('Support Ticket', $rolePermissions)))
                    <li
                        class="nav-item @if (request()->routeIs('admin.support_ticket.setting')) active
            @elseif (request()->routeIs('admin.support_tickets')) active
            @elseif (request()->routeIs('admin.support_tickets.message')) active @endif">
                        <a data-toggle="collapse" href="#support_ticket">
                            <i class="la flaticon-web-1"></i>
                            <p>{{ __('Help Desk') }}</p>
                            <span class="caret"></span>
                        </a>

                        <div id="support_ticket"
                            class="collapse
              @if (request()->routeIs('admin.support_ticket.setting')) show
              @elseif (request()->routeIs('admin.support_tickets')) show
              @elseif (request()->routeIs('admin.support_tickets.message')) show @endif">
                            <ul class="nav nav-collapse">
                                <li class="@if (request()->routeIs('admin.support_ticket.setting')) active @endif">
                                    <a href="{{ route('admin.support_ticket.setting') }}">
                                        <span class="sub-item">{{ __('Settings') }}</span>
                                    </a>
                                </li>
                                <li
                                    class="
                  @if (request()->routeIs('admin.support_tickets') && empty(request()->input('status'))) active
                  @elseif(request()->routeIs('admin.support_tickets.message')) active @endif ">
                                    <a href="{{ route('admin.support_tickets') }}">
                                        <span class="sub-item">{{ __('All Tickets') }}</span>
                                    </a>
                                </li>
                                <li
                                    class="{{ request()->routeIs('admin.support_tickets') && request()->input('status') == 1 ? 'active' : '' }}">
                                    <a href="{{ route('admin.support_tickets', ['status' => 1]) }}">
                                        <span class="sub-item">{{ __('Help Desk Messages') }}</span>
                                    </a>
                                </li>
                                <li
                                    class="{{ request()->routeIs('admin.support_tickets') && request()->input('status') == 2 ? 'active' : '' }}">
                                    <a href="{{ route('admin.support_tickets', ['status' => 2]) }}">
                                        <span class="sub-item">{{ __('Open Help') }}</span>
                                    </a>
                                </li>
                                <li
                                    class="{{ request()->routeIs('admin.support_tickets') && request()->input('status') == 3 ? 'active' : '' }}">
                                    <a href="{{ route('admin.support_tickets', ['status' => 3]) }}">
                                        <span class="sub-item">{{ __('Closed Help Msg') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                @endif


                <li class="nav-section"><span class="text-section">{{ __('Website') }}</span></li>
                {{-- website: every control that changes the public TICA pages --}}
                @php
                  $siteAreas = ['Home Page', 'Menu Builder', 'Footer', 'Custom Pages', 'Blog Management', 'FAQ Management', 'Contact Page'];
                  $canSite = is_null($roleInfo) || (!empty($rolePermissions) && array_intersect($siteAreas, $rolePermissions) !== []);
                  $canArea = fn ($area) => is_null($roleInfo) || (!empty($rolePermissions) && in_array($area, $rolePermissions));
                  $siteOpen = request()->routeIs('admin.tica.website', 'admin.custom_pages', 'admin.custom_pages.*', 'admin.blog_management.*', 'admin.faq_management');
                  $siteTab = request()->routeIs('admin.tica.website') ? request('tab', 'home') : null;
                @endphp
                @if ($canSite)
                    <li class="nav-item @if ($siteOpen) active submenu @endif">
                        <a data-toggle="collapse" href="#tica_website" aria-expanded="{{ $siteOpen ? 'true' : 'false' }}">
                            <i class="fal fa-globe"></i>
                            <p>{{ __('Website') }}</p>
                            <span class="caret"></span>
                        </a>
                        <div id="tica_website" class="collapse @if ($siteOpen) show @endif">
                            <ul class="nav nav-collapse">
                                @if ($canArea('Home Page'))
                                    <li class="{{ $siteTab === 'home' ? 'active' : '' }}"><a href="{{ route('admin.tica.website', ['tab' => 'home']) }}"><span class="sub-item">{{ __('Home page') }}</span></a></li>
                                @endif
                                @if ($canArea('Menu Builder'))
                                    <li class="{{ $siteTab === 'menu' ? 'active' : '' }}"><a href="{{ route('admin.tica.website', ['tab' => 'menu']) }}"><span class="sub-item">{{ __('Menu') }}</span></a></li>
                                @endif
                                @if ($canArea('Footer'))
                                    <li class="{{ $siteTab === 'footer' ? 'active' : '' }}"><a href="{{ route('admin.tica.website', ['tab' => 'footer']) }}"><span class="sub-item">{{ __('Footer') }}</span></a></li>
                                @endif
                                @if ($canArea('Contact Page'))
                                    <li class="{{ $siteTab === 'contact' ? 'active' : '' }}"><a href="{{ route('admin.tica.website', ['tab' => 'contact']) }}"><span class="sub-item">{{ __('Contact details') }}</span></a></li>
                                @endif
                                @if ($canArea('Custom Pages'))
                                    <li class="{{ request()->routeIs('admin.custom_pages', 'admin.custom_pages.*') ? 'active' : '' }}"><a href="{{ route('admin.custom_pages', ['language' => $defaultLang->code]) }}"><span class="sub-item">{{ __('Pages') }}</span></a></li>
                                @endif
                                @if ($canArea('Blog Management'))
                                    <li class="{{ request()->routeIs('admin.blog_management.blogs', 'admin.blog_management.create_blog', 'admin.blog_management.edit_blog') ? 'active' : '' }}"><a href="{{ route('admin.blog_management.blogs', ['language' => $defaultLang->code]) }}"><span class="sub-item">{{ __('Blog posts') }}</span></a></li>
                                    <li class="{{ request()->routeIs('admin.blog_management.categories') ? 'active' : '' }}"><a href="{{ route('admin.blog_management.categories', ['language' => $defaultLang->code]) }}"><span class="sub-item">{{ __('Blog categories') }}</span></a></li>
                                @endif
                                @if ($canArea('FAQ Management'))
                                    <li class="{{ request()->routeIs('admin.faq_management') ? 'active' : '' }}"><a href="{{ route('admin.faq_management', ['language' => $defaultLang->code]) }}"><span class="sub-item">{{ __('Questions (FAQ)') }}</span></a></li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif
                @if (is_null($roleInfo) || (!empty($rolePermissions) && in_array('Subscribers', $rolePermissions)))
                    <li
                        class="nav-item
          @if (request()->routeIs('admin.user_management.subscribers')) active
          @elseif(request()->routeIs('admin.user_management.mail_for_subscribers')) active @endif">
                        <a data-toggle="collapse" href="#subscribers">
                            <i class="la flaticon-envelope"></i>
                            <p>{{ __('Subscribers') }}</p>
                            <span class="caret"></span>
                        </a>
                        <div class="collapse
            @if (request()->routeIs('admin.user_management.subscribers')) show
            @elseif(request()->routeIs('admin.user_management.mail_for_subscribers')) show @endif"
                            id="subscribers">
                            <ul class="nav nav-collapse">
                                <li class="@if (request()->routeIs('admin.user_management.subscribers')) active @endif">
                                    <a href="{{ route('admin.user_management.subscribers') }}">
                                        <span class="sub-item">{{ __('Subscribers') }}</span>
                                    </a>
                                </li>
                                <li class="@if (request()->routeIs('admin.user_management.mail_for_subscribers')) active @endif">
                                    <a href="{{ route('admin.user_management.mail_for_subscribers') }}">
                                        <span class="sub-item">{{ __('Mail to Subscribers') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                @endif

                @if (is_null($roleInfo) || (!empty($rolePermissions) && in_array('Push Notification', $rolePermissions)))
                    <li
                        class="nav-item @if (request()->routeIs('admin.user_management.push_notification.settings')) active 
                    @elseif (request()->routeIs('admin.user_management.push_notification.notification_for_visitors')) active @endif">
                        <a data-toggle="collapse" href="#push_notification">
                            <i class="fal fa-bell"></i>
                            <p>{{ __('Push Notification') }}</p>
                            <span class="caret"></span>
                        </a>

                        <div id="push_notification"
                            class="collapse 
              @if (request()->routeIs('admin.user_management.push_notification.settings')) show 
                    @elseif (request()->routeIs('admin.user_management.push_notification.notification_for_visitors')) show @endif">
                            <ul class="nav nav-collapse">
                                <li
                                    class="{{ request()->routeIs('admin.user_management.push_notification.settings') ? 'active' : '' }}">
                                    <a href="{{ route('admin.user_management.push_notification.settings') }}">
                                        <span class="sub-item">{{ __('Settings') }}</span>
                                    </a>
                                </li>

                                <li
                                    class="{{ request()->routeIs('admin.user_management.push_notification.notification_for_visitors') ? 'active' : '' }}">
                                    <a
                                        href="{{ route('admin.user_management.push_notification.notification_for_visitors') }}">
                                        <span class="sub-item">{{ __('Send Notification') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                @endif

                <li class="nav-section"><span class="text-section">{{ __('Settings') }}</span></li>
                {{-- payment gateway --}}
                @if (is_null($roleInfo) || (!empty($rolePermissions) && in_array('Payment Gateways', $rolePermissions)))
                    <li
                        class="nav-item @if (request()->routeIs('admin.payment_gateways.online_gateways')) active
            @elseif (request()->routeIs('admin.payment_gateways.offline_gateways')) active @endif">
                        <a data-toggle="collapse" href="#payment_gateways">
                            <i class="la flaticon-paypal"></i>
                            <p>{{ __('Payment Gateways') }}</p>
                            <span class="caret"></span>
                        </a>

                        <div id="payment_gateways"
                            class="collapse
              @if (request()->routeIs('admin.payment_gateways.online_gateways')) show
              @elseif (request()->routeIs('admin.payment_gateways.offline_gateways')) show @endif">
                            <ul class="nav nav-collapse">
                                <li
                                    class="{{ request()->routeIs('admin.payment_gateways.online_gateways') ? 'active' : '' }}">
                                    <a href="{{ route('admin.payment_gateways.online_gateways') }}">
                                        <span class="sub-item">{{ __('Online Gateways') }}</span>
                                    </a>
                                </li>

                                <li
                                    class="{{ request()->routeIs('admin.payment_gateways.offline_gateways') ? 'active' : '' }}">
                                    <a href="{{ route('admin.payment_gateways.offline_gateways') }}">
                                        <span class="sub-item">{{ __('Offline Gateways') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                @endif

                {{-- basic settings --}}
                @if (is_null($roleInfo) || (!empty($rolePermissions) && in_array('Basic Settings', $rolePermissions)))
                    <li
                        class="nav-item @if (request()->routeIs('admin.tica.platform')) active @endif @if (request()->routeIs('admin.basic_settings.general_settings')) active 
            @elseif (request()->routeIs('admin.basic_settings.mail_from_admin')) active
            @elseif (request()->routeIs('admin.basic_settings.mail_to_admin')) active
            @elseif (request()->routeIs('admin.basic_settings.mail_templates')) active
            @elseif (request()->routeIs('admin.basic_settings.edit_mail_template')) active
            @elseif (request()->routeIs('admin.basic_settings.breadcrumb')) active
            @elseif (request()->routeIs('admin.basic_settings.page_headings')) active
            @elseif (request()->routeIs('admin.basic_settings.plugins')) active
            @elseif (request()->routeIs('admin.basic_settings.seo')) active
            @elseif (request()->routeIs('admin.basic_settings.maintenance_mode')) active
            @elseif (request()->routeIs('admin.basic_settings.cookie_alert')) active
            @elseif (request()->routeIs('admin.basic_settings.footer_logo')) active
            @elseif (request()->routeIs('admin.basic_settings.social_medias')) active @endif">
                        <a data-toggle="collapse" href="#basic_settings">
                            <i class="la flaticon-settings"></i>
                            <p>{{ __('Basic Settings') }}</p>
                            <span class="caret"></span>
                        </a>

                        <div id="basic_settings"
                            class="collapse
              @if (request()->routeIs('admin.tica.platform')) show @endif
              @if (request()->routeIs('admin.basic_settings.general_settings')) show
              @elseif (request()->routeIs('admin.basic_settings.mail_from_admin')) show
              @elseif (request()->routeIs('admin.basic_settings.mail_to_admin')) show
              @elseif (request()->routeIs('admin.basic_settings.mail_templates')) show
              @elseif (request()->routeIs('admin.basic_settings.edit_mail_template')) show
              @elseif (request()->routeIs('admin.basic_settings.breadcrumb')) show
              @elseif (request()->routeIs('admin.basic_settings.page_headings')) show
              @elseif (request()->routeIs('admin.basic_settings.plugins')) show
              @elseif (request()->routeIs('admin.basic_settings.seo')) show
              @elseif (request()->routeIs('admin.basic_settings.maintenance_mode')) show
              @elseif (request()->routeIs('admin.basic_settings.cookie_alert')) show
              @elseif (request()->routeIs('admin.basic_settings.footer_logo')) show
              @elseif (request()->routeIs('admin.basic_settings.social_medias')) show @endif">
                            <ul class="nav nav-collapse">
                                <li class="{{ request()->routeIs('admin.tica.platform') ? 'active' : '' }}">
                                    <a href="{{ route('admin.tica.platform') }}">
                                        <span class="sub-item">{{ __('Platform Settings') }}</span>
                                    </a>
                                </li>

                                <li
                                    class="{{ request()->routeIs('admin.basic_settings.general_settings') ? 'active' : '' }}">
                                    <a href="{{ route('admin.basic_settings.general_settings') }}">
                                        <span class="sub-item">{{ __('General Settings') }}</span>
                                    </a>
                                </li>

                                <li class="submenu">
                                    <a data-toggle="collapse" href="#mail_settings"
                                        aria-expanded="{{ request()->routeIs('admin.basic_settings.mail_from_admin') || request()->routeIs('admin.basic_settings.mail_to_admin') || request()->routeIs('admin.basic_settings.mail_templates') || request()->routeIs('admin.basic_settings.edit_mail_template') ? 'true' : 'false' }}">
                                        <span class="sub-item">{{ __('Email Settings') }}</span>
                                        <span class="caret"></span>
                                    </a>

                                    <div id="mail_settings"
                                        class="collapse
                    @if (request()->routeIs('admin.basic_settings.mail_from_admin')) show
                    @elseif (request()->routeIs('admin.basic_settings.mail_to_admin')) show
                    @elseif (request()->routeIs('admin.basic_settings.mail_templates')) show
                    @elseif (request()->routeIs('admin.basic_settings.edit_mail_template')) show @endif">
                                        <ul class="nav nav-collapse subnav">
                                            <li
                                                class="{{ request()->routeIs('admin.basic_settings.mail_from_admin') ? 'active' : '' }}">
                                                <a href="{{ route('admin.basic_settings.mail_from_admin') }}">
                                                    <span class="sub-item">{{ __('Mail From Admin') }}</span>
                                                </a>
                                            </li>

                                            <li
                                                class="{{ request()->routeIs('admin.basic_settings.mail_to_admin') ? 'active' : '' }}">
                                                <a href="{{ route('admin.basic_settings.mail_to_admin') }}">
                                                    <span class="sub-item">{{ __('Mail To Admin') }}</span>
                                                </a>
                                            </li>

                                            <li
                                                class="@if (request()->routeIs('admin.basic_settings.mail_templates')) active
                        @elseif (request()->routeIs('admin.basic_settings.edit_mail_template')) active @endif">
                                                <a href="{{ route('admin.basic_settings.mail_templates') }}">
                                                    <span class="sub-item">{{ __('Mail Templates') }}</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </li>
                                <li class="{{ request()->routeIs('admin.basic_settings.plugins') ? 'active' : '' }}">
                                    <a href="{{ route('admin.basic_settings.plugins') }}">
                                        <span class="sub-item">{{ __('Plugins') }}</span>
                                    </a>
                                </li>

                                <li class="{{ request()->routeIs('admin.basic_settings.seo') ? 'active' : '' }}">
                                    <a
                                        href="{{ route('admin.basic_settings.seo', ['language' => $defaultLang->code]) }}">
                                        <span class="sub-item">{{ __('SEO Informations') }}</span>
                                    </a>
                                </li>

                                <li
                                    class="{{ request()->routeIs('admin.basic_settings.maintenance_mode') ? 'active' : '' }}">
                                    <a href="{{ route('admin.basic_settings.maintenance_mode') }}">
                                        <span class="sub-item">{{ __('Maintenance Mode') }}</span>
                                    </a>
                                </li>                            </ul>
                        </div>
                    </li>
                @endif

                {{-- pwa setting --}}
                @if (is_null($roleInfo) || (!empty($rolePermissions) && in_array('PWA Settings', $rolePermissions)))
                    <li
                        class="nav-item
          @if (request()->routeIs('admin.pwa')) active 
          @elseif (request()->routeIs('admin.pwa.scanner')) active @endif">
                        <a data-toggle="collapse" href="#pwa_setting">
                            <i class="fab fa-app-store-ios"></i>
                            <p>{{ __('PWA Settings') }}</p>
                            <span class="caret"></span>
                        </a>

                        <div id="pwa_setting"
                            class="collapse
            @if (request()->routeIs('admin.pwa')) show
            @elseif (request()->routeIs('admin.pwa.scanner')) show @endif
            ">
                            <ul class="nav nav-collapse">
                                <li class="{{ request()->routeIs('admin.pwa.scanner') ? 'active' : '' }}">
                                    <a href="{{ route('admin.pwa.scanner') }}">
                                        <span class="sub-item">{{ __('PWA Scanner Setting') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                @endif

                {{-- admin --}}
                @if (is_null($roleInfo) || (!empty($rolePermissions) && in_array('Admin Management', $rolePermissions)))
                    <li
                        class="nav-item @if (request()->routeIs('admin.admin_management.role_permissions')) active
            @elseif (request()->routeIs('admin.admin_management.role.permissions')) active
            @elseif (request()->routeIs('admin.admin_management.registered_admins')) active @endif">
                        <a data-toggle="collapse" href="#admin">
                            <i class="fal fa-users-cog"></i>
                            <p>{{ __('Admin Management') }}</p>
                            <span class="caret"></span>
                        </a>

                        <div id="admin"
                            class="collapse
              @if (request()->routeIs('admin.admin_management.role_permissions')) show
              @elseif (request()->routeIs('admin.admin_management.role.permissions')) show
              @elseif (request()->routeIs('admin.admin_management.registered_admins')) show @endif">
                            <ul class="nav nav-collapse">
                                <li
                                    class="@if (request()->routeIs('admin.admin_management.role_permissions')) active
                  @elseif (request()->routeIs('admin.admin_management.role.permissions')) active @endif">
                                    <a href="{{ route('admin.admin_management.role_permissions') }}">
                                        <span class="sub-item">{{ __('Role & Permissions') }}</span>
                                    </a>
                                </li>

                                <li
                                    class="{{ request()->routeIs('admin.admin_management.registered_admins') ? 'active' : '' }}">
                                    <a href="{{ route('admin.admin_management.registered_admins') }}">
                                        <span class="sub-item">{{ __('Registered Admins') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                @endif

                {{-- language --}}
                @if (is_null($roleInfo) || (!empty($rolePermissions) && in_array('Language Management', $rolePermissions)))
                    <li
                        class="nav-item @if (request()->routeIs('admin.language_management')) active
            @elseif (request()->routeIs('admin.language_management.edit_keyword')) active @endif">
                        <a href="{{ route('admin.language_management') }}">
                            <i class="fal fa-language"></i>
                            <p>{{ __('Language Management') }}</p>
                        </a>
                    </li>


                    <li
                        class="nav-item @if (request()->routeIs('admin.edit_admin_keywords')) active
            @elseif (request()->routeIs('admin.edit_admin_keywords')) active @endif">
                        <a href="{{ route('admin.edit_admin_keywords') }}">
                            <i class="fal fa-language"></i>
                            <p>{{ __('Admin Language Keywords') }}</p>
                        </a>
                    </li>
                @endif

            </ul>
        </div>
    </div>
</div>
