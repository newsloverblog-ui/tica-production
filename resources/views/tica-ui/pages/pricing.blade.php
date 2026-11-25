@php
  $pricingCurrency = $payload['currency'];
  $pricingAmount = rtrim(rtrim(number_format($pricingCurrency['feeFlat'], 2), '0'), '.');
  $pricingFlatFee = $pricingCurrency['position'] === 'right'
    ? $pricingAmount . $pricingCurrency['symbol']
    : $pricingCurrency['symbol'] . $pricingAmount;
  $pricingFee = $pricingCurrency['feePct'] . '% + ' . $pricingFlatFee;
@endphp
<section class="page" data-page="pricing">
  <div class="wrap x-hero">
    <div>
      <h1 class="x-h1">Ticketing fees for your event</h1>
      <p class="x-lead">Sign up for free and pay no booking fees on free tickets. For paid tickets, add the fee at checkout or include it in the price you set.</p>
      <div class="x-fee"><b>{{ $pricingFee }}</b><span>booking fee per paid ticket</span></div>
      <div class="btns"><a class="btn btn-hot" href="/create">Create an event</a></div>
    </div>
    <form class="calc" id="calc" aria-label="Fee calculator">
      <h2>Estimate ticket sales</h2>
      <p class="calc-caption">This example adds the booking fee at checkout.</p>
      <div class="c-f"><label for="c1">Ticket price</label><output id="c1o" for="c1"></output><input id="c1" type="range" min="1000" max="100000" step="500" value="5000"></div>
      <div class="c-f"><label for="c2">Tickets sold</label><output id="c2o" for="c2"></output><input id="c2" type="range" min="10" max="2000" step="10" value="200"></div>
      <dl>
        <div><dt>Booking fee per ticket</dt><dd id="c-fee"></dd></div>
        <div><dt>Buyer pays per ticket</dt><dd id="c-buy"></dd></div>
        <div class="big"><dt>You receive from sales</dt><dd id="c-get"></dd></div>
      </dl>
    </form>
  </div>

  <div class="tint"><div class="wrap x-sec">
    <h2 class="x-h2 nz">Two ways to run your event</h2>
    <div class="plans">
      <article class="plan nz"><h3>Free events</h3><b class="pr">₦0</b><p>For meetups, campus nights and community gatherings.</p><ul><li>Unlimited free tickets</li><li>In-person, online or both</li><li>Your own event page</li><li>Instant e-tickets</li><li>QR check-in at the gate</li></ul><a class="btn btn-line" href="/create">Create a free event</a></article>
      <article class="plan feat nz"><h3>Paid events</h3><b class="pr">{{ $pricingFee }} per ticket</b><p>For shows, conferences and anything you sell tickets for.</p><ul><li>Everything in free events</li><li>Multiple ticket types</li><li>Paystack checkout</li><li>Sales and payout tracking</li></ul><a class="btn btn-hot" href="/create">Start selling tickets</a></article>
    </div>
  </div></div>

  <div class="wrap x-sec">
    <div class="x-big nz">
      <img loading="lazy" data-src="https://images.unsplash.com/photo-1778847195159-874f36cf4e3b?auto=format&fit=crop&w=1400&q=72" alt="">
      <div><h2 class="x-h2">Running something bigger?</h2><p>Talk to us about festivals, tours and campus-wide events.</p><a class="btn btn-hot" href="/contact">Contact us</a></div>
    </div>
  </div>
</section>

<!-- ABOUT -->
