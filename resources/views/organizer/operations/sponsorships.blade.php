@extends('organizer.layout')

@section('page-title', __('Sponsorship packages'))
@section('style') @include('organizer.operations._styles') @endsection

@section('content')
@php $eventTitle = fn($event) => optional($event->information)->title ?: __('Untitled event'); @endphp
<div class="op-wrap">
  <header class="op-head"><div><span class="op-kicker">Partnership inventory</span><h1>Sponsorship packages</h1><p>Turn brand opportunities into clear packages. Keep packages in draft while you shape them, then mark them live when they are ready to share.</p></div></header>
  @if(session('success'))<div class="op-alert" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="op-alert op-alert--error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <div class="sp-layout">
    <section class="op-card sp-editor" aria-labelledby="sp-new-h"><div class="op-card__head"><div><h2 id="sp-new-h">Build a package</h2><p>One offer, with specific deliverables and availability.</p></div></div>
      <form class="op-body" method="POST" action="{{ route('organizer.operations.sponsorships.store') }}">@csrf
        @include('organizer.operations.sponsorship-form', ['package' => null])
        <div class="op-actions"><button class="op-btn op-btn--primary" type="submit">Create package</button></div>
      </form>
    </section>
    <section aria-labelledby="sp-list-h"><div class="op-card__head" style="padding:0 0 16px;border:0"><div><h2 id="sp-list-h">Your package board</h2><p>{{ $packages->count() }} {{ Str::plural('package', $packages->count()) }} across your events.</p></div></div>
      @if($packages->isEmpty())<div class="op-card op-empty">Create your first package. A concise offer is easier for sponsors to assess.</div>@else<div class="sp-list">
        @foreach($packages as $package)
          <article class="op-card sp-package"><div class="sp-package__top"><div><h3>{{ $package->name }}</h3><p>{{ $package->summary ?: 'No summary added yet.' }}</p></div><div class="sp-price">{{ $package->price === null ? 'Talk to us' : ($package->currency.' '.number_format((float)$package->price, 0)) }}</div></div>
            <div class="sp-meta"><span class="op-pill op-pill--{{ $package->status==='live'?'live':'draft' }}">{{ ucfirst($package->status) }}</span><span class="op-pill">{{ optional(optional($package->event)->information)->title ?: 'All events' }}</span>@if($package->available_slots)<span class="op-pill">{{ $package->available_slots }} slots</span>@endif</div>
            <ul class="sp-deliverables">@foreach($package->deliverables as $deliverable)<li>{{ $deliverable }}</li>@endforeach</ul>
            <details class="sp-edit"><summary>Edit package</summary><form class="op-body" style="padding:18px 0 0" method="POST" action="{{ route('organizer.operations.sponsorships.update',$package) }}">@csrf @include('organizer.operations.sponsorship-form', ['package'=>$package])<div class="op-actions"><button class="op-btn op-btn--primary" type="submit">Save changes</button></div></form><form method="POST" action="{{ route('organizer.operations.sponsorships.delete',$package) }}" onsubmit="return confirm('Delete this sponsorship package?')">@csrf<button class="op-btn op-btn--danger" type="submit">Delete package</button></form></details>
          </article>
        @endforeach
      </div>@endif
    </section>
  </div>
</div>
@endsection
