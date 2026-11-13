@verbatim
<section class="page" data-page="create">
  <div class="ce-shell" id="ce-shell"><div class="ce-side" id="ce-side"></div><div class="ce-shell-main">
  <div class="tint full"><div class="wrap pagehead creategrid">
    <form id="create-form" class="panel ce" novalidate>
      <h1 class="display">Create your event</h1>
      <p class="lead">Fill this in and watch your event card build itself. You can change anything later.</p>
      <section class="ce-admin" id="ce-admin" hidden aria-labelledby="ce-admin-title">
        <div class="ce-admin-head"><span class="ce-admin-kicker">Admin controls</span><div><h2 id="ce-admin-title">Event ownership</h2><p>Create for TICA or assign the event to an organizer.</p></div></div>
        <div class="ce-admin-grid">
          <div class="inp"><label for="c-owner">Event owner</label><select id="c-owner" name="organizer_id"><option value="">TICA · platform-managed event</option></select><small id="c-owner-note">The selected organizer will manage tickets, attendees and payouts.</small></div>
          <label class="ce-admin-feature" for="c-featured"><span><b>Feature this event</b><small>Give it priority in approved featured placements.</small></span><input class="ck-sw" type="checkbox" id="c-featured" name="is_featured"></label>
          <div class="inp ce-admin-edit" hidden><label for="c-status">Status</label><select id="c-status"><option value="live">Live, people can buy tickets</option><option value="draft">Hidden, saved as a draft</option></select></div>
        </div>
        <div class="ce-admin-danger ce-admin-edit" hidden><span><b>Delete this event</b><small>Removes the event, its tickets and its page for good.</small></span><button class="btn btn-line ce-del" type="button" id="ce-delete">Delete event</button></div>
      </section>
      <ol class="ce-steps" aria-label="Steps"><li><a href="#ce-basics" data-ce="ce-basics">1. Basics</a></li><li><a href="#ce-where" data-ce="ce-where">2. Where</a></li><li><a href="#ce-tix" data-ce="ce-tix">3. Tickets</a></li><li><a href="#ce-fee" data-ce="ce-fee">4. Fees and payout</a></li><li><a href="#ce-more" data-ce="ce-more">5. Settings</a></li><li><a href="#ce-review" data-ce="ce-review">6. Review</a></li></ol>
      <p class="ce-progress" id="ce-progress" aria-live="polite">Step 1 of 6</p>

      <fieldset id="ce-basics"><legend>The basics</legend>
        <div class="inp"><label for="c-name">Event name</label><input id="c-name" name="name" type="text" maxlength="255" placeholder="Give it a name people will remember"></div>
        <div class="two">
          <div class="inp"><label for="c-cat">Category</label><select id="c-cat" name="category_id"><option>Party and nightlife</option><option>Concert or show</option><option>Campus</option><option>Conference or class</option><option>Faith</option><option>Sport and fitness</option><option>Art and food</option></select></div>
          <div class="inp"><label for="c-age">Who can come</label><select id="c-age" name="age_restriction"><option value="">All ages</option><option>18+ only, with ID</option><option>16+ only</option></select></div>
        </div>
        <div class="ce-one">
          <div class="two">
            <div class="inp"><label for="c-date">Date</label><input id="c-date" name="start_date" type="date"></div>
            <div class="inp"><label for="c-time">Start time</label><input id="c-time" name="start_time" type="time"></div>
          </div>
          <div class="two">
            <div class="inp"><label for="c-edate">End date</label><input id="c-edate" name="end_date" type="date"></div>
            <div class="inp"><label for="c-etime">End time</label><input id="c-etime" name="end_time" type="time"></div>
          </div>
        </div>
        <ul class="od-sw ce-sw"><li><span><b>Runs on more than one date</b><small>Add each date. Buyers choose one at checkout.</small></span><input class="ck-sw" type="checkbox" id="c-multi" aria-label="Runs on more than one date"></li></ul>
        <div class="ce-dates" id="ce-dates" hidden></div>
        <button class="ce-add" type="button" id="ce-add-date" hidden>+ Add a date</button>
        <div class="inp"><label for="c-desc">Description</label><textarea id="c-desc" name="description" rows="3" placeholder="What should people expect? Line-up, dress code, what's included."></textarea></div>
      </fieldset>

      <fieldset id="ce-where"><legend>Where it happens</legend>
        <div class="ce-mode" role="radiogroup" aria-label="Event type">
          <label><input type="radio" name="c-mode" value="In person" checked><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s7-6.5 7-12a7 7 0 0 0-14 0c0 5.5 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg><b>In person</b><small>At a venue</small></span></label>
          <label><input type="radio" name="c-mode" value="Online"><span><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="12" rx="2"/><path d="M8 21h8M12 17v4"/></svg><b>Online</b><small>Zoom, Meet, live stream</small></span></label>
          <label><input type="radio" name="c-mode" value="Hybrid"><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 20s5-4.6 5-8.5a5 5 0 0 0-10 0C3 15.4 8 20 8 20z"/><rect x="14" y="4" width="8" height="6" rx="1"/></svg><b>Both</b><small>Venue and live stream</small></span></label>
        </div>
        <div class="two">
          <div class="inp"><label for="c-country">Country</label><select id="c-country" name="country"></select></div>
          <div class="inp"><label for="c-cur">Currency</label><select id="c-cur" name="currency"></select></div>
        </div>
        <p class="hint" id="c-cur-note">Buyers pay in this currency. It's set from the country, and you can change it.</p>
        <div class="ce-in">
          <div class="two">
            <div class="inp"><label for="c-venue">Venue</label><input id="c-venue" name="address" type="text" placeholder="Where is it happening?"></div>
            <div class="inp"><label for="c-city">City</label><input id="c-city" name="city" type="text" placeholder="Owerri"></div>
          </div>
          <div class="inp"><label for="c-state">State or region</label><input id="c-state" name="state" type="text" placeholder="Imo"></div>
        </div>
        <div class="ce-on" hidden>
          <div class="two">
            <div class="inp"><label for="c-plat">Platform</label><select id="c-plat"><option>Zoom</option><option>Google Meet</option><option>YouTube Live</option><option>Instagram Live</option><option>Other link</option></select></div>
            <div class="inp"><label for="c-rev">Show the join link</label><select id="c-rev" name="join_reveal_minutes"><option value="15">15 minutes before</option><option value="30">30 minutes before</option><option value="60">1 hour before</option><option value="0">Right after purchase</option></select></div>
          </div>
          <div class="inp"><label for="c-link">Join link</label><input id="c-link" name="online_join_url" type="url" placeholder="https://zoom.us/j/..."></div>
          <p class="hint">Only ticket holders see the link, on their ticket and by email. You can add it later.</p>
        </div>
      </fieldset>

      <fieldset id="ce-tix"><legend>Your tickets</legend>
        <div class="ce-mode ce-how" role="radiogroup" aria-label="How people get in">
          <label><input type="radio" name="c-entry" value="ticketing" checked><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h16v3a2 2 0 0 0 0 3v3H4v-3a2 2 0 0 0 0-3zM14 8v2M14 13v1M14 16v1"/></svg><b>Tickets</b><small>Sell tickets or give them away free</small></span></label>
          <label><input type="radio" name="c-entry" value="corporate"><span><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg><b>Registration</b><small>People apply and you approve them</small></span></label>
        </div>
        <div class="ce-sell">
          <div class="ce-tiers" id="ce-tiers">
            <div class="ce-tier"><div class="inp"><label for="c-tier">Ticket name</label><input id="c-tier" type="text" value="Regular" placeholder="Regular"></div><div class="inp"><label for="c-price">Price (₦)</label><input id="c-price" type="number" min="0" step="500" placeholder="e.g. 5000" inputmode="decimal"></div><div class="inp"><label for="c-qty">Quantity</label><input id="c-qty" type="number" min="1" step="1" placeholder="e.g. 200" inputmode="numeric"></div></div>
          </div>
          <button class="ce-add" type="button" id="ce-add">+ Add a ticket type</button>
          <p class="hint">Use 0 for free tickets. You can add early bird, VIP or table tickets, and set when each one goes on sale.</p>
        </div>
        <div class="ce-reg" hidden>
          <div class="two">
            <div class="inp"><label for="c-reg-name">Form name</label><input id="c-reg-name" name="registration_form_name" type="text" placeholder="Delegate registration"></div>
            <div class="inp"><label for="c-reg-mode">Approval</label><select id="c-reg-mode" name="registration_approval"><option value="manual">I approve each person</option><option value="automatic">Approve everyone automatically</option></select></div>
          </div>
          <div class="inp"><label for="c-reg-intro">Message on the form</label><textarea id="c-reg-intro" name="registration_intro" rows="2" placeholder="Who should apply, and what happens next."></textarea></div>
          <p class="hint">After publishing, add your questions, sessions and badges from the event's registration page.</p>
        </div>
      </fieldset>

      <fieldset id="ce-fee"><legend>Fees and payout</legend>
        <p class="ce-q">Who pays the <span data-fee-pct>6%</span> booking fee?</p>
        <div class="ce-payer" role="radiogroup" aria-label="Who pays the booking fee">
          <label><input type="radio" name="c-payer" value="buyer" checked><span><b>Buyers pay it</b><small id="ce-ex-b">Buyer pays ₦5,300. You get ₦5,000.</small></span></label>
          <label><input type="radio" name="c-payer" value="me"><span><b>I'll include it</b><small id="ce-ex-m">Buyer pays ₦5,000. You get ₦4,700.</small></span></label>
        </div>
        <div class="ce-bank" id="ce-bank"><span><b>Payout account</b><small>Not added yet. Add it before your event so you're paid on time.</small></span><a class="btn btn-line" href="/dashboard/payouts">Add bank account</a></div>
      </fieldset>

      <fieldset id="ce-more"><legend>Settings</legend>
        <div class="two">
          <div class="inp"><label for="c-vis">Who can find it</label><select id="c-vis" name="visibility"><option value="public">Public, on Discover</option><option value="unlisted">Link only</option><option value="private">Private, by invite</option></select></div>
          <div class="inp"><label for="c-ref">Refunds</label><select id="c-ref" name="refund_policy"><option value="">TICA standard</option><option>Up to 7 days before</option><option>Up to 48 hours before</option></select></div>
        </div>
        <ul class="od-sw ce-sw">
          <li><span><b>Show tickets left</b></span><input class="ck-sw" type="checkbox" id="c-left" checked aria-label="Show tickets left"></li>
          <li><span><b>Waitlist when sold out</b></span><input class="ck-sw" type="checkbox" id="c-wait" checked aria-label="Waitlist when sold out"></li>
          <li><span><b>Let buyers transfer tickets</b><small>Buyers can pass an unused ticket to someone else before the event.</small></span><input class="ck-sw" type="checkbox" id="c-trans" checked aria-label="Let buyers transfer tickets"></li>
          <li><span><b>Ask each attendee a question</b><small>For example, T-shirt size or dietary needs</small></span><input class="ck-sw" type="checkbox" id="c-askq" aria-label="Ask each attendee a question"></li>
          <li><span><b>Show a countdown</b><small>On your event page, counting down to the start</small></span><input class="ck-sw" type="checkbox" id="c-cd" checked aria-label="Show a countdown"></li>
        </ul>
        <div class="inp ce-qx" hidden><label for="c-question">Your question</label><input id="c-question" name="attendee_question" type="text" maxlength="200" placeholder="What's your T-shirt size?"></div>
        <div class="inp"><label for="c-flyer">Flyer</label><input id="c-flyer" type="file" accept="image/*"></div>
        <p class="hint">Upload a flyer to see it on the card. JPG or PNG, up to 5 MB.</p>
        <div class="two">
          <div class="inp"><label for="c-seo">Search description</label><input id="c-seo" name="meta_description" type="text" maxlength="300" placeholder="One line for Google and link previews"></div>
          <div class="inp"><label for="c-keys">Search keywords</label><input id="c-keys" name="meta_keywords" type="text" maxlength="300" placeholder="afrobeats, owerri, concert"></div>
        </div>
      </fieldset>
      <fieldset id="ce-review"><legend>Review and publish</legend>
        <dl class="ce-sum" id="ce-sum"></dl>
        <p class="hint">Publish to put it on sale now, or save a draft and finish later.</p>
      </fieldset>
      <div class="ce-go ce-nav"><button class="btn btn-line" type="button" id="ce-back">Back</button><button class="btn btn-hot" type="button" id="ce-next">Continue</button><button class="btn btn-line" type="button" id="ce-draft" hidden>Save draft</button><button class="btn btn-hot" type="submit" id="ce-publish" hidden>Publish event</button></div>
      <p class="hint" id="c-msg" role="status"></p>
    </form>
    <aside class="preview" aria-label="Preview of your event card">
      <p class="previewlabel">Live preview</p>
      <article class="card">
        <div>
          <div class="flyer" id="p-flyer"><div class="f pblank"><div class="t" id="p-art">Your flyer here</div></div></div>
        </div>
        <div class="cardbody">
          <span class="tag ce-tag" id="p-mode" hidden>Online</span>
          <p class="cdate"><span id="p-day">--</span> <span id="p-mon">Date</span></p><h3 id="p-name">Your event name</h3>
          <p class="meta" id="p-meta">Time and venue</p>
          <div class="buy"><div class="price" id="p-price" hidden></div><span class="cdm" id="p-left"></span></div>
          <p class="ce-pf" id="p-fee" hidden></p>
        </div>
      </article>
    </aside>
  </div></div>
  </div></div>
</section>

<!-- POLICIES -->
@endverbatim
