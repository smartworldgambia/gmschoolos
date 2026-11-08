<?php

namespace App\Services\Billing\Gateways;

use RuntimeException;

/** A provider call failed or the provider is not usable. Messages never contain keys or card data. */
class GatewayException extends RuntimeException {}
