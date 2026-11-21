@verbatim
<section class="page" data-page="dashboard">
  <div class="db od">
    <div class="db-main od-main" id="db-top">
      <p class="db-flag" id="db-flag" hidden></p>

      <!-- OVERVIEW: key numbers, then sales and the next event, then recent orders. -->
      <div class="od-p ov-wrap" data-p="overview"><div class="ov">
        <header class="ov-head">
          <div class="ov-head__copy">
            <p class="ov-eyebrow" id="od-today"></p>
            <h1 id="db-hi">Welcome back</h1>
            <p class="ov-sub">Here's how your events are doing. <span class="ov-refreshed" id="od-refreshed"></span></p>
          </div>
          <div class="ov-head__actions">
            <div class="ov-seg" role="radiogroup" aria-label="Performance period" id="od-range-seg"></div>
            <select class="t-select ov-range-select" id="od-range" aria-label="Performance period"></select>
            <a class="t-btn t-btn--primary" id="od-overview-create" href="/create"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Create an event</a>
          </div>
        </header>

        <section class="ov-setup" id="od-setup" aria-labelledby="od-setup-h" hidden></section>

        <!-- One concise performance summary for the chosen period. -->
        <div class="ov-summary" id="db-kpis" aria-live="polite">
          <span class="t-skel"></span><span class="t-skel"></span><span class="t-skel"></span><span class="t-skel"></span>
        </div>

        <div class="ov-row ov-row--primary">
          <!-- Sales over time -->
          <section class="ov-card ov-sales" aria-labelledby="ov-chart-h">
            <div class="ov-card__head">
              <div><h2 class="ov-card__title" id="ov-chart-h">Sales performance</h2><p class="ov-card__sub" id="od-chart-sub"></p></div>
              <div class="ov-toggle" role="radiogroup" aria-label="Show">
                <button type="button" role="radio" aria-checked="true" data-metric="revenue">Revenue</button>
                <button type="button" role="radio" aria-checked="false" data-metric="tickets">Tickets</button>
              </div>
            </div>
            <div class="ov-chart" id="db-chart"></div>
            <details class="ov-table-view"><summary>Show as a table</summary><div id="od-chart-table"></div></details>
          </section>
          <!-- Next event -->
          <section class="ov-card ov-next" id="od-next" aria-label="Next event"></section>
        </div>
        <div class="ov-row ov-row--secondary">
          <!-- Events -->
          <section class="ov-card ov-events" aria-labelledby="od-top-h">
            <div class="ov-card__head"><div><h2 class="ov-card__title" id="od-top-h">Events</h2><p class="ov-card__sub">Published events and their current ticket pace.</p></div><button class="ov-link" type="button" data-panel="events">All events</button></div>
            <div id="od-top"></div>
          </section>
          <!-- Money -->
          <section class="ov-card ov-money" id="od-money" aria-labelledby="od-money-h"></section>
        </div>
        <!-- Recent orders -->
          <section class="ov-card ov-orders" aria-labelledby="ov-ord-h">
            <div class="ov-card__head"><div><h2 class="ov-card__title" id="ov-ord-h">Recent orders</h2><p class="ov-card__sub" id="od-order-count"></p></div><button class="ov-link" type="button" data-panel="orders">All orders</button></div>
            <div id="db-ord"></div>
          </section>
        <!-- Channels and promotion -->
        <section class="ov-card ov-channels" id="od-channels" aria-labelledby="od-channels-h"></section>
      </div></div>

      <!-- EVENTS -->
      <div class="od-p" data-p="events" hidden>
        <header class="t-page-head"><div><h1>Events</h1><p>Edit details, control sales and manage tickets for each event.</p></div><a class="t-btn t-btn--primary od-events-create" href="/create">Create an event</a></header>
        <div class="t-tabs" role="tablist" aria-label="Show events"><button class="t-tab" type="button" role="tab" aria-selected="true" data-ef="up">Upcoming<span class="t-count" data-efc="up">0</span></button><button class="t-tab" type="button" role="tab" aria-selected="false" data-ef="draft">Drafts<span class="t-count" data-efc="draft">0</span></button><button class="t-tab" type="button" role="tab" aria-selected="false" data-ef="past">Past<span class="t-count" data-efc="past">0</span></button></div>
        <div class="t-toolbar"><label class="t-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><span class="t-sr">Search events</span><input class="t-input" id="ev-q" type="search" placeholder="Search by name or city"></label></div>
        <div id="od-evlist"></div>
      </div>

      <!-- TICKET MANAGEMENT -->
      <div class="od-p tk" data-p="tickets" hidden>
        <header class="t-page-head"><div><h1>Ticket management</h1><p>Monitor inventory, ticket numbers, reserved seats and attendee assignments.</p></div>
          <div class="or-actions"><button class="t-btn t-btn--secondary" type="button" id="tk-export">Export tickets</button><button class="t-btn t-btn--primary" type="button" data-dlg="od-comp">Issue complimentary ticket</button></div></header>
        <div class="tk-stats" id="tk-stats"></div>
        <section class="t-card tk-categories" aria-labelledby="tk-cat-h">
          <div class="t-card__head"><div><h2 class="t-card__title" id="tk-cat-h">Inventory by ticket type</h2><p class="t-help">Unlimited categories show an em dash for inventory and availability.</p></div></div>
          <div class="t-toolbar"><label class="t-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><span class="t-sr">Search ticket types</span><input class="t-input" id="tk-cat-q" type="search" placeholder="Event or ticket type"></label><select class="t-select" id="tk-cat-event" aria-label="Event"><option value="">All events</option></select></div>
          <div id="tk-cat-table"></div>
        </section>
        <section class="t-card tk-allocations" aria-labelledby="tk-unit-h">
          <div class="t-card__head"><div><h2 class="t-card__title" id="tk-unit-h">Assigned tickets and seats</h2><p class="t-help">Search by ticket number, attendee, email, section, row or seat.</p></div></div>
          <div class="t-toolbar"><label class="t-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><span class="t-sr">Search assigned tickets</span><input class="t-input" id="tk-q" type="search" placeholder="REG-001, Ada, VIP, Row A or Seat 12"></label><select class="t-select" id="tk-event" aria-label="Event"><option value="">All events</option></select><select class="t-select" id="tk-category" aria-label="Ticket type"><option value="">All ticket types</option></select><select class="t-select" id="tk-status" aria-label="Ticket status"><option value="">Any status</option><option value="paid">Paid</option><option value="checked_in">Checked in</option><option value="refunded">Refunded or cancelled</option><option value="guest">Complimentary</option></select></div>
          <div id="tk-units" aria-live="polite"></div><div class="t-pager"><span id="tk-msg" role="status"></span><button class="t-btn t-btn--secondary t-btn--sm" id="tk-more" type="button" hidden>Load more</button></div>
        </section>
      </div>

      <!-- ORDERS -->
      <div class="od-p" data-p="orders" hidden>
        <header class="t-page-head"><div><h1>Orders and attendees</h1><p>Find anyone, resend tickets, refund orders and message your guests.</p></div>
          <div class="or-actions"><a class="t-btn t-btn--secondary" href="/organizer/event-booking/report">Report</a><a class="t-btn t-btn--secondary" href="/organizer/event-booking/exports">Export history</a><button class="t-btn t-btn--secondary" type="button" id="od-export">Export CSV</button><button class="t-btn t-btn--secondary" type="button" data-dlg="od-comp">Add guest</button><button class="t-btn t-btn--primary" type="button" data-dlg="od-msg">Message attendees</button></div></header>
        <div class="t-toolbar">
          <label class="t-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><span class="t-sr">Search attendees</span><input class="t-input" id="od-q" type="search" placeholder="Name, email, phone or order number"></label>
          <select class="t-select" id="od-fe" aria-label="Event"><option value="">All events</option></select>
          <select class="t-select" id="od-fs" aria-label="Status"><option value="">Any status</option></select>
          <label class="or-date"><span class="t-sr">Ordered from</span><input class="t-input" id="od-from" type="date" aria-label="Ordered from"></label>
          <label class="or-date"><span class="t-sr">Ordered to</span><input class="t-input" id="od-to" type="date" aria-label="Ordered to"></label>
          <button class="t-btn t-btn--ghost t-btn--sm" type="button" id="od-clear" hidden>Clear filters</button>
        </div>
        <div id="od-att" aria-live="polite"></div>
        <div class="t-pager"><span id="od-att-msg" role="status"></span><button class="t-btn t-btn--secondary t-btn--sm" id="od-att-more" type="button" hidden>Load more</button></div>
      </div>

      <!-- PAYOUTS -->
      <div class="od-p" data-p="payouts" hidden>
        <header class="t-page-head"><div><h1>Payouts</h1><p>Your ticket money, sent straight to your bank.</p></div><div class="or-actions"><a class="t-btn t-btn--secondary" href="/organizer/transaction">Transactions</a><a class="t-btn t-btn--secondary" href="/organizer/finance">Statement</a><a class="t-btn t-btn--secondary" href="/organizer/withdraw/exports">Export history</a><button class="t-btn t-btn--primary" type="button" id="od-wd-btn">Withdraw</button></div></header>
        <div class="pay-bal" id="od-bal">
          <div class="t-card t-stat"><span class="t-stat__label">Available</span><b class="t-stat__value" id="od-avail">₦0</b><span class="t-stat__note">Ready to withdraw now</span></div>
          <div class="t-card t-stat"><span class="t-stat__label">Pending</span><b class="t-stat__value" id="od-pend">₦0</b><span class="t-stat__note">Requested and on its way to your bank</span></div>
          <div class="t-card t-stat"><span class="t-stat__label">Paid out</span><b class="t-stat__value" id="od-paid">₦0</b><span class="t-stat__note">Since you joined</span></div>
        </div>
        <div class="pay-grid">
          <section class="t-card" id="od-bankcard" aria-labelledby="od-bank-h">
            <div class="t-card__head"><h2 class="t-card__title" id="od-bank-h">Payout account</h2></div>
            <div id="od-bank-view"></div>
          </section>
          <section class="t-card" aria-labelledby="od-sched-h">
            <div class="t-card__head"><h2 class="t-card__title" id="od-sched-h">How you get paid</h2></div>
            <div class="od-radio" role="radiogroup" aria-labelledby="od-sched-h">
              <label><input type="radio" name="od-sched" value="auto" checked><span><b>Automatically</b><small>We send each event's sales to your bank as soon as they're released.</small></span></label>
              <label><input type="radio" name="od-sched" value="manual"><span><b>When I ask</b><small>Money waits in your balance until you press Withdraw.</small></span></label>
            </div>
            <p class="t-help">Transfers are free. Payouts pause for 24 hours after a bank account change, to protect you from fraud.</p>
          </section>
        </div>
        <section class="t-card pay-hist" aria-labelledby="od-hist-h"><div class="t-card__head"><h2 class="t-card__title" id="od-hist-h">Payout history</h2></div><div id="od-hist"></div></section>
      </div>

      <!-- PROMOTE -->
      <div class="od-p pr pv" data-p="promote" hidden>
        <header class="pv-head">
          <h1>Promote your event</h1>
          <p>Sell more tickets with promo codes and tracking links, and get featured across TICA. Everything you run shows up below.</p>
        </header>

        <div class="pv-bar">
          <div class="pv-event"><label for="pr-ev">Event</label><span class="pv-select"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z"/></svg><select class="t-select" id="pr-ev"></select><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></span></div>
          <button class="t-btn t-btn--primary pv-create" type="button" data-prnew="choose"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Create promotion</button>
        </div>

        <div class="pv-tools">
          <button class="pv-tool pv-tool--code" type="button" data-prnew="code"><span class="pv-tool__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12V4h8l9.5 9.5-8 8z"/><circle cx="7.5" cy="8.5" r="1.5"/></svg></span><b>Promo code</b><span>Offer a discount with a custom code</span><i class="pv-tool__go"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></i></button>
          <button class="pv-tool pv-tool--link" type="button" data-prnew="link"><span class="pv-tool__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1 1"/><path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1-1"/></svg></span><b>Tracking link</b><span>Share a unique link to see what sells tickets</span><i class="pv-tool__go"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></i></button>
          <button class="pv-tool pv-tool--feat" type="button" data-prnew="featured"><span class="pv-tool__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5l2.6 5.4 5.9.8-4.3 4.1 1 5.8L12 16.8l-5.2 2.8 1-5.8-4.3-4.1 5.9-.8z"/></svg></span><b>Featured placement</b><span id="pv-feat-from">Get your event featured across TICA</span><i class="pv-tool__go"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></i></button>
        </div>

        <section class="pv-section" aria-labelledby="pv-camps-h">
          <div class="pv-section__head"><h2 id="pv-camps-h">Your campaigns</h2>
            <div class="pv-filter" role="tablist" aria-label="Show campaigns"><button type="button" role="tab" data-prfilter="all" aria-selected="true">All</button><button type="button" role="tab" data-prfilter="code" aria-selected="false">Codes</button><button type="button" role="tab" data-prfilter="link" aria-selected="false">Links</button><button type="button" role="tab" data-prfilter="feat" aria-selected="false">Placements</button></div></div>
          <div class="pv-camps" id="od-campaigns" role="table" aria-labelledby="pv-camps-h"></div>
        </section>

        <section class="pv-section" aria-labelledby="pv-perf-h">
          <div class="pv-section__head"><h2 id="pv-perf-h">Promotion performance</h2><span class="pv-note">Since you started promoting</span></div>
          <div class="pv-stats" id="od-promo-kpis"></div>
        </section>

        <section class="pv-share" aria-labelledby="pr-share-h">
          <div><h2 id="pr-share-h">Share your event link</h2><p>Post it anywhere. Use a tracking link instead when you want to see which post sells.</p></div>
          <span class="pr-copy"><input class="t-input" id="pr-url" type="text" readonly aria-label="Event link"><button class="t-btn t-btn--secondary" type="button" id="pr-copy">Copy link</button></span>
          <div class="pr-social" id="pr-social" aria-label="Share to"></div>
        </section>

        <details class="pr-card pr-more">
          <summary><span><b>Conversion tracking</b><small>Meta, TikTok and Google pixels</small></span><svg class="pr-chev" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></summary>
          <div class="pr-more__body" aria-labelledby="od-pixel-h">
            <p class="pr-card__sub" id="od-pixel-h">Add only the provider's ID. TICA loads it after a visitor agrees to cookies; custom code is never accepted.</p>
            <form class="pr-form pr-form--row" id="od-pixel-f" novalidate>
              <label class="pr-field"><span>Provider</span><select class="t-select" id="od-pixel-provider"><option value="meta">Meta Pixel</option><option value="tiktok">TikTok Pixel</option><option value="google">Google tag</option></select></label>
              <label class="pr-field"><span>Pixel or tag ID</span><input class="t-input" id="od-pixel-id" maxlength="100" required></label>
              <label class="pr-field"><span>Label</span><input class="t-input" id="od-pixel-label" maxlength="100" placeholder="Main website" required></label>
              <button class="t-btn t-btn--secondary" type="submit">Submit for review</button>
            </form>
            <p class="pr-msg" id="od-pixel-m" role="status"></p>
            <ul class="pr-list pr-list--plain" id="od-pixels"></ul>
          </div>
        </details>
      </div>

      <!-- TEAM -->
      <div class="od-p" data-p="team" hidden>
        <div class="db-head"><div><h1>Team</h1><p>Let people help run your events, with only the access they need.</p></div></div>
        <div class="od-team-layout">
          <section class="db-card od-team-card" aria-labelledby="od-team-members-h">
            <div class="db-ch od-team-heading"><div><h2 id="od-team-members-h">People with access</h2><p class="od-fine">Role changes take effect on the person's next action.</p></div><span class="od-team-count" id="od-team-count" aria-live="polite"></span></div>
            <ul class="od-team" id="od-team"></ul>
            <div class="od-invite-wrap">
              <div><h3>Invite someone</h3><p class="od-fine">Choose the smallest role that gives them the access they need.</p></div>
              <form class="od-form od-invite" id="od-inv" novalidate><div class="inp"><label for="od-ie">Email address</label><input id="od-ie" type="email" placeholder="name@example.com" autocomplete="email" required></div><div class="inp"><label for="od-ir">Role</label><select id="od-ir"><option>Gate staff</option><option>Manager</option><option>Finance</option></select></div><button class="btn btn-hot" type="submit">Send invite</button></form>
              <p class="hint" id="od-inv-m" role="status"></p>
            </div>
          </section>
          <aside class="db-card od-role-guide" aria-labelledby="od-role-guide-h">
            <div class="db-ch"><h2 id="od-role-guide-h">Role permissions</h2></div>
            <div class="od-roles" id="od-roles"></div>
          </aside>
        </div>
      </div>

      <!-- SETTINGS -->
      <div class="od-p" data-p="settings" hidden>
        <div class="db-head"><div><h1>Settings</h1></div><div class="or-actions"><a class="t-btn t-btn--secondary" href="/organizer/kyc">Identity and bank account</a></div></div>
        <div class="od-settings-layout">
          <section class="db-card od-settings-profile"><div class="db-ch"><h2>Profile and account</h2></div>
            <form class="od-form" id="od-prof" novalidate>
              <div class="od-profile-photo"><span class="od-photo-frame"><span class="od-photo-ini" id="od-photo-ini" aria-hidden="true"></span><img id="od-photo-preview" alt="Your profile photo" hidden></span><div><span class="od-photo-t">Profile photo</span><div class="od-photo-act"><label class="btn btn-line od-upl" for="od-photo"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>Choose a photo</label><input id="od-photo" class="od-file" name="photo" type="file" accept="image/jpeg,image/png,image/webp,image/gif"><span class="od-photo-name" id="od-photo-name"></span></div><small>JPG, PNG, WebP or GIF, up to 3 MB. Square photos look best.</small></div></div>
              <fieldset class="od-settings-group"><legend>Public profile</legend>
                <div class="inp"><label for="od-pn">Organiser name</label><input id="od-pn" type="text" autocomplete="organization" required></div>
                <div class="inp"><label for="od-pa">About</label><textarea id="od-pa" rows="3"></textarea></div>
              </fieldset>
              <fieldset class="od-settings-group"><legend>Sign-in details</legend>
                <div class="od-row2"><div class="inp"><label for="od-pe">Email</label><input id="od-pe" type="email" autocomplete="email" required><small>Changing this email signs you out and requires verification.</small></div><div class="inp"><label for="od-pu">Username</label><input id="od-pu" type="text" autocomplete="username" required></div></div>
                <div class="inp" id="od-pcp-row" hidden><label for="od-pcp">Current password</label><input id="od-pcp" type="password" autocomplete="current-password"></div>
              </fieldset>
              <fieldset class="od-settings-group"><legend>Contact and location</legend>
                <div class="od-row2"><div class="inp"><label for="od-pw">Phone / WhatsApp</label><input id="od-pw" type="tel" autocomplete="tel"></div><div class="inp"><label for="od-paddr">Address</label><input id="od-paddr" type="text" autocomplete="street-address"></div></div>
                <div class="od-row2"><div class="inp"><label for="od-pcity">City</label><input id="od-pcity" type="text" autocomplete="address-level2"></div><div class="inp"><label for="od-pstate">State</label><input id="od-pstate" type="text" autocomplete="address-level1"></div></div>
                <div class="od-row2"><div class="inp"><label for="od-pcountry">Country</label><input id="od-pcountry" type="text" autocomplete="country-name"></div><div class="inp"><label for="od-pzip">Postcode</label><input id="od-pzip" type="text" autocomplete="postal-code"></div></div>
              </fieldset>
              <fieldset class="od-settings-group"><legend>Social profiles</legend>
                <div class="od-row2"><div class="inp"><label for="od-pi">Instagram</label><input id="od-pi" type="text" placeholder="@yourhandle"></div><div class="inp"><label for="od-pt">TikTok</label><input id="od-pt" type="text" placeholder="@yourhandle"></div></div>
                <div class="inp"><label for="od-pweb">Website</label><input id="od-pweb" type="url" placeholder="https://example.com"></div>
                <div class="od-row2"><div class="inp"><label for="od-pfb">Facebook URL</label><input id="od-pfb" type="url" placeholder="https://facebook.com/..."></div><div class="inp"><label for="od-px">X / Twitter URL</label><input id="od-px" type="url" placeholder="https://x.com/..."></div></div>
                <div class="od-row2"><div class="inp"><label for="od-pli">LinkedIn URL</label><input id="od-pli" type="url" placeholder="https://linkedin.com/company/..."></div><div class="inp"><label for="od-pyt">YouTube URL</label><input id="od-pyt" type="url" placeholder="https://youtube.com/@..."></div></div>
              </fieldset>
              <div class="od-settings-actions"><button class="btn btn-hot" type="submit">Save changes</button><p class="hint" id="od-prof-m" role="status"></p></div>
            </form>
          </section>
          <div class="od-settings-stack">
          <section class="db-card"><div class="db-ch"><h2>Defaults for new events</h2></div>
            <ul class="od-sw">
              <li><span><b>Buyers pay the booking fee</b><small>Turn off to include the <span data-fee-pct>6%</span> + ₦100 per paid ticket in your prices instead.</small></span><input class="ck-sw" type="checkbox" checked aria-label="Buyers pay the booking fee" data-pref="buyers_pay_fee"></li>
              <li><span><b>Show tickets left on event pages</b></span><input class="ck-sw" type="checkbox" checked aria-label="Show tickets left" data-pref="show_remaining"></li>
              <li><span><b>Waitlist when sold out</b><small>Collect emails and tell people if tickets come back.</small></span><input class="ck-sw" type="checkbox" checked aria-label="Waitlist when sold out" data-pref="waitlist"></li>
              <li><span><b>Let buyers transfer tickets</b><small>Buyers can pass a ticket to someone else before the event.</small></span><input class="ck-sw" type="checkbox" checked aria-label="Let buyers transfer tickets" data-pref="transfers"></li>
            </ul>
          </section>
          <section class="db-card"><div class="db-ch"><h2>Notifications</h2></div>
            <ul class="od-sw">
              <li><span><b>Email me for every sale</b></span><input class="ck-sw" type="checkbox" aria-label="Email me for every sale" data-pref="notify_sale"></li>
              <li><span><b>Daily and weekly sales summaries</b></span><input class="ck-sw" type="checkbox" checked aria-label="Daily and weekly sales summaries" data-pref="daily_summary"></li>
              <li><span><b>Text me about payouts</b><small>An SMS to your phone number when a payout is sent or doesn't go through.</small></span><input class="ck-sw" type="checkbox" aria-label="Text me about payouts" data-pref="payout_sms"></li>
              <li><span><b>Payout status emails</b></span><span class="od-sec-state on">Always on</span></li>
            </ul>
          </section>
          <section class="db-card od-security-card" id="password"><div class="db-ch"><h2>Password</h2></div>
            <form class="od-form" id="od-pass" novalidate>
              <div class="inp"><label for="od-pass-current">Current password</label><input id="od-pass-current" type="password" autocomplete="current-password" required></div>
              <div class="od-row2"><div class="inp"><label for="od-pass-new">New password</label><input id="od-pass-new" type="password" autocomplete="new-password" minlength="8" required><small>At least 8 characters with upper and lowercase letters and a number.</small></div><div class="inp"><label for="od-pass-confirm">Confirm new password</label><input id="od-pass-confirm" type="password" autocomplete="new-password" minlength="8" required></div></div>
              <button class="t-btn t-btn--secondary" type="submit">Update password</button><p class="hint" id="od-pass-m" role="status"></p>
            </form>
          </section>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<dialog class="od-dlg" id="od-inv-dlg" aria-labelledby="od-inv-h">
  <div class="sh-head"><div><h2 id="od-inv-h">Invite a team member</h2><p>They get an email with a link to join. You can change their role or remove them any time.</p></div><button class="sh-x" type="button" data-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
  <form class="od-form" id="od-inv" novalidate><div class="inp"><label for="od-ie">Email</label><input id="od-ie" type="email" placeholder="name@example.com" autocomplete="email"></div><div class="inp"><label for="od-ir">Role</label><select id="od-ir"></select><small id="od-ir-help"></small></div><button class="t-btn t-btn--primary t-btn--block" type="submit">Send invite</button><p class="hint" id="od-inv-m" role="alert"></p></form>
