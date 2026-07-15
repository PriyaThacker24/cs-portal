<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_351 extends CI_Migration
{
    public function up(): void
    {
        $table = db_prefix() . 'filter_shares';

        if (! $this->db->table_exists($table)) {
            // Stores per-member sharing for saved filters. A row means the
            // filter is shared with that specific staff member. This is
            // independent of the filters.is_shared flag (share with everyone).
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `' . $table . '` (
                    `filter_id` int UNSIGNED NOT NULL,
                    `staff_id` int NOT NULL,
                    PRIMARY KEY (`filter_id`,`staff_id`),
                    FOREIGN KEY (`filter_id`) REFERENCES `' . db_prefix() . 'filters`(`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`staff_id`) REFERENCES `' . db_prefix() . 'staff`(`staffid`) ON DELETE CASCADE
                  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
            );
        }
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'filter_shares`');
    }
}
