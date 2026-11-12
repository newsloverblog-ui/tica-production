  <script>
    let url = "{{ route('check-qrcode') }}";
    let lookupUrl = "{{ route('organizer.scanner.lookup') }}";
    let manualCheckInUrl = "{{ route('organizer.scanner.manual-check-in') }}";
    let scannerDevicesUrl = "{{ route('organizer.scanner.devices.index') }}";
    let scannerDeviceCurrentUrl = "{{ route('organizer.scanner.devices.current') }}";
    let scannerDeviceEnrollUrl = "{{ route('organizer.scanner.devices.store') }}";
    let scannerDeviceRevokeUrlTemplate = "{{ route('organizer.scanner.devices.revoke', ['device' => '__DEVICE__']) }}";
    let scannerConflictsUrl = "{{ route('organizer.scanner.conflicts.index') }}";
    let scannerConflictResolveUrlTemplate = "{{ route('organizer.scanner.conflicts.resolve', ['conflict' => '__CONFLICT__']) }}";
    let scannerOfflineEventsUrl = "{{ route('organizer.scanner.offline.events') }}";
    let scannerMetricsUrlTemplate = "{{ route('organizer.scanner.metrics.show', ['event' => '__EVENT__']) }}";
    let scannerOfflineManifestUrl = "{{ route('organizer.scanner.offline.manifests.store') }}";
    let scannerOfflineSyncUrl = "{{ route('organizer.scanner.offline.sync') }}";
    let scannerLedgerIndexUrl = "{{ route('organizer.scanner.check_in_ledger_exports.index') }}";
    let scannerLedgerStoreUrlTemplate = "{{ route('organizer.scanner.check_in_ledger_exports.store', ['event' => '__EVENT__']) }}";
    let scannerStaffUrl = "{{ Auth::guard('organizer')->check() ? route('organizer.scanner.staff.index') : '' }}";
    let scannerStaffCreateUrl = "{{ Auth::guard('organizer')->check() ? route('organizer.scanner.staff.store') : '' }}";
    let scannerStaffUpdateUrlTemplate = "{{ Auth::guard('organizer')->check() ? route('organizer.scanner.staff.update', ['staff' => '__STAFF__']) : '' }}";
    let canManageScannerDevices = @json(Auth::guard('organizer')->check());
    let canReviewScannerConflicts = @json(Auth::guard('organizer')->check() || optional(Auth::guard('scanner')->user())->role === 'supervisor');
    let scanner_img = "{{ asset('assets/pwa_scanner/scanner.gif') }}";
  </script>