</dialog>
<dialog class="od-dlg pr-dlg pr-drawer" id="pr-code-dlg" aria-labelledby="pr-code-h">
  <div class="sh-head"><div><h2 id="pr-code-h">New discount code</h2><p id="pr-code-p">Buyers enter this at checkout.</p></div><button class="sh-x" type="button" data-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
  <form class="pr-form" id="od-code-f" novalidate>
    <label class="pr-field"><span>Code</span><input class="t-input" id="od-cn" type="text" placeholder="EARLYBIRD" autocapitalize="characters" maxlength="30" required></label>
    <div class="pr-form__two">
      <label class="pr-field"><span>Discount type</span><select class="t-select" id="od-ct"><option value="percentage">Percentage off</option><option value="fixed">Amount off</option></select></label>
      <label class="pr-field"><span id="od-cv-l">Percentage</span><input class="t-input" id="od-cv" type="number" min="1" step="1" value="10" required></label>
    </div>
    <label class="pr-field"><span>Event</span><select class="t-select" id="od-code-event"></select></label>
    <div class="pr-form__two">
      <label class="pr-field"><span>Use limit <small>(optional)</small></span><input class="t-input" id="od-cl" type="number" min="1" placeholder="No limit"></label>
      <label class="pr-field"><span>Minimum order <small>(optional)</small></span><input class="t-input" id="od-code-min" type="number" min="0" step="0.01" placeholder="0"></label>
    </div>
    <label class="pr-field"><span>Ends on <small>(optional)</small></span><input class="t-input" id="od-code-end" type="date"></label>
    <p class="pv-preview" id="od-code-preview" aria-live="polite"></p>
    <p class="pr-msg" id="od-code-m" role="alert"></p>
    <div class="pr-form__actions"><button class="t-btn t-btn--ghost" type="button" data-close>Cancel</button><button class="t-btn t-btn--primary" type="submit" id="od-code-go">Create code</button></div>
  </form>
