<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_341 extends CI_Migration
{
    public function up(): void
    {
        // Add permissions field to tblproject_members
        // Using JSON type for MySQL 5.7+ or LONGTEXT as fallback
        $this->db->query('ALTER TABLE `' . db_prefix() . 'project_members` ADD `permissions` JSON NULL DEFAULT NULL AFTER `staff_id`;');
        
        // If JSON type is not supported, use LONGTEXT instead
        // Uncomment the line below and comment the line above if your MySQL version doesn't support JSON
        // $this->db->query('ALTER TABLE `' . db_prefix() . 'project_members` ADD `permissions` LONGTEXT NULL DEFAULT NULL AFTER `staff_id`;');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `' . db_prefix() . 'project_members` DROP COLUMN `permissions`;');
    }
}

