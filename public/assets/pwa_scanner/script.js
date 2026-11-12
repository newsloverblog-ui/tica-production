"use strict";

var scannerDeviceId = localStorage.getItem('tica_scanner_device_id') || '';
var scannerDeviceSecret = localStorage.getItem('tica_scanner_device_secret') || '';
var scannerDeviceName = localStorage.getItem('tica_scanner_device_name') || '';
var requestSent = false;
var scannerStarted = false;
var offlineDbPromise = null;
var activeOfflineManifest = null;
var offlineAdmissionHashes = new Set();
var offlineSyncRunning = false;
var scannerMetricsTimer = null;
var scannerMetricsEventId = '';

$.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

$(document).ready(function () {
  sessionStorage.setItem('booking_id', '');
  initializeDevice();
  if (canManageScannerDevices) loadScannerStaff();
  updateNetworkState();
  window.addEventListener('online', function () { updateNetworkState(); synchronizeOfflineScans(true); });
  window.addEventListener('offline', updateNetworkState);
});

function credentialHeaders() {
  return { 'X-Scanner-Device': scannerDeviceId, 'X-Scanner-Secret': scannerDeviceSecret };
}

function saveCredentials(device, secret) {
  scannerDeviceId = device.public_id;
  scannerDeviceSecret = secret;
  scannerDeviceName = device.name;
  localStorage.setItem('tica_scanner_device_id', scannerDeviceId);
  localStorage.setItem('tica_scanner_device_secret', scannerDeviceSecret);
  localStorage.setItem('tica_scanner_device_name', scannerDeviceName);
}

function clearCredentials() {
  scannerDeviceId = '';
  scannerDeviceSecret = '';
  scannerDeviceName = '';
  localStorage.removeItem('tica_scanner_device_id');
  localStorage.removeItem('tica_scanner_device_secret');
  localStorage.removeItem('tica_scanner_device_name');
}

function initializeDevice() {
  if (!scannerDeviceId || !scannerDeviceSecret) {
    showEnrollment('Enroll this browser before scanning tickets.');
    return;
  }
  $.ajax({
    type: 'GET', url: scannerDeviceCurrentUrl, headers: credentialHeaders(),
    success: function (data) {
      scannerDeviceName = data.device.name;
      localStorage.setItem('tica_scanner_device_name', scannerDeviceName);
      showScanner(data.device);
    },
    error: function () {
      clearCredentials();
      showEnrollment('This browser credential has expired or been revoked. Enroll it again to continue.');
    }
  });
}

function showEnrollment(message) {
  stopScannerMetrics();
  $('#scanner-workspace').addClass('d-none');
  $('#scanner-enroll-form').toggleClass('d-none', !canManageScannerDevices);
  $('#scanner-manage-toggle').toggleClass('d-none', !canManageScannerDevices);
  $('#scanner-device-badge').removeClass('badge-success').addClass('badge-secondary').text('Not enrolled');
  $('#scanner-device-status').text(canManageScannerDevices ? message : 'Ask the organizer to enroll this gate browser before check-in begins.');
}

function showScanner(device) {
  $('#scanner-enroll-form').addClass('d-none');
  $('#scanner-manage-toggle').toggleClass('d-none', !canManageScannerDevices);
  $('#scanner-device-badge').removeClass('badge-secondary').addClass('badge-success').text('Active');
  $('#scanner-device-status').text(device.name + ' is approved for ticket check-in.');
  $('#scanner-workspace').removeClass('d-none');
  startScanner();
  initializeOfflineAccess();
  if (canReviewScannerConflicts) loadConflicts();
  else $('#scanner-conflicts-card').addClass('d-none');
}

function startScanner() {
  // The organiser's setup page has no scanner; Gate check-in does the scanning.
  if (scannerStarted || window.TICA_SCANNER_SETUP_ONLY) return;
  scannerStarted = true;
  var html5QrcodeScanner = new Html5QrcodeScanner('reader', { fps: 10, qrbox: 250 });
  html5QrcodeScanner.render(onScanSuccess, function () {});
}

