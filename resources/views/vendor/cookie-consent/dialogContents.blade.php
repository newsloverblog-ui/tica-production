<div class="js-cookie-consent cookie-consent">
  <div class="container">
    <div class="cookie-container">
      <span class="cookie-consent__message">
        {!! preg_replace(
          '~(\shref\s*=\s*["\'])(?:\.{1,2}/)*/?privacy-policy/?(["\'])~i',
          '$1/policies/privacy$2',
          $cookieAlertInfo->cookie_alert_text
        ) !!}
      </span>

      <button class="js-cookie-consent-agree cookie-consent__agree">
        {{ $cookieAlertInfo->cookie_alert_btn_text }}
      </button>
    </div>
  </div>
</div>
