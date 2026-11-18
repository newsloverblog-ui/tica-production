# TICA system map

> Generated from the local `main` source tree with Laravel booted in an isolated testing environment. No production database or payment provider was contacted.

## Architecture snapshot

- Backend: Laravel 13 / PHP 8.3+, server-rendered Blade plus JSON endpoints.
- Frontend: React 19 + TypeScript + Tailwind 4 for the newer TICA UI, alongside legacy Blade/Bootstrap views.
- Persistence: relational Eloquent models and Laravel migrations; production is PostgreSQL/Supabase, while automated validation uses an isolated test database.
- Async work: Laravel queued jobs, database-backed dispatch records, and scheduled reconciliation/notification commands.
- Payments: Paystack is the primary event/product checkout and payout integration; other legacy gateway controllers remain routeable under policy middleware.
- Authentication: separate admin, organizer, and customer guards, with role/permission and team-role middleware.

## Inventory totals

| Item | Count |
|---|---:|
| Application routes | 860 |
| Controllers | 175 |
| Form requests | 44 |
| Models | 164 |
| Jobs | 41 |
| Mailables | 10 |
| Notifications | 1 |
| Middleware | 29 |
| Console commands | 57 |
| Services | 176 |
| Providers | 5 |
| Rules | 5 |
| Exports | 20 |
| Migrations | 144 |
| Seeders | 5 |
| Unit tests | 240 |
| Feature tests | 3 |
| Form/network submission call sites | 473 |
| Paystack-related source occurrences | 1008 |

## Route areas

| URI area | Routes |
|---|---:|
| `admin` | 447 |
| `organizer` | 145 |
| `tica-api` | 49 |
| `customer` | 44 |
| `event-booking` | 31 |
| `product-order` | 22 |
| `scanner` | 17 |
| `shop` | 12 |
| `event` | 7 |
| `account-security` | 4 |
| `login` | 3 |
| `login-verification` | 3 |
| `organizers` | 3 |
| `allocated-registration` | 2 |
| `api` | 2 |
| `blog` | 2 |
| `checkout` | 2 |
| `contact` | 2 |
| `create` | 2 |
| `event-rsvp` | 2 |
| `event-waitlist` | 2 |
| `health` | 2 |
| `myfatoorah` | 2 |
| `promotion` | 2 |
| `registration-allocation` | 2 |
| `system` | 2 |
| `ticket-transfer` | 2 |
| `/` | 1 |
| `about` | 1 |
| `about-us` | 1 |
| `account` | 1 |
| `addto` | 1 |
| `advertisement` | 1 |
| `analytics` | 1 |
| `assets` | 1 |
| `auth` | 1 |
| `change-language` | 1 |
| `check-out2` | 1 |
| `checkin` | 1 |
| `dashboard` | 1 |
| `discover` | 1 |
| `event-booking-complete` | 1 |
| `events` | 1 |
| `faq` | 1 |
| `flutterwave` | 1 |
| `forgot` | 1 |
| `how` | 1 |
| `join` | 1 |
| `midtrans` | 1 |
| `monnify` | 1 |
| `offline` | 1 |
| `paystack` | 1 |
| `policies` | 1 |
| `pricing` | 1 |
| `product-order-complete` | 1 |
| `push-notification` | 1 |
| `remove` | 1 |
| `robots.txt` | 1 |
| `scanner.webmanifest` | 1 |
| `service-unavailable` | 1 |
| `signup` | 1 |
| `site.webmanifest` | 1 |
| `sitemap.xml` | 1 |
| `store-subscriber` | 1 |
| `stripe` | 1 |
| `team` | 1 |
| `ticket-booking` | 1 |
| `ticket-claim` | 1 |
| `tickets` | 1 |
| `xendit` | 1 |
| `{fallbackPlaceholder}` | 1 |
| `{slug}` | 1 |

## Runtime flows

### Identity and authorization

Admin, organizer, and customer sessions use distinct Laravel guards. Admin routes combine `auth:admin`, account-state, language, audit, retired-page, and permission middleware. Organizer mutations combine `auth:organizer`, account/email-state checks, and `tica.team` role checks. Scanner routes add device/staff verification middleware. Public forms and authentication endpoints are throttled.

### Event and ticket lifecycle

Events are created and edited through the shared TICA builder endpoints, then associated with tickets, dates, media, registration forms, programmes, seats, promotions, and organizer ownership. Checkout creates payment intents/reservations, provider verification finalizes bookings, and queued fulfillment issues ticket units and delivery records. Organizer/admin management routes cover attendee operations, cancellations, refunds, transfers, check-in, exports, and reporting.

### Paystack flow

Event and product checkout services initialize Paystack transactions using server-calculated amounts. Callback/webhook controllers verify provider responses, while reconciliation commands recover or reconcile delayed states. Webhook receipt middleware and intent/fulfillment idempotency records protect repeated delivery. Payout services create and submit transfers, reconcile transfer states, and post financial-ledger entries. Exact source call sites are catalogued below.

### Mail and notifications

Editable `MailTemplate` records are rendered through the application mail delivery service. Delivery is queued via durable application-mail rows and scheduled dispatch commands; specialized mailables/jobs cover ticket transfers, registration, cancellations, verification, waitlists, invitations, and payout status.

## Scheduled work

| File | Line | Schedule declaration |
|---|---:|---|
| `app/Console/Kernel.php` | 29 | `$schedule->call(function () {` |
| `app/Console/Kernel.php` | 34 | `})->everyMinute()->name('queue-heartbeats')->withoutOverlapping();` |
| `app/Console/Kernel.php` | 36 | `$schedule->command('ops:monitor-queues')` |
| `app/Console/Kernel.php` | 37 | `->everyFiveMinutes()` |
| `app/Console/Kernel.php` | 38 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 39 | `$schedule->command('ops:monitor-webhooks')` |
| `app/Console/Kernel.php` | 40 | `->everyFiveMinutes()` |
| `app/Console/Kernel.php` | 41 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 42 | `$schedule->command('ops:prune-webhook-receipts')` |
| `app/Console/Kernel.php` | 43 | `->dailyAt('03:20')` |
| `app/Console/Kernel.php` | 44 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 46 | `$schedule->command('payments:monitor-intents')` |
| `app/Console/Kernel.php` | 47 | `->everyFiveMinutes()` |
| `app/Console/Kernel.php` | 48 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 49 | `$schedule->command('payments:dispatch-iyzico-pending')` |
| `app/Console/Kernel.php` | 50 | `->everyTenMinutes()` |
| `app/Console/Kernel.php` | 51 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 52 | `$schedule->command('payments:dispatch-fulfillments')` |
| `app/Console/Kernel.php` | 53 | `->everyMinute()` |
| `app/Console/Kernel.php` | 54 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 55 | `$schedule->command('payments:process-refunds')` |
| `app/Console/Kernel.php` | 56 | `->everyMinute()` |
| `app/Console/Kernel.php` | 57 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 58 | `$schedule->command('payments:reconcile-refunds')` |
| `app/Console/Kernel.php` | 59 | `->everyTenMinutes()` |
| `app/Console/Kernel.php` | 60 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 61 | `$schedule->command('payments:reconcile-paystack')` |
| `app/Console/Kernel.php` | 62 | `->dailyAt('02:15')` |
| `app/Console/Kernel.php` | 63 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 64 | `$schedule->command('payments:reconcile-flutterwave')` |
| `app/Console/Kernel.php` | 65 | `->dailyAt('02:30')` |
| `app/Console/Kernel.php` | 66 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 67 | `$schedule->command('payments:reconcile-paystack-settlements')` |
| `app/Console/Kernel.php` | 68 | `->dailyAt('02:45')` |
| `app/Console/Kernel.php` | 69 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 70 | `$schedule->command('payouts:reconcile-paystack')` |
| `app/Console/Kernel.php` | 71 | `->everyTenMinutes()` |
| `app/Console/Kernel.php` | 72 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 73 | `$schedule->command('finance:release-earnings')` |
| `app/Console/Kernel.php` | 74 | `->everyFifteenMinutes()` |
| `app/Console/Kernel.php` | 75 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 76 | `$schedule->command('payouts:dispatch-pending')` |
| `app/Console/Kernel.php` | 77 | `->everyMinute()` |
| `app/Console/Kernel.php` | 78 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 79 | `$schedule->command('payouts:release-eligible --apply')` |
| `app/Console/Kernel.php` | 80 | `->dailyAt(sprintf('%02d:00', max(0, min(23, (int) config('payouts.eligibility_policy.release_hour', 10)))))` |
| `app/Console/Kernel.php` | 83 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 84 | `$schedule->command('bookings:monitor-deliveries')` |
| `app/Console/Kernel.php` | 85 | `->everyFiveMinutes()` |
| `app/Console/Kernel.php` | 86 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 87 | `$schedule->command('campaigns:dispatch-pending')` |
| `app/Console/Kernel.php` | 88 | `->everyMinute()` |
| `app/Console/Kernel.php` | 89 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 90 | `$schedule->command('promotions:sync')` |
| `app/Console/Kernel.php` | 91 | `->everyFiveMinutes()` |
| `app/Console/Kernel.php` | 92 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 93 | `$schedule->command('events:dispatch-waitlist')` |
| `app/Console/Kernel.php` | 94 | `->everyMinute()` |
| `app/Console/Kernel.php` | 95 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 96 | `$schedule->command('events:dispatch-cancellation-notices')` |
| `app/Console/Kernel.php` | 97 | `->everyMinute()` |
| `app/Console/Kernel.php` | 98 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 99 | `$schedule->command('tickets:dispatch-transfers')` |
| `app/Console/Kernel.php` | 100 | `->everyMinute()` |
| `app/Console/Kernel.php` | 101 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 102 | `$schedule->command('tickets:dispatch-resends')` |
| `app/Console/Kernel.php` | 103 | `->everyMinute()` |
| `app/Console/Kernel.php` | 104 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 105 | `$schedule->command('guests:dispatch-invitations')` |
| `app/Console/Kernel.php` | 106 | `->everyMinute()` |
| `app/Console/Kernel.php` | 107 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 108 | `$schedule->command('accounts:dispatch-verifications')` |
| `app/Console/Kernel.php` | 109 | `->everyMinute()` |
| `app/Console/Kernel.php` | 110 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 111 | `$schedule->command('mail:dispatch-pending')` |
| `app/Console/Kernel.php` | 112 | `->everyMinute()` |
| `app/Console/Kernel.php` | 113 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 114 | `$schedule->command('notifications:dispatch-lifecycle --mode=hourly')` |
| `app/Console/Kernel.php` | 115 | `->hourly()` |
| `app/Console/Kernel.php` | 116 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 117 | `$schedule->command('notifications:dispatch-lifecycle --mode=daily')` |
| `app/Console/Kernel.php` | 118 | `->dailyAt('08:00')` |
| `app/Console/Kernel.php` | 120 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 121 | `$schedule->command('notifications:dispatch-lifecycle --mode=weekly')` |
| `app/Console/Kernel.php` | 122 | `->weeklyOn(1, '08:15')` |
| `app/Console/Kernel.php` | 124 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 125 | `$schedule->command('scanner:prune-offline-data')` |
| `app/Console/Kernel.php` | 126 | `->dailyAt('03:15')` |
| `app/Console/Kernel.php` | 127 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 128 | `$schedule->command('exports:prune')` |
| `app/Console/Kernel.php` | 129 | `->dailyAt('03:30')` |
| `app/Console/Kernel.php` | 130 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 131 | `$schedule->command('exports:run-schedules')` |
| `app/Console/Kernel.php` | 132 | `->everyMinute()` |
| `app/Console/Kernel.php` | 133 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 134 | `$schedule->command('exports:dispatch-pending')` |
| `app/Console/Kernel.php` | 135 | `->everyMinute()` |
| `app/Console/Kernel.php` | 136 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 137 | `$schedule->command('exports:notify-ready')` |
| `app/Console/Kernel.php` | 138 | `->everyMinute()` |
| `app/Console/Kernel.php` | 139 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 140 | `$schedule->command('imports:dispatch-pending')` |
| `app/Console/Kernel.php` | 141 | `->everyMinute()` |
| `app/Console/Kernel.php` | 142 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 143 | `$schedule->command('registrations:dispatch-allocations')` |
| `app/Console/Kernel.php` | 144 | `->everyMinute()` |
| `app/Console/Kernel.php` | 145 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 146 | `$schedule->command('registrations:dispatch-notifications')` |
| `app/Console/Kernel.php` | 147 | `->everyMinute()` |
| `app/Console/Kernel.php` | 148 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 149 | `$schedule->command('audit:apply-retention --apply')` |
| `app/Console/Kernel.php` | 150 | `->dailyAt('04:05')` |
| `app/Console/Kernel.php` | 152 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 153 | `$schedule->command('registrations:apply-retention --apply')` |
| `app/Console/Kernel.php` | 154 | `->dailyAt('03:40')` |
| `app/Console/Kernel.php` | 156 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 157 | `$schedule->command('registrations:prune-imports')` |
| `app/Console/Kernel.php` | 158 | `->dailyAt('03:45')` |
| `app/Console/Kernel.php` | 159 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 160 | `$schedule->command('registrations:expire-invoices')` |
| `app/Console/Kernel.php` | 161 | `->everyFifteenMinutes()` |
| `app/Console/Kernel.php` | 162 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 163 | `$schedule->command('guests:apply-retention --apply')` |
| `app/Console/Kernel.php` | 164 | `->dailyAt('03:50')` |
| `app/Console/Kernel.php` | 166 | `->withoutOverlapping();` |
| `app/Console/Kernel.php` | 167 | `$schedule->command('guests:prune-imports')` |
| `app/Console/Kernel.php` | 168 | `->dailyAt('03:55')` |
| `app/Console/Kernel.php` | 169 | `->withoutOverlapping();` |

## Complete application route registry

| # | Method | URI | Name | Action | Middleware |
|---:|---|---|---|---|---|
| 1 | `GET\|HEAD` | `/` | `index` | `App\Http\Controllers\FrontEnd\TicaUiController@page` | `web, change.lang` |
| 2 | `GET\|HEAD` | `about` | `about` | `App\Http\Controllers\FrontEnd\TicaUiController@page` | `web, change.lang` |
| 3 | `GET\|HEAD` | `about-us` | `` | `Closure` | `web, change.lang` |
| 4 | `GET\|HEAD` | `account` | `tica.account` | `App\Http\Controllers\FrontEnd\TicaUiController@page` | `web, change.lang` |
| 5 | `GET\|HEAD` | `account-security/{guard}` | `account-security.show` | `App\Http\Controllers\AccountSecurityController@show` | `web` |
| 6 | `POST` | `account-security/{guard}/cancel` | `account-security.cancel` | `App\Http\Controllers\AccountSecurityController@cancelChange` | `web, throttle:authentication` |
| 7 | `POST` | `account-security/{guard}/request` | `account-security.request` | `App\Http\Controllers\AccountSecurityController@requestChange` | `web, throttle:3,10` |
| 8 | `POST` | `account-security/{guard}/verify` | `account-security.verify` | `App\Http\Controllers\AccountSecurityController@verifyChange` | `web, throttle:authentication` |
| 9 | `POST` | `addto/wishlist/{id}` | `addto.wishlist` | `App\Http\Controllers\FrontEnd\EventController@add_to_wishlist` | `web, change.lang, auth:customer` |
| 10 | `GET\|HEAD` | `admin` | `admin.login` | `App\Http\Controllers\BackEnd\AdminController@login` | `web, guest:admin` |
| 11 | `GET\|HEAD` | `admin/add-event` | `add.event.event` | `App\Http\Controllers\BackEnd\Event\EventController@add_event` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 12 | `POST` | `admin/admin-management/admin/{id}/update-status` | `admin.admin_management.admin.update_status` | `App\Http\Controllers\BackEnd\Administrator\SiteAdminController@updateStatus` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Admin Management` |
| 13 | `POST` | `admin/admin-management/delete-admin/{id}` | `admin.admin_management.delete_admin` | `App\Http\Controllers\BackEnd\Administrator\SiteAdminController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Admin Management` |
| 14 | `POST` | `admin/admin-management/delete-role/{id}` | `admin.admin_management.delete_role` | `App\Http\Controllers\BackEnd\Administrator\RolePermissionController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Admin Management` |
| 15 | `GET\|HEAD` | `admin/admin-management/registered-admins` | `admin.admin_management.registered_admins` | `App\Http\Controllers\BackEnd\Administrator\SiteAdminController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Admin Management` |
| 16 | `GET\|HEAD` | `admin/admin-management/role-permissions` | `admin.admin_management.role_permissions` | `App\Http\Controllers\BackEnd\Administrator\RolePermissionController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Admin Management` |
| 17 | `GET\|HEAD` | `admin/admin-management/role/{id}/permissions` | `admin.admin_management.role.permissions` | `App\Http\Controllers\BackEnd\Administrator\RolePermissionController@permissions` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Admin Management` |
| 18 | `POST` | `admin/admin-management/role/{id}/update-permissions` | `admin.admin_management.role.update_permissions` | `App\Http\Controllers\BackEnd\Administrator\RolePermissionController@updatePermissions` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Admin Management` |
| 19 | `POST` | `admin/admin-management/store-admin` | `admin.admin_management.store_admin` | `App\Http\Controllers\BackEnd\Administrator\SiteAdminController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Admin Management` |
| 20 | `POST` | `admin/admin-management/store-role` | `admin.admin_management.store_role` | `App\Http\Controllers\BackEnd\Administrator\RolePermissionController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Admin Management` |
| 21 | `POST` | `admin/admin-management/update-admin` | `admin.admin_management.update_admin` | `App\Http\Controllers\BackEnd\Administrator\SiteAdminController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Admin Management` |
| 22 | `POST` | `admin/admin-management/update-role` | `admin.admin_management.update_role` | `App\Http\Controllers\BackEnd\Administrator\RolePermissionController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Admin Management` |
| 23 | `GET\|HEAD` | `admin/admin/pwa` | `admin.pwa` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@pwa` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:PWA Settings` |
| 24 | `POST` | `admin/admin/pwa/post` | `admin.pwa.update` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updatepwa` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:PWA Settings` |
| 25 | `GET\|HEAD` | `admin/admin/pwa/scanner` | `admin.pwa.scanner` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@pwa_scanner` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:PWA Settings` |
| 26 | `POST` | `admin/admin/pwa/scanner/post` | `admin.pwa.scanner.update` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updatepwaScanner` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:PWA Settings` |
| 27 | `GET\|HEAD` | `admin/advertise/advertisements` | `admin.advertise.advertisements` | `App\Http\Controllers\BackEnd\AdvertisementController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Advertise` |
| 28 | `POST` | `admin/advertise/bulk-delete-advertisement` | `admin.advertise.bulk_delete_advertisement` | `App\Http\Controllers\BackEnd\AdvertisementController@bulkDestroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Advertise` |
| 29 | `POST` | `admin/advertise/delete-advertisement/{id}` | `admin.advertise.delete_advertisement` | `App\Http\Controllers\BackEnd\AdvertisementController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Advertise` |
| 30 | `GET\|HEAD` | `admin/advertise/settings` | `admin.advertise.settings` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@advertiseSettings` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Advertise` |
| 31 | `POST` | `admin/advertise/store-advertisement` | `admin.advertise.store_advertisement` | `App\Http\Controllers\BackEnd\AdvertisementController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Advertise` |
| 32 | `GET\|HEAD` | `admin/advertise/tica-promotions` | `admin.advertise.tica_promotions` | `App\Http\Controllers\BackEnd\TicaPromotionController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Advertise` |
| 33 | `POST` | `admin/advertise/tica-promotions/campaigns/{campaign}` | `admin.advertise.tica_promotions.campaign` | `App\Http\Controllers\BackEnd\TicaPromotionController@reviewCampaign` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Advertise, throttle:10,10` |
| 34 | `POST` | `admin/advertise/tica-promotions/pixels/{pixel}` | `admin.advertise.tica_promotions.pixel` | `App\Http\Controllers\BackEnd\TicaPromotionController@reviewPixel` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Advertise, throttle:10,10` |
| 35 | `POST` | `admin/advertise/tica-promotions/plans` | `admin.advertise.tica_promotions.plan.store` | `App\Http\Controllers\BackEnd\TicaPromotionController@storePlan` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Advertise, throttle:10,10` |
| 36 | `POST` | `admin/advertise/tica-promotions/plans/{plan}` | `admin.advertise.tica_promotions.plan` | `App\Http\Controllers\BackEnd\TicaPromotionController@updatePlan` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Advertise, throttle:10,10` |
| 37 | `POST` | `admin/advertise/tica-promotions/plans/{plan}/archive` | `admin.advertise.tica_promotions.plan.archive` | `App\Http\Controllers\BackEnd\TicaPromotionController@archivePlan` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Advertise, throttle:10,10` |
| 38 | `POST` | `admin/advertise/tica-promotions/plans/{plan}/duplicate` | `admin.advertise.tica_promotions.plan.duplicate` | `App\Http\Controllers\BackEnd\TicaPromotionController@duplicatePlan` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Advertise, throttle:10,10` |
| 39 | `POST` | `admin/advertise/update-advertisement` | `admin.advertise.update_advertisement` | `App\Http\Controllers\BackEnd\AdvertisementController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Advertise` |
| 40 | `POST` | `admin/advertise/update-settings` | `admin.advertise.update_settings` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updateAdvertiseSettings` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Advertise` |
| 41 | `GET\|HEAD` | `admin/analytics/funnel` | `admin.funnel_analytics.index` | `App\Http\Controllers\BackEnd\FunnelAnalyticsController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 42 | `GET\|HEAD` | `admin/announcement-popups` | `admin.announcement_popups` | `App\Http\Controllers\BackEnd\PopupController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Announcement Popups` |
| 43 | `POST` | `admin/announcement-popups/bulk-delete-popup` | `admin.announcement_popups.bulk_delete_popup` | `App\Http\Controllers\BackEnd\PopupController@bulkDestroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Announcement Popups` |
| 44 | `GET\|HEAD` | `admin/announcement-popups/create-popup/{type}` | `admin.announcement_popups.create_popup` | `App\Http\Controllers\BackEnd\PopupController@create` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Announcement Popups` |
| 45 | `POST` | `admin/announcement-popups/delete-popup/{id}` | `admin.announcement_popups.delete_popup` | `App\Http\Controllers\BackEnd\PopupController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Announcement Popups` |
| 46 | `GET\|HEAD` | `admin/announcement-popups/edit-popup/{id}` | `admin.announcement_popups.edit_popup` | `App\Http\Controllers\BackEnd\PopupController@edit` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Announcement Popups` |
| 47 | `POST` | `admin/announcement-popups/popup/{id}/update-status` | `admin.announcement_popups.update_popup_status` | `App\Http\Controllers\BackEnd\PopupController@updateStatus` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Announcement Popups` |
| 48 | `GET\|HEAD` | `admin/announcement-popups/select-popup-type` | `admin.announcement_popups.select_popup_type` | `App\Http\Controllers\BackEnd\PopupController@popupType` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Announcement Popups` |
| 49 | `POST` | `admin/announcement-popups/store-popup` | `admin.announcement_popups.store_popup` | `App\Http\Controllers\BackEnd\PopupController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Announcement Popups` |
| 50 | `POST` | `admin/announcement-popups/update-popup/{id}` | `admin.announcement_popups.update_popup` | `App\Http\Controllers\BackEnd\PopupController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Announcement Popups` |
| 51 | `POST` | `admin/auth` | `admin.auth` | `App\Http\Controllers\BackEnd\AdminController@authentication` | `web, guest:admin, throttle:authentication` |
| 52 | `GET\|HEAD` | `admin/basic-settings/appearance` | `admin.basic_settings.appearance` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@appearance` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 53 | `GET\|HEAD` | `admin/basic-settings/breadcrumb` | `admin.basic_settings.breadcrumb` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@breadcrumb` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 54 | `GET\|HEAD` | `admin/basic-settings/contact-page` | `admin.basic_settings.contact_page` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@information` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 55 | `GET\|HEAD` | `admin/basic-settings/cookie-alert` | `admin.basic_settings.cookie_alert` | `App\Http\Controllers\BackEnd\BasicSettings\CookieAlertController@cookieAlert` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 56 | `GET\|HEAD` | `admin/basic-settings/currency` | `admin.basic_settings.currency` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@currency` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 57 | `POST` | `admin/basic-settings/delete-social-media/{id}` | `admin.basic_settings.delete_social_media` | `App\Http\Controllers\BackEnd\BasicSettings\SocialMediaController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 58 | `GET\|HEAD` | `admin/basic-settings/edit-mail-template/{id}` | `admin.basic_settings.edit_mail_template` | `App\Http\Controllers\BackEnd\BasicSettings\MailTemplateController@edit` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 59 | `GET\|HEAD` | `admin/basic-settings/favicon` | `admin.basic_settings.favicon` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@favicon` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 60 | `GET\|HEAD` | `admin/basic-settings/footer-logo` | `admin.basic_settings.footer_logo` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@footerLogo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 61 | `GET\|HEAD` | `admin/basic-settings/general-settings` | `admin.basic_settings.general_settings` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@general_settings` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 62 | `GET\|HEAD` | `admin/basic-settings/logo` | `admin.basic_settings.logo` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@logo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 63 | `GET\|HEAD` | `admin/basic-settings/mail-from-admin` | `admin.basic_settings.mail_from_admin` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@mailFromAdmin` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 64 | `GET\|HEAD` | `admin/basic-settings/mail-templates` | `admin.basic_settings.mail_templates` | `App\Http\Controllers\BackEnd\BasicSettings\MailTemplateController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 65 | `GET\|HEAD` | `admin/basic-settings/mail-to-admin` | `admin.basic_settings.mail_to_admin` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@mailToAdmin` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 66 | `GET\|HEAD` | `admin/basic-settings/maintenance-mode` | `admin.basic_settings.maintenance_mode` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@maintenance` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 67 | `GET\|HEAD` | `admin/basic-settings/page-headings` | `admin.basic_settings.page_headings` | `App\Http\Controllers\BackEnd\BasicSettings\PageHeadingController@pageHeadings` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 68 | `GET\|HEAD` | `admin/basic-settings/plugins` | `admin.basic_settings.plugins` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@plugins` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 69 | `GET\|HEAD` | `admin/basic-settings/seo` | `admin.basic_settings.seo` | `App\Http\Controllers\BackEnd\BasicSettings\SEOController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 70 | `GET\|HEAD` | `admin/basic-settings/social-medias` | `admin.basic_settings.social_medias` | `App\Http\Controllers\BackEnd\BasicSettings\SocialMediaController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 71 | `POST` | `admin/basic-settings/store-social-media` | `admin.basic_settings.store_social_media` | `App\Http\Controllers\BackEnd\BasicSettings\SocialMediaController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 72 | `GET\|HEAD` | `admin/basic-settings/theme-and-home` | `admin.basic_settings.theme_and_home` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@themeAndHome` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 73 | `POST` | `admin/basic-settings/update-appearance` | `admin.basic_settings.update_appearance` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updateAppearance` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 74 | `POST` | `admin/basic-settings/update-breadcrumb` | `admin.basic_settings.update_breadcrumb` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updateBreadcrumb` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 75 | `POST` | `admin/basic-settings/update-cookie-alert` | `admin.basic_settings.update_cookie_alert` | `App\Http\Controllers\BackEnd\BasicSettings\CookieAlertController@updateCookieAlert` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 76 | `POST` | `admin/basic-settings/update-currency` | `admin.basic_settings.update_currency` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updateCurrency` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 77 | `POST` | `admin/basic-settings/update-disqus` | `admin.basic_settings.update_disqus` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updateDisqus` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 78 | `POST` | `admin/basic-settings/update-facebook` | `admin.basic_settings.update_facebook` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updateFacebook` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 79 | `POST` | `admin/basic-settings/update-favicon` | `admin.basic_settings.update_favicon` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updateFavicon` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 80 | `POST` | `admin/basic-settings/update-footer-logo` | `admin.basic_settings.update_footer_logo` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updateFooterLogo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 81 | `POST` | `admin/basic-settings/update-general-settings` | `admin.basic_settings.general_settings.update` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@update_general_setting` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 82 | `POST` | `admin/basic-settings/update-google` | `admin.basic_settings.update_google` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updateGoogle` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 83 | `POST` | `admin/basic-settings/update-info` | `admin.basic_settings.update_info` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updateInfo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 84 | `POST` | `admin/basic-settings/update-logo` | `admin.basic_settings.update_logo` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updateLogo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 85 | `POST` | `admin/basic-settings/update-mail-from-admin` | `admin.basic_settings.update_mail_from_admin` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updateMailFromAdmin` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 86 | `POST` | `admin/basic-settings/update-mail-template/{id}` | `admin.basic_settings.update_mail_template` | `App\Http\Controllers\BackEnd\BasicSettings\MailTemplateController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 87 | `POST` | `admin/basic-settings/update-mail-to-admin` | `admin.basic_settings.update_mail_to_admin` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updateMailToAdmin` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 88 | `POST` | `admin/basic-settings/update-maintenance-mode` | `admin.basic_settings.update_maintenance_mode` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updateMaintenance` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 89 | `POST` | `admin/basic-settings/update-page-headings` | `admin.basic_settings.update_page_headings` | `App\Http\Controllers\BackEnd\BasicSettings\PageHeadingController@updatePageHeadings` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 90 | `POST` | `admin/basic-settings/update-recaptcha` | `admin.basic_settings.update_recaptcha` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updateRecaptcha` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 91 | `POST` | `admin/basic-settings/update-seo` | `admin.basic_settings.update_seo` | `App\Http\Controllers\BackEnd\BasicSettings\SEOController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 92 | `PUT` | `admin/basic-settings/update-social-media` | `admin.basic_settings.update_social_media` | `App\Http\Controllers\BackEnd\BasicSettings\SocialMediaController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 93 | `POST` | `admin/basic-settings/update-theme-and-home` | `admin.basic_settings.update_theme_and_home` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updateThemeAndHome` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 94 | `POST` | `admin/basic-settings/update-whatsapp` | `admin.basic_settings.update_whatsapp` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updateWhatsApp` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 95 | `GET\|HEAD` | `admin/blog-management/blogs` | `admin.blog_management.blogs` | `App\Http\Controllers\BackEnd\Journal\BlogController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Blog Management` |
| 96 | `POST` | `admin/blog-management/bulk-delete-blog` | `admin.blog_management.bulk_delete_blog` | `App\Http\Controllers\BackEnd\Journal\BlogController@bulkDestroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Blog Management` |
| 97 | `POST` | `admin/blog-management/bulk-delete-category` | `admin.blog_management.bulk_delete_category` | `App\Http\Controllers\BackEnd\Journal\CategoryController@bulkDestroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Blog Management` |
| 98 | `GET\|HEAD` | `admin/blog-management/categories` | `admin.blog_management.categories` | `App\Http\Controllers\BackEnd\Journal\CategoryController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Blog Management` |
| 99 | `GET\|HEAD` | `admin/blog-management/create-blog` | `admin.blog_management.create_blog` | `App\Http\Controllers\BackEnd\Journal\BlogController@create` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Blog Management` |
| 100 | `POST` | `admin/blog-management/delete-blog/{id}` | `admin.blog_management.delete_blog` | `App\Http\Controllers\BackEnd\Journal\BlogController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Blog Management` |
| 101 | `POST` | `admin/blog-management/delete-category/{id}` | `admin.blog_management.delete_category` | `App\Http\Controllers\BackEnd\Journal\CategoryController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Blog Management` |
| 102 | `GET\|HEAD` | `admin/blog-management/edit-blog/{id}` | `admin.blog_management.edit_blog` | `App\Http\Controllers\BackEnd\Journal\BlogController@edit` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Blog Management` |
| 103 | `POST` | `admin/blog-management/store-blog` | `admin.blog_management.store_blog` | `App\Http\Controllers\BackEnd\Journal\BlogController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Blog Management` |
| 104 | `POST` | `admin/blog-management/store-category` | `admin.blog_management.store_category` | `App\Http\Controllers\BackEnd\Journal\CategoryController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Blog Management` |
| 105 | `POST` | `admin/blog-management/update-blog/{id}` | `admin.blog_management.update_blog` | `App\Http\Controllers\BackEnd\Journal\BlogController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Blog Management` |
| 106 | `PUT` | `admin/blog-management/update-category` | `admin.blog_management.update_category` | `App\Http\Controllers\BackEnd\Journal\CategoryController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Blog Management` |
| 107 | `POST` | `admin/bulk-delete-subscriber` | `admin.user_management.bulk_delete_subscriber` | `App\Http\Controllers\BackEnd\User\SubscriberController@bulkDestroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Subscribers` |
| 108 | `POST` | `admin/bulk/delete-customer` | `admin.customer_management.bulk_delete_customer` | `App\Http\Controllers\BackEnd\CustomerManagementController@bulkDestroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Customer Management, Closure` |
| 109 | `POST` | `admin/bulk/delete/bulk/event/ticket` | `admin.event_management.bulk_delete_event_ticket` | `App\Http\Controllers\BackEnd\Event\TicketController@bulk_delete` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 110 | `POST` | `admin/bulk/delete/event` | `admin.event_management.bulk_delete_event` | `App\Http\Controllers\BackEnd\Event\EventController@bulk_delete` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 111 | `GET\|HEAD` | `admin/change-password` | `admin.change_password` | `App\Http\Controllers\BackEnd\AdminController@changePassword` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired` |
| 112 | `POST` | `admin/change-theme` | `admin.change_theme` | `App\Http\Controllers\BackEnd\AdminController@changeTheme` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired` |
| 113 | `GET\|HEAD` | `admin/choose-event-type` | `admin.choose-event-type` | `App\Http\Controllers\BackEnd\Event\EventController@choose_event_type` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 114 | `GET\|HEAD` | `admin/coupons` | `admin.event_management.coupons` | `App\Http\Controllers\BackEnd\Event\CouponController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings` |
| 115 | `GET\|HEAD` | `admin/custom-pages` | `admin.custom_pages` | `App\Http\Controllers\BackEnd\CustomPageController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Custom Pages` |
| 116 | `POST` | `admin/custom-pages/bulk-delete-page` | `admin.custom_pages.bulk_delete_page` | `App\Http\Controllers\BackEnd\CustomPageController@bulkDestroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Custom Pages` |
| 117 | `GET\|HEAD` | `admin/custom-pages/create-page` | `admin.custom_pages.create_page` | `App\Http\Controllers\BackEnd\CustomPageController@create` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Custom Pages` |
| 118 | `POST` | `admin/custom-pages/delete-page/{id}` | `admin.custom_pages.delete_page` | `App\Http\Controllers\BackEnd\CustomPageController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Custom Pages` |
| 119 | `GET\|HEAD` | `admin/custom-pages/edit-page/{id}` | `admin.custom_pages.edit_page` | `App\Http\Controllers\BackEnd\CustomPageController@edit` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Custom Pages` |
| 120 | `POST` | `admin/custom-pages/store-page` | `admin.custom_pages.store_page` | `App\Http\Controllers\BackEnd\CustomPageController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Custom Pages` |
| 121 | `POST` | `admin/custom-pages/update-page/{id}` | `admin.custom_pages.update_page` | `App\Http\Controllers\BackEnd\CustomPageController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Custom Pages` |
| 122 | `GET\|HEAD` | `admin/customer-management/add-customer` | `admin.organizer_management.add_customer` | `App\Http\Controllers\BackEnd\CustomerManagementController@create` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Customer Management, Closure` |
| 123 | `GET\|HEAD` | `admin/customer-management/customer/{id}/change-password` | `admin.customer_management.customer.change_password` | `App\Http\Controllers\BackEnd\CustomerManagementController@changePassword` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Customer Management, Closure` |
| 124 | `POST` | `admin/customer-management/customer/{id}/customer-delete` | `admin.customer_management.customer_delete` | `App\Http\Controllers\BackEnd\CustomerManagementController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Customer Management, Closure` |
| 125 | `GET\|HEAD` | `admin/customer-management/customer/{id}/details` | `admin.customer_management.customer_details` | `App\Http\Controllers\BackEnd\CustomerManagementController@show` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Customer Management, Closure` |
| 126 | `GET\|HEAD` | `admin/customer-management/customer/{id}/edit` | `admin.customer_management.customer_edit` | `App\Http\Controllers\BackEnd\CustomerManagementController@edit` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Customer Management, Closure` |
| 127 | `POST` | `admin/customer-management/customer/{id}/secret-login` | `admin.customer_management.secret_login` | `App\Http\Controllers\BackEnd\CustomerManagementController@secret_login` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Customer Management, Closure` |
| 128 | `POST` | `admin/customer-management/customer/{id}/update` | `admin.customer_management.customer.update_customer` | `App\Http\Controllers\BackEnd\CustomerManagementController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Customer Management, Closure` |
| 129 | `POST` | `admin/customer-management/customer/{id}/update-account-status` | `admin.customer_management.customer.update_account_status` | `App\Http\Controllers\BackEnd\CustomerManagementController@updateAccountStatus` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Customer Management, Closure` |
| 130 | `POST` | `admin/customer-management/customer/{id}/update-email-status` | `admin.customer_management.customer.update_email_status` | `App\Http\Controllers\BackEnd\CustomerManagementController@updateEmailStatus` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Customer Management, Closure` |
| 131 | `POST` | `admin/customer-management/customer/{id}/update-password` | `admin.customer_management.customer.update_password` | `App\Http\Controllers\BackEnd\CustomerManagementController@updatePassword` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Customer Management, Closure` |
| 132 | `GET\|HEAD` | `admin/customer-management/privacy-requests` | `admin.customer_privacy_requests.index` | `App\Http\Controllers\BackEnd\CustomerPrivacyRequestController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Customer Management` |
| 133 | `POST` | `admin/customer-management/privacy-requests/{privacyRequest}` | `admin.customer_privacy_requests.update` | `App\Http\Controllers\BackEnd\CustomerPrivacyRequestController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Customer Management` |
| 134 | `GET\|HEAD` | `admin/customer-management/registered-customer` | `admin.organizer_management.registered_customer` | `App\Http\Controllers\BackEnd\CustomerManagementController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Customer Management, Closure` |
| 135 | `POST` | `admin/customer-management/registered-customer/export` | `admin.customer_management.export` | `App\Http\Controllers\BackEnd\AdminDirectoryExportController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Customer Management, throttle:5,1` |
| 136 | `POST` | `admin/customer-management/store-customer` | `admin.organizer_management.store_customer` | `App\Http\Controllers\BackEnd\CustomerManagementController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Customer Management, Closure` |
| 137 | `GET\|HEAD` | `admin/dashboard` | `admin.dashboard` | `App\Http\Controllers\BackEnd\AdminController@redirectToDashboard` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired` |
| 138 | `POST` | `admin/delete-coupon/{id}` | `admin.event_management.delete_coupon` | `App\Http\Controllers\BackEnd\Event\CouponController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings` |
| 139 | `POST` | `admin/delete-date/{id}` | `admin.event.delete.date` | `App\Http\Controllers\BackEnd\Event\EventController@deleteDate` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 140 | `POST` | `admin/delete-event/{id}` | `admin.event_management.delete_event` | `App\Http\Controllers\BackEnd\Event\EventController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 141 | `GET\|HEAD` | `admin/directory-exports` | `admin.directory_exports.index` | `App\Http\Controllers\BackEnd\AdminDirectoryExportController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired` |
| 142 | `GET\|HEAD` | `admin/directory-exports/{export}/download` | `admin.directory_exports.download` | `App\Http\Controllers\BackEnd\AdminDirectoryExportController@download` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, signed` |
| 143 | `GET\|HEAD` | `admin/edit-event/{id}` | `admin.event_management.edit_event` | `App\Http\Controllers\BackEnd\Event\EventController@edit` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 144 | `GET\|HEAD` | `admin/edit-keywords` | `admin.edit_admin_keywords` | `App\Http\Controllers\BackEnd\LanguageController@adminKeywordsEdit` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Language Management` |
| 145 | `GET\|HEAD` | `admin/edit-profile` | `admin.edit_profile` | `App\Http\Controllers\BackEnd\AdminController@editProfile` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired` |
| 146 | `GET\|HEAD` | `admin/event-booking` | `admin.event.booking` | `App\Http\Controllers\BackEnd\Event\EventBookingController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings` |
| 147 | `POST` | `admin/event-booking/bulk-delete` | `admin.event_booking.bulk_delete` | `App\Http\Controllers\BackEnd\Event\EventBookingController@bulkDestroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings` |
| 148 | `GET\|HEAD` | `admin/event-booking/details/{id}` | `admin.event_booking.details` | `App\Http\Controllers\BackEnd\Event\EventBookingController@show` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings` |
| 149 | `POST` | `admin/event-booking/export` | `admin.event_bookings.export` | `App\Http\Controllers\BackEnd\Event\EventBookingController@export` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings` |
| 150 | `POST` | `admin/event-booking/export-schedules` | `admin.data_export_schedules.store` | `App\Http\Controllers\BackEnd\DataExportController@storeSchedule` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings` |
| 151 | `POST` | `admin/event-booking/export-schedules/{schedule}/toggle` | `admin.data_export_schedules.toggle` | `App\Http\Controllers\BackEnd\DataExportController@toggleSchedule` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings` |
| 152 | `GET\|HEAD` | `admin/event-booking/exports` | `admin.data_exports.index` | `App\Http\Controllers\BackEnd\DataExportController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings` |
| 153 | `GET\|HEAD` | `admin/event-booking/exports/{export}/download` | `admin.data_exports.download` | `App\Http\Controllers\BackEnd\DataExportController@download` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings, signed` |
| 154 | `GET\|HEAD` | `admin/event-booking/report` | `admin.event_booking.report` | `App\Http\Controllers\BackEnd\Event\EventBookingController@report` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings` |
| 155 | `POST` | `admin/event-booking/update/payment-status/{id}` | `admin.event_booking.update_payment_status` | `App\Http\Controllers\BackEnd\Event\EventBookingController@updatePaymentStatus` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings, permission:Transaction` |
| 156 | `GET\|HEAD` | `admin/event-booking/{id}/attachment` | `admin.event_booking.attachment` | `App\Http\Controllers\BackEnd\Event\EventBookingController@attachment` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings` |
| 157 | `GET\|HEAD` | `admin/event-booking/{id}/invoice` | `admin.event_booking.invoice` | `App\Http\Controllers\BackEnd\Event\EventBookingController@invoice` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings` |
| 158 | `GET\|HEAD` | `admin/event-builder` | `admin.event_builder` | `App\Http\Controllers\FrontEnd\TicaUiController@adminCreate` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 159 | `POST` | `admin/event-builder` | `admin.event_builder.store` | `App\Http\Controllers\FrontEnd\TicaOrganizerController@storeAdminEvent` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management, throttle:20,1` |
| 160 | `POST` | `admin/event-builder/{id}` | `admin.event_builder.update` | `App\Http\Controllers\FrontEnd\TicaOrganizerController@updateAdminEvent` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management, throttle:20,1` |
| 161 | `GET\|HEAD` | `admin/event-builder/{id}/edit` | `admin.event_builder.edit` | `App\Http\Controllers\FrontEnd\TicaUiController@adminEdit` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 162 | `POST` | `admin/event-cancellation-cases/{case}/dispute` | `admin.event_cancellation_cases.dispute` | `App\Http\Controllers\BackEnd\EventCancellationController@openCaseDispute` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management, permission:Transaction` |
| 163 | `POST` | `admin/event-cancellation-cases/{case}/resolve` | `admin.event_cancellation_cases.resolve` | `App\Http\Controllers\BackEnd\EventCancellationController@resolveCase` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management, permission:Transaction` |
| 164 | `GET\|HEAD` | `admin/event-cancellation-exports` | `admin.event_cancellation_exports.index` | `App\Http\Controllers\BackEnd\EventCancellationExportController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management, permission:Transaction` |
| 165 | `GET\|HEAD` | `admin/event-cancellation-exports/{export}/download` | `admin.event_cancellation_exports.download` | `App\Http\Controllers\BackEnd\EventCancellationExportController@download` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management, permission:Transaction, signed` |
| 166 | `GET\|HEAD` | `admin/event-cancellations` | `admin.event_cancellations.index` | `App\Http\Controllers\BackEnd\EventCancellationController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 167 | `POST` | `admin/event-cancellations/{cancellation}/approve` | `admin.event_cancellations.approve` | `App\Http\Controllers\BackEnd\EventCancellationController@approve` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management, permission:Transaction` |
| 168 | `POST` | `admin/event-cancellations/{cancellation}/cases/export` | `admin.event_cancellation_exports.store` | `App\Http\Controllers\BackEnd\EventCancellationExportController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management, permission:Transaction` |
| 169 | `POST` | `admin/event-cancellations/{cancellation}/reject` | `admin.event_cancellations.reject` | `App\Http\Controllers\BackEnd\EventCancellationController@reject` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management, permission:Transaction` |
| 170 | `POST` | `admin/event-imagermv` | `admin.event.imagermv` | `App\Http\Controllers\BackEnd\Event\EventController@imagermv` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 171 | `GET\|HEAD` | `admin/event-images/{id}` | `admin.event.images` | `App\Http\Controllers\BackEnd\Event\EventController@images` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 172 | `POST` | `admin/event-imagesstore` | `admin.event.imagesstore` | `App\Http\Controllers\BackEnd\Event\EventController@gallerystore` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 173 | `POST` | `admin/event-img-dbrmv` | `admin.event.imgdbrmv` | `App\Http\Controllers\BackEnd\Event\EventController@imagedbrmv` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 174 | `POST` | `admin/event-management/bulk-delete-category` | `admin.event_management.bulk_delete_category` | `App\Http\Controllers\BackEnd\Event\CategoryController@bulkDestroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 175 | `GET\|HEAD` | `admin/event-management/categories` | `admin.event_management.categories` | `App\Http\Controllers\BackEnd\Event\CategoryController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 176 | `POST` | `admin/event-management/categories/reorder` | `admin.event_management.category.reorder` | `App\Http\Controllers\BackEnd\Event\CategoryController@reorder` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 177 | `POST` | `admin/event-management/category/{id}/update-featured` | `admin.event_management.category.update_featured` | `App\Http\Controllers\BackEnd\Event\CategoryController@updateFeatured` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 178 | `POST` | `admin/event-management/category/{id}/update-status` | `admin.event_management.category.update_status` | `App\Http\Controllers\BackEnd\Event\CategoryController@updateStatus` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 179 | `POST` | `admin/event-management/delete-category/{id}` | `admin.event_management.delete_category` | `App\Http\Controllers\BackEnd\Event\CategoryController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 180 | `GET\|HEAD` | `admin/event-management/events` | `admin.event_management.event` | `App\Http\Controllers\BackEnd\Event\EventController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 181 | `POST` | `admin/event-management/store-category` | `admin.event_management.store_category` | `App\Http\Controllers\BackEnd\Event\CategoryController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 182 | `PUT` | `admin/event-management/update-category` | `admin.event_management.update_category` | `App\Http\Controllers\BackEnd\Event\CategoryController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 183 | `POST` | `admin/event-store` | `admin.event_management.store_event` | `App\Http\Controllers\BackEnd\Event\EventController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 184 | `POST` | `admin/event-update` | `admin.event.update` | `App\Http\Controllers\BackEnd\Event\EventController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 185 | `GET\|HEAD` | `admin/event/add-ticket` | `admin.event.add.ticket` | `App\Http\Controllers\BackEnd\Event\TicketController@create` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 186 | `GET\|HEAD` | `admin/event/edit/ticket` | `admin.event.edit.ticket` | `App\Http\Controllers\BackEnd\Event\TicketController@edit` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 187 | `GET\|HEAD` | `admin/event/ticket` | `admin.event.ticket` | `App\Http\Controllers\BackEnd\Event\TicketController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 188 | `POST` | `admin/event/ticket/delete-ticket` | `admin.ticket_management.delete_ticket` | `App\Http\Controllers\BackEnd\Event\TicketController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 189 | `POST` | `admin/event/ticket/store-ticket` | `admin.ticket_management.store_ticket` | `App\Http\Controllers\BackEnd\Event\TicketController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 190 | `POST` | `admin/event/{event}/cancellation` | `admin.event_cancellations.store` | `App\Http\Controllers\BackEnd\EventCancellationController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 191 | `POST` | `admin/event/{id}/update-featured` | `admin.event_management.event.update_featured` | `App\Http\Controllers\BackEnd\Event\EventController@updateFeatured` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 192 | `POST` | `admin/event/{id}/update-status` | `admin.event_management.event.event_status` | `App\Http\Controllers\BackEnd\Event\EventController@updateStatus` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 193 | `GET\|HEAD` | `admin/event/{slug}/{id}/preview` | `admin.event.preview` | `App\Http\Controllers\FrontEnd\EventController@preview` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 194 | `GET\|HEAD` | `admin/faq-management` | `admin.faq_management` | `App\Http\Controllers\BackEnd\FaqController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:FAQ Management` |
| 195 | `POST` | `admin/faq-management/bulk-delete-faq` | `admin.faq_management.bulk_delete_faq` | `App\Http\Controllers\BackEnd\FaqController@bulkDestroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:FAQ Management` |
| 196 | `POST` | `admin/faq-management/delete-faq/{id}` | `admin.faq_management.delete_faq` | `App\Http\Controllers\BackEnd\FaqController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:FAQ Management` |
| 197 | `POST` | `admin/faq-management/store-faq` | `admin.faq_management.store_faq` | `App\Http\Controllers\BackEnd\FaqController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:FAQ Management` |
| 198 | `POST` | `admin/faq-management/update-faq` | `admin.faq_management.update_faq` | `App\Http\Controllers\BackEnd\FaqController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:FAQ Management` |
| 199 | `GET\|HEAD` | `admin/footer/contact-page` | `admin.contact.page` | `App\Http\Controllers\BackEnd\ContactController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Footer` |
| 200 | `GET\|HEAD` | `admin/footer/content` | `admin.footer.content` | `App\Http\Controllers\BackEnd\Footer\ContentController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Footer` |
| 201 | `POST` | `admin/footer/create-quick-link` | `admin.footer.create_quick_link` | `App\Http\Controllers\BackEnd\Footer\QuickLinkController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Footer` |
| 202 | `POST` | `admin/footer/delete-quick-link/{id}` | `admin.footer.delete_quick_link` | `App\Http\Controllers\BackEnd\Footer\QuickLinkController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Footer` |
| 203 | `GET\|HEAD` | `admin/footer/quick-links` | `admin.footer.quick_links` | `App\Http\Controllers\BackEnd\Footer\QuickLinkController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Footer` |
| 204 | `POST` | `admin/footer/update-content` | `admin.footer.update_content` | `App\Http\Controllers\BackEnd\Footer\ContentController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Footer` |
| 205 | `POST` | `admin/footer/update-quick-link` | `admin.footer.update_quick_link` | `App\Http\Controllers\BackEnd\Footer\QuickLinkController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Footer` |
| 206 | `POST` | `admin/footer/update/contact-page/{lagnid}` | `admin.update.contact_page` | `App\Http\Controllers\BackEnd\ContactController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Footer` |
| 207 | `GET\|HEAD` | `admin/forget-password` | `admin.forget_password` | `App\Http\Controllers\BackEnd\AdminController@forgetPassword` | `web, guest:admin` |
| 208 | `GET\|HEAD` | `admin/get-state-city/{id}` | `get.city.state` | `App\Http\Controllers\BackEnd\Event\EventController@city_state` | `web` |
| 209 | `GET\|HEAD` | `admin/guest-checkout` | `admin.event_booking.settings.guest_checkout` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@guestCheckout` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings` |
| 210 | `GET\|HEAD` | `admin/home-page/about-us-section` | `admin.home_page.about_us_section` | `App\Http\Controllers\BackEnd\HomePage\AboutUsController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 211 | `POST` | `admin/home-page/bulk-delete-event-feture` | `admin.home_page.bulk_delete_event_feature` | `App\Http\Controllers\BackEnd\HomePage\EventFeatureController@bulk_delete` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 212 | `POST` | `admin/home-page/bulk-delete-feature` | `admin.home_page.bulk_delete_feature` | `App\Http\Controllers\BackEnd\HomePage\FeatureController@bulkDestroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 213 | `POST` | `admin/home-page/bulk-delete-how-work/item` | `admin.home_page.bulk_delete_how_work_item` | `App\Http\Controllers\BackEnd\HomePage\PartnerController@bulk_delete` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 214 | `POST` | `admin/home-page/bulk-delete-testimonial` | `admin.home_page.bulk_delete_testimonial` | `App\Http\Controllers\BackEnd\HomePage\TestimonialController@bulkDestroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 215 | `POST` | `admin/home-page/delete-event-feture/{id}` | `admin.home_page.delete_event_feature` | `App\Http\Controllers\BackEnd\HomePage\EventFeatureController@delete` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 216 | `POST` | `admin/home-page/delete-feature/{id}` | `admin.home_page.delete_feature` | `App\Http\Controllers\BackEnd\HomePage\FeatureController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 217 | `POST` | `admin/home-page/delete-how-work-item/{id}` | `admin.home_page.delete_how_work_item` | `App\Http\Controllers\BackEnd\HomePage\HowWorkController@delete` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 218 | `POST` | `admin/home-page/delete-partner/{id}` | `admin.home_page.delete_partner` | `App\Http\Controllers\BackEnd\HomePage\PartnerController@delete` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 219 | `POST` | `admin/home-page/delete-testimonial/{id}` | `admin.home_page.delete_testimonial` | `App\Http\Controllers\BackEnd\HomePage\TestimonialController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 220 | `GET\|HEAD` | `admin/home-page/event-features-section` | `admin.home_page.event_features_section` | `App\Http\Controllers\BackEnd\HomePage\EventFeatureController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 221 | `GET\|HEAD` | `admin/home-page/features-section` | `admin.home_page.features_section` | `App\Http\Controllers\BackEnd\HomePage\FeatureController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 222 | `GET\|HEAD` | `admin/home-page/hero-section` | `admin.home_page.hero_section` | `App\Http\Controllers\BackEnd\HomePage\HeroController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 223 | `GET\|HEAD` | `admin/home-page/how-work` | `admin.home_page.how.work` | `App\Http\Controllers\BackEnd\HomePage\HowWorkController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 224 | `GET\|HEAD` | `admin/home-page/partner` | `admin.home_page.partner` | `App\Http\Controllers\BackEnd\HomePage\PartnerController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 225 | `GET\|HEAD` | `admin/home-page/section-customization` | `admin.home_page.section_customization` | `App\Http\Controllers\BackEnd\HomePage\SectionController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 226 | `GET\|HEAD` | `admin/home-page/section-titles` | `admin.home_page.section_titles` | `App\Http\Controllers\BackEnd\HomePage\SectionTitleController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 227 | `POST` | `admin/home-page/store-feature` | `admin.home_page.store_feature` | `App\Http\Controllers\BackEnd\HomePage\FeatureController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 228 | `POST` | `admin/home-page/store-testimonial` | `admin.home_page.store_testimonial` | `App\Http\Controllers\BackEnd\HomePage\TestimonialController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 229 | `POST` | `admin/home-page/store/event/feature` | `admin.home_page.store_event_feature` | `App\Http\Controllers\BackEnd\HomePage\EventFeatureController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 230 | `POST` | `admin/home-page/store/how-work/item` | `admin.home_page.store_how_work_item` | `App\Http\Controllers\BackEnd\HomePage\HowWorkController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 231 | `POST` | `admin/home-page/store/partner` | `admin.home_page.store_partner` | `App\Http\Controllers\BackEnd\HomePage\PartnerController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 232 | `GET\|HEAD` | `admin/home-page/testimonials-section` | `admin.home_page.testimonials_section` | `App\Http\Controllers\BackEnd\HomePage\TestimonialController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 233 | `POST` | `admin/home-page/update-about-us-section` | `admin.home_page.update_about_us_section` | `App\Http\Controllers\BackEnd\HomePage\AboutUsController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 234 | `POST` | `admin/home-page/update-event-features-section` | `admin.home_page.update_event_feature_section` | `App\Http\Controllers\BackEnd\HomePage\EventFeatureController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 235 | `PUT` | `admin/home-page/update-feature` | `admin.home_page.update_feature` | `App\Http\Controllers\BackEnd\HomePage\FeatureController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 236 | `POST` | `admin/home-page/update-feature-section-image` | `admin.home_page.update_feature_section_image` | `App\Http\Controllers\BackEnd\HomePage\FeatureController@updateImage` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 237 | `POST` | `admin/home-page/update-hero-section` | `admin.home_page.update_hero_section` | `App\Http\Controllers\BackEnd\HomePage\HeroController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 238 | `POST` | `admin/home-page/update-how-work` | `admin.home_page.update_how_work` | `App\Http\Controllers\BackEnd\HomePage\HowWorkController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 239 | `POST` | `admin/home-page/update-partner-section` | `admin.home_page.update_partner_section` | `App\Http\Controllers\BackEnd\HomePage\PartnerController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 240 | `POST` | `admin/home-page/update-section-status` | `admin.home_page.update_section_status` | `App\Http\Controllers\BackEnd\HomePage\SectionController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 241 | `POST` | `admin/home-page/update-section-titles` | `admin.home_page.update_section_title` | `App\Http\Controllers\BackEnd\HomePage\SectionTitleController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 242 | `POST` | `admin/home-page/update-testimonial` | `admin.home_page.update_testimonial` | `App\Http\Controllers\BackEnd\HomePage\TestimonialController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 243 | `POST` | `admin/home-page/update-testimonial-section-image` | `admin.home_page.update_testimonial_section_image` | `App\Http\Controllers\BackEnd\HomePage\TestimonialController@updateImage` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 244 | `PUT` | `admin/home-page/update/event/feature` | `admin.home_page.update_event_feature` | `App\Http\Controllers\BackEnd\HomePage\EventFeatureController@update_feature` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 245 | `PUT` | `admin/home-page/update/how-work/item` | `admin.home_page.update_how_work_item` | `App\Http\Controllers\BackEnd\HomePage\HowWorkController@update_feature` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 246 | `PUT` | `admin/home-page/update/partner` | `admin.home_page.update_partner` | `App\Http\Controllers\BackEnd\HomePage\PartnerController@update_partner` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Home Page` |
| 247 | `GET\|HEAD` | `admin/integrations` | `admin.integrations.index` | `App\Http\Controllers\BackEnd\IntegrationController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 248 | `GET\|HEAD` | `admin/integrations/{key}` | `admin.integrations.show` | `App\Http\Controllers\BackEnd\IntegrationController@show` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 249 | `POST` | `admin/integrations/{key}` | `admin.integrations.update` | `App\Http\Controllers\BackEnd\IntegrationController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings, throttle:20,1` |
| 250 | `POST` | `admin/integrations/{key}/generate` | `admin.integrations.generate` | `App\Http\Controllers\BackEnd\IntegrationController@generate` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings, throttle:5,1` |
| 251 | `POST` | `admin/integrations/{key}/test` | `admin.integrations.test` | `App\Http\Controllers\BackEnd\IntegrationController@test` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings, throttle:10,1` |
| 252 | `GET\|HEAD` | `admin/language-management` | `admin.language_management` | `App\Http\Controllers\BackEnd\LanguageController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Language Management` |
| 253 | `POST` | `admin/language-management/add-keyword` | `admin.language_management.add_keyword` | `App\Http\Controllers\BackEnd\LanguageController@addKeyword` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Language Management` |
| 254 | `POST` | `admin/language-management/store-language` | `admin.language_management.store_language` | `App\Http\Controllers\BackEnd\LanguageController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Language Management` |
| 255 | `POST` | `admin/language-management/update-language` | `admin.language_management.update_language` | `App\Http\Controllers\BackEnd\LanguageController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Language Management` |
| 256 | `GET\|HEAD` | `admin/language-management/{id}/check-rtl` | `` | `App\Http\Controllers\BackEnd\LanguageController@checkRTL` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Language Management` |
| 257 | `POST` | `admin/language-management/{id}/delete-language` | `admin.language_management.delete_language` | `App\Http\Controllers\BackEnd\LanguageController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Language Management` |
| 258 | `GET\|HEAD` | `admin/language-management/{id}/edit-keyword` | `admin.language_management.edit_keyword` | `App\Http\Controllers\BackEnd\LanguageController@editKeyword` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Language Management` |
| 259 | `POST` | `admin/language-management/{id}/make-default-language` | `admin.language_management.make_default_language` | `App\Http\Controllers\BackEnd\LanguageController@makeDefault` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Language Management` |
| 260 | `POST` | `admin/language-management/{id}/update-keyword` | `admin.language_management.update_keyword` | `App\Http\Controllers\BackEnd\LanguageController@updateKeyword` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Language Management` |
| 261 | `POST` | `admin/logout` | `admin.logout` | `App\Http\Controllers\BackEnd\AdminController@logout` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired` |
| 262 | `POST` | `admin/mail-for-forget-password` | `admin.mail_for_forget_password` | `App\Http\Controllers\BackEnd\AdminController@sendMail` | `web, guest:admin, throttle:authentication` |
| 263 | `GET\|HEAD` | `admin/mail-for-subscribers` | `admin.user_management.mail_for_subscribers` | `App\Http\Controllers\BackEnd\User\SubscriberController@writeEmail` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Subscribers` |
| 264 | `GET\|HEAD` | `admin/menu-builder` | `admin.menu_builder` | `App\Http\Controllers\BackEnd\MenuBuilderController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Menu Builder` |
| 265 | `POST` | `admin/menu-builder/update-menus` | `admin.menu_builder.update_menus` | `App\Http\Controllers\BackEnd\MenuBuilderController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Menu Builder` |
| 266 | `GET\|HEAD` | `admin/monthly-earning` | `admin.monthly_earning` | `App\Http\Controllers\BackEnd\AdminController@monthly_earning` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired` |
| 267 | `GET\|HEAD` | `admin/monthly-profit` | `admin.monthly_profit` | `App\Http\Controllers\BackEnd\AdminController@monthly_profit` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired` |
| 268 | `GET\|HEAD` | `admin/operations/audit-logs` | `admin.audit_logs.index` | `App\Http\Controllers\BackEnd\AuditLogController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 269 | `POST` | `admin/operations/audit-logs/export` | `admin.audit_logs.export` | `App\Http\Controllers\BackEnd\AuditLogController@export` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 270 | `GET\|HEAD` | `admin/operations/audit-logs/exports/{export}/download` | `admin.audit_logs.download` | `App\Http\Controllers\BackEnd\AuditLogController@download` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction, signed` |
| 271 | `GET\|HEAD` | `admin/operations/exports` | `admin.operations_exports.index` | `App\Http\Controllers\BackEnd\OperationsEvidenceExportController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 272 | `GET\|HEAD` | `admin/operations/exports/{export}/download` | `admin.operations_exports.download` | `App\Http\Controllers\BackEnd\OperationsEvidenceExportController@download` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction, signed` |
| 273 | `GET\|HEAD` | `admin/operations/incidents` | `admin.operational_incidents.index` | `App\Http\Controllers\BackEnd\OperationalIncidentController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 274 | `POST` | `admin/operations/incidents/export` | `admin.operational_incidents.export` | `App\Http\Controllers\BackEnd\OperationsEvidenceExportController@storeIncidents` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 275 | `POST` | `admin/operations/incidents/{incident}/resolve` | `admin.operational_incidents.resolve` | `App\Http\Controllers\BackEnd\OperationalIncidentController@resolve` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 276 | `GET\|HEAD` | `admin/operations/webhooks` | `admin.webhook_operations.index` | `App\Http\Controllers\BackEnd\WebhookOperationsController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 277 | `POST` | `admin/operations/webhooks/export` | `admin.webhook_operations.export` | `App\Http\Controllers\BackEnd\OperationsEvidenceExportController@storeWebhooks` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 278 | `GET\|HEAD` | `admin/organizer-management/add-organzer` | `admin.organizer_management.add_organizer` | `App\Http\Controllers\BackEnd\Organizer\OrganizerManagementController@add` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement, Closure` |
| 279 | `POST` | `admin/organizer-management/bulk-delete-user` | `admin.organizer_management.bulk_delete_organizer` | `App\Http\Controllers\BackEnd\Organizer\OrganizerManagementController@bulkDestroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement, Closure` |
| 280 | `GET\|HEAD` | `admin/organizer-management/kyc` | `admin.organizer_kyc.index` | `App\Http\Controllers\BackEnd\Organizer\OrganizerKycReviewController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement` |
| 281 | `GET\|HEAD` | `admin/organizer-management/kyc/document/{document}` | `admin.organizer_kyc.document` | `App\Http\Controllers\BackEnd\Organizer\OrganizerKycReviewController@download` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement` |
| 282 | `GET\|HEAD` | `admin/organizer-management/kyc/{submission}` | `admin.organizer_kyc.show` | `App\Http\Controllers\BackEnd\Organizer\OrganizerKycReviewController@show` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement` |
| 283 | `POST` | `admin/organizer-management/kyc/{submission}/review` | `admin.organizer_kyc.review` | `App\Http\Controllers\BackEnd\Organizer\OrganizerKycReviewController@review` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement` |
| 284 | `GET\|HEAD` | `admin/organizer-management/organizer/{id}/change-password` | `admin.organizer_management.organizer.change_password` | `App\Http\Controllers\BackEnd\Organizer\OrganizerManagementController@changePassword` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement, Closure` |
| 285 | `POST` | `admin/organizer-management/organizer/{id}/delete` | `admin.organizer_management.organizer.delete` | `App\Http\Controllers\BackEnd\Organizer\OrganizerManagementController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement, Closure` |
| 286 | `GET\|HEAD` | `admin/organizer-management/organizer/{id}/details` | `admin.organizer_management.organizer_details` | `App\Http\Controllers\BackEnd\Organizer\OrganizerManagementController@show` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement, Closure` |
| 287 | `GET\|HEAD` | `admin/organizer-management/organizer/{id}/edit` | `admin.edit_management.organizer_edit` | `App\Http\Controllers\BackEnd\Organizer\OrganizerManagementController@edit` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement, Closure` |
| 288 | `POST` | `admin/organizer-management/organizer/{id}/secret-login` | `admin.organizer_management.organizer.secret_login` | `App\Http\Controllers\BackEnd\Organizer\OrganizerManagementController@secret_login` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement, throttle:10,1, Closure` |
| 289 | `POST` | `admin/organizer-management/organizer/{id}/update` | `admin.organizer_management.organizer.update_organizer` | `App\Http\Controllers\BackEnd\Organizer\OrganizerManagementController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement, Closure` |
| 290 | `POST` | `admin/organizer-management/organizer/{id}/update-account-status` | `admin.organizer_management.organizer.update_account_status` | `App\Http\Controllers\BackEnd\Organizer\OrganizerManagementController@updateAccountStatus` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement, Closure` |
| 291 | `POST` | `admin/organizer-management/organizer/{id}/update-email-status` | `admin.organizer_management.organizer.update_email_status` | `App\Http\Controllers\BackEnd\Organizer\OrganizerManagementController@updateEmailStatus` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement, Closure` |
| 292 | `POST` | `admin/organizer-management/organizer/{id}/update-password` | `admin.organizer_management.organizer.update_password` | `App\Http\Controllers\BackEnd\Organizer\OrganizerManagementController@updatePassword` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement, Closure` |
| 293 | `POST` | `admin/organizer-management/organizer/{id}/update/organizer/balance` | `admin.organizer_management.update_organizer_balance` | `App\Http\Controllers\BackEnd\Organizer\OrganizerManagementController@update_organizer_balance` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement, Closure` |
| 294 | `GET\|HEAD` | `admin/organizer-management/registered-organizers` | `admin.organizer_management.registered_organizer` | `App\Http\Controllers\BackEnd\Organizer\OrganizerManagementController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement, Closure` |
| 295 | `POST` | `admin/organizer-management/registered-organizers/export` | `admin.organizer_management.export` | `App\Http\Controllers\BackEnd\AdminDirectoryExportController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement, throttle:5,1` |
| 296 | `POST` | `admin/organizer-management/save-organzer` | `admin.organizer_management.save-organizer` | `App\Http\Controllers\BackEnd\Organizer\OrganizerManagementController@create` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement, Closure` |
| 297 | `GET\|HEAD` | `admin/organizer-management/settings` | `admin.organizer_management.settings` | `App\Http\Controllers\BackEnd\Organizer\OrganizerManagementController@settings` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement, Closure` |
| 298 | `POST` | `admin/organizer-management/settings/update` | `admin.organizer_management.setting.update` | `App\Http\Controllers\BackEnd\Organizer\OrganizerManagementController@update_setting` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Organizer Mangement, Closure` |
| 299 | `POST` | `admin/payment-gateways/delete-offline-gateway/{id}` | `admin.payment_gateways.delete_offline_gateway` | `App\Http\Controllers\BackEnd\PaymentGateway\OfflineGatewayController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 300 | `GET\|HEAD` | `admin/payment-gateways/offline-gateways` | `admin.payment_gateways.offline_gateways` | `App\Http\Controllers\BackEnd\PaymentGateway\OfflineGatewayController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 301 | `GET\|HEAD` | `admin/payment-gateways/online-gateways` | `admin.payment_gateways.online_gateways` | `App\Http\Controllers\BackEnd\PaymentGateway\OnlineGatewayController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 302 | `POST` | `admin/payment-gateways/store-offline-gateway` | `admin.payment_gateways.store_offline_gateway` | `App\Http\Controllers\BackEnd\PaymentGateway\OfflineGatewayController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 303 | `POST` | `admin/payment-gateways/update-flutterwave-info` | `admin.payment_gateways.update_flutterwave_info` | `App\Http\Controllers\BackEnd\PaymentGateway\OnlineGatewayController@updateFlutterwaveInfo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 304 | `POST` | `admin/payment-gateways/update-instamojo-info` | `admin.payment_gateways.update_instamojo_info` | `App\Http\Controllers\BackEnd\PaymentGateway\OnlineGatewayController@updateInstamojoInfo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 305 | `POST` | `admin/payment-gateways/update-iyzico-info` | `admin.payment_gateways.update_iyzico_info` | `App\Http\Controllers\BackEnd\PaymentGateway\OnlineGatewayController@updateIyzicoInfo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 306 | `POST` | `admin/payment-gateways/update-mercadopago-info` | `admin.payment_gateways.update_mercadopago_info` | `App\Http\Controllers\BackEnd\PaymentGateway\OnlineGatewayController@updateMercadoPagoInfo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 307 | `POST` | `admin/payment-gateways/update-midtrans-info` | `admin.payment_gateways.update_midtrans_info` | `App\Http\Controllers\BackEnd\PaymentGateway\OnlineGatewayController@updateMidtransInfo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 308 | `POST` | `admin/payment-gateways/update-mollie-info` | `admin.payment_gateways.update_mollie_info` | `App\Http\Controllers\BackEnd\PaymentGateway\OnlineGatewayController@updateMollieInfo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 309 | `POST` | `admin/payment-gateways/update-monnify-info` | `admin.payment_gateways.update_monnify_info` | `App\Http\Controllers\BackEnd\PaymentGateway\OnlineGatewayController@updateMonnifyInfo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 310 | `POST` | `admin/payment-gateways/update-myfatoorah-info` | `admin.payment_gateways.update_myfatoorah_info` | `App\Http\Controllers\BackEnd\PaymentGateway\OnlineGatewayController@updateMyFatoorahInfo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 311 | `POST` | `admin/payment-gateways/update-offline-gateway` | `admin.payment_gateways.update_offline_gateway` | `App\Http\Controllers\BackEnd\PaymentGateway\OfflineGatewayController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 312 | `POST` | `admin/payment-gateways/update-paystack-info` | `admin.payment_gateways.update_paystack_info` | `App\Http\Controllers\BackEnd\PaymentGateway\OnlineGatewayController@updatePaystackInfo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 313 | `POST` | `admin/payment-gateways/update-paytabs-info` | `admin.payment_gateways.update_paytabs_info` | `App\Http\Controllers\BackEnd\PaymentGateway\OnlineGatewayController@updatePaytabsInfo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 314 | `POST` | `admin/payment-gateways/update-paytm-info` | `admin.payment_gateways.update_paytm_info` | `App\Http\Controllers\BackEnd\PaymentGateway\OnlineGatewayController@updatePaytmInfo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 315 | `POST` | `admin/payment-gateways/update-perfect_money-info` | `admin.payment_gateways.update_perfect_money_info` | `App\Http\Controllers\BackEnd\PaymentGateway\OnlineGatewayController@updatePerfectMoneyInfo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 316 | `POST` | `admin/payment-gateways/update-phonepe-info` | `admin.payment_gateways.update_phonepe_info` | `App\Http\Controllers\BackEnd\PaymentGateway\OnlineGatewayController@updatePhonepeInfo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 317 | `POST` | `admin/payment-gateways/update-razorpay-info` | `admin.payment_gateways.update_razorpay_info` | `App\Http\Controllers\BackEnd\PaymentGateway\OnlineGatewayController@updateRazorpayInfo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 318 | `POST` | `admin/payment-gateways/update-status/{id}` | `admin.payment_gateways.update_status` | `App\Http\Controllers\BackEnd\PaymentGateway\OfflineGatewayController@updateStatus` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 319 | `POST` | `admin/payment-gateways/update-stripe-info` | `admin.payment_gateways.update_stripe_info` | `App\Http\Controllers\BackEnd\PaymentGateway\OnlineGatewayController@updateStripeInfo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 320 | `POST` | `admin/payment-gateways/update-toyyibpay-info` | `admin.payment_gateways.update_toyyibpay_info` | `App\Http\Controllers\BackEnd\PaymentGateway\OnlineGatewayController@updateToyyibpayInfo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 321 | `POST` | `admin/payment-gateways/update-yoco-info` | `admin.payment_gateways.update_yoco_info` | `App\Http\Controllers\BackEnd\PaymentGateway\OnlineGatewayController@updateYocoInfo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 322 | `POST` | `admin/payment-gateways/update-zendit-info` | `admin.payment_gateways.update_xendit_info` | `App\Http\Controllers\BackEnd\PaymentGateway\OnlineGatewayController@updateXenditInfo` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Payment Gateways` |
| 323 | `GET\|HEAD` | `admin/payment-reconciliation` | `admin.payment_reconciliation.index` | `App\Http\Controllers\BackEnd\PaymentReconciliationController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 324 | `POST` | `admin/payment-reconciliation/export` | `admin.payment_reconciliation.export` | `App\Http\Controllers\BackEnd\ReconciliationExportController@storeRuns` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 325 | `GET\|HEAD` | `admin/payment-reconciliation/{run}` | `admin.payment_reconciliation.show` | `App\Http\Controllers\BackEnd\PaymentReconciliationController@show` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 326 | `POST` | `admin/payment-reconciliation/{run}/entries/export` | `admin.payment_reconciliation.entries.export` | `App\Http\Controllers\BackEnd\ReconciliationExportController@storeRunEntries` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 327 | `GET\|HEAD` | `admin/payout-accounts` | `admin.payout_accounts.index` | `App\Http\Controllers\BackEnd\PayoutAccountController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 328 | `POST` | `admin/payout-accounts/{account}/lock` | `admin.payout_accounts.lock` | `App\Http\Controllers\BackEnd\PayoutAccountController@lock` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 329 | `POST` | `admin/payout-accounts/{account}/reverify` | `admin.payout_accounts.reverify` | `App\Http\Controllers\BackEnd\PayoutAccountController@reverify` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method, throttle:20,1` |
| 330 | `POST` | `admin/payout-accounts/{account}/review` | `admin.payout_accounts.review` | `App\Http\Controllers\BackEnd\PayoutAccountController@review` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 331 | `POST` | `admin/payout-provider` | `admin.payout_provider.update` | `App\Http\Controllers\BackEnd\PayoutAccountController@provider` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 332 | `POST` | `admin/payout-provider/test` | `admin.payout_provider.test` | `App\Http\Controllers\BackEnd\PayoutAccountController@test` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method, throttle:10,1` |
| 333 | `GET\|HEAD` | `admin/platform-settings` | `admin.tica.platform` | `App\Http\Controllers\BackEnd\TicaPlatformController@show` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 334 | `POST` | `admin/platform-settings` | `admin.tica.platform.update` | `App\Http\Controllers\BackEnd\TicaPlatformController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 335 | `GET\|HEAD` | `admin/product/setting` | `admin.product.setting` | `App\Http\Controllers\BackEnd\ShopManagement\ProductController@settings` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 336 | `POST` | `admin/product/setting/update` | `admin.product.setting.update` | `App\Http\Controllers\BackEnd\ShopManagement\ProductController@setting_update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 337 | `GET\|HEAD` | `admin/push-notification/notification-for-visitors` | `admin.user_management.push_notification.notification_for_visitors` | `App\Http\Controllers\BackEnd\User\PushNotificationController@writeNotification` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Push Notification` |
| 338 | `POST` | `admin/push-notification/send-notification` | `admin.user_management.push_notification.send_notification` | `App\Http\Controllers\BackEnd\User\PushNotificationController@sendNotification` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Push Notification` |
| 339 | `GET\|HEAD` | `admin/push-notification/settings` | `admin.user_management.push_notification.settings` | `App\Http\Controllers\BackEnd\User\PushNotificationController@settings` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Push Notification` |
| 340 | `POST` | `admin/push-notification/update-settings` | `admin.user_management.push_notification.update_settings` | `App\Http\Controllers\BackEnd\User\PushNotificationController@updateSettings` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Push Notification` |
| 341 | `GET\|HEAD` | `admin/reconciliation-exports` | `admin.reconciliation_exports.index` | `App\Http\Controllers\BackEnd\ReconciliationExportController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 342 | `GET\|HEAD` | `admin/reconciliation-exports/{export}/download` | `admin.reconciliation_exports.download` | `App\Http\Controllers\BackEnd\ReconciliationExportController@download` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction, signed` |
| 343 | `POST` | `admin/refund-disputes` | `admin.refund_disputes.store` | `App\Http\Controllers\BackEnd\PaymentRefundDisputeController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 344 | `POST` | `admin/refund-disputes/{dispute}/approve` | `admin.refund_disputes.approve` | `App\Http\Controllers\BackEnd\PaymentRefundDisputeController@approve` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 345 | `POST` | `admin/refund-disputes/{dispute}/review` | `admin.refund_disputes.review` | `App\Http\Controllers\BackEnd\PaymentRefundDisputeController@review` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 346 | `POST` | `admin/refunds` | `admin.refunds.store` | `App\Http\Controllers\BackEnd\PaymentRefundController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 347 | `POST` | `admin/refunds/{refund}/approve` | `admin.refunds.approve` | `App\Http\Controllers\BackEnd\PaymentRefundController@approve` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 348 | `POST` | `admin/refunds/{refund}/reject` | `admin.refunds.reject` | `App\Http\Controllers\BackEnd\PaymentRefundController@reject` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 349 | `GET\|HEAD` | `admin/reset-password` | `admin.reset_password` | `App\Http\Controllers\BackEnd\AdminController@resetPassword` | `web, guest:admin` |
| 350 | `POST` | `admin/reset-password` | `admin.update_forgotten_password` | `App\Http\Controllers\BackEnd\AdminController@updateForgottenPassword` | `web, guest:admin, throttle:authentication` |
| 351 | `GET\|HEAD` | `admin/settings` | `admin.settings.index` | `App\Http\Controllers\BackEnd\AdminSettingsController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 352 | `GET\|HEAD` | `admin/settlement-reconciliation` | `admin.settlement_reconciliation.index` | `App\Http\Controllers\BackEnd\PaymentReconciliationController@settlements` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 353 | `POST` | `admin/settlement-reconciliation/export` | `admin.settlement_reconciliation.export` | `App\Http\Controllers\BackEnd\ReconciliationExportController@storeSettlements` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 354 | `GET\|HEAD` | `admin/settlement-reconciliation/{settlement}` | `admin.settlement_reconciliation.show` | `App\Http\Controllers\BackEnd\PaymentReconciliationController@settlementShow` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 355 | `POST` | `admin/settlement-reconciliation/{settlement}/entries/export` | `admin.settlement_reconciliation.entries.export` | `App\Http\Controllers\BackEnd\ReconciliationExportController@storeSettlementEntries` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 356 | `GET\|HEAD` | `admin/shop-management/category` | `admin.shop_management.category` | `App\Http\Controllers\BackEnd\ShopManagement\CategoryController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 357 | `POST` | `admin/shop-management/category/bulk-delete` | `admin.shop_management.bulk_delete_category` | `App\Http\Controllers\BackEnd\ShopManagement\CategoryController@bulk_delete` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 358 | `POST` | `admin/shop-management/category/delete/{id}` | `admin.shop_management.delete_category` | `App\Http\Controllers\BackEnd\ShopManagement\CategoryController@delete` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 359 | `POST` | `admin/shop-management/category/store` | `admin.shop_management.store_category` | `App\Http\Controllers\BackEnd\ShopManagement\CategoryController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 360 | `PUT` | `admin/shop-management/category/update` | `admin.shop_management.update_category` | `App\Http\Controllers\BackEnd\ShopManagement\CategoryController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 361 | `POST` | `admin/shop-management/category/update/feature/{id}` | `admin.shop_management.update_category_feature` | `App\Http\Controllers\BackEnd\ShopManagement\CategoryController@update_featured` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 362 | `GET\|HEAD` | `admin/shop-management/coupon` | `admin.shop_management.coupon` | `App\Http\Controllers\BackEnd\ShopManagement\ShopCouponController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 363 | `POST` | `admin/shop-management/coupon/bulk-delete` | `admin.shop_management.bulk_delete_coupon` | `App\Http\Controllers\BackEnd\ShopManagement\ShopCouponController@bulk_destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 364 | `POST` | `admin/shop-management/coupon/delete` | `admin.shop_management.delete_coupon` | `App\Http\Controllers\BackEnd\ShopManagement\ShopCouponController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 365 | `POST` | `admin/shop-management/coupon/store` | `admin.shop_management.store_coupon` | `App\Http\Controllers\BackEnd\ShopManagement\ShopCouponController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 366 | `PUT` | `admin/shop-management/coupon/update` | `admin.shop_management.update_coupon` | `App\Http\Controllers\BackEnd\ShopManagement\ShopCouponController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 367 | `GET\|HEAD` | `admin/shop-management/order/details/{id}` | `admin.product_order.details` | `App\Http\Controllers\BackEnd\ShopManagement\ProductOrderController@details` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 368 | `GET\|HEAD` | `admin/shop-management/order/{id}/invoice` | `admin.product_order.invoice` | `App\Http\Controllers\BackEnd\ShopManagement\ProductOrderController@invoice` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 369 | `GET\|HEAD` | `admin/shop-management/order/{id}/receipt` | `admin.product_order.receipt` | `App\Http\Controllers\BackEnd\ShopManagement\ProductOrderController@receipt` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 370 | `GET\|HEAD` | `admin/shop-management/orders` | `admin.product.order` | `App\Http\Controllers\BackEnd\ShopManagement\ProductOrderController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 371 | `POST` | `admin/shop-management/orders/bulk-delete` | `admin.product.order.bulk_delete` | `App\Http\Controllers\BackEnd\ShopManagement\ProductOrderController@bulk_delete` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 372 | `POST` | `admin/shop-management/orders/delete/{id}` | `admin.product.order.delete` | `App\Http\Controllers\BackEnd\ShopManagement\ProductOrderController@delete` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 373 | `POST` | `admin/shop-management/orders/refunds` | `admin.product_order.refunds.store` | `App\Http\Controllers\BackEnd\ShopManagement\ProductOrderRefundController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop, permission:Transaction` |
| 374 | `POST` | `admin/shop-management/orders/update-order-status/{id}` | `admin.order.update_order_status` | `App\Http\Controllers\BackEnd\ShopManagement\ProductOrderController@updateOrderStatus` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 375 | `POST` | `admin/shop-management/orders/update-status/{id}` | `admin.order.update_payment_status` | `App\Http\Controllers\BackEnd\ShopManagement\ProductOrderController@updateStatus` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop, permission:Transaction` |
| 376 | `POST` | `admin/shop-management/product-order/export` | `admin.product_order.export` | `App\Http\Controllers\BackEnd\ShopManagement\ProductOrderController@export` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop, permission:Transaction` |
| 377 | `GET\|HEAD` | `admin/shop-management/product-order/exports` | `admin.product_order_exports.index` | `App\Http\Controllers\BackEnd\ShopManagement\ProductOrderExportController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop, permission:Transaction` |
| 378 | `GET\|HEAD` | `admin/shop-management/product-order/exports/{export}/download` | `admin.product_order_exports.download` | `App\Http\Controllers\BackEnd\ShopManagement\ProductOrderExportController@download` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop, permission:Transaction, signed` |
| 379 | `GET\|HEAD` | `admin/shop-management/product-order/report` | `admin.product_order.report` | `App\Http\Controllers\BackEnd\ShopManagement\ProductOrderController@report` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 380 | `POST` | `admin/shop-management/product/bulk-destroy` | `admin.shop_management.product.bulk_delete` | `App\Http\Controllers\BackEnd\ShopManagement\ProductController@bulk_destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 381 | `GET\|HEAD` | `admin/shop-management/product/create` | `admin.shop_management.product.create` | `App\Http\Controllers\BackEnd\ShopManagement\ProductController@create` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 382 | `POST` | `admin/shop-management/product/destroy` | `admin.shop_management.product.destroy` | `App\Http\Controllers\BackEnd\ShopManagement\ProductController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 383 | `GET\|HEAD` | `admin/shop-management/product/edit` | `admin.shop_management.product.edit` | `App\Http\Controllers\BackEnd\ShopManagement\ProductController@edit` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 384 | `POST` | `admin/shop-management/product/feature-update` | `admin.shop_management.product.update_feature` | `App\Http\Controllers\BackEnd\ShopManagement\ProductController@feature_update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 385 | `GET\|HEAD` | `admin/shop-management/product/images/{id}` | `admin.shop_management.product.images` | `App\Http\Controllers\BackEnd\ShopManagement\ProductController@load_images` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 386 | `POST` | `admin/shop-management/product/img-dbrmv` | `admin.shop_management.imgdbrmv` | `App\Http\Controllers\BackEnd\ShopManagement\ProductController@imagedbrmv` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 387 | `POST` | `admin/shop-management/product/img-remove` | `admin.shop_management.product.imgrmv` | `App\Http\Controllers\BackEnd\ShopManagement\ProductController@imgrmv` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 388 | `POST` | `admin/shop-management/product/img-store` | `admin.shop_management.product.imgstore` | `App\Http\Controllers\BackEnd\ShopManagement\ProductController@imgstore` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 389 | `POST` | `admin/shop-management/product/status-update` | `admin.shop_management.product.status_update` | `App\Http\Controllers\BackEnd\ShopManagement\ProductController@status_update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 390 | `POST` | `admin/shop-management/product/store` | `admin.shop_management.product.store` | `App\Http\Controllers\BackEnd\ShopManagement\ProductController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 391 | `GET\|HEAD` | `admin/shop-management/product/type` | `admin.shop_management.product_type` | `App\Http\Controllers\BackEnd\ShopManagement\ProductController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 392 | `POST` | `admin/shop-management/product/update` | `admin.shop_management.product.update` | `App\Http\Controllers\BackEnd\ShopManagement\ProductController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 393 | `GET\|HEAD` | `admin/shop-management/products` | `admin.shop_management.products` | `App\Http\Controllers\BackEnd\ShopManagement\ProductController@show` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 394 | `GET\|HEAD` | `admin/shop-management/shipping` | `admin.shop_management.shipping_charge` | `App\Http\Controllers\BackEnd\ShopManagement\SettingController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 395 | `POST` | `admin/shop-management/shipping/bulk-delete` | `admin.shop_management.bulk_delete_shipping_charge` | `App\Http\Controllers\BackEnd\ShopManagement\SettingController@bulkdelete` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 396 | `POST` | `admin/shop-management/shipping/delete` | `admin.shop_management.delete_shipping` | `App\Http\Controllers\BackEnd\ShopManagement\SettingController@delete` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 397 | `POST` | `admin/shop-management/shipping/store` | `admin.shop_management.store_shipping` | `App\Http\Controllers\BackEnd\ShopManagement\SettingController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 398 | `PUT` | `admin/shop-management/shipping/update` | `admin.shop_management.update_shipping` | `App\Http\Controllers\BackEnd\ShopManagement\SettingController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Shop Management, tica.no-shop` |
| 399 | `POST` | `admin/store-coupon` | `admin.event_management.store_coupon` | `App\Http\Controllers\BackEnd\Event\CouponController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings` |
| 400 | `POST` | `admin/subscriber/{id}/delete` | `admin.user_management.subscriber.delete` | `App\Http\Controllers\BackEnd\User\SubscriberController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Subscribers` |
| 401 | `GET\|HEAD` | `admin/subscribers` | `admin.user_management.subscribers` | `App\Http\Controllers\BackEnd\User\SubscriberController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Subscribers` |
| 402 | `POST` | `admin/subscribers/send-email` | `admin.user_management.subscribers.send_email` | `App\Http\Controllers\BackEnd\User\SubscriberController@sendEmail` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Subscribers` |
| 403 | `POST` | `admin/summernote/remove-image` | `admin.summernote.remove` | `App\Http\Controllers\BackEnd\SummernoteController@remove` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings,Custom Pages,Subscribers,Event Management,Footer,Home Page,Blog Management,Payment Gateways,Shop Management,Support Ticket, throttle:60,1` |
| 404 | `POST` | `admin/summernote/upload-image` | `admin.summernote.upload` | `App\Http\Controllers\BackEnd\SummernoteController@upload` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings,Custom Pages,Subscribers,Event Management,Footer,Home Page,Blog Management,Payment Gateways,Shop Management,Support Ticket, throttle:60,1` |
| 405 | `POST` | `admin/support-ticket/assign-stuff/{id}` | `assign_stuff.supoort.ticket` | `App\Http\Controllers\BackEnd\SupportTicketController@assign_stuff` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Support Ticket` |
| 406 | `POST` | `admin/support-ticket/bulk/delete` | `admin.support_tickets.bulk_delete` | `App\Http\Controllers\BackEnd\SupportTicketController@bulk_delete` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Support Ticket` |
| 407 | `POST` | `admin/support-ticket/closed/{id}` | `admin.support_ticket.close` | `App\Http\Controllers\BackEnd\SupportTicketController@ticket_closed` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Support Ticket` |
| 408 | `POST` | `admin/support-ticket/delete/{id}` | `admin.support_tickets.delete` | `App\Http\Controllers\BackEnd\SupportTicketController@delete` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Support Ticket` |
| 409 | `POST` | `admin/support-ticket/reply/{id}` | `admin.support_ticket.reply` | `App\Http\Controllers\BackEnd\SupportTicketController@ticketreply` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Support Ticket` |
| 410 | `POST` | `admin/support-ticket/unassign-stuff/{id}` | `admin.support_tickets.unassign` | `App\Http\Controllers\BackEnd\SupportTicketController@unassign_stuff` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Support Ticket` |
| 411 | `POST` | `admin/support-ticket/zip-upload` | `admin.support_ticket.zip_file.upload` | `App\Http\Controllers\BackEnd\SupportTicketController@zip_file_upload` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Support Ticket` |
| 412 | `GET\|HEAD` | `admin/support/message/{id}` | `admin.support_tickets.message` | `App\Http\Controllers\BackEnd\SupportTicketController@message` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Support Ticket` |
| 413 | `GET\|HEAD` | `admin/support/tickets` | `admin.support_tickets` | `App\Http\Controllers\BackEnd\SupportTicketController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Support Ticket` |
| 414 | `GET\|HEAD` | `admin/tax-commission` | `admin.event_booking.settings.tax_commission` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@taxCommission` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings` |
| 415 | `GET\|HEAD` | `admin/ticket-management` | `admin.advanced_tickets.index` | `App\Http\Controllers\BackEnd\AdvancedTicketController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 416 | `GET\|HEAD` | `admin/ticket-management/export` | `admin.advanced_tickets.export` | `App\Http\Controllers\BackEnd\AdvancedTicketController@export` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management, throttle:10,1` |
| 417 | `POST` | `admin/ticket-management/issue` | `admin.advanced_tickets.issue` | `App\Http\Controllers\BackEnd\AdvancedTicketController@issue` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management, throttle:20,1` |
| 418 | `POST` | `admin/ticket-management/{ticket}/reissue` | `admin.advanced_tickets.reissue` | `App\Http\Controllers\BackEnd\AdvancedTicketController@reissue` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management, throttle:20,1` |
| 419 | `POST` | `admin/ticket-management/{ticket}/revoke` | `admin.advanced_tickets.revoke` | `App\Http\Controllers\BackEnd\AdvancedTicketController@revoke` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management, throttle:20,1` |
| 420 | `GET\|HEAD` | `admin/ticket/setting` | `admin.support_ticket.setting` | `App\Http\Controllers\BackEnd\SupportTicketController@setting` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Support Ticket` |
| 421 | `POST` | `admin/ticket/setting/update` | `admin.support_ticket.update_setting` | `App\Http\Controllers\BackEnd\SupportTicketController@update_setting` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Support Ticket` |
| 422 | `POST` | `admin/ticket_management/update/ticket` | `admin.ticket_management.update_ticket` | `App\Http\Controllers\BackEnd\Event\TicketController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Management` |
| 423 | `GET\|HEAD` | `admin/transcation` | `admin.transcation` | `App\Http\Controllers\BackEnd\AdminController@transcation` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 424 | `POST` | `admin/transcation/export` | `admin.transaction_exports.store` | `App\Http\Controllers\BackEnd\TransactionExportController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 425 | `GET\|HEAD` | `admin/transcation/exports` | `admin.transaction_exports.index` | `App\Http\Controllers\BackEnd\TransactionExportController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction` |
| 426 | `GET\|HEAD` | `admin/transcation/exports/{export}/download` | `admin.transaction_exports.download` | `App\Http\Controllers\BackEnd\TransactionExportController@download` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Transaction, signed` |
| 427 | `POST` | `admin/update-coupon` | `admin.event_management.update_coupon` | `App\Http\Controllers\BackEnd\Event\CouponController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings` |
| 428 | `POST` | `admin/update-guest-checkout` | `admin.event_booking.settings.update_guest_checkout` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updateGuestCheckout` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings` |
| 429 | `POST` | `admin/update-keywords` | `admin.update_admin_keywords` | `App\Http\Controllers\BackEnd\LanguageController@adminKeywordsUpdate` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Language Management` |
| 430 | `POST` | `admin/update-password` | `admin.update_password` | `App\Http\Controllers\BackEnd\AdminController@updatePassword` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired` |
| 431 | `POST` | `admin/update-profile` | `admin.update_profile` | `App\Http\Controllers\BackEnd\AdminController@updateProfile` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired` |
| 432 | `POST` | `admin/update-tax-commission` | `admin.event_booking.settings.update_tax_commission` | `App\Http\Controllers\BackEnd\BasicSettings\BasicController@updateEventTaxCommission` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings` |
| 433 | `GET\|HEAD` | `admin/website` | `admin.tica.website` | `App\Http\Controllers\BackEnd\TicaWebsiteController@show` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 434 | `POST` | `admin/website/{tab}` | `admin.tica.website.update` | `App\Http\Controllers\BackEnd\TicaWebsiteController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Basic Settings` |
| 435 | `POST` | `admin/withdraw/disputes/{dispute}/review` | `admin.withdraw.disputes.review` | `App\Http\Controllers\BackEnd\WithdrawController@reviewDispute` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 436 | `GET\|HEAD` | `admin/withdraw/payment-method/input` | `admin.withdraw_payment_method.mange_input` | `App\Http\Controllers\BackEnd\WithdrawPaymentMethodInputController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 437 | `POST` | `admin/withdraw/payment-method/input-delete` | `admin.withdraw_payment_method.options_delete` | `App\Http\Controllers\BackEnd\WithdrawPaymentMethodInputController@delete` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 438 | `GET\|HEAD` | `admin/withdraw/payment-method/input-edit/{id}` | `admin.withdraw_payment_method.edit_input` | `App\Http\Controllers\BackEnd\WithdrawPaymentMethodInputController@edit` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 439 | `GET\|HEAD` | `admin/withdraw/payment-method/input-option/{id}` | `admin.withdraw_payment_method.options` | `App\Http\Controllers\BackEnd\WithdrawPaymentMethodInputController@get_options` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 440 | `POST` | `admin/withdraw/payment-method/input-store` | `admin.withdraw_payment_method.store_input` | `App\Http\Controllers\BackEnd\WithdrawPaymentMethodInputController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 441 | `POST` | `admin/withdraw/payment-method/input-update` | `admin.withdraw_payment_method.update_input` | `App\Http\Controllers\BackEnd\WithdrawPaymentMethodInputController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 442 | `POST` | `admin/withdraw/payment-method/order-update` | `admin.withdraw_payment_method.order_update` | `App\Http\Controllers\BackEnd\WithdrawPaymentMethodInputController@order_update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 443 | `GET\|HEAD` | `admin/withdraw/payment-methods` | `admin.withdraw.payment_method` | `App\Http\Controllers\BackEnd\WithdrawPaymentMethodController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 444 | `POST` | `admin/withdraw/payment-methods/delete/{id}` | `admin.withdraw_payment_method.delete` | `App\Http\Controllers\BackEnd\WithdrawPaymentMethodController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 445 | `POST` | `admin/withdraw/payment-methods/store` | `admin.withdraw_payment_method.store` | `App\Http\Controllers\BackEnd\WithdrawPaymentMethodController@store` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 446 | `PUT` | `admin/withdraw/payment-methods/update` | `admin.withdraw_payment_method.update` | `App\Http\Controllers\BackEnd\WithdrawPaymentMethodController@update` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 447 | `GET\|HEAD` | `admin/withdraw/withdraw-request` | `admin.withdraw.withdraw_request` | `App\Http\Controllers\BackEnd\WithdrawController@index` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 448 | `POST` | `admin/withdraw/withdraw-request/approve/{id}` | `admin.witdraw.approve_withdraw` | `App\Http\Controllers\BackEnd\WithdrawController@approve` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 449 | `POST` | `admin/withdraw/withdraw-request/decline/{id}` | `admin.witdraw.decline_withdraw` | `App\Http\Controllers\BackEnd\WithdrawController@decline` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 450 | `POST` | `admin/withdraw/withdraw-request/delete` | `admin.witdraw.delete_withdraw` | `App\Http\Controllers\BackEnd\WithdrawController@delete` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 451 | `POST` | `admin/withdraw/withdraw-request/export` | `admin.withdraw_exports.store` | `App\Http\Controllers\BackEnd\WithdrawController@export` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method, permission:Transaction` |
| 452 | `GET\|HEAD` | `admin/withdraw/withdraw-request/exports` | `admin.withdraw_exports.index` | `App\Http\Controllers\BackEnd\DataExportController@withdrawalIndex` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method, permission:Transaction` |
| 453 | `GET\|HEAD` | `admin/withdraw/withdraw-request/exports/{export}/download` | `admin.withdraw_exports.download` | `App\Http\Controllers\BackEnd\DataExportController@downloadWithdrawal` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method, permission:Transaction, signed` |
| 454 | `POST` | `admin/withdraw/withdraw-request/hold/{id}` | `admin.witdraw.hold_withdraw` | `App\Http\Controllers\BackEnd\WithdrawController@hold` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 455 | `POST` | `admin/withdraw/withdraw-request/paystack/{id}` | `admin.withdraw.submit_paystack` | `App\Http\Controllers\BackEnd\WithdrawController@submitPaystack` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Withdraw Method` |
| 456 | `POST` | `admin/{id}/delete` | `admin.event_booking.delete` | `App\Http\Controllers\BackEnd\Event\EventBookingController@destroy` | `web, auth:admin, Deactive:admin, adminLang, audit.configuration, tica.retired, permission:Event Bookings` |
| 457 | `POST` | `advertisement/{id}/total-view` | `advertisement.count-view` | `App\Http\Controllers\Controller@countAdView` | `web, throttle:120,1` |
| 458 | `GET\|HEAD` | `allocated-registration/{claim}/{token}` | `event.registration-allocation.claim.show` | `App\Http\Controllers\FrontEnd\EventRegistrationAllocationController@claim` | `web, signed, throttle:authentication` |
| 459 | `POST` | `allocated-registration/{claim}/{token}` | `event.registration-allocation.claim.submit` | `App\Http\Controllers\FrontEnd\EventRegistrationAllocationController@submit` | `web, signed, throttle:checkout` |
| 460 | `POST` | `analytics/events` | `analytics.events.store` | `App\Http\Controllers\FrontEnd\AnalyticsController@store` | `web, throttle:60,1` |
| 461 | `GET\|HEAD` | `api/user` | `` | `Closure` | `api, auth:api` |
| 462 | `GET\|HEAD` | `api/vercel/cron` | `vercel.cron` | `App\Http\Controllers\VercelCronController` | `web, throttle:6,1` |
| 463 | `GET\|HEAD` | `assets/{path}` | `public_asset.show` | `App\Http\Controllers\FrontEnd\PublicAssetController@show` | `web` |
| 464 | `GET\|HEAD` | `auth/google/start` | `tica.auth.google` | `App\Http\Controllers\FrontEnd\SocialSignInController@redirect` | `web, throttle:20,1` |
| 465 | `GET\|HEAD` | `blog` | `blogs` | `App\Http\Controllers\FrontEnd\TicaUiController@blog` | `web, change.lang` |
| 466 | `GET\|HEAD` | `blog/{slug}` | `blog_details` | `App\Http\Controllers\FrontEnd\TicaUiController@blogPost` | `web, change.lang` |
| 467 | `GET\|HEAD` | `change-language` | `change_language` | `App\Http\Controllers\Controller@changeLanguage` | `web` |
| 468 | `POST` | `check-out2` | `check-out2` | `App\Http\Controllers\FrontEnd\CheckOutController@checkout2` | `web, change.lang, throttle:checkout` |
| 469 | `GET\|HEAD` | `checkin` | `tica.checkin` | `App\Http\Controllers\FrontEnd\TicaUiController@page` | `web, change.lang, tica.team` |
| 470 | `GET\|HEAD` | `checkout` | `check-out` | `App\Http\Controllers\FrontEnd\CheckOutController@checkout` | `web, change.lang` |
| 471 | `GET\|HEAD` | `checkout/{slug}/{id}` | `tica.checkout` | `App\Http\Controllers\FrontEnd\TicaUiController@checkout` | `web, change.lang` |
| 472 | `GET\|HEAD` | `contact` | `contact` | `App\Http\Controllers\FrontEnd\TicaUiController@page` | `web, change.lang` |
| 473 | `POST` | `contact/send-mail` | `contact.send_mail` | `App\Http\Controllers\FrontEnd\ContactController@sendMail` | `web, throttle:public-form` |
| 474 | `GET\|HEAD` | `create` | `tica.create` | `App\Http\Controllers\FrontEnd\TicaUiController@page` | `web, change.lang, tica.team` |
| 475 | `GET\|HEAD` | `create/{id}` | `tica.edit` | `App\Http\Controllers\FrontEnd\TicaUiController@organizerEdit` | `web, change.lang, auth:organizer, Deactive:organizer, tica.team` |
| 476 | `GET\|HEAD` | `customer/auth/facebook` | `auth.facebook` | `App\Http\Controllers\FrontEnd\CustomerController@facebookRedirect` | `web, change.lang, guest:customer, Closure` |
| 477 | `GET\|HEAD` | `customer/auth/google` | `auth.google` | `App\Http\Controllers\FrontEnd\CustomerController@googleRedirect` | `web, change.lang, guest:customer, Closure` |
| 478 | `GET\|HEAD` | `customer/booking/details/{id}` | `customer.booking_details` | `App\Http\Controllers\FrontEnd\Event\CustomerBookingController@details` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer` |
| 479 | `POST` | `customer/booking/ticket-transfer/{id}/cancel` | `customer.booking.ticket.transfer.cancel` | `App\Http\Controllers\FrontEnd\Event\CustomerBookingController@cancelTransfer` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer` |
| 480 | `GET\|HEAD` | `customer/booking/ticket/{id}/qr` | `customer.booking.ticket.qr` | `App\Http\Controllers\FrontEnd\Event\CustomerBookingController@ticketQr` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer` |
| 481 | `POST` | `customer/booking/ticket/{id}/reissue` | `customer.booking.ticket.reissue` | `App\Http\Controllers\FrontEnd\Event\CustomerBookingController@reissueTicket` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer` |
| 482 | `POST` | `customer/booking/ticket/{id}/transfer` | `customer.booking.ticket.transfer` | `App\Http\Controllers\FrontEnd\Event\CustomerBookingController@transferTicket` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer` |
| 483 | `GET\|HEAD` | `customer/booking/{id}/invoice` | `customer.booking_invoice` | `App\Http\Controllers\FrontEnd\Event\CustomerBookingController@invoice` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer` |
| 484 | `POST` | `customer/booking/{id}/resend-tickets` | `customer.booking.resend-tickets` | `App\Http\Controllers\FrontEnd\Event\CustomerBookingController@resendTickets` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer, throttle:3,10` |
| 485 | `GET\|HEAD` | `customer/booking/{id}/tickets.pdf` | `customer.booking.current-tickets` | `App\Http\Controllers\FrontEnd\Event\CustomerBookingController@currentTickets` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer, throttle:6,1` |
| 486 | `GET\|HEAD` | `customer/change-password` | `customer.change.password` | `App\Http\Controllers\FrontEnd\CustomerController@change_password` | `web, change.lang, auth:customer, Deactive:customer, EmailStatus:customer, Closure` |
| 487 | `POST` | `customer/create` | `customer.create` | `App\Http\Controllers\FrontEnd\CustomerController@create` | `web, change.lang, guest:customer, throttle:authentication, Closure` |
| 488 | `GET\|HEAD` | `customer/dashboard` | `customer.dashboard` | `App\Http\Controllers\FrontEnd\CustomerController@index` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer, Closure` |
| 489 | `GET\|HEAD` | `customer/edit-profile` | `customer.edit.profile` | `App\Http\Controllers\FrontEnd\CustomerController@edit_profile` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer, Closure` |
| 490 | `GET\|HEAD` | `customer/email/verify/{customer}` | `customer.email.verify` | `App\Http\Controllers\FrontEnd\CustomerController@signupVerify` | `web, signed, throttle:authentication, Closure` |
| 491 | `GET\|HEAD` | `customer/forget-password` | `customer.forget.password` | `App\Http\Controllers\FrontEnd\CustomerController@forget_passord` | `web, change.lang, guest:customer, Closure` |
| 492 | `GET\|HEAD` | `customer/login` | `customer.login` | `App\Http\Controllers\FrontEnd\CustomerController@login` | `web, change.lang, guest:customer, Closure` |
| 493 | `POST` | `customer/login-code/request` | `customer.login-code.request` | `App\Http\Controllers\FrontEnd\CustomerController@requestLoginCode` | `web, change.lang, guest:customer, throttle:3,10, Closure` |
| 494 | `POST` | `customer/login-code/verify` | `customer.login-code.verify` | `App\Http\Controllers\FrontEnd\CustomerController@verifyLoginCode` | `web, change.lang, guest:customer, throttle:authentication, Closure` |
| 495 | `POST` | `customer/logout` | `customer.logout` | `App\Http\Controllers\FrontEnd\CustomerController@logout` | `web, change.lang, auth:customer, Closure` |
| 496 | `GET\|HEAD` | `customer/my-bookings` | `customer.booking.my_booking` | `App\Http\Controllers\FrontEnd\Event\CustomerBookingController@my_booking` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer` |
| 497 | `GET\|HEAD` | `customer/my-data` | `customer.data-export.index` | `App\Http\Controllers\FrontEnd\CustomerDataExportController@index` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer` |
| 498 | `POST` | `customer/my-data` | `customer.data-export.store` | `App\Http\Controllers\FrontEnd\CustomerDataExportController@store` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer, throttle:2,60` |
| 499 | `GET\|HEAD` | `customer/my-data/{export}/download` | `customer.data-export.download` | `App\Http\Controllers\FrontEnd\CustomerDataExportController@download` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer, signed, throttle:6,1` |
| 500 | `GET\|HEAD` | `customer/my-orders` | `customer.my_orders` | `App\Http\Controllers\FrontEnd\Shop\CustomerOrderController@index` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer, tica.no-shop` |
| 501 | `GET\|HEAD` | `customer/my-orders/details/{id}` | `customer.order_details` | `App\Http\Controllers\FrontEnd\Shop\CustomerOrderController@details` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer, tica.no-shop` |
| 502 | `GET\|HEAD` | `customer/my-orders/{id}/invoice` | `customer.order_invoice` | `App\Http\Controllers\FrontEnd\Shop\CustomerOrderController@invoice` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer, tica.no-shop` |
| 503 | `POST` | `customer/privacy/deletion-request` | `customer.privacy-request.store` | `App\Http\Controllers\FrontEnd\CustomerPrivacyRequestController@store` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer, throttle:2,1440` |
| 504 | `POST` | `customer/privacy/deletion-request/{privacyRequest}/cancel` | `customer.privacy-request.cancel` | `App\Http\Controllers\FrontEnd\CustomerPrivacyRequestController@cancel` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer, throttle:6,1` |
| 505 | `GET\|HEAD` | `customer/reset-password` | `customer.reset.password` | `App\Http\Controllers\FrontEnd\CustomerController@reset_password` | `web, change.lang, guest:customer, Closure` |
| 506 | `POST` | `customer/send-forget-mail` | `customer.forget.mail` | `App\Http\Controllers\FrontEnd\CustomerController@forget_mail` | `web, change.lang, guest:customer, throttle:authentication, Closure` |
| 507 | `POST` | `customer/send-verify-link` | `customer.send.verify.link` | `App\Http\Controllers\FrontEnd\CustomerController@send_verification_link` | `web, change.lang, guest:customer, throttle:authentication, Closure` |
| 508 | `GET\|HEAD` | `customer/signup` | `customer.signup` | `App\Http\Controllers\FrontEnd\CustomerController@signup` | `web, change.lang, guest:customer, Closure` |
| 509 | `POST` | `customer/store` | `customer.authentication` | `App\Http\Controllers\FrontEnd\CustomerController@authentication` | `web, change.lang, guest:customer, throttle:authentication, Closure` |
| 510 | `GET\|HEAD` | `customer/support-ticket` | `customer.support_tickert` | `App\Http\Controllers\FrontEnd\SupportTicketController@index` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer` |
| 511 | `GET\|HEAD` | `customer/support-ticket/create` | `customer.support_tickert.create` | `App\Http\Controllers\FrontEnd\SupportTicketController@create` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer` |
| 512 | `GET\|HEAD` | `customer/support-ticket/message/{id}` | `customer.support_ticket.message` | `App\Http\Controllers\FrontEnd\SupportTicketController@message` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer` |
| 513 | `POST` | `customer/support-ticket/reply/{id}` | `customer-reply` | `App\Http\Controllers\FrontEnd\SupportTicketController@reply` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer` |
| 514 | `POST` | `customer/support-ticket/store` | `customer.support_ticket.store` | `App\Http\Controllers\FrontEnd\SupportTicketController@store` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer` |
| 515 | `POST` | `customer/update-forget-password` | `customer.update-forget-password` | `App\Http\Controllers\FrontEnd\CustomerController@update_password` | `web, change.lang, guest:customer, throttle:authentication, Closure` |
| 516 | `POST` | `customer/update-password` | `customer.password.update` | `App\Http\Controllers\FrontEnd\CustomerController@updated_password` | `web, change.lang, auth:customer, Deactive:customer, EmailStatus:customer, throttle:authentication, Closure` |
| 517 | `POST` | `customer/update-profile` | `customer.profile.update` | `App\Http\Controllers\FrontEnd\CustomerController@update_profile` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer, Closure` |
| 518 | `GET\|HEAD` | `customer/verify-email` | `customer.verify.email` | `App\Http\Controllers\FrontEnd\CustomerController@verify_email` | `web, change.lang, guest:customer, Closure` |
| 519 | `GET\|HEAD` | `customer/wishlist` | `customer.wishlist` | `App\Http\Controllers\FrontEnd\CustomerController@wishlist` | `web, auth:customer, Deactive:customer, change.lang, EmailStatus:customer, Closure` |
| 520 | `GET\|HEAD` | `dashboard/{section?}` | `tica.dashboard` | `App\Http\Controllers\FrontEnd\TicaUiController@page` | `web, change.lang, tica.team` |
| 521 | `GET\|HEAD` | `discover` | `events` | `App\Http\Controllers\FrontEnd\TicaUiController@page` | `web, change.lang` |
| 522 | `GET\|HEAD` | `event-booking-complete` | `event_booking.complete` | `App\Http\Controllers\FrontEnd\Event\BookingController@complete` | `web, change.lang, signed` |
| 523 | `POST` | `event-booking/apply-coupon` | `apply-coupon` | `App\Http\Controllers\FrontEnd\EventController@applyCoupon` | `web, throttle:checkout` |
| 524 | `GET\|HEAD` | `event-booking/bank-notify` | `bank.notify` | `App\Http\Controllers\FrontEnd\PaymentGateway\MidtransController@bankNotify` | `web, payment.gateway:event,midtrans, throttle:60,1` |
| 525 | `GET\|HEAD` | `event-booking/flutterwave/notify` | `event_booking.flutterwave.notify` | `App\Http\Controllers\FrontEnd\PaymentGateway\FlutterwaveController@notify` | `web, payment.gateway:event,flutterwave, throttle:checkout` |
| 526 | `GET\|HEAD` | `event-booking/flutterwave/recovery/{reference}` | `event_booking.flutterwave.recovery` | `App\Http\Controllers\FrontEnd\PaymentGateway\FlutterwaveController@recovery` | `web, payment.gateway:event,flutterwave, signed, throttle:checkout` |
| 527 | `POST` | `event-booking/flutterwave/recovery/{reference}` | `event_booking.flutterwave.recovery.verify` | `App\Http\Controllers\FrontEnd\PaymentGateway\FlutterwaveController@retryRecovery` | `web, payment.gateway:event,flutterwave, signed, throttle:checkout` |
| 528 | `GET\|HEAD` | `event-booking/instamojo/notify` | `event_booking.instamojo.notify` | `App\Http\Controllers\FrontEnd\PaymentGateway\InstamojoController@notify` | `web, payment.gateway:event,instamojo, throttle:60,1, Closure` |
| 529 | `POST` | `event-booking/iyzico/notify` | `event_booking.iyzico.notify` | `App\Http\Controllers\FrontEnd\PaymentGateway\IyzipayController@notify` | `web, payment.gateway:event,iyzico, throttle:60,1` |
| 530 | `GET\|HEAD` | `event-booking/make-payment` | `makePayment` | `App\Http\Controllers\FrontEnd\PaymentGateway\MidtransController@makePayment` | `web, payment.gateway:event,midtrans, throttle:60,1` |
| 531 | `GET\|HEAD` | `event-booking/mercadopago/notify` | `event_booking.mercadopago.notify` | `App\Http\Controllers\FrontEnd\PaymentGateway\MercadoPagoController@notify` | `web, payment.gateway:event,mercadopago, throttle:60,1, Closure` |
| 532 | `GET\|HEAD` | `event-booking/mollie/notify` | `event_booking.mollie.notify` | `App\Http\Controllers\FrontEnd\PaymentGateway\MollieController@notify` | `web, payment.gateway:event,mollie, throttle:60,1` |
| 533 | `GET\|HEAD` | `event-booking/monnify/notify` | `event_booking.monnify.notify` | `App\Http\Controllers\FrontEnd\PaymentGateway\MonnifyController@notify` | `web, payment.gateway:event,monnify, throttle:checkout` |
| 534 | `GET\|HEAD` | `event-booking/monnify/recovery/{reference}` | `event_booking.monnify.recovery` | `App\Http\Controllers\FrontEnd\PaymentGateway\MonnifyController@recovery` | `web, payment.gateway:event,monnify, signed, throttle:checkout` |
| 535 | `POST` | `event-booking/monnify/recovery/{reference}` | `event_booking.monnify.recovery.verify` | `App\Http\Controllers\FrontEnd\PaymentGateway\MonnifyController@retryRecovery` | `web, payment.gateway:event,monnify, signed, throttle:checkout` |
| 536 | `GET\|HEAD` | `event-booking/notify/{orderId}?` | `event.midtrans.notify` | `App\Http\Controllers\FrontEnd\PaymentGateway\MidtransController@ccNotify` | `web, payment.gateway:event,midtrans, throttle:60,1` |
| 537 | `GET\|HEAD` | `event-booking/paystack/notify` | `event_booking.paystack.notify` | `App\Http\Controllers\FrontEnd\PaymentGateway\PaystackController@notify` | `web, payment.gateway:event,paystack, throttle:checkout` |
| 538 | `GET\|HEAD` | `event-booking/paystack/recovery/{reference}` | `event_booking.paystack.recovery` | `App\Http\Controllers\FrontEnd\PaymentGateway\PaystackController@recovery` | `web, payment.gateway:event,paystack, signed, throttle:checkout` |
| 539 | `POST` | `event-booking/paystack/recovery/{reference}` | `event_booking.paystack.recovery.verify` | `App\Http\Controllers\FrontEnd\PaymentGateway\PaystackController@retryRecovery` | `web, payment.gateway:event,paystack, signed, throttle:checkout` |
| 540 | `GET\|HEAD` | `event-booking/paytabs/make-payment` | `` | `App\Http\Controllers\FrontEnd\PaymentGateway\PaytabsController@makePayment` | `web, payment.gateway:event,paytabs, throttle:60,1` |
| 541 | `POST` | `event-booking/paytabs/notify` | `event_booking.paytabs.notify` | `App\Http\Controllers\FrontEnd\PaymentGateway\PaytabsController@notify` | `web, payment.gateway:event,paytabs, throttle:60,1` |
| 542 | `POST` | `event-booking/paytm/notify` | `event_booking.paytm.notify` | `App\Http\Controllers\FrontEnd\PaymentGateway\PaytmController@notify` | `web, payment.gateway:event,paytm, throttle:60,1` |
| 543 | `GET\|HEAD` | `event-booking/perfect-money/cancel` | `event_booking.perfect-money.cancel` | `App\Http\Controllers\FrontEnd\PaymentGateway\PerfectMoneyController@cancel` | `web, payment.gateway:event,perfect_money, throttle:60,1` |
| 544 | `GET\|HEAD` | `event-booking/perfect-money/notify` | `event_booking.perfect-money.notify` | `App\Http\Controllers\FrontEnd\PaymentGateway\PerfectMoneyController@notify` | `web, payment.gateway:event,perfect_money, throttle:60,1` |
| 545 | `GET\|HEAD\|POST\|PUT\|PATCH\|DELETE\|OPTIONS\|QUERY` | `event-booking/phonepe/notify` | `event_booking.phonepe.notify` | `App\Http\Controllers\FrontEnd\PaymentGateway\PhonepeController@notify` | `web, payment.gateway:event,phonepe, throttle:60,1` |
| 546 | `POST` | `event-booking/razorpay/notify` | `event_booking.razorpay.notify` | `App\Http\Controllers\FrontEnd\PaymentGateway\RazorpayController@notify` | `web, payment.gateway:event,razorpay, throttle:60,1, Closure` |
| 547 | `GET\|HEAD` | `event-booking/stripe/notify` | `event_booking.stripe.notify` | `App\Http\Controllers\FrontEnd\PaymentGateway\StripeController@notify` | `web, payment.gateway:event,stripe, throttle:checkout` |
| 548 | `GET\|HEAD` | `event-booking/stripe/recovery/{reference}` | `event_booking.stripe.recovery` | `App\Http\Controllers\FrontEnd\PaymentGateway\StripeController@recovery` | `web, payment.gateway:event,stripe, signed, throttle:checkout` |
| 549 | `POST` | `event-booking/stripe/recovery/{reference}` | `event_booking.stripe.recovery.verify` | `App\Http\Controllers\FrontEnd\PaymentGateway\StripeController@retryRecovery` | `web, payment.gateway:event,stripe, signed, throttle:checkout` |
| 550 | `GET\|HEAD` | `event-booking/toyyibpay/notify` | `event_booking.toyyibpay.notify` | `App\Http\Controllers\FrontEnd\PaymentGateway\ToyyibpayController@notify` | `web, payment.gateway:event,toyyibpay, throttle:60,1` |
| 551 | `GET\|HEAD` | `event-booking/xendit/notify` | `event_booking.xindit.notify` | `App\Http\Controllers\FrontEnd\PaymentGateway\XenditController@notify` | `web, payment.gateway:event,xendit, throttle:60,1` |
| 552 | `GET\|HEAD` | `event-booking/yoco/notify` | `event_booking.yoco.notify` | `App\Http\Controllers\FrontEnd\PaymentGateway\YocoController@notify` | `web, payment.gateway:event,yoco, throttle:60,1` |
| 553 | `GET\|HEAD` | `event-booking/{id}/cancel` | `event_booking.cancel` | `App\Http\Controllers\FrontEnd\Event\BookingController@cancel` | `web, change.lang, signed` |
| 554 | `GET\|HEAD` | `event-rsvp/{invitation}/{token}` | `event.guest-rsvp.show` | `App\Http\Controllers\FrontEnd\EventGuestRsvpController@show` | `web, signed, throttle:authentication` |
| 555 | `POST` | `event-rsvp/{invitation}/{token}` | `event.guest-rsvp.respond` | `App\Http\Controllers\FrontEnd\EventGuestRsvpController@respond` | `web, signed, throttle:authentication` |
| 556 | `GET\|HEAD` | `event-waitlist/{waitlist}/unsubscribe` | `event.waitlist.unsubscribe.confirm` | `App\Http\Controllers\FrontEnd\EventWaitlistController@confirmUnsubscribe` | `web, signed, throttle:30,1` |
| 557 | `POST` | `event-waitlist/{waitlist}/unsubscribe` | `event.waitlist.unsubscribe` | `App\Http\Controllers\FrontEnd\EventWaitlistController@unsubscribe` | `web, signed, throttle:10,1` |
| 558 | `GET\|HEAD` | `event/{event}/registration` | `event.registration.show` | `App\Http\Controllers\FrontEnd\EventRegistrationController@show` | `web, throttle:60,1` |
| 559 | `POST` | `event/{event}/registration` | `event.registration.store` | `App\Http\Controllers\FrontEnd\EventRegistrationController@store` | `web, throttle:checkout` |
| 560 | `GET\|HEAD` | `event/{event}/registration/embed` | `event.registration.embed.show` | `App\Http\Controllers\FrontEnd\EventRegistrationController@showEmbedded` | `web, throttle:60,1` |
| 561 | `POST` | `event/{event}/registration/embed` | `event.registration.embed.store` | `App\Http\Controllers\FrontEnd\EventRegistrationController@storeEmbedded` | `web, signed, throttle:checkout` |
| 562 | `GET\|HEAD` | `event/{event}/share-card.png` | `event.share-card` | `App\Http\Controllers\FrontEnd\EventShareCardController@show` | `web, change.lang` |
| 563 | `POST` | `event/{event}/waitlist` | `event.waitlist.store` | `App\Http\Controllers\FrontEnd\EventWaitlistController@store` | `web, change.lang, throttle:10,1` |
| 564 | `GET\|HEAD` | `event/{slug}/{id}` | `event.details` | `App\Http\Controllers\FrontEnd\TicaUiController@event` | `web, change.lang` |
| 565 | `GET\|HEAD` | `events` | `` | `Closure` | `web, change.lang` |
| 566 | `GET\|HEAD` | `faq` | `faqs` | `Closure` | `web, change.lang` |
| 567 | `POST` | `flutterwave/webhook` | `flutterwave.webhook` | `App\Http\Controllers\FrontEnd\PaymentGateway\FlutterwaveController@webhook` | `web, webhook.receipt:flutterwave, throttle:120,1` |
| 568 | `GET\|HEAD` | `forgot` | `tica.forgot` | `App\Http\Controllers\FrontEnd\TicaUiController@page` | `web, change.lang` |
| 569 | `GET\|HEAD` | `health/live` | `health.live` | `Closure` | `web` |
| 570 | `GET\|HEAD` | `health/ready` | `health.ready` | `App\Http\Controllers\OperationalHealthController@ready` | `web` |
| 571 | `GET\|HEAD` | `how` | `tica.how` | `App\Http\Controllers\FrontEnd\TicaUiController@page` | `web, change.lang` |
| 572 | `GET\|HEAD` | `join` | `tica.join` | `App\Http\Controllers\FrontEnd\TicaUiController@page` | `web, change.lang` |
| 573 | `GET\|HEAD` | `login` | `login` | `App\Http\Controllers\FrontEnd\TicaUiController@page` | `web, change.lang` |
| 574 | `GET\|HEAD` | `login-verification` | `mfa.login.challenge` | `App\Http\Controllers\AccountSecurityController@challenge` | `web` |
| 575 | `POST` | `login-verification` | `mfa.login.verify` | `App\Http\Controllers\AccountSecurityController@verifyLogin` | `web, throttle:authentication` |
| 576 | `POST` | `login-verification/cancel` | `mfa.login.cancel` | `App\Http\Controllers\AccountSecurityController@cancel` | `web, throttle:authentication` |
| 577 | `GET\|HEAD` | `login/facebook/callback` | `` | `App\Http\Controllers\FrontEnd\CustomerController@handleFacebookCallback` | `web, Closure` |
| 578 | `GET\|HEAD` | `login/google/callback` | `` | `App\Http\Controllers\FrontEnd\SocialSignInController@callback` | `web` |
| 579 | `GET\|HEAD` | `midtrans/cancel` | `midtrans_cancel` | `App\Http\Controllers\FrontEnd\HomeController@midtrans_cancel` | `web` |
| 580 | `POST` | `monnify/webhook` | `monnify.webhook` | `App\Http\Controllers\FrontEnd\PaymentGateway\MonnifyController@webhook` | `web, payment.gateway:event,monnify, webhook.receipt:monnify, throttle:120,1` |
| 581 | `GET\|HEAD` | `myfatoorah/callback` | `myfatoorah_callback` | `App\Http\Controllers\FrontEnd\HomeController@myfatoorah_callback` | `web, payment.gateway:event,myfatoorah, throttle:60,1` |
| 582 | `GET\|HEAD` | `myfatoorah/cancel` | `myfatoorah_cancel` | `App\Http\Controllers\FrontEnd\HomeController@myfatoorah_cancel` | `web` |
| 583 | `GET\|HEAD` | `offline` | `` | `App\Http\Controllers\FrontEnd\HomeController@offline` | `web` |
| 584 | `GET\|HEAD` | `organizer/add-event` | `organizer.add.event.event` | `App\Http\Controllers\BackEnd\Organizer\OrganizerWorkspaceRedirectController@create` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 585 | `POST` | `organizer/bulk/delete/bulk/event/ticket` | `organizer.event_management.bulk_delete_event_ticket` | `App\Http\Controllers\BackEnd\Organizer\TicketController@bulk_delete` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 586 | `POST` | `organizer/bulk/delete/event` | `organizer.event_management.bulk_delete_event` | `App\Http\Controllers\BackEnd\Organizer\EventController@bulk_delete` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 587 | `GET\|HEAD` | `organizer/change-password` | `organizer.change.password` | `App\Http\Controllers\BackEnd\Organizer\OrganizerWorkspaceRedirectController@settings` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team` |
| 588 | `POST` | `organizer/change-theme` | `organizer.change_theme` | `App\Http\Controllers\BackEnd\Organizer\OrganizerController@changeTheme` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, Closure` |
| 589 | `GET\|HEAD` | `organizer/check-in-ledger/exports` | `organizer.check_in_ledger_exports.index` | `App\Http\Controllers\BackEnd\Organizer\CheckInLedgerExportController@index` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 590 | `GET\|HEAD` | `organizer/check-in-ledger/exports/{export}/download` | `organizer.check_in_ledger_exports.download` | `App\Http\Controllers\BackEnd\Organizer\CheckInLedgerExportController@download` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, signed` |
| 591 | `GET\|HEAD` | `organizer/choose-event-type` | `choose-event-type` | `App\Http\Controllers\BackEnd\Organizer\OrganizerWorkspaceRedirectController@create` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 592 | `POST` | `organizer/create` | `organizer.create` | `App\Http\Controllers\BackEnd\Organizer\OrganizerController@create` | `web, guest:organizer, change.lang, adminLang, throttle:authentication, Closure` |
| 593 | `GET\|HEAD` | `organizer/dashboard` | `organizer.dashboard` | `App\Http\Controllers\BackEnd\Organizer\OrganizerWorkspaceRedirectController@overview` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 594 | `POST` | `organizer/delete-date/{id}` | `organizer.event.delete.date` | `App\Http\Controllers\BackEnd\Organizer\EventController@deleteDate` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 595 | `POST` | `organizer/delete-event/{id}` | `organizer.event_management.delete_event` | `App\Http\Controllers\BackEnd\Organizer\EventController@destroy` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 596 | `GET\|HEAD` | `organizer/details/{id}/{name}` | `frontend.organizer.details` | `App\Http\Controllers\FrontEnd\OrganizerController@details` | `web, change.lang` |
| 597 | `GET\|HEAD` | `organizer/edit-event/{id}` | `organizer.event_management.edit_event` | `App\Http\Controllers\BackEnd\Organizer\EventController@edit` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 598 | `GET\|HEAD` | `organizer/edit-profile` | `organizer.edit.profile` | `App\Http\Controllers\BackEnd\Organizer\OrganizerWorkspaceRedirectController@settings` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 599 | `GET\|HEAD` | `organizer/event-booking` | `organizer.event.booking` | `App\Http\Controllers\BackEnd\Organizer\OrganizerWorkspaceRedirectController@orders` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 600 | `POST` | `organizer/event-booking/bulk-delete` | `organizer.event_booking.bulk_delete` | `App\Http\Controllers\BackEnd\Organizer\EventBookingController@bulkDestroy` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 601 | `GET\|HEAD` | `organizer/event-booking/details/{id}` | `organizer.event_booking.details` | `App\Http\Controllers\BackEnd\Organizer\EventBookingController@show` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 602 | `POST` | `organizer/event-booking/export` | `organizer.event_bookings.export` | `App\Http\Controllers\BackEnd\Organizer\EventBookingController@export` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 603 | `POST` | `organizer/event-booking/export-schedules` | `organizer.data_export_schedules.store` | `App\Http\Controllers\BackEnd\Organizer\DataExportController@storeSchedule` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 604 | `POST` | `organizer/event-booking/export-schedules/{schedule}/toggle` | `organizer.data_export_schedules.toggle` | `App\Http\Controllers\BackEnd\Organizer\DataExportController@toggleSchedule` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 605 | `GET\|HEAD` | `organizer/event-booking/exports` | `organizer.data_exports.index` | `App\Http\Controllers\BackEnd\Organizer\DataExportController@index` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 606 | `GET\|HEAD` | `organizer/event-booking/exports/{export}/download` | `organizer.data_exports.download` | `App\Http\Controllers\BackEnd\Organizer\DataExportController@download` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, signed` |
| 607 | `POST` | `organizer/event-booking/refund-disputes` | `organizer.refund_disputes.store` | `App\Http\Controllers\BackEnd\Organizer\PaymentRefundDisputeController@store` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 608 | `POST` | `organizer/event-booking/refunds` | `organizer.refunds.store` | `App\Http\Controllers\BackEnd\Organizer\PaymentRefundController@store` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 609 | `GET\|HEAD` | `organizer/event-booking/report` | `organizer.event_booking.report` | `App\Http\Controllers\BackEnd\Organizer\EventBookingController@report` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 610 | `POST` | `organizer/event-booking/ticket/{id}/reissue` | `organizer.event_booking.ticket.reissue` | `App\Http\Controllers\BackEnd\Organizer\EventBookingController@reissueTicket` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 611 | `POST` | `organizer/event-booking/ticket/{id}/revoke` | `organizer.event_booking.ticket.revoke` | `App\Http\Controllers\BackEnd\Organizer\EventBookingController@revokeTicket` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 612 | `POST` | `organizer/event-booking/update/payment-status/{id}` | `organizer.event_booking.update_payment_status` | `App\Http\Controllers\BackEnd\Organizer\EventBookingController@updatePaymentStatus` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 613 | `GET\|HEAD` | `organizer/event-booking/{id}/attachment` | `organizer.event_booking.attachment` | `App\Http\Controllers\BackEnd\Organizer\EventBookingController@attachment` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 614 | `GET\|HEAD` | `organizer/event-booking/{id}/invoice` | `organizer.event_booking.invoice` | `App\Http\Controllers\BackEnd\Organizer\EventBookingController@invoice` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 615 | `POST` | `organizer/event-booking/{id}/resend-tickets` | `organizer.event_booking.resend-tickets` | `App\Http\Controllers\BackEnd\Organizer\EventBookingController@resendTickets` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, throttle:3,10` |
| 616 | `POST` | `organizer/event-imagermv` | `organizer.event.imagermv` | `App\Http\Controllers\BackEnd\Organizer\EventController@imagermv` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 617 | `GET\|HEAD` | `organizer/event-images/{id}` | `organizer.event.images` | `App\Http\Controllers\BackEnd\Organizer\EventController@images` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 618 | `POST` | `organizer/event-imagesstore` | `organizer.event.imagesstore` | `App\Http\Controllers\BackEnd\Organizer\EventController@gallerystore` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 619 | `POST` | `organizer/event-img-dbrmv` | `organizer.event.imgdbrmv` | `App\Http\Controllers\BackEnd\Organizer\EventController@imagedbrmv` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 620 | `GET\|HEAD` | `organizer/event-management/events` | `organizer.event_management.event` | `App\Http\Controllers\BackEnd\Organizer\OrganizerWorkspaceRedirectController@events` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 621 | `POST` | `organizer/event-store` | `organizer.event_management.store_event` | `App\Http\Controllers\BackEnd\Organizer\EventController@store` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 622 | `POST` | `organizer/event-update` | `organizer.event.update` | `App\Http\Controllers\BackEnd\Organizer\EventController@update` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 623 | `GET\|HEAD` | `organizer/event/add-ticket` | `organizer.event.add.ticket` | `App\Http\Controllers\BackEnd\Organizer\TicketController@create` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 624 | `GET\|HEAD` | `organizer/event/edit/ticket` | `organizer.event.edit.ticket` | `App\Http\Controllers\BackEnd\Organizer\TicketController@edit` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 625 | `GET\|HEAD` | `organizer/event/guest-list` | `organizer.event.guest-list.index` | `App\Http\Controllers\BackEnd\Organizer\EventGuestListController@index` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 626 | `POST` | `organizer/event/guest-list` | `organizer.event.guest-list.store` | `App\Http\Controllers\BackEnd\Organizer\EventGuestListController@store` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, throttle:checkout` |
| 627 | `POST` | `organizer/event/guest-list/export` | `organizer.event.guest-list.export` | `App\Http\Controllers\BackEnd\Organizer\EventGuestListController@export` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 628 | `POST` | `organizer/event/guest-list/import` | `organizer.event.guest-list.import` | `App\Http\Controllers\BackEnd\Organizer\EventGuestListController@import` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, throttle:10,1` |
| 629 | `GET\|HEAD` | `organizer/event/guest-list/import-template` | `organizer.event.guest-list.import-template` | `App\Http\Controllers\BackEnd\Organizer\EventGuestListController@template` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 630 | `POST` | `organizer/event/guest-list/{invitation}/cancel` | `organizer.event.guest-list.cancel` | `App\Http\Controllers\BackEnd\Organizer\EventGuestListController@cancel` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 631 | `POST` | `organizer/event/guest-list/{invitation}/check-in` | `organizer.event.guest-list.check-in` | `App\Http\Controllers\BackEnd\Organizer\EventGuestListController@checkIn` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, throttle:scanner` |
| 632 | `GET\|HEAD` | `organizer/event/registrations` | `organizer.event.registrations.index` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@index` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 633 | `POST` | `organizer/event/registrations/allocations/{allocation}/cancel` | `organizer.event.registrations.allocations.cancel` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@cancelAllocation` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 634 | `POST` | `organizer/event/registrations/export` | `organizer.event.registrations.export` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@export` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 635 | `GET\|HEAD` | `organizer/event/registrations/files/{file}` | `organizer.event.registrations.files.download` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@downloadFile` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 636 | `POST` | `organizer/event/registrations/form` | `organizer.event.registrations.form.save` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@saveForm` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 637 | `POST` | `organizer/event/registrations/form/{form}/allocations` | `organizer.event.registrations.allocations.store` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@createAllocation` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, throttle:checkout` |
| 638 | `POST` | `organizer/event/registrations/form/{form}/badge-settings` | `organizer.event.registrations.badges.settings` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@saveBadgeSettings` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 639 | `GET\|HEAD` | `organizer/event/registrations/form/{form}/badges` | `organizer.event.registrations.badges.download` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@downloadBadges` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 640 | `POST` | `organizer/event/registrations/form/{form}/embed-settings` | `organizer.event.registrations.embed.settings` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@saveEmbedSettings` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 641 | `GET\|HEAD` | `organizer/event/registrations/form/{form}/import-template` | `organizer.event.registrations.import-template` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@template` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 642 | `POST` | `organizer/event/registrations/form/{form}/publish` | `organizer.event.registrations.form.publish` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@publish` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 643 | `POST` | `organizer/event/registrations/form/{form}/questions` | `organizer.event.registrations.questions.store` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@addQuestion` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 644 | `POST` | `organizer/event/registrations/form/{form}/questions/reorder` | `organizer.event.registrations.questions.reorder` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@reorderQuestions` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 645 | `POST` | `organizer/event/registrations/form/{form}/sessions` | `organizer.event.registrations.sessions.save` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@saveSession` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 646 | `POST` | `organizer/event/registrations/form/{form}/templates` | `organizer.event.registrations.templates.save` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@saveTemplate` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 647 | `POST` | `organizer/event/registrations/form/{form}/templates/{template}/apply` | `organizer.event.registrations.templates.apply` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@applyTemplate` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 648 | `POST` | `organizer/event/registrations/import` | `organizer.event.registrations.import` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@import` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, throttle:10,1` |
| 649 | `GET\|HEAD` | `organizer/event/registrations/invoices/{invoice}` | `organizer.event.registrations.invoices.show` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@invoice` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 650 | `POST` | `organizer/event/registrations/invoices/{invoice}/paid` | `organizer.event.registrations.invoices.paid` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@markInvoicePaid` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, throttle:checkout` |
| 651 | `POST` | `organizer/event/registrations/questions/{question}/delete` | `organizer.event.registrations.questions.delete` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@deleteQuestion` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 652 | `POST` | `organizer/event/registrations/questions/{question}/move` | `organizer.event.registrations.questions.move` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@moveQuestion` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 653 | `POST` | `organizer/event/registrations/questions/{question}/update` | `organizer.event.registrations.questions.update` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@updateQuestion` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 654 | `POST` | `organizer/event/registrations/sessions/{session}/cancel` | `organizer.event.registrations.sessions.cancel` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@cancelSession` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 655 | `POST` | `organizer/event/registrations/submissions/{submission}/decision` | `organizer.event.registrations.submissions.decide` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@decide` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 656 | `POST` | `organizer/event/registrations/submissions/{submission}/invoice` | `organizer.event.registrations.invoices.issue` | `App\Http\Controllers\BackEnd\Organizer\EventRegistrationController@issueInvoice` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 657 | `GET\|HEAD` | `organizer/event/ticket` | `organizer.event.ticket` | `App\Http\Controllers\BackEnd\Organizer\OrganizerWorkspaceRedirectController@events` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 658 | `POST` | `organizer/event/ticket/delete-ticket` | `organizer.ticket_management.delete_ticket` | `App\Http\Controllers\BackEnd\Organizer\TicketController@destroy` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 659 | `POST` | `organizer/event/ticket/store-ticket` | `organizer.ticket_management.store_ticket` | `App\Http\Controllers\BackEnd\Organizer\TicketController@store` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 660 | `POST` | `organizer/event/{event}/cancellation` | `organizer.event_cancellations.store` | `App\Http\Controllers\BackEnd\Organizer\EventCancellationController@store` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 661 | `POST` | `organizer/event/{id}/update-featured` | `organizer.event_management.event.update_featured` | `App\Http\Controllers\BackEnd\Organizer\EventController@updateFeatured` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 662 | `POST` | `organizer/event/{id}/update-status` | `organizer.event_management.event.event_status` | `App\Http\Controllers\BackEnd\Organizer\EventController@updateStatus` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 663 | `GET\|HEAD` | `organizer/event/{slug}/{id}/preview` | `organizer.event.preview` | `App\Http\Controllers\FrontEnd\EventController@preview` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 664 | `POST` | `organizer/events/{event}/check-in-ledger/export` | `organizer.check_in_ledger_exports.store` | `App\Http\Controllers\BackEnd\Organizer\CheckInLedgerExportController@store` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 665 | `GET\|HEAD` | `organizer/finance` | `organizer.finance.index` | `App\Http\Controllers\BackEnd\Organizer\OrganizerFinanceController@index` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 666 | `GET\|HEAD` | `organizer/finance/export` | `organizer.finance.export` | `App\Http\Controllers\BackEnd\Organizer\OrganizerFinanceController@export` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 667 | `GET\|HEAD` | `organizer/forget-password` | `organizer.forget.password` | `App\Http\Controllers\BackEnd\Organizer\OrganizerController@forget_passord` | `web, guest:organizer, change.lang, adminLang, Closure` |
| 668 | `GET\|HEAD` | `organizer/forms` | `organizer.forms.index` | `App\Http\Controllers\BackEnd\Organizer\RegistrationFormController@index` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 669 | `POST` | `organizer/forms` | `organizer.forms.store` | `App\Http\Controllers\BackEnd\Organizer\RegistrationFormController@store` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, throttle:30,1` |
| 670 | `GET\|HEAD` | `organizer/forms/{form}` | `organizer.forms.show` | `App\Http\Controllers\BackEnd\Organizer\RegistrationFormController@show` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 671 | `POST` | `organizer/forms/{form}/delete` | `organizer.forms.delete` | `App\Http\Controllers\BackEnd\Organizer\RegistrationFormController@destroy` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 672 | `POST` | `organizer/forms/{form}/duplicate` | `organizer.forms.duplicate` | `App\Http\Controllers\BackEnd\Organizer\RegistrationFormController@duplicate` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, throttle:20,1` |
| 673 | `POST` | `organizer/forms/{form}/events` | `organizer.forms.events` | `App\Http\Controllers\BackEnd\Organizer\RegistrationFormController@useOnEvents` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, throttle:20,1` |
| 674 | `POST` | `organizer/forms/{form}/keep` | `organizer.forms.adopt` | `App\Http\Controllers\BackEnd\Organizer\RegistrationFormController@adopt` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 675 | `POST` | `organizer/forms/{form}/push` | `organizer.forms.push` | `App\Http\Controllers\BackEnd\Organizer\RegistrationFormController@pushChanges` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, throttle:20,1` |
| 676 | `POST` | `organizer/forms/{form}/settings` | `organizer.forms.settings` | `App\Http\Controllers\BackEnd\Organizer\RegistrationFormController@settings` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 677 | `POST` | `organizer/forms/{form}/stop` | `organizer.forms.stop` | `App\Http\Controllers\BackEnd\Organizer\RegistrationFormController@stop` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 678 | `GET\|HEAD` | `organizer/get-withdraw-method/input/{id}` | `` | `App\Http\Controllers\BackEnd\Organizer\OrganizerWithdrawController@get_inputs` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 679 | `POST` | `organizer/impersonation/return` | `organizer.impersonation.return` | `App\Http\Controllers\BackEnd\Organizer\AdminImpersonationController@destroy` | `web, auth:organizer, throttle:10,1` |
| 680 | `GET\|HEAD` | `organizer/kyc` | `organizer.kyc.show` | `App\Http\Controllers\BackEnd\Organizer\OrganizerKycController@show` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 681 | `POST` | `organizer/kyc` | `organizer.kyc.submit` | `App\Http\Controllers\BackEnd\Organizer\OrganizerKycController@submit` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 682 | `GET\|HEAD` | `organizer/kyc/document/{document}` | `organizer.kyc.document` | `App\Http\Controllers\BackEnd\Organizer\OrganizerKycController@download` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 683 | `POST` | `organizer/kyc/payout-account` | `organizer.kyc.payout-account` | `App\Http\Controllers\BackEnd\Organizer\OrganizerKycController@verifyPayoutAccount` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, throttle:checkout` |
| 684 | `GET\|HEAD` | `organizer/login` | `organizer.login` | `App\Http\Controllers\BackEnd\Organizer\OrganizerController@login` | `web, guest:organizer, change.lang, adminLang, Closure` |
| 685 | `POST` | `organizer/logout` | `organizer.logout` | `App\Http\Controllers\BackEnd\Organizer\OrganizerController@logout` | `web, auth:organizer, Closure` |
| 686 | `GET\|HEAD` | `organizer/monthly-income` | `organizer.monthly_income` | `App\Http\Controllers\BackEnd\Organizer\OrganizerWorkspaceRedirectController@overview` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 687 | `POST` | `organizer/organizer-update-profile` | `organizer.update_profile` | `App\Http\Controllers\BackEnd\Organizer\OrganizerController@update_profile` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, Closure` |
| 688 | `GET\|HEAD` | `organizer/payout-account/{account}/lock` | `organizer.payout-account.lock.confirm` | `App\Http\Controllers\BackEnd\Organizer\OrganizerKycController@confirmPayoutLock` | `web, signed, throttle:authentication` |
| 689 | `POST` | `organizer/payout-account/{account}/lock` | `organizer.payout-account.lock` | `App\Http\Controllers\BackEnd\Organizer\OrganizerKycController@lockPayoutAccount` | `web, signed, throttle:authentication` |
| 690 | `GET\|HEAD` | `organizer/pwa` | `organizer.pwa` | `App\Http\Controllers\BackEnd\Organizer\OrganizerController@pwa` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, Closure` |
| 691 | `GET\|HEAD` | `organizer/reset-password` | `organizer.reset.password` | `App\Http\Controllers\BackEnd\Organizer\OrganizerController@reset_password` | `web, guest:organizer, change.lang, adminLang, Closure` |
| 692 | `GET\|HEAD` | `organizer/scanner/devices` | `organizer.scanner.devices.index` | `App\Http\Controllers\BackEnd\Organizer\ScannerDeviceController@index` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 693 | `POST` | `organizer/scanner/devices` | `organizer.scanner.devices.store` | `App\Http\Controllers\BackEnd\Organizer\ScannerDeviceController@store` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, throttle:scanner` |
| 694 | `POST` | `organizer/scanner/devices/{device}/revoke` | `organizer.scanner.devices.revoke` | `App\Http\Controllers\BackEnd\Organizer\ScannerDeviceController@revoke` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 695 | `GET\|HEAD` | `organizer/scanner/staff` | `organizer.scanner.staff.index` | `App\Http\Controllers\BackEnd\Organizer\ScannerStaffController@index` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 696 | `POST` | `organizer/scanner/staff` | `organizer.scanner.staff.store` | `App\Http\Controllers\BackEnd\Organizer\ScannerStaffController@store` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 697 | `POST` | `organizer/scanner/staff/{staff}/update` | `organizer.scanner.staff.update` | `App\Http\Controllers\BackEnd\Organizer\ScannerStaffController@update` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 698 | `POST` | `organizer/send-forget-mail` | `organizer.forget.mail` | `App\Http\Controllers\BackEnd\Organizer\OrganizerController@forget_mail` | `web, guest:organizer, change.lang, adminLang, throttle:authentication, Closure` |
| 699 | `POST` | `organizer/send-verify-link` | `organizer.send.verify.link` | `App\Http\Controllers\BackEnd\Organizer\OrganizerController@send_link` | `web, guest:organizer, change.lang, adminLang, throttle:authentication, Closure` |
| 700 | `GET\|HEAD` | `organizer/signup` | `organizer.signup` | `App\Http\Controllers\BackEnd\Organizer\OrganizerController@signup` | `web, guest:organizer, change.lang, adminLang, Closure` |
| 701 | `POST` | `organizer/store` | `organizer.authentication` | `App\Http\Controllers\BackEnd\Organizer\OrganizerController@authentication` | `web, guest:organizer, change.lang, adminLang, throttle:authentication, Closure` |
| 702 | `POST` | `organizer/support-tikcet/bulk/delete` | `organizer.support_tickets.bulk_delete` | `App\Http\Controllers\BackEnd\Organizer\SupportTicketController@bulk_delete` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 703 | `GET\|HEAD` | `organizer/support-tikcet/create` | `organizer.support_ticket.create` | `App\Http\Controllers\BackEnd\Organizer\SupportTicketController@create` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 704 | `POST` | `organizer/support-tikcet/delete/{id}` | `organizer.support_tickets.delete` | `App\Http\Controllers\BackEnd\Organizer\SupportTicketController@delete` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 705 | `GET\|HEAD` | `organizer/support-tikcet/message/{id}` | `organizer.support_tickets.message` | `App\Http\Controllers\BackEnd\Organizer\SupportTicketController@message` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 706 | `POST` | `organizer/support-tikcet/reply/{id}` | `organizer.support_ticket.reply` | `App\Http\Controllers\BackEnd\Organizer\SupportTicketController@ticketreply` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 707 | `POST` | `organizer/support-tikcet/store` | `organizer.support_ticket.store` | `App\Http\Controllers\BackEnd\Organizer\SupportTicketController@store` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 708 | `GET\|HEAD` | `organizer/support-tikcet/tickets` | `organizer.support_tickets` | `App\Http\Controllers\BackEnd\Organizer\SupportTicketController@index` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 709 | `POST` | `organizer/support-tikcet/zip-upload` | `organizer.support_ticket.zip_file.upload` | `App\Http\Controllers\BackEnd\Organizer\SupportTicketController@zip_file_upload` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 710 | `POST` | `organizer/ticket_management/update/ticket` | `organizer.ticket_management.update_ticket` | `App\Http\Controllers\BackEnd\Organizer\TicketController@update` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 711 | `GET\|HEAD` | `organizer/transaction` | `organizer.transcation` | `App\Http\Controllers\BackEnd\Organizer\OrganizerController@transaction` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, Closure` |
| 712 | `POST` | `organizer/transaction/export` | `organizer.transaction_exports.store` | `App\Http\Controllers\BackEnd\Organizer\TransactionExportController@store` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 713 | `GET\|HEAD` | `organizer/transaction/exports` | `organizer.transaction_exports.index` | `App\Http\Controllers\BackEnd\Organizer\TransactionExportController@index` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 714 | `GET\|HEAD` | `organizer/transaction/exports/{export}/download` | `organizer.transaction_exports.download` | `App\Http\Controllers\BackEnd\Organizer\TransactionExportController@download` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, signed` |
| 715 | `POST` | `organizer/update-forget-password` | `organizer.update-forget-password` | `App\Http\Controllers\BackEnd\Organizer\OrganizerController@update_password` | `web, guest:organizer, change.lang, adminLang, throttle:authentication, Closure` |
| 716 | `POST` | `organizer/update-password` | `organizer.update_password` | `App\Http\Controllers\BackEnd\Organizer\OrganizerController@updated_password` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, throttle:authentication, tica.team, Closure` |
| 717 | `GET\|HEAD` | `organizer/verify-email` | `organizer.verify.email` | `App\Http\Controllers\BackEnd\Organizer\OrganizerController@verify_email` | `web, guest:organizer, change.lang, adminLang, Closure` |
| 718 | `GET\|HEAD` | `organizer/withdraw` | `organizer.withdraw` | `App\Http\Controllers\BackEnd\Organizer\OrganizerWorkspaceRedirectController@payouts` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 719 | `GET\|HEAD` | `organizer/withdraw/balance-calculation/{method}/{amount}` | `` | `App\Http\Controllers\BackEnd\Organizer\OrganizerWithdrawController@balance_calculation` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 720 | `GET\|HEAD` | `organizer/withdraw/create` | `organizer.withdraw.create` | `App\Http\Controllers\BackEnd\Organizer\OrganizerWorkspaceRedirectController@payouts` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 721 | `POST` | `organizer/withdraw/export` | `organizer.withdraw_exports.store` | `App\Http\Controllers\BackEnd\Organizer\OrganizerWithdrawController@export` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 722 | `GET\|HEAD` | `organizer/withdraw/exports` | `organizer.withdraw_exports.index` | `App\Http\Controllers\BackEnd\Organizer\DataExportController@withdrawalIndex` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 723 | `GET\|HEAD` | `organizer/withdraw/exports/{export}/download` | `organizer.withdraw_exports.download` | `App\Http\Controllers\BackEnd\Organizer\DataExportController@downloadWithdrawal` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team, signed` |
| 724 | `POST` | `organizer/withdraw/send-request` | `organizer.withdraw.send-request` | `App\Http\Controllers\BackEnd\Organizer\OrganizerWithdrawController@send_request` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 725 | `POST` | `organizer/withdraw/witdraw/bulk-delete` | `organizer.witdraw.bulk_delete_withdraw` | `App\Http\Controllers\BackEnd\Organizer\OrganizerWithdrawController@bulkDelete` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 726 | `POST` | `organizer/withdraw/witdraw/delete` | `organizer.witdraw.delete_withdraw` | `App\Http\Controllers\BackEnd\Organizer\OrganizerWithdrawController@Delete` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 727 | `POST` | `organizer/withdraw/{withdrawal}/disputes` | `organizer.withdraw.disputes.store` | `App\Http\Controllers\BackEnd\Organizer\OrganizerWithdrawController@openDispute` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 728 | `POST` | `organizer/{id}/delete` | `organizer.event_booking.delete` | `App\Http\Controllers\BackEnd\Organizer\EventBookingController@destroy` | `web, auth:organizer, Deactive:organizer, EmailStatus:organizer, adminLang, tica.team` |
| 729 | `GET\|HEAD` | `organizers` | `frontend.all.organizer` | `App\Http\Controllers\FrontEnd\OrganizerController@index` | `web, change.lang` |
| 730 | `POST` | `organizers/contact/send-mail` | `organizer.contact.send_mail` | `App\Http\Controllers\FrontEnd\OrganizerController@sendMail` | `web, change.lang, throttle:public-form` |
| 731 | `GET\|HEAD` | `organizers/email/verify/{organizer}` | `organizer.email.verify` | `App\Http\Controllers\BackEnd\Organizer\OrganizerController@confirm_email` | `web, signed, throttle:authentication, Closure` |
| 732 | `POST` | `paystack/webhook` | `paystack.webhook` | `App\Http\Controllers\FrontEnd\PaymentGateway\PaystackController@webhook` | `web, webhook.receipt:paystack, throttle:120,1` |
| 733 | `GET\|HEAD` | `policies/{section?}` | `tica.policies` | `App\Http\Controllers\FrontEnd\TicaUiController@page` | `web, change.lang` |
| 734 | `GET\|HEAD` | `pricing` | `pricing` | `App\Http\Controllers\FrontEnd\TicaUiController@page` | `web, change.lang` |
| 735 | `GET\|HEAD` | `product-order-complete/complete/{via?}` | `product_order.complete` | `App\Http\Controllers\FrontEnd\Shop\OrderController@complete` | `web, change.lang, tica.no-shop` |
| 736 | `GET\|HEAD` | `product-order/bank-notify` | `shop.bank.notify` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\MidtransController@bankNotify` | `web, payment.gateway:shop,midtrans, throttle:60,1` |
| 737 | `POST` | `product-order/flutterwave/notify` | `product_order.flutterwave.notify` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\FlutterwaveController@notify` | `web, payment.gateway:shop,flutterwave, throttle:60,1, Closure` |
| 738 | `GET\|HEAD` | `product-order/instamojo/notify` | `product_order.instamojo.notify` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\InstamojoController@notify` | `web, payment.gateway:shop,instamojo, throttle:60,1, Closure` |
| 739 | `POST` | `product-order/iyzico/notify` | `shop.iyzico.notify` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\IyzipayController@notify` | `web, payment.gateway:shop,iyzico, throttle:60,1` |
| 740 | `GET\|HEAD` | `product-order/make-payment` | `shop.makePayment` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\MidtransController@makePayment` | `web, payment.gateway:shop,midtrans, throttle:60,1` |
| 741 | `POST` | `product-order/mercadopago/notify` | `product_order.mercadopago.notify` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\MercadoPagoController@notify` | `web, payment.gateway:shop,mercadopago, throttle:60,1, Closure` |
| 742 | `GET\|HEAD` | `product-order/mollie/notify` | `product_order.mollie.notify` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\MollieController@notify` | `web, payment.gateway:shop,mollie, throttle:60,1` |
| 743 | `GET\|HEAD` | `product-order/notify/{orderId}` | `shop.midtrans.notify` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\MidtransController@ccNotify` | `web, payment.gateway:shop,midtrans, throttle:60,1` |
| 744 | `GET\|HEAD` | `product-order/paystack/notify` | `product_order.paystack.notify` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\PaystackController@notify` | `web, payment.gateway:shop,paystack, throttle:20,1` |
| 745 | `GET\|HEAD` | `product-order/paystack/recovery/{intent}` | `product_order.paystack.recovery` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\PaystackController@recovery` | `web, payment.gateway:shop,paystack, signed, throttle:20,1` |
| 746 | `POST` | `product-order/paystack/recovery/{intent}` | `product_order.paystack.recovery.verify` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\PaystackController@retryRecovery` | `web, payment.gateway:shop,paystack, signed, throttle:10,1` |
| 747 | `GET\|HEAD` | `product-order/paytabs/make-payment` | `shop.paytabs.makePayment` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\PaytabsController@makePayment` | `web, payment.gateway:shop,paytabs, throttle:60,1` |
| 748 | `POST` | `product-order/paytabs/notify` | `shop.paytabs.notify` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\PaytabsController@notify` | `web, payment.gateway:shop,paytabs, throttle:60,1` |
| 749 | `POST` | `product-order/paytm/notify` | `product_order.paytm.notify` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\PaytmController@notify` | `web, payment.gateway:shop,paytm, throttle:60,1` |
| 750 | `GET\|HEAD` | `product-order/perfect-money/cancel` | `shop.perfect-money.cancel` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\PerfectMoneyController@cancel` | `web, payment.gateway:shop,perfect_money, throttle:60,1` |
| 751 | `GET\|HEAD` | `product-order/perfect-money/notify` | `shop.perfect-money.notify` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\PerfectMoneyController@notify` | `web, payment.gateway:shop,perfect_money, throttle:60,1` |
| 752 | `GET\|HEAD\|POST\|PUT\|PATCH\|DELETE\|OPTIONS\|QUERY` | `product-order/phonepe/notify` | `shop.phonepe.notify` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\PhonepeController@notify` | `web, payment.gateway:shop,phonepe, throttle:60,1` |
| 753 | `POST` | `product-order/razorpay/notify` | `product_order.razorpay.notify` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\RazorpayController@notify` | `web, payment.gateway:shop,razorpay, throttle:60,1, Closure` |
| 754 | `GET\|HEAD` | `product-order/toyyibpay/notify` | `shop.toyyibpay.notify` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\ToyyibpayController@notify` | `web, payment.gateway:shop,toyyibpay, throttle:60,1` |
| 755 | `GET\|HEAD` | `product-order/xendit/notify` | `shop.xendit.notify` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\XenditController@notify` | `web, payment.gateway:shop,xendit, throttle:60,1` |
| 756 | `GET\|HEAD` | `product-order/yoco/notify` | `shop.yoco.notify` | `App\Http\Controllers\FrontEnd\Shop\PaymentGateway\YocoController@notify` | `web, payment.gateway:shop,yoco, throttle:60,1` |
| 757 | `GET\|HEAD` | `product-order/{id}/cancel` | `product_order.cancel` | `App\Http\Controllers\FrontEnd\Shop\OrderController@cancel` | `web, change.lang, tica.no-shop` |
| 758 | `GET\|HEAD` | `promotion/paystack/callback` | `tica.promotion.paystack.callback` | `App\Http\Controllers\FrontEnd\PromotionTrackingController@paystackCallback` | `web, throttle:authentication` |
| 759 | `GET\|HEAD` | `promotion/{campaign}/click` | `tica.promotion.click` | `App\Http\Controllers\FrontEnd\PromotionTrackingController@click` | `web, throttle:120,1` |
| 760 | `POST` | `push-notification/store-endpoint` | `push-notification.store-endpoint` | `App\Http\Controllers\FrontEnd\PushNotificationController@store` | `web, throttle:30,1` |
| 761 | `GET\|HEAD` | `registration-allocation/{allocation}/{token}` | `event.registration-allocation.show` | `App\Http\Controllers\FrontEnd\EventRegistrationAllocationController@show` | `web, signed, throttle:authentication` |
| 762 | `POST` | `registration-allocation/{allocation}/{token}` | `event.registration-allocation.invite` | `App\Http\Controllers\FrontEnd\EventRegistrationAllocationController@invite` | `web, signed, throttle:checkout` |
| 763 | `POST` | `remove/wishlist/{id}` | `remove.wishlist` | `App\Http\Controllers\FrontEnd\CustomerController@remove_wishlist` | `web, change.lang, auth:customer, Closure` |
| 764 | `GET\|HEAD` | `robots.txt` | `tica.robots` | `App\Http\Controllers\FrontEnd\TicaSeoController@robots` | `web` |
| 765 | `GET\|HEAD` | `scanner.webmanifest` | `manifest.scanner` | `App\Http\Controllers\FrontEnd\ManifestController@scanner` | `web` |
| 766 | `GET\|HEAD` | `scanner/check-in-ledger/exports` | `organizer.scanner.check_in_ledger_exports.index` | `App\Http\Controllers\BackEnd\Organizer\CheckInLedgerExportController@index` | `web, scanner.access, scanner.supervisor` |
| 767 | `GET\|HEAD` | `scanner/check-in-ledger/exports/{export}/download` | `organizer.scanner.check_in_ledger_exports.download` | `App\Http\Controllers\BackEnd\Organizer\CheckInLedgerExportController@download` | `web, scanner.access, scanner.supervisor, signed` |
| 768 | `POST` | `scanner/check-qrcode` | `check-qrcode` | `App\Http\Controllers\BackEnd\Organizer\OrganizerController@check_qrcode` | `web, scanner.access, scanner.device, throttle:scanner, Closure` |
| 769 | `GET\|HEAD` | `scanner/conflicts` | `organizer.scanner.conflicts.index` | `App\Http\Controllers\BackEnd\Organizer\ScannerConflictController@index` | `web, scanner.access, scanner.supervisor` |
| 770 | `POST` | `scanner/conflicts/{conflict}/resolve` | `organizer.scanner.conflicts.resolve` | `App\Http\Controllers\BackEnd\Organizer\ScannerConflictController@resolve` | `web, scanner.access, scanner.supervisor` |
| 771 | `GET\|HEAD` | `scanner/device/current` | `organizer.scanner.devices.current` | `App\Http\Controllers\BackEnd\Organizer\ScannerDeviceController@current` | `web, scanner.access, scanner.device` |
| 772 | `POST` | `scanner/events/{event}/check-in-ledger/export` | `organizer.scanner.check_in_ledger_exports.store` | `App\Http\Controllers\BackEnd\Organizer\CheckInLedgerExportController@store` | `web, scanner.access, scanner.supervisor` |
| 773 | `GET\|HEAD` | `scanner/events/{event}/metrics` | `organizer.scanner.metrics.show` | `App\Http\Controllers\BackEnd\Organizer\ScannerMetricsController@show` | `web, scanner.access, scanner.device` |
| 774 | `GET\|HEAD` | `scanner/login` | `scanner.login` | `App\Http\Controllers\BackEnd\Organizer\ScannerStaffController@loginForm` | `web, guest:scanner` |
| 775 | `POST` | `scanner/login` | `scanner.authentication` | `App\Http\Controllers\BackEnd\Organizer\ScannerStaffController@login` | `web, guest:scanner, throttle:authentication` |
| 776 | `POST` | `scanner/logout` | `scanner.logout` | `App\Http\Controllers\BackEnd\Organizer\ScannerStaffController@logout` | `web, auth:scanner` |
| 777 | `POST` | `scanner/manual-check-in` | `organizer.scanner.manual-check-in` | `App\Http\Controllers\BackEnd\Organizer\OrganizerController@manualCheckIn` | `web, scanner.access, scanner.device, throttle:scanner, Closure` |
| 778 | `GET\|HEAD` | `scanner/offline/events` | `organizer.scanner.offline.events` | `App\Http\Controllers\BackEnd\Organizer\ScannerOfflineController@events` | `web, scanner.access, scanner.device` |
| 779 | `POST` | `scanner/offline/manifests` | `organizer.scanner.offline.manifests.store` | `App\Http\Controllers\BackEnd\Organizer\ScannerOfflineController@store` | `web, scanner.access, scanner.device, throttle:scanner` |
| 780 | `POST` | `scanner/offline/sync` | `organizer.scanner.offline.sync` | `App\Http\Controllers\BackEnd\Organizer\ScannerOfflineController@sync` | `web, scanner.access, scanner.device, throttle:scanner` |
| 781 | `POST` | `scanner/ticket-lookup` | `organizer.scanner.lookup` | `App\Http\Controllers\BackEnd\Organizer\OrganizerController@ticketLookup` | `web, scanner.access, scanner.device, throttle:scanner, Closure` |
| 782 | `GET\|HEAD` | `scanner/workspace` | `scanner.workspace` | `App\Http\Controllers\BackEnd\Organizer\ScannerStaffController@workspace` | `web, scanner.access` |
| 783 | `GET\|HEAD` | `service-unavailable` | `service_unavailable` | `App\Http\Controllers\Controller@serviceUnavailable` | `web, exists.down` |
| 784 | `GET\|HEAD` | `shop` | `shop` | `App\Http\Controllers\FrontEnd\Shop\ShopController@index` | `web, change.lang, tica.no-shop` |
| 785 | `POST` | `shop/add-to-cart-2/{id}` | `add.cart2` | `App\Http\Controllers\FrontEnd\Shop\ShopController@addToCart2` | `web, change.lang, tica.no-shop, throttle:60,1` |
| 786 | `POST` | `shop/add-to-cart/{id}` | `add.cart` | `App\Http\Controllers\FrontEnd\Shop\ShopController@addToCart` | `web, change.lang, tica.no-shop, throttle:60,1` |
| 787 | `POST` | `shop/apply-coupon` | `shop.apply-coupon` | `App\Http\Controllers\FrontEnd\Shop\ShopController@applyCoupon` | `web, change.lang, tica.no-shop, throttle:checkout` |
| 788 | `POST` | `shop/buy` | `shop.buy` | `App\Http\Controllers\FrontEnd\Shop\OrderController@enrol` | `web, change.lang, tica.no-shop, throttle:checkout` |
| 789 | `GET\|HEAD` | `shop/cart` | `shopping.cart` | `App\Http\Controllers\FrontEnd\Shop\ShopController@cart` | `web, change.lang, tica.no-shop` |
| 790 | `POST` | `shop/cart/item/remove/{id}` | `cart.item.remove` | `App\Http\Controllers\FrontEnd\Shop\ShopController@cartitemremove` | `web, change.lang, tica.no-shop, throttle:60,1` |
| 791 | `POST` | `shop/cart/update` | `cart.update` | `App\Http\Controllers\FrontEnd\Shop\ShopController@updatecart` | `web, change.lang, tica.no-shop, throttle:60,1` |
| 792 | `GET\|HEAD` | `shop/checkout` | `shop.checkout` | `App\Http\Controllers\FrontEnd\Shop\ShopController@checkout` | `web, change.lang, tica.no-shop` |
| 793 | `GET\|HEAD` | `shop/details/{slug}/{id}` | `shop.details` | `App\Http\Controllers\FrontEnd\Shop\ShopController@details` | `web, change.lang, tica.no-shop` |
| 794 | `POST` | `shop/order-now` | `order-now` | `App\Http\Controllers\FrontEnd\Shop\ShopController@orderNow` | `web, change.lang, tica.no-shop, throttle:60,1` |
| 795 | `POST` | `shop/review-submit` | `product.review.submit` | `App\Http\Controllers\FrontEnd\Shop\ShopController@review` | `web, change.lang, tica.no-shop, auth:customer, Deactive:customer, EmailStatus:customer, throttle:10,1` |
| 796 | `GET\|HEAD` | `signup` | `tica.signup` | `App\Http\Controllers\FrontEnd\TicaUiController@page` | `web, change.lang` |
| 797 | `GET\|HEAD` | `site.webmanifest` | `manifest.web` | `App\Http\Controllers\FrontEnd\ManifestController@web` | `web` |
| 798 | `GET\|HEAD` | `sitemap.xml` | `tica.sitemap` | `App\Http\Controllers\FrontEnd\TicaSeoController@sitemap` | `web` |
| 799 | `POST` | `store-subscriber` | `store_subscriber` | `App\Http\Controllers\Controller@storeSubscriber` | `web, throttle:public-form` |
| 800 | `POST` | `stripe/webhook` | `stripe.webhook` | `App\Http\Controllers\FrontEnd\PaymentGateway\StripeController@webhook` | `web, payment.gateway:event,stripe, webhook.receipt:stripe, throttle:120,1` |
| 801 | `GET\|HEAD` | `system/update` | `system.database.show` | `App\Http\Controllers\SystemUpdateController@show` | `web, throttle:30,1` |
| 802 | `POST` | `system/update` | `system.database.apply` | `App\Http\Controllers\SystemUpdateController@apply` | `web, throttle:10,1` |
| 803 | `GET\|HEAD` | `team/accept/{token}` | `tica.team.accept` | `App\Http\Controllers\FrontEnd\TicaUiController@teamAccept` | `web, change.lang` |
| 804 | `POST` | `tica-api/checkin/code` | `tica.api.checkin.code` | `App\Http\Controllers\FrontEnd\TicaApiController@checkinCode` | `web, change.lang, throttle:scanner` |
| 805 | `POST` | `tica-api/checkout` | `tica.api.checkout` | `App\Http\Controllers\FrontEnd\TicaApiController@checkout` | `web, change.lang, throttle:checkout` |
| 806 | `POST` | `tica-api/coupon` | `tica.api.coupon` | `App\Http\Controllers\FrontEnd\TicaApiController@coupon` | `web, change.lang, throttle:checkout` |
| 807 | `POST` | `tica-api/events` | `tica.api.events.store` | `App\Http\Controllers\FrontEnd\TicaOrganizerController@storeEvent` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:20,1` |
| 808 | `GET\|HEAD` | `tica-api/events/{event}/seats` | `tica.api.events.seats` | `App\Http\Controllers\FrontEnd\TicaApiController@seats` | `web, change.lang, throttle:120,1` |
| 809 | `POST` | `tica-api/forgot` | `tica.api.forgot` | `App\Http\Controllers\FrontEnd\TicaApiController@forgot` | `web, change.lang, throttle:authentication` |
| 810 | `POST` | `tica-api/login` | `tica.api.login` | `App\Http\Controllers\FrontEnd\TicaApiController@login` | `web, change.lang, throttle:authentication` |
| 811 | `POST` | `tica-api/login-code` | `tica.api.login-code` | `App\Http\Controllers\FrontEnd\TicaApiController@loginCode` | `web, change.lang, throttle:3,10` |
| 812 | `POST` | `tica-api/login-code/verify` | `tica.api.login-code.verify` | `App\Http\Controllers\FrontEnd\TicaApiController@loginCodeVerify` | `web, change.lang, throttle:authentication` |
| 813 | `POST` | `tica-api/logout` | `tica.api.logout` | `App\Http\Controllers\FrontEnd\TicaApiController@logout` | `web, change.lang, throttle:30,1` |
| 814 | `POST` | `tica-api/order-lookup` | `tica.api.order-lookup` | `App\Http\Controllers\FrontEnd\TicaApiController@orderLookup` | `web, change.lang, throttle:10,1` |
| 815 | `GET\|HEAD` | `tica-api/organizer/attendees` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@attendees` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:120,1` |
| 816 | `GET\|HEAD` | `tica-api/organizer/attendees.csv` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@attendeesCsv` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:10,1` |
| 817 | `POST` | `tica-api/organizer/bank` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@saveBank` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:10,10` |
| 818 | `POST` | `tica-api/organizer/bank/resolve` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@resolveBank` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:10,1` |
| 819 | `GET\|HEAD` | `tica-api/organizer/banks` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@banks` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:30,1` |
| 820 | `POST` | `tica-api/organizer/codes` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@storeCode` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:20,1` |
| 821 | `POST` | `tica-api/organizer/codes/{coupon}` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@updateCode` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:20,1` |
| 822 | `POST` | `tica-api/organizer/codes/{coupon}/delete` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@deleteCode` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:20,1` |
| 823 | `POST` | `tica-api/organizer/events/{id}` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@updateEvent` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:60,1` |
| 824 | `POST` | `tica-api/organizer/events/{id}/cancel` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@cancel` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:5,1` |
| 825 | `POST` | `tica-api/organizer/events/{id}/date` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@changeDate` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:10,1` |
| 826 | `POST` | `tica-api/organizer/events/{id}/details` | `` | `App\Http\Controllers\FrontEnd\TicaOrganizerController@updateOrganizerEvent` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:30,1` |
| 827 | `POST` | `tica-api/organizer/guests` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@guest` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:30,1` |
| 828 | `POST` | `tica-api/organizer/links` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@storeLink` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:20,1` |
| 829 | `POST` | `tica-api/organizer/links/{link}` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@updateLink` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:20,1` |
| 830 | `POST` | `tica-api/organizer/links/{link}/delete` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@deleteLink` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:20,1` |
| 831 | `POST` | `tica-api/organizer/message` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@message` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:5,1` |
| 832 | `GET\|HEAD` | `tica-api/organizer/notifications` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@notifications` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:60,1` |
| 833 | `POST` | `tica-api/organizer/notifications/read` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@readNotifications` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:60,1` |
| 834 | `POST` | `tica-api/organizer/orders/{booking}/refund` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@refund` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:10,1` |
| 835 | `POST` | `tica-api/organizer/orders/{booking}/resend` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@resend` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:20,1` |
| 836 | `POST` | `tica-api/organizer/password` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@password` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:authentication` |
| 837 | `POST` | `tica-api/organizer/payout-schedule` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@payoutSchedule` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:20,1` |
| 838 | `POST` | `tica-api/organizer/prefs` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@prefs` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:60,1` |
| 839 | `POST` | `tica-api/organizer/profile` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@profile` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:20,1` |
| 840 | `POST` | `tica-api/organizer/promotions` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@storePromotion` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:10,1` |
| 841 | `POST` | `tica-api/organizer/promotions/{campaign}/cancel` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@cancelPromotion` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:10,1` |
| 842 | `POST` | `tica-api/organizer/promotions/{campaign}/checkout` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@checkoutPromotion` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:5,10` |
| 843 | `POST` | `tica-api/organizer/promotions/{campaign}/pay` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@payPromotion` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:5,10` |
| 844 | `POST` | `tica-api/organizer/team` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@invite` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:10,1` |
| 845 | `POST` | `tica-api/organizer/team/{member}/remove` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@removeMember` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:20,1` |
| 846 | `POST` | `tica-api/organizer/team/{member}/role` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@changeMemberRole` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:20,1` |
| 847 | `POST` | `tica-api/organizer/tracking-pixels` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@storePixel` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:10,10` |
| 848 | `POST` | `tica-api/organizer/withdraw` | `` | `App\Http\Controllers\FrontEnd\TicaWorkspaceController@withdraw` | `web, change.lang, auth:organizer, Deactive:organizer, EmailStatus:organizer, tica.team, throttle:5,1` |
| 849 | `POST` | `tica-api/signup` | `tica.api.signup` | `App\Http\Controllers\FrontEnd\TicaApiController@signup` | `web, change.lang, throttle:authentication` |
| 850 | `POST` | `tica-api/team/accept` | `tica.api.team.accept` | `App\Http\Controllers\FrontEnd\TicaApiController@acceptTeamInvite` | `web, change.lang, throttle:authentication` |
| 851 | `POST` | `tica-api/waitlist` | `tica.api.waitlist` | `App\Http\Controllers\FrontEnd\TicaApiController@waitlist` | `web, change.lang, throttle:10,1` |
| 852 | `POST` | `tica-api/wishlist` | `tica.api.wishlist` | `App\Http\Controllers\FrontEnd\TicaApiController@wishlist` | `web, change.lang, throttle:60,1` |
| 853 | `POST` | `ticket-booking/{id}` | `ticket.booking` | `App\Http\Controllers\FrontEnd\Event\BookingController@index` | `web, change.lang, throttle:checkout` |
| 854 | `GET\|HEAD` | `ticket-claim/{booking}` | `customer.ticket-claim` | `App\Http\Controllers\FrontEnd\CustomerController@claimTickets` | `web, signed, throttle:authentication, Closure` |
| 855 | `GET\|HEAD` | `ticket-transfer/{transfer}/{token}` | `ticket.transfer.invitation` | `App\Http\Controllers\FrontEnd\Event\CustomerBookingController@showTransferInvitation` | `web, signed, throttle:authentication` |
| 856 | `POST` | `ticket-transfer/{transfer}/{token}/accept` | `ticket.transfer.accept` | `App\Http\Controllers\FrontEnd\Event\CustomerBookingController@acceptTransfer` | `web, auth:customer, Deactive:customer, EmailStatus:customer, signed, throttle:authentication` |
| 857 | `GET\|HEAD` | `tickets` | `tica.tickets` | `App\Http\Controllers\FrontEnd\TicaUiController@page` | `web, change.lang` |
| 858 | `POST` | `xendit/callback` | `xendit_cancel` | `App\Http\Controllers\FrontEnd\HomeController@xendit_callback` | `web, payment.gateway:event,xendit, throttle:60,1` |
| 859 | `GET\|HEAD` | `{fallbackPlaceholder}` | `` | `App\Http\Controllers\FrontEnd\TicaUiController@missing` | `web, change.lang` |
| 860 | `GET\|HEAD` | `{slug}` | `dynamic_page` | `App\Http\Controllers\FrontEnd\TicaUiController@customPage` | `web, change.lang` |

## Controllers

| File | Public actions |
|---|---|
| `app/Http/Controllers/AccountSecurityController.php` | challenge, verifyLogin, cancel, show, requestChange, verifyChange, cancelChange |
| `app/Http/Controllers/BackEnd/AdminController.php` | login, authentication, forgetPassword, sendMail, resetPassword, updateForgottenPassword, redirectToDashboard, changeTheme, editProfile, updateProfile, changePassword, updatePassword, logout, transcation, destroy, bulk_destroy, monthly_earning, monthly_profit |
| `app/Http/Controllers/BackEnd/AdminDirectoryExportController.php` | store, index, download |
| `app/Http/Controllers/BackEnd/Administrator/RolePermissionController.php` | index, store, permissions, updatePermissions, update, destroy |
| `app/Http/Controllers/BackEnd/Administrator/SiteAdminController.php` | index, store, updateStatus, update, destroy |
| `app/Http/Controllers/BackEnd/AdminSettingsController.php` | index |
| `app/Http/Controllers/BackEnd/AdvancedTicketController.php` | index, issue, revoke, reissue, export |
| `app/Http/Controllers/BackEnd/AdvertisementController.php` | index, store, update, destroy, bulkDestroy |
| `app/Http/Controllers/BackEnd/AuditLogController.php` | index, export, download |
| `app/Http/Controllers/BackEnd/BasicSettings/BasicController.php` | information, updateInfo, themeAndHome, updateThemeAndHome, currency, updateCurrency, appearance, updateAppearance, mailFromAdmin, updateMailFromAdmin, mailToAdmin, updateMailToAdmin, breadcrumb, updateBreadcrumb, plugins, updateRecaptcha, updateDisqus, updateWhatsApp, updateFacebook, updateGoogle, maintenance, updateMaintenance, footerLogo, updateFooterLogo, advertiseSettings, updateAdvertiseSettings, pwa, updatePwa, pwa_scanner, updatepwaScanner, guestCheckout, updateGuestCheckout, taxCommission, updateEventTaxCommission, general_settings, update_general_setting |
| `app/Http/Controllers/BackEnd/BasicSettings/CookieAlertController.php` | cookieAlert, updateCookieAlert |
| `app/Http/Controllers/BackEnd/BasicSettings/MailTemplateController.php` | index, edit, update |
| `app/Http/Controllers/BackEnd/BasicSettings/PageHeadingController.php` | pageHeadings, updatePageHeadings |
| `app/Http/Controllers/BackEnd/BasicSettings/SEOController.php` | index, update |
| `app/Http/Controllers/BackEnd/BasicSettings/SocialMediaController.php` | index, store, update, destroy |
| `app/Http/Controllers/BackEnd/ContactController.php` | index, update |
| `app/Http/Controllers/BackEnd/CustomerManagementController.php` | __construct, index, show, create, store, updateAccountStatus, updateEmailStatus, changePassword, updatePassword, edit, update, destroy, bulkDestroy, secret_login |
| `app/Http/Controllers/BackEnd/CustomerPrivacyRequestController.php` | index, update |
| `app/Http/Controllers/BackEnd/CustomPageController.php` | index, create, store, edit, update, destroy, bulkDestroy |
| `app/Http/Controllers/BackEnd/DataExportController.php` | index, withdrawalIndex, storeSchedule, toggleSchedule, download, downloadWithdrawal |
| `app/Http/Controllers/BackEnd/Event/CategoryController.php` | index, store, updateFeatured, updateStatus, reorder, update, destroy, bulkDestroy |
| `app/Http/Controllers/BackEnd/Event/CouponController.php` | index, store, update, destroy |
| `app/Http/Controllers/BackEnd/Event/EventBookingController.php` | index, updatePaymentStatus, generateInvoice, show, invoice, attachment, destroy, bulkDestroy, report, export |
| `app/Http/Controllers/BackEnd/Event/EventController.php` | index, choose_event_type, add_event, gallerystore, imagermv, store, deleteDate, updateStatus, updateFeatured, edit, imagedbrmv, images, update, destroy, bulk_delete |
| `app/Http/Controllers/BackEnd/Event/TicketController.php` | index, create, store, edit, update, destroy, bulk_delete |
| `app/Http/Controllers/BackEnd/EventCancellationController.php` | index, store, approve, reject, openCaseDispute, resolveCase |
| `app/Http/Controllers/BackEnd/EventCancellationExportController.php` | index, store, download |
| `app/Http/Controllers/BackEnd/FaqController.php` | index, store, update, destroy, bulkDestroy |
| `app/Http/Controllers/BackEnd/Footer/ContentController.php` | index, update |
| `app/Http/Controllers/BackEnd/Footer/QuickLinkController.php` | index, store, update, destroy |
| `app/Http/Controllers/BackEnd/FunnelAnalyticsController.php` | index |
| `app/Http/Controllers/BackEnd/HomePage/AboutUsController.php` | index, update |
| `app/Http/Controllers/BackEnd/HomePage/EventFeatureController.php` | index, update, store, update_feature, delete, bulk_delete |
| `app/Http/Controllers/BackEnd/HomePage/FeatureController.php` | index, updateImage, store, update, destroy, bulkDestroy |
| `app/Http/Controllers/BackEnd/HomePage/HeroController.php` | index, update |
| `app/Http/Controllers/BackEnd/HomePage/HowWorkController.php` | index, update, store, update_feature, delete, bulk_delete |
| `app/Http/Controllers/BackEnd/HomePage/PartnerController.php` | index, update, store, update_partner, delete, bulk_delete |
| `app/Http/Controllers/BackEnd/HomePage/SectionController.php` | index, update |
| `app/Http/Controllers/BackEnd/HomePage/SectionTitleController.php` | index, update |
| `app/Http/Controllers/BackEnd/HomePage/TestimonialController.php` | index, updateImage, store, update, destroy, bulkDestroy |
| `app/Http/Controllers/BackEnd/IntegrationController.php` | index, show, update, test, generate |
| `app/Http/Controllers/BackEnd/Journal/BlogController.php` | index, create, store, edit, update, destroy, bulkDestroy |
| `app/Http/Controllers/BackEnd/Journal/CategoryController.php` | index, store, update, destroy, bulkDestroy |
| `app/Http/Controllers/BackEnd/LanguageController.php` | index, store, makeDefault, update, addKeyword, editKeyword, updateKeyword, destroy, checkRTL, adminKeywordsEdit, adminKeywordsUpdate |
| `app/Http/Controllers/BackEnd/MenuBuilderController.php` | index, update |
| `app/Http/Controllers/BackEnd/OperationalIncidentController.php` | index, resolve |
| `app/Http/Controllers/BackEnd/OperationsEvidenceExportController.php` | index, storeIncidents, storeWebhooks, download |
| `app/Http/Controllers/BackEnd/Organizer/AdminImpersonationController.php` | destroy |
| `app/Http/Controllers/BackEnd/Organizer/CheckInLedgerExportController.php` | index, store, download |
| `app/Http/Controllers/BackEnd/Organizer/DataExportController.php` | index, withdrawalIndex, storeSchedule, toggleSchedule, download, downloadWithdrawal |
| `app/Http/Controllers/BackEnd/Organizer/EventBookingController.php` | index, updatePaymentStatus, generateInvoice, show, invoice, resendTickets, attachment, reissueTicket, revokeTicket, destroy, bulkDestroy, report, export |
| `app/Http/Controllers/BackEnd/Organizer/EventCancellationController.php` | store |
| `app/Http/Controllers/BackEnd/Organizer/EventController.php` | deleteDate, index, choose_event_type, add_event, city_state, gallerystore, imagermv, store, updateStatus, updateFeatured, edit, imagedbrmv, images, update, destroy, bulk_delete |
| `app/Http/Controllers/BackEnd/Organizer/EventGuestListController.php` | index, store, cancel, checkIn, import, template, export |
| `app/Http/Controllers/BackEnd/Organizer/EventRegistrationController.php` | index, saveForm, addQuestion, deleteQuestion, updateQuestion, reorderQuestions, moveQuestion, publish, decide, downloadFile, export, import, template, saveTemplate, applyTemplate, createAllocation, cancelAllocation, saveSession, cancelSession, issueInvoice, markInvoicePaid, invoice, saveBadgeSettings, downloadBadges, saveEmbedSettings |
| `app/Http/Controllers/BackEnd/Organizer/OrganizerController.php` | __construct, index, login, signup, create, authentication, forget_passord, forget_mail, reset_password, update_password, logout, change_password, updated_password, edit_profile, update_profile, verify_email, send_link, confirm_email, pwa, check_qrcode, ticketLookup, manualCheckIn, changeTheme, transaction, monthly_income |
| `app/Http/Controllers/BackEnd/Organizer/OrganizerFinanceController.php` | index, export |
| `app/Http/Controllers/BackEnd/Organizer/OrganizerKycController.php` | show, submit, download, verifyPayoutAccount, confirmPayoutLock, lockPayoutAccount |
| `app/Http/Controllers/BackEnd/Organizer/OrganizerKycReviewController.php` | index, show, review, download |
| `app/Http/Controllers/BackEnd/Organizer/OrganizerManagementController.php` | __construct, settings, update_setting, index, add, create, updateEmailStatus, show, updateAccountStatus, changePassword, updatePassword, edit, update, update_organizer_balance, destroy, bulkDestroy, secret_login |
| `app/Http/Controllers/BackEnd/Organizer/OrganizerWithdrawController.php` | index, export, create, get_inputs, balance_calculation, send_request, openDispute, Delete, bulkDelete |
| `app/Http/Controllers/BackEnd/Organizer/OrganizerWorkspaceRedirectController.php` | overview, settings, events, create, orders, payouts |
| `app/Http/Controllers/BackEnd/Organizer/PaymentRefundController.php` | store |
| `app/Http/Controllers/BackEnd/Organizer/PaymentRefundDisputeController.php` | store |
| `app/Http/Controllers/BackEnd/Organizer/RegistrationFormController.php` | index, store, show, settings, useOnEvents, pushChanges, duplicate, adopt, destroy, stop |
| `app/Http/Controllers/BackEnd/Organizer/ScannerConflictController.php` | index, resolve |
| `app/Http/Controllers/BackEnd/Organizer/ScannerDeviceController.php` | current, index, store, revoke |
| `app/Http/Controllers/BackEnd/Organizer/ScannerMetricsController.php` | show |
| `app/Http/Controllers/BackEnd/Organizer/ScannerOfflineController.php` | events, store, sync |
| `app/Http/Controllers/BackEnd/Organizer/ScannerStaffController.php` | loginForm, login, logout, workspace, index, store, update |
| `app/Http/Controllers/BackEnd/Organizer/SupportTicketController.php` | index, create, store, message, zip_file_upload, ticketreply, delete, bulk_delete |
| `app/Http/Controllers/BackEnd/Organizer/TicketController.php` | index, create, store, edit, update, destroy, bulk_delete |
| `app/Http/Controllers/BackEnd/Organizer/TransactionExportController.php` | index, store, download |
| `app/Http/Controllers/BackEnd/PaymentGateway/OfflineGatewayController.php` | index, store, updateStatus, update, destroy |
| `app/Http/Controllers/BackEnd/PaymentGateway/OnlineGatewayController.php` | index, updateInstamojoInfo, updatePaystackInfo, updateMonnifyInfo, updateFlutterwaveInfo, updateRazorpayInfo, updateMercadoPagoInfo, updateMollieInfo, updateStripeInfo, updatePaytmInfo, updateMidtransInfo, updatePaytabsInfo, updateToyyibpayInfo, updateIyzicoInfo, updatePhonepeInfo, updateYocoInfo, updateXenditInfo, updateMyFatoorahInfo, updatePerfectMoneyInfo |
| `app/Http/Controllers/BackEnd/PaymentReconciliationController.php` | index, show, settlements, settlementShow |
| `app/Http/Controllers/BackEnd/PaymentRefundController.php` | store, approve, reject |
| `app/Http/Controllers/BackEnd/PaymentRefundDisputeController.php` | store, review, approve |
| `app/Http/Controllers/BackEnd/PayoutAccountController.php` | index, review, reverify, lock, provider, test |
| `app/Http/Controllers/BackEnd/PopupController.php` | index, popupType, create, store, updateStatus, edit, update, destroy, bulkDestroy |
| `app/Http/Controllers/BackEnd/ReconciliationExportController.php` | index, storeRuns, storeSettlements, storeRunEntries, storeSettlementEntries, download |
| `app/Http/Controllers/BackEnd/ShopManagement/CategoryController.php` | index, store, update_featured, update, delete, bulk_delete |
| `app/Http/Controllers/BackEnd/ShopManagement/ProductController.php` | index, settings, setting_update, create, imgstore, imgrmv, store, show, status_update, feature_update, edit, load_images, imagedbrmv, update, destroy, bulk_destroy |
| `app/Http/Controllers/BackEnd/ShopManagement/ProductOrderController.php` | index, delete, bulk_delete, updateStatus, updateOrderStatus, sendOrderMail, generateInvoice, invoice, receipt, details, report, export |
| `app/Http/Controllers/BackEnd/ShopManagement/ProductOrderExportController.php` | index, download |
| `app/Http/Controllers/BackEnd/ShopManagement/ProductOrderRefundController.php` | store |
| `app/Http/Controllers/BackEnd/ShopManagement/SettingController.php` | index, store, delete, bulkdelete, update |
| `app/Http/Controllers/BackEnd/ShopManagement/ShopCouponController.php` | index, store, update, destroy, bulk_destroy |
| `app/Http/Controllers/BackEnd/SummernoteController.php` | upload, remove |
| `app/Http/Controllers/BackEnd/SupportTicketController.php` | index, message, zip_file_upload, ticketreply, ticket_closed, unassign_stuff, setting, update_setting, assign_stuff, delete, bulk_delete |
| `app/Http/Controllers/BackEnd/TicaPlatformController.php` | show, update |
| `app/Http/Controllers/BackEnd/TicaPromotionController.php` | index, storePlan, updatePlan, duplicatePlan, archivePlan, reviewCampaign, reviewPixel |
| `app/Http/Controllers/BackEnd/TicaWebsiteController.php` | show, update |
| `app/Http/Controllers/BackEnd/TransactionExportController.php` | index, store, download |
| `app/Http/Controllers/BackEnd/User/PushNotificationController.php` | settings, updateSettings, writeNotification, sendNotification |
| `app/Http/Controllers/BackEnd/User/SubscriberController.php` | index, destroy, bulkDestroy, writeEmail, sendEmail |
| `app/Http/Controllers/BackEnd/WebhookOperationsController.php` | index |
| `app/Http/Controllers/BackEnd/WithdrawController.php` | index, export, delete, approve, decline, hold, submitPaystack, reviewDispute |
| `app/Http/Controllers/BackEnd/WithdrawPaymentMethodController.php` | index, store, update, destroy |
| `app/Http/Controllers/BackEnd/WithdrawPaymentMethodInputController.php` | index, store, edit, update, order_update, get_options, delete |
| `app/Http/Controllers/Controller.php` | getCurrencyInfo, getLanguage, getPageHeading, changeLanguage, serviceUnavailable, countAdView, storeSubscriber |
| `app/Http/Controllers/FrontEnd/AnalyticsController.php` | store |
| `app/Http/Controllers/FrontEnd/BlogController.php` | blogs, details, getCategories |
| `app/Http/Controllers/FrontEnd/CheckOutController.php` | checkout2, checkout |
| `app/Http/Controllers/FrontEnd/ContactController.php` | contact, sendMail |
| `app/Http/Controllers/FrontEnd/CustomerController.php` | __construct, index, signup, create, verify_email, send_verification_link, signupVerify, login, authentication, requestLoginCode, verifyLoginCode, claimTickets, forget_passord, forget_mail, reset_password, update_password, logout, change_password, updated_password, edit_profile, update_profile, wishlist, remove_wishlist, facebookRedirect, handleFacebookCallback, googleRedirect, handleGoogleCallback, authenticationViaProvider |
| `app/Http/Controllers/FrontEnd/CustomerDataExportController.php` | index, store, download |
| `app/Http/Controllers/FrontEnd/CustomerPrivacyRequestController.php` | store, cancel |
| `app/Http/Controllers/FrontEnd/Event/BookingController.php` | index, storeData, complete, cancel, sendMail, generateInvoice |
| `app/Http/Controllers/FrontEnd/Event/CustomerBookingController.php` | my_booking, details, resendTickets, invoice, currentTickets, transferTicket, cancelTransfer, reissueTicket, ticketQr, showTransferInvitation, acceptTransfer |
| `app/Http/Controllers/FrontEnd/EventController.php` | __construct, index, details, preview, applyCoupon, add_to_wishlist |
| `app/Http/Controllers/FrontEnd/EventGuestRsvpController.php` | show, respond |
| `app/Http/Controllers/FrontEnd/EventRegistrationAllocationController.php` | show, invite, claim, submit |
| `app/Http/Controllers/FrontEnd/EventRegistrationController.php` | show, store, showEmbedded, storeEmbedded |
| `app/Http/Controllers/FrontEnd/EventShareCardController.php` | show |
| `app/Http/Controllers/FrontEnd/EventWaitlistController.php` | store, confirmUnsubscribe, unsubscribe |
| `app/Http/Controllers/FrontEnd/FaqController.php` | faqs |
| `app/Http/Controllers/FrontEnd/HomeController.php` | __construct, index, offline, about, pricing, midtrans_cancel, xendit_callback, myfatoorah_callback, myfatoorah_cancel |
| `app/Http/Controllers/FrontEnd/ManifestController.php` | web, scanner |
| `app/Http/Controllers/FrontEnd/OrganizerController.php` | index, details, sendMail |
| `app/Http/Controllers/FrontEnd/PageController.php` | page |
| `app/Http/Controllers/FrontEnd/PaymentGateway/Concerns/FulfilsVerifiedPayment.php` |  |
| `app/Http/Controllers/FrontEnd/PaymentGateway/FlutterwaveController.php` | bookingProcess, notify, recovery, retryRecovery, webhook |
| `app/Http/Controllers/FrontEnd/PaymentGateway/InstamojoController.php` | __construct, bookingProcess, notify |
| `app/Http/Controllers/FrontEnd/PaymentGateway/IyzipayController.php` | makePayment, notify |
| `app/Http/Controllers/FrontEnd/PaymentGateway/MercadoPagoController.php` | __construct, bookingProcess, notify |
| `app/Http/Controllers/FrontEnd/PaymentGateway/MidtransController.php` | makePayment, ccNotify, bankNotify |
| `app/Http/Controllers/FrontEnd/PaymentGateway/MollieController.php` | bookingProcess, notify |
| `app/Http/Controllers/FrontEnd/PaymentGateway/MonnifyController.php` | bookingProcess, webhook, notify, recovery, retryRecovery |
| `app/Http/Controllers/FrontEnd/PaymentGateway/MyFatoorahController.php` | __construct, makePayment, successCallback, failCallback |
| `app/Http/Controllers/FrontEnd/PaymentGateway/OfflineController.php` | bookingProcess |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | bookingProcess, webhook, notify, recovery, retryRecovery |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaytabsController.php` | makePayment, notify |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaytmController.php` | bookingProcess, notify |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PerfectMoneyController.php` | notify, cancel |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PhonepeController.php` | makePayment, notify |
| `app/Http/Controllers/FrontEnd/PaymentGateway/RazorpayController.php` | __construct, bookingProcess, notify |
| `app/Http/Controllers/FrontEnd/PaymentGateway/StripeController.php` | bookingProcess, webhook, notify, recovery, retryRecovery |
| `app/Http/Controllers/FrontEnd/PaymentGateway/ToyyibpayController.php` | makePayment, notify |
| `app/Http/Controllers/FrontEnd/PaymentGateway/XenditController.php` | makePayment, callback, notify |
| `app/Http/Controllers/FrontEnd/PaymentGateway/YocoController.php` | makePayment, notify |
| `app/Http/Controllers/FrontEnd/PromotionTrackingController.php` | paystackCallback, click |
| `app/Http/Controllers/FrontEnd/PublicAssetController.php` | show |
| `app/Http/Controllers/FrontEnd/PushNotificationController.php` | store |
| `app/Http/Controllers/FrontEnd/Shop/CustomerOrderController.php` | index, details, invoice |
| `app/Http/Controllers/FrontEnd/Shop/OrderController.php` | enrol, storeData, storeOders, storeOrderWithItems, complete, cancel, sendMail, generateInvoice |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/FlutterwaveController.php` | __construct, enrolmentProcess, notify |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/InstamojoController.php` | __construct, enrolmentProcess, notify |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/IyzipayController.php` | purchaseProcess, notify |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/MercadoPagoController.php` | __construct, enrolmentProcess, notify, curlCalls |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/MidtransController.php` | purchaseProcess, ccNotify |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/MollieController.php` | enrolmentProcess, notify |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/MyFatoorahController.php` | __construct, purchaseProcess, successCallback, failCallback |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/OfflineController.php` | enrolmentProcess |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | enrolmentProcess, notify, recovery, retryRecovery |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaytabsController.php` | purchaseProcess, notify |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaytmController.php` | enrolmentProcess, notify |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PerfectMoneyController.php` | notify, cancel |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PhonepeController.php` | purchaseProcess, notify |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/RazorpayController.php` | __construct, enrolmentProcess, notify |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/StripeController.php` | enrolmentProcess |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/ToyyibpayController.php` | purchaseProcess, notify |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/XenditController.php` | purchaseProcess, callback, notify |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/YocoController.php` | purchaseProcess, notify |
| `app/Http/Controllers/FrontEnd/Shop/ShopController.php` | index, details, addToCart, addToCart2, cart, cartitemremove, updatecart, orderNow, checkout, applyCoupon, buy, review |
| `app/Http/Controllers/FrontEnd/SocialSignInController.php` | redirect, callback |
| `app/Http/Controllers/FrontEnd/SupportTicketController.php` | index, create, store, message, reply |
| `app/Http/Controllers/FrontEnd/TicaApiController.php` | coupon, checkout, seats, login, checkinCode, acceptTeamInvite, signup, forgot, loginCode, loginCodeVerify, logout, wishlist, orderLookup, waitlist |
| `app/Http/Controllers/FrontEnd/TicaOrganizerController.php` | storeEvent, storeAdminEvent, updateOrganizerEvent, updateAdminEvent |
| `app/Http/Controllers/FrontEnd/TicaSeoController.php` | sitemap, robots |
| `app/Http/Controllers/FrontEnd/TicaUiController.php` | page, adminCreate, adminEdit, organizerEdit, event, checkout, order, teamAccept, missing, blog, blogPost, customPage |
| `app/Http/Controllers/FrontEnd/TicaWorkspaceController.php` | updateEvent, changeDate, cancel, attendees, attendeesCsv, resend, refund, message, guest, banks, resolveBank, saveBank, payoutSchedule, withdraw, storeCode, updateCode, deleteCode, storeLink, updateLink, deleteLink, storePromotion, payPromotion, checkoutPromotion, cancelPromotion, storePixel, invite, changeMemberRole, removeMember, profile, password, prefs, notifications, readNotifications |
| `app/Http/Controllers/OperationalHealthController.php` | ready |
| `app/Http/Controllers/SystemUpdateController.php` | show, apply |
| `app/Http/Controllers/VercelCronController.php` | __invoke |

## Form requests

- `app/Http/Requests/Admin/StoreRequest.php`
- `app/Http/Requests/Admin/UpdateRequest.php`
- `app/Http/Requests/Advertisement/StoreRequest.php`
- `app/Http/Requests/Advertisement/UpdateRequest.php`
- `app/Http/Requests/Blog/StoreRequest.php`
- `app/Http/Requests/Blog/UpdateRequest.php`
- `app/Http/Requests/BlogCategoryRequest.php`
- `app/Http/Requests/ContactPageRequest.php`
- `app/Http/Requests/CountInformationRequest.php`
- `app/Http/Requests/CouponRequest.php`
- `app/Http/Requests/Course/StoreRequest.php`
- `app/Http/Requests/Course/UpdateRequest.php`
- `app/Http/Requests/CourseCategoryRequest.php`
- `app/Http/Requests/CurrencyRequest.php`
- `app/Http/Requests/Event/EventCategoryRequest.php`
- `app/Http/Requests/Event/StoreRequest.php`
- `app/Http/Requests/Event/TicketRequest.php`
- `app/Http/Requests/Event/UpdateCategoryRequest.php`
- `app/Http/Requests/Event/UpdateRequest.php`
- `app/Http/Requests/EventFeatureRequest.php`
- `app/Http/Requests/FeatureRequest.php`
- `app/Http/Requests/Instructor/StoreRequest.php`
- `app/Http/Requests/Instructor/UpdateRequest.php`
- `app/Http/Requests/Language/StoreRequest.php`
- `app/Http/Requests/Language/UpdateRequest.php`
- `app/Http/Requests/MailFromAdminRequest.php`
- `app/Http/Requests/PageHeadingRequest.php`
- `app/Http/Requests/PartnerRequest.php`
- `app/Http/Requests/PartnerUpdateRequest.php`
- `app/Http/Requests/Popup/StoreRequest.php`
- `app/Http/Requests/Popup/UpdateRequest.php`
- `app/Http/Requests/SectionStatusRequest.php`
- `app/Http/Requests/ShopManagement/CategoryRequest.php`
- `app/Http/Requests/ShopManagement/ProductStoreRequest.php`
- `app/Http/Requests/ShopManagement/ProductUpdateRequest.php`
- `app/Http/Requests/ShopManagement/ShippingChargeRequest.php`
- `app/Http/Requests/ShopManagement/ShopCouponRequest.php`
- `app/Http/Requests/ShopManagement/UpdateShopCounponRequest.php`
- `app/Http/Requests/SocialMediaRequest.php`
- `app/Http/Requests/Testimonial/StoreRequest.php`
- `app/Http/Requests/Testimonial/UpdateRequest.php`
- `app/Http/Requests/UserProfileRequest.php`
- `app/Http/Requests/WithdrawPaymentMethodRequest.php`
- `app/Http/Requests/WithdrawRequest.php`

## Models

- `app/Models/AccountLoginChallenge.php`
- `app/Models/Admin.php`
- `app/Models/Advertisement.php`
- `app/Models/ApplicationMailDelivery.php`
- `app/Models/AuditLog.php`
- `app/Models/BasicSettings/Basic.php`
- `app/Models/BasicSettings/CookieAlert.php`
- `app/Models/BasicSettings/MailTemplate.php`
- `app/Models/BasicSettings/PageHeading.php`
- `app/Models/BasicSettings/SEO.php`
- `app/Models/BasicSettings/SocialMedia.php`
- `app/Models/BookingDelivery.php`
- `app/Models/BookingTicketDeliveryRequest.php`
- `app/Models/City.php`
- `app/Models/ContactPage.php`
- `app/Models/Conversation.php`
- `app/Models/Country.php`
- `app/Models/Customer.php`
- `app/Models/CustomerEmailVerificationDelivery.php`
- `app/Models/CustomerLoginCode.php`
- `app/Models/CustomerPrivacyRequest.php`
- `app/Models/CustomPage/Page.php`
- `app/Models/CustomPage/PageContent.php`
- `app/Models/DataExport.php`
- `app/Models/DataExportSchedule.php`
- `app/Models/Earning.php`
- `app/Models/Event.php`
- `app/Models/Event/Booking.php`
- `app/Models/Event/BookingTicket.php`
- `app/Models/Event/BookingTicketEvent.php`
- `app/Models/Event/BookingTicketTransfer.php`
- `app/Models/Event/Coupon.php`
- `app/Models/Event/EventCategory.php`
- `app/Models/Event/EventContent.php`
- `app/Models/Event/EventDates.php`
- `app/Models/Event/EventImage.php`
- `app/Models/Event/Ticket.php`
- `app/Models/Event/TicketContent.php`
- `app/Models/Event/VariationContent.php`
- `app/Models/Event/Wishlist.php`
- `app/Models/EventAgendaItem.php`
- `app/Models/EventCancellationCase.php`
- `app/Models/EventCancellationNotification.php`
- `app/Models/EventCancellationRequest.php`
- `app/Models/EventGuestImport.php`
- `app/Models/EventGuestImportRow.php`
- `app/Models/EventGuestInvitation.php`
- `app/Models/EventProgrammePerson.php`
- `app/Models/EventRegistrationAllocation.php`
- `app/Models/EventRegistrationAllocationClaim.php`
- `app/Models/EventRegistrationAnswer.php`
- `app/Models/EventRegistrationAttendee.php`
- `app/Models/EventRegistrationBadgeSetting.php`
- `app/Models/EventRegistrationEmbedSetting.php`
- `app/Models/EventRegistrationFile.php`
- `app/Models/EventRegistrationForm.php`
- `app/Models/EventRegistrationFormTemplate.php`
- `app/Models/EventRegistrationImport.php`
- `app/Models/EventRegistrationImportRow.php`
- `app/Models/EventRegistrationInvoice.php`
- `app/Models/EventRegistrationNotification.php`
- `app/Models/EventRegistrationQuestion.php`
- `app/Models/EventRegistrationSession.php`
- `app/Models/EventRegistrationSessionSelection.php`
- `app/Models/EventRegistrationSubmission.php`
- `app/Models/EventSeat.php`
- `app/Models/EventWaitlistEntry.php`
- `app/Models/FAQ.php`
- `app/Models/FinancialLedgerAccount.php`
- `app/Models/FinancialLedgerEntry.php`
- `app/Models/FinancialLedgerTransaction.php`
- `app/Models/Footer/FooterContent.php`
- `app/Models/Footer/QuickLink.php`
- `app/Models/FunnelAnalyticsEvent.php`
- `app/Models/GatewaySettlement.php`
- `app/Models/GatewaySettlementEntry.php`
- `app/Models/Guest.php`
- `app/Models/HomePage/AboutUsSection.php`
- `app/Models/HomePage/EventFeature.php`
- `app/Models/HomePage/EventFeatureSection.php`
- `app/Models/HomePage/Feature.php`
- `app/Models/HomePage/HeroSection.php`
- `app/Models/HomePage/HowWork.php`
- `app/Models/HomePage/HowWorkItem.php`
- `app/Models/HomePage/Partner.php`
- `app/Models/HomePage/PartnerSection.php`
- `app/Models/HomePage/Section.php`
- `app/Models/HomePage/SectionTitle.php`
- `app/Models/HomePage/Testimonial.php`
- `app/Models/HomePage/TestimonialSection.php`
- `app/Models/Journal/Blog.php`
- `app/Models/Journal/BlogCategory.php`
- `app/Models/Journal/BlogInformation.php`
- `app/Models/Language.php`
- `app/Models/MenuBuilder.php`
- `app/Models/OperationalIncident.php`
- `app/Models/Organizer.php`
- `app/Models/OrganizerEarningRelease.php`
- `app/Models/OrganizerEmailVerificationDelivery.php`
- `app/Models/OrganizerInfo.php`
- `app/Models/OrganizerKycDocument.php`
- `app/Models/OrganizerKycSubmission.php`
- `app/Models/OrganizerPayoutAccount.php`
- `app/Models/OrganizerTeamMember.php`
- `app/Models/PaymentGateway/OfflineGateway.php`
- `app/Models/PaymentGateway/OnlineGateway.php`
- `app/Models/PaymentIntent.php`
- `app/Models/PaymentReconciliationEntry.php`
- `app/Models/PaymentReconciliationRun.php`
- `app/Models/PaymentRefund.php`
- `app/Models/PaymentRefundDispute.php`
- `app/Models/PayoutDispute.php`
- `app/Models/PayoutTransfer.php`
- `app/Models/Popup.php`
- `app/Models/ProductGatewayRefund.php`
- `app/Models/ProductOrderDelivery.php`
- `app/Models/ProductOrderRefund.php`
- `app/Models/ProductPaymentIntent.php`
- `app/Models/PushNotificationCampaign.php`
- `app/Models/PushNotificationCampaignDelivery.php`
- `app/Models/RolePermission.php`
- `app/Models/ScannerCheckInConflict.php`
- `app/Models/ScannerDevice.php`
- `app/Models/ScannerOfflineManifest.php`
- `app/Models/ScannerOfflineManifestItem.php`
- `app/Models/ScannerOfflineSyncItem.php`
- `app/Models/ScannerStaff.php`
- `app/Models/Scopes/SchemaAwareSoftDeletingScope.php`
- `app/Models/SeatingRow.php`
- `app/Models/SeatingSection.php`
- `app/Models/SeatReservation.php`
- `app/Models/ShopManagement/OrderItem.php`
- `app/Models/ShopManagement/Product.php`
- `app/Models/ShopManagement/ProductCategory.php`
- `app/Models/ShopManagement/ProductContent.php`
- `app/Models/ShopManagement/ProductImage.php`
- `app/Models/ShopManagement/ProductOrder.php`
- `app/Models/ShopManagement/ProductReview.php`
- `app/Models/ShopManagement/ShippingCharge.php`
- `app/Models/ShopManagement/ShopCoupon.php`
- `app/Models/State.php`
- `app/Models/Subscriber.php`
- `app/Models/SubscriberCampaign.php`
- `app/Models/SubscriberCampaignDelivery.php`
- `app/Models/SupportTicket.php`
- `app/Models/SupportTicketStatus.php`
- `app/Models/Teacher/Instructor.php`
- `app/Models/Teacher/SocialLink.php`
- `app/Models/TicaPromotionCampaign.php`
- `app/Models/TicaPromotionEvent.php`
- `app/Models/TicaPromotionPlan.php`
- `app/Models/TicaTrackingLink.php`
- `app/Models/TicaTrackingPixel.php`
- `app/Models/TicketNumberAllocation.php`
- `app/Models/TicketNumberSequence.php`
- `app/Models/TicketReservation.php`
- `app/Models/Timezone.php`
- `app/Models/Transaction.php`
- `app/Models/User.php`
- `app/Models/WebhookReceipt.php`
- `app/Models/Withdraw.php`
- `app/Models/WithdrawMethodInput.php`
- `app/Models/WithdrawMethodOption.php`
- `app/Models/WithdrawPaymentMethod.php`

## Jobs

- `app/Jobs/DispatchPushNotificationCampaign.php`
- `app/Jobs/DispatchSubscriberCampaign.php`
- `app/Jobs/FinalizePaidEventBooking.php`
- `app/Jobs/FinalizePaidProductOrder.php`
- `app/Jobs/GenerateAdminDirectoryExport.php`
- `app/Jobs/GenerateAuditLogExport.php`
- `app/Jobs/GenerateBookingExport.php`
- `app/Jobs/GenerateCheckInLedgerExport.php`
- `app/Jobs/GenerateCustomerDataExport.php`
- `app/Jobs/GenerateEventCancellationCaseExport.php`
- `app/Jobs/GenerateGuestListExport.php`
- `app/Jobs/GenerateOperationsEvidenceExport.php`
- `app/Jobs/GenerateOrganizerFinanceExport.php`
- `app/Jobs/GenerateProductOrderExport.php`
- `app/Jobs/GenerateReconciliationExport.php`
- `app/Jobs/GenerateRegistrationExport.php`
- `app/Jobs/GenerateTransactionExport.php`
- `app/Jobs/GenerateWithdrawalExport.php`
- `app/Jobs/IyzicoEventPendingPayment.php`
- `app/Jobs/IyzicoProductOrderPendingPayment.php`
- `app/Jobs/ProcessEventGuestImport.php`
- `app/Jobs/ProcessEventRegistrationImport.php`
- `app/Jobs/QueueHeartbeat.php`
- `app/Jobs/ResendEventBookingTickets.php`
- `app/Jobs/SendApplicationMailDelivery.php`
- `app/Jobs/SendCustomerEmailVerification.php`
- `app/Jobs/SendEventBookingSms.php`
- `app/Jobs/SendEventCancellationNotice.php`
- `app/Jobs/SendEventGuestInvitation.php`
- `app/Jobs/SendEventRegistrationAllocationInvitation.php`
- `app/Jobs/SendEventRegistrationNotification.php`
- `app/Jobs/SendEventRegistrationSponsorAccess.php`
- `app/Jobs/SendEventWaitlistAvailability.php`
- `app/Jobs/SendOrganizerEmailVerification.php`
- `app/Jobs/SendPayoutStatusNotification.php`
- `app/Jobs/SendPushNotificationCampaignDelivery.php`
- `app/Jobs/SendRejectedEventBookingNotification.php`
- `app/Jobs/SendRejectedProductOrderNotification.php`
- `app/Jobs/SendSubscriberCampaignDelivery.php`
- `app/Jobs/SendTicketTransferInvitation.php`
- `app/Jobs/SubmitPaystackPayout.php`

## Mailables

- `app/Mail/CustomerEmailVerificationMail.php`
- `app/Mail/EventCancellationNoticeMail.php`
- `app/Mail/EventGuestInvitationMail.php`
- `app/Mail/EventRegistrationAllocationInvitationMail.php`
- `app/Mail/EventRegistrationSponsorAccessMail.php`
- `app/Mail/EventRegistrationStatusMail.php`
- `app/Mail/EventWaitlistAvailableMail.php`
- `app/Mail/OrganizerEmailVerificationMail.php`
- `app/Mail/PayoutStatusMail.php`
- `app/Mail/TicketTransferInvitationMail.php`

## Notifications

- `app/Notifications/PushNotification.php`

## Middleware

- `app/Http/Middleware/AuditConfigurationChanges.php`
- `app/Http/Middleware/Authenticate.php`
- `app/Http/Middleware/BlockWebInstaller.php`
- `app/Http/Middleware/ChangeLanguage.php`
- `app/Http/Middleware/Deactive.php`
- `app/Http/Middleware/Demo.php`
- `app/Http/Middleware/EmailStatus.php`
- `app/Http/Middleware/EncryptCookies.php`
- `app/Http/Middleware/EnsureCertificateIsEnable.php`
- `app/Http/Middleware/EnsurePaymentGatewayAllowed.php`
- `app/Http/Middleware/HasPermission.php`
- `app/Http/Middleware/PreventRequestsDuringMaintenance.php`
- `app/Http/Middleware/PreventRequestsForDeactivatedAccount.php`
- `app/Http/Middleware/RedirectIfAuthenticated.php`
- `app/Http/Middleware/RedirectIfDownFileExists.php`
- `app/Http/Middleware/RequestId.php`
- `app/Http/Middleware/ScannerAccess.php`
- `app/Http/Middleware/ScannerSupervisor.php`
- `app/Http/Middleware/SecurityHeaders.php`
- `app/Http/Middleware/SetLangMiddleware.php`
- `app/Http/Middleware/TicaRetiredAdminPage.php`
- `app/Http/Middleware/TicaShopRemoved.php`
- `app/Http/Middleware/TicaTeamRole.php`
- `app/Http/Middleware/TrackWebhookReceipt.php`
- `app/Http/Middleware/TrimStrings.php`
- `app/Http/Middleware/TrustHosts.php`
- `app/Http/Middleware/TrustProxies.php`
- `app/Http/Middleware/VerifyCsrfToken.php`
- `app/Http/Middleware/VerifyScannerDevice.php`

## Console commands

- `app/Console/Commands/ApplyAuditLogRetention.php`
- `app/Console/Commands/ApplyEventGuestRetention.php`
- `app/Console/Commands/ApplyEventRegistrationRetention.php`
- `app/Console/Commands/AuditBookingTicketUnits.php`
- `app/Console/Commands/BackfillGatewayLedgerIdentities.php`
- `app/Console/Commands/BackfillOfflineLedgerIdentities.php`
- `app/Console/Commands/BackfillOfflineProductLedgerIdentities.php`
- `app/Console/Commands/CheckReleaseReadiness.php`
- `app/Console/Commands/DispatchEventWaitlist.php`
- `app/Console/Commands/DispatchIyzicoPendingPayments.php`
- `app/Console/Commands/DispatchLifecycleNotifications.php`
- `app/Console/Commands/DispatchPendingApplicationMail.php`
- `app/Console/Commands/DispatchPendingCampaigns.php`
- `app/Console/Commands/DispatchPendingDataExports.php`
- `app/Console/Commands/DispatchPendingEmailVerifications.php`
- `app/Console/Commands/DispatchPendingEventCancellationNotices.php`
- `app/Console/Commands/DispatchPendingEventRegistrationNotifications.php`
- `app/Console/Commands/DispatchPendingFulfillments.php`
- `app/Console/Commands/DispatchPendingGuestInvitations.php`
- `app/Console/Commands/DispatchPendingImports.php`
- `app/Console/Commands/DispatchPendingPayouts.php`
- `app/Console/Commands/DispatchPendingRegistrationAllocations.php`
- `app/Console/Commands/DispatchPendingTicketResends.php`
- `app/Console/Commands/DispatchPendingTicketTransfers.php`
- `app/Console/Commands/DispatchReadyDataExportNotifications.php`
- `app/Console/Commands/EncryptBasicSettingSecrets.php`
- `app/Console/Commands/EncryptOnlineGatewayCredentials.php`
- `app/Console/Commands/ExpireEventRegistrationInvoices.php`
- `app/Console/Commands/IssueBookingTickets.php`
- `app/Console/Commands/MigrateEventBookingDocuments.php`
- `app/Console/Commands/MigrateProductOrderDocuments.php`
- `app/Console/Commands/MonitorBookingDeliveries.php`
- `app/Console/Commands/MonitorPaymentIntents.php`
- `app/Console/Commands/MonitorQueues.php`
- `app/Console/Commands/MonitorWebhooks.php`
- `app/Console/Commands/ProcessPaymentRefunds.php`
- `app/Console/Commands/PruneDataExports.php`
- `app/Console/Commands/PruneEventGuestImports.php`
- `app/Console/Commands/PruneEventRegistrationImports.php`
- `app/Console/Commands/PruneScannerOfflineData.php`
- `app/Console/Commands/PruneWebhookReceipts.php`
- `app/Console/Commands/PurgeLegacyPublicCheckoutDocuments.php`
- `app/Console/Commands/PurgeLegacyPublicTicketQrArtifacts.php`
- `app/Console/Commands/ReconcileFlutterwavePayments.php`
- `app/Console/Commands/ReconcilePaymentRefunds.php`
- `app/Console/Commands/ReconcilePaystackPayments.php`
- `app/Console/Commands/ReconcilePaystackPayouts.php`
- `app/Console/Commands/ReconcilePaystackSettlements.php`
- `app/Console/Commands/RecoverTicketSmsDelivery.php`
- `app/Console/Commands/RefreshStaticImages.php`
- `app/Console/Commands/RehashGuestBuyerIdentities.php`
- `app/Console/Commands/ReleaseEligiblePayouts.php`
- `app/Console/Commands/ReleaseOrganizerEarnings.php`
- `app/Console/Commands/RunDataExportSchedules.php`
- `app/Console/Commands/SeedQaAccounts.php`
- `app/Console/Commands/SyncTicaPromotions.php`
- `app/Console/Commands/TicaCurrencyPayout.php`

## Services

- `app/Services/AccountMfaService.php`
- `app/Services/AdminAccountService.php`
- `app/Services/ApplicationMailService.php`
- `app/Services/AuditLogExportQueryService.php`
- `app/Services/AuditLogger.php`
- `app/Services/AutomaticPayoutReleaseService.php`
- `app/Services/BookingCompletionUrlService.php`
- `app/Services/BookingExportFieldPolicyService.php`
- `app/Services/BookingExportQueryService.php`
- `app/Services/BookingPaymentState.php`
- `app/Services/BookingTicketDeliveryService.php`
- `app/Services/CheckInLedgerExportQueryService.php`
- `app/Services/ComplimentaryTicketService.php`
- `app/Services/ConfiguredMailer.php`
- `app/Services/CustomerEmailVerificationService.php`
- `app/Services/CustomerLoginCodeService.php`
- `app/Services/CustomerPrivacyRequestService.php`
- `app/Services/DataExportReadyNotificationService.php`
- `app/Services/DataExportScheduleService.php`
- `app/Services/DataExportService.php`
- `app/Services/EventBookingDocumentStorage.php`
- `app/Services/EventBookingInvoiceService.php`
- `app/Services/EventCancellationCaseExportQueryService.php`
- `app/Services/EventCancellationCaseService.php`
- `app/Services/EventCancellationNotificationDispatchService.php`
- `app/Services/EventCancellationService.php`
- `app/Services/EventCheckoutQuoteService.php`
- `app/Services/EventCouponService.php`
- `app/Services/EventFeeCalculatorService.php`
- `app/Services/EventGuestImportService.php`
- `app/Services/EventGuestListService.php`
- `app/Services/EventGuestRetentionService.php`
- `app/Services/EventLifecycleNotificationService.php`
- `app/Services/EventProgrammeService.php`
- `app/Services/EventPublicVisibility.php`
- `app/Services/EventRegistrationAllocationService.php`
- `app/Services/EventRegistrationBadgeService.php`
- `app/Services/EventRegistrationEmbedService.php`
- `app/Services/EventRegistrationFileService.php`
- `app/Services/EventRegistrationImportService.php`
- `app/Services/EventRegistrationInvoiceDocumentService.php`
- `app/Services/EventRegistrationInvoiceService.php`
- `app/Services/EventRegistrationNotificationDispatchService.php`
- `app/Services/EventRegistrationRetentionService.php`
- `app/Services/EventRegistrationService.php`
- `app/Services/EventRegistrationSessionService.php`
- `app/Services/EventSaleAccountingService.php`
- `app/Services/EventShareCardService.php`
- `app/Services/EventTicketPurchaseLimitService.php`
- `app/Services/EventWaitlistService.php`
- `app/Services/FinancialLedgerService.php`
- `app/Services/FlutterwaveGatewayClient.php`
- `app/Services/FlutterwavePaymentVerifier.php`
- `app/Services/FlutterwaveReconciliationService.php`
- `app/Services/FunnelAnalyticsService.php`
- `app/Services/GatewayLedgerBackfillService.php`
- `app/Services/GatewayLedgerIntegrityService.php`
- `app/Services/GuestBuyerIdentityRehashService.php`
- `app/Services/GuestInvitationExportQueryService.php`
- `app/Services/GuestTicketClaimService.php`
- `app/Services/IncidentAlertService.php`
- `app/Services/Iyzipay.php`
- `app/Services/LegacyCheckoutDocumentValidator.php`
- `app/Services/LegacyPublicCheckoutDocuments.php`
- `app/Services/LegacyPublicTicketQrArtifacts.php`
- `app/Services/LifecycleMailService.php`
- `app/Services/MailTemplateService.php`
- `app/Services/ManualBookingPaymentResult.php`
- `app/Services/ManualBookingPaymentService.php`
- `app/Services/ManualPaymentDecisionMailService.php`
- `app/Services/ManualPaymentDecisionNotificationService.php`
- `app/Services/ManualProductOrderPaymentResult.php`
- `app/Services/ManualProductOrderPaymentService.php`
- `app/Services/MonnifyEventCheckoutService.php`
- `app/Services/MonnifyGatewayClient.php`
- `app/Services/MonnifyPaymentRecoveryService.php`
- `app/Services/MonnifyPaymentVerifier.php`
- `app/Services/OfflineLedgerBackfillService.php`
- `app/Services/OfflineProductLedgerBackfillService.php`
- `app/Services/OperationalArchiveService.php`
- `app/Services/OperationalStatusService.php`
- `app/Services/OperationsEvidenceExportQueryService.php`
- `app/Services/OrganizerBalanceAdjustmentService.php`
- `app/Services/OrganizerEmailVerificationService.php`
- `app/Services/OrganizerFinanceStatementService.php`
- `app/Services/OrganizerKycService.php`
- `app/Services/OrganizerPayoutAccountService.php`
- `app/Services/OrganizerPayoutScheduleService.php`
- `app/Services/PaidBookingFulfillmentService.php`
- `app/Services/PaidProductOrderFulfillmentService.php`
- `app/Services/PasswordPolicy.php`
- `app/Services/PasswordResetService.php`
- `app/Services/PaymentGatewayPolicy.php`
- `app/Services/PaymentRefundDisputeService.php`
- `app/Services/PaymentRefundService.php`
- `app/Services/PayoutDisputeService.php`
- `app/Services/PayoutEligibilityService.php`
- `app/Services/Payouts/BankDirectory.php`
- `app/Services/Payouts/MonnifyPayoutProvider.php`
- `app/Services/Payouts/PayoutProviders.php`
- `app/Services/Payouts/PaystackPayoutProvider.php`
- `app/Services/PayoutTransferService.php`
- `app/Services/PaystackEventCheckoutService.php`
- `app/Services/PaystackGatewayClient.php`
- `app/Services/PaystackPaymentRecoveryService.php`
- `app/Services/PaystackPaymentVerifier.php`
- `app/Services/PaystackReconciliationService.php`
- `app/Services/PaystackSettlementReconciliationService.php`
- `app/Services/PlatformEarningService.php`
- `app/Services/ProductGatewayRefundService.php`
- `app/Services/ProductOrderAccountingService.php`
- `app/Services/ProductOrderDocumentStorage.php`
- `app/Services/ProductOrderExportQueryService.php`
- `app/Services/ProductOrderInventoryService.php`
- `app/Services/ProductOrderRefundService.php`
- `app/Services/ProductPaystackCheckoutService.php`
- `app/Services/ProductPaystackPaymentService.php`
- `app/Services/ProductPaystackPaymentVerifier.php`
- `app/Services/PublicAssetStorage.php`
- `app/Services/PublicContentPolicy.php`
- `app/Services/PushNotificationCampaignService.php`
- `app/Services/QueueHealthService.php`
- `app/Services/ReconciliationExportQueryService.php`
- `app/Services/RegistrationExportQueryService.php`
- `app/Services/RegistrationFormLibrary.php`
- `app/Services/ReleaseReadinessService.php`
- `app/Services/RolePermissionService.php`
- `app/Services/RuntimeConfigurationService.php`
- `app/Services/RuntimeDocumentStore.php`
- `app/Services/ScannerConflictService.php`
- `app/Services/ScannerDeviceService.php`
- `app/Services/ScannerMetricsService.php`
- `app/Services/ScannerOfflineService.php`
- `app/Services/ScannerStaffService.php`
- `app/Services/SeatReservationService.php`
- `app/Services/SensitiveActionConfirmation.php`
- `app/Services/SharedStateHealthService.php`
- `app/Services/ShopCheckoutPricingService.php`
- `app/Services/ShopCheckoutSessionService.php`
- `app/Services/StripeChargeService.php`
- `app/Services/StripeEventCheckoutService.php`
- `app/Services/StripeGatewayClient.php`
- `app/Services/StripePaymentRecoveryService.php`
- `app/Services/StripePaymentVerifier.php`
- `app/Services/SubscriberCampaignService.php`
- `app/Services/SupportTicketMailService.php`
- `app/Services/TermiiSmsSender.php`
- `app/Services/TicaPromotionAnalyticsService.php`
- `app/Services/TicaPromotionNotificationService.php`
- `app/Services/TicaPromotionPaystackService.php`
- `app/Services/TicaPromotionPlacementService.php`
- `app/Services/TicaPromotionService.php`
- `app/Services/TicaUi/AdminOverview.php`
- `app/Services/TicaUi/EventBuilderPrefill.php`
- `app/Services/TicaUi/EventCatalog.php`
- `app/Services/TicaUi/OrganizerAttendeeDirectory.php`
- `app/Services/TicaUi/OrganizerOverviewMetrics.php`
- `app/Services/TicaUi/OrganizerTeam.php`
- `app/Services/TicaUi/OrganizerWorkspace.php`
- `app/Services/TicaUi/TicaUiPayload.php`
- `app/Services/TicketDocumentService.php`
- `app/Services/TicketIssuanceService.php`
- `app/Services/TicketLifecycleService.php`
- `app/Services/TicketNumberAllocator.php`
- `app/Services/TicketReservationService.php`
- `app/Services/TicketRetirementService.php`
- `app/Services/TicketSmsService.php`
- `app/Services/TicketUnitIntegrityService.php`
- `app/Services/TransactionExportQueryService.php`
- `app/Services/TransactionLedgerService.php`
- `app/Services/WaitlistNotificationDispatchService.php`
- `app/Services/WithdrawalCancellationService.php`
- `app/Services/WithdrawalDecisionService.php`
- `app/Services/WithdrawalExportQueryService.php`
- `app/Services/WithdrawalService.php`
- `app/Services/WithdrawalTransactionEvidenceService.php`

## Providers

- `app/Providers/AppServiceProvider.php`
- `app/Providers/AuthServiceProvider.php`
- `app/Providers/BroadcastServiceProvider.php`
- `app/Providers/EventServiceProvider.php`
- `app/Providers/RouteServiceProvider.php`

## Rules

- `app/Rules/ImageMimeTypeRule.php`
- `app/Rules/KycDocumentRule.php`
- `app/Rules/MatchEmailRule.php`
- `app/Rules/MatchOldPasswordRule.php`
- `app/Rules/PaymentEvidenceFileRule.php`

## Exports

- `app/Exports/AdminDirectoryQueryExport.php`
- `app/Exports/AuditLogQueryExport.php`
- `app/Exports/BookingExport.php`
- `app/Exports/BookingQueryExport.php`
- `app/Exports/CheckInLedgerExport.php`
- `app/Exports/EnrolmentsExport.php`
- `app/Exports/EventCancellationCaseExport.php`
- `app/Exports/GatewaySettlementEntryExport.php`
- `app/Exports/GatewaySettlementExport.php`
- `app/Exports/GuestInvitationQueryExport.php`
- `app/Exports/OperationalIncidentExport.php`
- `app/Exports/OrganizerFinanceExport.php`
- `app/Exports/OrganizerFinanceQueryExport.php`
- `app/Exports/PaymentReconciliationEntryExport.php`
- `app/Exports/PaymentReconciliationRunExport.php`
- `app/Exports/ProductOrderQueryExport.php`
- `app/Exports/RegistrationQueryExport.php`
- `app/Exports/TransactionQueryExport.php`
- `app/Exports/WebhookReceiptExport.php`
- `app/Exports/WithdrawalQueryExport.php`

## Migrations

- `database/migrations/2023_03_04_134315_drop_secondary_color_from_basic_settings_table.php`
- `database/migrations/2023_03_05_152403_add_tax_commission_percentage_column_to_bookings_table.php`
- `database/migrations/2023_03_22_115828_add_column_to_conversations_table.php`
- `database/migrations/2023_05_11_150902_create_ticket_contents_table.php`
- `database/migrations/2023_05_13_124521_create_variation_contents_table.php`
- `database/migrations/2023_05_20_154216_add_about_page_title_column_to_page_headings_table.php`
- `database/migrations/2023_05_20_154329_add_meta_keyword_description_column_to_seos_table.php`
- `database/migrations/2023_07_30_094527_add_scan_status_column_to_bookings_table.php`
- `database/migrations/2023_09_30_162759_add_tax_percentage_column_to_product_orders_table.php`
- `database/migrations/2023_11_16_062730_add_event_guest_checkout_status_to_basic_settings_table.php`
- `database/migrations/2024_02_07_055018_add_midtrans_payment_gateway_row_to_online_gateways_table.php`
- `database/migrations/2024_02_07_172740_add_iyzico_payment_gateway_into_online_gateways_table.php`
- `database/migrations/2024_02_08_153546_add_paytabs_payment_gateway_into_online_gateways.php`
- `database/migrations/2024_02_10_105443_add_toyyibpay_payment_gateway_into_online_gateways.php`
- `database/migrations/2024_02_10_122829_add_phonepe_payment_gateway_into_online_gateways_table.php`
- `database/migrations/2024_02_10_152845_add_yoco_payment_gateway_into_online_gateways.php`
- `database/migrations/2024_02_10_172724_add_xindit_payment_gateway_into_online_gateways.php`
- `database/migrations/2024_02_11_143939_add_myfatoorah_payment_gateway_into_online_gateways.php`
- `database/migrations/2024_02_12_120007_add_conversation_id_to_event_bookings_table.php`
- `database/migrations/2024_02_12_162617_add_conversation_id_to_product_orders_table.php`
- `database/migrations/2024_02_14_112643_add_perfect_money_payment_gateway_into_online_gateways_table.php`
- `database/migrations/2026_11_01_000000_create_audit_logs_table.php`
- `database/migrations/2026_11_01_010000_create_payment_intents_table.php`
- `database/migrations/2026_11_01_020000_create_ticket_reservations_table.php`
- `database/migrations/2026_11_01_030000_create_payment_refunds_table.php`
- `database/migrations/2026_11_01_040000_create_payment_reconciliations_tables.php`
- `database/migrations/2026_11_01_050000_create_gateway_settlements_tables.php`
- `database/migrations/2026_11_01_060000_create_organizer_kyc_tables.php`
- `database/migrations/2026_11_01_070000_create_organizer_payout_accounts_table.php`
- `database/migrations/2026_11_01_080000_add_review_fields_to_withdrawals_table.php`
- `database/migrations/2026_11_01_090000_add_soft_deletes_to_tickets_table.php`
- `database/migrations/2026_11_01_120000_create_booking_tickets_table.php`
- `database/migrations/2026_11_01_130000_create_booking_ticket_events_table.php`
- `database/migrations/2026_11_01_140000_create_booking_ticket_transfers_table.php`
- `database/migrations/2026_11_01_150000_create_scanner_devices_table.php`
- `database/migrations/2026_11_01_160000_create_scanner_check_in_conflicts_table.php`
- `database/migrations/2026_11_01_170000_create_scanner_staff_table.php`
- `database/migrations/2026_11_01_180000_create_scanner_offline_tables.php`
- `database/migrations/2026_11_01_190000_add_recovery_fields_to_payment_intents_table.php`
- `database/migrations/2026_11_01_200000_add_gateway_identity_to_transactions_table.php`
- `database/migrations/2026_11_01_210000_create_booking_deliveries_table.php`
- `database/migrations/2026_11_01_220000_ensure_queue_tables_exist.php`
- `database/migrations/2026_11_01_230000_create_payout_transfers_table.php`
- `database/migrations/2026_11_01_240000_create_funnel_analytics_events_table.php`
- `database/migrations/2026_11_01_250000_create_data_exports_table.php`
- `database/migrations/2026_11_01_260000_create_operational_incidents_table.php`
- `database/migrations/2026_11_01_270000_create_webhook_receipts_table.php`
- `database/migrations/2026_11_01_280000_add_inventory_lifecycle_to_product_orders.php`
- `database/migrations/2026_11_01_290000_create_product_order_deliveries_table.php`
- `database/migrations/2026_11_01_300000_create_product_order_refunds_table.php`
- `database/migrations/2026_11_01_310000_add_gateway_transaction_id_to_payment_intents_table.php`
- `database/migrations/2026_11_01_320000_create_product_payment_intents_table.php`
- `database/migrations/2026_11_01_330000_create_product_gateway_refunds_table.php`
- `database/migrations/2026_11_02_000000_add_organizer_approval_to_payment_refunds.php`
- `database/migrations/2026_11_02_010000_create_event_cancellation_workflow.php`
- `database/migrations/2026_11_02_020000_create_event_waitlist_entries_table.php`
- `database/migrations/2026_11_02_030000_create_event_guest_invitations_table.php`
- `database/migrations/2026_11_02_040000_create_event_registration_workflow.php`
- `database/migrations/2026_11_02_050000_create_event_registration_notifications_table.php`
- `database/migrations/2026_11_02_060000_add_retention_fields_to_event_registration_submissions.php`
- `database/migrations/2026_11_02_070000_create_event_registration_imports.php`
- `database/migrations/2026_11_02_080000_create_event_guest_imports.php`
- `database/migrations/2026_11_02_090000_add_retention_fields_to_event_guest_invitations.php`
- `database/migrations/2026_11_02_100000_add_registration_templates_and_ticket_scoping.php`
- `database/migrations/2026_11_02_110000_create_registration_sponsor_allocations.php`
- `database/migrations/2026_11_02_120000_create_event_registration_sessions.php`
- `database/migrations/2026_11_02_130000_create_event_registration_invoices.php`
- `database/migrations/2026_11_02_140000_create_event_registration_badge_settings.php`
- `database/migrations/2026_11_02_150000_create_event_registration_embed_settings.php`
- `database/migrations/2026_11_02_160000_create_data_export_schedules_table.php`
- `database/migrations/2026_11_02_170000_add_export_fields_to_role_permissions.php`
- `database/migrations/2026_11_02_180000_create_payout_disputes_table.php`
- `database/migrations/2026_11_02_190000_create_payment_refund_disputes_table.php`
- `database/migrations/2026_11_02_200000_add_resolution_to_event_cancellation_cases.php`
- `database/migrations/2026_11_02_210000_create_booking_ticket_delivery_requests_table.php`
- `database/migrations/2026_11_03_090000_add_delivery_state_to_booking_ticket_transfers_table.php`
- `database/migrations/2026_11_03_120000_add_release_policy_metadata_to_payout_transfers.php`
- `database/migrations/2026_11_03_120000_add_variation_to_event_registration_attendees.php`
- `database/migrations/2026_11_03_120000_create_organizer_email_verification_deliveries_table.php`
- `database/migrations/2026_11_03_130000_add_settings_to_event_registration_questions.php`
- `database/migrations/2026_11_03_130000_create_customer_email_verification_deliveries_table.php`
- `database/migrations/2026_11_03_131000_create_event_registration_files_table.php`
- `database/migrations/2026_11_03_140000_create_subscriber_campaign_tables.php`
- `database/migrations/2026_11_03_150000_create_application_mail_deliveries_table.php`
- `database/migrations/2026_11_03_160000_create_push_notification_campaign_tables.php`
- `database/migrations/2026_11_03_170000_expand_basic_setting_secret_columns.php`
- `database/migrations/2026_11_03_180000_remove_legacy_maintenance_bypass_tokens.php`
- `database/migrations/2026_11_04_000000_enforce_single_platform_earning_row.php`
- `database/migrations/2026_11_04_010000_create_customer_privacy_requests_table.php`
- `database/migrations/2026_11_05_010000_add_scanner_staff_identity_to_booking_tickets.php`
- `database/migrations/2026_11_05_020000_add_check_in_source_to_booking_tickets.php`
- `database/migrations/2026_11_05_030000_add_checkout_submission_hash_to_bookings.php`
- `database/migrations/2026_11_05_040000_add_buyer_identity_hashes.php`
- `database/migrations/2026_11_05_050000_add_sms_metadata_to_booking_deliveries.php`
- `database/migrations/2026_11_05_060000_enforce_unique_sms_provider_references.php`
- `database/migrations/2026_11_05_070000_add_campaign_dispatch_metadata.php`
- `database/migrations/2026_11_05_080000_add_data_export_dispatch_metadata.php`
- `database/migrations/2026_11_05_090000_add_import_dispatch_metadata.php`
- `database/migrations/2026_11_05_100000_add_payout_dispatch_metadata.php`
- `database/migrations/2026_11_05_110000_add_payout_notification_dispatch_metadata.php`
- `database/migrations/2026_11_05_120000_add_allocation_dispatch_metadata.php`
- `database/migrations/2026_11_05_130000_add_ticket_transfer_dispatch_metadata.php`
- `database/migrations/2026_11_05_140000_add_event_guest_invitation_dispatch_metadata.php`
- `database/migrations/2026_11_05_150000_add_email_verification_dispatch_metadata.php`
- `database/migrations/2026_11_05_160000_add_application_mail_dispatch_metadata.php`
- `database/migrations/2026_11_05_170000_add_booking_ticket_delivery_dispatch_metadata.php`
- `database/migrations/2026_11_05_180000_add_event_cancellation_notification_dispatch_metadata.php`
- `database/migrations/2026_11_05_190000_add_event_registration_notification_dispatch_metadata.php`
- `database/migrations/2026_11_05_200000_add_event_waitlist_dispatch_metadata.php`
- `database/migrations/2026_11_05_210000_add_fulfillment_dispatch_metadata.php`
- `database/migrations/2026_11_05_220000_add_ready_notification_metadata_to_data_exports.php`
- `database/migrations/2026_11_06_170000_encrypt_payment_intent_sensitive_fields.php`
- `database/migrations/2026_11_06_180000_encrypt_finance_diagnostic_fields.php`
- `database/migrations/2026_11_06_190000_encrypt_operational_diagnostic_fields.php`
- `database/migrations/2026_11_10_120000_add_security_hold_to_organizer_payout_accounts.php`
- `database/migrations/2026_11_10_121000_add_dedupe_key_to_application_mail_deliveries.php`
- `database/migrations/2026_11_10_122000_add_private_join_url_to_events.php`
- `database/migrations/2026_11_10_123000_create_customer_login_codes_table.php`
- `database/migrations/2026_11_10_124000_create_database_cache_and_session_tables.php`
- `database/migrations/2026_11_11_090000_add_percentage_booking_fee.php`
- `database/migrations/2026_11_11_091000_add_tica_event_settings.php`
- `database/migrations/2026_11_11_092000_add_ticket_id_to_bookings.php`
- `database/migrations/2026_11_11_093000_add_tica_organizer_workspace.php`
- `database/migrations/2026_11_11_094000_add_event_currencies.php`
- `database/migrations/2026_11_11_095000_add_tica_platform_settings.php`
- `database/migrations/2026_11_11_096000_add_tica_site_content.php`
- `database/migrations/2026_11_11_097000_add_monnify_gateway.php`
- `database/migrations/2026_11_12_150000_create_tica_promotion_governance_tables.php`
- `database/migrations/2026_11_12_170000_add_account_mfa_security.php`
- `database/migrations/2026_11_13_090000_add_icon_to_event_categories.php`
- `database/migrations/2026_11_13_100000_add_organizer_order_index_to_bookings.php`
- `database/migrations/2026_11_13_120000_add_review_fields_to_organizer_payout_accounts.php`
- `database/migrations/2026_11_13_130000_add_totp_to_organizers.php`
- `database/migrations/2026_11_13_200000_create_organizer_notifications_table.php`
- `database/migrations/2026_11_13_220000_create_platform_integrations_table.php`
- `database/migrations/2026_11_14_100000_make_registration_forms_reusable.php`
- `database/migrations/2026_11_14_160000_add_totp_to_account_actors.php`
- `database/migrations/2026_11_14_180000_create_advanced_ticketing_tables.php`
- `database/migrations/2026_11_14_180000_expand_tica_promotion_system.php`
- `database/migrations/2026_11_15_090000_create_organizer_operations_tables.php`
- `database/migrations/2026_11_17_100000_add_timezone_to_events.php`
- `database/migrations/2026_11_17_120000_create_financial_ledger.php`
- `database/migrations/2026_11_17_180000_create_event_programme_tables.php`
- `database/migrations/2026_11_18_120000_add_transactional_mail_templates.php`

## Seeders

- `database/seeders/DatabaseSeeder.php`
- `database/seeders/MailTemplateSeeder.php`
- `database/seeders/TicaUiTestAdminSeeder.php`
- `database/seeders/TicaUiTestCustomerSeeder.php`
- `database/seeders/TicaUiTestOrganizerSeeder.php`

## Unit tests

- `tests/Unit/AccountMfaMigrationTest.php`
- `tests/Unit/AccountMfaSecurityTest.php`
- `tests/Unit/AccountMutationRouteBoundaryTest.php`
- `tests/Unit/AdminAccountServiceTest.php`
- `tests/Unit/AdminDirectoryOperationsTest.php`
- `tests/Unit/AdminEventBuilderParityTest.php`
- `tests/Unit/AdminLoginPresentationTest.php`
- `tests/Unit/AdminMutationAuthorizationTest.php`
- `tests/Unit/AdminProfileUploadValidationTest.php`
- `tests/Unit/AdminQueuesTest.php`
- `tests/Unit/AdminSidebarPresentationTest.php`
- `tests/Unit/AdvancedTicketingModuleTest.php`
- `tests/Unit/ApplicationMailDeliveryTest.php`
- `tests/Unit/ApplicationMailDispatchMigrationTest.php`
- `tests/Unit/AttendeeDateFilterTest.php`
- `tests/Unit/AuditGovernanceTest.php`
- `tests/Unit/AuditLoggerTest.php`
- `tests/Unit/BasicSettingSecretSecurityTest.php`
- `tests/Unit/BlockWebInstallerMiddlewareTest.php`
- `tests/Unit/BookingCompletionUrlServiceTest.php`
- `tests/Unit/BookingPaymentStateTest.php`
- `tests/Unit/BookingTicketDeliveryDispatchMigrationTest.php`
- `tests/Unit/BookingTicketDeliveryMigrationTest.php`
- `tests/Unit/BookingTicketDeliveryServiceTest.php`
- `tests/Unit/BuyerIdentityHashMigrationTest.php`
- `tests/Unit/CheckInLedgerExportTest.php`
- `tests/Unit/CheckInSourceMigrationTest.php`
- `tests/Unit/CheckoutGatewayOfferingBoundaryTest.php`
- `tests/Unit/CheckoutTicketPricingAuthorityTest.php`
- `tests/Unit/CiSupplyChainPolicyTest.php`
- `tests/Unit/ConfiguredMailerTest.php`
- `tests/Unit/CustomerDataExportTest.php`
- `tests/Unit/CustomerEmailVerificationMigrationTest.php`
- `tests/Unit/CustomerEmailVerificationServiceTest.php`
- `tests/Unit/CustomerLoginCodeServiceTest.php`
- `tests/Unit/CustomerPrivacyRequestMigrationTest.php`
- `tests/Unit/CustomerPrivacyRequestTest.php`
- `tests/Unit/CustomerProfileMutationBoundaryTest.php`
- `tests/Unit/DatabaseCacheSessionMigrationTest.php`
- `tests/Unit/DatabaseUpdateScriptTest.php`
- `tests/Unit/DataExportReadyNotificationTest.php`
- `tests/Unit/DataExportServiceTest.php`
- `tests/Unit/DispatchLifecycleNotificationPreferencesTest.php`
- `tests/Unit/EmailVerificationDispatchMigrationTest.php`
- `tests/Unit/EventBookingDocumentAccessTest.php`
- `tests/Unit/EventBookingDocumentStorageTest.php`
- `tests/Unit/EventBuilderEditTest.php`
- `tests/Unit/EventCancellationCaseExportTest.php`
- `tests/Unit/EventCancellationCaseMigrationTest.php`
- `tests/Unit/EventCancellationNotificationDispatchMigrationTest.php`
- `tests/Unit/EventCancellationServiceTest.php`
- `tests/Unit/EventCheckoutBoundaryTest.php`
- `tests/Unit/EventCheckoutQuoteServiceTest.php`
- `tests/Unit/EventCouponServiceTest.php`
- `tests/Unit/EventDetailPresentationTest.php`
- `tests/Unit/EventDetailsFailureSemanticsTest.php`
- `tests/Unit/EventFeeCalculatorServiceTest.php`
- `tests/Unit/EventGuestInvitationDispatchMigrationTest.php`
- `tests/Unit/EventGuestListServiceTest.php`
- `tests/Unit/EventGuestOperationsTest.php`
- `tests/Unit/EventProgrammeModuleTest.php`
- `tests/Unit/EventPublicVisibilityTest.php`
- `tests/Unit/EventRegistrationAllocationServiceTest.php`
- `tests/Unit/EventRegistrationExportTest.php`
- `tests/Unit/EventRegistrationFileAccessTest.php`
- `tests/Unit/EventRegistrationFilesMigrationTest.php`
- `tests/Unit/EventRegistrationImportTest.php`
- `tests/Unit/EventRegistrationNotificationDispatchMigrationTest.php`
- `tests/Unit/EventRegistrationQuestionSettingsMigrationTest.php`
- `tests/Unit/EventRegistrationRetentionServiceTest.php`
- `tests/Unit/EventRegistrationServiceTest.php`
- `tests/Unit/EventRegistrationVariationMigrationTest.php`
- `tests/Unit/EventShareCardServiceTest.php`
- `tests/Unit/EventWaitlistDispatchMigrationTest.php`
- `tests/Unit/EventWaitlistServiceTest.php`
- `tests/Unit/ExampleTest.php`
- `tests/Unit/FinalizePaidEventBookingTest.php`
- `tests/Unit/FinalizePaidProductOrderTest.php`
- `tests/Unit/FinanceDiagnosticFieldSecurityTest.php`
- `tests/Unit/FinancialLedgerServiceTest.php`
- `tests/Unit/FlutterwaveGatewayClientTest.php`
- `tests/Unit/FlutterwavePaymentVerifierTest.php`
- `tests/Unit/FlutterwaveReconciliationServiceTest.php`
- `tests/Unit/FreeBookingIdempotencyTest.php`
- `tests/Unit/FulfillmentDispatchMigrationTest.php`
- `tests/Unit/FunnelAnalyticsServiceTest.php`
- `tests/Unit/GatewayLedgerBackfillServiceTest.php`
- `tests/Unit/GuestBuyerIdentityRehashServiceTest.php`
- `tests/Unit/GuestListPageTest.php`
- `tests/Unit/GuestTicketClaimServiceTest.php`
- `tests/Unit/HasPermissionMiddlewareTest.php`
- `tests/Unit/ImageMimeTypeRuleTest.php`
- `tests/Unit/ImportDispatchRecoveryTest.php`
- `tests/Unit/IncidentAlertServiceTest.php`
- `tests/Unit/IncidentPayloadPrivacyGuardTest.php`
- `tests/Unit/IndividualTicketCheckInTest.php`
- `tests/Unit/IntegrationsTest.php`
- `tests/Unit/IyzicoDispatchCommandTest.php`
- `tests/Unit/KycDocumentRuleTest.php`
- `tests/Unit/LegacyCheckoutDocumentValidatorTest.php`
- `tests/Unit/LegacyFulfillmentHandoffTest.php`
- `tests/Unit/LegacyPublicCheckoutDocumentsTest.php`
- `tests/Unit/LegacyPublicTicketQrArtifactsTest.php`
- `tests/Unit/LifecycleMailServiceTest.php`
- `tests/Unit/ManualBookingPaymentServiceTest.php`
- `tests/Unit/ManualPaymentDecisionMailServiceTest.php`
- `tests/Unit/ManualProductOrderPaymentServiceTest.php`
- `tests/Unit/MigrateEventBookingDocumentsTest.php`
- `tests/Unit/MigrateProductOrderDocumentsTest.php`
- `tests/Unit/MySqlMigrationIdentifierCompatibilityTest.php`
- `tests/Unit/OfflineGatewayConfigurationSecurityTest.php`
- `tests/Unit/OfflineLedgerBackfillServiceTest.php`
- `tests/Unit/OfflineProductLedgerBackfillServiceTest.php`
- `tests/Unit/OnlineGatewayCredentialSecurityTest.php`
- `tests/Unit/OperationalArchiveServiceTest.php`
- `tests/Unit/OperationalDiagnosticFieldSecurityTest.php`
- `tests/Unit/OperationalRouteBoundaryTest.php`
- `tests/Unit/OperationalStatusServiceTest.php`
- `tests/Unit/OperationsEvidenceExportTest.php`
- `tests/Unit/OrganizerAttendeeDirectoryTest.php`
- `tests/Unit/OrganizerAttendeeIndexMigrationTest.php`
- `tests/Unit/OrganizerAvatarAndProfileTest.php`
- `tests/Unit/OrganizerBalanceAdjustmentServiceTest.php`
- `tests/Unit/OrganizerEmailVerificationMigrationTest.php`
- `tests/Unit/OrganizerEmailVerificationServiceTest.php`
- `tests/Unit/OrganizerEventDefaultPreferencesTest.php`
- `tests/Unit/OrganizerFinanceStatementServiceTest.php`
- `tests/Unit/OrganizerInboxTest.php`
- `tests/Unit/OrganizerKycServiceTest.php`
- `tests/Unit/OrganizerNotificationPreferenceTest.php`
- `tests/Unit/OrganizerOverviewMetricsTest.php`
- `tests/Unit/OrganizerPayoutAccountServiceTest.php`
- `tests/Unit/OrganizerProfileMutationBoundaryTest.php`
- `tests/Unit/OrganizerShellTest.php`
- `tests/Unit/OrganizerToolPagesTest.php`
- `tests/Unit/OrganizerWorkspaceConsolidationTest.php`
- `tests/Unit/OutboundTransportSecurityTest.php`
- `tests/Unit/PasswordPolicyTest.php`
- `tests/Unit/PasswordResetServiceTest.php`
- `tests/Unit/PaymentEvidenceFileRuleTest.php`
- `tests/Unit/PaymentExceptionDisclosureTest.php`
- `tests/Unit/PaymentGatewayCallbackBoundaryTest.php`
- `tests/Unit/PaymentGatewayPolicyTest.php`
- `tests/Unit/PaymentIntentMonitorBoundedTest.php`
- `tests/Unit/PaymentIntentSensitiveDataSecurityTest.php`
- `tests/Unit/PaymentReconciliationCommandOperationalSafetyTest.php`
- `tests/Unit/PaymentRefundDisputeMigrationTest.php`
- `tests/Unit/PaymentRefundServiceTest.php`
- `tests/Unit/PayoutDisputeServiceTest.php`
- `tests/Unit/PayoutEligibilityServiceTest.php`
- `tests/Unit/PayoutNotificationDispatchMigrationTest.php`
- `tests/Unit/PayoutProviderTest.php`
- `tests/Unit/PayoutReleasePolicyMigrationTest.php`
- `tests/Unit/PayoutSmsAndDefaultsTest.php`
- `tests/Unit/PayoutTransferServiceTest.php`
- `tests/Unit/PaystackEventCheckoutServiceTest.php`
- `tests/Unit/PaystackGatewayClientTest.php`
- `tests/Unit/PaystackPaymentRecoveryServiceTest.php`
- `tests/Unit/PaystackPaymentVerifierTest.php`
- `tests/Unit/PaystackReconciliationServiceTest.php`
- `tests/Unit/PaystackSettlementCommandIsolationTest.php`
- `tests/Unit/PaystackSettlementReconciliationServiceTest.php`
- `tests/Unit/PhaseThreeEventManagementTest.php`
- `tests/Unit/PlatformEarningMutationBoundaryTest.php`
- `tests/Unit/PlatformEarningServiceTest.php`
- `tests/Unit/PlatformEarningSingletonMigrationTest.php`
- `tests/Unit/ProductOrderAccountingServiceTest.php`
- `tests/Unit/ProductOrderDataExportTest.php`
- `tests/Unit/ProductOrderDocumentStorageTest.php`
- `tests/Unit/ProductOrderInventoryServiceTest.php`
- `tests/Unit/ProductOrderRefundServiceTest.php`
- `tests/Unit/ProductPaystackCheckoutServiceTest.php`
- `tests/Unit/ProductPaystackPaymentServiceTest.php`
- `tests/Unit/ProductPaystackPaymentVerifierTest.php`
- `tests/Unit/ProductReviewBoundaryTest.php`
- `tests/Unit/PublicAssetStorageRouteTest.php`
- `tests/Unit/PublicContentPolicyTest.php`
- `tests/Unit/PublicControllerFailureSemanticsTest.php`
- `tests/Unit/PublicImageTest.php`
- `tests/Unit/PublicWriteBoundaryTest.php`
- `tests/Unit/PurgeLegacyPublicCheckoutDocumentsTest.php`
- `tests/Unit/PushNotificationCampaignMigrationTest.php`
- `tests/Unit/PushNotificationCampaignServiceTest.php`
- `tests/Unit/QueueHealthServiceTest.php`
- `tests/Unit/QueueMonitorCommandTest.php`
- `tests/Unit/ReconciliationDataExportTest.php`
- `tests/Unit/RefundCommandOperationalSafetyTest.php`
- `tests/Unit/RegistrationAllocationDispatchMigrationTest.php`
- `tests/Unit/RegistrationFormEditorViewTest.php`
- `tests/Unit/ReleaseReadinessCommandTest.php`
- `tests/Unit/ReleaseReadinessServiceTest.php`
- `tests/Unit/RemovedOrganizerOperationsTest.php`
- `tests/Unit/RepositorySecretHygieneTest.php`
- `tests/Unit/RequestIdMiddlewareTest.php`
- `tests/Unit/RolePermissionServiceTest.php`
- `tests/Unit/RuntimeConfigurationServiceTest.php`
- `tests/Unit/RuntimeDocumentStoreTest.php`
- `tests/Unit/ScannerDeviceServiceTest.php`
- `tests/Unit/ScannerMetricsServiceTest.php`
- `tests/Unit/ScannerOfflineServiceTest.php`
- `tests/Unit/ScannerStaffAttributionMigrationTest.php`
- `tests/Unit/ScannerStaffServiceTest.php`
- `tests/Unit/SchemaAwareTicketSoftDeleteTest.php`
- `tests/Unit/SecurityHeadersMiddlewareTest.php`
- `tests/Unit/SensitiveActionConfirmationTest.php`
- `tests/Unit/ServerlessFilesystemSafetyTest.php`
- `tests/Unit/SharedStateHealthServiceTest.php`
- `tests/Unit/ShopCartMutationBoundaryTest.php`
- `tests/Unit/ShopCheckoutPricingServiceTest.php`
- `tests/Unit/ShopCheckoutSessionServiceTest.php`
- `tests/Unit/ShopGuestCheckoutBoundaryTest.php`
- `tests/Unit/SignedCancellationUrlTest.php`
- `tests/Unit/SignedTicketPayloadTest.php`
- `tests/Unit/SubscriberCampaignMigrationTest.php`
- `tests/Unit/SubscriberCampaignServiceTest.php`
- `tests/Unit/SummernoteControllerSecurityTest.php`
- `tests/Unit/TeamPermissionMatrixTest.php`
- `tests/Unit/TicaCurrencyMemoizationTest.php`
- `tests/Unit/TicaImageDeferralTest.php`
- `tests/Unit/TicaPromotionGovernanceTest.php`
- `tests/Unit/TicaTeamAccessRevocationTest.php`
- `tests/Unit/TicaWebsiteCmsTest.php`
- `tests/Unit/TicketIssuanceServiceTest.php`
- `tests/Unit/TicketLifecycleServiceTest.php`
- `tests/Unit/TicketReservationServiceTest.php`
- `tests/Unit/TicketRetirementServiceTest.php`
- `tests/Unit/TicketSmsDeliveryMigrationTest.php`
- `tests/Unit/TicketSmsDeliveryTest.php`
- `tests/Unit/TicketTransferDeliveryMigrationTest.php`
- `tests/Unit/TicketTransferDispatchMigrationTest.php`
- `tests/Unit/TicketTransferInvitationMailTest.php`
- `tests/Unit/TransactionalMailTemplateTest.php`
- `tests/Unit/TransactionDataExportTest.php`
- `tests/Unit/TransactionLedgerServiceTest.php`
- `tests/Unit/UploadFileSecurityTest.php`
- `tests/Unit/WebhookObservabilityTest.php`
- `tests/Unit/WithdrawalDataExportTest.php`
- `tests/Unit/WithdrawalDecisionServiceTest.php`
- `tests/Unit/WithdrawalServiceTest.php`
- `tests/Unit/WorkspaceWidthConsistencyTest.php`

## Feature tests

- `tests/Feature/ExampleTest.php`
- `tests/Feature/TicaPromotionExpansionMigrationTest.php`
- `tests/Feature/TicaPromotionPurchaseFlowTest.php`

## Form and client-side submission inventory

This appendix records every source call site containing an HTML form, React form helper, `FormData`, `fetch`, or mutating Axios call. Vendor installer views are included because they are present in the application tree; installer access is separately blocked by middleware.

| File | Line | Call site |
|---|---:|---|
| `resources/views/auth/account-security.blade.php` | 30 | `<div class="setup-grid"><div><div class="qr">{!! $qrSvg !!}</div><details class="manual"><summary>Can't scan the QR code?</summary><code class="secret">{{ $setup['secret'] }}</code></details></div><form method="post" ...` |
| `resources/views/auth/account-security.blade.php` | 35 | `<div class="danger-zone"><h3>Turn off two-step verification</h3><p>Confirm with both your password and an existing authenticator or recovery code.</p><form class="form" method="post" action="{{ route('account-security...` |
| `resources/views/auth/account-security.blade.php` | 40 | `<form class="form" method="post" action="{{ route('account-security.request', $guard) }}">@csrf<input type="hidden" name="action" value="enable"><div class="field"><label for="password">Current password</label><input ...` |
| `resources/views/auth/account-security.blade.php` | 41 | `<div class="danger-zone"><h3>Turn off email verification</h3>@if($legacyPending)<form class="form" method="post" action="{{ route('account-security.verify', $guard) }}">@csrf<div class="field"><label for="legacy-code"...` |
| `resources/views/auth/account-security.blade.php` | 46 | `<form class="form" method="post" action="{{ route('account-security.request', $guard) }}">@csrf<input type="hidden" name="action" value="enable"><div class="field"><label for="password">Confirm your current password</...` |
| `resources/views/auth/mfa-challenge.blade.php` | 25 | `<form method="post" action="{{ route('mfa.login.verify') }}">` |
| `resources/views/auth/mfa-challenge.blade.php` | 34 | `<form method="post" action="{{ route('mfa.login.cancel') }}">@csrf<button class="cancel" type="submit">Cancel sign-in</button></form>` |
| `resources/views/backend/admin/audit-logs/index.blade.php` | 10 | `<form method="GET" class="row align-items-end">` |
| `resources/views/backend/admin/audit-logs/index.blade.php` | 17 | `<form method="POST" action="{{ route('admin.audit_logs.export') }}" class="d-flex align-items-center mt-2">` |
| `resources/views/backend/admin/change-password.blade.php` | 35 | `<form id="ajaxEditForm" action="{{ route('admin.update_password') }}" method="post">` |
| `resources/views/backend/admin/data-exports/index.blade.php` | 19 | `<form method="POST" action="{{ route('admin.data_export_schedules.store') }}" class="row align-items-end">` |
| `resources/views/backend/admin/data-exports/index.blade.php` | 29 | `<tr><td>{{ ucfirst($schedule->frequency) }} · {{ strtoupper($schedule->format) }}</td><td>{{ trans_choice(':count day\|:count days', $schedule->date_window_days, ['count' => $schedule->date_window_days]) }}</td><td>{{...` |
| `resources/views/backend/admin/earning.blade.php` | 34 | `<form action="{{ route('admin.monthly_earning') }}" id="year" method="get">` |
| `resources/views/backend/admin/edit-profile.blade.php` | 35 | `<form id="editProfileForm" action="{{ route('admin.update_profile') }}" method="POST"` |
| `resources/views/backend/admin/funnel-analytics/index.blade.php` | 23 | `<form method="get" class="form-inline justify-content-lg-end">` |
| `resources/views/backend/admin/operational-incidents/index.blade.php` | 22 | `<form method="get" class="form-inline mt-2 mt-lg-0">` |
| `resources/views/backend/admin/operational-incidents/index.blade.php` | 38 | `<form method="post" action="{{ route('admin.operational_incidents.export') }}" class="form-inline justify-content-end mb-3">` |
| `resources/views/backend/admin/operational-incidents/index.blade.php` | 65 | `<form method="post" action="{{ route('admin.operational_incidents.resolve', $incident) }}" class="d-flex">` |
| `resources/views/backend/admin/payment-reconciliation/index.blade.php` | 24 | `<form method="get" class="form-inline justify-content-lg-end">` |
| `resources/views/backend/admin/payment-reconciliation/index.blade.php` | 47 | `<form method="post" action="{{ route('admin.payment_reconciliation.export') }}" class="form-inline justify-content-end mb-3">` |
| `resources/views/backend/admin/payment-reconciliation/settlement-show.blade.php` | 22 | `<div class="col-lg-7"><form method="get" class="form-inline justify-content-lg-end">` |
| `resources/views/backend/admin/payment-reconciliation/settlement-show.blade.php` | 32 | `<form method="post" action="{{ route('admin.settlement_reconciliation.entries.export',$settlement) }}" class="form-inline justify-content-end mb-3">` |
| `resources/views/backend/admin/payment-reconciliation/settlements.blade.php` | 18 | `<div class="col-lg-8"><form method="get" class="form-inline justify-content-lg-end">` |
| `resources/views/backend/admin/payment-reconciliation/settlements.blade.php` | 31 | `<form method="post" action="{{ route('admin.settlement_reconciliation.export') }}" class="form-inline justify-content-end mb-3">` |
| `resources/views/backend/admin/payment-reconciliation/show.blade.php` | 23 | `<div class="col-lg-7"><form method="get" class="form-inline justify-content-lg-end">` |
| `resources/views/backend/admin/payment-reconciliation/show.blade.php` | 35 | `<form method="post" action="{{ route('admin.payment_reconciliation.entries.export',$run) }}" class="form-inline justify-content-end mb-3">` |
| `resources/views/backend/admin/profit.blade.php` | 34 | `<form action="{{ route('admin.monthly_profit') }}" id="year" method="get">` |
| `resources/views/backend/admin/transaction.blade.php` | 64 | `<form method="post" action="{{ route('admin.refund_disputes.review', $dispute) }}" class="mb-3">` |
| `resources/views/backend/admin/transaction.blade.php` | 72 | `<form method="post" action="{{ route('admin.refund_disputes.approve', $dispute) }}">` |
| `resources/views/backend/admin/transaction.blade.php` | 84 | `<form method="post" action="{{ route('admin.refund_disputes.review', $dispute) }}" class="mt-2">` |
| `resources/views/backend/admin/transaction.blade.php` | 135 | `<form method="post" action="{{ route('admin.refunds.approve', $refundRequest) }}" class="d-inline-block mb-2">` |
| `resources/views/backend/admin/transaction.blade.php` | 141 | `<form method="post" action="{{ route('admin.refunds.reject', $refundRequest) }}" class="mt-2">` |
| `resources/views/backend/admin/transaction.blade.php` | 172 | `<form action="{{ route('admin.transcation') }}" method="get" class="row align-items-end">` |
| `resources/views/backend/admin/transaction.blade.php` | 181 | `<form method="POST" action="{{ route('admin.transaction_exports.store') }}" class="form-inline mr-2 mb-2">@csrf` |
| `resources/views/backend/admin/transaction.blade.php` | 351 | `<form method="POST" action="{{ $hasCheckedInAdmission ? route('admin.refund_disputes.store') : route('admin.refunds.store') }}">` |
| `resources/views/backend/admin/webhook-operations/index.blade.php` | 24 | `<form method="get" class="form-inline justify-content-lg-end">` |
| `resources/views/backend/admin/webhook-operations/index.blade.php` | 57 | `<form method="post" action="{{ route('admin.webhook_operations.export') }}" class="form-inline justify-content-end mb-3">` |
| `resources/views/backend/administrator/role-permission/create.blade.php` | 12 | `<form id="ajaxForm" class="modal-form" action="{{ route('admin.admin_management.store_role') }}" method="post">` |
| `resources/views/backend/administrator/role-permission/edit.blade.php` | 12 | `<form id="ajaxEditForm" class="modal-form" action="{{ route('admin.admin_management.update_role') }}" method="post">` |
| `resources/views/backend/administrator/role-permission/index.blade.php` | 73 | `<form class="deleteForm d-inline-block" action="{{ route('admin.admin_management.delete_role', ['id' => $role->id]) }}" method="post">` |
| `resources/views/backend/administrator/role-permission/permissions.blade.php` | 36 | `<form action="{{ route('admin.admin_management.role.update_permissions', ['id' => $role->id]) }}" method="post">` |
| `resources/views/backend/administrator/site-admin/create.blade.php` | 12 | `<form id="ajaxForm" class="modal-form" action="{{ route('admin.admin_management.store_admin') }}" method="POST" enctype="multipart/form-data">` |
| `resources/views/backend/administrator/site-admin/edit.blade.php` | 12 | `<form id="ajaxEditForm" class="modal-form" action="{{ route('admin.admin_management.update_admin') }}" method="POST" enctype="multipart/form-data">` |
| `resources/views/backend/administrator/site-admin/index.blade.php` | 76 | `<form id="statusForm-{{ $admin->id }}" class="d-inline-block" action="{{ route('admin.admin_management.admin.update_status', ['id' => $admin->id]) }}" method="post">` |
| `resources/views/backend/administrator/site-admin/index.blade.php` | 93 | `<form class="archiveAdminForm d-inline-block" action="{{ route('admin.admin_management.delete_admin', ['id' => $admin->id]) }}" method="post">` |
| `resources/views/backend/advertisement/create.blade.php` | 12 | `<form id="ajaxForm" class="modal-form" action="{{ route('admin.advertise.store_advertisement') }}" method="post" enctype="multipart/form-data">` |
| `resources/views/backend/advertisement/edit.blade.php` | 12 | `<form id="ajaxEditForm" class="modal-form" action="{{ route('admin.advertise.update_advertisement') }}" method="post" enctype="multipart/form-data">` |
| `resources/views/backend/advertisement/index.blade.php` | 100 | `<form class="deleteForm d-inline-block"` |
| `resources/views/backend/advertisement/settings.blade.php` | 30 | `<form action="{{ route('admin.advertise.update_settings') }}" method="post">` |
| `resources/views/backend/basic-settings/breadcrumb.blade.php` | 41 | `<form id="imageForm" action="{{ route('admin.basic_settings.update_breadcrumb') }}" method="POST" enctype="multipart/form-data">` |
| `resources/views/backend/basic-settings/contact.blade.php` | 24 | `<form action="{{ route('admin.basic_settings.update_info') }}" method="post">` |
| `resources/views/backend/basic-settings/cookie-alert.blade.php` | 48 | `<form id="ajaxForm" action="{{ route('admin.basic_settings.update_cookie_alert', ['language' => request()->input('language')]) }}" method="post">` |
| `resources/views/backend/basic-settings/email/edit-template.blade.php` | 56 | `<form id="mailTemplateForm"` |
| `resources/views/backend/basic-settings/email/mail-from-admin.blade.php` | 36 | `<form action="{{ route('admin.basic_settings.update_mail_from_admin') }}" method="post">` |
| `resources/views/backend/basic-settings/email/mail-to-admin.blade.php` | 36 | `<form action="{{ route('admin.basic_settings.update_mail_to_admin') }}" method="post">` |
| `resources/views/backend/basic-settings/footer-logo.blade.php` | 41 | `<form id="imageForm" action="{{ route('admin.basic_settings.update_footer_logo') }}" method="POST" enctype="multipart/form-data">` |
| `resources/views/backend/basic-settings/general-settings.blade.php` | 30 | `<form action="{{ route('admin.basic_settings.general_settings.update') }}" method="post"` |
| `resources/views/backend/basic-settings/maintenance.blade.php` | 41 | `<form id="maintenanceForm" action="{{ route('admin.basic_settings.update_maintenance_mode') }}"` |
| `resources/views/backend/basic-settings/page-headings.blade.php` | 33 | `<form` |
| `resources/views/backend/basic-settings/plugins.blade.php` | 32 | `<form action="{{ route('admin.basic_settings.update_disqus') }}" method="post">` |
| `resources/views/backend/basic-settings/plugins.blade.php` | 94 | `<form action="{{ route('admin.basic_settings.update_recaptcha') }}" method="post">` |
| `resources/views/backend/basic-settings/plugins.blade.php` | 166 | `<form action="{{ route('admin.basic_settings.update_facebook') }}" method="post">` |
| `resources/views/backend/basic-settings/plugins.blade.php` | 238 | `<form action="{{ route('admin.basic_settings.update_google') }}" method="post">` |
| `resources/views/backend/basic-settings/pwa.blade.php` | 24 | `<form class="" action="{{ route('admin.pwa.update') }}" method="post" enctype="multipart/form-data">` |
| `resources/views/backend/basic-settings/pwa_scanner.blade.php` | 28 | `<form class="" action="{{ route('admin.pwa.scanner.update') }}" method="post"` |
| `resources/views/backend/basic-settings/seo.blade.php` | 33 | `<form action="{{ route('admin.basic_settings.update_seo', ['language' => request()->input('language')]) }}"` |
| `resources/views/backend/basic-settings/social-media/create.blade.php` | 12 | `<form id="ajaxForm" class="modal-form" action="{{ route('admin.basic_settings.store_social_media') }}" method="post">` |
| `resources/views/backend/basic-settings/social-media/edit.blade.php` | 12 | `<form id="ajaxEditForm" class="modal-form" action="{{ route('admin.basic_settings.update_social_media') }}" method="post">` |
| `resources/views/backend/basic-settings/social-media/index.blade.php` | 73 | `<form class="deleteForm d-inline-block" action="{{ route('admin.basic_settings.delete_social_media', ['id' => $media->id]) }}" method="post">` |
| `resources/views/backend/basic-settings/theme-&-home.blade.php` | 30 | `<form action="{{ route('admin.basic_settings.update_theme_and_home') }}" method="post">` |
| `resources/views/backend/contact.blade.php` | 55 | `<form id="ajaxForm" class="create"` |
| `resources/views/backend/custom-page/create.blade.php` | 49 | `<form id="pageForm" action="{{ route('admin.custom_pages.store_page') }}" method="POST">` |
| `resources/views/backend/custom-page/edit.blade.php` | 49 | `<form id="pageForm" action="{{ route('admin.custom_pages.update_page', ['id' => $page->id]) }}"` |
| `resources/views/backend/custom-page/index.blade.php` | 93 | `<form class="deleteForm d-block"` |
| `resources/views/backend/end-user/customer/change-password.blade.php` | 62 | `<form id="ajaxEditForm"` |
| `resources/views/backend/end-user/customer/create.blade.php` | 47 | `<form id="ajaxEditForm" action="{{ route('admin.organizer_management.store_customer') }}" method="post">` |
| `resources/views/backend/end-user/customer/details.blade.php` | 164 | `<form class="float-right ml-3"` |
| `resources/views/backend/end-user/customer/details.blade.php` | 172 | `<form id="searchByStatusForm" class="float-right d-flex flex-row align-items-center"` |
| `resources/views/backend/end-user/customer/details.blade.php` | 256 | `<form id="paymentStatusForm-{{ $booking->id }}"` |
| `resources/views/backend/end-user/customer/details.blade.php` | 320 | `<form class="deleteForm d-block"` |
| `resources/views/backend/end-user/customer/edit.blade.php` | 62 | `<form id="ajaxEditForm"` |
| `resources/views/backend/end-user/customer/index.blade.php` | 27 | `<form class="directory-filter" action="{{ route('admin.organizer_management.registered_customer') }}" method="GET">` |
| `resources/views/backend/end-user/customer/index.blade.php` | 34 | `<form class="directory-export" method="POST" action="{{ route('admin.customer_management.export') }}">@csrf<input type="hidden" name="search" value="{{ request('info') }}"><input type="hidden" name="status" value="{{ ...` |
| `resources/views/backend/end-user/customer/index.blade.php` | 44 | `<td data-label="{{ __('Account') }}"><form action="{{ route('admin.customer_management.customer.update_account_status',['id'=>$customer->id]) }}" method="post">@csrf<select class="directory-state-select" name="account...` |
| `resources/views/backend/end-user/customer/index.blade.php` | 45 | `<td data-label="{{ __('Verification') }}"><form action="{{ route('admin.customer_management.customer.update_email_status',['id'=>$customer->id]) }}" method="post">@csrf<select class="directory-state-select" name="emai...` |
| `resources/views/backend/end-user/customer/index.blade.php` | 46 | `<td class="directory-table-action"><div class="dropdown"><button class="directory-more" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="{{ __('Actions for :name',['name'=>$c...` |
| `resources/views/backend/end-user/customer/privacy-requests.blade.php` | 12 | `<form method="GET" class="form-inline">` |
| `resources/views/backend/end-user/customer/privacy-requests.blade.php` | 40 | `<form method="POST" action="{{ route('admin.customer_privacy_requests.update', $privacyRequest) }}">` |
| `resources/views/backend/end-user/organizer/change-password.blade.php` | 61 | `<form id="ajaxEditForm"` |
| `resources/views/backend/end-user/organizer/create.blade.php` | 53 | `<form id="eventForm" class="organizer-form-area"` |
| `resources/views/backend/end-user/organizer/details.blade.php` | 158 | `<form class="deleteForm d-block"` |
| `resources/views/backend/end-user/organizer/edit.blade.php` | 64 | `<form id="eventForm"` |
| `resources/views/backend/end-user/organizer/edit.blade.php` | 287 | `<form` |
| `resources/views/backend/end-user/organizer/index.blade.php` | 31 | `<form class="directory-filter" action="{{ route('admin.organizer_management.registered_organizer') }}" method="GET">` |
| `resources/views/backend/end-user/organizer/index.blade.php` | 38 | `<form class="directory-export" method="POST" action="{{ route('admin.organizer_management.export') }}">` |
| `resources/views/backend/end-user/organizer/index.blade.php` | 55 | `<td data-label="{{ __('Account') }}"><form action="{{ route('admin.organizer_management.organizer.update_account_status', ['id'=>$organizer->id]) }}" method="post">@csrf<select class="directory-state-select" name="acc...` |
| `resources/views/backend/end-user/organizer/index.blade.php` | 56 | `<td data-label="{{ __('Verification') }}"><form action="{{ route('admin.organizer_management.organizer.update_email_status', ['id'=>$organizer->id]) }}" method="post">@csrf<select class="directory-state-select" name="...` |
| `resources/views/backend/end-user/organizer/index.blade.php` | 61 | `<form method="post" action="{{ route('admin.organizer_management.organizer.secret_login', ['id'=>$organizer->id]) }}">@csrf<button class="dropdown-item" type="submit"><i class="fas fa-user-shield mr-2" aria-hidden="tr...` |
| `resources/views/backend/end-user/organizer/index.blade.php` | 63 | `<form class="deleteForm" action="{{ route('admin.organizer_management.organizer.delete', ['id'=>$organizer->id]) }}" method="post">@csrf<button type="submit" class="dropdown-item text-danger deleteBtn" data-action="ar...` |
| `resources/views/backend/end-user/organizer/kyc/index.blade.php` | 6 | `<div class="card-header"><div class="row align-items-center"><div class="col-lg-6"><div class="card-title">{{ __('Verification submissions') }}</div></div><div class="col-lg-6"><form method="get" class="form-inline ju...` |
| `resources/views/backend/end-user/organizer/kyc/show.blade.php` | 14 | `<form method="post" action="{{ route('admin.organizer_kyc.review', $submission) }}">@csrf<div class="form-group"><label>{{ __('Decision') }}</label><select name="decision" id="kycDecision" class="form-control" require...` |
| `resources/views/backend/end-user/organizer/settings.blade.php` | 41 | `<form id="organizerSettingForm" action="{{ route('admin.organizer_management.setting.update') }}"` |
| `resources/views/backend/end-user/push-notification/settings.blade.php` | 41 | `<form id="settingsForm" action="{{ route('admin.user_management.push_notification.update_settings') }}"` |
| `resources/views/backend/end-user/push-notification/write-notification.blade.php` | 30 | `<form action="{{ route('admin.user_management.push_notification.send_notification') }}" method="post">` |
| `resources/views/backend/end-user/subscriber/index.blade.php` | 47 | `<form class="float-right" action="{{ route('admin.user_management.subscribers') }}"` |
| `resources/views/backend/end-user/subscriber/index.blade.php` | 83 | `<form class="deleteForm d-inline-block"` |
| `resources/views/backend/end-user/subscriber/write-email.blade.php` | 36 | `<form action="{{ route('admin.user_management.subscribers.send_email') }}" method="post">` |
| `resources/views/backend/end-user/user/change-password.blade.php` | 47 | `<form id="ajaxEditForm" action="{{ route('admin.user_management.user.update_password', ['id' => $userInfo->id]) }}" method="post">` |
| `resources/views/backend/end-user/user/index.blade.php` | 41 | `<form class="float-right" action="{{ route('admin.user_management.registered_users') }}" method="GET">` |
| `resources/views/backend/end-user/user/index.blade.php` | 78 | `<form id="emailStatusForm-{{ $user->id }}" class="d-inline-block" action="{{ route('admin.user_management.user.update_email_status', ['id' => $user->id]) }}" method="post">` |
| `resources/views/backend/end-user/user/index.blade.php` | 92 | `<form id="accountStatusForm-{{ $user->id }}" class="d-inline-block" action="{{ route('admin.user_management.user.update_account_status', ['id' => $user->id]) }}" method="post">` |
| `resources/views/backend/end-user/user/index.blade.php` | 119 | `<form class="deleteForm d-block" action="{{ route('admin.user_management.user.delete', ['id' => $user->id]) }}" method="post">` |
| `resources/views/backend/event/advanced-tickets.blade.php` | 39 | `<form method="post" action="{{ route('admin.advanced_tickets.issue') }}" class="at-form issue-form">` |
| `resources/views/backend/event/advanced-tickets.blade.php` | 63 | `<form class="at-filters" method="get">` |
| `resources/views/backend/event/advanced-tickets.blade.php` | 93 | `<form method="post" action="{{ route('admin.advanced_tickets.reissue',$unit) }}">@csrf<input type="hidden" name="reason" value="Reissued by TICA admin"><button type="submit">{{ __('Reissue QR credential') }}</button><...` |
| `resources/views/backend/event/advanced-tickets.blade.php` | 94 | `<form method="post" action="{{ route('admin.advanced_tickets.revoke',$unit) }}" onsubmit="return confirm('{{ __('Cancel this ticket and invalidate its QR code?') }}')">@csrf<input type="hidden" name="reason" value="Ca...` |
| `resources/views/backend/event/advanced-tickets.blade.php` | 134 | `const response = await fetch('/tica-api/events/' + encodeURIComponent(eventSelect.value) + '/seats', {headers:{Accept:'application/json'}, credentials:'same-origin'});` |
| `resources/views/backend/event/booking/index.blade.php` | 75 | `<form class="d-flex" action="{{ route('admin.event_bookings.export') }}" method="POST">` |
| `resources/views/backend/event/booking/index.blade.php` | 88 | `<form class="ml-3" action="{{ route('admin.event.booking') }}" method="GET">` |
| `resources/views/backend/event/booking/index.blade.php` | 95 | `<form class="ml-3" action="{{ route('admin.event.booking') }}" method="GET">` |
| `resources/views/backend/event/booking/index.blade.php` | 102 | `<form id="searchByStatusForm" class="d-flex flex-row align-items-center"` |
| `resources/views/backend/event/booking/index.blade.php` | 231 | `<form class="d-inline-block paymentStatusForm"` |
| `resources/views/backend/event/booking/index.blade.php` | 305 | `<form class="deleteForm d-block"` |
| `resources/views/backend/event/booking/report.blade.php` | 35 | `<form action="{{ url()->full() }}" class="form-inline">` |
| `resources/views/backend/event/booking/report.blade.php` | 89 | `<form method="POST" action="{{ route('admin.event_bookings.export') }}" class="form-inline justify-content-lg-end justify-content-start">` |
| `resources/views/backend/event/cancellations.blade.php` | 86 | `<form method="post" action="{{ route('admin.event_cancellations.approve', $cancellation) }}" class="mb-3">` |
| `resources/views/backend/event/cancellations.blade.php` | 93 | `<form method="post" action="{{ route('admin.event_cancellations.reject', $cancellation) }}">` |
| `resources/views/backend/event/cancellations.blade.php` | 110 | `<form method="post" action="{{ route('admin.event_cancellation_exports.store', $cancellation) }}" class="form-inline mb-2">` |
| `resources/views/backend/event/cancellations.blade.php` | 170 | `<form method="post" action="{{ route('admin.event_cancellation_cases.dispute', $case) }}" class="mt-2">` |
| `resources/views/backend/event/cancellations.blade.php` | 184 | `<form method="post" action="{{ route('admin.event_cancellation_cases.resolve', $case) }}" class="mb-3">` |
| `resources/views/backend/event/cancellations.blade.php` | 194 | `<form method="post" action="{{ route('admin.event_cancellation_cases.resolve', $case) }}" class="mt-2">` |
| `resources/views/backend/event/cancellations.blade.php` | 206 | `<form method="post" action="{{ route('admin.event_cancellation_cases.resolve', $case) }}" class="mt-2">` |
| `resources/views/backend/event/category/create.blade.php` | 13 | `<form id="modalForm" class="modal-form create" action="{{ route('admin.event_management.store_category') }}"` |
| `resources/views/backend/event/category/edit.blade.php` | 12 | `<form id="ajaxEditForm" class="modal-form" action="{{ route('admin.event_management.update_category') }}" method="post">` |
| `resources/views/backend/event/category/index.blade.php` | 183 | `<form class="tcat-switch-form" action="{{ route('admin.event_management.category.update_status', ['id' => $category->id]) }}" method="post">` |
| `resources/views/backend/event/category/index.blade.php` | 192 | `<form class="tcat-switch-form" action="{{ route('admin.event_management.category.update_featured', ['id' => $category->id]) }}" method="post">` |
| `resources/views/backend/event/category/index.blade.php` | 217 | `<form class="deleteForm d-inline-block" action="{{ route('admin.event_management.delete_category', ['id' => $category->id]) }}" method="post">` |
| `resources/views/backend/event/category/index.blade.php` | 293 | `fetch(sortable.dataset.reorderUrl, {method:'POST', credentials:'same-origin', headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':@json(csrf_token()),'X-Requested-With':'XMLHttpReques...` |
| `resources/views/backend/event/coupon/create.blade.php` | 13 | `<form id="ajaxForm" class="modal-form" action="{{ route('admin.event_management.store_coupon') }}"` |
| `resources/views/backend/event/coupon/edit.blade.php` | 13 | `<form id="ajaxEditForm" class="modal-form" action="{{ route('admin.event_management.update_coupon') }}"` |
| `resources/views/backend/event/coupon/index.blade.php` | 22 | `<form class="coupon-toolbar" method="GET" action="{{ route('admin.event_management.coupons') }}">` |
| `resources/views/backend/event/coupon/index.blade.php` | 45 | `<div class="dropdown-divider"></div><form class="deleteForm" action="{{ route('admin.event_management.delete_coupon',['id'=>$coupon->id]) }}" method="post">@csrf<button type="submit" class="dropdown-item text-danger d...` |
| `resources/views/backend/event/create.blade.php` | 105 | `<form action="{{ route('admin.event.imagesstore') }}" id="my-dropzone" enctype="multipart/formdata"` |
| `resources/views/backend/event/create.blade.php` | 117 | `<form id="eventForm" action="{{ route('admin.event_management.store_event') }}" method="POST"` |
| `resources/views/backend/event/edit.blade.php` | 110 | `<form action="{{ route('admin.event.imagesstore') }}" id="my-dropzone" enctype="multipart/formdata"` |
| `resources/views/backend/event/edit.blade.php` | 124 | `<form id="eventForm" action="{{ route('admin.event.update') }}" method="POST"` |
| `resources/views/backend/event/guest-checkout.blade.php` | 36 | `<form action="{{ route('admin.event_booking.settings.update_guest_checkout') }}" method="post">` |
| `resources/views/backend/event/index.blade.php` | 110 | `<form action="" method="get">` |
| `resources/views/backend/event/index.blade.php` | 171 | `<form id="statusForm-{{ $event->id }}" class="d-inline-block"` |
| `resources/views/backend/event/index.blade.php` | 202 | `<form id="featuredForm-{{ $event->id }}" class="d-inline-block"` |
| `resources/views/backend/event/index.blade.php` | 241 | `<form class="deleteForm d-block"` |
| `resources/views/backend/event/index.blade.php` | 264 | `<form method="post" action="{{ route('admin.event_cancellations.store', $event->id) }}">` |
| `resources/views/backend/event/tax.blade.php` | 36 | `<form action="{{ route('admin.event_booking.settings.update_tax_commission') }}" method="post">` |
| `resources/views/backend/event/ticket/create.blade.php` | 86 | `<form id="eventForm" action="{{ route('admin.ticket_management.store_ticket') }}" method="POST"` |
| `resources/views/backend/event/ticket/edit.blade.php` | 86 | `<form id="eventForm" action="{{ route('admin.ticket_management.update_ticket') }}" method="POST"` |
| `resources/views/backend/event/ticket/index.blade.php` | 58 | `<form action="" method="get" id="LangFrom">` |
| `resources/views/backend/event/ticket/index.blade.php` | 261 | `<form class="deleteForm d-block"` |
| `resources/views/backend/faq/create.blade.php` | 12 | `<form id="ajaxForm" class="modal-form create" action="{{ route('admin.faq_management.store_faq') }}" method="post">` |
| `resources/views/backend/faq/edit.blade.php` | 12 | `<form id="ajaxEditForm" class="modal-form" action="{{ route('admin.faq_management.update_faq') }}" method="post">` |
| `resources/views/backend/faq/index.blade.php` | 82 | `<form class="deleteForm d-inline-block" action="{{ route('admin.faq_management.delete_faq', ['id' => $faq->id]) }}" method="post">` |
| `resources/views/backend/footer/content.blade.php` | 48 | `<form id="ajaxForm"` |
| `resources/views/backend/footer/quick-link/create.blade.php` | 12 | `<form id="ajaxForm" class="modal-form create" action="{{ route('admin.footer.create_quick_link') }}" method="post">` |
| `resources/views/backend/footer/quick-link/edit.blade.php` | 12 | `<form id="ajaxEditForm" class="modal-form" action="{{ route('admin.footer.update_quick_link') }}" method="post">` |
| `resources/views/backend/footer/quick-link/index.blade.php` | 80 | `<form class="deleteForm d-inline-block" action="{{ route('admin.footer.delete_quick_link', ['id' => $quickLink->id]) }}" method="post">` |
| `resources/views/backend/forget-password.blade.php` | 8 | `<form action="{{ route('admin.mail_for_forget_password') }}" method="POST" novalidate>` |
| `resources/views/backend/home-page/about-us-section.blade.php` | 48 | `<form id="aboutUsForm"` |
| `resources/views/backend/home-page/event-feature/create.blade.php` | 12 | `<form id="ajaxForm" class="modal-form create" action="{{ route('admin.home_page.store_event_feature', ['language' => request()->input('language')]) }}" method="post">` |
| `resources/views/backend/home-page/event-feature/edit.blade.php` | 12 | `<form id="ajaxEditForm" class="modal-form" action="{{ route('admin.home_page.update_event_feature') }}" method="post">` |
| `resources/views/backend/home-page/event-feature/index.blade.php` | 49 | `<form id="featureForm" action="{{ route('admin.home_page.update_event_feature_section') }}" method="POST" enctype="multipart/form-data">` |
| `resources/views/backend/home-page/event-feature/index.blade.php` | 163 | `<form class="deleteForm d-inline-block" action="{{ route('admin.home_page.delete_event_feature', ['id' => $feature->id]) }}" method="post">` |
| `resources/views/backend/home-page/feature-section/create.blade.php` | 12 | `<form id="ajaxForm" class="modal-form create" action="{{ route('admin.home_page.store_feature', ['language' => request()->input('language')]) }}" method="post">` |
| `resources/views/backend/home-page/feature-section/edit.blade.php` | 12 | `<form id="ajaxEditForm" class="modal-form" action="{{ route('admin.home_page.update_feature') }}" method="post">` |
| `resources/views/backend/home-page/feature-section/index.blade.php` | 45 | `<form id="featureForm" action="{{ route('admin.home_page.update_feature_section_image') }}" method="POST" enctype="multipart/form-data">` |
| `resources/views/backend/home-page/feature-section/index.blade.php` | 162 | `<form class="deleteForm d-inline-block" action="{{ route('admin.home_page.delete_feature', ['id' => $feature->id]) }}" method="post">` |
| `resources/views/backend/home-page/hero-section.blade.php` | 48 | `<form id="heroForm" action="{{ route('admin.home_page.update_hero_section', ['language' => request()->input('language')]) }}" method="POST" enctype="multipart/form-data">` |
| `resources/views/backend/home-page/how-work/create.blade.php` | 12 | `<form id="ajaxForm" class="modal-form create" action="{{ route('admin.home_page.store_how_work_item', ['language' => request()->input('language')]) }}" method="post">` |
| `resources/views/backend/home-page/how-work/edit.blade.php` | 12 | `<form id="ajaxEditForm" class="modal-form" action="{{ route('admin.home_page.update_how_work_item') }}" method="post">` |
| `resources/views/backend/home-page/how-work/index.blade.php` | 49 | `<form id="featureForm" action="{{ route('admin.home_page.update_how_work') }}" method="POST" enctype="multipart/form-data">` |
| `resources/views/backend/home-page/how-work/index.blade.php` | 163 | `<form class="deleteForm d-inline-block" action="{{ route('admin.home_page.delete_how_work_item', ['id' => $feature->id]) }}" method="post">` |
| `resources/views/backend/home-page/partner/create.blade.php` | 12 | `<form id="ajaxForm" class="modal-form create" action="{{ route('admin.home_page.store_partner') }}" method="post" enctype="multipart/form-data">` |
| `resources/views/backend/home-page/partner/edit.blade.php` | 12 | `<form id="ajaxEditForm" class="modal-form" action="{{ route('admin.home_page.update_partner') }}" method="post" enctype="multipart/form-data">` |
| `resources/views/backend/home-page/partner/index.blade.php` | 49 | `<form id="featureForm" action="{{ route('admin.home_page.update_partner_section') }}" method="POST"` |
| `resources/views/backend/home-page/partner/index.blade.php` | 156 | `<form class="deleteForm d-inline-block"` |
| `resources/views/backend/home-page/section-customization.blade.php` | 30 | `<form action="{{ route('admin.home_page.update_section_status') }}" method="POST">` |
| `resources/views/backend/home-page/section-titles.blade.php` | 33 | `<form action="{{ route('admin.home_page.update_section_title', ['language' => request()->input('language')]) }}" method="post">` |
| `resources/views/backend/home-page/testimonial-section/create.blade.php` | 13 | `<form id="ajaxForm" class="modal-form create"` |
| `resources/views/backend/home-page/testimonial-section/edit.blade.php` | 13 | `<form id="ajaxEditForm" class="modal-form" action="{{ route('admin.home_page.update_testimonial') }}"` |
| `resources/views/backend/home-page/testimonial-section/index.blade.php` | 49 | `<form id="featureForm" action="{{ route('admin.home_page.update_testimonial_section_image') }}"` |
| `resources/views/backend/home-page/testimonial-section/index.blade.php` | 190 | `<form class="deleteForm d-inline-block"` |
| `resources/views/backend/integrations/show.blade.php` | 30 | `<form class="t-card in-form" method="POST" action="{{ route('admin.integrations.update', $i['key']) }}" novalidate autocomplete="off">` |
| `resources/views/backend/integrations/show.blade.php` | 99 | `<form method="POST" action="{{ route('admin.integrations.test', $i['key']) }}">` |
| `resources/views/backend/integrations/show.blade.php` | 104 | `<form method="POST" action="{{ route('admin.integrations.generate', $i['key']) }}" class="in-gen">` |
| `resources/views/backend/journal/blog/create.blade.php` | 55 | `<form id="blogForm" action="{{ route('admin.blog_management.store_blog') }}" method="POST"` |
| `resources/views/backend/journal/blog/edit.blade.php` | 55 | `<form id="blogForm" action="{{ route('admin.blog_management.update_blog', ['id' => $blog->id]) }}"` |
| `resources/views/backend/journal/blog/index.blade.php` | 96 | `<form class="deleteForm d-inline-block" action="{{ route('admin.blog_management.delete_blog', ['id' => $blog->id]) }}" method="post">` |
| `resources/views/backend/journal/category/create.blade.php` | 12 | `<form id="ajaxForm" class="modal-form create" action="{{ route('admin.blog_management.store_category') }}" method="post">` |
| `resources/views/backend/journal/category/edit.blade.php` | 13 | `<form id="ajaxEditForm" class="modal-form" action="{{ route('admin.blog_management.update_category') }}"` |
| `resources/views/backend/journal/category/index.blade.php` | 96 | `<form class="deleteForm d-inline-block" action="{{ route('admin.blog_management.delete_category', ['id' => $category->id]) }}" method="post">` |
| `resources/views/backend/language/admin_keywords_edit.blade.php` | 38 | `<form id="languageKeywordForm"` |
| `resources/views/backend/language/create.blade.php` | 13 | `<form id="ajaxForm2" action="{{ route('admin.language_management.store_language') }}" method="POST">` |
| `resources/views/backend/language/edit-keyword.blade.php` | 48 | `<form id="languageKeywordForm"` |
| `resources/views/backend/language/edit.blade.php` | 13 | `<form id="ajaxEditForm" action="{{ route('admin.language_management.update_language') }}"` |
| `resources/views/backend/language/index.blade.php` | 68 | `<form class="d-inline-block"` |
| `resources/views/backend/language/index.blade.php` | 98 | `<form class="deleteForm d-block"` |
| `resources/views/backend/language/index.blade.php` | 136 | `<form id="ajaxForm" action="{{ route('admin.language_management.add_keyword') }}"` |
| `resources/views/backend/login.blade.php` | 7 | `<form action="{{ route('admin.auth') }}" method="POST" novalidate>` |
| `resources/views/backend/menu-builder.blade.php` | 96 | `<form id="menu-builder-form" class="form-horizontal">` |
| `resources/views/backend/partials/side-navbar.blade.php` | 47 | `<form method="POST" action="{{ route('admin.logout') }}">` |
| `resources/views/backend/partials/side-navbar.blade.php` | 57 | `<form class="tica-admin-sidebar-search" action="" onsubmit="return false" role="search">` |
| `resources/views/backend/partials/top-navbar.blade.php` | 72 | `<form method="POST" action="{{ route('admin.logout') }}">@csrf<button type="submit">{{ __('Log out') }}</button></form>` |
| `resources/views/backend/payment-gateways/offline-gateways/create.blade.php` | 13 | `<form id="ajaxForm" class="modal-form" action="{{ route('admin.payment_gateways.store_offline_gateway') }}"` |
| `resources/views/backend/payment-gateways/offline-gateways/edit.blade.php` | 13 | `<form id="ajaxEditForm" class="modal-form" action="{{ route('admin.payment_gateways.update_offline_gateway') }}"` |
| `resources/views/backend/payment-gateways/offline-gateways/index.blade.php` | 65 | `<form id="statusForm-{{ $offlineGateway->id }}" class="d-inline-block" action="{{ route('admin.payment_gateways.update_status', ['id' => $offlineGateway->id]) }}" method="post">` |
| `resources/views/backend/payment-gateways/offline-gateways/index.blade.php` | 85 | `<form class="deleteForm d-inline-block" action="{{ route('admin.payment_gateways.delete_offline_gateway', ['id' => $offlineGateway->id]) }}" method="post">` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 48 | `<form action="{{ route('admin.payment_gateways.update_paystack_info') }}" method="post">` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 115 | `<form action="{{ route('admin.payment_gateways.update_monnify_info') }}" method="post">` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 202 | `<form action="{{ route('admin.payment_gateways.update_flutterwave_info') }}" method="post">` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 274 | `<form action="{{ route('admin.payment_gateways.update_stripe_info') }}" method="post">` |
| `resources/views/backend/payout-accounts/index.blade.php` | 73 | `<form method="POST" action="{{ route('admin.payout_provider.update') }}">` |
| `resources/views/backend/payout-accounts/index.blade.php` | 81 | `<form method="POST" action="{{ route('admin.payout_provider.test') }}">` |
| `resources/views/backend/payout-accounts/index.blade.php` | 94 | `<form class="tpa-search" method="GET" action="{{ route('admin.payout_accounts.index') }}" role="search">` |
| `resources/views/backend/payout-accounts/index.blade.php` | 137 | `<form method="POST" action="{{ route('admin.payout_accounts.review', $account->public_id) }}">` |
| `resources/views/backend/payout-accounts/index.blade.php` | 144 | `<form method="POST" action="{{ route('admin.payout_accounts.reverify', $account->public_id) }}">` |
| `resources/views/backend/payout-accounts/index.blade.php` | 149 | `<form method="POST" action="{{ route('admin.payout_accounts.lock', $account->public_id) }}" data-confirm="Lock this account? No payouts will go to it.">` |
| `resources/views/backend/payout-accounts/index.blade.php` | 156 | `<form class="tpa-rej" id="rej-{{ $account->public_id }}" method="POST" action="{{ route('admin.payout_accounts.review', $account->public_id) }}">` |
| `resources/views/backend/popup/create.blade.php` | 46 | `<form id="ajaxForm" class="create"` |
| `resources/views/backend/popup/edit.blade.php` | 45 | `<form id="ajaxEditForm" action="{{ route('admin.announcement_popups.update_popup', ['id' => $popup->id]) }}" method="POST" enctype="multipart/form-data">` |
| `resources/views/backend/popup/index.blade.php` | 87 | `<form id="statusForm-{{ $popup->id }}" class="d-inline-block" action="{{ route('admin.announcement_popups.update_popup_status', ['id' => $popup->id]) }}" method="post">` |
| `resources/views/backend/popup/index.blade.php` | 105 | `<form class="deleteForm d-inline-block" action="{{ route('admin.announcement_popups.delete_popup', ['id' => $popup->id]) }}" method="post">` |
| `resources/views/backend/product/category/create.blade.php` | 12 | `<form id="modalForm" class="modal-form create" action="{{ route('admin.shop_management.store_category') }}" method="post" enctype="multipart/form-data">` |
| `resources/views/backend/product/category/edit.blade.php` | 12 | `<form id="ajaxEditForm" class="modal-form" action="{{ route('admin.shop_management.update_category') }}" method="post">` |
| `resources/views/backend/product/category/index.blade.php` | 97 | `<form id="featuredForm-{{ $category->id }}" class="d-inline-block" action="{{ route('admin.shop_management.update_category_feature', ['id'=>$category->id]) }}" method="post">` |
| `resources/views/backend/product/category/index.blade.php` | 117 | `<form class="deleteForm d-inline-block" action="{{ route('admin.shop_management.delete_category', ['id' => $category->id]) }}" method="post">` |
| `resources/views/backend/product/coupon/create.blade.php` | 12 | `<form id="modalForm" class="modal-form create" action="{{ route('admin.shop_management.store_coupon') }}" method="post" enctype="multipart/form-data">` |
| `resources/views/backend/product/coupon/edit.blade.php` | 13 | `<form id="ajaxEditForm" class="modal-form" action="{{ route('admin.shop_management.update_coupon') }}"` |
| `resources/views/backend/product/coupon/index.blade.php` | 130 | `<form class="deleteForm d-inline-block" action="{{ route('admin.shop_management.delete_coupon', ['id' => $item->id]) }}" method="post">` |
| `resources/views/backend/product/create.blade.php` | 62 | `<form action="{{ route('admin.shop_management.product.imgstore') }}" id="my-dropzone"` |
| `resources/views/backend/product/create.blade.php` | 71 | `<form id="eventForm" action="{{ route('admin.shop_management.product.store') }}" method="POST"` |
| `resources/views/backend/product/edit.blade.php` | 71 | `<form action="{{ route('admin.shop_management.product.imgstore') }}" id="my-dropzone"` |
| `resources/views/backend/product/edit.blade.php` | 80 | `<form id="eventForm" action="{{ route('admin.shop_management.product.update') }}" method="POST"` |
| `resources/views/backend/product/order/index.blade.php` | 83 | `<form class="float-right ml-3" action="{{ route('admin.product.order') }}" method="GET">` |
| `resources/views/backend/product/order/index.blade.php` | 88 | `<form id="searchByStatusForm" class="float-right d-flex flex-row align-items-center"` |
| `resources/views/backend/product/order/index.blade.php` | 179 | `<form id="paymentStatusForm-{{ $order->id }}" class="d-inline-block"` |
| `resources/views/backend/product/order/index.blade.php` | 233 | `<form id="orderStatusForm-{{ $order->id }}" class="d-inline-block"` |
| `resources/views/backend/product/order/index.blade.php` | 309 | `<form class="deleteForm d-block"` |
| `resources/views/backend/product/order/refund-modal.blade.php` | 6 | `<form method="POST" action="{{ route('admin.product_order.refunds.store') }}">` |
| `resources/views/backend/product/order/report.blade.php` | 41 | `<form action="{{ url()->full() }}" class="form-inline">` |
| `resources/views/backend/product/order/report.blade.php` | 108 | `<form method="POST" action="{{ route('admin.product_order.export') }}" class="form-inline justify-content-end">` |
| `resources/views/backend/product/settings.blade.php` | 39 | `<form id="eventForm" action="{{ route('admin.product.setting.update') }}" method="POST" enctype="multipart/form-data">` |
| `resources/views/backend/product/shop_setting/create.blade.php` | 13 | `<form id="modalForm" class="modal-form create"` |
| `resources/views/backend/product/shop_setting/edit.blade.php` | 12 | `<form id="ajaxEditForm" class="modal-form" action="{{ route('admin.shop_management.update_shipping') }}" method="post">` |
| `resources/views/backend/product/shop_setting/index.blade.php` | 103 | `<form class="deleteForm d-inline-block" action="{{ route('admin.shop_management.delete_shipping', ['id' => $item->id]) }}" method="post">` |
| `resources/views/backend/product/show.blade.php` | 107 | `<form id="statusForm-{{ $product->id }}" class="d-inline-block"` |
| `resources/views/backend/product/show.blade.php` | 127 | `<form id="featuredForm-{{ $product->id }}" class="d-inline-block"` |
| `resources/views/backend/product/show.blade.php` | 150 | `<form class="deleteForm d-block"` |
| `resources/views/backend/reset-password.blade.php` | 8 | `<form action="{{ route('admin.update_forgotten_password') }}" method="POST" novalidate>` |
| `resources/views/backend/support_ticket/index.blade.php` | 71 | `<form action="" class="float-lg-right float-none" method="GET">` |
| `resources/views/backend/support_ticket/index.blade.php` | 164 | `<form method="post" action="{{ route('admin.support_tickets.unassign', $item->id) }}">` |
| `resources/views/backend/support_ticket/index.blade.php` | 178 | `<form class="deleteForm d-block"` |
| `resources/views/backend/support_ticket/index.blade.php` | 218 | `<form action="{{ route('assign_stuff.supoort.ticket', $item->id) }}" method="post">` |
| `resources/views/backend/support_ticket/messages.blade.php` | 52 | `<form class="closeForm d-block" action="{{ route('admin.support_ticket.close', $ticket->id) }}"` |
| `resources/views/backend/support_ticket/messages.blade.php` | 196 | `<form action="{{ route('admin.support_ticket.reply', $ticket->id) }}" id="ajaxform" method="POST"` |
| `resources/views/backend/support_ticket/setting.blade.php` | 39 | `<form id="eventForm" action="{{ route('admin.support_ticket.update_setting') }}" method="POST" enctype="multipart/form-data">` |
| `resources/views/backend/tica/platform-settings.blade.php` | 37 | `<form class="ta-forms" method="POST" action="{{ route('admin.tica.platform.update') }}" id="ta-platform">` |
| `resources/views/backend/tica/promotions.blade.php` | 148 | `<form class="promotion-filters" method="get" action="{{ route('admin.advertise.tica_promotions') }}">` |
| `resources/views/backend/tica/promotions.blade.php` | 236 | `<form method="post" action="{{ route('admin.advertise.tica_promotions.campaign', $campaign) }}">` |
| `resources/views/backend/tica/promotions.blade.php` | 297 | `<form method="post" action="{{ route('admin.advertise.tica_promotions.plan.store') }}" class="plan-editor plan-editor--create">` |
| `resources/views/backend/tica/promotions.blade.php` | 324 | `<form method="post" action="{{ route('admin.advertise.tica_promotions.plan', $plan) }}" class="plan-editor">` |
| `resources/views/backend/tica/promotions.blade.php` | 399 | `<form method="post" action="{{ route('admin.advertise.tica_promotions.pixel', $pixel) }}">` |
| `resources/views/backend/tica/website.blade.php` | 43 | `<form method="POST" action="{{ route('admin.tica.website.update', 'branding') }}" enctype="multipart/form-data" class="ta-forms">` |
| `resources/views/backend/tica/website.blade.php` | 68 | `<form method="POST" action="{{ route('admin.tica.website.update', 'seo') }}" enctype="multipart/form-data" class="ta-forms">` |
| `resources/views/backend/tica/website.blade.php` | 96 | `<form method="POST" action="{{ route('admin.tica.website.update', 'home') }}" enctype="multipart/form-data" class="ta-forms">` |
| `resources/views/backend/tica/website.blade.php` | 175 | `<form method="POST" action="{{ route('admin.tica.website.update', 'menu') }}" class="ta-forms">` |
| `resources/views/backend/tica/website.blade.php` | 194 | `<form method="POST" action="{{ route('admin.tica.website.update', 'footer') }}" class="ta-forms">` |
| `resources/views/backend/tica/website.blade.php` | 221 | `<form method="POST" action="{{ route('admin.tica.website.update', 'contact') }}" class="ta-forms">` |
| `resources/views/backend/withdraw/create.blade.php` | 14 | `<form id="ajaxForm" class="modal-form create" action="{{ route('admin.withdraw_payment_method.store') }}"` |
| `resources/views/backend/withdraw/edit.blade.php` | 14 | `<form id="ajaxEditForm" class="modal-form create" action="{{ route('admin.withdraw_payment_method.update') }}"` |
| `resources/views/backend/withdraw/form/create-input.blade.php` | 6 | `<form id="ajaxForm" action="{{ route('admin.withdraw_payment_method.store_input') }}" method="post"` |
| `resources/views/backend/withdraw/form/created-inputs.blade.php` | 4 | `<form class="ui-state-default" action="{{ route('admin.withdraw_payment_method.options_delete') }}" method="post"` |
| `resources/views/backend/withdraw/form/created-inputs.blade.php` | 35 | `<form class="ui-state-default" action="{{ route('admin.withdraw_payment_method.options_delete') }}"` |
| `resources/views/backend/withdraw/form/created-inputs.blade.php` | 66 | `<form class="ui-state-default" action="{{ route('admin.withdraw_payment_method.options_delete') }}"` |
| `resources/views/backend/withdraw/form/created-inputs.blade.php` | 107 | `<form class="ui-state-default" action="{{ route('admin.withdraw_payment_method.options_delete') }}"` |
| `resources/views/backend/withdraw/form/created-inputs.blade.php` | 149 | `<form class="ui-state-default" action="{{ route('admin.withdraw_payment_method.options_delete') }}"` |
| `resources/views/backend/withdraw/form/created-inputs.blade.php` | 181 | `<form class="ui-state-default" action="{{ route('admin.withdraw_payment_method.options_delete') }}"` |
| `resources/views/backend/withdraw/form/created-inputs.blade.php` | 214 | `<form class="ui-state-default" action="{{ route('admin.withdraw_payment_method.options_delete') }}"` |
| `resources/views/backend/withdraw/form/form-edit.blade.php` | 42 | `<form id="ajaxForm" action="{{ route('admin.withdraw_payment_method.update_input') }}" method="post"` |
| `resources/views/backend/withdraw/form/index.blade.php` | 60 | `<form class="ui-state-default ui-state-disabled">` |
| `resources/views/backend/withdraw/form/index.blade.php` | 70 | `<form class="ui-state-default ui-state-disabled">` |
| `resources/views/backend/withdraw/form/index.blade.php` | 106 | `let fd = new FormData();` |
| `resources/views/backend/withdraw/history/index.blade.php` | 54 | `<form method="post" action="{{ route('admin.withdraw.disputes.review', $dispute) }}">` |
| `resources/views/backend/withdraw/history/index.blade.php` | 80 | `<form action="{{ route('admin.withdraw.withdraw_request') }}" method="GET" class="row align-items-end">` |
| `resources/views/backend/withdraw/history/index.blade.php` | 89 | `<form method="POST" action="{{ route('admin.withdraw_exports.store') }}" class="form-inline mr-2 mb-2">` |
| `resources/views/backend/withdraw/history/view.blade.php` | 62 | `<form method="post" action="{{ route('admin.withdraw.submit_paystack', ['id' => $item->id]) }}">` |
| `resources/views/backend/withdraw/history/view.blade.php` | 81 | `<form method="post" action="{{ route('admin.witdraw.approve_withdraw', ['id' => $item->id]) }}">` |
| `resources/views/backend/withdraw/history/view.blade.php` | 96 | `<form method="post" action="{{ route('admin.witdraw.decline_withdraw', ['id' => $item->id]) }}">` |
| `resources/views/backend/withdraw/history/view.blade.php` | 112 | `<form method="post" action="{{ route('admin.witdraw.hold_withdraw', ['id' => $item->id]) }}">` |
| `resources/views/backend/withdraw/index.blade.php` | 104 | `<form class="deleteForm d-inline-block"` |
| `resources/views/components/shell/organizer.blade.php` | 141 | `<form class="tsh-foot" method="POST" action="{{ route('organizer.logout') }}">` |
| `resources/views/components/shell/organizer.blade.php` | 154 | `<form method="POST" action="{{ route('organizer.impersonation.return') }}">@csrf<button type="submit">{{ __('Return to admin') }}</button></form>` |
| `resources/views/components/shell/organizer.blade.php` | 188 | `<form method="POST" action="{{ route('organizer.logout') }}">@csrf<button type="submit">Log out</button></form>` |
| `resources/views/components/shell/organizer.blade.php` | 213 | `fetch('/tica-api/organizer/notifications/read', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })` |
| `resources/views/frontend/check-out.blade.php` | 75 | `<form action="{{ route('ticket.booking', $event->id) }}" method="POST" enctype="multipart/form-data"` |
| `resources/views/frontend/customer/dashboard/booking/details.blade.php` | 108 | `<form method="post" action="{{ route('customer.booking.resend-tickets', $booking->id) }}" class="mt-2">` |
| `resources/views/frontend/customer/dashboard/booking/details.blade.php` | 355 | `<form method="post" action="{{ route('customer.booking.ticket.transfer.cancel', $ticketUnit->pendingTransfer->id) }}" class="mt-2">` |
| `resources/views/frontend/customer/dashboard/booking/details.blade.php` | 363 | `<form method="post" action="{{ route('customer.booking.ticket.transfer', $ticketUnit->id) }}" class="mt-2">` |
| `resources/views/frontend/customer/dashboard/booking/details.blade.php` | 373 | `<form method="post" action="{{ route('customer.booking.ticket.reissue', $ticketUnit->id) }}" class="mt-2">` |
| `resources/views/frontend/customer/dashboard/support_ticket/messages.blade.php` | 174 | `<form action="{{ route('customer-reply', $ticket->id) }}" method="POST"` |
| `resources/views/frontend/customer/partials/sidebar.blade.php` | 31 | `<form method="POST" action="{{ route('customer.logout') }}">` |
| `resources/views/frontend/event/event-details.blade.php` | 206 | `<form method="POST" class="wishlist-action-form" action="{{ $checkWishList == false ? route('addto.wishlist', $content->id) : route('remove.wishlist', $content->id) }}">` |
| `resources/views/frontend/event/event-details.blade.php` | 358 | `<form action="{{ route('check-out2') }}" method="post"` |
| `resources/views/frontend/event/event-details.blade.php` | 982 | `<form method="POST" action="{{ route('event.waitlist.store', $content->id) }}">` |
| `resources/views/frontend/event/event-details.blade.php` | 1313 | `<form method="POST" class="wishlist-action-form" action="{{ $checkWishList == false ? route('addto.wishlist', $event->id) : route('remove.wishlist', $event->id) }}">` |
| `resources/views/frontend/event/guest-rsvp.blade.php` | 32 | `<form method="POST" action="{{ url()->full() }}">` |
| `resources/views/frontend/event/registration-allocation.blade.php` | 38 | `<form method="POST" action="{{ request()->fullUrl() }}">` |
| `resources/views/frontend/event/registration.blade.php` | 58 | `<form method="POST" action="{{ $registrationAction }}" enctype="multipart/form-data" data-registration-form novalidate>` |
| `resources/views/frontend/event/ticket-transfer-invitation.blade.php` | 53 | `<form method="post" action="{{ route('customer.logout') }}">` |
| `resources/views/frontend/event/ticket-transfer-invitation.blade.php` | 59 | `<form method="post" action="{{ $acceptUrl }}">` |
| `resources/views/frontend/event/waitlist-unsubscribe.blade.php` | 18 | `<form method="POST" action="{{ request()->fullUrl() }}">` |
| `resources/views/frontend/home/index-v1.blade.php` | 69 | `<form id="event-search" class="city-pulse-search" name="event-search" action="{{ route('events') }}" method="get">` |
| `resources/views/frontend/home/index-v1.blade.php` | 617 | `<form method="POST" class="wishlist-action-form"` |
| `resources/views/frontend/journal/side-bar.blade.php` | 6 | `<form action="{{ route('blogs') }}" method="GET">` |
| `resources/views/frontend/journal/side-bar.blade.php` | 50 | `<form class="d-none" action="{{ route('blogs') }}" method="GET">` |
| `resources/views/frontend/layout.blade.php` | 129 | `window.fetch(@json(route('analytics.events.store')), {` |
| `resources/views/frontend/layout.blade.php` | 151 | `var data = new FormData(searchForm);` |
| `resources/views/frontend/organizer/details.blade.php` | 302 | `<form method="POST" class="wishlist-action-form"` |
| `resources/views/frontend/organizer/details.blade.php` | 544 | `<form method="POST" class="wishlist-action-form"` |
| `resources/views/frontend/organizer/details.blade.php` | 728 | `<form action="{{ route('organizer.contact.send_mail') }}" method="POST" id="vendorContactForm">` |
| `resources/views/frontend/partials/popups.blade.php` | 29 | `<form class="subscriptionForm" action="{{ route('store_subscriber') }}" method="POST">` |
| `resources/views/frontend/partials/popups.blade.php` | 68 | `<form class="subscriptionForm" action="{{ route('store_subscriber') }}" method="POST">` |
| `resources/views/frontend/payment/order_razorpay.blade.php` | 3 | `<form name="razorpayform" action="{{ $notifyURL }}" method="POST">` |
| `resources/views/frontend/payment/razorpay.blade.php` | 3 | `<form name="razorpayform" action="{{ $notifyURL }}" method="POST">` |
| `resources/views/frontend/payment/recovery.blade.php` | 165 | `<form method="POST" action="{{ $verifyUrl }}">` |
| `resources/views/frontend/shop/checkout.blade.php` | 32 | `<form class="form" action="{{ route('shop.buy', ['type' => 'guest']) }}" method="POST"` |
| `resources/views/frontend/shop/details.blade.php` | 111 | `<form action="javascript:void(0)" method="post">` |
| `resources/views/frontend/shop/details.blade.php` | 206 | `<form class="mt-5" action="{{ route('product.review.submit') }}" method="POST">@csrf` |
| `resources/views/frontend/shop/index.blade.php` | 55 | `<form action="">` |
| `resources/views/frontend/shop/index.blade.php` | 64 | `<form action="{{ route('shop') }}" id="catForm">` |
| `resources/views/frontend/shop/index.blade.php` | 96 | `<form action="{{ route('shop') }}" id="shortForm">` |
| `resources/views/frontend/shop/index.blade.php` | 203 | `<form id="filtersForm" class="d-none" action="{{ route('shop') }}" method="GET">` |
| `resources/views/organizer/change-password.blade.php` | 35 | `<form id="ajaxEditForm" action="{{ route('organizer.update_password') }}" method="post">` |
| `resources/views/organizer/check-in-ledger-exports.blade.php` | 17 | `<form method="POST" action="" id="check-in-ledger-form" class="row align-items-end">` |
| `resources/views/organizer/data-exports/index.blade.php` | 19 | `<form method="POST" action="{{ route('organizer.data_export_schedules.store') }}" class="row align-items-end">` |
| `resources/views/organizer/data-exports/index.blade.php` | 29 | `<tr><td>{{ ucfirst($schedule->frequency) }} · {{ strtoupper($schedule->format) }}</td><td>{{ trans_choice(':count day\|:count days', $schedule->date_window_days, ['count' => $schedule->date_window_days]) }}</td><td>{{...` |
| `resources/views/organizer/edit-profile.blade.php` | 40 | `<form id="eventForm" action="{{ route('organizer.update_profile') }}" method="POST"` |
| `resources/views/organizer/event/booking/details.blade.php` | 77 | `<form method="post" action="{{ route('organizer.event_booking.resend-tickets', $booking->id) }}" class="float-right mr-2">` |
| `resources/views/organizer/event/booking/details.blade.php` | 540 | `<form method="post" action="{{ route('organizer.refund_disputes.store') }}" class="row">` |
| `resources/views/organizer/event/booking/details.blade.php` | 572 | `<form method="post" action="{{ route('organizer.refunds.store') }}" class="row">` |
| `resources/views/organizer/event/booking/details.blade.php` | 647 | `<form method="post" action="{{ route('organizer.event_booking.ticket.reissue', $ticketUnit->id) }}" class="mt-2">` |
| `resources/views/organizer/event/booking/details.blade.php` | 655 | `<form method="post" action="{{ route('organizer.event_booking.ticket.revoke', $ticketUnit->id) }}" class="mt-2">` |
| `resources/views/organizer/event/booking/index.blade.php` | 33 | `<form class="d-flex" action="{{ route('organizer.event_bookings.export') }}" method="POST">` |
| `resources/views/organizer/event/booking/index.blade.php` | 46 | `<form class="ml-3" action="{{ route('organizer.event.booking') }}" method="GET">` |
| `resources/views/organizer/event/booking/index.blade.php` | 52 | `<form class="ml-3" action="{{ route('organizer.event.booking') }}" method="GET">` |
| `resources/views/organizer/event/booking/index.blade.php` | 58 | `<form id="searchByStatusForm" class="d-flex flex-row align-items-center"` |
| `resources/views/organizer/event/booking/index.blade.php` | 222 | `<form class="deleteForm d-block"` |
| `resources/views/organizer/event/booking/report.blade.php` | 29 | `<form method="POST" action="{{ route('organizer.event_bookings.export') }}" class="tica-export-form">` |
| `resources/views/organizer/event/booking/report.blade.php` | 46 | `<form action="{{ url()->full() }}" method="GET" class="tica-report-filters">` |
| `resources/views/organizer/event/create.blade.php` | 68 | `<form action="{{ route('organizer.event.imagesstore') }}" id="my-dropzone" enctype="multipart/formdata"` |
| `resources/views/organizer/event/create.blade.php` | 80 | `<form id="eventForm" action="{{ route('organizer.event_management.store_event') }}" method="POST"` |
| `resources/views/organizer/event/edit.blade.php` | 79 | `<form action="{{ route('organizer.event.imagesstore') }}" id="my-dropzone" enctype="multipart/formdata"` |
| `resources/views/organizer/event/edit.blade.php` | 93 | `<form id="eventForm" action="{{ route('organizer.event.update') }}" method="POST"` |
| `resources/views/organizer/event/guest-list/index.blade.php` | 39 | `<form class="gl-event" method="GET" action="{{ route('organizer.event.guest-list.index') }}" data-gl-event>` |
| `resources/views/organizer/event/guest-list/index.blade.php` | 117 | `<form method="POST" action="{{ route('organizer.event.guest-list.check-in', $invitation) }}">@csrf<button class="t-btn t-btn--primary t-btn--sm" type="submit">{{ __('Check in') }}</button></form>` |
| `resources/views/organizer/event/guest-list/index.blade.php` | 120 | `<form method="POST" action="{{ route('organizer.event.guest-list.cancel', $invitation) }}" data-gl-confirm="{{ __('Cancel invite?') }}">@csrf<button class="gl-link gl-link--danger" type="submit">{{ __('Cancel') }}</bu...` |
| `resources/views/organizer/event/guest-list/index.blade.php` | 161 | `<form method="POST" action="{{ route('organizer.event.guest-list.store') }}" class="gl-dlg__form">` |
| `resources/views/organizer/event/guest-list/index.blade.php` | 178 | `<form method="POST" enctype="multipart/form-data" action="{{ route('organizer.event.guest-list.import') }}" class="gl-dlg__form">` |
| `resources/views/organizer/event/guest-list/index.blade.php` | 198 | `<form method="POST" action="{{ route('organizer.event.guest-list.export') }}" class="gl-dlg__form">` |
| `resources/views/organizer/event/index.blade.php` | 80 | `<form action="" method="get">` |
| `resources/views/organizer/event/index.blade.php` | 134 | `<form id="statusForm-{{ $event->id }}" class="d-inline-block"` |
| `resources/views/organizer/event/index.blade.php` | 167 | `<form id="featuredForm-{{ $event->id }}"` |
| `resources/views/organizer/event/index.blade.php` | 211 | `<form class="deleteForm d-block"` |
| `resources/views/organizer/event/index.blade.php` | 234 | `<form method="post" action="{{ route('organizer.event_cancellations.store', $event->id) }}">` |
| `resources/views/organizer/event/registration/index.blade.php` | 12 | `<form method="GET" action="{{ route('organizer.event.registrations.index') }}" class="form-inline">` |
| `resources/views/organizer/event/registration/index.blade.php` | 53 | `<form method="POST" action="{{ route('organizer.event.registrations.form.save') }}">` |
| `resources/views/organizer/event/registration/index.blade.php` | 85 | `<form class="mt-3" method="POST" action="{{ route('organizer.event.registrations.form.publish', $form) }}">` |
| `resources/views/organizer/event/registration/index.blade.php` | 99 | `<form method="POST" action="{{ route('organizer.event.registrations.templates.save', $form) }}">` |
| `resources/views/organizer/event/registration/index.blade.php` | 115 | `<form class="d-flex align-items-center justify-content-between border rounded p-2 mb-2" method="POST"` |
| `resources/views/organizer/event/registration/index.blade.php` | 143 | `<form id="question-order-form" method="POST" action="{{ route('organizer.event.registrations.questions.reorder', $form) }}">` |
| `resources/views/organizer/event/registration/index.blade.php` | 184 | `<form method="POST" action="{{ route('organizer.event.registrations.questions.move', $question) }}">` |
| `resources/views/organizer/event/registration/index.blade.php` | 188 | `<form method="POST" action="{{ route('organizer.event.registrations.questions.move', $question) }}">` |
| `resources/views/organizer/event/registration/index.blade.php` | 193 | `<form method="POST" action="{{ route('organizer.event.registrations.questions.delete', $question) }}" onsubmit="return confirm('{{ __('Remove this question?') }}')">` |
| `resources/views/organizer/event/registration/index.blade.php` | 216 | `<form method="POST" action="{{ $editingQuestion ? route('organizer.event.registrations.questions.update', $editingQuestion) : route('organizer.event.registrations.questions.store', $form) }}">` |
| `resources/views/organizer/event/registration/index.blade.php` | 325 | `<form class="border rounded p-3 mb-4" method="POST" action="{{ route('organizer.event.registrations.sessions.save', $form) }}">` |
| `resources/views/organizer/event/registration/index.blade.php` | 350 | `<td>@if($session->status === 'published')<form method="POST" action="{{ route('organizer.event.registrations.sessions.cancel', $session) }}" onsubmit="return confirm('{{ __('Cancel this session? Sessions with active s...` |
| `resources/views/organizer/event/registration/index.blade.php` | 364 | `<form method="POST" action="{{ route('organizer.event.registrations.badges.settings', $form) }}">` |
| `resources/views/organizer/event/registration/index.blade.php` | 425 | `<form method="POST" action="{{ route('organizer.event.registrations.embed.settings', $form) }}">` |
| `resources/views/organizer/event/registration/index.blade.php` | 461 | `<form class="border rounded p-3 mb-4" method="POST" action="{{ route('organizer.event.registrations.allocations.store', $form) }}">` |
| `resources/views/organizer/event/registration/index.blade.php` | 489 | `<form method="POST" action="{{ route('organizer.event.registrations.allocations.cancel', $allocation) }}" onsubmit="return confirm('{{ __('Cancel this allocation and invalidate outstanding attendee links?') }}')">@csr...` |
| `resources/views/organizer/event/registration/index.blade.php` | 513 | `<form method="POST" enctype="multipart/form-data" action="{{ route('organizer.event.registrations.import') }}">` |
| `resources/views/organizer/event/registration/index.blade.php` | 584 | `<form class="border rounded p-3 mb-4" method="POST" action="{{ route('organizer.event.registrations.export') }}">` |
| `resources/views/organizer/event/registration/index.blade.php` | 631 | `<form class="mb-2" method="POST" action="{{ route('organizer.event.registrations.submissions.decide', $submission) }}">` |
| `resources/views/organizer/event/registration/index.blade.php` | 635 | `<form method="POST" action="{{ route('organizer.event.registrations.submissions.decide', $submission) }}">` |
| `resources/views/organizer/event/registration/index.blade.php` | 643 | `<form method="POST" action="{{ route('organizer.event.registrations.invoices.issue', $submission) }}">` |
| `resources/views/organizer/event/registration/index.blade.php` | 654 | `<form method="POST" action="{{ route('organizer.event.registrations.invoices.paid', $submission->invoice) }}">` |
| `resources/views/organizer/event/ticket/create.blade.php` | 76 | `<form id="eventForm" action="{{ route('organizer.ticket_management.store_ticket') }}" method="POST"` |
| `resources/views/organizer/event/ticket/edit.blade.php` | 47 | `<form id="eventForm" action="{{ route('organizer.ticket_management.update_ticket') }}" method="POST"` |
| `resources/views/organizer/event/ticket/index.blade.php` | 28 | `<form action="" method="get" id="LangFrom">` |
| `resources/views/organizer/event/ticket/index.blade.php` | 231 | `<form class="deleteForm d-block"` |
| `resources/views/organizer/finance/index.blade.php` | 18 | `<form method="get" class="lg-range">` |
| `resources/views/organizer/forms/index.blade.php` | 28 | `<form method="POST" action="{{ route('organizer.forms.store') }}">` |
| `resources/views/organizer/forms/index.blade.php` | 81 | `<form method="POST" action="{{ route('organizer.forms.duplicate', $form) }}">@csrf<button class="t-btn t-btn--ghost t-btn--sm" type="submit">{{ __('Duplicate') }}</button></form>` |
| `resources/views/organizer/forms/index.blade.php` | 104 | `<form method="POST" action="{{ route('organizer.forms.adopt', $form) }}">@csrf<button class="t-btn t-btn--ghost t-btn--sm" type="submit">{{ __('Save to my forms') }}</button></form>` |
| `resources/views/organizer/forms/show.blade.php` | 32 | `<form method="POST" action="{{ route('organizer.forms.duplicate', $form) }}">@csrf<button class="t-btn t-btn--secondary t-btn--sm" type="submit">{{ __('Duplicate') }}</button></form>` |
| `resources/views/organizer/forms/show.blade.php` | 33 | `<form method="POST" action="{{ route('organizer.forms.delete', $form) }}" data-rf-confirm="{{ __('Delete form?') }}">@csrf<button class="t-btn t-btn--ghost t-btn--sm rf-danger" type="submit">{{ __('Delete form') }}</b...` |
| `resources/views/organizer/forms/show.blade.php` | 66 | `<form method="POST" action="{{ route('organizer.event.registrations.questions.store', $form) }}" class="rf-chip-form">` |
| `resources/views/organizer/forms/show.blade.php` | 112 | `<form method="POST" action="{{ route('organizer.forms.stop', $copy) }}" data-rf-confirm="{{ __('Stop? Back to tickets') }}">@csrf<button class="t-btn t-btn--ghost t-btn--sm rf-danger" type="submit">{{ __('Stop') }}</b...` |
| `resources/views/organizer/forms/show.blade.php` | 117 | `<form method="POST" action="{{ route('organizer.forms.push', $form) }}" class="rf-push">` |
| `resources/views/organizer/forms/show.blade.php` | 130 | `<form method="POST" action="{{ route('organizer.forms.events', $form) }}">` |
| `resources/views/organizer/forms/show.blade.php` | 160 | `<form method="POST" action="{{ route('organizer.forms.settings', $form) }}" data-rf-live>` |
| `resources/views/organizer/forms/show.blade.php` | 183 | `<form method="POST" class="rf-dlg__form" data-rf-edit-form novalidate>` |
| `resources/views/organizer/forms/_motion.blade.php` | 30 | `return Promise.resolve().then(function () { return fetch(url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': to...` |
| `resources/views/organizer/forms/_motion.blade.php` | 101 | `var fd = new FormData(edit); if (!edit.is_required.checked) fd.set('is_required', '0');` |
| `resources/views/organizer/forms/_motion.blade.php` | 115 | `post(q(row).delete, new FormData()).then(function (d) { render(d); toast(d.message); }, function (er) { row.classList.remove('is-leaving'); busy(rm, false); toast(errorText(er), true); });` |
| `resources/views/organizer/forms/_motion.blade.php` | 121 | `var btn = form.querySelector('[data-rf-chip]'), fd = new FormData(form);` |
| `resources/views/organizer/forms/_motion.blade.php` | 136 | `var fd = new FormData(); rows().forEach(function (r) { fd.append('ordered_questions[]', r.dataset.id); });` |
| `resources/views/organizer/forms/_motion.blade.php` | 195 | `post(form.action, new FormData(form)).then(function (d) {` |
| `resources/views/organizer/income.blade.php` | 33 | `<form action="{{ route('organizer.monthly_income') }}" id="year" method="get">` |
| `resources/views/organizer/kyc/lock-payout-account.blade.php` | 17 | `<form method="post" action="{{ URL::temporarySignedRoute('organizer.payout-account.lock', now()->addMinutes(30), ['account' => $payoutAccount->public_id]) }}">` |
| `resources/views/organizer/kyc/show.blade.php` | 33 | `<form method="post" action="{{ route('organizer.kyc.submit') }}" enctype="multipart/form-data" id="kv-form" novalidate>` |
| `resources/views/organizer/kyc/show.blade.php` | 154 | `<form method="post" action="{{ route('organizer.kyc.payout-account') }}" class="kv-bankform" id="kv-bankform" @unless ($errors->has('account_number') \|\| $errors->has('bank_code')) hidden @endunless novalidate>` |
| `resources/views/organizer/kyc/show.blade.php` | 231 | `fetch('/tica-api/organizer/bank/resolve', {` |
| `resources/views/organizer/pwa/index.blade.php` | 46 | `<form method="POST" action="{{ route('scanner.logout') }}">@csrf<button class="btn btn-sm btn-outline-secondary" type="submit">{{ __('Sign out') }}</button></form>` |
| `resources/views/organizer/pwa/partials/panels.blade.php` | 15 | `<form id="scanner-enroll-form" class="form-row d-none">` |
| `resources/views/organizer/pwa/partials/panels.blade.php` | 41 | `<form id="scanner-staff-form" class="form-row">` |
| `resources/views/organizer/pwa/partials/panels.blade.php` | 112 | `<form id="ticket-lookup-form" class="form-row">` |
| `resources/views/organizer/pwa/staff-login.blade.php` | 23 | `<form method="POST" action="{{ route('scanner.authentication') }}">` |
| `resources/views/organizer/support_ticket/create.blade.php` | 6 | `<form class="t-card lg-form" action="{{ route('organizer.support_ticket.store') }}" enctype="multipart/form-data" method="POST" novalidate>` |
| `resources/views/organizer/support_ticket/index.blade.php` | 15 | `<form action="" method="GET" class="t-toolbar lg-filters" role="search">` |
| `resources/views/organizer/support_ticket/index.blade.php` | 53 | `<form class="deleteForm" action="{{ route('organizer.support_tickets.delete', $item->id) }}" method="post">@csrf<button type="submit" class="deleteBtn is-danger">{{ __('Delete') }}</button></form>` |
| `resources/views/organizer/support_ticket/messages.blade.php` | 91 | `<form action="{{ route('organizer.support_ticket.reply', $ticket->id) }}" id="ajaxform" method="POST"` |
| `resources/views/organizer/transaction.blade.php` | 20 | `<form method="POST" action="{{ route('organizer.transaction_exports.store') }}" class="lg-export">@csrf` |
| `resources/views/organizer/transaction.blade.php` | 31 | `<form action="{{ route('organizer.transcation') }}" method="get" class="t-toolbar lg-filters">` |
| `resources/views/organizer/verify.blade.php` | 35 | `<form id="ajaxEditForm" action="{{ route('organizer.send.verify.link') }}" method="post">` |
| `resources/views/organizer/withdraw/create.blade.php` | 60 | `<form id="ajaxForm" action="{{ route('organizer.withdraw.send-request') }}" method="POST"` |
| `resources/views/organizer/withdraw/index.blade.php` | 57 | `<form action="{{ route('organizer.withdraw') }}" method="GET" class="row align-items-end">` |
| `resources/views/organizer/withdraw/index.blade.php` | 65 | `<form method="POST" action="{{ route('organizer.withdraw_exports.store') }}" class="form-inline mr-2 mb-2">` |
| `resources/views/organizer/withdraw/index.blade.php` | 157 | `<form class="deleteForm d-inline-block"` |
| `resources/views/organizer/withdraw/index.blade.php` | 221 | `<form method="post" action="{{ route('organizer.withdraw.disputes.store', $item) }}">` |
| `resources/views/payments/perfect-money.blade.php` | 10 | `<form action="{{ $data['url'] }}" method="{{ $data['method'] }}" id="paymentForm">` |
| `resources/views/tica-ui/pages/account.blade.php` | 71 | `<form class="acd-form" id="acd-profile" novalidate>` |
| `resources/views/tica-ui/pages/account.blade.php` | 83 | `<form class="acd-form" id="acd-pass" novalidate>` |
| `resources/views/tica-ui/pages/checkin.blade.php` | 15 | `<form class="ck-manual" id="ck-manual" novalidate><label for="ck-code">Or type the ticket code</label><div><input id="ck-code" type="text" autocomplete="off" placeholder="TICA-123456-1" autocapitalize="characters"><bu...` |
| `resources/views/tica-ui/pages/create.blade.php` | 5 | `<form id="create-form" class="panel ce" novalidate>` |
| `resources/views/tica-ui/pages/dashboard.blade.php` | 164 | `<form class="od-form" id="od-promo-f" novalidate>` |
| `resources/views/tica-ui/pages/dashboard.blade.php` | 179 | `<form class="pr-form pr-form--row" id="od-pixel-f" novalidate>` |
| `resources/views/tica-ui/pages/dashboard.blade.php` | 200 | `<form class="od-form od-invite" id="od-inv" novalidate><div class="inp"><label for="od-ie">Email address</label><input id="od-ie" type="email" placeholder="name@example.com" autocomplete="email" required></div><div cl...` |
| `resources/views/tica-ui/pages/dashboard.blade.php` | 216 | `<form class="od-form" id="od-prof" novalidate>` |
| `resources/views/tica-ui/pages/dashboard.blade.php` | 258 | `<form class="od-form" id="od-pass" novalidate>` |
| `resources/views/tica-ui/pages/dashboard.blade.php` | 275 | `<form class="od-form" id="od-inv" novalidate><div class="inp"><label for="od-ie">Email</label><input id="od-ie" type="email" placeholder="name@example.com" autocomplete="email"></div><div class="inp"><label for="od-ir...` |
| `resources/views/tica-ui/pages/dashboard.blade.php` | 279 | `<form class="pr-form" id="od-code-f" novalidate>` |
| `resources/views/tica-ui/pages/dashboard.blade.php` | 297 | `<form class="pr-form" id="od-link-f" novalidate>` |
| `resources/views/tica-ui/pages/dashboard.blade.php` | 315 | `<form class="pr-form" id="od-promo-pay-f" novalidate>` |
| `resources/views/tica-ui/pages/dashboard.blade.php` | 325 | `<form class="od-form" id="od-msg-f" novalidate>` |
| `resources/views/tica-ui/pages/dashboard.blade.php` | 334 | `<form class="od-form" id="od-comp-f" novalidate>` |
| `resources/views/tica-ui/pages/forgot.blade.php` | 10 | `<div class="au-main"><form class="au-card" data-auth="forgot" novalidate>` |
| `resources/views/tica-ui/pages/home.blade.php` | 25 | `<form class="search ms" id="home-search" role="search" aria-label="Find events">` |
| `resources/views/tica-ui/pages/login.blade.php` | 10 | `<div class="au-main"><form class="au-card" data-auth="login" novalidate>` |
| `resources/views/tica-ui/pages/pricing.blade.php` | 9 | `<form class="calc" id="calc" aria-label="Fee calculator">` |
| `resources/views/tica-ui/pages/signup.blade.php` | 10 | `<div class="au-main"><form class="au-card" data-auth="signup" novalidate>` |
| `resources/views/tica-ui/pages/tickets.blade.php` | 13 | `<form class="au-card mt-card" id="mt-form" novalidate>` |
| `resources/views/vendor/installer/environment-classic.blade.php` | 13 | `<form method="post" action="{{ route('LaravelInstaller::environmentSaveClassic') }}">` |
| `resources/views/vendor/installer/environment-wizard.blade.php` | 20 | `<form method="post" action="{{ route('LaravelInstaller::environmentSaveWizard') }}" class="tabs-wrap">` |
| `resources/views/vendor/installer/license.blade.php` | 17 | `<form method="post" action="{{ route('LaravelInstaller::licenseCheck') }}" class="tabs-wrap">` |
| `resources/js/pages/AccountSettingsPage.tsx` | 11 | `return <main className="min-h-[72vh] bg-background"><section className="border-b border-line bg-white"><div className="shell py-9 sm:py-11"><p className="text-xs font-extrabold uppercase tracking-[.12em] text-primary"...` |
| `resources/js/pages/AuthPage.tsx` | 12 | `<form className="mt-8 grid gap-5" action={props.action} method="POST"><input type="hidden" name="_token" value={props.csrf}/>{Object.entries(props.hiddenFields\|\|{}).map(([name,value])=><input key={name} type="hidden" ...` |
| `resources/js/pages/BookingsPage.tsx` | 9 | `{props.heldTickets.length>0&&<div><div className="flex items-center gap-3"><IconTile accent><Users size={20}/></IconTile><h2 className="display text-2xl sm:text-3xl">Tickets transferred to you</h2></div><div className...` |
| `resources/js/pages/ContentPage.tsx` | 34 | `<div className="rounded-[24px] border border-line bg-white p-6 shadow-card sm:p-9"><h2 className="display text-3xl">Send a message</h2><p className="mt-3 text-soft">We usually reply within one working day.</p>{contact...` |
| `resources/js/pages/DirectoryPage.tsx` | 6 | `<section className="bg-navy text-white"><div className="shell py-16 sm:py-24"><p className="text-sm font-extrabold uppercase tracking-[.18em] text-orange">TICA</p><h1 className="display mt-4 max-w-5xl text-[clamp(42px...` |
| `resources/js/pages/DiscoverPage.tsx` | 14 | `<form action={props.action} method="GET" className="mb-8 grid gap-3 rounded-2xl bg-white p-3 shadow-card md:grid-cols-[1.7fr_repeat(3,1fr)]">` |
| `resources/js/pages/DiscoverPage.tsx` | 20 | `<div className="md:hidden"><Dialog title="Filter events" trigger={<Button className="w-full" variant="outline"><SlidersHorizontal size={17}/> Filter events</Button>}><form action={props.action} method="GET"><input typ...` |
| `resources/js/pages/HomePage.tsx` | 26 | `<form action={props.routes.discover} className="grid overflow-hidden rounded-[20px] bg-white p-2 shadow-float sm:grid-cols-2 lg:grid-cols-[1fr_1.1fr_1fr_1.25fr_auto]" role="search">` |
| `resources/js/pages/PrivacyPage.tsx` | 13 | `<form action={props.exportUrl} method="post" className="mt-6"><input type="hidden" name="_token" value={props.csrf}/><Button>Request my data <ArrowRight size={17}/></Button></form>` |
| `resources/js/pages/PrivacyPage.tsx` | 18 | `{props.openRequest?<div className="mt-6 rounded-2xl border border-orange/25 bg-peach p-5"><p className="text-sm font-extrabold text-navy">Current request: {props.openRequest.publicId}</p><p className="mt-1 text-sm cap...` |
| `resources/js/pages/SupportPage.tsx` | 5 | `return <main className="min-h-[72vh] bg-background"><section className="border-b border-line bg-white"><div className="shell py-9 sm:py-11"><p className="text-xs font-extrabold uppercase tracking-[.12em] text-primary"...` |

## Paystack and payment integration inventory

| File | Line | Relevant source |
|---|---:|---|
| `app/Console/Commands/DispatchLifecycleNotifications.php` | 99 | `'event_booking.paystack.recovery', now()->addHours(24), ['reference' => $intent->reference]` |
| `app/Console/Commands/MonitorPaymentIntents.php` | 9 | `use App\Services\ProductPaystackPaymentService;` |
| `app/Console/Commands/MonitorPaymentIntents.php` | 102 | `$outcome = app(ProductPaystackPaymentService::class)->verify($intent);` |
| `app/Console/Commands/ReconcilePaymentRefunds.php` | 10 | `use App\Services\PaystackGatewayClient;` |
| `app/Console/Commands/ReconcilePaymentRefunds.php` | 39 | `$query->where('gateway', 'paystack')` |
| `app/Console/Commands/ReconcilePaymentRefunds.php` | 52 | `if ($refund->gateway === 'paystack' && !$refund->gateway_refund_id) {` |
| `app/Console/Commands/ReconcilePaymentRefunds.php` | 53 | `$service->recoverUnknownPaystackSubmission($refund);` |
| `app/Console/Commands/ReconcilePaymentRefunds.php` | 58 | `'paystack' => app(PaystackGatewayClient::class),` |
| `app/Console/Commands/ReconcilePaymentRefunds.php` | 64 | `if ($refund->gateway === 'paystack') {` |
| `app/Console/Commands/ReconcilePaymentRefunds.php` | 102 | `$payload = app(PaystackGatewayClient::class)->fetchRefund($refund->gateway_refund_id);` |
| `app/Console/Commands/ReconcilePaystackPayments.php` | 5 | `use App\Services\PaystackReconciliationService;` |
| `app/Console/Commands/ReconcilePaystackPayments.php` | 10 | `class ReconcilePaystackPayments extends Command implements Isolatable` |
| `app/Console/Commands/ReconcilePaystackPayments.php` | 16 | `protected $signature = 'payments:reconcile-paystack {--date= : Date to reconcile in YYYY-MM-DD format}';` |
| `app/Console/Commands/ReconcilePaystackPayments.php` | 17 | `protected $description = 'Compare successful Paystack charges with local payment intents and bookings';` |
| `app/Console/Commands/ReconcilePaystackPayments.php` | 19 | `public function handle(PaystackReconciliationService $service): int` |
| `app/Console/Commands/ReconcilePaystackPayments.php` | 32 | `'Paystack %s: %d matched, %d mismatched (%s).',` |
| `app/Console/Commands/ReconcilePaystackPayouts.php` | 9 | `class ReconcilePaystackPayouts extends Command implements Isolatable` |
| `app/Console/Commands/ReconcilePaystackPayouts.php` | 15 | `protected $signature = 'payouts:reconcile-paystack` |
| `app/Console/Commands/ReconcilePaystackPayouts.php` | 18 | `protected $description = 'Verify unresolved Paystack organizer payout transfers';` |
| `app/Console/Commands/ReconcilePaystackPayouts.php` | 28 | `if (!(bool) config('payouts.paystack_transfers_enabled', false)) {` |
| `app/Console/Commands/ReconcilePaystackPayouts.php` | 29 | `$this->line('Paystack payout transfers are disabled; nothing to reconcile.');` |
| `app/Console/Commands/ReconcilePaystackSettlements.php` | 5 | `use App\Services\PaystackSettlementReconciliationService;` |
| `app/Console/Commands/ReconcilePaystackSettlements.php` | 10 | `class ReconcilePaystackSettlements extends Command implements Isolatable` |
| `app/Console/Commands/ReconcilePaystackSettlements.php` | 16 | `protected $signature = 'payments:reconcile-paystack-settlements` |
| `app/Console/Commands/ReconcilePaystackSettlements.php` | 19 | `protected $description = 'Reconcile Paystack bank settlements and their transactions with local payments';` |
| `app/Console/Commands/ReconcilePaystackSettlements.php` | 26 | `public function handle(PaystackSettlementReconciliationService $service): int` |
| `app/Console/Commands/ReconcilePaystackSettlements.php` | 46 | `$this->error('Paystack settlement reconciliation failed before the requested provider range completed.');` |
| `app/Console/Commands/ReconcilePaystackSettlements.php` | 52 | `'Reviewed %d Paystack settlements: %d mismatched, %d failed.',` |
| `app/Console/Kernel.php` | 61 | `$schedule->command('payments:reconcile-paystack')` |
| `app/Console/Kernel.php` | 67 | `$schedule->command('payments:reconcile-paystack-settlements')` |
| `app/Console/Kernel.php` | 70 | `$schedule->command('payouts:reconcile-paystack')` |
| `app/Contracts/PayoutProvider.php` | 6 | `* A bank payout provider (Paystack, Monnify). Every call runs on the server;` |
| `app/Contracts/PayoutProvider.php` | 14 | `/** Machine name stored on accounts and transfers, e.g. "paystack". */` |
| `app/Http/Controllers/BackEnd/AdminController.php` | 343 | `->whereIn('gateway', ['paystack', 'flutterwave'])` |
| `app/Http/Controllers/BackEnd/IntegrationController.php` | 159 | `if ($key !== 'paystack') {` |
| `app/Http/Controllers/BackEnd/IntegrationController.php` | 162 | `$def = IntegrationRegistry::get('paystack');` |
| `app/Http/Controllers/BackEnd/IntegrationController.php` | 164 | `$secret = trim((string) ($input['secret_key'] ?? Integrations::value('paystack', $field('secret_key'))));` |
| `app/Http/Controllers/BackEnd/IntegrationController.php` | 165 | `$public = trim((string) ($input['public_key'] ?? Integrations::value('paystack', $field('public_key'))));` |
| `app/Http/Controllers/BackEnd/IntegrationController.php` | 166 | `$mode = (string) ($input['mode'] ?? Integrations::value('paystack', $field('mode')) ?: (str_starts_with($secret, 'sk_live_') ? 'live' : 'test'));` |
| `app/Http/Controllers/BackEnd/OperationsEvidenceExportController.php` | 42 | `'gateway'=>['nullable','in:paystack,flutterwave'],` |
| `app/Http/Controllers/BackEnd/Organizer/EventBookingController.php` | 159 | `->whereIn('gateway', ['paystack', 'flutterwave'])` |
| `app/Http/Controllers/BackEnd/Organizer/OrganizerKycController.php` | 35 | `$banks = Cache::remember('paystack.ngn.banks', now()->addDay(), function () {` |
| `app/Http/Controllers/BackEnd/PaymentGateway/OnlineGatewayController.php` | 21 | `$gatewayInfo['paystack'] = OnlineGateway::where('keyword', 'paystack')->first();` |
| `app/Http/Controllers/BackEnd/PaymentGateway/OnlineGatewayController.php` | 75 | `public function updatePaystackInfo(Request $request)` |
| `app/Http/Controllers/BackEnd/PaymentGateway/OnlineGatewayController.php` | 77 | `$paystackInfo = $this->gatewayWithPreservedCredentials($request, 'paystack', ['paystack_key' => 'key']);` |
| `app/Http/Controllers/BackEnd/PaymentGateway/OnlineGatewayController.php` | 80 | `'paystack_status' => 'required\|in:0,1',` |
| `app/Http/Controllers/BackEnd/PaymentGateway/OnlineGatewayController.php` | 81 | `'paystack_key' => 'required\|string\|max:1000'` |
| `app/Http/Controllers/BackEnd/PaymentGateway/OnlineGatewayController.php` | 90 | `$information = json_decode((string) $paystackInfo->information, true) ?: [];` |
| `app/Http/Controllers/BackEnd/PaymentGateway/OnlineGatewayController.php` | 91 | `$information['key'] = $request->paystack_key;` |
| `app/Http/Controllers/BackEnd/PaymentGateway/OnlineGatewayController.php` | 93 | `$paystackInfo->update([` |
| `app/Http/Controllers/BackEnd/PaymentGateway/OnlineGatewayController.php` | 95 | `'status' => $request->paystack_status` |
| `app/Http/Controllers/BackEnd/PaymentGateway/OnlineGatewayController.php` | 98 | `Session::flash('success', 'Updated Paystack Informaion Successfully');` |
| `app/Http/Controllers/BackEnd/PaymentGateway/OnlineGatewayController.php` | 631 | `'instamojo', 'paystack', 'monnify', 'flutterwave', 'razorpay', 'mercadopago',` |
| `app/Http/Controllers/BackEnd/PaymentReconciliationController.php` | 15 | `->when(in_array($request->input('gateway'), ['paystack', 'flutterwave'], true), function ($query) use ($request) {` |
| `app/Http/Controllers/BackEnd/PayoutAccountController.php` | 60 | `'transfersEnabled' => (bool) config('payouts.paystack_transfers_enabled'),` |
| `app/Http/Controllers/BackEnd/PayoutAccountController.php` | 114 | `$validated = $request->validate(['provider' => ['required', Rule::in(['paystack', 'monnify'])]]);` |
| `app/Http/Controllers/BackEnd/PayoutAccountController.php` | 126 | `$validated = $request->validate(['provider' => ['required', Rule::in(['paystack', 'monnify'])]]);` |
| `app/Http/Controllers/BackEnd/ReconciliationExportController.php` | 94 | `$rules['gateway'] = ['nullable', 'in:paystack,flutterwave'];` |
| `app/Http/Controllers/BackEnd/ShopManagement/ProductOrderRefundController.php` | 33 | `->where('gateway', 'paystack')` |
| `app/Http/Controllers/BackEnd/ShopManagement/ProductOrderRefundController.php` | 43 | `return back()->with('success', 'Full product refund '.$refund->public_id.' was queued for Paystack submission. Financial records will change only after provider confirmation.');` |
| `app/Http/Controllers/BackEnd/TicaPlatformController.php` | 30 | `'paystack' => TicaCurrency::PAYSTACK,` |
| `app/Http/Controllers/BackEnd/TicaPromotionController.php` | 14 | `use App\Services\TicaPromotionPaystackService;` |
| `app/Http/Controllers/BackEnd/TicaPromotionController.php` | 169 | `public function reviewCampaign(Request $request, int $campaign, TicaPromotionService $promotions, TicaPromotionPaystackService $gatewayPayments)` |
| `app/Http/Controllers/BackEnd/TicaPromotionController.php` | 182 | `if (optional($record->paymentTransaction)->gateway === 'paystack') {` |
| `app/Http/Controllers/BackEnd/TicaPromotionController.php` | 184 | `return back()->with('success', __('Promotion refund submitted to Paystack. The campaign will update when the provider confirms it.'));` |
| `app/Http/Controllers/BackEnd/WebhookOperationsController.php` | 15 | `'gateway' => ['nullable', 'in:paystack,flutterwave'],` |
| `app/Http/Controllers/BackEnd/WithdrawController.php` | 234 | `public function submitPaystack($id)` |
| `app/Http/Controllers/FrontEnd/Event/BookingController.php` | 14 | `use App\Http\Controllers\FrontEnd\PaymentGateway\PaystackController;` |
| `app/Http/Controllers/FrontEnd/Event/BookingController.php` | 120 | `if (!\App\Support\TicaCurrency::isBase($eventCurrency) && !in_array($gatewaySelection, ['paystack', 'stripe'], true)) {` |
| `app/Http/Controllers/FrontEnd/Event/BookingController.php` | 121 | `Session::flash('error', 'This event is priced in ' . $eventCurrency . ' and can only be paid with Paystack or Stripe.');` |
| `app/Http/Controllers/FrontEnd/Event/BookingController.php` | 157 | `} else if ($gatewaySelection == 'paystack') {` |
| `app/Http/Controllers/FrontEnd/Event/BookingController.php` | 158 | `$paystack = new PaystackController();` |
| `app/Http/Controllers/FrontEnd/Event/BookingController.php` | 160 | `return $paystack->bookingProcess($request, $id);` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/MonnifyController.php` | 53 | `->with('currency_error', 'Monnify only takes payments in naira. Choose Paystack for this event.')` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 15 | `use App\Services\PaystackPaymentVerifier;` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 16 | `use App\Services\PaystackPaymentRecoveryService;` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 17 | `use App\Services\PaystackEventCheckoutService;` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 22 | `use App\Services\ProductPaystackPaymentService;` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 24 | `use App\Services\TicaPromotionPaystackService;` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 32 | `class PaystackController extends Controller` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 50 | `$serverSecret = trim((string) config('services.paystack.secret'));` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 57 | `$gateway = OnlineGateway::whereKeyword('paystack')->first();` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 58 | `$paystackData = $gateway ? json_decode($gateway->information, true) : null;` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 60 | `if (!is_array($paystackData) \|\| empty($paystackData['key'])) {` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 61 | `throw new \RuntimeException('Paystack gateway credentials are not configured.');` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 64 | `$this->api_key = $paystackData['key'];` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 65 | `$this->secret_key = $paystackData['secret'] ?? $paystackData['key'];` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 69 | `// STEP 1 — Initialize payment and redirect to Paystack` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 80 | `'gateway' => 'required\|in:paystack',` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 104 | `if (!in_array($chargeCurrency, \App\Support\TicaCurrency::PAYSTACK, true)) {` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 106 | `->with('currency_error', 'Invalid currency for Paystack payment.')` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 151 | `'paymentMethod' => 'Paystack',` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 163 | `$intent = app(PaystackEventCheckoutService::class)->initialize([` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 175 | `'Paystack did not return a final initialization response. No second charge was created; check this payment status before trying again.'` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 181 | `throw new \RuntimeException('Paystack returned an unsafe checkout URL.');` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 187 | `Log::error('Paystack checkout initialization failed.', [` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 201 | `// STEP 2 — Webhook (called directly by Paystack servers after payment)` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 209 | `'paystack-webhook-credentials',` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 210 | `'Paystack webhook gateway credentials are unavailable.', [` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 217 | `$signature = $request->header('x-paystack-signature');` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 266 | `$refund = app(TicaPromotionPaystackService::class)->acceptRefundWebhook(` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 280 | `if ($eventName === 'charge.failed') {` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 283 | `? PaymentIntent::query()->where('gateway', 'paystack')->where('reference', $reference)->first()` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 288 | `'last_error' => (string) ($event->data->gateway_response ?? 'Paystack reported an unsuccessful charge.'),` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 297 | `if ($eventName !== 'charge.success') {` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 310 | `->where('gateway', 'paystack')` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 315 | `$result = app(ProductPaystackPaymentService::class)->acceptWebhook(` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 320 | `Log::error('Product Paystack webhook processing failed.', [` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 330 | `$intent = PaymentIntent::where('gateway', 'paystack')->where('reference', $reference)->first();` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 340 | `$campaign = app(TicaPromotionPaystackService::class)->acceptWebhook(json_decode(json_encode($event->data), true));` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 343 | `Log::error('Promotion Paystack webhook processing failed.', [` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 351 | `$verificationErrors = PaystackPaymentVerifier::errors(` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 357 | `'paystack-webhook-verification:' . optional($intent)->id,` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 358 | `'Paystack webhook payment verification mismatch.', [` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 383 | `Log::error('Paystack webhook processing failed.', [` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 396 | `'provider' => 'Paystack',` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 405 | `// STEP 3 — notify() — Browser redirect back after Paystack payment page` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 414 | `$intent = PaymentIntent::where('gateway', 'paystack')->where('reference', $reference)->first();` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 416 | `Log::warning('Paystack browser callback used an unknown reference.', [` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 427 | `$outcome = app(PaystackPaymentRecoveryService::class)->attempt(` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 432 | `Log::error('Paystack browser callback verification unavailable.', [` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 440 | `'We could not reach Paystack just now. Your payment is not lost and no second charge was created.'` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 450 | `->where('gateway', 'paystack')` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 460 | `'providerName' => 'Paystack',` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 463 | `'event_booking.paystack.recovery.verify',` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 470 | `public function retryRecovery(Request $request, string $reference, PaystackPaymentRecoveryService $service)` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 473 | `->where('gateway', 'paystack')` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 483 | `Log::error('Paystack recovery verification unavailable.', [` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 490 | `->with('warning', 'We could not reach Paystack just now. No new charge was created; please try checking again.');` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 502 | `$lockName = 'paystack_ref_' . hash('sha256', (string) $reference);` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 515 | `$intent = PaymentIntent::where('gateway', 'paystack')->where('reference', $reference)->first();` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 517 | `->where('gateway', 'paystack')` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 534 | `throw new \LogicException('A durable Paystack payment intent is required before fulfillment.');` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 546 | `$intent = PaymentIntent::where('gateway', 'paystack')` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 557 | `$alreadyStored = Transaction::where('gateway', 'paystack')` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 579 | `app(EventSaleAccountingService::class)->apply($bookingInfo, 'paystack', $reference);` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 589 | `'gateway' => 'paystack',` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 598 | `$existingTxn = Transaction::where('gateway', 'paystack')` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 618 | `Log::error('Paystack payment processing failed.', [` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 623 | `$failedIntent = PaymentIntent::where('gateway', 'paystack')` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 665 | `'event_booking.paystack.recovery',` |
| `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | 681 | `return $scheme === 'https' && $host === 'checkout.paystack.com' ? $url : null;` |
| `app/Http/Controllers/FrontEnd/PromotionTrackingController.php` | 8 | `use App\Services\TicaPromotionPaystackService;` |
| `app/Http/Controllers/FrontEnd/PromotionTrackingController.php` | 14 | `public function paystackCallback(Request $request, TicaPromotionPaystackService $payments): RedirectResponse` |
| `app/Http/Controllers/FrontEnd/Shop/OrderController.php` | 14 | `use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\PaystackController;` |
| `app/Http/Controllers/FrontEnd/Shop/OrderController.php` | 162 | `} else if ($request['gateway'] == 'paystack') {` |
| `app/Http/Controllers/FrontEnd/Shop/OrderController.php` | 163 | `$paystack = new PaystackController();` |
| `app/Http/Controllers/FrontEnd/Shop/OrderController.php` | 165 | `return $paystack->enrolmentProcess($request);` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 7 | `use App\Services\ProductPaystackCheckoutService;` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 8 | `use App\Services\ProductPaystackPaymentService;` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 16 | `class PaystackController extends Controller` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 21 | `$intent = app(ProductPaystackCheckoutService::class)->start(` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 28 | `Log::warning('Product Paystack checkout initialization failed.', [` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 35 | `'Paystack could not start the payment. Your cart is unchanged; please try again.'` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 45 | `'Paystack did not return a final checkout response. Your stock is reserved and no second charge has been created; check this attempt here.'` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 49 | `public function notify(Request $request, ProductPaystackPaymentService $service)` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 53 | `->where('gateway', 'paystack')` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 63 | `Log::error('Product Paystack callback verification unavailable.', [` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 71 | `'Your payment is not lost. We could not confirm it with Paystack just now; check the same transaction again.'` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 80 | `abort_unless($intent->gateway === 'paystack', 404);` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 84 | `'providerName' => 'Paystack',` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 87 | `'product_order.paystack.recovery.verify',` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 96 | `public function retryRecovery(ProductPaymentIntent $intent, ProductPaystackPaymentService $service)` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 98 | `abort_unless($intent->gateway === 'paystack', 404);` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 102 | `Log::error('Product Paystack recovery verification unavailable.', [` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 109 | `'We could not reach Paystack just now. No new charge was created; please check again.'` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 122 | `'completed' => redirect()->route('product_order.complete', ['via' => 'paystack']),` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 123 | `'failed' => redirect()->route('shop.checkout')->with('error', 'Paystack did not complete this transaction. You can safely try again.'),` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 130 | `'Paystack has not confirmed this payment yet. No second charge has been created.'` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 138 | `'product_order.paystack.recovery',` |
| `app/Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php` | 153 | `\|\| ($host !== 'checkout.paystack.com' && !str_ends_with($host, '.paystack.com'))) {` |
| `app/Http/Controllers/FrontEnd/TicaApiController.php` | 206 | `if ($target && (!$local \|\| str_contains((string) parse_url($target, PHP_URL_PATH), 'event-booking-complete') \|\| preg_match('#/(paystack\|monnify\|flutterwave\|stripe)/recovery/#', (string) parse_url($target, PHP_URL_PATH...` |
| `app/Http/Controllers/FrontEnd/TicaOrganizerController.php` | 592 | `if ($paid && !in_array($validated['currency'], TicaCurrency::PAYSTACK, true)) {` |
| `app/Http/Controllers/FrontEnd/TicaWorkspaceController.php` | 37 | `use App\Services\PaystackGatewayClient;` |
| `app/Http/Controllers/FrontEnd/TicaWorkspaceController.php` | 43 | `use App\Services\TicaPromotionPaystackService;` |
| `app/Http/Controllers/FrontEnd/TicaWorkspaceController.php` | 715 | `public function checkoutPromotion(int $campaign, TicaPromotionPaystackService $payments): JsonResponse` |
| `app/Http/Controllers/FrontEnd/TicaWorkspaceController.php` | 721 | `&& strtolower((string) parse_url($url, PHP_URL_HOST)) === 'checkout.paystack.com', 502, 'The payment provider returned an unsafe checkout URL.');` |
| `app/Http/Middleware/TrackWebhookReceipt.php` | 43 | `'paystack' => 'x-paystack-signature',` |
| `app/Http/Middleware/VerifyCsrfToken.php` | 15 | `'paystack/webhook',` |
| `app/Jobs/SubmitPaystackPayout.php` | 14 | `class SubmitPaystackPayout implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique` |
| `app/Jobs/SubmitPaystackPayout.php` | 28 | `return 'paystack-payout:' . $this->transferId;` |
| `app/Models/PaymentIntent.php` | 65 | `'event_booking.paystack.recovery', now()->addHours(24), ['reference' => $intent->reference]` |
| `app/Services/DataExportService.php` | 343 | `if (!in_array($gateway, ['paystack', 'flutterwave'], true)) {` |
| `app/Services/DataExportService.php` | 428 | `if (!in_array($gateway, ['paystack', 'flutterwave'], true)) {` |
| `app/Services/EventCancellationCaseService.php` | 109 | `if ($intent->status !== 'completed' \|\| !in_array($intent->gateway, ['paystack', 'flutterwave'], true)) {` |
| `app/Services/EventCancellationCaseService.php` | 176 | `&& in_array($intent->gateway, ['paystack', 'flutterwave'], true)) {` |
| `app/Services/EventCancellationService.php` | 279 | `->whereIn('gateway', ['paystack', 'flutterwave'])` |
| `app/Services/EventCancellationService.php` | 447 | `if (in_array($intent->gateway, ['paystack', 'flutterwave'], true)` |
| `app/Services/EventCancellationService.php` | 499 | `if (!in_array($intent->gateway, ['paystack', 'flutterwave'], true) \|\| $this->isCheckedIn($booking)) {` |
| `app/Services/FinancialLedgerService.php` | 285 | `$transaction = $this->post('gateway-fee:paystack:' . $gatewayReference, 'gateway_fee', [` |
| `app/Services/GatewayLedgerBackfillService.php` | 160 | `(array) config('payments.production_ready_gateways', ['paystack', 'flutterwave'])` |
| `app/Services/OrganizerPayoutAccountService.php` | 11 | `use App\Services\Payouts\PaystackPayoutProvider;` |
| `app/Services/OrganizerPayoutAccountService.php` | 308 | `'provider' => 'paystack',` |
| `app/Services/OrganizerPayoutAccountService.php` | 322 | `$own = OrganizerPayoutAccount::where('organizer_id', $organizerId)->where('provider', 'paystack')` |
| `app/Services/OrganizerPayoutAccountService.php` | 416 | `/** Accepts a provider, a legacy Paystack client (tests), or nothing for the admin's choice. */` |
| `app/Services/OrganizerPayoutAccountService.php` | 422 | `if ($client instanceof PaystackGatewayClient) {` |
| `app/Services/OrganizerPayoutAccountService.php` | 423 | `return new PaystackPayoutProvider($client);` |
| `app/Services/PaymentGatewayPolicy.php` | 7 | `private const HARDENED_EVENT_GATEWAYS = ['paystack', 'flutterwave', 'monnify', 'stripe'];` |
| `app/Services/PaymentGatewayPolicy.php` | 10 | `public const TICA_GATEWAYS = ['paystack', 'monnify', 'flutterwave', 'stripe'];` |
| `app/Services/PaymentGatewayPolicy.php` | 46 | `* legacy shop integration as safe with an environment variable. Paystack` |
| `app/Services/PaymentGatewayPolicy.php` | 52 | `return ['paystack'];` |
| `app/Services/PaymentRefundDisputeService.php` | 61 | `if (!in_array($intent->gateway, ['paystack', 'flutterwave'], true) \|\| $intent->status !== 'completed') {` |
| `app/Services/PaymentRefundService.php` | 23 | `private const SUPPORTED_GATEWAYS = ['paystack', 'flutterwave'];` |
| `app/Services/PaymentRefundService.php` | 341 | `PaystackGatewayClient\|FlutterwaveGatewayClient\|null $client = null` |
| `app/Services/PaymentRefundService.php` | 391 | `$refund = PaymentRefund::where('gateway', 'paystack')` |
| `app/Services/PaymentRefundService.php` | 444 | `public function recoverUnknownPaystackSubmission(` |
| `app/Services/PaymentRefundService.php` | 446 | `?PaystackGatewayClient $client = null` |
| `app/Services/PaymentRefundService.php` | 449 | `if ($refund->gateway !== 'paystack'` |
| `app/Services/PaymentRefundService.php` | 458 | `return $this->markPaystackRecoveryAttention(` |
| `app/Services/PaymentRefundService.php` | 460 | `'The original Paystack transaction ID is unavailable for deterministic refund recovery.'` |
| `app/Services/PaymentRefundService.php` | 464 | `$payload = ($client ?: new PaystackGatewayClient())->listRefundsForTransaction($transactionId);` |
| `app/Services/PaymentRefundService.php` | 478 | `'last_error' => 'Paystack has not returned a matching refund yet; reconciliation will retry.',` |
| `app/Services/PaymentRefundService.php` | 480 | `$this->alertIfPaystackRecoveryStale($refund->fresh());` |
| `app/Services/PaymentRefundService.php` | 484 | `return $this->markPaystackRecoveryAttention(` |
| `app/Services/PaymentRefundService.php` | 486 | `'Multiple Paystack refunds matched the controlled transaction, amount and currency.'` |
| `app/Services/PaymentRefundService.php` | 502 | `if ($refund->gateway === 'paystack') {` |
| `app/Services/PaymentRefundService.php` | 521 | `'paystack-refund-mismatch:' . $refund->id,` |
| `app/Services/PaymentRefundService.php` | 522 | `'Paystack event refund status did not match the controlled refund.',` |
| `app/Services/PaymentRefundService.php` | 678 | `private function markPaystackRecoveryAttention(PaymentRefund $refund, string $message): PaymentRefund` |
| `app/Services/PaymentRefundService.php` | 683 | `'A Paystack event refund could not be recovered deterministically.',` |
| `app/Services/PaymentRefundService.php` | 708 | `private function alertIfPaystackRecoveryStale(PaymentRefund $refund): void` |
| `app/Services/PaymentRefundService.php` | 716 | `'A Paystack event refund remains indeterminate after provider reconciliation.',` |
| `app/Services/PaymentRefundService.php` | 724 | `PaystackGatewayClient\|FlutterwaveGatewayClient\|null $client` |
| `app/Services/PaymentRefundService.php` | 726 | `if ($refund->gateway === 'paystack') {` |
| `app/Services/PaymentRefundService.php` | 727 | `if ($client && !$client instanceof PaystackGatewayClient) {` |
| `app/Services/PaymentRefundService.php` | 728 | `throw new RuntimeException('The selected refund client does not match Paystack.');` |
| `app/Services/PaymentRefundService.php` | 731 | `return ($client ?: new PaystackGatewayClient())->createRefund(` |
| `app/Services/PayoutEligibilityService.php` | 31 | `if (!(bool) config('payouts.paystack_transfers_enabled', false)) {` |
| `app/Services/PayoutEligibilityService.php` | 236 | `&& $account->provider === 'paystack'` |
| `app/Services/Payouts/PayoutProviders.php` | 20 | `'paystack' => app(PaystackPayoutProvider::class),` |
| `app/Services/Payouts/PaystackPayoutProvider.php` | 6 | `use App\Services\PaystackGatewayClient;` |
| `app/Services/Payouts/PaystackPayoutProvider.php` | 8 | `class PaystackPayoutProvider implements PayoutProvider` |
| `app/Services/Payouts/PaystackPayoutProvider.php` | 10 | `public function __construct(private ?PaystackGatewayClient $client = null)` |
| `app/Services/Payouts/PaystackPayoutProvider.php` | 16 | `return 'paystack';` |
| `app/Services/Payouts/PaystackPayoutProvider.php` | 44 | `throw new \RuntimeException('Paystack did not return a transfer recipient code.');` |
| `app/Services/Payouts/PaystackPayoutProvider.php` | 80 | `private function client(): PaystackGatewayClient` |
| `app/Services/Payouts/PaystackPayoutProvider.php` | 82 | `return $this->client ??= new PaystackGatewayClient();` |
| `app/Services/PayoutTransferService.php` | 8 | `use App\Services\Payouts\PaystackPayoutProvider;` |
| `app/Services/PayoutTransferService.php` | 10 | `use App\Jobs\SubmitPaystackPayout;` |
| `app/Services/PayoutTransferService.php` | 59 | `if (!(bool) config('payouts.paystack_transfers_enabled', false)) {` |
| `app/Services/PayoutTransferService.php` | 60 | `throw new RuntimeException('Paystack payout transfers are disabled by configuration.');` |
| `app/Services/PayoutTransferService.php` | 88 | `: 'Only pending or held withdrawals can be submitted to Paystack.');` |
| `app/Services/PayoutTransferService.php` | 99 | `->whereIn('provider', ['paystack', 'monnify'])` |
| `app/Services/PayoutTransferService.php` | 114 | `'gateway' => (string) ($account->provider ?: 'paystack'),` |
| `app/Services/PayoutTransferService.php` | 127 | `'decision_reason' => 'Paystack transfer queued for processing.',` |
| `app/Services/PayoutTransferService.php` | 182 | `SubmitPaystackPayout::dispatch($transferId);` |
| `app/Services/PayoutTransferService.php` | 263 | `throw new RuntimeException('The Paystack payout balance is too low for this transfer. Add funds before retrying.');` |
| `app/Services/PayoutTransferService.php` | 287 | `if (!in_array($eventName, ['transfer.success', 'transfer.failed', 'transfer.reversed'], true)) {` |
| `app/Services/PayoutTransferService.php` | 297 | `->where('gateway', 'paystack')` |
| `app/Services/PayoutTransferService.php` | 391 | `/** The provider a transfer was created with. Tests may pass a Paystack client or provider. */` |
| `app/Services/PayoutTransferService.php` | 397 | `if ($client instanceof PaystackGatewayClient) {` |
| `app/Services/PayoutTransferService.php` | 398 | `return new PaystackPayoutProvider($client);` |
| `app/Services/PayoutTransferService.php` | 401 | `return app(PayoutProviders::class)->for($gateway ?: 'paystack');` |
| `app/Services/PayoutTransferService.php` | 427 | `'paystack-payout-mismatch:' . $transfer->id,` |
| `app/Services/PayoutTransferService.php` | 428 | `'Paystack payout response did not match the controlled transfer.',` |
| `app/Services/PayoutTransferService.php` | 464 | `'paystack-payout-financial-evidence:' . $transfer->id,` |
| `app/Services/PayoutTransferService.php` | 465 | `'Paystack payout could not reconcile its withdrawal financial evidence.',` |
| `app/Services/PayoutTransferService.php` | 547 | `'decision_reason' => 'Paystack transfer ' . $status . '; funds were returned to the available balance.',` |
| `app/Services/PayoutTransferService.php` | 604 | `'paystack-payout-unknown:' . $transferId,` |
| `app/Services/PayoutTransferService.php` | 605 | `'Paystack payout outcome is unknown and requires verification.', [` |
| `app/Services/PayoutTransferService.php` | 644 | `'paystack-payout-verification-exhausted:' . $transfer->id,` |
| `app/Services/PayoutTransferService.php` | 645 | `'Paystack payout verification attempts exhausted.',` |
| `app/Services/PayoutTransferService.php` | 652 | `Log::warning('Paystack payout verification failed.', [` |
| `app/Services/PayoutTransferService.php` | 675 | `'decision_reason' => 'Paystack transfer submission failed; finance review is required.',` |
| `app/Services/PaystackEventCheckoutService.php` | 11 | `class PaystackEventCheckoutService` |
| `app/Services/PaystackEventCheckoutService.php` | 13 | `public function initialize(array $checkout, ?PaystackGatewayClient $client = null): PaymentIntent` |
| `app/Services/PaystackEventCheckoutService.php` | 22 | `throw new RuntimeException('The Paystack event checkout data is invalid.');` |
| `app/Services/PaystackEventCheckoutService.php` | 27 | `'gateway' => 'paystack',` |
| `app/Services/PaystackEventCheckoutService.php` | 44 | `'gateway' => 'paystack',` |
| `app/Services/PaystackEventCheckoutService.php` | 50 | `$response = ($client ?: new PaystackGatewayClient())->initializeTransaction(` |
| `app/Services/PaystackEventCheckoutService.php` | 55 | `route('event_booking.paystack.notify'),` |
| `app/Services/PaystackEventCheckoutService.php` | 59 | `throw new RuntimeException('Paystack returned a different transaction reference.');` |
| `app/Services/PaystackEventCheckoutService.php` | 73 | `'paystack-event-init-unknown:' . $intent->id,` |
| `app/Services/PaystackEventCheckoutService.php` | 74 | `'Event Paystack initialization has an unknown outcome.',` |
| `app/Services/PaystackEventCheckoutService.php` | 83 | `'gateway' => 'paystack',` |
| `app/Services/PaystackGatewayClient.php` | 11 | `class PaystackGatewayClient` |
| `app/Services/PaystackGatewayClient.php` | 17 | `$secret = $secret ?: (string) config('services.paystack.secret');` |
| `app/Services/PaystackGatewayClient.php` | 23 | `$gateway = OnlineGateway::whereKeyword('paystack')->firstOrFail();` |
| `app/Services/PaystackGatewayClient.php` | 27 | `throw new RuntimeException('Paystack secret key is not configured.');` |
| `app/Services/PaystackGatewayClient.php` | 42 | `->post('https://api.paystack.co/transaction/initialize', [` |
| `app/Services/PaystackGatewayClient.php` | 54 | `(string) ($payload['message'] ?? 'Paystack initialization returned an indeterminate response.')` |
| `app/Services/PaystackGatewayClient.php` | 64 | `\|\| $authorizationHost !== 'checkout.paystack.com') {` |
| `app/Services/PaystackGatewayClient.php` | 65 | `throw new RuntimeException((string) ($payload['message'] ?? 'Paystack rejected the payment initialization.'));` |
| `app/Services/PaystackGatewayClient.php` | 76 | `->post('https://api.paystack.co/refund', [` |
| `app/Services/PaystackGatewayClient.php` | 87 | `(string) ($payload['message'] ?? 'Paystack refund submission returned an indeterminate response.')` |
| `app/Services/PaystackGatewayClient.php` | 91 | `throw new RuntimeException((string) ($payload['message'] ?? 'Paystack rejected the refund request.'));` |
| `app/Services/PaystackGatewayClient.php` | 102 | `->get('https://api.paystack.co/transaction/verify/' . rawurlencode($reference));` |
| `app/Services/PaystackGatewayClient.php` | 106 | `throw new RuntimeException((string) ($payload['message'] ?? 'Unable to verify the Paystack transaction.'));` |
| `app/Services/PaystackGatewayClient.php` | 117 | `->get('https://api.paystack.co/refund/' . rawurlencode($refundId));` |
| `app/Services/PaystackGatewayClient.php` | 121 | `throw new RuntimeException((string) ($payload['message'] ?? 'Unable to retrieve the Paystack refund.'));` |
| `app/Services/PaystackGatewayClient.php` | 134 | `throw new RuntimeException('A Paystack transaction ID is required to recover a refund.');` |
| `app/Services/PaystackGatewayClient.php` | 140 | `->get('https://api.paystack.co/refund', [` |
| `app/Services/PaystackGatewayClient.php` | 150 | `throw new RuntimeException((string) ($payload['message'] ?? 'Unable to list Paystack refunds.'));` |
| `app/Services/PaystackGatewayClient.php` | 165 | `->get('https://api.paystack.co/transaction', [` |
| `app/Services/PaystackGatewayClient.php` | 175 | `throw new RuntimeException((string) ($payload['message'] ?? 'Unable to retrieve Paystack transactions.'));` |
| `app/Services/PaystackGatewayClient.php` | 187 | `return $this->getPaginated('https://api.paystack.co/settlement', [` |
| `app/Services/PaystackGatewayClient.php` | 192 | `], 'Unable to retrieve Paystack settlements.');` |
| `app/Services/PaystackGatewayClient.php` | 201 | `'https://api.paystack.co/settlement/' . rawurlencode($settlementId) . '/transactions',` |
| `app/Services/PaystackGatewayClient.php` | 203 | `'Unable to retrieve Paystack settlement transactions.'` |
| `app/Services/PaystackGatewayClient.php` | 212 | `->get('https://api.paystack.co/bank', [` |
| `app/Services/PaystackGatewayClient.php` | 221 | `throw new RuntimeException((string) ($payload['message'] ?? 'Unable to retrieve Paystack banks.'));` |
| `app/Services/PaystackGatewayClient.php` | 232 | `->get('https://api.paystack.co/bank/resolve', [` |
| `app/Services/PaystackGatewayClient.php` | 254 | `->post('https://api.paystack.co/transferrecipient', [` |
| `app/Services/PaystackGatewayClient.php` | 264 | `throw new RuntimeException((string) ($payload['message'] ?? 'Paystack rejected the transfer recipient.'));` |
| `app/Services/PaystackGatewayClient.php` | 280 | `->post('https://api.paystack.co/transfer', [` |
| `app/Services/PaystackGatewayClient.php` | 292 | `(string) ($payload['message'] ?? 'Paystack transfer submission returned an indeterminate response.')` |
| `app/Services/PaystackGatewayClient.php` | 296 | `throw new RuntimeException((string) ($payload['message'] ?? 'Paystack rejected the transfer request.'));` |
| `app/Services/PaystackGatewayClient.php` | 305 | `->get('https://api.paystack.co/balance');` |
| `app/Services/PaystackGatewayClient.php` | 308 | `throw new RuntimeException((string) ($payload['message'] ?? 'Unable to retrieve the Paystack balance.'));` |
| `app/Services/PaystackGatewayClient.php` | 318 | `->get('https://api.paystack.co/transfer/verify/' . rawurlencode($reference));` |
| `app/Services/PaystackGatewayClient.php` | 322 | `throw new RuntimeException((string) ($payload['message'] ?? 'Unable to verify the Paystack transfer.'));` |
| `app/Services/PaystackPaymentRecoveryService.php` | 7 | `class PaystackPaymentRecoveryService` |
| `app/Services/PaystackPaymentRecoveryService.php` | 12 | `?PaystackGatewayClient $client = null` |
| `app/Services/PaystackPaymentRecoveryService.php` | 20 | `$gateway = ($client ?: new PaystackGatewayClient())->verifyTransaction($intent->reference);` |
| `app/Services/PaystackPaymentRecoveryService.php` | 26 | `$errors = PaystackPaymentVerifier::errors($data, $intent->payload ?: []);` |
| `app/Services/PaystackPaymentRecoveryService.php` | 31 | `'paystack-recovery-mismatch:' . $intent->id,` |
| `app/Services/PaystackPaymentRecoveryService.php` | 32 | `'Paystack recovery verification mismatch.', [` |
| `app/Services/PaystackPaymentRecoveryService.php` | 41 | `['gateway' => 'paystack', 'reference' => $intent->reference, 'errors' => $errors]` |
| `app/Services/PaystackPaymentRecoveryService.php` | 61 | `return ['status' => 'pending', 'message' => 'Paystack has not confirmed this payment yet. No second charge has been created.'];` |
| `app/Services/PaystackPaymentRecoveryService.php` | 65 | `$message = 'Paystack reported the transaction as ' . $gatewayStatus . '.';` |
| `app/Services/PaystackPaymentRecoveryService.php` | 76 | `['gateway' => 'paystack', 'reference' => $intent->reference, 'gateway_status' => $gatewayStatus]` |
| `app/Services/PaystackPaymentRecoveryService.php` | 78 | `return ['status' => 'failed', 'message' => 'Paystack did not complete this transaction. You can safely return to checkout.'];` |
| `app/Services/PaystackPaymentRecoveryService.php` | 81 | `$intent->update(['last_error' => 'Unrecognized Paystack status: ' . $gatewayStatus]);` |
| `app/Services/PaystackPaymentVerifier.php` | 5 | `class PaystackPaymentVerifier` |
| `app/Services/PaystackReconciliationService.php` | 14 | `class PaystackReconciliationService` |
| `app/Services/PaystackReconciliationService.php` | 18 | `public function reconcile(CarbonImmutable $date, ?PaystackGatewayClient $client = null): PaymentReconciliationRun` |
| `app/Services/PaystackReconciliationService.php` | 22 | `'gateway' => 'paystack',` |
| `app/Services/PaystackReconciliationService.php` | 35 | `$client ??= new PaystackGatewayClient();` |
| `app/Services/PaystackReconciliationService.php` | 37 | `->where('gateway', 'paystack')` |
| `app/Services/PaystackReconciliationService.php` | 60 | `->forReferences('paystack', $references);` |
| `app/Services/PaystackReconciliationService.php` | 79 | `throw new \RuntimeException('Paystack reconciliation exceeded the provider page safety limit.');` |
| `app/Services/PaystackReconciliationService.php` | 97 | `'paystack-reconciliation-mismatch:' . $run->public_id,` |
| `app/Services/PaystackReconciliationService.php` | 98 | `'Paystack reconciliation found payment mismatches.', [` |
| `app/Services/PaystackReconciliationService.php` | 115 | `'paystack-reconciliation-failed:' . $run->public_id,` |
| `app/Services/PaystackReconciliationService.php` | 116 | `'Paystack reconciliation failed.', [` |
| `app/Services/PaystackReconciliationService.php` | 144 | `'gateway' => 'paystack',` |
| `app/Services/PaystackReconciliationService.php` | 199 | `'gateway' => 'paystack',` |
| `app/Services/PaystackSettlementReconciliationService.php` | 13 | `class PaystackSettlementReconciliationService` |
| `app/Services/PaystackSettlementReconciliationService.php` | 20 | `?PaystackGatewayClient $client = null` |
| `app/Services/PaystackSettlementReconciliationService.php` | 22 | `$client = $client ?: new PaystackGatewayClient();` |
| `app/Services/PaystackSettlementReconciliationService.php` | 54 | `'paystack-settlement-failed:' . $settlementId,` |
| `app/Services/PaystackSettlementReconciliationService.php` | 55 | `'Paystack settlement reconciliation failed.', [` |
| `app/Services/PaystackSettlementReconciliationService.php` | 68 | `'paystack-settlement-list-failed:' . $from->toDateString() . ':' . $to->toDateString(),` |
| `app/Services/PaystackSettlementReconciliationService.php` | 69 | `'Paystack settlement listing failed.', [` |
| `app/Services/PaystackSettlementReconciliationService.php` | 86 | `'gateway' => 'paystack',` |
| `app/Services/PaystackSettlementReconciliationService.php` | 104 | `PaystackGatewayClient $client,` |
| `app/Services/PaystackSettlementReconciliationService.php` | 141 | `: PaymentIntent::where('gateway', 'paystack')` |
| `app/Services/PaystackSettlementReconciliationService.php` | 146 | `->forReferences('paystack', $references);` |
| `app/Services/PaystackSettlementReconciliationService.php` | 289 | `'paystack-settlement-mismatch:' . $settlement->gateway_settlement_id,` |
| `app/Services/PaystackSettlementReconciliationService.php` | 290 | `'Paystack settlement contains reconciliation mismatches.',` |
| `app/Services/PaystackSettlementReconciliationService.php` | 304 | `throw new \RuntimeException('Paystack settlement reconciliation exceeded the provider page safety limit.');` |
| `app/Services/ProductGatewayRefundService.php` | 34 | `->where('gateway', 'paystack')` |
| `app/Services/ProductGatewayRefundService.php` | 38 | `throw new RuntimeException('Only a completed durable Paystack product payment can use provider refunds.');` |
| `app/Services/ProductGatewayRefundService.php` | 60 | `if ($sale->gateway && ($sale->gateway !== 'paystack' \|\| $sale->gateway_reference !== $intent->reference)) {` |
| `app/Services/ProductGatewayRefundService.php` | 70 | `'gateway' => 'paystack',` |
| `app/Services/ProductGatewayRefundService.php` | 88 | `public function submit(ProductGatewayRefund $refund, ?PaystackGatewayClient $client = null): ProductGatewayRefund` |
| `app/Services/ProductGatewayRefundService.php` | 103 | `$payload = ($client ?: new PaystackGatewayClient())->createRefund(` |
| `app/Services/ProductGatewayRefundService.php` | 136 | `->where('gateway', 'paystack')` |
| `app/Services/ProductGatewayRefundService.php` | 150 | `?PaystackGatewayClient $client = null` |
| `app/Services/ProductGatewayRefundService.php` | 162 | `'The original Paystack transaction ID is unavailable for deterministic refund recovery.'` |
| `app/Services/ProductGatewayRefundService.php` | 166 | `$payload = ($client ?: new PaystackGatewayClient())->listRefundsForTransaction($transactionId);` |
| `app/Services/ProductGatewayRefundService.php` | 180 | `'last_error' => 'Paystack has not returned a matching refund yet; reconciliation will retry.',` |
| `app/Services/ProductGatewayRefundService.php` | 188 | `'Multiple Paystack refunds matched the controlled transaction, amount and currency.'` |
| `app/Services/ProductGatewayRefundService.php` | 220 | `'product-paystack-refund-mismatch:' . $refund->id,` |
| `app/Services/ProductGatewayRefundService.php` | 221 | `'Paystack product refund status did not match the controlled refund.',` |
| `app/Services/ProductGatewayRefundService.php` | 265 | `$externalReference = 'PAYSTACK-' . ($refund->gateway_refund_id ?: $refund->public_id);` |
| `app/Services/ProductGatewayRefundService.php` | 270 | `'Paystack-confirmed refund: ' . $refund->reason,` |
| `app/Services/ProductGatewayRefundService.php` | 322 | `'A Paystack product refund could not be recovered deterministically.',` |
| `app/Services/ProductGatewayRefundService.php` | 337 | `'A Paystack product refund remains indeterminate after provider reconciliation.',` |
| `app/Services/ProductOrderAccountingService.php` | 130 | `if ($gateway === '' && strtolower(trim((string) $order->method)) === 'paystack') {` |
| `app/Services/ProductOrderAccountingService.php` | 133 | `throw new LogicException('The completed Paystack product order is missing its gateway reference.');` |
| `app/Services/ProductOrderAccountingService.php` | 136 | `return ['paystack', $reference];` |
| `app/Services/ProductOrderRefundService.php` | 128 | `AuditLogger::record('product_refund.processed', $refund, [], [` |
| `app/Services/ProductPaystackCheckoutService.php` | 15 | `class ProductPaystackCheckoutService` |
| `app/Services/ProductPaystackCheckoutService.php` | 22 | `?PaystackGatewayClient $client = null` |
| `app/Services/ProductPaystackCheckoutService.php` | 31 | `throw new DomainException('Paystack shop checkout requires NGN as the base currency.');` |
| `app/Services/ProductPaystackCheckoutService.php` | 87 | `'method' => 'Paystack',` |
| `app/Services/ProductPaystackCheckoutService.php` | 98 | `'gateway' => 'paystack',` |
| `app/Services/ProductPaystackCheckoutService.php` | 111 | `], ['gateway' => 'paystack', 'reference' => $reference, 'order_id' => $order->id]);` |
| `app/Services/ProductPaystackCheckoutService.php` | 117 | `$payload = ($client ?: new PaystackGatewayClient())->initializeTransaction(` |
| `app/Services/ProductPaystackCheckoutService.php` | 122 | `route('product_order.paystack.notify'),` |
| `app/Services/ProductPaystackCheckoutService.php` | 126 | `throw new RuntimeException('Paystack returned a different transaction reference.');` |
| `app/Services/ProductPaystackCheckoutService.php` | 138 | `'product-paystack-init-unknown:' . $intent->id,` |
| `app/Services/ProductPaystackCheckoutService.php` | 139 | `'Product Paystack initialization has an unknown outcome.',` |
| `app/Services/ProductPaystackCheckoutService.php` | 147 | `'gateway' => 'paystack', 'reference' => $reference,` |
| `app/Services/ProductPaystackPaymentService.php` | 10 | `class ProductPaystackPaymentService` |
| `app/Services/ProductPaystackPaymentService.php` | 12 | `public function verify(ProductPaymentIntent $intent, ?PaystackGatewayClient $client = null): array` |
| `app/Services/ProductPaystackPaymentService.php` | 20 | `$response = ($client ?: new PaystackGatewayClient())->verifyTransaction($intent->reference);` |
| `app/Services/ProductPaystackPaymentService.php` | 30 | `throw new DomainException('The Paystack webhook has no transaction reference.');` |
| `app/Services/ProductPaystackPaymentService.php` | 33 | `->where('gateway', 'paystack')` |
| `app/Services/ProductPaystackPaymentService.php` | 46 | `$errors = ProductPaystackPaymentVerifier::errors($payment, $intent);` |
| `app/Services/ProductPaystackPaymentService.php` | 51 | `'product-paystack-verification-mismatch:' . $intent->id,` |
| `app/Services/ProductPaystackPaymentService.php` | 52 | `'Product Paystack payment verification mismatch.',` |
| `app/Services/ProductPaystackPaymentService.php` | 70 | `$message = 'Paystack reported the transaction as ' . $gatewayStatus . '.';` |
| `app/Services/ProductPaystackPaymentService.php` | 79 | `$intent->update(['last_error' => 'Unrecognized Paystack status: ' . $gatewayStatus]);` |
| `app/Services/ProductPaystackPaymentService.php` | 100 | `'product-paystack-paid-without-stock:' . $lockedIntent->id,` |
| `app/Services/ProductPaystackPaymentVerifier.php` | 7 | `class ProductPaystackPaymentVerifier` |
| `app/Services/ReleaseReadinessService.php` | 968 | `['paystack', 'flutterwave'],` |
| `app/Services/ReleaseReadinessService.php` | 973 | `$providerEvidenceReady = (!in_array('paystack', $certifiedEnabledGateways, true)` |
| `app/Services/ReleaseReadinessService.php` | 974 | `\|\| ((bool) config('payments.drill_paystack_verified', false)` |
| `app/Services/ReleaseReadinessService.php` | 975 | `&& (bool) config('payments.drill_shop_paystack_verified', false)))` |
| `app/Services/ReleaseReadinessService.php` | 995 | `'Assign the payment operations owner and complete a current production-like drill for every enabled certified gateway, including event checkout, Paystack shop checkout when enabled, signed webhooks, replay, lost-callb...` |
| `app/Services/ReleaseReadinessService.php` | 999 | `if ((bool) config('payouts.paystack_transfers_enabled', false)) {` |
| `app/Services/ReleaseReadinessService.php` | 1001 | `'payouts.paystack_gateway',` |
| `app/Services/ReleaseReadinessService.php` | 1002 | `in_array('paystack', $enabledGateways, true),` |
| `app/Services/ReleaseReadinessService.php` | 1004 | `'Paystack must be enabled before organizer payout transfers can be enabled.'` |
| `app/Services/ReleaseReadinessService.php` | 1020 | `'Complete a production-like Paystack recipient, transfer, signed-webhook, reconciliation, reversal and notification drill, then record its UTC date and non-secret evidence reference.'` |
| `app/Services/ReleaseReadinessService.php` | 1027 | `(bool) config('payouts.paystack_transfers_enabled', false)` |
| `app/Services/ReleaseReadinessService.php` | 1040 | `: 'Automatic release requires Paystack transfers, an approved versioned policy, approval reference, settlement owner and valid risk limits. Issues: '` |
| `app/Services/TicaPromotionPaystackService.php` | 16 | `class TicaPromotionPaystackService` |
| `app/Services/TicaPromotionPaystackService.php` | 18 | `public function initialize(int $campaignId, int $organizerId, ?PaystackGatewayClient $client = null): PaymentIntent` |
| `app/Services/TicaPromotionPaystackService.php` | 24 | `if (!in_array(strtoupper($campaign->currency), TicaCurrency::PAYSTACK, true)) throw ValidationException::withMessages(['campaign' => ['Paystack does not support this package currency.']]);` |
| `app/Services/TicaPromotionPaystackService.php` | 27 | `? PaymentIntent::query()->whereKey((int) $metadata['payment_intent_id'])->where('gateway', 'paystack')->first()` |
| `app/Services/TicaPromotionPaystackService.php` | 34 | `'gateway' => 'paystack', 'reference' => $reference, 'event_id' => $campaign->event_id, 'customer_id' => null,` |
| `app/Services/TicaPromotionPaystackService.php` | 47 | `$response = ($client ?: new PaystackGatewayClient())->initializeTransaction(` |
| `app/Services/TicaPromotionPaystackService.php` | 49 | `route('tica.promotion.paystack.callback'), ['payment_kind' => 'promotion', 'campaign_id' => $campaign->id]` |
| `app/Services/TicaPromotionPaystackService.php` | 51 | `if ((string) data_get($response, 'data.reference') !== $reference) throw new RuntimeException('Paystack returned a different transaction reference.');` |
| `app/Services/TicaPromotionPaystackService.php` | 53 | `AuditLogger::record('promotion.gateway_payment_initialized', $campaign, [], ['payment_status' => 'pending'], ['gateway' => 'paystack', 'reference' => $reference]);` |
| `app/Services/TicaPromotionPaystackService.php` | 61 | `public function verifyReference(string $reference, ?PaystackGatewayClient $client = null): TicaPromotionCampaign` |
| `app/Services/TicaPromotionPaystackService.php` | 65 | `$payload = ($client ?: new PaystackGatewayClient())->verifyTransaction($reference);` |
| `app/Services/TicaPromotionPaystackService.php` | 75 | `public function requestRefund(int $campaignId, string $reason, ?PaystackGatewayClient $client = null): TicaPromotionCampaign` |
| `app/Services/TicaPromotionPaystackService.php` | 81 | `if ($campaign->payment_status !== 'paid' \|\| !$payment \|\| $payment->gateway !== 'paystack' \|\| !$payment->gateway_reference) {` |
| `app/Services/TicaPromotionPaystackService.php` | 82 | `throw ValidationException::withMessages(['decision' => ['This campaign does not have a refundable Paystack payment.']]);` |
| `app/Services/TicaPromotionPaystackService.php` | 84 | `$response = ($client ?: new PaystackGatewayClient())->createRefund($payment->gateway_reference, (string) $campaign->price, (string) $campaign->currency, $reason ?: 'TICA promotion refund');` |
| `app/Services/TicaPromotionPaystackService.php` | 86 | `$metadata['refund'] = ['provider' => 'paystack', 'id' => (string) data_get($response, 'data.id'), 'status' => (string) data_get($response, 'data.status', 'pending')];` |
| `app/Services/TicaPromotionPaystackService.php` | 89 | `AuditLogger::record('promotion.gateway_refund_requested', $campaign, $before, $campaign->only(array_keys($before)), ['gateway' => 'paystack', 'gateway_reference' => $payment->gateway_reference]);` |
| `app/Services/TicaPromotionPaystackService.php` | 100 | `if ($eventName === 'refund.processed' \|\| in_array($status, ['processed', 'success', 'succeeded'], true)) {` |
| `app/Services/TicaPromotionPaystackService.php` | 103 | `AuditLogger::record('promotion.gateway_refund_completed', $campaign, $before, $campaign->only(array_keys($before)), ['gateway' => 'paystack', 'gateway_reference' => $reference]);` |
| `app/Services/TicaPromotionPaystackService.php` | 104 | `app(TicaPromotionNotificationService::class)->send($campaign, 'refunded', 'Promotion payment refunded', 'Paystack confirmed your promotion refund. Bank processing times may vary.');` |
| `app/Services/TicaPromotionPaystackService.php` | 105 | `} elseif (in_array($eventName, ['refund.failed', 'refund.error'], true) \|\| in_array($status, ['failed', 'error'], true)) {` |
| `app/Services/TicaPromotionPaystackService.php` | 107 | `AuditLogger::record('promotion.gateway_refund_failed', $campaign, ['payment_status' => 'refund_pending'], ['payment_status' => 'paid'], ['gateway' => 'paystack', 'gateway_reference' => $reference]);` |
| `app/Services/TicaPromotionPaystackService.php` | 114 | `$errors = PaystackPaymentVerifier::errors($gatewayData, (array) $intent->payload);` |
| `app/Services/TicaPromotionPaystackService.php` | 117 | `throw ValidationException::withMessages(['payment' => ['Paystack verification failed: ' . implode(', ', $errors)]]);` |
| `app/Services/TicaPromotionPaystackService.php` | 127 | `['gateway' => 'paystack', 'gateway_reference' => $lockedIntent->reference],` |
| `app/Services/TicaPromotionPaystackService.php` | 131 | `'payment_status' => 1, 'payment_method' => 'Paystack promotion', 'grand_total' => $campaign->price,` |
| `app/Services/TicaPromotionPaystackService.php` | 145 | `AuditLogger::record('promotion.gateway_payment_verified', $campaign, ['payment_status' => 'pending'], ['payment_status' => 'paid', 'status' => $campaign->status], ['gateway' => 'paystack', 'reference' => $lockedIntent...` |
| `app/Services/TicaPromotionPaystackService.php` | 146 | `DB::afterCommit(fn () => app(TicaPromotionNotificationService::class)->send($campaign, 'payment_confirmed', 'Promotion payment confirmed', 'Paystack verified your payment. The campaign will not appear publicly until i...` |
| `app/Services/TicaPromotionPaystackService.php` | 153 | `$intent = PaymentIntent::query()->where('gateway', 'paystack')->where('reference', $reference)->firstOrFail();` |
| `app/Services/TicaUi/EventCatalog.php` | 125 | `'paystack' => TicaCurrency::PAYSTACK,` |
| `app/Support/Integrations/IntegrationRegistry.php` | 22 | `public const CORE = ['paystack', 'email', 'google_login'];` |
| `app/Support/Integrations/IntegrationRegistry.php` | 36 | `'paystack' => [` |
| `app/Support/Integrations/IntegrationRegistry.php` | 37 | `'group' => 'payments', 'name' => 'Paystack',` |
| `app/Support/Integrations/IntegrationRegistry.php` | 39 | `'summary' => 'Card, bank transfer and USSD payments at checkout. The same key sends organiser payouts when Paystack is the payout provider.',` |
| `app/Support/Integrations/IntegrationRegistry.php` | 40 | `'enabled' => 'gateway:paystack',` |
| `app/Support/Integrations/IntegrationRegistry.php` | 42 | `['name' => 'mode', 'label' => 'Mode', 'type' => 'select', 'source' => 'gateway:paystack.mode', 'options' => ['test' => 'Test', 'live' => 'Live'], 'required' => true, 'default' => 'test', 'help' => 'Use test mode until...` |
| `app/Support/Integrations/IntegrationRegistry.php` | 43 | `['name' => 'public_key', 'label' => 'Public key', 'type' => 'text', 'source' => 'gateway:paystack.public_key', 'required' => true, 'help' => 'Starts with pk_test_ or pk_live_ and must match the selected mode.', 'patte...` |
| `app/Support/Integrations/IntegrationRegistry.php` | 44 | `['name' => 'secret_key', 'label' => 'Secret key', 'type' => 'secret', 'source' => 'gateway:paystack.key', 'required' => true, 'help' => 'Paystack dashboard → Settings → API Keys & Webhooks. Starts with sk_live_ (o...` |
| `app/Support/Integrations/IntegrationRegistry.php` | 46 | `'urls' => [['label' => 'Webhook URL (paste into Paystack → Settings → API Keys & Webhooks)', 'route' => 'paystack.webhook']],` |
| `app/Support/Integrations/IntegrationRegistry.php` | 47 | `'notes' => ['env_override' => ['PAYSTACK_SECRET_KEY', 'The server variable PAYSTACK_SECRET_KEY is set, so payouts use that key instead of the one saved here. Remove it on Vercel to manage the key only here.']],` |
| `app/Support/Integrations/IntegrationRegistry.php` | 78 | `['name' => 'provider', 'label' => 'Payout provider', 'type' => 'select', 'source' => 'platform.payout_provider', 'options' => ['paystack' => 'Paystack', 'monnify' => 'Monnify'], 'required' => true, 'help' => 'Set up t...` |
| `app/Support/Integrations/IntegrationRegistry.php` | 79 | `['name' => 'transfers_enabled', 'label' => 'Send payouts through the provider', 'type' => 'toggle', 'source' => 'store', 'config' => 'payouts.paystack_transfers_enabled', 'env' => 'PAYSTACK_TRANSFERS_ENABLED', 'help' ...` |
| `app/Support/Integrations/Integrations.php` | 207 | `if ($key === 'paystack') {` |
| `app/Support/Integrations/IntegrationTester.php` | 23 | `'paystack' => self::paystack(),` |
| `app/Support/Integrations/IntegrationTester.php` | 57 | `private static function paystack(): array` |
| `app/Support/Integrations/IntegrationTester.php` | 59 | `$secret = self::val('paystack', 'secret_key');` |
| `app/Support/Integrations/IntegrationTester.php` | 60 | `$public = self::val('paystack', 'public_key');` |
| `app/Support/Integrations/IntegrationTester.php` | 61 | `$mode = self::val('paystack', 'mode') ?: (str_starts_with($secret, 'sk_live_') ? 'live' : 'test');` |
| `app/Support/Integrations/IntegrationTester.php` | 71 | `$r = self::http()->withToken($secret)->get('https://api.paystack.co/balance');` |
| `app/Support/Integrations/IntegrationTester.php` | 73 | `return [false, 'Paystack rejected this secret key.'];` |
| `app/Support/Integrations/IntegrationTester.php` | 76 | `return [false, 'Paystack answered ' . $r->status() . ': ' . mb_substr((string) $r->json('message'), 0, 150)];` |
| `app/Support/Integrations/IntegrationTester.php` | 80 | `return [true, "Paystack accepted the key ({$mode})."];` |
| `app/Support/Integrations/IntegrationTester.php` | 118 | `$provider = self::val('payouts', 'provider') ?: 'paystack';` |
| `app/Support/Integrations/IntegrationTester.php` | 119 | `[$ok, $message] = $provider === 'monnify' ? self::monnify() : self::paystack();` |
| `app/Support/TicaCurrency.php` | 19 | `/** Currencies Paystack can charge in. Paid tickets need one of these. */` |
| `app/Support/TicaCurrency.php` | 20 | `public const PAYSTACK = ['NGN', 'GHS', 'KES', 'ZAR', 'USD', 'XOF', 'EGP'];` |
| `app/Support/TicaCurrency.php` | 129 | `'paid' => in_array($code, self::PAYSTACK, true),` |
| `app/Support/TicaPayments.php` | 9 | `* The online gateways a TICA buyer can pay with. Paystack takes every` |
| `app/Support/TicaPayments.php` | 10 | `* Paystack currency, Monnify takes naira only, Flutterwave charges in the` |
| `app/Support/TicaPayments.php` | 15 | `/** [keyword => [label, currencies; null means any Paystack currency, 'base' the base currency]] in display order */` |
| `app/Support/TicaPayments.php` | 17 | `'paystack' => ['Paystack', null],` |
| `app/Support/TicaPayments.php` | 30 | `default => TicaCurrency::PAYSTACK,` |
| `app/Support/TicaPlatform.php` | 69 | `/** Which provider sends organiser payouts: "paystack" (default) or "monnify". */` |
| `app/Support/TicaPlatform.php` | 72 | `$provider = (string) (self::stored()['payout_provider'] ?? 'paystack');` |
| `app/Support/TicaPlatform.php` | 74 | `return in_array($provider, ['paystack', 'monnify'], true) ? $provider : 'paystack';` |
| `app/Support/TicaPlatform.php` | 79 | `if (!in_array($provider, ['paystack', 'monnify'], true)) {` |
| `routes/admin.php` | 297 | `Route::post('withdraw/withdraw-request/paystack/{id}', 'BackEnd\WithdrawController@submitPaystack')->name('admin.withdraw.submit_paystack');` |
| `routes/admin.php` | 719 | `Route::post('/update-paystack-info', 'BackEnd\PaymentGateway\OnlineGatewayController@updatePaystackInfo')->name('admin.payment_gateways.update_paystack_info');` |
| `routes/web.php` | 46 | `\| Paystack Webhook` |
| `routes/web.php` | 48 | `\| MUST be declared outside ALL middleware groups so that Paystack's servers` |
| `routes/web.php` | 51 | `\| Also add 'paystack/webhook' to the $except array in:` |
| `routes/web.php` | 54 | `\| And register the URL in Paystack Dashboard:` |
| `routes/web.php` | 55 | `\| Settings → API Keys & Webhooks → Live → https://tica.ng/paystack/webhook` |
| `routes/web.php` | 59 | `'/paystack/webhook',` |
| `routes/web.php` | 60 | `[App\Http\Controllers\FrontEnd\PaymentGateway\PaystackController::class, 'webhook']` |
| `routes/web.php` | 61 | `)->middleware(['webhook.receipt:paystack', 'throttle:120,1'])->name('paystack.webhook');` |
| `routes/web.php` | 247 | `// Paystack browser callback (GET) — user redirected back here after paying` |
| `routes/web.php` | 248 | `Route::get('/paystack/notify', 'FrontEnd\PaymentGateway\PaystackController@notify')` |
| `routes/web.php` | 249 | `->middleware(['payment.gateway:event,paystack', 'throttle:checkout'])` |
| `routes/web.php` | 250 | `->name('event_booking.paystack.notify');` |
| `routes/web.php` | 251 | `Route::get('/paystack/recovery/{reference}', 'FrontEnd\PaymentGateway\PaystackController@recovery')` |
| `routes/web.php` | 252 | `->middleware(['payment.gateway:event,paystack', 'signed', 'throttle:checkout'])` |
| `routes/web.php` | 253 | `->name('event_booking.paystack.recovery');` |
| `routes/web.php` | 254 | `Route::post('/paystack/recovery/{reference}', 'FrontEnd\PaymentGateway\PaystackController@retryRecovery')` |
| `routes/web.php` | 255 | `->middleware(['payment.gateway:event,paystack', 'signed', 'throttle:checkout'])` |
| `routes/web.php` | 256 | `->name('event_booking.paystack.recovery.verify');` |
| `routes/web.php` | 438 | `Route::get('paystack/notify', 'FrontEnd\Shop\PaymentGateway\PaystackController@notify')` |
| `routes/web.php` | 439 | `->middleware(['payment.gateway:shop,paystack', 'throttle:20,1'])->name('product_order.paystack.notify');` |
| `routes/web.php` | 440 | `Route::get('paystack/recovery/{intent}', 'FrontEnd\Shop\PaymentGateway\PaystackController@recovery')` |
| `routes/web.php` | 441 | `->middleware(['payment.gateway:shop,paystack', 'signed', 'throttle:20,1'])->name('product_order.paystack.recovery');` |
| `routes/web.php` | 442 | `Route::post('paystack/recovery/{intent}', 'FrontEnd\Shop\PaymentGateway\PaystackController@retryRecovery')` |
| `routes/web.php` | 443 | `->middleware(['payment.gateway:shop,paystack', 'signed', 'throttle:10,1'])->name('product_order.paystack.recovery.verify');` |
| `routes/web.php` | 645 | `Route::get('/promotion/paystack/callback', 'FrontEnd\PromotionTrackingController@paystackCallback')` |
| `routes/web.php` | 646 | `->middleware('throttle:authentication')->name('tica.promotion.paystack.callback');` |
| `config/payments.php` | 5 | `explode(',', (string) env('PRODUCTION_READY_PAYMENT_GATEWAYS', 'paystack,flutterwave,monnify,stripe'))` |
| `config/payments.php` | 34 | `'drill_paystack_verified' => filter_var(env('PAYMENT_DRILL_PAYSTACK_VERIFIED', false), FILTER_VALIDATE_BOOL),` |
| `config/payments.php` | 36 | `'drill_shop_paystack_verified' => filter_var(env('PAYMENT_DRILL_SHOP_PAYSTACK_VERIFIED', false), FILTER_VALIDATE_BOOL),` |
| `config/payouts.php` | 6 | `'paystack_transfers_enabled' => (bool) env('PAYSTACK_TRANSFERS_ENABLED', false),` |
| `config/services.php` | 84 | `\| When PAYSTACK_SECRET_KEY is empty, the key saved in Admin > Payment` |
| `config/services.php` | 87 | `'paystack' => [` |
| `config/services.php` | 88 | `'secret' => env('PAYSTACK_SECRET_KEY'),` |
| `database/migrations/2026_11_01_070000_create_organizer_payout_accounts_table.php` | 15 | `$table->string('provider', 30)->default('paystack');` |
| `database/migrations/2026_11_01_230000_create_payout_transfers_table.php` | 23 | `$table->string('gateway', 30)->default('paystack');` |
| `resources/views/backend/admin/payment-reconciliation/index.blade.php` | 27 | `@foreach (['paystack' => 'Paystack', 'flutterwave' => 'Flutterwave'] as $value => $label)` |
| `resources/views/backend/admin/payment-reconciliation/index.blade.php` | 45 | `{{ __('Runs compare successful Paystack and Flutterwave charges with local payment intents, bookings and sale-ledger identities. Resolve every mismatch before finance close.') }}` |
| `resources/views/backend/admin/payment-reconciliation/settlements.blade.php` | 17 | `<div class="col-lg-4"><div class="card-title d-inline-block">{{ __('Paystack bank settlements') }}</div><a href="{{ route('admin.reconciliation_exports.index') }}" class="btn btn-sm btn-outline-secondary ml-2">{{ __('...` |
| `resources/views/backend/admin/webhook-operations/index.blade.php` | 29 | `@foreach (['paystack', 'flutterwave'] as $gateway)` |
| `resources/views/backend/event/cancellations.blade.php` | 21 | `{{ __('Approval permanently cancels the event, keeps sales closed, queues full refunds only for verified Paystack/Flutterwave payments that have not checked in, and sends attendee notices. Any legacy, unsupported, con...` |
| `resources/views/backend/event/cancellations.blade.php` | 165 | `&& in_array($case->paymentIntent->gateway, ['paystack', 'flutterwave']);` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 45 | `{{-- paystack --}}` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 48 | `<form action="{{ route('admin.payment_gateways.update_paystack_info') }}" method="post">` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 53 | `<div class="card-title">{{ __('Paystack') }}</div>` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 62 | `<label>{{ __('Paystack Status') }}</label>` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 65 | `<input type="radio" name="paystack_status" value="1" class="selectgroup-input"` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 66 | `{{ $paystack->status == 1 ? 'checked' : '' }}>` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 70 | `<input type="radio" name="paystack_status" value="0" class="selectgroup-input"` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 71 | `{{ $paystack->status == 0 ? 'checked' : '' }}>` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 75 | `@if ($errors->has('paystack_status'))` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 76 | `<p class="mt-1 mb-0 text-danger">{{ $errors->first('paystack_status') }}</p>` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 80 | `@php $paystackInfo = json_decode($paystack->information, true); @endphp` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 83 | `<label>{{ __('Paystack Secret Key') }}</label>` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 84 | `<input type="password" class="form-control" name="paystack_key" value=""` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 86 | `placeholder="{{ !empty($paystackInfo['key']) ? __('Configured — leave blank to keep') : __('Enter Paystack secret key') }}">` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 88 | `@if ($errors->has('paystack_key'))` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 89 | `<p class="mt-1 mb-0 text-danger">{{ $errors->first('paystack_key') }}</p>` |
| `resources/views/backend/payment-gateways/online-gateways.blade.php` | 126 | `<p class="text-muted mb-3">{{ __('Card, bank transfer and USSD payments in naira. Events priced in other currencies use Paystack.') }}</p>` |
| `resources/views/backend/payout-accounts/index.blade.php` | 69 | `<p class="tpa-note">Transfers are switched off (PAYSTACK_TRANSFERS_ENABLED=false), so payouts are marked paid by hand.</p>` |
| `resources/views/backend/payout-accounts/index.blade.php` | 76 | `@foreach (['paystack' => 'Paystack', 'monnify' => 'Monnify'] as $key => $label)` |
| `resources/views/backend/product/order/refund-modal.blade.php` | 2 | `@php($providerRefund = optional($order->payment_intent)->gateway === 'paystack' && optional($order->payment_intent)->status === 'completed')` |
| `resources/views/backend/product/order/refund-modal.blade.php` | 11 | `<h5 class="modal-title" id="productRefundModalLabel-{{ $order->id }}">{{ $providerRefund ? __('Request full Paystack refund') : __('Record full product refund') }}</h5>` |
| `resources/views/backend/product/order/refund-modal.blade.php` | 19 | `? __('This submits a full refund to Paystack. The order, earnings and inventory will change only after Paystack confirms the refund as processed.')` |
| `resources/views/backend/product/order/refund-modal.blade.php` | 58 | `? __('I authorize this full Paystack refund and understand that it will be submitted asynchronously.')` |
| `resources/views/backend/product/order/refund-modal.blade.php` | 65 | `<button type="submit" class="btn btn-warning">{{ $providerRefund ? __('Request Paystack refund') : __('Record and reconcile refund') }}</button>` |
| `resources/views/backend/settings/index.blade.php` | 60 | `<span><b>{{ __('Integrations') }}</b><small>{{ __('Paystack, payouts, email, SMS, sign-in and storage') }}</small></span>` |
| `resources/views/backend/tica/platform-settings.blade.php` | 105 | `@if (in_array($code, $paystack, true))<em class="tag-green">{{ __('Card payments') }}</em>@else<em class="tag-grey">{{ __('Free events only') }}</em>@endif` |
| `resources/views/backend/tica/platform-settings.blade.php` | 147 | `<a href="{{ route('admin.payment_gateways.online_gateways') }}"><b>{{ __('Payments') }}</b><small>{{ __('Paystack and Monnify keys, and other ways to pay') }}</small></a>` |
| `resources/views/backend/tica/promotions.blade.php` | 250 | `@if($campaign->payment_status === 'paid')<option value="refund">{{ optional($campaign->paymentTransaction)->gateway === 'paystack' ? __('Refund through Paystack') : __('Refund to organizer balance') }}</option>@endif` |
| `resources/views/backend/withdraw/history/index.blade.php` | 163 | `@if (config('payouts.paystack_transfers_enabled') && in_array(optional($item->payoutAccount)->provider, ['paystack', 'monnify'], true) && optional($item->payoutAccount)->status === 'verified')` |
| `resources/views/backend/withdraw/history/index.blade.php` | 165 | `<button type="button" data-toggle="modal" data-target="#paystackModal{{ $item->id }}" class="btn btn-primary mt-1 btn-xs"><span class="btn-label"><i class="fas fa-paper-plane"></i></span> {{ $lastTransfer && in_array(...` |
| `resources/views/backend/withdraw/history/view.blade.php` | 39 | `<p><strong>{{ __('Latest Paystack transfer') }}</strong></p>` |
| `resources/views/backend/withdraw/history/view.blade.php` | 59 | `@if (config('payouts.paystack_transfers_enabled') && optional($item->payoutAccount)->provider === 'paystack' && optional($item->payoutAccount)->status === 'verified')` |
| `resources/views/backend/withdraw/history/view.blade.php` | 60 | `<div class="modal fade" id="paystackModal{{ $item->id }}" tabindex="-1" role="dialog" aria-hidden="true">` |
| `resources/views/backend/withdraw/history/view.blade.php` | 62 | `<form method="post" action="{{ route('admin.withdraw.submit_paystack', ['id' => $item->id]) }}">` |
| `resources/views/backend/withdraw/history/view.blade.php` | 64 | `<div class="modal-header"><h4 class="modal-title">{{ __('Submit Paystack payout') }}</h4><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>` |
| `resources/views/backend/withdraw/history/view.blade.php` | 66 | `<p>{{ __('This queues a real transfer to the verified destination below. The withdrawal stays reserved until a signed Paystack webhook or server verification confirms the final outcome.') }}</p>` |
| `resources/views/emails/payout-status.blade.php` | 30 | `Review the payout in the finance withdrawal queue. Do not submit a new transfer until the existing reference has been verified with Paystack.` |
| `resources/views/frontend/check-out.blade.php` | 210 | `<option value="{{ $online_gateway->keyword }}" {{ $online_gateway->keyword == old('gateway', 'paystack') ? 'selected' : '' }}>{{ $online_gateway->name }}</option>` |
| `resources/views/tica-ui/pages/how.blade.php` | 35 | `<li><b>Pay securely</b><span>Check out with Paystack using card, bank transfer or USSD.</span></li>` |
| `resources/views/tica-ui/pages/policies.blade.php` | 70 | `<li><b>Payment.</b> Payments are handled by Paystack, by card, bank transfer or USSD. Your order is confirmed when Paystack confirms the payment and we send your tickets. If the payment fails, no order is made and you...` |
| `resources/views/tica-ui/pages/policies.blade.php` | 85 | `<div class="lg-tr" role="row"><span role="cell">You were charged twice, or paid but got no ticket</span><span role="cell">A refund of the extra charge, or your ticket, once Paystack confirms the payment.</span></div>` |
| `resources/views/tica-ui/pages/policies.blade.php` | 141 | `<li><b>When you pay:</b> Paystack confirms whether the payment worked and gives us the payment reference, the type of card and its last four digits. We never see or store your full card number or PIN.</li>` |
| `resources/views/tica-ui/pages/policies.blade.php` | 161 | `<li><b>Paystack,</b> to process payments.</li>` |
| `resources/views/tica-ui/pages/policies.blade.php` | 206 | `<div class="lg-tr" role="row"><span role="cell">Paystack</span><span role="cell">Payment security and fraud checks inside Paystack's window</span><span role="cell">Essential, set by Paystack</span><span role="cell">Se...` |
| `resources/views/tica-ui/pages/pricing.blade.php` | 25 | `<article class="plan feat nz"><h3>Paid events</h3><b class="pr"><span data-fee-pct>6%</span> per ticket</b><p>For shows, conferences and anything you sell tickets for.</p><ul><li>Everything in free events</li><li>Mult...` |
| `tests/e2e/sweep.spec.ts` | 19 | `'/admin/integrations', '/admin/integrations/paystack', '/admin/integrations/email',` |
| `tests/Unit/AuditGovernanceTest.php` | 59 | `'paystack_secret_key' => 'must-never-enter-audit-history',` |
| `tests/Unit/AuditGovernanceTest.php` | 73 | `$this->assertSame(['base_currency_text', 'paystack_secret_key'], $metadata['changed_fields']);` |
| `tests/Unit/DataExportServiceTest.php` | 105 | `'booking_id' => 1, 'requested_by_admin_id' => 1, 'gateway' => 'paystack',` |
| `tests/Unit/DataExportServiceTest.php` | 191 | `'payment_method' => 'paystack', 'payment_status' => 'completed',` |
| `tests/Unit/DataExportServiceTest.php` | 196 | `$this->assertSame('paystack', $export->filters['payment_method']);` |
| `tests/Unit/DataExportServiceTest.php` | 293 | `'paymentMethod' => 'paystack', 'paymentStatus' => 'completed',` |
| `tests/Unit/DataExportServiceTest.php` | 301 | `'transcation_id' => 'PAY-REF-1', 'gateway' => 'paystack',` |
| `tests/Unit/DataExportServiceTest.php` | 308 | `'booking_id' => $bookingId, 'requested_by_admin_id' => 1, 'gateway' => 'paystack',` |
| `tests/Unit/DataExportServiceTest.php` | 314 | `'id' => 1, 'gateway' => 'paystack', 'gateway_settlement_id' => 'SETTLEMENT-1',` |
| `tests/Unit/EventCancellationServiceTest.php` | 125 | `[$intent, $eligibleBooking] = $this->paidBooking($event, 'paystack', 'verified-ref');` |
| `tests/Unit/EventCancellationServiceTest.php` | 209 | `[$intent, $booking] = $this->paidBooking($event, 'paystack', 'late-payment-ref');` |
| `tests/Unit/EventCancellationServiceTest.php` | 232 | `[$intent, $booking] = $this->paidBooking($event, 'paystack', 'checked-in-cancel-ref');` |
| `tests/Unit/EventCancellationServiceTest.php` | 333 | `[$intent, $booking] = $this->paidBooking($event, 'paystack', 'repair-provider-ref');` |
| `tests/Unit/EventCancellationServiceTest.php` | 411 | `$this->paidBooking($event, 'paystack', 'notice-ref');` |
| `tests/Unit/FinalizePaidEventBookingTest.php` | 700 | `'paymentMethod' => 'Paystack',` |
| `tests/Unit/FinancialLedgerServiceTest.php` | 146 | `'transcation_id' => 'ref-1', 'gateway' => 'paystack', 'gateway_reference' => 'ref-1',` |
| `tests/Unit/FinancialLedgerServiceTest.php` | 148 | `'payment_status' => 1, 'payment_method' => 'paystack', 'grand_total' => 25000,` |
| `tests/Unit/FunnelAnalyticsServiceTest.php` | 76 | `'gateway' => 'paystack',` |
| `tests/Unit/FunnelAnalyticsServiceTest.php` | 87 | `$this->assertSame('paystack', $first->properties['gateway']);` |
| `tests/Unit/FunnelAnalyticsServiceTest.php` | 89 | `$this->assertStringNotContainsString('paystack', (string) DB::table('funnel_analytics_events')->value('properties'));` |
| `tests/Unit/FunnelAnalyticsServiceTest.php` | 95 | `'gateway' => 'paystack',` |
| `tests/Unit/GatewayLedgerBackfillServiceTest.php` | 23 | `config()->set('payments.production_ready_gateways', ['paystack', 'flutterwave']);` |
| `tests/Unit/GatewayLedgerBackfillServiceTest.php` | 31 | `$intent = $this->intent('paystack', 'ref-dry', 71, 10500);` |
| `tests/Unit/GatewayLedgerBackfillServiceTest.php` | 34 | `$result = (new GatewayLedgerBackfillService())->run(false, ['paystack']);` |
| `tests/Unit/GatewayLedgerBackfillServiceTest.php` | 66 | `$this->intent('paystack', 'ref-conflict', 73, 12000);` |
| `tests/Unit/GatewayLedgerBackfillServiceTest.php` | 69 | `$result = (new GatewayLedgerBackfillService())->run(true, ['paystack']);` |
| `tests/Unit/GatewayLedgerBackfillServiceTest.php` | 80 | `$this->intent('paystack', 'expected-reference', 74, 10500);` |
| `tests/Unit/GatewayLedgerBackfillServiceTest.php` | 82 | `$this->intent('paystack', 'missing-reference', 75, 10500);` |
| `tests/Unit/GatewayLedgerBackfillServiceTest.php` | 84 | `$result = (new GatewayLedgerBackfillService())->run(true, ['paystack']);` |
| `tests/Unit/IntegrationsTest.php` | 51 | `foreach (['paystack', 'flutterwave', 'monnify', 'payouts', 'email', 'sms', 'push', 'google_login', 'facebook_login', 'recaptcha', 'storage'] as $key) {` |
| `tests/Unit/IntegrationsTest.php` | 54 | `$this->assertSame(['paystack', 'email', 'google_login'], IntegrationRegistry::CORE);` |
| `tests/Unit/IntegrationsTest.php` | 143 | `$request = Request::create('/admin/integrations/paystack', 'POST', ['f_secret_key' => 'pk_live_wrongkind', 'current_password' => 'right-password']);` |
| `tests/Unit/IntegrationsTest.php` | 146 | `app(IntegrationController::class)->update($request, 'paystack');` |
| `tests/Unit/IntegrationsTest.php` | 153 | `public function test_paystack_keys_must_match_the_selected_mode(): void` |
| `tests/Unit/IntegrationsTest.php` | 156 | `$request = Request::create('/admin/integrations/paystack', 'POST', [` |
| `tests/Unit/IntegrationsTest.php` | 165 | `app(IntegrationController::class)->update($request, 'paystack');` |
| `tests/Unit/IntegrationsTest.php` | 188 | `Integrations::save('paystack', [` |
| `tests/Unit/IntegrationsTest.php` | 194 | `'api.paystack.co/balance' => Http::response(['status' => true, 'data' => []], 200),` |
| `tests/Unit/IntegrationsTest.php` | 198 | `[$paystackOk] = IntegrationTester::run('paystack', 'admin@tica.ng');` |
| `tests/Unit/IntegrationsTest.php` | 206 | `$this->assertTrue($paystackOk);` |
| `tests/Unit/IntegrationsTest.php` | 248 | `$paystack = new \App\Models\PaymentGateway\OnlineGateway();` |
| `tests/Unit/IntegrationsTest.php` | 249 | `$paystack->forceFill(['name' => 'Paystack', 'keyword' => 'paystack', 'status' => 0, 'information' => json_encode([])])->save();` |
| `tests/Unit/IyzicoDispatchCommandTest.php` | 51 | `['paymentStatus' => 'pending', 'paymentMethod' => 'Paystack', 'conversation_id' => 'event-other'],` |
| `tests/Unit/ManualBookingPaymentServiceTest.php` | 321 | `'gateway' => 'paystack',` |
| `tests/Unit/ManualBookingPaymentServiceTest.php` | 328 | `'payment_method' => 'Paystack',` |
| `tests/Unit/OnlineGatewayCredentialSecurityTest.php` | 39 | `'name' => 'Paystack',` |
| `tests/Unit/OnlineGatewayCredentialSecurityTest.php` | 40 | `'keyword' => 'paystack',` |
| `tests/Unit/OnlineGatewayCredentialSecurityTest.php` | 63 | `'name' => 'Paystack', 'keyword' => 'paystack',` |
| `tests/Unit/OnlineGatewayCredentialSecurityTest.php` | 90 | `$this->assertStringContainsString('name="paystack_key" value=""', $view);` |
| `tests/Unit/OnlineGatewayCredentialSecurityTest.php` | 94 | `$this->assertStringNotContainsString('value="{{ $paystackInfo[\'key\'] }}"', $view);` |
| `tests/Unit/OnlineGatewayCredentialSecurityTest.php` | 112 | `'name' => 'Paystack', 'keyword' => 'paystack',` |
| `tests/Unit/OnlineGatewayCredentialSecurityTest.php` | 113 | `'information' => json_encode(['key' => 'paystack-existing']), 'status' => 1,` |
| `tests/Unit/OnlineGatewayCredentialSecurityTest.php` | 126 | `$controller->updatePaystackInfo(Request::create('/admin/payment-gateways/update-paystack-info', 'POST', [` |
| `tests/Unit/OnlineGatewayCredentialSecurityTest.php` | 127 | `'paystack_status' => '0', 'paystack_key' => '',` |
| `tests/Unit/OnlineGatewayCredentialSecurityTest.php` | 137 | `$paystack = OnlineGateway::whereKeyword('paystack')->firstOrFail();` |
| `tests/Unit/OnlineGatewayCredentialSecurityTest.php` | 140 | `$this->assertSame('paystack-existing', json_decode($paystack->information, true)['key']);` |
| `tests/Unit/OnlineGatewayCredentialSecurityTest.php` | 145 | `$this->assertSame(0, (int) $paystack->status);` |
| `tests/Unit/OnlineGatewayCredentialSecurityTest.php` | 191 | `'name' => 'Paystack', 'keyword' => 'paystack',` |
| `tests/Unit/OnlineGatewayCredentialSecurityTest.php` | 192 | `'information' => json_encode(['key' => 'paystack-key']), 'status' => 0,` |
| `tests/Unit/OnlineGatewayCredentialSecurityTest.php` | 196 | `(new OnlineGatewayController())->updatePaystackInfo(Request::create('/admin/payment-gateways/update-paystack-info', 'POST', [` |
| `tests/Unit/OnlineGatewayCredentialSecurityTest.php` | 197 | `'paystack_status' => 'enabled', 'paystack_key' => 'paystack-key',` |
| `tests/Unit/OnlineGatewayCredentialSecurityTest.php` | 200 | `$this->assertSame(0, (int) OnlineGateway::whereKeyword('paystack')->value('status'));` |
| `tests/Unit/OnlineGatewayCredentialSecurityTest.php` | 201 | `$this->assertTrue(session('errors')->has('paystack_status'));` |
| `tests/Unit/OperationsEvidenceExportTest.php` | 70 | `$this->insertWebhook(1, 'paystack', '+charge.success', 'processed', '2026-11-03 10:00:00');` |
| `tests/Unit/OperationsEvidenceExportTest.php` | 72 | `'from'=>'2026-11-03','to'=>'2026-11-03','gateway'=>'paystack','outcome'=>'processed',` |
| `tests/Unit/OperationsEvidenceExportTest.php` | 74 | `$this->insertWebhook(2, 'paystack', 'late', 'processed', '2026-11-04 10:00:00');` |
| `tests/Unit/OperationsEvidenceExportTest.php` | 78 | `$this->assertStringContainsString('paystack', $webhookCsv);` |
| `tests/Unit/OperationsEvidenceExportTest.php` | 79 | `$this->assertStringContainsString("'+charge.success", $webhookCsv);` |
| `tests/Unit/OrganizerAttendeeDirectoryTest.php` | 203 | `'paymentMethod' => 'Paystack',` |
| `tests/Unit/OrganizerFinanceStatementServiceTest.php` | 165 | `'payment_method' => 'Paystack',` |
| `tests/Unit/OrganizerPayoutAccountServiceTest.php` | 8 | `use App\Services\PaystackGatewayClient;` |
| `tests/Unit/OrganizerPayoutAccountServiceTest.php` | 148 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/OrganizerPayoutAccountServiceTest.php` | 187 | `private function client(string $number, string $name): PaystackGatewayClient` |
| `tests/Unit/OrganizerPayoutAccountServiceTest.php` | 189 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaymentExceptionDisclosureTest.php` | 5 | `use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\PaystackController;` |
| `tests/Unit/PaymentExceptionDisclosureTest.php` | 6 | `use App\Services\ProductPaystackCheckoutService;` |
| `tests/Unit/PaymentExceptionDisclosureTest.php` | 16 | `public function test_product_paystack_initialization_does_not_disclose_provider_exception_text(): void` |
| `tests/Unit/PaymentExceptionDisclosureTest.php` | 19 | `$service = Mockery::mock(ProductPaystackCheckoutService::class);` |
| `tests/Unit/PaymentExceptionDisclosureTest.php` | 21 | `$this->app->instance(ProductPaystackCheckoutService::class, $service);` |
| `tests/Unit/PaymentExceptionDisclosureTest.php` | 25 | `$request = Request::create('/shop/paystack', 'POST');` |
| `tests/Unit/PaymentExceptionDisclosureTest.php` | 28 | `$controller = new class extends PaystackController` |
| `tests/Unit/PaymentExceptionDisclosureTest.php` | 41 | `'Paystack could not start the payment. Your cart is unchanged; please try again.',` |
| `tests/Unit/PaymentExceptionDisclosureTest.php` | 46 | `return $message === 'Product Paystack checkout initialization failed.'` |
| `tests/Unit/PaymentExceptionDisclosureTest.php` | 57 | `'Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php',` |
| `tests/Unit/PaymentGatewayCallbackBoundaryTest.php` | 50 | `config()->set('payments.production_ready_gateways', ['paystack', 'flutterwave']);` |
| `tests/Unit/PaymentGatewayCallbackBoundaryTest.php` | 66 | `config()->set('payments.production_ready_gateways', ['paystack', 'flutterwave']);` |
| `tests/Unit/PaymentGatewayCallbackBoundaryTest.php` | 73 | `'paystack'` |
| `tests/Unit/PaymentGatewayCallbackBoundaryTest.php` | 79 | `'paystack'` |
| `tests/Unit/PaymentGatewayCallbackBoundaryTest.php` | 150 | `'event_booking.paystack.notify' => 'payment.gateway:event,paystack',` |
| `tests/Unit/PaymentGatewayCallbackBoundaryTest.php` | 151 | `'event_booking.paystack.recovery' => 'payment.gateway:event,paystack',` |
| `tests/Unit/PaymentGatewayCallbackBoundaryTest.php` | 152 | `'event_booking.paystack.recovery.verify' => 'payment.gateway:event,paystack',` |
| `tests/Unit/PaymentGatewayCallbackBoundaryTest.php` | 156 | `'product_order.paystack.notify' => 'payment.gateway:shop,paystack',` |
| `tests/Unit/PaymentGatewayCallbackBoundaryTest.php` | 157 | `'product_order.paystack.recovery' => 'payment.gateway:shop,paystack',` |
| `tests/Unit/PaymentGatewayCallbackBoundaryTest.php` | 158 | `'product_order.paystack.recovery.verify' => 'payment.gateway:shop,paystack',` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 12 | `config()->set('payments.production_ready_gateways', [' Paystack ', 'flutterwave', 'PAYSTACK', '']);` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 15 | `['paystack', 'flutterwave'],` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 22 | `config()->set('payments.production_ready_gateways', ['paystack', 'flutterwave']);` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 26 | `(new PaymentGatewayPolicy())->unsafeEnabledGateways(['PAYSTACK', 'paypal', 'stripe', 'paypal'])` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 32 | `config()->set('payments.production_ready_gateways', ['paystack']);` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 37 | `$this->assertTrue($policy->canOfferOnline('paystack'));` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 41 | `public function test_production_shop_checkout_allows_only_hardened_paystack(): void` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 47 | `$this->assertSame(['paystack'], $policy->productionReadyShopGateways());` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 48 | `$this->assertTrue($policy->canOfferShopOnline('paystack'));` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 56 | `config()->set('payments.production_ready_gateways', ['paystack', 'flutterwave']);` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 61 | `$this->assertTrue($policy->canOfferOnline('paystack'));` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 62 | `$this->assertTrue($policy->canOfferShopOnline('paystack'));` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 70 | `config()->set('payments.production_ready_gateways', ['paystack']);` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 83 | `config()->set('payments.production_ready_gateways', ['paystack', 'flutterwave']);` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 95 | `config()->set('payments.production_ready_gateways', ['paystack', 'flutterwave']);` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 100 | `$this->assertTrue($policy->canOfferOnline('paystack'));` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 107 | `config()->set('payments.production_ready_gateways', ['paystack', 'paypal']);` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 114 | `$this->assertSame(['paystack'], $policy->productionReadyGateways());` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 121 | `config()->set('payments.production_ready_gateways', ['paystack', 'razorpay']);` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 127 | `$this->assertSame(['paystack'], $policy->productionReadyGateways());` |
| `tests/Unit/PaymentGatewayPolicyTest.php` | 128 | `$this->assertSame(['razorpay'], $policy->unsafeEnabledGateways(['paystack', 'razorpay']));` |
| `tests/Unit/PaymentIntentMonitorBoundedTest.php` | 48 | `'gateway' => 'paystack',` |
| `tests/Unit/PaymentReconciliationCommandOperationalSafetyTest.php` | 6 | `use App\Console\Commands\ReconcilePaystackPayments;` |
| `tests/Unit/PaymentReconciliationCommandOperationalSafetyTest.php` | 16 | `foreach ([new ReconcilePaystackPayments(), new ReconcileFlutterwavePayments()] as $command) {` |
| `tests/Unit/PaymentReconciliationCommandOperationalSafetyTest.php` | 29 | `$this->artisan('payments:reconcile-paystack', ['--date' => '2026-13-40'])` |
| `tests/Unit/PaymentRefundServiceTest.php` | 15 | `use App\Services\PaystackGatewayClient;` |
| `tests/Unit/PaymentRefundServiceTest.php` | 67 | `[$intent, $booking] = $this->payment(100, 'paystack', '778899', 7);` |
| `tests/Unit/PaymentRefundServiceTest.php` | 95 | `[$intent] = $this->payment(100, 'paystack', '778899', 7);` |
| `tests/Unit/PaymentRefundServiceTest.php` | 109 | `[$intent] = $this->payment(100, 'paystack', '778899', 7);` |
| `tests/Unit/PaymentRefundServiceTest.php` | 148 | `[$intent, $booking] = $this->payment(100, 'paystack', '778899', 7);` |
| `tests/Unit/PaymentRefundServiceTest.php` | 195 | `[$intent, $booking] = $this->payment(100, 'paystack', '778899', 7);` |
| `tests/Unit/PaymentRefundServiceTest.php` | 262 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaymentRefundServiceTest.php` | 283 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaymentRefundServiceTest.php` | 294 | `public function test_indeterminate_paystack_refund_is_recovered_by_original_transaction_identity(): void` |
| `tests/Unit/PaymentRefundServiceTest.php` | 296 | `[$intent, $booking] = $this->payment(100, 'paystack', '778899');` |
| `tests/Unit/PaymentRefundServiceTest.php` | 303 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaymentRefundServiceTest.php` | 315 | `$result = $service->recoverUnknownPaystackSubmission($refund, $client);` |
| `tests/Unit/PaymentRefundServiceTest.php` | 325 | `public function test_paystack_refund_reconciliation_rejects_wrong_provider_identity(): void` |
| `tests/Unit/PaymentRefundServiceTest.php` | 327 | `[$intent, $booking] = $this->payment(100, 'paystack', '778899');` |
| `tests/Unit/PaymentRefundServiceTest.php` | 352 | `[$intent, $booking] = $this->payment(100, 'paystack', '778899');` |
| `tests/Unit/PaymentRefundServiceTest.php` | 359 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaymentRefundServiceTest.php` | 368 | `$result = $service->recoverUnknownPaystackSubmission($refund, $client);` |
| `tests/Unit/PaymentRefundServiceTest.php` | 379 | `[$intent, $booking] = $this->payment(100, 'paystack', '778899');` |
| `tests/Unit/PaymentRefundServiceTest.php` | 410 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaymentRefundServiceTest.php` | 418 | `'transaction_reference' => 'paystack_ref_1',` |
| `tests/Unit/PaymentRefundServiceTest.php` | 424 | `$service->handleWebhook('refund.processed', $webhook);` |
| `tests/Unit/PaymentRefundServiceTest.php` | 425 | `$service->handleWebhook('refund.processed', $webhook);` |
| `tests/Unit/PaymentRefundServiceTest.php` | 526 | `string $gateway = 'paystack',` |
| `tests/Unit/PayoutEligibilityServiceTest.php` | 5 | `use App\Jobs\SubmitPaystackPayout;` |
| `tests/Unit/PayoutEligibilityServiceTest.php` | 37 | `config()->set('payouts.paystack_transfers_enabled', true);` |
| `tests/Unit/PayoutEligibilityServiceTest.php` | 80 | `Queue::assertPushed(SubmitPaystackPayout::class, 1);` |
| `tests/Unit/PayoutEligibilityServiceTest.php` | 213 | `'provider' => 'paystack',` |
| `tests/Unit/PayoutEligibilityServiceTest.php` | 232 | `'gateway' => 'paystack',` |
| `tests/Unit/PayoutProviderTest.php` | 62 | `$this->assertSame(980050, $data['amount'], 'Amounts are converted to kobo like Paystack.');` |
| `tests/Unit/PayoutProviderTest.php` | 73 | `$provider->initiateTransfer('RCP_paystack_code', 100, 'ref', 'reason', 'NGN');` |
| `tests/Unit/PayoutProviderTest.php` | 86 | `foreach (['sk_live', 'sk_test', 'secret_key', 'PAYSTACK_SECRET', 'api.paystack.co', 'monnify.com'] as $needle) {` |
| `tests/Unit/PayoutTransferServiceTest.php` | 5 | `use App\Console\Commands\ReconcilePaystackPayouts;` |
| `tests/Unit/PayoutTransferServiceTest.php` | 6 | `use App\Jobs\SubmitPaystackPayout;` |
| `tests/Unit/PayoutTransferServiceTest.php` | 14 | `use App\Services\PaystackGatewayClient;` |
| `tests/Unit/PayoutTransferServiceTest.php` | 43 | `config()->set('payouts.paystack_transfers_enabled', true);` |
| `tests/Unit/PayoutTransferServiceTest.php` | 69 | `Queue::assertPushed(SubmitPaystackPayout::class, 1);` |
| `tests/Unit/PayoutTransferServiceTest.php` | 70 | `Queue::assertPushed(SubmitPaystackPayout::class, function ($job) use ($first) {` |
| `tests/Unit/PayoutTransferServiceTest.php` | 130 | `Queue::assertNotPushed(SubmitPaystackPayout::class);` |
| `tests/Unit/PayoutTransferServiceTest.php` | 134 | `Queue::assertPushed(SubmitPaystackPayout::class,` |
| `tests/Unit/PayoutTransferServiceTest.php` | 135 | `fn (SubmitPaystackPayout $job): bool => $job->transferId === $transfer->id);` |
| `tests/Unit/PayoutTransferServiceTest.php` | 187 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PayoutTransferServiceTest.php` | 231 | `$service->handleWebhook('transfer.success', $payload);` |
| `tests/Unit/PayoutTransferServiceTest.php` | 232 | `$service->handleWebhook('transfer.success', $payload);` |
| `tests/Unit/PayoutTransferServiceTest.php` | 249 | `(new PayoutTransferService())->handleWebhook('transfer.success', [` |
| `tests/Unit/PayoutTransferServiceTest.php` | 268 | `(new PayoutTransferService())->handleWebhook('transfer.success', [` |
| `tests/Unit/PayoutTransferServiceTest.php` | 295 | `(new PayoutTransferService())->handleWebhook('transfer.failed', [` |
| `tests/Unit/PayoutTransferServiceTest.php` | 313 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PayoutTransferServiceTest.php` | 339 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PayoutTransferServiceTest.php` | 363 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PayoutTransferServiceTest.php` | 364 | `$client->shouldReceive('verifyTransfer')->twice()->andThrow(new RuntimeException('Paystack unavailable'));` |
| `tests/Unit/PayoutTransferServiceTest.php` | 379 | `'Paystack unavailable',` |
| `tests/Unit/PayoutTransferServiceTest.php` | 383 | `'Paystack unavailable',` |
| `tests/Unit/PayoutTransferServiceTest.php` | 396 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PayoutTransferServiceTest.php` | 421 | `$command = new ReconcilePaystackPayouts();` |
| `tests/Unit/PayoutTransferServiceTest.php` | 430 | `$this->artisan('payouts:reconcile-paystack', ['--limit' => 501])` |
| `tests/Unit/PayoutTransferServiceTest.php` | 437 | `$result = (new PayoutTransferService())->handleWebhook('transfer.success', [` |
| `tests/Unit/PayoutTransferServiceTest.php` | 449 | `(new PayoutTransferService())->handleWebhook('transfer.success', [` |
| `tests/Unit/PayoutTransferServiceTest.php` | 492 | `(new PayoutTransferService())->handleWebhook('transfer.success', [` |
| `tests/Unit/PayoutTransferServiceTest.php` | 556 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PayoutTransferServiceTest.php` | 571 | `private function payoutFixture(?string $recipientCode = null, string $provider = 'paystack'): array` |
| `tests/Unit/PaystackEventCheckoutServiceTest.php` | 6 | `use App\Services\PaystackEventCheckoutService;` |
| `tests/Unit/PaystackEventCheckoutServiceTest.php` | 7 | `use App\Services\PaystackGatewayClient;` |
| `tests/Unit/PaystackEventCheckoutServiceTest.php` | 17 | `class PaystackEventCheckoutServiceTest extends TestCase` |
| `tests/Unit/PaystackEventCheckoutServiceTest.php` | 53 | `public function test_it_persists_intent_before_calling_paystack_and_uses_same_reference(): void` |
| `tests/Unit/PaystackEventCheckoutServiceTest.php` | 56 | `'https://api.paystack.co/transaction/initialize' => function ($request) {` |
| `tests/Unit/PaystackEventCheckoutServiceTest.php` | 65 | `'authorization_url' => 'https://checkout.paystack.com/secure/event',` |
| `tests/Unit/PaystackEventCheckoutServiceTest.php` | 71 | `$intent = (new PaystackEventCheckoutService())->initialize(` |
| `tests/Unit/PaystackEventCheckoutServiceTest.php` | 73 | `new PaystackGatewayClient('test-secret')` |
| `tests/Unit/PaystackEventCheckoutServiceTest.php` | 78 | `$this->assertSame('https://checkout.paystack.com/secure/event', $intent->authorization_url);` |
| `tests/Unit/PaystackEventCheckoutServiceTest.php` | 86 | `'https://api.paystack.co/transaction/initialize' => Http::response([` |
| `tests/Unit/PaystackEventCheckoutServiceTest.php` | 92 | `(new PaystackEventCheckoutService())->initialize(` |
| `tests/Unit/PaystackEventCheckoutServiceTest.php` | 94 | `new PaystackGatewayClient('test-secret')` |
| `tests/Unit/PaystackEventCheckoutServiceTest.php` | 113 | `'https://api.paystack.co/transaction/initialize' => Http::response([` |
| `tests/Unit/PaystackEventCheckoutServiceTest.php` | 121 | `$intent = (new PaystackEventCheckoutService())->initialize(` |
| `tests/Unit/PaystackEventCheckoutServiceTest.php` | 123 | `new PaystackGatewayClient('test-secret')` |
| `tests/Unit/PaystackEventCheckoutServiceTest.php` | 132 | `public function test_event_paystack_controller_has_no_session_cache_or_blocking_http_fallback(): void` |
| `tests/Unit/PaystackEventCheckoutServiceTest.php` | 135 | `'Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php'` |
| `tests/Unit/PaystackEventCheckoutServiceTest.php` | 140 | `$this->assertStringNotContainsString('paystack_data_', $source);` |
| `tests/Unit/PaystackEventCheckoutServiceTest.php` | 141 | `$this->assertStringNotContainsString('paystack_event_', $source);` |
| `tests/Unit/PaystackEventCheckoutServiceTest.php` | 142 | `$this->assertStringNotContainsString('paystack_done_', $source);` |
| `tests/Unit/PaystackGatewayClientTest.php` | 6 | `use App\Services\PaystackGatewayClient;` |
| `tests/Unit/PaystackGatewayClientTest.php` | 11 | `class PaystackGatewayClientTest extends TestCase` |
| `tests/Unit/PaystackGatewayClientTest.php` | 16 | `'https://api.paystack.co/transaction/initialize' => Http::response([` |
| `tests/Unit/PaystackGatewayClientTest.php` | 20 | `'authorization_url' => 'https://checkout.paystack.com/example',` |
| `tests/Unit/PaystackGatewayClientTest.php` | 25 | `$payload = (new PaystackGatewayClient('test-secret'))->initializeTransaction(` |
| `tests/Unit/PaystackGatewayClientTest.php` | 26 | `'tica-shop-1', 'buyer@example.com', 10500.25, 'ngn', 'https://tica.test/product-order/paystack/notify',` |
| `tests/Unit/PaystackGatewayClientTest.php` | 33 | `&& $request->url() === 'https://api.paystack.co/transaction/initialize'` |
| `tests/Unit/PaystackGatewayClientTest.php` | 45 | `'https://api.paystack.co/transaction/initialize' => Http::response(['message' => 'timeout'], 503),` |
| `tests/Unit/PaystackGatewayClientTest.php` | 49 | `(new PaystackGatewayClient('test-secret'))->initializeTransaction(` |
| `tests/Unit/PaystackGatewayClientTest.php` | 54 | `public function test_payment_initialization_rejects_checkout_urls_outside_paystack(): void` |
| `tests/Unit/PaystackGatewayClientTest.php` | 57 | `'https://api.paystack.co/transaction/initialize' => Http::response([` |
| `tests/Unit/PaystackGatewayClientTest.php` | 61 | `'authorization_url' => 'https://checkout.paystack.example/phishing',` |
| `tests/Unit/PaystackGatewayClientTest.php` | 67 | `(new PaystackGatewayClient('test-secret'))->initializeTransaction(` |
| `tests/Unit/PaystackGatewayClientTest.php` | 75 | `'https://api.paystack.co/refund*' => Http::response([` |
| `tests/Unit/PaystackGatewayClientTest.php` | 87 | `$payload = (new PaystackGatewayClient('test-secret'))` |
| `tests/Unit/PaystackGatewayClientTest.php` | 93 | `&& str_starts_with($request->url(), 'https://api.paystack.co/refund?')` |
| `tests/Unit/PaystackGatewayClientTest.php` | 103 | `'https://api.paystack.co/transfer' => Http::response([` |
| `tests/Unit/PaystackGatewayClientTest.php` | 109 | `$payload = (new PaystackGatewayClient('test-secret'))->initiateTransfer(` |
| `tests/Unit/PaystackGatewayClientTest.php` | 116 | `&& $request->url() === 'https://api.paystack.co/transfer'` |
| `tests/Unit/PaystackGatewayClientTest.php` | 129 | `'https://api.paystack.co/transfer' => Http::response(['message' => 'upstream timeout'], 503),` |
| `tests/Unit/PaystackGatewayClientTest.php` | 133 | `(new PaystackGatewayClient('test-secret'))->initiateTransfer(` |
| `tests/Unit/PaystackGatewayClientTest.php` | 141 | `'https://api.paystack.co/transfer' => Http::response([` |
| `tests/Unit/PaystackGatewayClientTest.php` | 147 | `(new PaystackGatewayClient('test-secret'))->initiateTransfer(` |
| `tests/Unit/PaystackPaymentRecoveryServiceTest.php` | 6 | `use App\Services\PaystackGatewayClient;` |
| `tests/Unit/PaystackPaymentRecoveryServiceTest.php` | 7 | `use App\Services\PaystackPaymentRecoveryService;` |
| `tests/Unit/PaystackPaymentRecoveryServiceTest.php` | 14 | `class PaystackPaymentRecoveryServiceTest extends TestCase` |
| `tests/Unit/PaystackPaymentRecoveryServiceTest.php` | 32 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaystackPaymentRecoveryServiceTest.php` | 36 | `$outcome = (new PaystackPaymentRecoveryService())->attempt(` |
| `tests/Unit/PaystackPaymentRecoveryServiceTest.php` | 54 | `$outcome = (new PaystackPaymentRecoveryService())->attempt(` |
| `tests/Unit/PaystackPaymentRecoveryServiceTest.php` | 74 | `$outcome = (new PaystackPaymentRecoveryService())->attempt(` |
| `tests/Unit/PaystackPaymentRecoveryServiceTest.php` | 102 | `$outcome = (new PaystackPaymentRecoveryService())->attempt(` |
| `tests/Unit/PaystackPaymentRecoveryServiceTest.php` | 124 | `$outcome = (new PaystackPaymentRecoveryService())->attempt(` |
| `tests/Unit/PaystackPaymentRecoveryServiceTest.php` | 142 | `'gateway' => 'paystack',` |
| `tests/Unit/PaystackPaymentRecoveryServiceTest.php` | 160 | `private function clientReturning(string $reference, array $data): PaystackGatewayClient` |
| `tests/Unit/PaystackPaymentRecoveryServiceTest.php` | 162 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaystackPaymentVerifierTest.php` | 5 | `use App\Services\PaystackPaymentVerifier;` |
| `tests/Unit/PaystackPaymentVerifierTest.php` | 8 | `class PaystackPaymentVerifierTest extends TestCase` |
| `tests/Unit/PaystackPaymentVerifierTest.php` | 19 | `$this->assertSame([], PaystackPaymentVerifier::errors([` |
| `tests/Unit/PaystackPaymentVerifierTest.php` | 34 | `], PaystackPaymentVerifier::errors([` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 8 | `use App\Services\PaystackGatewayClient;` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 9 | `use App\Services\PaystackReconciliationService;` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 19 | `class PaystackReconciliationServiceTest extends TestCase` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 37 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 47 | `$run = (new PaystackReconciliationService())->reconcile(CarbonImmutable::today(), $client);` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 64 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 80 | `$run = (new PaystackReconciliationService())->reconcile(CarbonImmutable::today(), $client);` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 92 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 99 | `$run = (new PaystackReconciliationService())->reconcile(CarbonImmutable::today(), $client);` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 108 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 110 | `->andThrow(new RuntimeException('sensitive Paystack provider response', 503));` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 113 | `return str_starts_with($key, 'paystack-reconciliation-failed:')` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 114 | `&& $title === 'Paystack reconciliation failed.'` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 121 | `$run = (new PaystackReconciliationService())->reconcile(CarbonImmutable::today(), $client);` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 124 | `$this->assertStringContainsString('sensitive Paystack provider response', (string) $run->last_error);` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 126 | `'sensitive Paystack provider response',` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 135 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 142 | `$run = (new PaystackReconciliationService())->reconcile(CarbonImmutable::today(), $client);` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 158 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 165 | `$run = (new PaystackReconciliationService())->reconcile(CarbonImmutable::today(), $client);` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 182 | `'gateway' => 'paystack', 'reference' => $reference, 'event_id' => 5,` |
| `tests/Unit/PaystackReconciliationServiceTest.php` | 191 | `'gateway' => 'paystack',` |
| `tests/Unit/PaystackSettlementCommandIsolationTest.php` | 5 | `use App\Console\Commands\ReconcilePaystackSettlements;` |
| `tests/Unit/PaystackSettlementCommandIsolationTest.php` | 11 | `class PaystackSettlementCommandIsolationTest extends TestCase` |
| `tests/Unit/PaystackSettlementCommandIsolationTest.php` | 15 | `$command = new ReconcilePaystackSettlements();` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 8 | `use App\Services\PaystackGatewayClient;` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 9 | `use App\Services\PaystackSettlementReconciliationService;` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 19 | `class PaystackSettlementReconciliationServiceTest extends TestCase` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 37 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 48 | `$settlements = (new PaystackSettlementReconciliationService())->reconcile(` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 69 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 88 | `$settlement = (new PaystackSettlementReconciliationService())->reconcile(` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 102 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 113 | `$settlement = (new PaystackSettlementReconciliationService())->reconcile(` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 127 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 134 | `(new PaystackSettlementReconciliationService())->reconcile(` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 148 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 154 | `return $key === 'paystack-settlement-failed:3001'` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 155 | `&& $title === 'Paystack settlement reconciliation failed.'` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 162 | `$settlement = (new PaystackSettlementReconciliationService())->reconcile(` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 176 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 181 | `return str_starts_with($key, 'paystack-settlement-list-failed:')` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 182 | `&& $title === 'Paystack settlement listing failed.'` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 190 | `(new PaystackSettlementReconciliationService())->reconcile(` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 204 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 212 | `$settlement = (new PaystackSettlementReconciliationService())->reconcile(` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 226 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 233 | `$service = new PaystackSettlementReconciliationService();` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 246 | `$client = Mockery::mock(PaystackGatewayClient::class);` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 254 | `$settlement = (new PaystackSettlementReconciliationService())->reconcile(` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 267 | `'gateway' => 'paystack', 'reference' => $reference, 'event_id' => 5,` |
| `tests/Unit/PaystackSettlementReconciliationServiceTest.php` | 275 | `'gateway' => 'paystack',` |
| `tests/Unit/PlatformEarningMutationBoundaryTest.php` | 55 | `app_path('Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php'),` |
| `tests/Unit/PlatformEarningMutationBoundaryTest.php` | 57 | `app_path('Http/Controllers/FrontEnd/Shop/PaymentGateway/PaystackController.php'),` |
| `tests/Unit/ProductOrderAccountingServiceTest.php` | 160 | `['gateway' => 'paystack', 'gateway_reference' => 'other-reference', 'message' => 'conflicting gateway identity'],` |
| `tests/Unit/ProductOrderDataExportTest.php` | 48 | `'payment_method' => 'paystack', 'payment_status' => 'completed',` |
| `tests/Unit/ProductOrderDataExportTest.php` | 54 | `$this->assertStringNotContainsString('paystack', (string) DB::table('data_exports')->value('filters'));` |
| `tests/Unit/ProductOrderDataExportTest.php` | 150 | `'currency_symbol' => '₦', 'method' => 'paystack', 'payment_status' => 'completed',` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 13 | `use App\Services\PaystackGatewayClient;` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 70 | `$this->assertDatabaseHas('audit_logs', ['action' => 'product_refund.processed']);` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 182 | `public function test_paystack_refund_changes_finance_only_after_provider_confirms_processed(): void` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 184 | `[$order, $product, $refund, $service] = $this->pendingPaystackRefund();` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 208 | `public function test_paystack_refund_identity_mismatch_cannot_change_finance_or_inventory(): void` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 210 | `[$order, $product, $refund, $service] = $this->pendingPaystackRefund();` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 230 | `[$order, $product, $refund, $service] = $this->pendingPaystackRefund();` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 248 | `public function test_unknown_paystack_refund_submission_is_recovered_by_provider_transaction_identity(): void` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 250 | `[$order, $product, $refund, $service] = $this->pendingPaystackRefund();` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 259 | `'https://api.paystack.co/refund*' => Http::response([` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 273 | `new PaystackGatewayClient('test-secret')` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 284 | `public function test_ambiguous_paystack_refund_recovery_fails_to_manual_attention(): void` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 286 | `[$order, $product, $refund, $service] = $this->pendingPaystackRefund();` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 297 | `'https://api.paystack.co/refund*' => Http::response([` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 305 | `new PaystackGatewayClient('test-secret')` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 315 | `private function pendingPaystackRefund(): array` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 318 | `$order->forceFill(['method' => 'Paystack', 'gateway_type' => 'online', 'tnxid' => 'tica-shop-refund'])->save();` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 321 | `'gateway' => 'paystack',` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 323 | `'payment_method' => 'Paystack',` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 327 | `'gateway' => 'paystack', 'reference' => 'tica-shop-refund',` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 333 | `'https://api.paystack.co/refund' => Http::response([` |
| `tests/Unit/ProductOrderRefundServiceTest.php` | 339 | `$refund = $service->submit($refund, new PaystackGatewayClient('test-secret'));` |
| `tests/Unit/ProductPaystackCheckoutServiceTest.php` | 5 | `use App\Services\PaystackGatewayClient;` |
| `tests/Unit/ProductPaystackCheckoutServiceTest.php` | 6 | `use App\Services\ProductPaystackCheckoutService;` |
| `tests/Unit/ProductPaystackCheckoutServiceTest.php` | 13 | `class ProductPaystackCheckoutServiceTest extends TestCase` |
| `tests/Unit/ProductPaystackCheckoutServiceTest.php` | 46 | `'https://api.paystack.co/transaction/initialize' => function ($request) {` |
| `tests/Unit/ProductPaystackCheckoutServiceTest.php` | 51 | `'authorization_url' => 'https://checkout.paystack.com/secure-token',` |
| `tests/Unit/ProductPaystackCheckoutServiceTest.php` | 57 | `$intent = app(ProductPaystackCheckoutService::class)->start(` |
| `tests/Unit/ProductPaystackCheckoutServiceTest.php` | 62 | `new PaystackGatewayClient('test-secret')` |
| `tests/Unit/ProductPaystackCheckoutServiceTest.php` | 85 | `'https://api.paystack.co/transaction/initialize' => Http::response([` |
| `tests/Unit/ProductPaystackCheckoutServiceTest.php` | 91 | `app(ProductPaystackCheckoutService::class)->start(` |
| `tests/Unit/ProductPaystackCheckoutServiceTest.php` | 93 | `new PaystackGatewayClient('test-secret')` |
| `tests/Unit/ProductPaystackCheckoutServiceTest.php` | 95 | `$this->fail('Expected Paystack rejection to be raised.');` |
| `tests/Unit/ProductPaystackPaymentServiceTest.php` | 8 | `use App\Services\ProductPaystackPaymentService;` |
| `tests/Unit/ProductPaystackPaymentServiceTest.php` | 15 | `class ProductPaystackPaymentServiceTest extends TestCase` |
| `tests/Unit/ProductPaystackPaymentServiceTest.php` | 41 | `$service = app(ProductPaystackPaymentService::class);` |
| `tests/Unit/ProductPaystackPaymentServiceTest.php` | 72 | `$service = app(ProductPaystackPaymentService::class);` |
| `tests/Unit/ProductPaystackPaymentServiceTest.php` | 87 | `'method' => 'Paystack', 'gateway_type' => 'online',` |
| `tests/Unit/ProductPaystackPaymentServiceTest.php` | 94 | `'gateway' => 'paystack', 'reference' => 'tica-shop-1',` |
| `tests/Unit/ProductPaystackPaymentVerifierTest.php` | 6 | `use App\Services\ProductPaystackPaymentVerifier;` |
| `tests/Unit/ProductPaystackPaymentVerifierTest.php` | 9 | `class ProductPaystackPaymentVerifierTest extends TestCase` |
| `tests/Unit/ProductPaystackPaymentVerifierTest.php` | 18 | `$this->assertSame([], ProductPaystackPaymentVerifier::errors([` |
| `tests/Unit/ProductPaystackPaymentVerifierTest.php` | 30 | `$errors = ProductPaystackPaymentVerifier::errors([` |
| `tests/Unit/ReconciliationDataExportTest.php` | 40 | `$this->insertRun(1, 'paystack', 'mismatched', '2026-11-03');` |
| `tests/Unit/ReconciliationDataExportTest.php` | 44 | `'gateway'=>'paystack', 'status'=>'mismatched', 'date'=>'2026-11-03',` |
| `tests/Unit/ReconciliationDataExportTest.php` | 50 | `$this->assertStringNotContainsString('paystack', (string) DB::table('data_exports')->value('filters'));` |
| `tests/Unit/ReconciliationDataExportTest.php` | 59 | `$this->insertRun(1, '=paystack', 'mismatched', '2026-11-03');` |
| `tests/Unit/ReconciliationDataExportTest.php` | 67 | `$this->assertStringContainsString("'=paystack", $runCsv);` |
| `tests/Unit/ReconciliationDataExportTest.php` | 72 | `$this->insertSettlement(1, '=paystack', '+SETTLEMENT', 'mismatched', '2026-11-03 09:00:00');` |
| `tests/Unit/ReconciliationDataExportTest.php` | 77 | `$this->assertStringContainsString("'=paystack", $settlementCsv);` |
| `tests/Unit/ReconciliationDataExportTest.php` | 88 | `$this->insertRun(1,'paystack','mismatched','2026-11-03');` |
| `tests/Unit/ReconciliationDataExportTest.php` | 101 | `$this->insertSettlement(1,'paystack','SET-1','mismatched','2026-11-03 09:00:00');` |
| `tests/Unit/ReconciliationDataExportTest.php` | 198 | `DB::table('payment_reconciliation_entries')->insert(['id'=>$id,'run_id'=>$run,'payment_intent_id'=>11,'booking_id'=>22,'gateway'=>'paystack','reference'=>$reference,'result'=>$result,'expected_amount'=>10000,'gateway_...` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 43 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack', 'flutterwave']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 80 | `$checks = collect((new ReleaseReadinessService())->evaluate(['paystack']));` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 87 | `$checks = collect((new ReleaseReadinessService())->evaluate(['paystack']));` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 97 | `$missing = collect((new ReleaseReadinessService())->evaluate(['paystack']))` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 103 | `$insecure = collect((new ReleaseReadinessService())->evaluate(['paystack']))` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 113 | `$disabled = collect((new ReleaseReadinessService())->evaluate(['paystack']))` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 119 | `$insecure = collect((new ReleaseReadinessService())->evaluate(['paystack']))` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 129 | `$incomplete = collect((new ReleaseReadinessService())->evaluate(['paystack']))` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 136 | `$stale = collect((new ReleaseReadinessService())->evaluate(['paystack']))` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 141 | `$ready = collect((new ReleaseReadinessService())->evaluate(['paystack']))` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 151 | `$disabled = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 162 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack', 'paypal']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 172 | `config()->set('payments.production_ready_gateways', ['paystack', 'paypal']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 174 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack', 'paypal']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 183 | `config()->set('payments.production_ready_gateways', ['paystack', 'razorpay']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 185 | `$contract = collect((new ReleaseReadinessService())->evaluate(['paystack', 'razorpay']))` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 197 | `$check = collect((new ReleaseReadinessService())->evaluate(['paystack']))` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 209 | `$incomplete = collect((new ReleaseReadinessService())->evaluate(['paystack']))` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 216 | `$stale = collect((new ReleaseReadinessService())->evaluate(['paystack']))` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 222 | `$paystackOnly = collect((new ReleaseReadinessService())->evaluate(['paystack']))` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 224 | `$bothGateways = collect((new ReleaseReadinessService())->evaluate(['paystack', 'flutterwave']))` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 226 | `$this->assertSame('pass', $paystackOnly['status']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 234 | `$checks = (new ReleaseReadinessService('8.2.20', '12.0.0'))->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 669 | `public function test_paystack_payouts_cannot_be_enabled_without_paystack_checkout(): void` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 672 | `config()->set('payouts.paystack_transfers_enabled', true);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 677 | `->firstWhere('id', 'payouts.paystack_gateway')['status']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 685 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 694 | `config()->set('payouts.paystack_transfers_enabled', true);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 716 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 722 | `public function test_paystack_payouts_require_a_finance_alert_recipient(): void` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 725 | `config()->set('payouts.paystack_transfers_enabled', true);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 728 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 734 | `public function test_paystack_payouts_require_a_current_production_like_drill(): void` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 737 | `config()->set('payouts.paystack_transfers_enabled', true);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 741 | `$missing = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 748 | `$current = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 760 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 773 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 784 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 795 | `$stale = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 800 | `$future = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 809 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 822 | `$missing = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 828 | `$stale = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 837 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 867 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 877 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 888 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 893 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 902 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 917 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 928 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 938 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 951 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 962 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 973 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 984 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 995 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 1011 | `$checks = (new ReleaseReadinessService())->evaluate(['paystack']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 1067 | `config()->set('payments.production_ready_gateways', ['paystack', 'flutterwave']);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 1073 | `config()->set('payments.drill_paystack_verified', true);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 1075 | `config()->set('payments.drill_shop_paystack_verified', true);` |
| `tests/Unit/ReleaseReadinessServiceTest.php` | 1082 | `config()->set('payouts.paystack_transfers_enabled', false);` |
| `tests/Unit/ShopCheckoutSessionServiceTest.php` | 76 | `if (in_array(basename($path), ['OfflineController.php', 'PaystackController.php'], true)` |
| `tests/Unit/ShopGuestCheckoutBoundaryTest.php` | 111 | `'gateway' => 'paystack',` |
| `tests/Unit/TicaPromotionGovernanceTest.php` | 113 | `$gateway = file_get_contents(app_path('Services/TicaPromotionPaystackService.php'));` |
| `tests/Unit/TicaPromotionGovernanceTest.php` | 115 | `$this->assertStringContainsString('PaystackPaymentVerifier::errors', $gateway);` |
| `tests/Unit/TicaPromotionGovernanceTest.php` | 116 | `$this->assertStringContainsString("['gateway' => 'paystack', 'gateway_reference' => \$lockedIntent->reference]", $gateway);` |
| `tests/Unit/TransactionalMailTemplateTest.php` | 144 | `'admin_payment_webhook_failure' => 'app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php',` |
| `tests/Unit/TransactionDataExportTest.php` | 126 | `DB::table('transactions')->insert(['id'=>$id,'transcation_id'=>$reference,'gateway'=>'=paystack','gateway_reference'=>'+GATEWAY-REF'.$id,'booking_id'=>'99','transcation_type'=>$type,'customer_id'=>5,'organizer_id'=>$o...` |
| `tests/Unit/TransactionLedgerServiceTest.php` | 37 | `$first = $service->recordGatewaySale($booking, 'Paystack', 'pay_ref_81');` |
| `tests/Unit/TransactionLedgerServiceTest.php` | 38 | `$second = $service->recordGatewaySale($booking, 'paystack', 'pay_ref_81');` |
| `tests/Unit/TransactionLedgerServiceTest.php` | 42 | `$this->assertSame('paystack', $first->gateway);` |
| `tests/Unit/TransactionLedgerServiceTest.php` | 52 | `$service->recordGatewaySale($this->booking(81, $organizer->id, 25000), 'paystack', 'pay_ref_81');` |
| `tests/Unit/TransactionLedgerServiceTest.php` | 55 | `$service->recordGatewaySale($this->booking(82, $organizer->id, 25000), 'paystack', 'pay_ref_81');` |
| `tests/Unit/TransactionLedgerServiceTest.php` | 63 | `$service->recordGatewaySale($booking, 'paystack', 'pay_ref_evidence');` |
| `tests/Unit/TransactionLedgerServiceTest.php` | 69 | `$service->recordGatewaySale($booking, 'paystack', 'pay_ref_evidence');` |
| `tests/Unit/TransactionLedgerServiceTest.php` | 78 | `$service->recordGatewaySale($booking, 'paystack', 'shared-ref');` |
| `tests/Unit/TransactionLedgerServiceTest.php` | 91 | `$first = $service->apply($booking, 'Paystack', 'pay_ref_accounting');` |
| `tests/Unit/TransactionLedgerServiceTest.php` | 92 | `$second = $service->apply($booking, 'paystack', 'pay_ref_accounting');` |
| `tests/Unit/TransactionLedgerServiceTest.php` | 109 | `$service->apply($this->booking(81, $organizer->id, 25000), 'paystack', 'fixed-reference');` |
| `tests/Unit/TransactionLedgerServiceTest.php` | 112 | `$service->apply($this->booking(82, $organizer->id, 30000), 'paystack', 'fixed-reference');` |
| `tests/Unit/TransactionLedgerServiceTest.php` | 143 | `'paymentMethod' => 'Paystack',` |
| `tests/Unit/WebhookObservabilityTest.php` | 42 | `'event' => 'charge.success',` |
| `tests/Unit/WebhookObservabilityTest.php` | 45 | `$request = Request::create('/paystack/webhook', 'POST', [], [], [], [` |
| `tests/Unit/WebhookObservabilityTest.php` | 46 | `'CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => 'signature-present',` |
| `tests/Unit/WebhookObservabilityTest.php` | 53 | `}, 'paystack');` |
| `tests/Unit/WebhookObservabilityTest.php` | 57 | `$this->assertSame('charge.success', $receipt->event_type);` |
| `tests/Unit/WebhookObservabilityTest.php` | 93 | `'gateway' => 'paystack', 'event_type' => 'charge.success',` |
| `tests/Unit/WebhookObservabilityTest.php` | 101 | `$key === 'webhook-health:paystack' && $context['counts']['failed'] === 2` |
| `tests/Unit/WebhookObservabilityTest.php` | 117 | `'gateway' => 'paystack',` |
| `tests/Unit/WebhookObservabilityTest.php` | 118 | `'event_type' => 'charge.success',` |
| `tests/Unit/WithdrawalDecisionServiceTest.php` | 41 | `$paid = $service->markPaid($withdrawal->id, 'PAYSTACK-TRF-1001', 8);` |
| `tests/Unit/WithdrawalDecisionServiceTest.php` | 43 | `$this->assertSame('PAYSTACK-TRF-1001', $paid->payout_reference);` |
| `tests/Unit/WithdrawalServiceTest.php` | 128 | `'provider' => 'paystack',` |

## Discovery constraints

- Route discovery used dummy `pk_test_` / `sk_test_` values and an in-memory SQLite connection.
- No browser or HTTP request was sent to production, Supabase, Paystack, Vercel, or another shared service.
- Runtime verification, migrations, seeding, form submission checks, and defect remediation are tracked separately in `QA/QA_REPORT.md`.