</dialog>
<dialog class="od-dlg pr-dlg pr-drawer" id="pr-link-dlg" aria-labelledby="pr-link-h">
  <div class="sh-head"><div><h2 id="pr-link-h">New tracking link</h2><p id="pr-link-p">Share it with one channel or promoter to see what it sells.</p></div><button class="sh-x" type="button" data-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
  <form class="pr-form" id="od-link-f" novalidate>
    <div class="pv-chips" role="group" aria-label="Where you will share it"><button type="button" data-chan="Instagram">Instagram</button><button type="button" data-chan="WhatsApp">WhatsApp</button><button type="button" data-chan="X">X</button><button type="button" data-chan="TikTok">TikTok</button><button type="button" data-chan="Facebook">Facebook</button><button type="button" data-chan="Email">Email</button></div>
    <label class="pr-field"><span>Link name</span><input class="t-input" id="od-ln" type="text" placeholder="e.g. Chioma's Instagram" maxlength="120" required></label>
    <div class="pr-form__two">
      <label class="pr-field"><span>Channel <small>(optional)</small></span><input class="t-input" id="od-link-channel" type="text" placeholder="Instagram" maxlength="80"></label>
      <label class="pr-field"><span>Influencer <small>(optional)</small></span><input class="t-input" id="od-link-influencer" type="text" placeholder="Chioma" maxlength="120"></label>
    </div>
    <label class="pr-field"><span>Event</span><select class="t-select" id="od-link-event"></select></label>
    <p class="pr-msg" id="od-link-m" role="alert"></p>
    <div class="pr-form__actions"><button class="t-btn t-btn--ghost" type="button" data-close>Cancel</button><button class="t-btn t-btn--primary" type="submit" id="od-link-go">Create link</button></div>
  </form>
