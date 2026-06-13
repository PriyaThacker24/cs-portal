<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_347 extends CI_Migration
{
    public function up(): void
    {
        $table = db_prefix() . 'organization_companies';
        if ($this->db->table_exists($table) && ! $this->db->field_exists('deleted_at', $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` ADD `deleted_at` DATETIME NULL');
        }
    }

    public function down(): void
    {
        $table = db_prefix() . 'organization_companies';
        if ($this->db->table_exists($table) && $this->db->field_exists('deleted_at', $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` DROP COLUMN `deleted_at`');
        }
    }
}
