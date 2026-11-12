<?php

namespace App\Models\BasicSettings;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Basic extends Model
{
  use HasFactory;

  public const CREATED_AT = null;

  public const ENCRYPTED_SECRET_PREFIX = 'enc:v1:';

  public const SECRET_ATTRIBUTES = [
    'smtp_password',
    'google_recaptcha_secret_key',
    'facebook_app_secret',
    'google_client_secret',
  ];

  protected $table = 'basic_settings';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
    'uniqid',
    'favicon',
    'logo',
    'website_title',
    'email_address',
    'contact_number',
    'address',
    'latitude',
    'longitude',
    'theme_version',
    'base_currency_symbol',
    'base_currency_symbol_position',
    'base_currency_text',
    'base_currency_text_position',
    'base_currency_rate',
    'primary_color',
    'breadcrumb_overlay_color',
    'breadcrumb_overlay_opacity',
    'smtp_status',
    'smtp_host',
    'smtp_port',
    'encryption',
    'smtp_username',
    'smtp_password',
    'from_mail',
    'from_name',
    'to_mail',
    'breadcrumb',
    'disqus_status',
    'disqus_short_name',
    'google_recaptcha_status',
    'google_recaptcha_site_key',
    'google_recaptcha_secret_key',
    'facebook_login_status',
    'facebook_app_id',
    'facebook_app_secret',
    'google_login_status',
    'google_client_id',
    'google_client_secret',
    'whatsapp_status',
    'whatsapp_number',
    'whatsapp_header_title',
    'whatsapp_popup_status',
    'whatsapp_popup_message',
    'maintenance_img',
    'maintenance_status',
    'maintenance_msg',
    'footer_logo',
    'admin_theme_version',
    'features_section_image',
    'testimonials_section_image',
    'course_categories_section_image',
    'notification_image',
    'google_adsense_publisher_id',
    'shop_status',
    'catalog_mode',
    'is_shop_rating',
    'shop_guest_checkout',
    'shop_tax',
  ];

  public function getSmtpPasswordAttribute(?string $value): ?string
  {
    return self::decryptSecret($value);
  }

  public function setSmtpPasswordAttribute(?string $value): void
  {
    $this->attributes['smtp_password'] = self::encryptSecret($value);
  }

  public function getGoogleRecaptchaSecretKeyAttribute(?string $value): ?string
  {
    return self::decryptSecret($value);
  }

  public function setGoogleRecaptchaSecretKeyAttribute(?string $value): void
  {
    $this->attributes['google_recaptcha_secret_key'] = self::encryptSecret($value);
  }

  public function getFacebookAppSecretAttribute(?string $value): ?string
  {
    return self::decryptSecret($value);
  }

  public function setFacebookAppSecretAttribute(?string $value): void
  {
    $this->attributes['facebook_app_secret'] = self::encryptSecret($value);
  }

  public function getGoogleClientSecretAttribute(?string $value): ?string
  {
    return self::decryptSecret($value);
  }

  public function setGoogleClientSecretAttribute(?string $value): void
  {
    $this->attributes['google_client_secret'] = self::encryptSecret($value);
  }

  public static function encryptSecret(?string $value): ?string
  {
    if ($value === null || $value === '' || self::secretIsEncrypted($value)) {
      return $value;
    }

    return self::ENCRYPTED_SECRET_PREFIX . Crypt::encryptString($value);
  }

  public static function decryptSecret(?string $value): ?string
  {
    if ($value === null || !self::secretIsEncrypted($value)) {
      return $value;
    }

    return Crypt::decryptString(substr($value, strlen(self::ENCRYPTED_SECRET_PREFIX)));
  }

  public static function secretIsEncrypted(?string $value): bool
  {
    return is_string($value) && str_starts_with($value, self::ENCRYPTED_SECRET_PREFIX);
  }
}
