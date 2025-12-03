<?php
/**
 * History action types
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\History;

enum HistoryAction: string
{
    case UPLOADED = 'uploaded';
    case MIGRATED = 'migrated';
    case DELETED = 'deleted';
    case EDITED = 'edited';
    case SETTINGS_CHANGED = 'settings_changed';
    case MIGRATION_STARTED = 'migration_started';
    case MIGRATION_COMPLETED = 'migration_completed';
    case MIGRATION_FAILED = 'migration_failed';
    case INVALIDATION = 'invalidation';
    case OPTIMIZED = 'optimized';
    case OPTIMIZATION_STARTED = 'optimization_started';
    case OPTIMIZATION_COMPLETED = 'optimization_completed';
    case RESIZED = 'resized';
}
