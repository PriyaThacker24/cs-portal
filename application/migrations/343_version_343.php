<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_343 extends CI_Migration
{
    public function up(): void
    {
        $table = db_prefix() . 'organization_companies';

        if (! $this->db->table_exists($table)) {
            $this->db->query('CREATE TABLE `' . $table . '` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(191) NOT NULL,
                `address` TEXT NULL,
                `city` VARCHAR(100) NULL,
                `state` VARCHAR(100) NULL,
                `country_code` VARCHAR(20) NULL,
                `zip_code` VARCHAR(20) NULL,
                `phone` VARCHAR(50) NULL,
                `vat` VARCHAR(50) NULL,
                `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
                `datecreated` DATETIME NOT NULL,
                `addedfrom` INT(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `is_primary` (`is_primary`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $this->db->char_set . ';');
        }

        if ($this->db->count_all($table) == 0) {
            $this->db->insert($table, [
                'name'          => get_option('invoice_company_name'),
                'address'       => get_option('invoice_company_address'),
                'city'          => get_option('invoice_company_city'),
                'state'         => get_option('company_state'),
                'country_code'  => get_option('invoice_company_country_code'),
                'zip_code'      => get_option('invoice_company_postal_code'),
                'phone'         => get_option('invoice_company_phonenumber'),
                'vat'           => get_option('company_vat'),
                'is_primary'    => 1,
                'datecreated'   => date('Y-m-d H:i:s'),
                'addedfrom'     => 0,
            ]);
        }
    }

    public function down(): void
    {
        $table = db_prefix() . 'organization_companies';

        if ($this->db->table_exists($table)) {
            $this->db->query('DROP TABLE `' . $table . '`;');
        }
    }
}
