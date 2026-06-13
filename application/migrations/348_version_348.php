<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_348 extends CI_Migration
{
    public function up(): void
    {
        $table = db_prefix() . 'organization_companies';
        if ($this->db->table_exists($table)) {
            if (! $this->db->field_exists('email', $table)) {
                $this->db->query('ALTER TABLE `' . $table . '` ADD `email` VARCHAR(191) NULL AFTER `phone`');
            }
            if (! $this->db->field_exists('gst', $table)) {
                $this->db->query('ALTER TABLE `' . $table . '` ADD `gst` VARCHAR(191) NULL AFTER `vat`');
            }
        }
    }

    public function down(): void
    {
        $table = db_prefix() . 'organization_companies';
        if ($this->db->table_exists($table)) {
            if ($this->db->field_exists('email', $table)) {
                $this->db->query('ALTER TABLE `' . $table . '` DROP COLUMN `email`');
            }
            if ($this->db->field_exists('gst', $table)) {
                $this->db->query('ALTER TABLE `' . $table . '` DROP COLUMN `gst`');
            }
        }
    }
}
