<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_355 extends CI_Migration
{
    public function up(): void
    {
        $table = db_prefix() . 'invoices';

        // Note captured in the "Mark as Failed" popup, shown on the invoice HTML
        // view under the "Failed Notes" label.
        if (! $this->db->field_exists('failed_note', $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` ADD `failed_note` TEXT NULL DEFAULT NULL');
        }
    }

    public function down(): void
    {
        $table = db_prefix() . 'invoices';

        if ($this->db->field_exists('failed_note', $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` DROP COLUMN `failed_note`');
        }
    }
}
