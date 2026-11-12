<?php

namespace App\Models\PaymentGateway;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class OnlineGateway extends Model
{
  use HasFactory;

  public const ENCRYPTED_INFORMATION_PREFIX = 'enc:v1:';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = ['name', 'keyword', 'information', 'status'];

  public function getInformationAttribute(?string $value): ?string
  {
    return self::decryptInformation($value);
  }

  public function setInformationAttribute(?string $value): void
  {
    $this->attributes['information'] = self::encryptInformation($value);
  }

  public static function encryptInformation(?string $value): ?string
  {
    if ($value === null) {
      return null;
    }

    if (self::informationIsEncrypted($value)) {
      return $value;
    }

    return self::ENCRYPTED_INFORMATION_PREFIX . Crypt::encryptString($value);
  }

  public static function decryptInformation(?string $value): ?string
  {
    if ($value === null || !self::informationIsEncrypted($value)) {
      return $value;
    }

    return Crypt::decryptString(substr($value, strlen(self::ENCRYPTED_INFORMATION_PREFIX)));
  }

  public static function informationIsEncrypted(?string $value): bool
  {
    return is_string($value) && str_starts_with($value, self::ENCRYPTED_INFORMATION_PREFIX);
  }

  // as the timestamps is not needed, so make it false.
  public $timestamps = false;
}
