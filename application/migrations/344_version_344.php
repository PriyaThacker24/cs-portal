<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_344 extends CI_Migration
{
    public function up(): void
    {
        $table = db_prefix() . 'organization_companies';

        if (! $this->db->table_exists($table)) {
            return;
        }

        if (! $this->db->field_exists('gst', $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` ADD `gst` VARCHAR(50) NULL AFTER `vat`;');
        }

        if (! $this->db->field_exists('company_info_format', $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` ADD `company_info_format` TEXT NULL AFTER `gst`;');
        }

        $primary = $this->db->where('is_primary', 1)->limit(1)->get($table)->row();
        if ($primary && empty($primary->company_info_format)) {
            $format = get_option('company_info_format');
            if ($format !== '') {
                $this->db->where('id', $primary->id);
                $this->db->update($table, ['company_info_format' => $format]);
            }
        }

        if ($primary && empty($primary->gst) && ! empty($primary->vat)) {
            $this->db->where('id', $primary->id);
            $this->db->update($table, ['gst' => $primary->vat]);
        }
    }

    public function down(): void
    {
        $table = db_prefix() . 'organization_companies';

        if ($this->db->field_exists('company_info_format', $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` DROP COLUMN `company_info_format`;');
        }

        if ($this->db->field_exists('gst', $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` DROP COLUMN `gst`;');
        }
    }
}