</dialog>
<dialog class="od-dlg pr-dlg pr-drawer" id="pr-choose-dlg" aria-labelledby="pr-choose-h">
  <div class="sh-head"><div><span class="pv-kicker">New promotion</span><h2 id="pr-choose-h">Create a promotion</h2><p>Pick one. Each takes a minute or two.</p></div><button class="sh-x" type="button" data-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
  <div class="pv-choose">
    <button class="pv-opt" type="button" data-prnew="code"><span class="pv-opt__ico pv-tone-code"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12V4h8l9.5 9.5-8 8z"/><circle cx="7.5" cy="8.5" r="1.5"/></svg></span><span><b>Promo code</b><small>Money or a percentage off tickets</small></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
    <button class="pv-opt" type="button" data-prnew="link"><span class="pv-opt__ico pv-tone-link"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1 1"/><path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1-1"/></svg></span><span><b>Tracking link</b><small>A link for each place you post</small></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
    <button class="pv-opt" type="button" data-prnew="featured"><span class="pv-opt__ico pv-tone-feat"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5l2.6 5.4 5.9.8-4.3 4.1 1 5.8L12 16.8l-5.2 2.8 1-5.8-4.3-4.1 5.9-.8z"/></svg></span><span><b>Featured placement</b><small>Home page, Discover or a pop-up across TICA</small></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
  </div>
