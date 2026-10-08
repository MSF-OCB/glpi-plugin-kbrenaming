<?php

/**
 * Standalone test bootstrap.
 *
 * The unit tests cover the pure helpers of hook.php and PluginKbrenamingToolbox.
 * GLPI is not loaded: the few core classes they touch are stubbed below, and the
 * $DB global is a recording double, so the suite runs on a bare PHP.
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', __DIR__);
}

if (!class_exists('Item_SoftwareVersion')) {
    class Item_SoftwareVersion
    {
        public static function getTable(): string
        {
            return 'glpi_items_softwareversions';
        }
    }
}

if (!class_exists('SoftwareVersion')) {
    class SoftwareVersion
    {
        public static function getTable(): string
        {
            return 'glpi_softwareversions';
        }
    }
}

if (!class_exists('Toolbox')) {
    class Toolbox
    {
        public static function logDebug(...$args): void
        {
        }
    }
}

/**
 * Records the update() calls and fails the ones listed in $fail_old_ids.
 */
class KbrenamingTestDB
{
    public array $updates = [];
    public array $fail_old_ids = [];

    public function update(string $table, array $params, array $where): bool
    {
        $this->updates[] = [$table, $params, $where];
        return !in_array($where['softwareversions_id'] ?? null, $this->fail_old_ids, true);
    }
}

require_once __DIR__ . '/../inc/toolbox.class.php';
require_once __DIR__ . '/../hook.php';
