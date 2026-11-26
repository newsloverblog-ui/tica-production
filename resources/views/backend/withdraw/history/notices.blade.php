@if ($notice = session('payout_review_notice'))
  <div class="alert {{ $notice['success'] ? 'alert-success' : 'alert-warning' }}" role="{{ $notice['success'] ? 'status' : 'alert' }}">
    {{ $notice['message'] }}
  </div>
@elseif (session('warning') || session('success'))
  <div class="alert {{ session('warning') ? 'alert-warning' : 'alert-success' }}" role="{{ session('warning') ? 'alert' : 'status' }}">
    {{ session('warning') ?? session('success') }}
  </div>
@endif
@if ($errors->getBag('default')->isNotEmpty())
  <div class="alert alert-warning" role="alert">
    <ul class="mb-0">
      @foreach ($errors->getBag('default')->all() as $message)
        <li>{{ $message }}</li>
      @endforeach
    </ul>
  </div>
@endif