function deviceError(xhr, fallback) {
  if (xhr.status === 401 && xhr.responseJSON && String(xhr.responseJSON.code || '').indexOf('scanner_device_') === 0) {
    clearCredentials();
    showEnrollment(xhr.responseJSON.message);
  }
  return xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : fallback;
}

function onScanSuccess(qrCodeMessage) {
  if (requestSent && qrCodeMessage === sessionStorage.getItem('booking_id')) return;
  requestSent = true;
  sessionStorage.setItem('booking_id', qrCodeMessage);
  $.ajax({
    type: 'POST', url: url, headers: credentialHeaders(),
    data: { booking_id: qrCodeMessage, source: 'scanner' },
    success: function (data) {
      rememberOnlineAdmission(qrCodeMessage);
      loadScannerMetrics(scannerMetricsEventId);
      swal({ title: data.message, icon: data.alert_type === 'success' ? 'success' : 'error' })
        .then(function () { requestSent = false; });
    },
    error: function (xhr) {
      if (xhr.responseJSON && xhr.responseJSON.multi_gate_conflict) loadConflicts();
      if (xhr.status === 0) {
        queueOfflineScan(qrCodeMessage).then(function (result) {
          swal({ title: result.message, text: result.detail || '', icon: result.accepted ? 'success' : 'error' })
            .then(function () { requestSent = false; });
        });
        return;
      }
      swal({ title: deviceError(xhr, 'Unable to verify this ticket.'), icon: 'error' })
        .then(function () { requestSent = false; });
    }
  });
}

function escapeHtml(value) {
  return $('<div>').text(value == null ? '' : String(value)).html();
}

$('#scanner-enroll-form').on('submit', function (event) {
  event.preventDefault();
  var button = $(this).find('button[type="submit"]');
  button.prop('disabled', true).text('Enrolling…');
  $.ajax({
    type: 'POST', url: scannerDeviceEnrollUrl, data: { name: $('#scanner-device-name').val().trim() },
    success: function (data) {
      saveCredentials(data.device, data.secret);
      $('#scanner-device-name').val('');
      showScanner(data.device);
      loadDevices();
    },
    error: function (xhr) {
      var errors = xhr.responseJSON && xhr.responseJSON.errors;
      $('#scanner-device-status').text(errors && errors.name ? errors.name[0] : 'Unable to enroll this scanner.');
    },
    complete: function () { button.prop('disabled', false).text('Enroll'); }
  });
});

$('#scanner-manage-toggle').on('click', function () {
  $('#scanner-device-list').toggleClass('d-none');
  if (!$('#scanner-device-list').hasClass('d-none')) loadDevices();
});

function loadDevices() {
  if (!canManageScannerDevices) return;
  $('#scanner-device-list').html('<p class="text-muted">Loading scanners…</p>');
  $.get(scannerDevicesUrl).done(function (data) {
    var devices = data.devices || [];
    if (!devices.length) {
      $('#scanner-device-list').html('<p class="text-muted">No enrolled scanners yet.</p>');
      return;
    }
    var html = '';
    devices.forEach(function (device) {
      var current = device.public_id === scannerDeviceId ? ' <span class="badge badge-info">This browser</span>' : '';
      var lastSeen = device.last_seen_at ? new Date(device.last_seen_at).toLocaleString() : 'Never used';
      var action = device.status === 'active'
        ? '<button type="button" class="btn btn-sm btn-outline-danger revoke-scanner" data-device-id="' + device.id + '" data-public-id="' + escapeHtml(device.public_id) + '">Revoke</button>'
        : '<span class="badge badge-secondary">Revoked</span>';
      html += '<div class="scanner-device-row"><div><strong>' + escapeHtml(device.name) + '</strong>' + current +
        '<br><small class="text-muted">Last used: ' + escapeHtml(lastSeen) + '</small></div><div>' + action + '</div></div>';
    });
    $('#scanner-device-list').html(html);
  }).fail(function () {
    $('#scanner-device-list').html('<p class="text-danger">Unable to load enrolled scanners.</p>');
  });
}

