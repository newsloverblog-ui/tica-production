@verbatim
<section class="page" data-page="account">
  <div class="acd">
    <aside class="acd-side" aria-label="Your account">
      <div class="acd-me"><i class="acd-av" id="acd-av" aria-hidden="true"></i><div><b id="acd-name"></b><span id="acd-since"></span></div></div>
      <a class="btn acd-find" href="/discover">Find events</a>
      <nav class="acd-nav" aria-label="Account sections">
        <span class="acd-nav-label">General</span>
        <button type="button" data-acp="overview"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13h6V4H4zM14 20h6v-9h-6zM4 20h6v-4H4zM14 4v4h6V4z"/></svg>Overview</button>
        <span class="acd-nav-label">Tickets and orders</span>
        <button type="button" data-acp="tickets"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h16v3a2 2 0 0 0 0 3v3H4v-3a2 2 0 0 0 0-3zM14 8v2M14 13v1M14 16v1"/></svg>My tickets</button>
        <button type="button" data-acp="orders"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/></svg>Orders</button>
        <button type="button" data-acp="saved"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20.5s-8-4.9-8-11A4.5 4.5 0 0 1 12 6.7a4.5 4.5 0 0 1 8 2.8c0 6.1-8 11-8 11z"/></svg>Saved</button>
        <span class="acd-nav-label">Account</span>
        <button type="button" data-acp="profile"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>Profile</button>
        <button type="button" data-acp="help"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .8-1 1.5v.7M12 17h.01"/></svg>Help</button>
      </nav>
      <button class="acd-out" type="button" data-logout="customer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H4"/></svg>Log out</button>
    </aside>

    <div class="acd-main">
      <!-- OVERVIEW -->
      <div class="acd-p" data-p="overview">
        <div class="acd-head">
          <div><p class="acd-crumb">Your account</p><h1 id="ac-hi">Your tickets</h1><p id="acd-today"></p></div>
          <a class="btn acd-primary" href="/discover">Find events</a>
        </div>
        <section class="acd-next" id="acd-next" aria-label="Your next event"></section>
        <div class="acd-stats" id="acd-stats"></div>
        <div class="acd-two">
          <section class="acd-card" aria-labelledby="acd-up-h"><div class="acd-ch"><h2 id="acd-up-h">Coming up</h2><button type="button" class="acd-lnk" data-acp="tickets">All tickets</button></div><ul class="acd-mini" id="acd-up"></ul></section>
          <section class="acd-card" aria-labelledby="acd-qa-h"><div class="acd-ch"><h2 id="acd-qa-h">Quick actions</h2></div>
            <div class="acd-qa">
              <a href="/discover"><i aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4 4"/></svg></i><span><b>Discover events</b><small>Concerts, nightlife, conferences</small></span></a>
              <button type="button" data-acp="orders"><i aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 4v11M8 11l4 4 4-4M5 19h14"/></svg></i><span><b>Download tickets</b><small>PDF tickets and receipts</small></span></button>
              <button type="button" data-acp="saved"><i aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 20.5s-8-4.9-8-11A4.5 4.5 0 0 1 12 6.7a4.5 4.5 0 0 1 8 2.8c0 6.1-8 11-8 11z"/></svg></i><span><b>Saved events</b><small>Events you tapped the heart on</small></span></button>
              <button type="button" data-acp="help"><i aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .8-1 1.5v.7M12 17h.01"/></svg></i><span><b>Get help</b><small>Refunds, lost tickets, support</small></span></button>
            </div>
          </section>
        </div>
        <section class="acd-recs" aria-labelledby="acd-recs-h"><div class="acd-ch bare"><h2 id="acd-recs-h">Events you may like</h2><a class="acd-lnk" href="/discover">See all</a></div><div class="grid4" id="acd-recs"></div></section>
      </div>

      <!-- MY TICKETS -->
      <div class="acd-p" data-p="tickets" hidden>
        <div class="acd-head"><div><p class="acd-crumb">Your account</p><h1>My tickets</h1><p>Show the QR code on your ticket at the gate. Tickets also work offline once opened.</p></div></div>
        <div class="ac-tabs acd-seg" role="group" aria-label="Show tickets">
          <button type="button" data-tab="up" aria-pressed="true">Upcoming</button>
          <button type="button" data-tab="past" aria-pressed="false">Past</button>
        </div>
        <div id="ac-list" aria-live="polite"></div>
      </div>

      <!-- ORDERS -->
      <div class="acd-p" data-p="orders" hidden>
        <div class="acd-head"><div><p class="acd-crumb">Your account</p><h1>Orders</h1><p>Every ticket order, with your receipt and a PDF of the tickets.</p></div></div>
        <div id="acd-orders"></div>
      </div>

      <!-- SAVED -->
      <div class="acd-p" data-p="saved" hidden>
        <div class="acd-head"><div><p class="acd-crumb">Your account</p><h1>Saved events</h1><p>Tap the heart on any event to keep it here.</p></div></div>
        <div id="acd-saved"></div>
      </div>

      <!-- PROFILE -->
      <div class="acd-p" data-p="profile" hidden>
        <div class="acd-head"><div><p class="acd-crumb">Your account</p><h1>Profile</h1><p>Your details are used for receipts and tickets.</p></div></div>
        <section class="acd-card">
          <div class="acd-ch"><h2>Your details</h2></div>
          <form class="acd-form" id="acd-profile" novalidate>
            <div class="acd-row"><div class="inp"><label for="pf-fname">First name</label><input id="pf-fname" name="fname" autocomplete="given-name" required></div><div class="inp"><label for="pf-lname">Last name</label><input id="pf-lname" name="lname" autocomplete="family-name" required></div></div>
            <div class="acd-row"><div class="inp"><label for="pf-email">Email address</label><input id="pf-email" name="email" type="email" autocomplete="email" required></div><div class="inp"><label for="pf-phone">Phone number</label><input id="pf-phone" name="phone" type="tel" autocomplete="tel"></div></div>
            <div class="acd-row"><div class="inp"><label for="pf-city">City</label><input id="pf-city" name="city" autocomplete="address-level2"></div><div class="inp"><label for="pf-country">Country</label><input id="pf-country" name="country" autocomplete="country-name"></div></div>
            <div class="inp"><label for="pf-username">Username</label><input id="pf-username" name="username" autocomplete="username" required></div>
            <div class="inp" id="pf-cur-wrap" hidden><label for="pf-cur">Current password</label><input id="pf-cur" name="current_password" type="password" autocomplete="current-password"><small class="hint">Needed to change your email address.</small></div>
            <p class="acd-msg" id="pf-msg" role="status"></p>
            <button class="btn acd-primary" type="submit">Save details</button>
          </form>
        </section>
        <section class="acd-card">
          <div class="acd-ch"><h2>Password</h2></div>
          <form class="acd-form" id="acd-pass" novalidate>
            <div class="inp"><label for="pw-cur">Current password</label><input id="pw-cur" name="current_password" type="password" autocomplete="current-password" required></div>
            <div class="acd-row"><div class="inp"><label for="pw-new">New password</label><input id="pw-new" name="new_password" type="password" autocomplete="new-password" required></div><div class="inp"><label for="pw-new2">Type it again</label><input id="pw-new2" name="new_password_confirmation" type="password" autocomplete="new-password" required></div></div>
            <p class="acd-msg" id="pw-msg" role="status"></p>
            <button class="btn btn-line" type="submit">Change password</button>
          </form>
        </section>
        <section class="acd-card acd-security">
          <div class="acd-ch"><div><h2>Two-step verification</h2><p class="acd-text">Require a one-time email code after your password.</p></div><span class="acd-sec-state" id="acd-mfa-state">Off</span></div>
          <a class="btn btn-line" id="acd-security" href="/account-security/customer">Manage account security</a>
        </section>
        <section class="acd-card">
          <div class="acd-ch"><h2>Your data</h2></div>
          <p class="acd-text">Download a copy of everything TICA holds about you, or ask us to close your account.</p>
          <a class="btn btn-line" id="acd-data" href="/customer/my-data">Download or delete my data</a>
        </section>
      </div>

      <!-- HELP -->
      <div class="acd-p" data-p="help" hidden>
        <div class="acd-head"><div><p class="acd-crumb">Your account</p><h1>Help</h1><p>Answers for the most common ticket questions.</p></div></div>
        <div class="acd-help">
          <a href="/how#faq"><i aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .8-1 1.5v.7M12 17h.01"/></svg></i><b>Questions and answers</b><small>Tickets, payments, transfers and more</small></a>
          <a href="/policies/refunds"><i aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 12a8 8 0 1 0 2.3-5.7M4 4v4h4"/></svg></i><b>Refunds</b><small>What happens if an event is cancelled</small></a>
          <button type="button" data-acp="orders"><i aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7l8 6 8-6M4 6h16v12H4z"/></svg></i><b>I can't find my ticket</b><small>Resend tickets to your email from Orders</small></button>
          <a href="/contact" id="acd-support"><i aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5h16v11H8l-4 4z"/><path d="M8 9h8M8 12h5"/></svg></i><b>Contact TICA</b><small>Call, email or message the team</small></a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CHECK-IN -->
@endverbatim
