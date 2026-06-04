<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Organization_companies extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('form_validation');
        $this->load->model('organization_companies_model');
        $this->load->helper('organization_companies');
        $this->load->helper('custom_fields');
    }

    private function json_response($data, $status = 200)
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data));

        return;
    }

    public function manage()
    {
        if (staff_cant('edit', 'settings')) {
            ajax_access_denied();
        }

        if (! $this->input->post()) {
            show_404();
        }

        if (! organization_companies_table_exists()) {
            $this->json_response([
                'success' => false,
                'message' => _l('organization_companies_migration_required'),
            ]);

            return;
        }

        $this->form_validation->set_rules('name', _l('settings_sales_company_name'), 'required|max_length[191]');

        if ($this->form_validation->run() === false) {
            $this->json_response([
                'success' => false,
                'message' => strip_tags(validation_errors()),
            ]);

            return;
        }

        $data = $this->input->post(null, false);
        $id   = trim((string) ($data['id'] ?? ''));

        if ($id === '') {
            $insert_id = $this->organization_companies_model->add($data);
            if ($insert_id) {
                $company = $this->organization_companies_model->get($insert_id);
                $this->process_company_logo_upload($insert_id, $company ? ($company->logo ?? '') : '');
            }
            $this->json_response([
                'success' => (bool) $insert_id,
                'message' => $insert_id ? _l('added_successfully', _l('organization_company')) : _l('organization_company_save_failed'),
            ]);

            return;
        }

        $data['id'] = $id;
        $success    = $this->organization_companies_model->edit($data);
        if ($success) {
            $company = $this->organization_companies_model->get((int) $id);
            $this->process_company_logo_upload((int) $id, $company ? ($company->logo ?? '') : '');
        }
        $this->json_response([
            'success' => $success,
            'message' => $success ? _l('updated_successfully', _l('organization_company')) : _l('organization_company_save_failed'),
        ]);
    }

    private function process_company_logo_upload($company_id, $old_logo = '')
    {
        if (! $company_id) {
            return;
        }

        $upload = organization_company_handle_logo_upload('company_logo');
        if (! ($upload['success'] ?? false)) {
            return;
        }

        if (! $this->db->field_exists('logo', db_prefix() . 'organization_companies')) {
            return;
        }

        $this->db->where('id', (int) $company_id);
        $this->db->update(db_prefix() . 'organization_companies', ['logo' => $upload['filename']]);
        if ($old_logo && $old_logo !== $upload['filename']) {
            organization_company_delete_logo($old_logo);
        }
    }

    public function get($id)
    {
        if (staff_cant('edit', 'settings')) {
            ajax_access_denied();
        }

        if (! organization_companies_table_exists()) {
            $this->json_response(['success' => false]);

            return;
        }

        $company = $this->organization_companies_model->get($id);

        if (! $company) {
            $this->json_response(['success' => false]);

            return;
        }

        $this->json_response([
            'success' => true,
            'company' => $this->organization_companies_model->company_to_array($company),
        ]);
    }

    public function custom_fields_html($id = 0)
    {
        if (staff_cant('view', 'settings')) {
            ajax_access_denied();
        }

        $rel_id = (int) $id;
        echo organization_company_render_custom_fields($rel_id > 0 ? $rel_id : false);
    }

    public function preview_info($id = 0)
    {
        if (! is_staff_logged_in()) {
            ajax_access_denied();
        }

        $company = get_organization_company((int) $id);
        if (! $company) {
            $company = get_primary_organization_company();
        }

        $this->json_response([
            'success' => (bool) $company,
            'html'    => $company ? format_invoice_organization_info($company) : '',
        ]);
    }

    public function delete($id)
    {
        if (staff_cant('edit', 'settings')) {
            ajax_access_denied();
        }

        if (! $id) {
            $this->json_response(['success' => false]);

            return;
        }

        $response = $this->organization_companies_model->delete($id);

        if (is_array($response) && isset($response['primary_company'])) {
            $this->json_response([
                'success' => false,
                'message' => _l('organization_company_cant_delete_primary'),
            ]);

            return;
        }

        if (is_array($response) && isset($response['last_company'])) {
            $this->json_response([
                'success' => false,
                'message' => _l('organization_company_cant_delete_last'),
            ]);

            return;
        }

        $this->json_response([
            'success' => (bool) $response,
            'message' => $response ? _l('deleted', _l('organization_company')) : _l('problem_deleting', _l('organization_company')),
        ]);
    }

    public function remove_logo($id = 0)
    {
        if (staff_cant('edit', 'settings')) {
            ajax_access_denied();
        }

        $id = (int) $id;
        if ($id <= 0 || ! organization_companies_table_exists()) {
            $this->json_response(['success' => false]);
            return;
        }

        $company = $this->organization_companies_model->get($id);
        if (! $company) {
            $this->json_response(['success' => false]);
            return;
        }

        if ($this->db->field_exists('logo', db_prefix() . 'organization_companies')) {
            $logo = $company->logo ?? '';
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'organization_companies', ['logo' => null]);
            if ($logo) {
                organization_company_delete_logo($logo);
            }
        }

        $this->json_response([
            'success' => true,
            'message' => _l('deleted', _l('settings_general_company_logo')),
        ]);
    }
}