</dialog>
<dialog class="od-dlg pr-dlg pr-drawer" id="pr-feat-dlg" aria-labelledby="pr-feat-h">
  <div class="sh-head"><div><span class="pv-kicker">Featured placement</span><h2 id="pr-feat-h">Get featured on TICA</h2></div><button class="sh-x" type="button" data-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
  <div class="pv-steps" id="pr-feat-steps"><span id="pr-feat-step">Step 1 of 3 · Where</span><div class="pv-bars" aria-hidden="true"><i></i><i></i><i></i></div></div>
  <form class="pr-form pv-feat" id="od-promo-f" novalidate>
    <div class="pv-pane" data-fstep="1">
      <p class="pv-q">Where should your event appear?</p>
      <div class="pv-plans" id="od-package-grid" role="radiogroup" aria-label="Placement"></div>
      <div class="od-empty-state" id="od-promo-empty" hidden><h3>No placements available right now</h3><p>TICA will publish new placements here.</p></div>
    </div>
    <div class="pv-pane" data-fstep="2" hidden>
      <p class="pv-q">Which event, and when should it start?</p>
      <label class="pr-field"><span>Event</span><select class="t-select" id="od-promo-event" required></select></label>
      <label class="pr-field"><span>Start date</span><input class="t-input" id="od-promo-start" type="date" required></label>
      <p class="pv-runs" id="od-package-summary" aria-live="polite"></p>
      <details class="pv-extra"><summary>Add your own image and words <small>(optional)</small></summary>
        <label class="pr-field"><span>Headline</span><input class="t-input" id="od-promo-headline" maxlength="160"></label>
        <label class="pr-field"><span>Short description</span><textarea class="t-input" id="od-promo-body" maxlength="1000" rows="3"></textarea></label>
        <label class="pr-field"><span>Link to open <small>(leave blank for your event page)</small></span><input class="t-input" id="od-promo-url" type="url" placeholder="https://..."></label>
        <label class="pr-field"><span>Image <small>JPG, PNG, WebP or GIF up to 3 MB</small></span><input class="t-input" id="od-promo-creative" type="file" accept="image/jpeg,image/png,image/webp,image/gif"></label>
      </details>
    </div>
    <div class="pv-pane" data-fstep="3" hidden>
      <p class="pv-q">Check and pay</p>
      <div class="pv-ticket" id="pr-feat-card"></div>
      <dl class="pv-sum" id="pr-feat-sum"></dl>
      <p class="pv-fine" id="pr-feat-note"></p>
    </div>
    <div class="pv-pane pv-done" data-fstep="4" hidden>
      <span class="pv-done__ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
      <h3 id="pr-feat-done-h">Sent for review</h3>
      <p id="pr-feat-done-p"></p>
      <ol class="pv-next"><li><b>We check your request</b><small>Usually within one working day.</small></li><li><b>You get an email</b><small>Approved, or what to change.</small></li><li><b>It goes live on the start date</b><small>Clicks and sales show on this page.</small></li></ol>
    </div>
    <p class="pr-msg" id="od-promo-m" role="alert"></p>
    <div class="pr-form__actions pv-foot">
      <button class="t-btn t-btn--ghost" type="button" id="pr-f-back">Back</button>
      <button class="t-btn t-btn--secondary" type="button" id="pr-f-balance" hidden>Use TICA balance</button>
      <button class="t-btn t-btn--primary" type="button" id="pr-f-next">Continue</button>
      <button class="t-btn pv-pay" type="submit" id="pr-f-pay" hidden>Pay</button>
      <button class="t-btn t-btn--primary" type="button" id="pr-f-done" data-close hidden>Done</button>
    </div>
  </form>
