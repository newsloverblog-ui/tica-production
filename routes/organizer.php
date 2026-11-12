<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User Interface Routes
|--------------------------------------------------------------------------
*/

Route::get('organizers/email/verify/{organizer}', 'BackEnd\Organizer\OrganizerController@confirm_email')
  ->middleware('signed', 'throttle:authentication')
  ->name('organizer.email.verify');

Route::get('/organizer/payout-account/{account}/lock', 'BackEnd\Organizer\OrganizerKycController@confirmPayoutLock')
  ->middleware(['signed', 'throttle:authentication'])
  ->name('organizer.payout-account.lock.confirm');
Route::post('/organizer/payout-account/{account}/lock', 'BackEnd\Organizer\OrganizerKycController@lockPayoutAccount')
  ->middleware(['signed', 'throttle:authentication'])
  ->name('organizer.payout-account.lock');

Route::prefix('/scanner')->group(function () {
  Route::get('/login', 'BackEnd\Organizer\ScannerStaffController@loginForm')->middleware('guest:scanner')->name('scanner.login');
  Route::post('/login', 'BackEnd\Organizer\ScannerStaffController@login')->middleware('guest:scanner', 'throttle:authentication')->name('scanner.authentication');
  Route::post('/logout', 'BackEnd\Organizer\ScannerStaffController@logout')->middleware('auth:scanner')->name('scanner.logout');

  Route::middleware('scanner.access')->group(function () {
    Route::get('/workspace', 'BackEnd\Organizer\ScannerStaffController@workspace')->name('scanner.workspace');
    Route::get('/device/current', 'BackEnd\Organizer\ScannerDeviceController@current')->middleware('scanner.device')->name('organizer.scanner.devices.current');
    Route::post('/check-qrcode', 'BackEnd\Organizer\OrganizerController@check_qrcode')->middleware('scanner.device', 'throttle:scanner')->name('check-qrcode');
    Route::post('/ticket-lookup', 'BackEnd\Organizer\OrganizerController@ticketLookup')->middleware('scanner.device', 'throttle:scanner')->name('organizer.scanner.lookup');
    Route::post('/manual-check-in', 'BackEnd\Organizer\OrganizerController@manualCheckIn')->middleware('scanner.device', 'throttle:scanner')->name('organizer.scanner.manual-check-in');
    Route::get('/offline/events', 'BackEnd\Organizer\ScannerOfflineController@events')->middleware('scanner.device')->name('organizer.scanner.offline.events');
    Route::get('/events/{event}/metrics', 'BackEnd\Organizer\ScannerMetricsController@show')->middleware('scanner.device')->name('organizer.scanner.metrics.show');
    Route::post('/offline/manifests', 'BackEnd\Organizer\ScannerOfflineController@store')->middleware('scanner.device', 'throttle:scanner')->name('organizer.scanner.offline.manifests.store');
    Route::post('/offline/sync', 'BackEnd\Organizer\ScannerOfflineController@sync')->middleware('scanner.device', 'throttle:scanner')->name('organizer.scanner.offline.sync');
    Route::get('/conflicts', 'BackEnd\Organizer\ScannerConflictController@index')->middleware('scanner.supervisor')->name('organizer.scanner.conflicts.index');
    Route::post('/conflicts/{conflict}/resolve', 'BackEnd\Organizer\ScannerConflictController@resolve')->middleware('scanner.supervisor')->name('organizer.scanner.conflicts.resolve');
    Route::post('/events/{event}/check-in-ledger/export', 'BackEnd\Organizer\CheckInLedgerExportController@store')->middleware('scanner.supervisor')->name('organizer.scanner.check_in_ledger_exports.store');
    Route::get('/check-in-ledger/exports', 'BackEnd\Organizer\CheckInLedgerExportController@index')->middleware('scanner.supervisor')->name('organizer.scanner.check_in_ledger_exports.index');
    Route::get('/check-in-ledger/exports/{export}/download', 'BackEnd\Organizer\CheckInLedgerExportController@download')->middleware(['scanner.supervisor', 'signed'])->name('organizer.scanner.check_in_ledger_exports.download');
  });
});