$(document).on('click', '.revoke-scanner', function () {
  var button = $(this);
  var publicId = String(button.data('public-id'));
  if (!window.confirm('Revoke this scanner? It will immediately lose check-in access.')) return;
  button.prop('disabled', true).text('Revoking…');
  $.post(scannerDeviceRevokeUrlTemplate.replace('__DEVICE__', button.data('device-id')))
    .done(function () {
      if (publicId === scannerDeviceId) {
        clearCredentials();
        showEnrollment('This browser was revoked. Enroll it again only if it should regain access.');
      }
      loadDevices();
    })
    .fail(function () { button.prop('disabled', false).text('Revoke'); });
});

function loadScannerStaff() {
  if (!canManageScannerDevices || !scannerStaffUrl) return;
  $('#scanner-staff-status').text('Loading gate accounts…');
  $.get(scannerStaffUrl).done(function (data) {
    var people = data.staff || [];
    $('#scanner-staff-status').text(people.length ? '' : 'No gate staff accounts yet.');
    var html = '';
    people.forEach(function (person) {
      var activeSelected = person.status === 'active' ? ' selected' : '';
      var inactiveSelected = person.status === 'inactive' ? ' selected' : '';
      var checkSelected = person.role === 'check_in' ? ' selected' : '';
      var supervisorSelected = person.role === 'supervisor' ? ' selected' : '';
      var lastSeen = person.last_seen_at ? new Date(person.last_seen_at).toLocaleString() : 'Never signed in';
      html += '<div class="border rounded p-3 mb-3 scanner-staff-row" data-staff-id="' + person.id + '">' +
        '<div class="form-row"><div class="col-md-6 mb-2"><label>Name</label><input class="form-control staff-edit-name" value="' + escapeHtml(person.name) + '"></div>' +
        '<div class="col-md-6 mb-2"><label>Email</label><input class="form-control" value="' + escapeHtml(person.email) + '" readonly></div>' +
        '<div class="col-md-4 mb-2"><label>Role</label><select class="form-control staff-edit-role"><option value="check_in"' + checkSelected + '>Check-in staff</option><option value="supervisor"' + supervisorSelected + '>Gate supervisor</option></select></div>' +
        '<div class="col-md-4 mb-2"><label>Status</label><select class="form-control staff-edit-status"><option value="active"' + activeSelected + '>Active</option><option value="inactive"' + inactiveSelected + '>Inactive</option></select></div>' +
        '<div class="col-md-4 mb-2"><label>New password (optional)</label><input type="password" class="form-control staff-edit-password" minlength="8"></div></div>' +
        '<div class="d-flex justify-content-between align-items-center"><small class="text-muted">Last active: ' + escapeHtml(lastSeen) + '</small><button type="button" class="btn btn-sm btn-outline-primary save-scanner-staff">Save</button></div></div>';
    });
    $('#scanner-staff-list').html(html);
  }).fail(function () { $('#scanner-staff-status').text('Unable to load gate accounts.'); });
}

$('#scanner-staff-form').on('submit', function (event) {
  event.preventDefault();
  var form = $(this);
  var button = form.find('button[type="submit"]');
  button.prop('disabled', true).text('Creating…');
  $.post(scannerStaffCreateUrl, {
    name: $('#staff-name').val().trim(), email: $('#staff-email').val().trim(), role: $('#staff-role').val(),
    password: $('#staff-password').val(), password_confirmation: $('#staff-password-confirmation').val()
  }).done(function () {
    form[0].reset();
    $('#scanner-staff-status').text('Gate account created. Share its login details securely.');
    loadScannerStaff();
  }).fail(function (xhr) {
    var errors = xhr.responseJSON && xhr.responseJSON.errors;
    $('#scanner-staff-status').text(errors ? Object.values(errors)[0][0] : 'Unable to create gate account.');
  }).always(function () { button.prop('disabled', false).text('Create gate account'); });
});

