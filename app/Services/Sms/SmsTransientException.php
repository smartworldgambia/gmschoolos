<?php

namespace App\Services\Sms;

use RuntimeException;

/** The provider could not be reached or asked us to slow down: worth trying again later. */
class SmsTransientException extends RuntimeException {}
