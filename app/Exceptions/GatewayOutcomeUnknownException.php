<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The gateway may have accepted a money-moving request, but the response was
 * not authoritative. Callers must verify by the merchant-controlled reference
 * before retrying or changing financial state.
 */
class GatewayOutcomeUnknownException extends RuntimeException
{
}