$(document).on('click', '.save-scanner-staff', function () {
  var button = $(this);
  var row = button.closest('.scanner-staff-row');
  var password = row.find('.staff-edit-password').val();
  button.prop('disabled', true).text('Saving…');
  $.post(scannerStaffUpdateUrlTemplate.replace('__STAFF__', row.data('staff-id')), {
    name: row.find('.staff-edit-name').val().trim(), role: row.find('.staff-edit-role').val(),
    status: row.find('.staff-edit-status').val(), password: password || '', password_confirmation: password || ''
  }).done(function () {
    loadScannerStaff();
  }).fail(function (xhr) {
    button.prop('disabled', false).text('Save');
    var errors = xhr.responseJSON && xhr.responseJSON.errors;
    window.alert(errors ? Object.values(errors)[0][0] : 'Unable to update gate account.');
  });
});

function loadConflicts() {
  if (!canReviewScannerConflicts) return;
  $('#scanner-conflict-status').text('Loading conflicts…');
  $.get(scannerConflictsUrl).done(function (data) {
    var conflicts = data.conflicts || [];
    $('#scanner-conflict-status').text(conflicts.length ? '' : 'No unresolved multi-gate conflicts.');
    $('#scanner-conflict-count').toggleClass('d-none', !conflicts.length).text(conflicts.length);
    var html = '';
    conflicts.forEach(function (conflict) {
      var attendee = conflict.attendee || 'Attendee';
      var detected = conflict.last_detected_at ? new Date(conflict.last_detected_at).toLocaleString() : '';
      html += '<div class="scanner-device-row"><div><strong>' + escapeHtml(attendee) + '</strong>' +
        '<br><small>Booking ' + escapeHtml(conflict.booking_id) + ' · Event ' + escapeHtml(conflict.event_id) + '</small>' +
        '<br><small class="text-danger">First: ' + escapeHtml(conflict.original_gate) + ' · Attempted: ' + escapeHtml(conflict.attempted_gate) + '</small>' +
        '<br><small class="text-muted">' + escapeHtml(detected) + ' · ' + escapeHtml(conflict.occurrences) + ' attempt(s)</small></div>' +
        '<div><button type="button" class="btn btn-sm btn-outline-success resolve-conflict" data-conflict-id="' + conflict.id + '">Mark reviewed</button></div></div>';
    });
    $('#scanner-conflict-list').html(html);
  }).fail(function () {
    $('#scanner-conflict-status').text('Unable to load gate conflicts.');
  });
}

$('#scanner-conflicts-refresh').on('click', loadConflicts);

function loadCheckInLedgers() {
  if (!canReviewScannerConflicts || typeof scannerLedgerIndexUrl === 'undefined') return;
  $.ajax({ type: 'GET', url: scannerLedgerIndexUrl, headers: credentialHeaders() })
    .done(function (data) {
      var exports = data.exports || [];
      if (!exports.length) {
        $('#scanner-ledger-list').html('<p class="text-muted mb-0">No ledgers requested yet.</p>');
        return;
      }
      $('#scanner-ledger-list').html(exports.map(function (item) {
        var action = item.download_url
          ? '<a class="btn btn-sm btn-outline-primary" href="' + escapeHtml(item.download_url) + '">Download</a>'
          : '<span class="badge badge-secondary">' + escapeHtml(item.status) + '</span>';
        return '<div class="d-flex justify-content-between align-items-center border-top py-2">' +
          '<div><strong>Event #' + item.event_id + '</strong><br><small>' + item.row_count + ' rows · ' + escapeHtml(item.format.toUpperCase()) + '</small></div>' + action + '</div>';
      }).join(''));
    })
    .fail(function (xhr) { $('#scanner-ledger-status').text(deviceError(xhr, 'Unable to load export history.')); });
}

$('#scanner-ledger-create').on('click', function () {
  var eventId = $('#offline-event-select').val();
  if (!eventId) {
    $('#scanner-ledger-status').text('Choose an event under Offline event access first.');
    return;
  }
  var button = $(this).prop('disabled', true).text('Queueing…');
  $.ajax({
    type: 'POST',
    url: scannerLedgerStoreUrlTemplate.replace('__EVENT__', encodeURIComponent(eventId)),
    headers: credentialHeaders(),
    data: { format: $('#scanner-ledger-format').val() }
  }).done(function (data) {
    $('#scanner-ledger-status').text(data.message || 'Ledger queued.');
    loadCheckInLedgers();
  }).fail(function (xhr) {
    $('#scanner-ledger-status').text(deviceError(xhr, 'Unable to queue this ledger.'));
  }).always(function () { button.prop('disabled', false).text('Queue ledger'); });
});

