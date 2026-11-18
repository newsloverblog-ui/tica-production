@verbatim
<section class="page" data-page="checkin">
  <div class="ck">
    <div class="wrap ck-wrap">
      <header class="t-page-head ck-head"><div><h1>Gate check-in</h1><p>Scan tickets at the door. Results stay live, and it keeps working offline.</p></div><div class="or-actions"><a class="t-btn t-btn--secondary" href="/organizer/check-in-ledger/exports">Check-in ledger</a><a class="t-btn t-btn--secondary" href="/organizer/pwa">Gate staff and devices</a></div></header>
      <div class="ck-top">
        <div class="ck-ev"><label for="ck-event">Checking in for</label><select id="ck-event"></select></div>
        <p class="ck-sync"><i aria-hidden="true"></i>Works offline. Synced just now.</p>
      </div>
      <div class="ck-grid">
        <div class="ck-scan-col">
          <div class="ck-cam" aria-hidden="true"><div class="ck-frame"><b></b><b></b><b></b><b></b><span class="ck-line"></span></div><p>Point the camera at the QR code on a ticket</p></div>
          <div class="ck-res" id="ck-res" role="status" aria-live="assertive"><p class="ck-idle">Scan a ticket to see the result here.</p></div>
          <div class="ck-sim"></div>
          <form class="ck-manual" id="ck-manual" novalidate><label for="ck-code">Or type the ticket code</label><div><input id="ck-code" type="text" autocomplete="off" placeholder="TICA-123456-1" autocapitalize="characters"><button class="btn btn-ghost2" type="submit">Check</button></div></form><form class="ck-manual ck-find" id="ck-find" role="search" novalidate><label for="ck-q">Can't scan? Find the guest</label><div><input id="ck-q" type="search" autocomplete="off" placeholder="Name, email or booking ID" maxlength="100"><button class="btn btn-ghost2" type="submit">Search</button></div></form><ul class="ck-found" id="ck-found" aria-live="polite"></ul>
        </div>
        <div class="ck-side">
          <div class="ck-stat"><div><span>Checked in</span><b><span id="ck-in">0</span> <small>of <span id="ck-total">0</span></small></b></div><div class="ck-bar" aria-hidden="true"><i id="ck-bar"></i></div><p id="ck-left"></p></div>
          <div class="ck-log"><h2>Recent check-ins</h2><ul id="ck-log"></ul></div>
          <a class="ck-back" href="/dashboard">Back to dashboard</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- AUTH -->
@endverbatim
