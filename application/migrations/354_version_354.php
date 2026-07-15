<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_354 extends CI_Migration
{
    public function up(): void
    {
        $table = db_prefix() . 'tasks';

        // Estimated hours entered when creating/editing a task, shown in the
        // Task Details modal under the Task Info section.
        if (! $this->db->field_exists('estimated_hours', $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` ADD `estimated_hours` DECIMAL(11,2) NULL DEFAULT NULL AFTER `hourly_rate`');
        }
    }

    public function down(): void
    {
        $table = db_prefix() . 'tasks';

        if ($this->db->field_exists('estimated_hours', $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` DROP COLUMN `estimated_hours`');
        }
    }
}