</dialog>
<dialog class="od-dlg pr-dlg pr-qr" id="pr-qr-dlg" aria-labelledby="pr-qr-h">
  <div class="sh-head"><div><h2 id="pr-qr-h">QR code</h2><p id="pr-qr-p"></p></div><button class="sh-x" type="button" data-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
  <canvas id="pr-qr-c" width="320" height="320" role="img" aria-label="QR code for this tracking link"></canvas>
  <div class="pr-form__actions"><button class="t-btn t-btn--secondary" type="button" id="pr-qr-dl">Download PNG</button></div>
</dialog>
<dialog class="od-dlg pr-dlg" id="pr-pay-dlg" aria-labelledby="pr-pay-h">
  <div class="sh-head"><div><h2 id="pr-pay-h">Use TICA balance</h2><p>Confirm this promotion payment with your organiser password.</p></div><button class="sh-x" type="button" data-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
  <form class="pr-form" id="od-promo-pay-f" novalidate>
    <label class="pr-field"><span>Current password</span><input class="t-input" id="od-promo-password" type="password" autocomplete="current-password" required></label>
    <p class="pr-msg" id="od-promo-pay-m" role="alert"></p>
    <div class="pr-form__actions"><button class="t-btn t-btn--ghost" type="button" data-close>Cancel</button><button class="t-btn t-btn--primary" type="submit">Confirm payment</button></div>
  </form>
