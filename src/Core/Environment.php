<?php
/**
 * Deployment environment enum
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Core;

enum Environment: string
{
    case DEVELOP = 'develop';
    case STAGING = 'staging';
    case PRODUCTION = 'production';
}
