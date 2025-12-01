<?php
/**
 * Admin Migration class
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Admin;

use Metodo\MediaToolkit\Migration\Migration;
use Metodo\MediaToolkit\Stats\Stats;

/**
 * Handles admin migration page
 */
final class Admin_Migration
{
    private ?Migration $migration;
    private Stats $stats;

    public function __construct(?Migration $migration, Stats $stats)
    {
        $this->migration = $migration;
        $this->stats = $stats;
    }

    /**
     * Get migration page data
     */
    public function get_page_data(): array
    {
        $state = $this->migration?->get_state();
        
        return [
            'state' => $state?->toArray() ?? [],
            'migration_stats' => $this->stats->get_migration_stats(),
            'is_configured' => $this->migration !== null,
        ];
    }
}

