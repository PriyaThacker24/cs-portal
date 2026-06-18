<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_349 extends CI_Migration
{
    public function up(): void
    {
        $table = db_prefix() . 'staff';
        if ($this->db->table_exists($table)) {
            // Soft-delete flag for staff members. When set to 1 the staff
            // member is treated as deleted (hidden from the staff list) while
            // all related data is preserved.
            if (! $this->db->field_exists('deleted', $table)) {
                $this->db->query('ALTER TABLE `' . $table . '` ADD `deleted` TINYINT(1) NOT NULL DEFAULT 0');
            }
        }
    }

    public function down(): void
    {
        $table = db_prefix() . 'staff';
        if ($this->db->table_exists($table)) {
            if ($this->db->field_exists('deleted', $table)) {
                $this->db->query('ALTER TABLE `' . $table . '` DROP COLUMN `deleted`');
            }
        }
    }
}
