<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_345 extends CI_Migration
{
    public function up(): void
    {
        $this->load->helper('organization_companies');

        $clients = db_prefix() . 'clients';
        if ($this->db->table_exists($clients) && ! $this->db->field_exists('organization_company_id', $clients)) {
            $this->db->query('ALTER TABLE `' . $clients . '` ADD `organization_company_id` INT(11) NULL DEFAULT NULL AFTER `company`');
            $this->db->query('ALTER TABLE `' . $clients . '` ADD KEY `organization_company_id` (`organization_company_id`)');
        }

        $invoices = db_prefix() . 'invoices';
        if ($this->db->table_exists($invoices) && ! $this->db->field_exists('organization_company_id', $invoices)) {
            $this->db->query('ALTER TABLE `' . $invoices . '` ADD `organization_company_id` INT(11) NULL DEFAULT NULL AFTER `clientid`');
            $this->db->query('ALTER TABLE `' . $invoices . '` ADD KEY `organization_company_id` (`organization_company_id`)');
        }

        if (organization_companies_table_exists()) {
            $primary = get_primary_organization_company();
            if ($primary) {
                $primary_id = (int) $primary->id;
                $this->db->where('organization_company_id IS NULL', null, false);
                $this->db->update($clients, ['organization_company_id' => $primary_id]);

                $this->db->where('organization_company_id IS NULL', null, false);
                $this->db->update($invoices, ['organization_company_id' => $primary_id]);
            }
        }
    }

    public function down(): void
    {
        $clients = db_prefix() . 'clients';
        if ($this->db->field_exists('organization_company_id', $clients)) {
            $this->db->query('ALTER TABLE `' . $clients . '` DROP COLUMN `organization_company_id`');
        }

        $invoices = db_prefix() . 'invoices';
        if ($this->db->field_exists('organization_company_id', $invoices)) {
            $this->db->query('ALTER TABLE `' . $invoices . '` DROP COLUMN `organization_company_id`');
        }
    }
}
