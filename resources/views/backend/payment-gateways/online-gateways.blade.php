@extends('backend.layout')

@php
  $credentialPlaceholder = __('Leave blank to keep the saved credential, or enter a new value to rotate it.');
@endphp

@section('content')
  @include('backend.integrations._moved')
  <div class="page-header">
    <h4 class="page-title">{{ __('Online Gateways') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home">
        <a href="{{ route('admin.dashboard') }}">
          <i class="flaticon-home"></i>
        </a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="#">{{ __('Payment Gateways') }}</a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="#">{{ __('Online Gateways') }}</a>
      </li>
    </ul>
  </div>

  <div class="alert alert-info" role="status">
    <strong>{{ __('Transactional gateway policy') }}:</strong>
    {{ __('Only gateways backed by the certified durable payment contract can be activated for normal use.') }}
    {{ __('Certified event gateways:') }}
    <strong>{{ collect($certifiedEventGateways ?? [])->map(fn ($gateway) => ucfirst($gateway))->join(', ') ?: __('None') }}</strong>.
    @if ($legacyActivationAllowed ?? false)
      {{ __('The explicit local/testing integration-development override is active; legacy gateways must not be used for real payments.') }}
    @else
      {{ __('Legacy gateway activation is blocked in this environment.') }}
    @endif
  </div>

  <div class="row">

    {{-- paystack --}}
    <div class="col-lg-4">
      <div class="card">
        <form action="{{ route('admin.payment_gateways.update_paystack_info') }}" method="post">
          @csrf
          <div class="card-header">
            <div class="row">
              <div class="col-lg-12">
                <div class="card-title">{{ __('Paystack') }}</div>
              </div>
            </div>
          </div>

          <div class="card-body">
            <div class="row">
              <div class="col-lg-12">
                <div class="form-group">
                  <label>{{ __('Paystack Status') }}</label>
                  <div class="selectgroup w-100">
                    <label class="selectgroup-item">
                      <input type="radio" name="paystack_status" value="1" class="selectgroup-input"
                        {{ $paystack->status == 1 ? 'checked' : '' }}>
                      <span class="selectgroup-button">{{ __('Active') }}</span>
                    </label>
                    <label class="selectgroup-item">
                      <input type="radio" name="paystack_status" value="0" class="selectgroup-input"
                        {{ $paystack->status == 0 ? 'checked' : '' }}>
                      <span class="selectgroup-button">{{ __('Deactive') }}</span>
                    </label>
                  </div>
                  @if ($errors->has('paystack_status'))
                    <p class="mt-1 mb-0 text-danger">{{ $errors->first('paystack_status') }}</p>
                  @endif
                </div>

                @php $paystackInfo = json_decode($paystack->information, true); @endphp

                <div class="form-group">
                  <label>{{ __('Paystack Secret Key') }}</label>
                  <input type="password" class="form-control" name="paystack_key" value=""
                    autocomplete="new-password"
                    placeholder="{{ !empty($paystackInfo['key']) ? __('Configured — leave blank to keep') : __('Enter Paystack secret key') }}">
                  <small class="form-text text-muted">{{ __('Saved credentials are never displayed. Enter a value only to rotate this key.') }}</small>
                  @if ($errors->has('paystack_key'))
                    <p class="mt-1 mb-0 text-danger">{{ $errors->first('paystack_key') }}</p>
                  @endif
                </div>
              </div>
            </div>
          </div>

          <div class="card-footer">
            <div class="row">
              <div class="col-12 text-center">
                <button type="submit" class="btn btn-success">
                  {{ __('Update') }}
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
    {{-- monnify --}}
    @if ($monnify)
      @php
        $monnifyInfo = json_decode($monnify->information, true) ?: [];
      @endphp
      <div class="col-lg-4">
        <div class="card">
          <form action="{{ route('admin.payment_gateways.update_monnify_info') }}" method="post">
            @csrf
            <div class="card-header">
              <div class="row">
                <div class="col-lg-12">
                  <div class="card-title">{{ __('Monnify') }}</div>
                </div>
              </div>
            </div>

            <div class="card-body">
              <p class="text-muted mb-3">{{ __('Card, bank transfer and USSD payments in naira. Events priced in other currencies use Paystack.') }}</p>
              <div class="form-group">
                <label>{{ __('Monnify Status') }}</label>
                <div class="selectgroup w-100">
                  <label class="selectgroup-item">
                    <input type="radio" name="monnify_status" value="1" class="selectgroup-input" {{ $monnify->status == 1 ? 'checked' : '' }}>
                    <span class="selectgroup-button">{{ __('Active') }}</span>
                  </label>
                  <label class="selectgroup-item">
                    <input type="radio" name="monnify_status" value="0" class="selectgroup-input" {{ $monnify->status == 0 ? 'checked' : '' }}>
                    <span class="selectgroup-button">{{ __('Deactive') }}</span>
                  </label>
                </div>
                @if ($errors->has('monnify_status'))
                  <p class="mt-1 mb-0 text-danger">{{ $errors->first('monnify_status') }}</p>
                @endif
              </div>

              <div class="form-group">
                <label>{{ __('Mode') }}</label>
                <div class="selectgroup w-100">
                  <label class="selectgroup-item">
                    <input type="radio" name="monnify_mode" value="sandbox" class="selectgroup-input" {{ ($monnifyInfo['mode'] ?? 'sandbox') !== 'live' ? 'checked' : '' }}>
                    <span class="selectgroup-button">{{ __('Sandbox (test)') }}</span>
                  </label>
                  <label class="selectgroup-item">
                    <input type="radio" name="monnify_mode" value="live" class="selectgroup-input" {{ ($monnifyInfo['mode'] ?? '') === 'live' ? 'checked' : '' }}>
                    <span class="selectgroup-button">{{ __('Live') }}</span>
                  </label>
                </div>
              </div>

              <div class="form-group">
                <label>{{ __('API Key') }}</label>
                <input type="password" class="form-control" name="monnify_api_key" value="" autocomplete="new-password"
                  placeholder="{{ !empty($monnifyInfo['api_key']) ? __('Configured — leave blank to keep') : 'MK_TEST_…' }}">
                @if ($errors->has('monnify_api_key'))
                  <p class="mt-1 mb-0 text-danger">{{ $errors->first('monnify_api_key') }}</p>
                @endif
              </div>

              <div class="form-group">
                <label>{{ __('Secret Key') }}</label>
                <input type="password" class="form-control" name="monnify_secret_key" value="" autocomplete="new-password"
                  placeholder="{{ !empty($monnifyInfo['secret_key']) ? __('Configured — leave blank to keep') : __('Enter Monnify secret key') }}">
                @if ($errors->has('monnify_secret_key'))
                  <p class="mt-1 mb-0 text-danger">{{ $errors->first('monnify_secret_key') }}</p>
                @endif
              </div>

              <div class="form-group">
                <label>{{ __('Contract Code') }}</label>
                <input type="text" class="form-control" name="monnify_contract_code" value="{{ old('monnify_contract_code', $monnifyInfo['contract_code'] ?? '') }}" placeholder="1234567890">
                @if ($errors->has('monnify_contract_code'))
                  <p class="mt-1 mb-0 text-danger">{{ $errors->first('monnify_contract_code') }}</p>
                @endif
              </div>

              <small class="form-text text-muted">{{ __('Keys are stored encrypted and never shown again. In the Monnify dashboard, set the Transaction Completion webhook to:') }} <code>{{ route('monnify.webhook') }}</code></small>
            </div>

            <div class="card-footer">
              <div class="row">
                <div class="col-12 text-center">
                  <button type="submit" class="btn btn-success">{{ __('Update') }}</button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    @endif

    {{-- Flutterwave --}}
    <div class="col-lg-4">
      <div class="card">
        <form action="{{ route('admin.payment_gateways.update_flutterwave_info') }}" method="post">
          @csrf
          <div class="card-header">
            <div class="row">
              <div class="col-lg-12">
                <div class="card-title">{{ __('Flutterwave') }}</div>
              </div>
            </div>
          </div>

          <div class="card-body">
            <div class="row">
              <div class="col-lg-12">
                <div class="form-group">
                  <label>{{ __('Flutterwave Status') }}</label>
                  <div class="selectgroup w-100">
                    <label class="selectgroup-item">
                      <input type="radio" name="flutterwave_status" value="1" class="selectgroup-input"
                        {{ $flutterwave->status == 1 ? 'checked' : '' }}>
                      <span class="selectgroup-button">{{ __('Active') }}</span>
                    </label>
                    <label class="selectgroup-item">
                      <input type="radio" name="flutterwave_status" value="0" class="selectgroup-input"
                        {{ $flutterwave->status == 0 ? 'checked' : '' }}>
                      <span class="selectgroup-button">{{ __('Deactive') }}</span>
                    </label>
                  </div>
                  @if ($errors->has('flutterwave_status'))
                    <p class="mt-1 mb-0 text-danger">{{ $errors->first('flutterwave_status') }}</p>
                  @endif
                </div>

                @php $flutterwaveInfo = json_decode($flutterwave->information, true); @endphp

                <div class="form-group">
                  <label>{{ __('Flutterwave Public Key') }}</label>
                  <input type="text" class="form-control" name="flutterwave_public_key"
                    value="{{ $flutterwaveInfo['public_key'] }}">
                  @if ($errors->has('flutterwave_public_key'))
                    <p class="mt-1 mb-0 text-danger">{{ $errors->first('flutterwave_public_key') }}</p>
                  @endif
                </div>

                <div class="form-group">
                  <label>{{ __('Flutterwave Secret Key') }}</label>
                  <input type="password" class="form-control" name="flutterwave_secret_key" value=""
                    autocomplete="new-password"
                    placeholder="{{ !empty($flutterwaveInfo['secret_key']) ? __('Configured — leave blank to keep') : __('Enter Flutterwave secret key') }}">
                  <small class="form-text text-muted">{{ __('Saved credentials are never displayed. Enter a value only to rotate this key.') }}</small>
                  @if ($errors->has('flutterwave_secret_key'))
                    <p class="mt-1 mb-0 text-danger">{{ $errors->first('flutterwave_secret_key') }}</p>
                  @endif
                </div>
              </div>
            </div>
          </div>

          <div class="card-footer">
            <div class="row">
              <div class="col-12 text-center">
                <button type="submit" class="btn btn-success">
                  {{ __('Update') }}
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
    {{-- Stripe --}}
    <div class="col-lg-4">
      <div class="card">
        <form action="{{ route('admin.payment_gateways.update_stripe_info') }}" method="post">
          @csrf
          <div class="card-header">
            <div class="row">
              <div class="col-lg-12">
                <div class="card-title">{{ __('Stripe') }}</div>
              </div>
            </div>
          </div>

          <div class="card-body">
            <div class="row">
              <div class="col-lg-12">
                <div class="form-group">
                  <label>{{ __('Stripe Status') }}</label>
                  <div class="selectgroup w-100">
                    <label class="selectgroup-item">
                      <input type="radio" name="stripe_status" value="1" class="selectgroup-input"
                        {{ $stripe->status == 1 ? 'checked' : '' }}>
                      <span class="selectgroup-button">{{ __('Active') }}</span>
                    </label>
                    <label class="selectgroup-item">
                      <input type="radio" name="stripe_status" value="0" class="selectgroup-input"
                        {{ $stripe->status == 0 ? 'checked' : '' }}>
                      <span class="selectgroup-button">{{ __('Deactive') }}</span>
                    </label>
                  </div>
                  @if ($errors->has('stripe_status'))
                    <p class="mt-1 mb-0 text-danger">{{ $errors->first('stripe_status') }}</p>
                  @endif
                </div>

                @php $stripeInfo = json_decode($stripe->information, true) ?: []; @endphp

                <div class="form-group">
                  <label>{{ __('Stripe Key') }}</label>
                  <input type="text" class="form-control" name="stripe_key" value="{{ $stripeInfo['key'] ?? '' }}" placeholder="pk_test_…">
                  @if ($errors->has('stripe_key'))
                    <p class="mt-1 mb-0 text-danger">{{ $errors->first('stripe_key') }}</p>
                  @endif
                </div>

                <div class="form-group">
                  <label>{{ __('Stripe Secret') }}</label>
                  <input type="password" class="form-control" name="stripe_secret" value=""
                    autocomplete="new-password" placeholder="{{ $credentialPlaceholder }}">
                  @if ($errors->has('stripe_secret'))
                    <p class="mt-1 mb-0 text-danger">{{ $errors->first('stripe_secret') }}</p>
                  @endif
                </div>

                <div class="form-group">
                  <label>{{ __('Webhook Signing Secret') }}</label>
                  <input type="password" class="form-control" name="stripe_webhook_secret" value=""
                    autocomplete="new-password" placeholder="{{ !empty($stripeInfo['webhook_secret']) ? __('Configured — leave blank to keep') : 'whsec_…' }}">
                  @if ($errors->has('stripe_webhook_secret'))
                    <p class="mt-1 mb-0 text-danger">{{ $errors->first('stripe_webhook_secret') }}</p>
                  @endif
                </div>
                <small class="form-text text-muted">{{ __('Stripe Checkout takes card payments in naira, cedi, shilling, rand, pound, dollar, euro and dirham. In Stripe → Developers → Webhooks, add this endpoint with the checkout.session events:') }} <code>{{ route('stripe.webhook') }}</code></small>
              </div>
            </div>
          </div>

          <div class="card-footer">
            <div class="row">
              <div class="col-12 text-center">
                <button type="submit" class="btn btn-success">
                  {{ __('Update') }}
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection
