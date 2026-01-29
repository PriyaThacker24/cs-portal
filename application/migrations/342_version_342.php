<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_342 extends CI_Migration
{
    public function up(): void
    {
        $table = db_prefix() . 'projects';

        if (! $this->db->field_exists('owner_id', $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` ADD `owner_id` INT(11) NULL DEFAULT NULL AFTER `addedfrom`;');
        }

        if (! $this->db->field_exists('manager_id', $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` ADD `manager_id` INT(11) NULL DEFAULT NULL AFTER `owner_id`;');
        }
    }

    public function down(): void
    {
        $table = db_prefix() . 'projects';

        if ($this->db->field_exists('owner_id', $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` DROP COLUMN `owner_id`;');
        }

        if ($this->db->field_exists('manager_id', $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` DROP COLUMN `manager_id`;');
        }
    }
}
