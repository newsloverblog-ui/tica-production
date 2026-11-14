<?php

namespace App\Exceptions;

use RuntimeException;

/** The payout provider didn't answer in time; the request can be retried. */
class PayoutProviderUnavailable extends RuntimeException
{
}
