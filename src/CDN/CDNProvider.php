<?php
/**
 * CDN provider enum
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\CDN;

enum CDNProvider: string
{
    case NONE = 'none';
    case CLOUDFRONT = 'cloudfront';
    case CLOUDFLARE = 'cloudflare';
    case OTHER = 'other';
}

