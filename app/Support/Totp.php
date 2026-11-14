<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Time-based one-time passwords (RFC 6238, the scheme Google Authenticator,
 * Authy and 1Password use): HMAC-SHA1, 6 digits, 30-second steps.
 * Same method names as pragmarx/google2fa so it can be swapped in later.
 */
class Totp
{
  private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

  public function generateSecretKey(int $length = 32): string
  {
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
      $secret .= self::ALPHABET[random_int(0, 31)];
    }

    return $secret;
  }

  /** The 6-digit code for a given 30-second step. */
  public function oathTotp(string $secret, int $step): string
  {
    $key = $this->base32Decode($secret);
    $hash = hash_hmac('sha1', pack('N*', 0, $step), $key, true);
    $offset = ord($hash[19]) & 0x0f;
    $value = ((ord($hash[$offset]) & 0x7f) << 24)
      | ((ord($hash[$offset + 1]) & 0xff) << 16)
      | ((ord($hash[$offset + 2]) & 0xff) << 8)
      | (ord($hash[$offset + 3]) & 0xff);

    return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
  }

  /**
   * Check a code, allowing one step of clock drift either side. Returns the
   * matched step so callers can refuse a code that was already used
   * ($afterStep), or false.
   */
  public function verifyKeyNewer(string $secret, string $code, ?int $afterStep, int $window = 1, ?int $time = null): int|false
  {
    $code = preg_replace('/\s+/', '', $code);
    if (!preg_match('/^\d{6}$/', $code)) {
      return false;
    }
    $now = intdiv($time ?? time(), 30);
    for ($step = $now - $window; $step <= $now + $window; $step++) {
      if ($afterStep !== null && $step <= $afterStep) {
        continue;
      }
      if (hash_equals($this->oathTotp($secret, $step), $code)) {
        return $step;
      }
    }

    return false;
  }

  /** otpauth:// link that authenticator apps read from the QR code. */
  public function getQRCodeUrl(string $issuer, string $account, string $secret): string
  {
    return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
      . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
  }

  public function base32Decode(string $secret): string
  {
    $secret = strtoupper(rtrim(preg_replace('/\s+/', '', $secret), '='));
    $bits = '';
    foreach (str_split($secret) as $char) {
      $index = strpos(self::ALPHABET, $char);
      if ($index === false) {
        throw new InvalidArgumentException('The authenticator secret is not valid base32.');
      }
      $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    foreach (str_split($bits, 8) as $byte) {
      if (strlen($byte) === 8) {
        $bytes .= chr(bindec($byte));
      }
    }

    return $bytes;
  }

  public function base32Encode(string $bytes): string
  {
    $bits = '';
    foreach (str_split($bytes) as $char) {
      $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
      $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
    }

    return $out;
  }
}