</dialog>
<dialog class="od-dlg" id="od-ev-dlg" aria-labelledby="od-ev-h"><div id="od-ev-body"></div></dialog>
<dialog class="od-dlg" id="od-wd" aria-labelledby="od-wd-h"><div id="od-wd-body"></div></dialog>
<dialog class="od-dlg" id="od-msg" aria-labelledby="od-msg-h">
  <div class="sh-head"><div><h2 id="od-msg-h">Message attendees</h2><p>Sent by email and SMS from TICA, with your organiser name.</p></div><button class="sh-x" type="button" data-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
  <form class="od-form" id="od-msg-f" novalidate>
    <div class="inp"><label for="od-ma">Send to</label><select id="od-ma"><option>Everyone with a ticket to Afterdark Owerri (348)</option><option>Afterdark Owerri, VIP only (52)</option><option>Everyone with a ticket to Kulture Market (132)</option><option>People who haven't checked in yet</option></select></div>
    <div class="inp"><label for="od-mt">Message</label><textarea id="od-mt" rows="4">Hi! Doors open at 9:30 PM. Bring your QR code and a valid ID. See you tonight.</textarea></div>
    <label class="od-chk"><input type="checkbox" checked> Also send as SMS <small>(₦4 per text, taken from your sales)</small></label>
    <button class="btn btn-hot" type="submit">Send message</button><p class="hint" id="od-msg-m" role="status"></p>
  </form>
