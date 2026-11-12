<div class="modal fade bd-example-modal-lg" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel"
  aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
    <div class="modal-content p-2">
      <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
      </button>

      <iframe
        src="//maps.google.com/maps?width=100%25&amp;height=600&amp;hl=en&amp;q={{ $map_address }}&amp;t=&amp;z=14&amp;ie=UTF8&amp;iwloc=B&amp;output=embed"
        height="380" style="border:0; width: 100%;max-height:600px" allowfullscreen="" loading="lazy"></iframe>


    </div>
  </div>
</div>

@php
  $shareUrl = url()->current();
  $shareTitle = isset($content) && !empty($content->title)
      ? $content->title
      : ($websiteInfo->website_title ?? 'Tica');
  $shareMessage = __('Discover') . ' ' . $shareTitle . ' ' . __('on Tica');
@endphp
<div class="modal fade share-event pulse-share-modal" tabindex="-1" role="dialog"
  aria-labelledby="pulse-share-title" aria-hidden="true" data-share-url="{{ $shareUrl }}"
  data-share-title="{{ $shareTitle }}">
  <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <span class="pulse-share-eyebrow">{{ __('Share the moment') }}</span>
          <h2 id="pulse-share-title">{{ __('Share this event') }}</h2>
          <p>{{ __('Invite your people with a link, QR code, or social post.') }}</p>
        </div>
        <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close share dialog') }}">
          <i class="fas fa-times" aria-hidden="true"></i>
        </button>
      </div>

      <div class="modal-body">
        <section class="pulse-share-link-card" aria-labelledby="pulse-share-link-title">
          <div class="pulse-share-card-title">
            <i class="fas fa-link" aria-hidden="true"></i>
            <h3 id="pulse-share-link-title">{{ __('Event link') }}</h3>
          </div>
          <p class="pulse-share-link-preview">{{ preg_replace('#^https?://#', '', $shareUrl) }}</p>
          <div class="pulse-share-copy-row">
            <input type="text" value="{{ $shareUrl }}" readonly aria-label="{{ __('Event link') }}"
              data-share-url-input>
            <button type="button" data-copy-event-link>
              <i class="far fa-copy" aria-hidden="true"></i><span>{{ __('Copy link') }}</span>
            </button>
          </div>

          <div class="pulse-share-divider"><span>{{ __('Share to') }}</span></div>
          <div class="pulse-share-socials">
            <a class="pulse-share-social pulse-share-social--whatsapp"
              href="https://wa.me/?text={{ urlencode($shareMessage . ' ' . $shareUrl) }}" target="_blank"
              rel="noopener noreferrer" aria-label="{{ __('Share on WhatsApp') }}">
              <i class="fab fa-whatsapp" aria-hidden="true"></i><span>{{ __('WhatsApp') }}</span>
            </a>
            <a class="pulse-share-social" href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}"
              target="_blank" rel="noopener noreferrer" aria-label="{{ __('Share on Facebook') }}">
              <i class="fab fa-facebook-f" aria-hidden="true"></i><span>{{ __('Facebook') }}</span>
            </a>
            <a class="pulse-share-social"
              href="https://twitter.com/intent/tweet?text={{ urlencode($shareMessage) }}&amp;url={{ urlencode($shareUrl) }}"
              target="_blank" rel="noopener noreferrer" aria-label="{{ __('Share on X') }}">
              <i class="fab fa-twitter" aria-hidden="true"></i><span>{{ __('X / Twitter') }}</span>
            </a>
            <a class="pulse-share-social"
              href="https://www.linkedin.com/shareArticle?mini=true&amp;url={{ urlencode($shareUrl) }}&amp;title={{ urlencode($shareTitle) }}"
              target="_blank" rel="noopener noreferrer" aria-label="{{ __('Share on LinkedIn') }}">
              <i class="fab fa-linkedin-in" aria-hidden="true"></i><span>{{ __('LinkedIn') }}</span>
            </a>
            <a class="pulse-share-social"
              href="mailto:?subject={{ urlencode($shareTitle) }}&amp;body={{ urlencode($shareMessage . ' ' . $shareUrl) }}"
              aria-label="{{ __('Share by email') }}">
              <i class="far fa-envelope" aria-hidden="true"></i><span>{{ __('Email') }}</span>
            </a>
            <button class="pulse-share-social pulse-share-native" type="button" data-native-share hidden>
              <i class="fas fa-share-alt" aria-hidden="true"></i><span>{{ __('More') }}</span>
            </button>
          </div>
        </section>

        <aside class="pulse-share-qr-card" aria-labelledby="pulse-share-qr-title">
          <span class="pulse-share-qr-kicker">{{ __('Scan to open') }}</span>
          <h3 id="pulse-share-qr-title">{{ __('Take Tica with you') }}</h3>
          <p>{{ __('Guests can scan this code to open the event page instantly.') }}</p>
          <div class="pulse-share-qr" data-share-qr aria-label="{{ __('QR code for this event') }}">
            {!! QrCode::size(160)->margin(2)->generate($shareUrl) !!}
          </div>
          <a class="pulse-share-qr-download" href="#" download="tica-event-qr.svg" data-download-qr>
            <i class="fas fa-qrcode" aria-hidden="true"></i>{{ __('Download QR code') }}
          </a>
        </aside>
      </div>

      <div class="pulse-share-status" role="status" aria-live="polite" data-share-status></div>
    </div>
  </div>
</div>
