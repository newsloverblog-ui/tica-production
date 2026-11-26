@php
  $feeCurrency = $payload['currency'];
  $flatAmount = rtrim(rtrim(number_format($feeCurrency['feeFlat'], 2), '0'), '.');
  $flatFee = $feeCurrency['position'] === 'right'
    ? $flatAmount . $feeCurrency['symbol']
    : $feeCurrency['symbol'] . $flatAmount;
@endphp
<section class="page" data-page="policies" data-policy-view="all">
<div class="lg-head"><div class="wrap">
  <h1 class="x-h1" data-policy-title>Terms and policies</h1>
</div></div>
<div class="wrap lg">
  <nav class="lg-nav" aria-label="On this page"><p>On this page</p><ul>
    <li><a href="/policies/summary" data-pol="summary">Key points</a></li>
    <li><a href="/policies/terms" data-pol="terms">Terms of use</a></li>
    <li><a href="/policies/tickets" data-pol="tickets">Buying tickets</a></li>
    <li><a href="/policies/refunds" data-pol="refunds">Refunds</a></li>
    <li><a href="/policies/organisers" data-pol="organisers">Organiser terms</a></li>
    <li><a href="/policies/privacy" data-pol="privacy">Privacy policy</a></li>
    <li><a href="/policies/cookies" data-pol="cookies">Cookies and location</a></li>
    <li><a href="/policies/contact" data-pol="contact">Contact</a></li>
  </ul></nav>

  <div class="policy lg-body">
  <section id="pol-summary" class="lg-sec">
    <h2>Key points</h2>
    <ul class="lg-keys">
      <li><b>You see the full price before you pay.</b>Paid tickets carry a {{ $feeCurrency['feePct'] }}% + {{ $flatFee }} booking fee per ticket. The organiser either adds it at checkout or includes it in the price. Free tickets have no fee.</li>
      <li><b>Cancelled event, full refund.</b>If an event is cancelled, you get back everything you paid, including the booking fee.</li>
      <li><b>One ticket, one entry.</b>Each QR code works once. Don't share it or buy tickets from resellers.</li>
      <li><b>We don't sell your data.</b>Organisers only see the details of people attending their own events.</li>
      <li><b>Optional cookies stay off until you say yes.</b>You can change your choice at any time.</li>
    </ul>
  </section>

  <section id="pol-terms" class="lg-sec">
    <h2>Terms of use</h2>
    <h3>Who we are</h3>
    <p>TICA is run by TICA Innovations, a company registered in Nigeria. In these terms, "TICA", "we" and "us" mean TICA Innovations.</p>
    <p>TICA is a ticketing platform. We sell tickets on behalf of event organisers. The organiser runs the event and is responsible for it. Each event page shows who the organiser is.</p>
    <h3>Agreeing to these terms</h3>
    <p>By using TICA or buying a ticket, you agree to these terms. If you run events, the organiser terms below also apply to you. If you don't agree, please don't use TICA.</p>
    <h3>Your account</h3>
    <ul>
      <li>You must be 18 or older to create an account or buy tickets.</li>
      <li>Give accurate details and keep your password private. Tell us straight away at <a href="mailto:tickets@tica.ng">tickets@tica.ng</a> if you think someone else has used your account.</li>
      <li>You can close your account at any time by emailing <a href="mailto:hello@tica.ng">hello@tica.ng</a>.</li>
      <li>We may suspend or close accounts used for fraud, abuse or anything that breaks these terms. Where it's safe to do so, we'll tell you why.</li>
    </ul>
    <h3>What you can't do on TICA</h3>
    <ul>
      <li>List fake or misleading events, or pretend to be someone else.</li>
      <li>Use bots or scripts to buy tickets, or copy content from the site in bulk.</li>
      <li>Try to get around our security, or interfere with how the site works.</li>
      <li>Harass other people, or send us reports you know are false.</li>
    </ul>
    <h3>Event content</h3>
    <p>Organisers own their flyers, photos and descriptions, and they're responsible for them. By publishing an event, an organiser lets TICA show and promote that content to sell tickets. If you think an event breaks the law or these terms, use <b>Report this event</b> on its page or email <a href="mailto:reports@tica.ng">reports@tica.ng</a>.</p>
    <h3>Our responsibility to you</h3>
    <p>We're responsible for selling and delivering your tickets and handling your payment as these terms describe. The organiser is responsible for the event itself, including what happens at the venue, the line-up and safety on the day.</p>
    <p>As far as the law allows, our total responsibility to you for any order is limited to the amount you paid for that order. Nothing in these terms limits responsibility that the law doesn't allow us to limit, including for death or injury caused by our negligence, or your rights under the Federal Competition and Consumer Protection Act 2018.</p>
    <h3>Changes to these terms</h3>
    <p>We may update these terms. If a change affects you significantly, we'll tell you by email before it applies. Orders already placed follow the terms in force when you bought.</p>
    <h3>Law and disputes</h3>
    <p>These terms are governed by the laws of the Federal Republic of Nigeria. If something goes wrong, please contact us first so we can try to fix it. Any dispute will be decided by the courts of Nigeria.</p>
  </section>

  <section id="pol-tickets" class="lg-sec">
    <h2>Buying tickets</h2>
    <ul>
      <li><b>Prices.</b> Prices are in naira. Paid tickets carry a booking fee of {{ $feeCurrency['feePct'] }}% of the ticket price plus {{ $flatFee }} per paid ticket. The organiser decides whether it's added at checkout or included in the ticket price. Either way, you see the full total before you pay. Free tickets have no fee.</li>
      <li><b>Holding your tickets.</b> When you start checkout, we hold your tickets for 10 minutes. If you don't pay in time, they go back on sale.</li>
      <li><b>Payment.</b> Payments are handled by Paystack, by card, bank transfer or USSD. Your order is confirmed when Paystack confirms the payment and we send your tickets. If the payment fails, no order is made and you aren't charged.</li>
      <li><b>Tickets for other people.</b> Each ticket carries an attendee's name and email. If you enter someone else's details, make sure they're happy for you to do so. We only use them to send that ticket and updates about the event.</li>
      <li><b>One ticket, one entry.</b> Each ticket has a QR code that works once. Only the first scan is let in, so keep your code private and don't post it online.</li>
      <li><b>Entry conditions.</b> Organisers set conditions such as age limits, ID checks and bag searches, and show them on the event page. The organiser or venue may refuse entry, or ask someone to leave, for unsafe or unlawful behaviour. If you're refused entry because you didn't meet a condition shown before you bought, you won't get a refund.</li>
      <li><b>Resale.</b> Only buy tickets from TICA or the organiser. Tickets may not be resold for more than their original price. We may cancel tickets offered for sale above their original price.</li>
      <li><b>Lost tickets.</b> Find them under <a href="/tickets">My tickets</a>, or email <a href="mailto:tickets@tica.ng">tickets@tica.ng</a> and we'll send them again.</li>
    </ul>
  </section>

  <section id="pol-refunds" class="lg-sec">
    <h2>Refunds</h2>
    <div class="lg-table" role="table" aria-label="When you get a refund">
      <div class="lg-tr lg-th" role="row"><span role="columnheader">What happened</span><span role="columnheader">What you get</span></div>
      <div class="lg-tr" role="row"><span role="cell">The event is cancelled</span><span role="cell">A full refund of everything you paid, including the booking fee. You don't need to ask.</span></div>
      <div class="lg-tr" role="row"><span role="cell">The event moves to a new date, a new city, or the main advertised act no longer appears</span><span role="cell">Your ticket stays valid. If you'd rather not go, ask for a full refund at least 48 hours before the event starts.</span></div>
      <div class="lg-tr" role="row"><span role="cell">You were charged twice, or paid but got no ticket</span><span role="cell">A refund of the extra charge, or your ticket, once Paystack confirms the payment.</span></div>
      <div class="lg-tr" role="row"><span role="cell">You can't go any more</span><span role="cell">No refund, unless the organiser's own policy on the event page allows one. You can pass your ticket to someone else (see below).</span></div>
    </div>
    <h3>How refunds are paid</h3>
    <ul>
      <li>We pay refunds back to the card or account you paid with. We start them within 3 working days, and they usually arrive within 7 to 10 working days, depending on your bank.</li>
      <li>To ask for a refund, email <a href="mailto:tickets@tica.ng">tickets@tica.ng</a> from the email you used, with your order number.</li>
    </ul>
    <h3>Passing your ticket to someone else</h3>
    <p>Email <a href="mailto:tickets@tica.ng">tickets@tica.ng</a> from the email on the order at least 24 hours before the event, with the new attendee's name and email. We'll issue a new ticket in their name, and the old QR code will stop working.</p>
    <h3>If something went wrong at the event</h3>
    <p>If you were wrongly refused entry, or the event was very different from what was advertised, tell us at <a href="mailto:tickets@tica.ng">tickets@tica.ng</a> as soon as you can, and within 7 days of the event. If you contact us before the organiser is paid, we can hold their payout while we look into it.</p>
    <p class="lg-note">Nothing in this policy affects your rights under Nigerian consumer protection law.</p>
  </section>

  <section id="pol-organisers" class="lg-sec">
    <h2>Organiser terms</h2>
    <p>These terms apply when you create or sell tickets for an event on TICA, together with the terms of use above.</p>
    <h3>Getting verified</h3>
    <ul>
      <li>You must be 18 or older, acting for yourself or for a business you're allowed to represent.</li>
      <li>Before your first payout, we check your identity and a bank account in your name or your business's name. The verified badge means we've completed these checks. It doesn't guarantee an event.</li>
    </ul>
    <h3>Your event listing</h3>
    <ul>
      <li>Keep the date, time, venue, line-up, prices, age limits and entry conditions accurate and up to date.</li>
      <li>Don't sell more tickets than the venue can safely hold.</li>
      <li>Get any permits, licences and venue permission your event needs, and follow the law and safety rules on the day.</li>
      <li>You may offer a more generous refund policy than ours. If you do, show it on your event page and honour it.</li>
    </ul>
    <h3>Events we don't allow</h3>
    <p>Events that are illegal, fraudulent or misleading; events that promote hatred or violence; explicit adult content; and the sale of weapons, drugs or unlicensed gambling. We may unpublish events that break these rules. If we do, buyers are refunded.</p>
    <h3>Fees and payouts</h3>
    <ul>
      <li>For each event, you choose who pays the {{ $feeCurrency['feePct'] }}% + {{ $flatFee }} per paid ticket booking fee. If buyers pay it, you receive the full ticket price. If you include it, we take it from the ticket price, up to the ticket subtotal. Free events cost nothing.</li>
      <li>Your sales are released 3 working days after your event ends, less any refunds and chargebacks. We send them to your verified bank account automatically, or when you withdraw, as you choose in Payouts.</li>
      <li>Payouts only go to a bank account in your name or your business's name. We check the account name with your bank before saving it, ask for a code sent to your phone, and hold payouts for 24 hours after any change.</li>
      <li>We may hold a payout if your event is cancelled or changed, if we receive many complaints, if there's a chargeback, or if we suspect fraud. We'll tell you why and what we need from you.</li>
      <li>You're responsible for any tax on your ticket income.</li>
    </ul>
    <h3>Cancelling or changing an event</h3>
    <ul>
      <li>Tell us straight away at <a href="mailto:hello@tica.ng">hello@tica.ng</a>, and tell your attendees through TICA.</li>
      <li>If you cancel, we refund buyers in full from the sales we hold. Booking fees refunded because of your cancellation may be taken from your payouts. If refunds are more than we hold, you must pay us the difference within 14 days.</li>
    </ul>
    <h3>Your attendees' information</h3>
    <p>You can see the names, emails and phone numbers of people attending your events. You're responsible for how you use them, under the Nigeria Data Protection Act 2023. You may use them only to run the event and contact attendees about it. Only send marketing to attendees who agreed to hear from you. Keep the information secure, don't sell it, and delete it when you no longer need it.</p>
  </section>

  <section id="pol-privacy" class="lg-sec privacy-policy">
    <h2>Privacy policy</h2>
    <p class="privacy-intro">TICA Innovations uses personal information to sell and deliver tickets, support organisers and keep payments safe. We do not sell personal information.</p>

    <div class="privacy-promise" aria-label="Our privacy promises">
      <p><b>Only what we need</b><span>Information that helps us run your account, tickets and payments.</span></p>
      <p><b>Shared with purpose</b><span>Organisers see details only for people attending their events.</span></p>
      <p><b>You stay in control</b><span>You can ask to access, correct, download or delete your information.</span></p>
    </div>

    <div class="privacy-sections">
      <section>
        <h3>Information we collect</h3>
        <ul>
          <li>Your name, email, phone number and account details.</li>
          <li>Your orders, tickets, saved events and support messages.</li>
          <li>For organisers: identity checks, organisation and payout details.</li>
          <li>Device, browser, IP address and security activity when you use TICA.</li>
        </ul>
      </section>
      <section>
        <h3>How we use it</h3>
        <ul>
          <li>Process orders, issue tickets, check entry and handle refunds.</li>
          <li>Pay organisers, prevent fraud and protect accounts.</li>
          <li>Provide support and keep financial records required by law.</li>
          <li>Use optional analytics or event recommendations only when you allow them.</li>
        </ul>
      </section>
      <section>
        <h3>Who receives it</h3>
        <p>The organiser receives the attendee details needed to run the event. Paystack processes payments. Hosting, email and messaging providers help us operate TICA. We share information with authorities only when legally required or needed to prevent harm or fraud.</p>
        <p>Some providers may process information outside Nigeria. They must protect it under their contracts with us.</p>
      </section>
      <section>
        <h3>Payments</h3>
        <p>Paystack handles card, bank and USSD details. TICA receives the payment status, reference and limited payment-method information. We do not store your full card number or PIN.</p>
      </section>
      <section>
        <h3>How long we keep it</h3>
        <ul>
          <li>Account details: while your account is open, then up to 12 months.</li>
          <li>Orders, payments and payouts: 6 years for financial records.</li>
          <li>Support messages and reports: up to 2 years.</li>
          <li>Security records: only as long as needed to investigate and prevent abuse.</li>
        </ul>
      </section>
      <section>
        <h3>Your choices</h3>
        <p>You can ask for a copy of your information, correct it, download it or ask us to delete it. We may keep records required for payments, fraud prevention or law. You can unsubscribe from marketing and withdraw optional cookie permission at any time.</p>
      </section>
      <section>
        <h3>Security and children</h3>
        <p>We encrypt information in transit, limit staff access and monitor our systems. TICA accounts and purchases are for adults. A parent or guardian may buy a ticket for a child where an event allows it.</p>
      </section>
      <section>
        <h3>Updates</h3>
        <p>We may update this policy. If a change significantly affects you, we will explain it by email or on TICA before it takes effect.</p>
      </section>
    </div>

    <div class="privacy-contact">
      <div><h3>Questions or requests</h3><p>Email <a href="mailto:hello@tica.ng?subject=Privacy">hello@tica.ng</a> with “Privacy” in the subject.</p></div>
      <p>We may confirm your identity before releasing or changing information. We normally reply within 30 days.</p>
    </div>
  </section>

  <section id="pol-cookies" class="lg-sec">
    <h2>Cookies and location</h2>
    <p>Cookies are small files saved by your browser. We only use optional cookies if you agree. You can change your choice at any time from <a href="/policies/cookies" data-cookie-open>Cookie settings</a>, also in the footer. We ask again every 12 months.</p>
    <div class="lg-table four" role="table" aria-label="Cookies TICA uses">
      <div class="lg-tr lg-th" role="row"><span role="columnheader">Cookie</span><span role="columnheader">What it does</span><span role="columnheader">Type</span><span role="columnheader">How long</span></div>
      <div class="lg-tr" role="row"><span role="cell"><code>tica_session</code></span><span role="cell">Keeps you logged in and protects forms from attack</span><span role="cell">Essential</span><span role="cell">Until you log out, or 30 days</span></div>
      <div class="lg-tr" role="row"><span role="cell"><code>tica_hold</code></span><span role="cell">Holds your tickets during checkout</span><span role="cell">Essential</span><span role="cell">10 minutes</span></div>
      <div class="lg-tr" role="row"><span role="cell"><code>tica_consent_v1</code></span><span role="cell">Remembers your cookie choices</span><span role="cell">Essential</span><span role="cell">12 months</span></div>
      <div class="lg-tr" role="row"><span role="cell">Paystack</span><span role="cell">Payment security and fraud checks inside Paystack's window</span><span role="cell">Essential, set by Paystack</span><span role="cell">Set by Paystack</span></div>
      <div class="lg-tr" role="row"><span role="cell"><code>tica_city</code></span><span role="cell">Remembers your approximate city to show events near you</span><span role="cell">Personalisation</span><span role="cell">Your visit only</span></div>
    </div>
    <h3>How we work out your location</h3>
    <p>If you allow personalisation, we estimate your city from your internet connection's IP address, to suggest events nearby. This is approximate and city-level only. We don't use your phone's GPS unless you choose "Use my location" in search, and we don't store your location.</p>
  </section>

  <section id="pol-contact" class="lg-sec">
    <h2>Contact</h2>
    <ul class="lg-contact">
      <li><b>Tickets and refunds</b><a href="mailto:tickets@tica.ng">tickets@tica.ng</a></li>
      <li><b>Privacy and everything else</b><a href="mailto:hello@tica.ng">hello@tica.ng</a></li>
      <li><b>Report an event</b><a href="mailto:reports@tica.ng">reports@tica.ng</a></li>
      <li><b>Phone</b><a href="tel:+2347073797999">0707 379 7999</a></li>
      <li><b>Company</b><span>TICA Innovations</span></li>
      <li><b>Data protection officer</b><span>Paul Unigwe</span></li>
    </ul>
  </section>
  </div>
</div>
</section>

<!-- MY TICKETS -->
