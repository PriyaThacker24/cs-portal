<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_352 extends CI_Migration
{
    public function up(): void
    {
        $table = db_prefix() . 'invoices';

        // Manually-entered Wise payment link, shown/required when the Wise
        // payment method is one of the invoice's allowed payment modes.
        if (! $this->db->field_exists('wise_payment_link', $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` ADD `wise_payment_link` TEXT NULL AFTER `allowed_payment_modes`');
        }
    }

    public function down(): void
    {
        $table = db_prefix() . 'invoices';

        if ($this->db->field_exists('wise_payment_link', $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` DROP COLUMN `wise_payment_link`');
        }
    }
}
