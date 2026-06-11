<?php

declare(strict_types=1);

namespace App\Payments;

/** The checkout was paid before we could expire it — reconcile, never bail. */
class CheckoutAlreadyCompletedException extends GatewayException {}