Route::prefix('/organizer')->group(function () {
  Route::middleware('guest:organizer', 'change.lang', 'adminLang')->group(function () {
    Route::get('/login', 'BackEnd\Organizer\OrganizerController@login')->name('organizer.login');
    Route::get('/signup', 'BackEnd\Organizer\OrganizerController@signup')->name('organizer.signup');
    Route::post('/create', 'BackEnd\Organizer\OrganizerController@create')->middleware('throttle:authentication')->name('organizer.create');

    Route::post('/store', 'BackEnd\Organizer\OrganizerController@authentication')->middleware('throttle:authentication')->name('organizer.authentication');
    Route::get('/forget-password', 'BackEnd\Organizer\OrganizerController@forget_passord')->name('organizer.forget.password');
    Route::post('/send-forget-mail', 'BackEnd\Organizer\OrganizerController@forget_mail')->middleware('throttle:authentication')->name('organizer.forget.mail');
    Route::get('/verify-email', 'BackEnd\Organizer\OrganizerController@verify_email')->name('organizer.verify.email');
    Route::post('/send-verify-link', 'BackEnd\Organizer\OrganizerController@send_link')
      ->middleware('throttle:authentication')->name('organizer.send.verify.link');
    Route::get('/reset-password', 'BackEnd\Organizer\OrganizerController@reset_password')->name('organizer.reset.password');
    Route::post('/update-forget-password', 'BackEnd\Organizer\OrganizerController@update_password')->middleware('throttle:authentication')->name('organizer.update-forget-password');
  });

  Route::post('/logout', 'BackEnd\Organizer\OrganizerController@logout')
    ->middleware('auth:organizer')->name('organizer.logout');
  Route::get('/change-password', 'BackEnd\Organizer\OrganizerWorkspaceRedirectController@settings')
    ->middleware(['auth:organizer', 'Deactive:organizer', 'EmailStatus:organizer', 'tica.team'])
    ->name('organizer.change.password');
  Route::post('/update-password', 'BackEnd\Organizer\OrganizerController@updated_password')
    ->middleware(['auth:organizer', 'Deactive:organizer', 'EmailStatus:organizer', 'throttle:authentication', 'tica.team'])
    ->name('organizer.update_password');
});

