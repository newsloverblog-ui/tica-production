<?php

namespace App\Models;

use App\Models\Event\Booking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class Organizer extends Model implements AuthenticatableContract
{
  use HasFactory, Authenticatable;
  protected $fillable = [
    'photo',
    'email',
    'phone',
    'username',
    'password',
    'status',
    'amount',
    'facebook',
    'twitter',
    'linkedin',
    'email_verified_at',
    'kyc_status',
    'verified_legal_name',
    'kyc_verified_at',
  ];

  protected $casts = [
    'email_verified_at' => 'datetime',
    'kyc_verified_at' => 'datetime',
    'two_factor_enabled_at' => 'datetime',
  ];

  /**
   * The one place an organiser's photo URL is built. The ?v= stamp changes
   * whenever the account is saved, so a new photo shows without a hard refresh.
   * Null when there's no photo: callers show initials instead.
   */
  public function getAvatarUrlAttribute(): ?string
  {
    $file = basename((string) $this->photo);
    if ($file === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $file)) {
      return null;
    }
    $version = $this->updated_at ? $this->updated_at->getTimestamp() : null;

    return asset('assets/admin/img/organizer-photo/' . $file) . ($version ? '?v=' . $version : '');
  }

  /** Up to two initials for the avatar fallback. */
  public function getAvatarInitialsAttribute(): string
  {
    $name = trim((string) ($this->verified_legal_name ?: optional($this->organizer_info)->name ?: $this->username ?: $this->email));
    $words = preg_split('/\s+/u', $name) ?: [];
    $letters = array_map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)), array_slice(array_filter($words), 0, 2));

    return implode('', $letters) ?: 'T';
  }

  /**
   * Read a saved boolean preference without treating malformed string values
   * such as "false" as truthy. Older rows may contain either JSON booleans or
   * their form-encoded string equivalents.
   */
  public function preferenceEnabled(string $key, bool $default = false): bool
  {
    $preferences = $this->preferences;
    if (is_string($preferences)) {
      $preferences = json_decode($preferences, true);
    }
    if (!is_array($preferences) || !array_key_exists($key, $preferences)) {
      return $default;
    }

    $value = $preferences[$key];
    if (is_bool($value)) {
      return $value;
    }
    if (is_int($value) || is_float($value)) {
      return $value !== 0 && $value !== 0.0;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
  }

  //withdraw
  public function withdraws()
  {
    return $this->hasMany(Withdraw::class);
  }

  //organizer info
  public function organizer_info()
  {
    return $this->hasOne(OrganizerInfo::class);
  }

  public function kycSubmissions()
  {
    return $this->hasMany(OrganizerKycSubmission::class);
  }

  public function payoutAccounts()
  {
    return $this->hasMany(OrganizerPayoutAccount::class);
  }

  public function defaultPayoutAccount()
  {
    return $this->hasOne(OrganizerPayoutAccount::class)
      ->where('status', 'verified')
      ->where('is_default', true);
  }

  public function scannerDevices()
  {
    return $this->hasMany(ScannerDevice::class);
  }

  public function scannerStaff()
  {
    return $this->hasMany(ScannerStaff::class);
  }
}
