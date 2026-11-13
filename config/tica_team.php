<?php

/*
| Organiser team roles: the one place that says who can do what.
|
| Team members sign in for the organiser's account. Every organiser page,
| dashboard section and /tica-api/organizer action maps to one permission
| below; a member can only reach it if their role holds that permission.
| Anything not mapped is owner-only (deny by default). Gate staff don't sign
| in here at all: they use the scanner login, limited to their events.
*/

return [
  'roles' => [
    'owner' => ['*'],
    'manager' => [
      'overview.view', 'events.view', 'events.manage', 'orders.view', 'orders.manage', 'attendees.message',
      'checkin.run', 'promote.manage', 'support.use',
    ],
    'finance' => [
      'overview.view', 'events.view', 'orders.view', 'orders.export', 'reports.view', 'refunds.manage',
      'payouts.view', 'withdrawals.request', 'support.use',
    ],
  ],

  /* Plain-language descriptions shown on the Team page. */
  'role_summaries' => [
    'owner' => [
      'can' => ['Everything, including payouts, the bank account, the team and account settings'],
      'cannot' => [],
    ],
    'manager' => [
      'can' => ['Create and edit events and tickets', 'See orders and attendees', 'Message attendees and resend tickets', 'Run gate check-in', 'Use Promote: codes, links and pixels'],
      'cannot' => ['See payouts or request withdrawals', 'Change the bank account', 'Manage the team', 'Change account settings'],
    ],
    'finance' => [
      'can' => ['See sales and revenue reports', 'Export orders', 'Issue refunds', 'Request withdrawals', 'See payout history'],
      'cannot' => ['Create, edit or delete events', 'Message attendees', 'Add or change the bank account', 'Manage the team'],
    ],
    'gate' => [
      'can' => ['Check tickets at the gate, for the events they\'re given'],
      'cannot' => ['Anything else. They sign in to the scanner only.'],
    ],
  ],

  /* Reachable by any signed-in team member. */
  'common_routes' => [
    'organizer.dashboard', 'organizer.change_theme', 'organizer.logout', 'organizer.pwa',
  ],
  'common_actions' => [
    'PublicAssetController@show',
  ],

  /* Legacy organiser pages, by route name (wildcards allowed). */
  'routes' => [
    'reports.view' => ['organizer.monthly_income', 'organizer.transcation', 'organizer.finance.index', 'organizer.event_booking.report'],
    'orders.export' => [
      'organizer.transaction_exports.*', 'organizer.finance.export', 'organizer.event_bookings.export',
      'organizer.data_exports.index', 'organizer.data_exports.download',
    ],
    'events.manage' => [
      'choose-event-type', 'organizer.add.event.event', 'organizer.event_management.*', 'organizer.event.imagesstore',
      'organizer.event.imagermv', 'organizer.event.imgdbrmv', 'organizer.event.delete.date', 'organizer.event.images',
      'organizer.event.update', 'organizer.event.preview', 'organizer.event.ticket', 'organizer.event.add.ticket',
      'organizer.event.edit.ticket', 'organizer.ticket_management.*', 'organizer.event_cancellations.store',
      'organizer.event.registrations.*', 'organizer.event.guest-list.*',
    ],
    'orders.view' => ['organizer.event.booking', 'organizer.event_booking.details', 'organizer.event_booking.invoice', 'organizer.event_booking.attachment'],
    'orders.manage' => ['organizer.event_booking.resend-tickets', 'organizer.event_booking.ticket.reissue', 'organizer.event_booking.ticket.revoke'],
    'refunds.manage' => ['organizer.refunds.store', 'organizer.refund_disputes.store', 'organizer.event_booking.update_payment_status'],
    'payouts.view' => ['organizer.withdraw', 'organizer.withdraw_exports.*'],
    'withdrawals.request' => ['organizer.withdraw.create', 'organizer.withdraw.send-request', 'organizer.withdraw.disputes.store', 'organizer.witdraw.*'],
    'checkin.run' => ['organizer.scanner.devices.index', 'organizer.scanner.devices.store', 'organizer.scanner.devices.revoke', 'organizer.check_in_ledger_exports.*'],
    'support.use' => ['organizer.support_ticket.*', 'organizer.support_tickets*'],
    // Owner only (listed so the matrix test covers them): bookings deletion,
    // profile, password, identity and bank, scanner staff, export schedules.
    'owner.only' => [
      'organizer.event_booking.delete', 'organizer.event_booking.bulk_delete', 'organizer.edit.profile', 'organizer.update_profile',
      'organizer.change.password', 'organizer.update_password', 'organizer.kyc.*', 'organizer.scanner.staff.*',
      'organizer.data_export_schedules.*',
    ],
  ],

  /* New dashboard pages, by page and section. */
  'pages' => [
    'tica.create' => 'events.manage',
    'tica.edit' => 'events.manage',
    'tica.checkin' => 'checkin.run',
  ],
  'sections' => [
    'overview' => 'overview.view',
    'events' => 'events.view',
    'orders' => 'orders.view',
    'promote' => 'promote.manage',
    'payouts' => 'payouts.view',
    'team' => 'team.manage',
    'settings' => 'settings.manage',
  ],

  /* /tica-api/organizer actions, by Controller@method. */
  'actions' => [
    'TicaOrganizerController@storeEvent' => 'events.manage',
    'TicaOrganizerController@updateOrganizerEvent' => 'events.manage',
    'TicaWorkspaceController@updateEvent' => 'events.manage',
    'TicaWorkspaceController@changeDate' => 'events.manage',
    'TicaWorkspaceController@cancel' => 'events.manage',
    'TicaWorkspaceController@guest' => 'events.manage',
    'TicaWorkspaceController@attendees' => 'orders.view',
    'TicaWorkspaceController@attendeesCsv' => 'orders.export',
    'TicaWorkspaceController@resend' => 'orders.manage',
    'TicaWorkspaceController@refund' => 'refunds.manage',
    'TicaWorkspaceController@message' => 'attendees.message',
    'TicaWorkspaceController@banks' => 'bank.manage',
    'TicaWorkspaceController@resolveBank' => 'bank.manage',
    'TicaWorkspaceController@saveBank' => 'bank.manage',
    'TicaWorkspaceController@payoutSchedule' => 'bank.manage',
    'TicaWorkspaceController@withdraw' => 'withdrawals.request',
    'TicaWorkspaceController@storeCode' => 'promote.manage',
    'TicaWorkspaceController@storeLink' => 'promote.manage',
    'TicaWorkspaceController@storePromotion' => 'promote.manage',
    'TicaWorkspaceController@storePixel' => 'promote.manage',
    'TicaWorkspaceController@payPromotion' => 'promote.pay',
    'TicaWorkspaceController@cancelPromotion' => 'promote.pay',
    'TicaWorkspaceController@invite' => 'team.manage',
    'TicaWorkspaceController@removeMember' => 'team.manage',
    'TicaWorkspaceController@changeMemberRole' => 'team.manage',
    'TwoFactorController@start' => 'settings.manage',
    'TwoFactorController@confirm' => 'settings.manage',
    'TwoFactorController@disable' => 'settings.manage',
    'TwoFactorController@regenerate' => 'settings.manage',
    'TicaWorkspaceController@profile' => 'settings.manage',
    'TicaWorkspaceController@password' => 'settings.manage',
    'TicaWorkspaceController@prefs' => 'settings.manage',
  ],
];
