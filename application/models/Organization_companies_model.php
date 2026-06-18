<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Organization_companies_model extends App_Model
{
    private $table;

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('organization_companies');
        $this->table = db_prefix() . 'organization_companies';
    }

    public function get($id = false)
    {
        if (! organization_companies_table_exists()) {
            return is_numeric($id) ? null : [];
        }

        if (is_numeric($id)) {
            $this->db->where('id', $id);

            return $this->db->get($this->table)->row();
        }

        if ($this->db->field_exists('deleted_at', $this->table)) {
            $this->db->where('deleted_at IS NULL', null, false);
        }
        $this->db->order_by('is_primary', 'DESC');
        $this->db->order_by('name', 'ASC');

        return $this->db->get($this->table)->result_array();
    }

    public function get_primary()
    {
        if (! organization_companies_table_exists()) {
            return null;
        }

        $this->db->where('is_primary', 1);
        if ($this->db->field_exists('deleted_at', $this->table)) {
            $this->db->where('deleted_at IS NULL', null, false);
        }
        $this->db->limit(1);
        $company = $this->db->get($this->table)->row();

        if ($company) {
            return $company;
        }

        if ($this->db->field_exists('deleted_at', $this->table)) {
            $this->db->where('deleted_at IS NULL', null, false);
        }
        $this->db->order_by('id', 'ASC');
        $this->db->limit(1);

        return $this->db->get($this->table)->row();
    }

    public function add($data)
    {
        if (! organization_companies_table_exists()) {
            return false;
        }

        $custom_fields = $data['custom_fields'] ?? null;
        unset($data['custom_fields']);

        $payload = $this->prepare_payload($data);
        $payload['datecreated'] = date('Y-m-d H:i:s');
        $payload['addedfrom']   = get_staff_user_id();

        if ($this->db->field_exists('deleted_at', $this->table)) {
            $this->db->where('deleted_at IS NULL', null, false);
        }
        $count = $this->db->count_all_results($this->table);
        if ($count === 0) {
            $payload['is_primary'] = 1;
        }

        if (! empty($payload['is_primary'])) {
            $this->clear_primary_flag();
        }

        if (! $this->db->insert($this->table, $payload)) {
            log_message('error', 'Organization company insert failed: ' . json_encode($this->db->error()));

            return false;
        }

        $insert_id = $this->db->insert_id();

        if ($insert_id) {
            $this->save_custom_fields($insert_id, $custom_fields);
            if (! empty($payload['is_primary'])) {
                sync_organization_company_to_options($this->get($insert_id));
            }
            log_activity('New Organization Company Added [ID: ' . $insert_id . ']');

            return $insert_id;
        }

        return false;
    }

    public function edit($data)
    {
        if (! organization_companies_table_exists()) {
            return false;
        }

        $id = (int) $data['id'];
        unset($data['id']);

        $custom_fields = $data['custom_fields'] ?? null;
        unset($data['custom_fields']);

        $payload = $this->prepare_payload($data);

        if (! empty($payload['is_primary'])) {
            $this->clear_primary_flag();
        } else {
            $current = $this->get($id);
            if ($current && (int) $current->is_primary === 1) {
                $payload['is_primary'] = 1;
            }
        }

        $this->db->where('id', $id);
        $this->db->update($this->table, $payload);

        $this->save_custom_fields($id, $custom_fields);

        $company = $this->get($id);
        if ($company) {
            if ((int) $company->is_primary === 1) {
                sync_organization_company_to_options($company);
            }
            log_activity('Organization Company Updated [ID: ' . $id . ']');

            return true;
        }

        return false;
    }

    public function delete($id)
    {
        if (! organization_companies_table_exists()) {
            return ['error' => 'table_missing'];
        }

        $id      = (int) $id;
        $company = $this->get($id);

        if (! $company) {
            return false;
        }

        if ((int) $company->is_primary === 1) {
            return ['primary_company' => true];
        }

        if ($this->db->field_exists('deleted_at', $this->table)) {
            $this->db->where('deleted_at IS NULL', null, false);
        }
        $total = $this->db->count_all_results($this->table);
        if ($total <= 1) {
            return ['last_company' => true];
        }

        $this->db->where('id', $id);
        if ($this->db->field_exists('deleted_at', $this->table)) {
            $this->db->update($this->table, ['deleted_at' => date('Y-m-d H:i:s')]);
        } else {
            $this->db->delete($this->table);
        }

        if ($this->db->affected_rows() > 0) {
            log_activity('Organization Company Soft Deleted [ID: ' . $id . ']');

            return true;
        }

        return false;
    }

    public function company_to_array($company)
    {
        if (! $company) {
            return [];
        }

        $rel_id = (int) $company->id;
        $custom_fields_values = [];

        if (! function_exists('get_custom_fields')) {
            $this->load->helper('custom_fields');
        }

        foreach (get_custom_fields('company') as $field) {
            $custom_fields_values[$field['id']] = organization_company_get_custom_field_value($rel_id, $field['id']);
        }

        return [
            'id'                  => (int) $company->id,
            'name'                => organization_company_row_value($company, 'name'),
            'address'             => organization_company_row_value($company, 'address'),
            'city'                => organization_company_row_value($company, 'city'),
            'state'               => organization_company_row_value($company, 'state'),
            'country_code'        => organization_company_row_value($company, 'country_code'),
            'zip_code'            => organization_company_row_value($company, 'zip_code'),
            'phone'               => organization_company_row_value($company, 'phone'),
            'email'               => organization_company_row_value($company, 'email'),
            'vat'                 => organization_company_row_value($company, 'vat'),
            'gst'                 => organization_company_row_value($company, 'gst'),
            'logo'                => organization_company_row_value($company, 'logo'),
            'logo_url'            => organization_company_logo_url($company),
            'company_info_format' => organization_company_row_value($company, 'company_info_format'),
            'bank_details'        => organization_company_row_value($company, 'bank_details'),
            'is_primary'          => (int) organization_company_row_value($company, 'is_primary', 0),
            'custom_fields'       => $custom_fields_values,
        ];
    }

    private function save_custom_fields($rel_id, $custom_fields)
    {
        organization_company_save_custom_fields($rel_id, $custom_fields);
    }

    private function prepare_payload($data)
    {
        $table = $this->table;

        $payload = [
            'name'         => trim($data['name'] ?? ''),
            'address'      => $data['address'] ?? '',
            'city'         => $data['city'] ?? '',
            'state'        => $data['state'] ?? '',
            'country_code' => $data['country_code'] ?? '',
            'zip_code'     => $data['zip_code'] ?? '',
            'phone'        => $data['phone'] ?? '',
            'vat'          => $data['vat'] ?? '',
            'is_primary'   => ! empty($data['is_primary']) ? 1 : 0,
        ];

        if ($this->db->field_exists('email', $table)) {
            $payload['email'] = $data['email'] ?? '';
        }

        if ($this->db->field_exists('gst', $table)) {
            $payload['gst'] = $data['gst'] ?? '';
        }

        if ($this->db->field_exists('company_info_format', $table)) {
            $payload['company_info_format'] = $data['company_info_format'] ?? '';
        }

        if ($this->db->field_exists('bank_details', $table)) {
            $payload['bank_details'] = $data['bank_details'] ?? '';
        }

        if ($this->db->field_exists('logo', $table) && array_key_exists('logo', $data)) {
            $payload['logo'] = $data['logo'] ?: null;
        }

        return $payload;
    }

    private function clear_primary_flag()
    {
        if ($this->db->field_exists('deleted_at', $this->table)) {
            $this->db->where('deleted_at IS NULL', null, false);
        }
        $this->db->update($this->table, ['is_primary' => 0]);
    }
}