</dialog>
<dialog class="od-dlg" id="od-comp" aria-labelledby="od-comp-h">
  <div class="sh-head"><div><h2 id="od-comp-h">Add a guest</h2><p>Send a free ticket to a guest, artist or sponsor. It counts toward capacity.</p></div><button class="sh-x" type="button" data-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
  <form class="od-form" id="od-comp-f" novalidate>
    <div class="od-row2"><div class="inp"><label for="od-gn">Guest name</label><input id="od-gn" type="text"></div><div class="inp"><label for="od-ge">Email</label><input id="od-ge" type="email"></div></div>
    <div class="od-row2"><div class="inp"><label for="od-gv">Event</label><select id="od-gv"><option>Afterdark Owerri</option><option>Kulture Market</option><option>Freelance Money Masterclass</option></select></div><div class="inp"><label for="od-gt">Ticket</label><select id="od-gt"><option>Regular</option><option>VIP</option></select></div></div>
    <div class="inp" id="od-gs-wrap" hidden><label for="od-gs">Reserved seat</label><select id="od-gs"><option value="">Choose an available seat</option></select><small>The seat is held and assigned when the complimentary ticket is issued.</small></div>
    <button class="btn btn-hot" type="submit">Send free ticket</button><p class="hint" id="od-comp-m" role="status"></p>
  </form>
</dialog>

<!-- ACCOUNT -->
@endverbatim