Route::prefix('/organizer')->middleware('auth:organizer', 'Deactive:organizer', 'EmailStatus:organizer', 'adminLang', 'tica.team')->group(function () {
  Route::get('/pwa', 'BackEnd\Organizer\OrganizerController@pwa')->name('organizer.pwa');
  Route::get('/scanner/devices', 'BackEnd\Organizer\ScannerDeviceController@index')->name('organizer.scanner.devices.index');
  Route::post('/scanner/devices', 'BackEnd\Organizer\ScannerDeviceController@store')->middleware('throttle:scanner')->name('organizer.scanner.devices.store');
  Route::post('/scanner/devices/{device}/revoke', 'BackEnd\Organizer\ScannerDeviceController@revoke')->name('organizer.scanner.devices.revoke');
  Route::get('/scanner/staff', 'BackEnd\Organizer\ScannerStaffController@index')->name('organizer.scanner.staff.index');
  Route::post('/scanner/staff', 'BackEnd\Organizer\ScannerStaffController@store')->name('organizer.scanner.staff.store');
  Route::post('/scanner/staff/{staff}/update', 'BackEnd\Organizer\ScannerStaffController@update')->name('organizer.scanner.staff.update');
  Route::post('/events/{event}/check-in-ledger/export', 'BackEnd\Organizer\CheckInLedgerExportController@store')->name('organizer.check_in_ledger_exports.store');
  Route::get('/check-in-ledger/exports', 'BackEnd\Organizer\CheckInLedgerExportController@index')->name('organizer.check_in_ledger_exports.index');
  Route::get('/check-in-ledger/exports/{export}/download', 'BackEnd\Organizer\CheckInLedgerExportController@download')->middleware('signed')->name('organizer.check_in_ledger_exports.download');
  Route::get('/dashboard', 'BackEnd\Organizer\OrganizerWorkspaceRedirectController@overview')->name('organizer.dashboard');
  Route::get('monthly-income', 'BackEnd\Organizer\OrganizerWorkspaceRedirectController@overview')->name('organizer.monthly_income');
  Route::get('/transaction', 'BackEnd\Organizer\OrganizerController@transaction')->name('organizer.transcation');
  Route::post('/transaction/export', 'BackEnd\Organizer\TransactionExportController@store')->name('organizer.transaction_exports.store');
  Route::get('/transaction/exports', 'BackEnd\Organizer\TransactionExportController@index')->name('organizer.transaction_exports.index');
  Route::get('/transaction/exports/{export}/download', 'BackEnd\Organizer\TransactionExportController@download')
    ->middleware('signed')->name('organizer.transaction_exports.download');
  Route::get('/finance', 'BackEnd\Organizer\OrganizerFinanceController@index')->name('organizer.finance.index');
  Route::get('/finance/export', 'BackEnd\Organizer\OrganizerFinanceController@export')->name('organizer.finance.export');

  // change admin-panel theme (dark/light) route
  Route::post('/change-theme', 'BackEnd\Organizer\OrganizerController@changeTheme')->name('organizer.change_theme');

  Route::get('/edit-profile', 'BackEnd\Organizer\OrganizerWorkspaceRedirectController@settings')->name('organizer.edit.profile');
  Route::post('/organizer-update-profile', 'BackEnd\Organizer\OrganizerController@update_profile')->name('organizer.update_profile');

  Route::get('/kyc', 'BackEnd\Organizer\OrganizerKycController@show')->name('organizer.kyc.show');
  Route::post('/kyc', 'BackEnd\Organizer\OrganizerKycController@submit')->name('organizer.kyc.submit');
  Route::get('/kyc/document/{document}', 'BackEnd\Organizer\OrganizerKycController@download')->name('organizer.kyc.document');
  Route::post('/kyc/payout-account', 'BackEnd\Organizer\OrganizerKycController@verifyPayoutAccount')
    ->middleware('throttle:checkout')
    ->name('organizer.kyc.payout-account');

  Route::get('event-management/events/', 'BackEnd\Organizer\OrganizerWorkspaceRedirectController@events')->name('organizer.event_management.event');
  Route::get('choose-event-type/', 'BackEnd\Organizer\OrganizerWorkspaceRedirectController@create')->name('choose-event-type');
  Route::get('add-event/', 'BackEnd\Organizer\OrganizerWorkspaceRedirectController@create')->name('organizer.add.event.event');
  Route::post('event-imagesstore', 'BackEnd\Organizer\EventController@gallerystore')->name('organizer.event.imagesstore');
  Route::post('event-imagermv', 'BackEnd\Organizer\EventController@imagermv')->name('organizer.event.imagermv');
  Route::post('event-store', 'BackEnd\Organizer\EventController@store')->name('organizer.event_management.store_event');
  Route::post('/event/{event}/cancellation', 'BackEnd\Organizer\EventCancellationController@store')->name('organizer.event_cancellations.store');
  Route::post('/event/{id}/update-status', 'BackEnd\Organizer\EventController@updateStatus')->name('organizer.event_management.event.event_status');
  Route::post('/event/{id}/update-featured', 'BackEnd\Organizer\EventController@updateFeatured')->name('organizer.event_management.event.update_featured');
  Route::post('/delete-event/{id}', 'BackEnd\Organizer\EventController@destroy')->name('organizer.event_management.delete_event');
  Route::get('/edit-event/{id}', 'BackEnd\Organizer\EventController@edit')->name('organizer.event_management.edit_event');
  Route::get('/event/{slug}/{id}/preview', 'FrontEnd\EventController@preview')->name('organizer.event.preview');
  Route::post('/event-img-dbrmv', 'BackEnd\Organizer\EventController@imagedbrmv')->name('organizer.event.imgdbrmv');
  Route::post('/delete-date/{id}', 'BackEnd\Organizer\EventController@deleteDate')->name('organizer.event.delete.date');
  Route::get('/event-images/{id}', 'BackEnd\Organizer\EventController@images')->name('organizer.event.images');
  Route::post('/event-update', 'BackEnd\Organizer\EventController@update')->name('organizer.event.update');
  Route::post('bulk/delete/event', 'BackEnd\Organizer\EventController@bulk_delete')->name('organizer.event_management.bulk_delete_event');


  Route::get('event/ticket', 'BackEnd\Organizer\OrganizerWorkspaceRedirectController@events')->name('organizer.event.ticket');
  Route::get('event/add-ticket', 'BackEnd\Organizer\TicketController@create')->name('organizer.event.add.ticket');
  Route::post('event/ticket/store-ticket', 'BackEnd\Organizer\TicketController@store')->name('organizer.ticket_management.store_ticket');
  Route::get('event/edit/ticket', 'BackEnd\Organizer\TicketController@edit')->name('organizer.event.edit.ticket');
  Route::post('event/ticket/delete-ticket', 'BackEnd\Organizer\TicketController@destroy')->name('organizer.ticket_management.delete_ticket');
  Route::post('ticket_management/update/ticket', 'BackEnd\Organizer\TicketController@update')->name('organizer.ticket_management.update_ticket');
  Route::post('bulk/delete/bulk/event/ticket', 'BackEnd\Organizer\TicketController@bulk_delete')->name('organizer.event_management.bulk_delete_event_ticket');

  Route::get('event/guest-list', 'BackEnd\Organizer\EventGuestListController@index')->name('organizer.event.guest-list.index');
  Route::post('event/guest-list', 'BackEnd\Organizer\EventGuestListController@store')->middleware('throttle:checkout')->name('organizer.event.guest-list.store');
  Route::post('event/guest-list/{invitation}/cancel', 'BackEnd\Organizer\EventGuestListController@cancel')->name('organizer.event.guest-list.cancel');
  Route::post('event/guest-list/{invitation}/check-in', 'BackEnd\Organizer\EventGuestListController@checkIn')->middleware('throttle:scanner')->name('organizer.event.guest-list.check-in');
  Route::post('event/guest-list/import', 'BackEnd\Organizer\EventGuestListController@import')->middleware('throttle:10,1')->name('organizer.event.guest-list.import');
  Route::get('event/guest-list/import-template', 'BackEnd\Organizer\EventGuestListController@template')->name('organizer.event.guest-list.import-template');
  Route::post('event/guest-list/export', 'BackEnd\Organizer\EventGuestListController@export')->name('organizer.event.guest-list.export');

  Route::get('event/registrations', 'BackEnd\Organizer\EventRegistrationController@index')->name('organizer.event.registrations.index');
  Route::post('event/registrations/form', 'BackEnd\Organizer\EventRegistrationController@saveForm')->name('organizer.event.registrations.form.save');
  Route::post('event/registrations/form/{form}/questions', 'BackEnd\Organizer\EventRegistrationController@addQuestion')->name('organizer.event.registrations.questions.store');
  Route::post('event/registrations/form/{form}/questions/reorder', 'BackEnd\Organizer\EventRegistrationController@reorderQuestions')->name('organizer.event.registrations.questions.reorder');
  Route::post('event/registrations/questions/{question}/update', 'BackEnd\Organizer\EventRegistrationController@updateQuestion')->name('organizer.event.registrations.questions.update');
  Route::post('event/registrations/questions/{question}/move', 'BackEnd\Organizer\EventRegistrationController@moveQuestion')->name('organizer.event.registrations.questions.move');
  Route::post('event/registrations/questions/{question}/delete', 'BackEnd\Organizer\EventRegistrationController@deleteQuestion')->name('organizer.event.registrations.questions.delete');
  Route::post('event/registrations/form/{form}/publish', 'BackEnd\Organizer\EventRegistrationController@publish')->name('organizer.event.registrations.form.publish');
  Route::post('event/registrations/submissions/{submission}/decision', 'BackEnd\Organizer\EventRegistrationController@decide')->name('organizer.event.registrations.submissions.decide');
  Route::post('event/registrations/export', 'BackEnd\Organizer\EventRegistrationController@export')->name('organizer.event.registrations.export');
  Route::post('event/registrations/import', 'BackEnd\Organizer\EventRegistrationController@import')->middleware('throttle:10,1')->name('organizer.event.registrations.import');
  Route::get('event/registrations/form/{form}/import-template', 'BackEnd\Organizer\EventRegistrationController@template')->name('organizer.event.registrations.import-template');
  Route::post('event/registrations/form/{form}/templates', 'BackEnd\Organizer\EventRegistrationController@saveTemplate')->name('organizer.event.registrations.templates.save');
  Route::post('event/registrations/form/{form}/templates/{template}/apply', 'BackEnd\Organizer\EventRegistrationController@applyTemplate')->name('organizer.event.registrations.templates.apply');
  Route::post('event/registrations/form/{form}/allocations', 'BackEnd\Organizer\EventRegistrationController@createAllocation')->middleware('throttle:checkout')->name('organizer.event.registrations.allocations.store');
  Route::post('event/registrations/allocations/{allocation}/cancel', 'BackEnd\Organizer\EventRegistrationController@cancelAllocation')->name('organizer.event.registrations.allocations.cancel');
  Route::post('event/registrations/form/{form}/sessions', 'BackEnd\Organizer\EventRegistrationController@saveSession')->name('organizer.event.registrations.sessions.save');
  Route::post('event/registrations/sessions/{session}/cancel', 'BackEnd\Organizer\EventRegistrationController@cancelSession')->name('organizer.event.registrations.sessions.cancel');
  Route::post('event/registrations/submissions/{submission}/invoice', 'BackEnd\Organizer\EventRegistrationController@issueInvoice')->name('organizer.event.registrations.invoices.issue');
  Route::post('event/registrations/invoices/{invoice}/paid', 'BackEnd\Organizer\EventRegistrationController@markInvoicePaid')->middleware('throttle:checkout')->name('organizer.event.registrations.invoices.paid');
  Route::get('event/registrations/invoices/{invoice}', 'BackEnd\Organizer\EventRegistrationController@invoice')->name('organizer.event.registrations.invoices.show');
  Route::post('event/registrations/form/{form}/badge-settings', 'BackEnd\Organizer\EventRegistrationController@saveBadgeSettings')->name('organizer.event.registrations.badges.settings');
  Route::get('event/registrations/form/{form}/badges', 'BackEnd\Organizer\EventRegistrationController@downloadBadges')->name('organizer.event.registrations.badges.download');
  Route::get('event/registrations/files/{file}', 'BackEnd\Organizer\EventRegistrationController@downloadFile')->name('organizer.event.registrations.files.download');
  Route::post('event/registrations/form/{form}/embed-settings', 'BackEnd\Organizer\EventRegistrationController@saveEmbedSettings')->name('organizer.event.registrations.embed.settings');

  Route::get('withdraw', 'BackEnd\Organizer\OrganizerWorkspaceRedirectController@payouts')->name('organizer.withdraw');
  Route::get('withdraw/create', 'BackEnd\Organizer\OrganizerWorkspaceRedirectController@payouts')->name('organizer.withdraw.create');
  Route::post('withdraw/export', 'BackEnd\Organizer\OrganizerWithdrawController@export')->name('organizer.withdraw_exports.store');
  Route::get('withdraw/exports', 'BackEnd\Organizer\DataExportController@withdrawalIndex')->name('organizer.withdraw_exports.index');
  Route::get('withdraw/exports/{export}/download', 'BackEnd\Organizer\DataExportController@downloadWithdrawal')
    ->middleware('signed')->name('organizer.withdraw_exports.download');
  Route::get('/get-withdraw-method/input/{id}', 'BackEnd\Organizer\OrganizerWithdrawController@get_inputs');

  Route::get('withdraw/balance-calculation/{method}/{amount}', 'BackEnd\Organizer\OrganizerWithdrawController@balance_calculation');

  Route::post('/withdraw/send-request', 'BackEnd\Organizer\OrganizerWithdrawController@send_request')->name('organizer.withdraw.send-request');
  Route::post('/withdraw/{withdrawal}/disputes', 'BackEnd\Organizer\OrganizerWithdrawController@openDispute')->name('organizer.withdraw.disputes.store');
  Route::post('/withdraw/witdraw/bulk-delete', 'BackEnd\Organizer\OrganizerWithdrawController@bulkDelete')->name('organizer.witdraw.bulk_delete_withdraw');
  Route::post('/withdraw/witdraw/delete', 'BackEnd\Organizer\OrganizerWithdrawController@Delete')->name('organizer.witdraw.delete_withdraw');

  Route::get('event-booking', 'BackEnd\Organizer\OrganizerWorkspaceRedirectController@orders')->name('organizer.event.booking');
  Route::post('event-booking/update/payment-status/{id}', 'BackEnd\Organizer\EventBookingController@updatePaymentStatus')->name('organizer.event_booking.update_payment_status');
  Route::get('event-booking/details/{id}', 'BackEnd\Organizer\EventBookingController@show')->name('organizer.event_booking.details');
  Route::post('event-booking/refunds', 'BackEnd\Organizer\PaymentRefundController@store')->name('organizer.refunds.store');
  Route::post('event-booking/refund-disputes', 'BackEnd\Organizer\PaymentRefundDisputeController@store')->name('organizer.refund_disputes.store');
  Route::get('event-booking/{id}/invoice', 'BackEnd\Organizer\EventBookingController@invoice')->name('organizer.event_booking.invoice');
  Route::post('event-booking/{id}/resend-tickets', 'BackEnd\Organizer\EventBookingController@resendTickets')
    ->middleware('throttle:3,10')->name('organizer.event_booking.resend-tickets');
  Route::get('event-booking/{id}/attachment', 'BackEnd\Organizer\EventBookingController@attachment')->name('organizer.event_booking.attachment');
  Route::post('event-booking/ticket/{id}/reissue', 'BackEnd\Organizer\EventBookingController@reissueTicket')->name('organizer.event_booking.ticket.reissue');
  Route::post('event-booking/ticket/{id}/revoke', 'BackEnd\Organizer\EventBookingController@revokeTicket')->name('organizer.event_booking.ticket.revoke');
  Route::post('/{id}/delete', 'BackEnd\Organizer\EventBookingController@destroy')->name('organizer.event_booking.delete');
  Route::post('/event-booking/bulk-delete', 'BackEnd\Organizer\EventBookingController@bulkDestroy')->name('organizer.event_booking.bulk_delete');
  Route::get('/event-booking/report', 'BackEnd\Organizer\EventBookingController@report')->name('organizer.event_booking.report');
  Route::post('/event-booking/export', 'BackEnd\Organizer\EventBookingController@export')->name('organizer.event_bookings.export');
  Route::get('/event-booking/exports', 'BackEnd\Organizer\DataExportController@index')->name('organizer.data_exports.index');
  Route::post('/event-booking/export-schedules', 'BackEnd\Organizer\DataExportController@storeSchedule')->name('organizer.data_export_schedules.store');
  Route::post('/event-booking/export-schedules/{schedule}/toggle', 'BackEnd\Organizer\DataExportController@toggleSchedule')->name('organizer.data_export_schedules.toggle');
  Route::get('/event-booking/exports/{export}/download', 'BackEnd\Organizer\DataExportController@download')
    ->middleware('signed')
    ->name('organizer.data_exports.download');


  /*
  |---------------------------------------------
  |support ticket
  |---------------------------------------------
  */


  Route::prefix('support-tikcet')->group(function () {
    Route::get('create', 'BackEnd\Organizer\SupportTicketController@create')->name('organizer.support_ticket.create');
    Route::post('/store', 'BackEnd\Organizer\SupportTicketController@store')->name('organizer.support_ticket.store');
    Route::get('tickets', 'BackEnd\Organizer\SupportTicketController@index')->name('organizer.support_tickets');
    Route::get('/message/{id}', 'BackEnd\Organizer\SupportTicketController@message')->name('organizer.support_tickets.message');
    Route::post('/zip-upload', 'BackEnd\Organizer\SupportTicketController@zip_file_upload')->name('organizer.support_ticket.zip_file.upload');
    Route::post('/reply/{id}', 'BackEnd\Organizer\SupportTicketController@ticketreply')->name('organizer.support_ticket.reply');

    Route::post('/delete/{id}', 'BackEnd\Organizer\SupportTicketController@delete')->name('organizer.support_tickets.delete');
    Route::post('/bulk/delete/', 'BackEnd\Organizer\SupportTicketController@bulk_delete')->name('organizer.support_tickets.bulk_delete');
  });
});
