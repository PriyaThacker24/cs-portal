<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_353 extends CI_Migration
{
    public function up(): void
    {
        $table = db_prefix() . 'staff';

        // Stores hashed one-time Two-Factor Authentication recovery/backup codes
        // (JSON array of password_hash strings) so a staff member can log in if
        // they lose access to their authenticator app.
        if (! $this->db->field_exists('two_factor_backup_codes', $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` ADD `two_factor_backup_codes` TEXT NULL AFTER `google_auth_secret`');
        }
    }

    public function down(): void
    {
        $table = db_prefix() . 'staff';

        if ($this->db->field_exists('two_factor_backup_codes', $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` DROP COLUMN `two_factor_backup_codes`');
        }
    }
}
