<!DOCTYPE html>
<html>

<head lang="{{ $currentLanguageInfo->code }}" @if ($currentLanguageInfo->direction == 1) dir="rtl" @endif>
  {{-- required meta tags --}}
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">

  {{-- title --}}
  <title>{{ 'Invoice | ' . config('app.name') }}</title>

  @php
    $invoiceAsset = static function (string $path, ?string $url = null): string {
      $local = public_path($path);
      return is_file($local) ? $local : ($url ?? asset($path));
    };
    $brandFile = basename((string) $websiteInfo->logo);
    $brandUrl = \App\Support\PublicImage::url('assets/admin/img', $brandFile);
    $invoiceLogo = $brandUrl
      ? $invoiceAsset('assets/admin/img/' . $brandFile, $brandUrl)
      : $invoiceAsset('assets/tica-ui/img/tica-logo.png');
  @endphp
  {{-- styles --}}
  <link rel="stylesheet" href="{{ $invoiceAsset('assets/admin/css/bootstrap.min.css') }}">
  <link rel="stylesheet" href="{{ $invoiceAsset('assets/admin/css/invoice.css') }}">
  @php
    $_15px = '15px';
    $_10px = '10px';
    $_12px = '12px';
    $b_color = '565656';
    $w_47 = '47%';
    
  @endphp
  <style>
    body {
      font-size: {{ $_15px }};
    }

    .border {
      border-color: #{{ $b_color }} !important;
    }

    .bg-primary {
      background: #{{ $basicInfo->primary_color }} !important;
    }

    p {
      font-size: {{ $_12px }};
      margin-bottom: {{ $_10px }};
    }

    .tica-ticket {
      border: 1px solid #dfe5ef;
      border-top: 5px solid var(--ticket-accent, #245fd3);
      border-radius: 16px;
      padding: 20px;
      margin-bottom: 18px;
      background: linear-gradient(145deg, #fff 0%, #f7f9fd 100%);
      page-break-inside: avoid;
    }
    .tica-ticket__event { color: #12213b; font-size: 19px; margin-bottom: 4px; }
    .tica-ticket__logo { width: 52px; height: 52px; object-fit: cover; border-radius: 11px; margin: 0 auto 10px; border: 1px solid #dfe5ef; }
    .tica-ticket__number { color: var(--ticket-accent, #245fd3); font-size: 22px; letter-spacing: .03em; }
    .tica-ticket__meta { color: #526079; }
    .tica-ticket__id { color: #77849b; font-size: 9px; overflow-wrap: anywhere; }
    @media (max-width: 767px) {
      .float-left, .float-right { float: none !important; width: 100% !important; }
      .tica-ticket { padding: 16px; }
    }
  </style>
</head>

<body>
  <div class="my-5">
    <div class="row">
      <div class="col-lg-12">
        <div class="logo text-center" style="margin-bottom: {{ $mb }};">
          <img src="{{ $invoiceLogo }}" alt="Company Logo">
        </div>


        @php
          $position = $bookingInfo->currencyTextPosition;
          $currency = $bookingInfo->currencyText;
        @endphp

        <div class="clearfix">
          {{-- enrolment details start --}}
          <div class="float-left px-1" style="width: {{ $w_47 }}">
            <div class="p-3 border mt-5 mb-2">
              <h6 class="mt-2 mb-3">{{ __('Booking Details') }}</h6>
              <p>
                {{ __('Booking ID') . ': ' }} <span class="text-muted">{{ '#' . $bookingInfo->booking_id }}</span>
              </p>
              <p>
                {{ __('Booking Date') . ': ' }} <span
                  class="text-muted">{{ date_format($bookingInfo->created_at, 'M d, Y') }}</span>
              </p>

              <p>
                {{ __('Event Name') . ': ' }} <span class="text-muted">{{ @$eventInfo->title }}</span>
              </p>

              <p>
                {{ __('Event Start Date') . ': ' }} <span class="text-muted">
                  {{ FullDateTimeInvoice($bookingInfo->event_date) }}
                </span>
              </p>

              @if (@$bookingInfo->evnt->date_type == 'single')
                <p>
                  {{ __('Event End Date') . ': ' }} <span class="text-muted">
                    {{ Carbon\Carbon::parse(@$bookingInfo->evnt->end_date . @$bookingInfo->evnt->end_time)->format('d M, Y h:ia') }}
                  </span>
                </p>
                <p>
                  {{ __('Duration') . ': ' }} <span class="text-muted">
                    {{ @$bookingInfo->evnt->duration }}
                  </span>
                </p>
              @else
                @php
                  $date = Carbon\Carbon::parse($bookingInfo->event_date)->format('Y-m-d');
                  $time = Carbon\Carbon::parse($bookingInfo->event_date)->format('H:i');
                  $evnt = @$bookingInfo->evnt
                      ->dates()
                      ->where('start_date', $date)
                      ->where('start_time', $time)
                      ->first();
                @endphp

                <p>
                  {{ __('Event End Date') . ': ' }} <span class="text-muted">
                    @if (!empty($evnt))
                      {{ Carbon\Carbon::parse(@$evnt->end_date . @$evnt->end_time)->format('D, M d, Y H:i a') }}
                    @endif
                  </span>
                </p>

                <p>
                  {{ __('Duration') . ': ' }} <span class="text-muted">
                    @if (!empty($evnt))
                      {{ $evnt->duration }}
                    @endif
                  </span>
                </p>
              @endif

              <p>
                {{ __('Booking fee') }} : <span
                  class="text-muted">{{ $position == 'left' ? $currency . ' ' : '' }}{{ is_null($bookingInfo->tax) ? '0.00' : $bookingInfo->tax }}{{ $position == 'right' ? ' ' . $currency : '' }}</span>
              </p>


              <p>
                {{ __('Early Bird Discount') . ': ' }} <span
                  class="text-muted">{{ $position == 'left' ? $currency . ' ' : '' }}{{ is_null($bookingInfo->early_bird_discount) ? '0.00' : $bookingInfo->early_bird_discount }}{{ $position == 'right' ? ' ' . $currency : '' }}</span>
              </p>
              <p>
                {{ __('Coupon Discount') . ': ' }} <span
                  class="text-muted">{{ $position == 'left' ? $currency . ' ' : '' }}{{ is_null($bookingInfo->discount) ? '0.00' : $bookingInfo->discount }}{{ $position == 'right' ? ' ' . $currency : '' }}</span>
              </p>

              <p>
                {{ __('Total Paid') . ': ' }} <span
                  class="text-muted">{{ $position == 'left' ? $currency . ' ' : '' }}{{ $bookingInfo->price + $bookingInfo->tax }}{{ $position == 'right' ? ' ' . $currency : '' }}</span>
              </p>

              <p>
                {{ __('Payment Method') . ': ' }} <span
                  class="text-muted">{{ is_null($bookingInfo->paymentMethod) ? '-' : $bookingInfo->paymentMethod }}
              </p>

              <p>
                {{ __('Payment Status') . ': ' }} <span class="text-muted">
                  @if ($bookingInfo->paymentStatus == 'completed')
                    {{ __('Completed') }}
                  @elseif ($bookingInfo->paymentStatus == 'pending')
                    {{ __('Pending') }}
                  @elseif ($bookingInfo->paymentStatus == 'rejected')t
                    {{ __('Rejected') }}
                  @else
                    -
                  @endif
                </span>
              </p>

              <p>
                {{ __('Quantity') . ': ' }} <span class="text-muted">
                  @if (!is_null($bookingInfo->quantity))
                    {{ $bookingInfo->quantity }}
                  @else
                    -
                  @endif
                </span>
              </p>

            </div>
          </div>
          {{-- billing details start --}}
          <div class="float-right px-1" style="width: {{ $w_47 }}">
            <div class="p-3 border mt-5 mb-2">
              @php $ticketUnits = $bookingInfo->relationLoaded('ticketUnits') ? $bookingInfo->ticketUnits : $bookingInfo->ticketUnits()->get(); @endphp
              @forelse ($ticketUnits as $ticketUnit)
                @php
                  $design = (array) optional($ticketUnit->ticket)->design_settings;
                  $show = static fn ($field) => !array_key_exists('show_'.$field, $design) || filter_var($design['show_'.$field], FILTER_VALIDATE_BOOLEAN);
                  $accent = preg_match('/\A#[0-9A-Fa-f]{6}\z/', (string) ($design['accent'] ?? '')) ? $design['accent'] : '#245fd3';
                  $category = $ticketUnit->variation_name ?: optional($ticketUnit->ticket)->title;
                  $venue = collect([optional($eventInfo)->address, optional($eventInfo)->city])->filter()->join(', ');
                  $eventLogoName = basename((string) optional($eventInfo)->thumbnail);
                  $eventLogoUrl = \App\Support\PublicImage::flyer($eventLogoName);
                  $eventLogo = $eventLogoUrl ? $invoiceAsset('assets/admin/img/event/thumbnail/' . $eventLogoName, $eventLogoUrl) : null;
                @endphp
                <div class="tica-ticket text-center" style="--ticket-accent: {{ $accent }};">
                  @if ($show('logo') && $eventLogo)
                    <img class="tica-ticket__logo" src="{{ $eventLogo }}" alt="{{ __('Event logo') }}">
                  @endif
                  <p class="tica-ticket__event"><strong>{{ @$eventInfo->title }}</strong></p>
                  @if ($show('number'))
                    <p class="tica-ticket__number"><strong>{{ $ticketUnit->ticket_number ?: __('Ticket').' '.$ticketUnit->sequence.' '.__('of').' '.$ticketUnits->count() }}</strong></p>
                  @endif
                  @if ($show('attendee') && $ticketUnit->holder_name)
                    <p class="tica-ticket__meta">{{ $ticketUnit->holder_name }}</p>
                  @endif
                  @if ($show('category') && $category)
                    <p class="tica-ticket__meta">{{ $category }}</p>
                  @endif
                  @if ($show('date'))
                    <p class="tica-ticket__meta">{{ FullDateTimeInvoice($bookingInfo->event_date) }}</p>
                  @endif
                  @if ($show('venue') && $venue)
                    <p class="tica-ticket__meta">{{ $venue }}</p>
                  @endif
                  @php
                    $seatParts = collect([
                      $show('section') ? $ticketUnit->section_label : null,
                      $show('row') && $ticketUnit->row_label ? __('Row').' '.$ticketUnit->row_label : null,
                      $show('seat') && $ticketUnit->seat_label ? __('Seat').' '.$ticketUnit->seat_label : null,
                    ])->filter();
                  @endphp
                  @if ($seatParts->isNotEmpty())<p class="tica-ticket__meta">{{ $seatParts->join(' · ') }}</p>@endif
                  @if ($ticketUnit->status === 'issued' && $ticketUnit->qr_data_uri)
                    <img style="width: 180px; height: 180px;"
                      src="{{ $ticketUnit->qr_data_uri }}"
                      alt="{{ __('Ticket QR code') }}">
                  @else
                    <p><strong>{{ __(ucfirst(str_replace('_', ' ', $ticketUnit->status))) }}</strong></p>
                    <p class="text-muted" style="font-size: 10px;">{{ __('This admission credential is no longer active and its QR code is intentionally omitted.') }}</p>
                  @endif
                  @if ($show('status'))<p class="tica-ticket__meta">{{ __('Status') }}: {{ __(ucfirst(str_replace('_', ' ', $ticketUnit->status))) }}</p>@endif
                  <p class="tica-ticket__id">{{ __('Secure ticket ID') }}: {{ $ticketUnit->public_id }}</p>
                </div>
              @empty
                <p class="text-muted text-center">{{ __('Ticket QR code is being prepared.') }}</p>
              @endforelse
            </div>
          </div>
        </div>

        <div class="clearfix">
          @if ($bookingInfo->variation != null)
            <div class="float-left px-1" style="width: {{ $w_47 }}">
              <div class="p-3 border mt-3">
                <table class="table">
                  <thead>
                    <tr>
                      <th>{{ __('Ticket') }}</th>
                      <th>{{ __('Quantity') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @php
                      $variations = json_decode($bookingInfo->variation, true);
                    @endphp
                    @foreach ($variations as $variation)
                      <tr>
                        <td>
                          @php
                            $ticket = App\Models\Event\Ticket::where('id', $variation['ticket_id'])->first();
                            
                            $ticketContent = App\Models\Event\TicketContent::where([['ticket_id', $variation['ticket_id'], ['language_id', $currentLanguageInfo->id]]])->first();
                            if (empty($ticketContent)) {
                                $ticketContent = App\Models\Event\TicketContent::where([['ticket_id', $variation['ticket_id']]])->first();
                            }
                          @endphp

                          @if ($ticketContent && $ticket)
                            <small>
                              {{ $ticketContent->title }}
                              @if ($ticket->pricing_type == 'variation')
                                @php
                                  $varition_key = App\Models\Event\VariationContent::where([['ticket_id', $ticket->id], ['name', $variation['name']]])
                                      ->select('key')
                                      ->first();
                                  $de_lang = App\Models\Language::where('is_default', 1)->first();
                                  
                                  $varition_name = App\Models\Event\VariationContent::where([['ticket_id', $ticket->id], ['language_id', $de_lang->id], ['key', $varition_key->key]])->first();
                                  
                                  if ($varition_name) {
                                      $name = $varition_name->name;
                                  } else {
                                      $name = '';
                                  }
                                @endphp
                                ({{ $name }})
                              @endif
                            </small>
                          @endif
                        </td>
                        <td>
                          {{ $variation['qty'] }}
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          @endif


          <div class="float-right px-1" style="width: {{ $w_47 }}">
            <div class="p-3 border mt-3">
              <div class="mt-2 mb-3">
                <h6>{{ __('Billing Details') }}</h6>
              </div>

              <p>
                {{ __('Name') . ': ' }} <span class="text-muted">{{ $bookingInfo->fname . ' ' . $bookingInfo->lname }}
                </span>
              </p>

              <p>
                {{ __('Email') . ': ' }} <span class="text-muted">{{ $bookingInfo->email }} </span>
              </p>

              <p>
                {{ __('Contact Number') . ': ' }} <span class="text-muted">{{ $bookingInfo->phone }} </span>
              </p>

              <!--<p>-->
              <!--  {{ __('Address') . ': ' }} <span class="text-muted">{{ $bookingInfo->address }} </span>-->
              <!--</p>-->

              <!--<p>-->
              <!--  {{ __('City') . ': ' }} <span class="text-muted">{{ $bookingInfo->city }} </span>-->
              <!--</p>-->

              <!--<p>-->
              <!--  {{ __('State') . ': ' }} <span-->
              <!--    class="text-muted">{{ is_null($bookingInfo->state) ? '-' : $bookingInfo->state }} </span>-->
              <!--</p>-->

              <!--<p>-->
              <!--  {{ __('Country') . ': ' }} <span class="text-muted">{{ $bookingInfo->country }} </span>-->
              <!--</p>-->
            </div>
          </div>
          {{-- billing details end --}}
        </div>
      </div>
    </div>
  </div>
</body>

</html>
