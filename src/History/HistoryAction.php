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
    case AI_METADATA_GENERATED = 'ai_metadata_generated';
    case AI_METADATA_STARTED = 'ai_metadata_started';
    case AI_METADATA_COMPLETED = 'ai_metadata_completed';
    case BACKUP_CREATED = 'backup_created';
    case BACKUP_RESTORED = 'backup_restored';
    case CONVERTED_WEBP = 'converted_webp';
    case CONVERTED_AVIF = 'converted_avif';
}