if (canReviewScannerConflicts) loadCheckInLedgers();

$(document).on('click', '.resolve-conflict', function () {
  var button = $(this);
  var note = window.prompt('Add a short resolution note (for example: duplicate wristband stopped at gate).');
  if (note === null) return;
  note = note.trim();
  if (note.length < 3) {
    window.alert('Enter at least three characters.');
    return;
  }
  button.prop('disabled', true).text('Saving…');
  $.post(scannerConflictResolveUrlTemplate.replace('__CONFLICT__', button.data('conflict-id')), { note: note })
    .done(loadConflicts)
    .fail(function (xhr) {
      button.prop('disabled', false).text('Mark reviewed');
      window.alert(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unable to resolve conflict.');
    });
});

$('#ticket-lookup-form').on('submit', function (event) {
  event.preventDefault();
  var query = $('#ticket-lookup-query').val().trim();
  $('#ticket-lookup-status').text('Searching…');
  $('#ticket-lookup-results').empty();
  $.ajax({
    type: 'POST', url: lookupUrl, headers: credentialHeaders(), data: { query: query },
    success: function (data) {
      var tickets = data.tickets || [];
      $('#ticket-lookup-status').text(tickets.length ? tickets.length + ' ticket(s) found.' : 'No matching tickets found.');
      tickets.forEach(function (ticket) {
        var checked = ticket.status === 'checked_in';
        var disabled = ticket.status !== 'issued' ? ' disabled' : '';
        var label = checked ? 'Already checked in' : (ticket.status === 'issued' ? 'Check in' : ticket.status.replace('_', ' '));
        $('#ticket-lookup-results').append(
          '<div class="border rounded p-3 mb-3"><div class="d-flex justify-content-between align-items-start">' +
          '<div><strong>' + escapeHtml(ticket.holder_name || 'Attendee') + '</strong><br><small>' + escapeHtml(ticket.holder_email) +
          '</small><br><small>Booking ' + escapeHtml(ticket.booking_id) + ' · Ticket ' + escapeHtml(ticket.sequence) + ' of ' + escapeHtml(ticket.quantity) +
          '</small></div><button type="button" class="btn btn-sm btn-primary manual-check-in" data-ticket-id="' + ticket.id + '"' + disabled + '>' +
          escapeHtml(label) + '</button></div></div>'
        );
      });
    },
    error: function (xhr) { $('#ticket-lookup-status').text(deviceError(xhr, 'Search failed.')); }
  });
});

$(document).on('click', '.manual-check-in', function () {
  var button = $(this);
  button.prop('disabled', true).text('Checking in…');
  $.ajax({
    type: 'POST', url: manualCheckInUrl, headers: credentialHeaders(), data: { ticket_unit_id: button.data('ticket-id') },
    success: function (data) {
      button.text('Checked in');
      loadScannerMetrics(scannerMetricsEventId);
      swal({ title: data.message, icon: 'success' });
    },
    error: function (xhr) {
      button.text('Try again').prop('disabled', false);
      swal({ title: deviceError(xhr, 'Check-in failed.'), icon: 'error' });
    }
  });
});

function openOfflineDb() {
  if (!window.indexedDB) return Promise.reject(new Error('Offline storage is not supported by this browser.'));
  if (offlineDbPromise) return offlineDbPromise;
  offlineDbPromise = new Promise(function (resolve, reject) {
    var request = indexedDB.open('tica_scanner_offline_v1', 1);
    request.onupgradeneeded = function () {
      var db = request.result;
      if (!db.objectStoreNames.contains('manifests')) db.createObjectStore('manifests', { keyPath: 'device_id' });
      if (!db.objectStoreNames.contains('scans')) {
        var scans = db.createObjectStore('scans', { keyPath: 'client_scan_id' });
        scans.createIndex('device_id', 'device_id', { unique: false });
      }
      if (!db.objectStoreNames.contains('seen')) db.createObjectStore('seen', { keyPath: 'key' });
    };
    request.onsuccess = function () { resolve(request.result); };
    request.onerror = function () { reject(request.error); };
  });
  return offlineDbPromise;
}

function idbRequest(storeName, mode, operation) {
  return openOfflineDb().then(function (db) {
    return new Promise(function (resolve, reject) {
      var transaction = db.transaction(storeName, mode);
      var store = transaction.objectStore(storeName);
      var request = operation(store);
      request.onsuccess = function () { resolve(request.result); };
      request.onerror = function () { reject(request.error); };
    });
  });
}

function offlineGetManifest() {
  return idbRequest('manifests', 'readonly', function (store) { return store.get(scannerDeviceId); });
}

function offlinePutManifest(value) {
  return idbRequest('manifests', 'readwrite', function (store) { return store.put(value); });
}

function offlineGetScans() {
  return idbRequest('scans', 'readonly', function (store) { return store.index('device_id').getAll(scannerDeviceId); });
}

function offlinePutScan(value) {
  return idbRequest('scans', 'readwrite', function (store) { return store.put(value); });
}

function offlineDeleteScan(id) {
  return idbRequest('scans', 'readwrite', function (store) { return store.delete(id); });
}

function offlineSeen(manifestId, hash) {
  return idbRequest('seen', 'readonly', function (store) { return store.get(manifestId + ':' + hash); });
}

function offlineRememberSeen(manifestId, hash) {
  return idbRequest('seen', 'readwrite', function (store) {
    return store.put({ key: manifestId + ':' + hash, device_id: scannerDeviceId, seen_at: new Date().toISOString() });
  });
}

function sha256(value) {
  if (!window.crypto || !window.crypto.subtle || !window.TextEncoder) {
    return Promise.reject(new Error('Secure ticket hashing is unavailable in this browser.'));
  }
  return crypto.subtle.digest('SHA-256', new TextEncoder().encode(value)).then(function (buffer) {
    return Array.prototype.map.call(new Uint8Array(buffer), function (byte) {
      return byte.toString(16).padStart(2, '0');
    }).join('');
  });
}

function scannerUuid() {
  if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
  var bytes = new Uint8Array(16);
  crypto.getRandomValues(bytes);
  bytes[6] = (bytes[6] & 15) | 64;
  bytes[8] = (bytes[8] & 63) | 128;
  var hex = Array.prototype.map.call(bytes, function (b) { return b.toString(16).padStart(2, '0'); }).join('');
  return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
}

function updateNetworkState() {
  var online = navigator.onLine;
  $('#offline-network-badge').toggleClass('badge-success', online).toggleClass('badge-warning', !online).text(online ? 'Online' : 'Offline');
}

function initializeOfflineAccess() {
  loadOfflineEvents();
  offlineGetManifest().then(function (stored) {
    if (stored) activateOfflineManifest(stored);
    refreshOfflineQueue();
  }).catch(function (error) {
    $('#offline-manifest-status').text(error.message);
    $('#offline-prepare').prop('disabled', true);
  });
}

function loadOfflineEvents() {
  $.ajax({ type: 'GET', url: scannerOfflineEventsUrl, headers: credentialHeaders() })
    .done(function (data) {
      var events = data.events || [];
      var html = '<option value="">Select an event</option>';
      events.forEach(function (event) {
        html += '<option value="' + event.id + '">' + escapeHtml(event.title) + '</option>';
      });
      $('#offline-event-select').html(html);
      if (activeOfflineManifest) {
        $('#offline-event-select').val(String(activeOfflineManifest.event_id));
        startScannerMetrics(String(activeOfflineManifest.event_id));
      }
    })
    .fail(function () {
      $('#offline-event-select').html('<option value="">Reconnect to load events</option>');
    });
}

function activateOfflineManifest(stored) {
  activeOfflineManifest = stored.manifest;
  offlineAdmissionHashes = new Set(stored.admissions || []);
  var expires = new Date(activeOfflineManifest.expires_at);
  var expired = expires.getTime() <= Date.now();
  $('#offline-manifest-status')
    .toggleClass('text-danger', expired)
    .toggleClass('text-muted', !expired)
    .text((expired ? 'Expired: ' : 'Ready: ') + activeOfflineManifest.event_title + ' · ' +
      activeOfflineManifest.ticket_count + ' eligible ticket(s) · expires ' + expires.toLocaleString());
  $('#offline-event-select').val(String(activeOfflineManifest.event_id));
  startScannerMetrics(String(activeOfflineManifest.event_id));
}

$('#offline-event-select').on('change', function () {
  startScannerMetrics($(this).val());
});

$('#scanner-metrics-refresh').on('click', function () {
  loadScannerMetrics(scannerMetricsEventId);
});

function startScannerMetrics(eventId) {
  stopScannerMetrics();
  scannerMetricsEventId = String(eventId || '');
  if (!scannerMetricsEventId) {
    $('#scanner-live-metrics').addClass('d-none');
    return;
  }
  $('#scanner-live-metrics').removeClass('d-none');
  loadScannerMetrics(scannerMetricsEventId);
  scannerMetricsTimer = window.setInterval(function () {
    if (navigator.onLine && document.visibilityState === 'visible') loadScannerMetrics(scannerMetricsEventId);
  }, 15000);
}

function stopScannerMetrics() {
  if (scannerMetricsTimer) window.clearInterval(scannerMetricsTimer);
  scannerMetricsTimer = null;
  scannerMetricsEventId = '';
}

function loadScannerMetrics(eventId) {
  if (!eventId || !navigator.onLine) return;
  $('#scanner-metrics-updated').text('Refreshing live totals…');
  $.ajax({
    type: 'GET', url: scannerMetricsUrlTemplate.replace('__EVENT__', eventId), headers: credentialHeaders()
  }).done(function (data) {
    $('#scanner-metrics-event').text(data.event.title);
    $('#scanner-metrics-scanned').text(data.scanned);
    $('#scanner-metrics-remaining').text(data.remaining);
    $('#scanner-metrics-total').text(data.total);
    var gates = data.per_gate || [];
    $('#scanner-metrics-gates').html(gates.length ? gates.map(function (gate) {
      return '<div class="scanner-gate-row"><span>' + escapeHtml(gate.gate) + '</span><strong>' + escapeHtml(gate.scanned) + '</strong></div>';
    }).join('') : '<span class="text-muted small">No admissions recorded yet.</span>');
    $('#scanner-metrics-updated').text('Updated ' + new Date(data.refreshed_at).toLocaleTimeString());
  }).fail(function (xhr) {
    $('#scanner-metrics-updated').text(deviceError(xhr, 'Live attendance totals are temporarily unavailable.'));
  });
}

$('#offline-prepare').on('click', function () {
  var eventId = $('#offline-event-select').val();
  if (!eventId) {
    $('#offline-manifest-status').text('Select an event first.');
    return;
  }
  if (!navigator.onLine) {
    $('#offline-manifest-status').text('Reconnect before preparing a fresh event manifest.');
    return;
  }
  var button = $(this);
  button.prop('disabled', true).text('Preparing…');
  $.ajax({
    type: 'POST', url: scannerOfflineManifestUrl, headers: credentialHeaders(), data: { event_id: eventId }
  }).done(function (data) {
    var stored = { device_id: scannerDeviceId, manifest: data.manifest, admissions: data.admissions };
    offlinePutManifest(stored).then(function () {
      activateOfflineManifest(stored);
      $('#offline-sync-status').text('Offline access prepared. Keep this page open and synchronize as soon as connectivity returns.');
    });
  }).fail(function (xhr) {
    $('#offline-manifest-status').text(deviceError(xhr, 'Unable to prepare offline event access.'));
  }).always(function () { button.prop('disabled', false).text('Prepare offline'); });
});

function rememberOnlineAdmission(payload) {
  if (!activeOfflineManifest) return;
  sha256(payload).then(function (hash) {
    if (offlineAdmissionHashes.has(hash)) return offlineRememberSeen(activeOfflineManifest.id, hash);
  }).catch(function () {});
}

function queueOfflineScan(payload) {
  if (!activeOfflineManifest) {
    return Promise.resolve({ accepted: false, message: 'No offline event is prepared.', detail: 'Reconnect and prepare the event before relying on offline scanning.' });
  }
  if (new Date(activeOfflineManifest.expires_at).getTime() <= Date.now()) {
    return Promise.resolve({ accepted: false, message: 'Offline event access has expired.', detail: 'Reconnect and prepare a fresh manifest.' });
  }
  return sha256(payload).then(function (hash) {
    if (!offlineAdmissionHashes.has(hash)) {
      return { accepted: false, message: 'Ticket is not in the offline event manifest.', detail: 'Do not admit this attendee without online verification.' };
    }
    return offlineSeen(activeOfflineManifest.id, hash).then(function (seen) {
      if (seen) {
        return { accepted: false, message: 'Possible duplicate offline scan.', detail: 'This device has already seen this ticket. Ask a supervisor to review it.' };
      }
      var scan = {
        client_scan_id: scannerUuid(), device_id: scannerDeviceId, manifest_id: activeOfflineManifest.id,
        payload: payload, payload_hash: hash, scanned_at: new Date().toISOString()
      };
      return offlinePutScan(scan).then(function () {
        return offlineRememberSeen(activeOfflineManifest.id, hash);
      }).then(function () {
        refreshOfflineQueue();
        return {
          accepted: true,
          message: 'Provisional offline admission recorded',
          detail: 'Synchronize this device as soon as it reconnects. The server remains authoritative.'
        };
      });
    });
  }).catch(function (error) {
    return { accepted: false, message: 'Unable to store this offline scan.', detail: error.message };
  });
}

function refreshOfflineQueue() {
  return offlineGetScans().then(function (scans) {
    $('#offline-pending-count').text(scans.length);
    $('#offline-sync').prop('disabled', !scans.length || !navigator.onLine || offlineSyncRunning);
    return scans;
  });
}

$('#offline-sync').on('click', function () { synchronizeOfflineScans(false); });

function synchronizeOfflineScans(silent) {
  if (offlineSyncRunning || !navigator.onLine || !scannerDeviceId) return;
  offlineSyncRunning = true;
  $('#offline-sync').prop('disabled', true).text('Synchronizing…');
  refreshOfflineQueue().then(function (scans) {
    if (!scans.length) return [];
    var groups = {};
    scans.forEach(function (scan) {
      if (!groups[scan.manifest_id]) groups[scan.manifest_id] = [];
      groups[scan.manifest_id].push(scan);
    });
    var jobs = Object.keys(groups).map(function (manifestId) {
      var batch = groups[manifestId].slice(0, 250);
      return new Promise(function (resolve) {
        $.ajax({
          type: 'POST', url: scannerOfflineSyncUrl, headers: credentialHeaders(), contentType: 'application/json',
          data: JSON.stringify({ manifest_id: manifestId, scans: batch.map(function (scan) {
            return { client_scan_id: scan.client_scan_id, payload: scan.payload, scanned_at: scan.scanned_at };
          }) })
        }).done(function (data) {
          var deletions = [];
          (data.results || []).forEach(function (result) {
            if (result.result !== 'retry' && result.result !== 'processing') deletions.push(offlineDeleteScan(result.client_scan_id));
            if (result.result === 'conflict') loadConflicts();
          });
          Promise.all(deletions).then(function () { resolve(data.summary || {}); });
        }).fail(function (xhr) {
          if (!silent) $('#offline-sync-status').text(deviceError(xhr, 'Synchronization failed. The queued scans remain safely on this device.'));
          resolve({ failed: batch.length });
        });
      });
    });
    return Promise.all(jobs);
  }).then(function (summaries) {
    if (summaries && summaries.length) {
      var totals = {};
      summaries.forEach(function (summary) {
        Object.keys(summary).forEach(function (key) { totals[key] = (totals[key] || 0) + summary[key]; });
      });
      $('#offline-sync-status').text('Synchronization complete: ' + Object.keys(totals).map(function (key) {
        return totals[key] + ' ' + key;
      }).join(', ') + '.');
    }
  }).finally(function () {
    offlineSyncRunning = false;
    $('#offline-sync').text('Synchronize now');
    refreshOfflineQueue();
    loadScannerMetrics(scannerMetricsEventId);
  });
}
