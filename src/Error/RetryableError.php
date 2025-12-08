<?php
/**
 * Error types that can be retried
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Error;

enum RetryableError: string
{
    case REQUEST_TIMEOUT = 'RequestTimeout';
    case SERVICE_UNAVAILABLE = 'ServiceUnavailable';
    case THROTTLING = 'ThrottlingException';
    case SLOW_DOWN = 'SlowDown';
    case INTERNAL_ERROR = 'InternalError';
    case BAD_DIGEST = 'BadDigest'; // CRC32 checksum mismatch - can happen with concurrent file access
}

