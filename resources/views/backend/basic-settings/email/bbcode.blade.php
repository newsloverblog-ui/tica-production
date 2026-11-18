<div class="col-lg-5">
  <div class="card border-0 bg-light h-100">
    <div class="card-body">
      <h5 class="mb-2">{{ __('Available placeholders') }}</h5>
      <p class="text-muted mb-3">{{ __('Insert these exactly as shown. TICA replaces them when the email is queued.') }}</p>
      <div class="table-responsive">
        <table class="table table-sm table-borderless mb-0">
          <tbody>
            @foreach ($availablePlaceholders as $placeholder)
              <tr>
                <td><code>{<span>{{ $placeholder }}</span>}</code></td>
                <td>{{ __($placeholderLabels[$placeholder] ?? str_replace('_', ' ', ucfirst($placeholder))) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
