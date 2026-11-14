<?php

namespace App\Contracts;

/** Sends a short text message. Implementations run on the server only. */
interface SmsSender
{
  public function configured(): bool;

  /** @throws \RuntimeException when the provider refuses or can't be reached */
  public function send(string $phone, string $message): void;
}
