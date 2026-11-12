@verbatim
<section class="page" data-page="checkin">
  <div class="ck">
    <div class="wrap ck-wrap">
      <div class="ck-top">
        <div class="ck-ev"><label for="ck-event">Checking in for</label><select id="ck-event"><option value="afterdark-owerri">Afterdark Owerri, Sat 12 Dec</option><option value="kulture-market">Kulture Market, Sat 28 Nov</option></select></div>
        <p class="ck-sync"><i aria-hidden="true"></i>Works offline. Synced just now.</p>
      </div>
      <div class="ck-grid">
        <div class="ck-scan-col">
          <div class="ck-cam" aria-hidden="true"><div class="ck-frame"><b></b><b></b><b></b><b></b><span class="ck-line"></span></div><p>Point the camera at the QR code on a ticket</p></div>
          <div class="ck-res" id="ck-res" role="status" aria-live="assertive"><p class="ck-idle">Scan a ticket to see the result here.</p></div>
          <div class="ck-sim"><p>Camera scanning is part of the real app. Try a result here:</p><div><button class="btn btn-hot" type="button" data-sim="ok">Scan a valid ticket</button><button class="btn btn-ghost2" type="button" data-sim="used">Scan a used ticket</button><button class="btn btn-ghost2" type="button" data-sim="bad">Scan an unknown code</button></div></div>
          <form class="ck-manual" id="ck-manual" novalidate><label for="ck-code">Or type the ticket code</label><div><input id="ck-code" type="text" autocomplete="off" placeholder="TICA-123456-1" autocapitalize="characters"><button class="btn btn-ghost2" type="submit">Check</button></div></form><form class="ck-manual ck-find" id="ck-find" role="search" novalidate><label for="ck-q">Can't scan? Find the guest</label><div><input id="ck-q" type="search" autocomplete="off" placeholder="Name, email or booking ID" maxlength="100"><button class="btn btn-ghost2" type="submit">Search</button></div></form><ul class="ck-found" id="ck-found" aria-live="polite"></ul>
        </div>
        <div class="ck-side">
          <div class="ck-stat"><div><span>Checked in</span><b><span id="ck-in">311</span> <small>of <span id="ck-total">526</span></small></b></div><div class="ck-bar" aria-hidden="true"><i id="ck-bar"></i></div><p id="ck-left">215 still to arrive</p></div>
          <div class="ck-log"><h2>Recent check-ins</h2><ul id="ck-log"></ul></div>
          <a class="ck-setup" id="ck-setup" href="/organizer/pwa" hidden><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><rect x="15" y="9" width="6" height="10" rx="1.5"/></svg><span><b>Gate staff and devices</b><small>Approve phones, add gate staff, prepare offline</small></span></a><a class="ck-back" href="/dashboard">Back to dashboard</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- AUTH -->
@endverbatim
