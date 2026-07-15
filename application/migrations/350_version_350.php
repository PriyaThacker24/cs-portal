<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_350 extends CI_Migration
{
    public function up(): void
    {
        $table = db_prefix() . 'organization_companies';
        if ($this->db->table_exists($table)) {
            // Per-company bank details shown on the invoice bottom and PDF.
            if (! $this->db->field_exists('bank_details', $table)) {
                $this->db->query('ALTER TABLE `' . $table . '` ADD `bank_details` TEXT NULL AFTER `company_info_format`');
            }
        }
    }

    public function down(): void
    {
        $table = db_prefix() . 'organization_companies';
        if ($this->db->table_exists($table)) {
            if ($this->db->field_exists('bank_details', $table)) {
                $this->db->query('ALTER TABLE `' . $table . '` DROP COLUMN `bank_details`');
            }
        }
    }
}
