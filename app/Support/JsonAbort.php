<?php

namespace App\Support;

use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Thrown by json_fail() to end a request early with a JSON error body.
 * Laravel renders it by returning the wrapped response as-is.
 */
class JsonAbort extends HttpResponseException
{
}
