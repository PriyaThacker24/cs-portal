<?php

defined('BASEPATH') or exit('No direct script access allowed');

function organization_companies_table_exists()
{
    $CI = &get_instance();

    return $CI->db->table_exists(db_prefix() . 'organization_companies');
}

function organization_company_row_value($company, $field, $default = '')
{
    if (! $company) {
        return $default;
    }

    if (is_array($company)) {
        return $company[$field] ?? $default;
    }

    return property_exists($company, $field) ? ($company->{$field} ?? $default) : $default;
}

function organization_company_has_column($column)
{
    $CI = &get_instance();

    return organization_companies_table_exists()
        && $CI->db->field_exists($column, db_prefix() . 'organization_companies');
}

function get_organization_companies()
{
    $CI = &get_instance();
    $CI->load->model('organization_companies_model');

    return $CI->organization_companies_model->get();
}

function get_primary_organization_company()
{
    $CI = &get_instance();
    $CI->load->model('organization_companies_model');

    return $CI->organization_companies_model->get_primary();
}

function get_organization_company($id)
{
    if (! organization_companies_table_exists() || ! $id) {
        return null;
    }

    $CI = &get_instance();
    $CI->load->model('organization_companies_model');

    return $CI->organization_companies_model->get((int) $id);
}

function organization_company_client_column_exists()
{
    $CI = &get_instance();

    return $CI->db->field_exists('organization_company_id', db_prefix() . 'clients');
}

function organization_company_invoice_column_exists()
{
    $CI = &get_instance();

    return $CI->db->field_exists('organization_company_id', db_prefix() . 'invoices');
}

function organization_company_normalize_id($id)
{
    $id = (int) $id;

    return $id > 0 ? $id : null;
}

function organization_company_id_from_client($client_id)
{
    if (! $client_id || ! organization_company_client_column_exists()) {
        return null;
    }

    $CI = &get_instance();
    $CI->db->select('organization_company_id');
    $CI->db->where('userid', (int) $client_id);
    $row = $CI->db->get(db_prefix() . 'clients')->row();

    return $row && ! empty($row->organization_company_id)
        ? organization_company_normalize_id($row->organization_company_id)
        : null;
}

function organization_company_resolve_for_invoice($invoice)
{
    if (! organization_companies_table_exists()) {
        return get_primary_organization_company();
    }

    if (is_numeric($invoice)) {
        $CI = &get_instance();
        $CI->load->model('invoices_model');
        $invoice = $CI->invoices_model->get($invoice);
    }

    if (is_object($invoice) && organization_company_invoice_column_exists()
        && ! empty($invoice->organization_company_id)) {
        $company = get_organization_company($invoice->organization_company_id);
        if ($company) {
            return $company;
        }
    }

    if (is_object($invoice) && ! empty($invoice->clientid)) {
        $client_company_id = organization_company_id_from_client($invoice->clientid);
        if ($client_company_id) {
            $company = get_organization_company($client_company_id);
            if ($company) {
                return $company;
            }
        }
    }

    return get_primary_organization_company();
}

function organization_company_resolve_for_payment($payment)
{
    if (is_object($payment) && ! empty($payment->invoice)) {
        return organization_company_resolve_for_invoice($payment->invoice);
    }

    if (is_object($payment) && ! empty($payment->invoice_data)) {
        return organization_company_resolve_for_invoice($payment->invoice_data);
    }

    if (is_object($payment) && ! empty($payment->invoiceid)) {
        return organization_company_resolve_for_invoice($payment->invoiceid);
    }

    return get_primary_organization_company();
}

function organization_company_resolve_id_for_invoice_data(array $data)
{
    if (! organization_company_invoice_column_exists()) {
        return null;
    }

    if (! empty($data['organization_company_id'])) {
        return organization_company_normalize_id($data['organization_company_id']);
    }

    if (! empty($data['organization_company_id_hidden'])) {
        return organization_company_normalize_id($data['organization_company_id_hidden']);
    }

    if (! empty($data['clientid'])) {
        $from_client = organization_company_id_from_client($data['clientid']);
        if ($from_client) {
            return $from_client;
        }
    }

    $primary = get_primary_organization_company();

    return $primary ? (int) $primary->id : null;
}

function organization_company_select_options()
{
    if (! organization_companies_table_exists()) {
        return [];
    }

    $options = [];
    foreach (get_organization_companies() as $row) {
        $name = $row['name'] ?? '';
        if ((int) ($row['is_primary'] ?? 0) === 1) {
            $name .= ' (' . _l('organization_company_primary') . ')';
        }
        $options[] = [
            'id'   => (int) $row['id'],
            'name' => $name,
        ];
    }

    return $options;
}

function organization_company_logo_url($company)
{
    if (! $company) {
        return '';
    }

    $logo = organization_company_row_value($company, 'logo');
    if ($logo === '') {
        return '';
    }

    return base_url('uploads/company/' . $logo);
}

function organization_company_logo_path($company)
{
    if (! $company) {
        return '';
    }

    $logo = organization_company_row_value($company, 'logo');
    if ($logo === '') {
        return '';
    }

    return get_upload_path_by_type('company') . $logo;
}

function organization_company_delete_logo($filename)
{
    if (! $filename) {
        return;
    }

    $path = get_upload_path_by_type('company') . $filename;
    if (file_exists($path)) {
        @unlink($path);
    }
}

function organization_company_handle_logo_upload($field_name = 'company_logo')
{
    if (! isset($_FILES[$field_name]) || empty($_FILES[$field_name]['name'])) {
        return ['success' => false];
    }

    if (_perfex_upload_error($_FILES[$field_name]['error'])) {
        return ['success' => false, 'message' => _perfex_upload_error($_FILES[$field_name]['error'])];
    }

    $tmpFilePath = $_FILES[$field_name]['tmp_name'];
    if (empty($tmpFilePath)) {
        return ['success' => false];
    }

    $extension = strtolower(pathinfo($_FILES[$field_name]['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
    $allowed_extensions = array_unique(
        hooks()->apply_filters('company_logo_upload_allowed_extensions', $allowed_extensions)
    );

    if (! in_array($extension, $allowed_extensions)) {
        return ['success' => false, 'message' => _l('file_type_not_allowed')];
    }

    $path = get_upload_path_by_type('company');
    _maybe_create_upload_path($path);
    $filename = md5('organization_company_logo_' . microtime(true) . app_generate_hash()) . '.' . $extension;
    $newFilePath = $path . $filename;

    if (! move_uploaded_file($tmpFilePath, $newFilePath)) {
        return ['success' => false, 'message' => _l('organization_company_save_failed')];
    }

    return ['success' => true, 'filename' => $filename];
}

/**
 * Stacked company block for invoice PDF/preview (preserves line breaks; matches invoice header layout).
 *
 * @param mixed $company Organization company row/object, id, or null for primary
 *
 * @return string
 */
function format_invoice_organization_info($company = null)
{
    if ($company === null) {
        $company = get_primary_organization_company();
    } elseif (is_numeric($company)) {
        $company = get_organization_company($company);
    }

    if (! $company) {
        return '';
    }

    $lines = [];

    $name = organization_company_row_value($company, 'name');
    if ($name !== '') {
        $lines[] = '<b style="color:#000000;font-weight:bold;">' . e($name) . '</b>';
    }

    $address = organization_company_row_value($company, 'address');
    if ($address !== '') {
        $lines[] = e($address);
    }

    $city      = organization_company_row_value($company, 'city');
    $state     = organization_company_row_value($company, 'state');
    $cityState = trim($city . ($city !== '' && $state !== '' ? ' ' : '') . $state);
    if ($cityState !== '') {
        $lines[] = e($cityState);
    }

    $country = organization_company_row_value($company, 'country_code');
    $zip     = organization_company_row_value($company, 'zip_code');
    if ($country !== '' && $zip !== '') {
        $lines[] = e($country . ' - ' . $zip);
    } elseif ($country !== '') {
        $lines[] = e($country);
    } elseif ($zip !== '') {
        $lines[] = e($zip);
    }

    // $phone = organization_company_row_value($company, 'phone');
    // if ($phone !== '') {
    //     $lines[] = e($phone);
    // }

    // $vat = organization_company_row_value($company, 'vat');
    // if ($vat !== '') {
    //     $lines[] = e(_l('company_vat_number') . ': ' . $vat);
    // }

    // if (organization_company_has_column('gst')) {
    //     $gst = organization_company_row_value($company, 'gst');
    //     if ($gst !== '') {
    //         $lines[] = e(_l('company_gst_number') . ': ' . $gst);
    //     }
    // }

    // foreach (get_company_custom_fields((int) $company->id) as $field) {
    //     $value = trim(strip_tags((string) $field['value']));
    //     if ($value !== '') {
    //         $lines[] = e($field['label']) . ': ' . e($value);
    //     }
    // }

    return hooks()->apply_filters('invoice_organization_info_text', implode('<br />' . "\n", $lines), $company);
}

/**
 * Invoice number (blue) + stacked company block for PDF and HTML previews.
 *
 * @param object|int $invoice Invoice row or id
 * @param mixed      $company Optional company override
 *
 * @return string
 */
function format_invoice_organization_header($invoice, $company = null)
{
    if ($company === null && $invoice) {
        $company = organization_company_resolve_for_invoice($invoice);
    }

    $invoice_number = '';
    if (is_object($invoice) && ! empty($invoice->id)) {
        $invoice_number = format_invoice_number($invoice->id);
    }

    $html = '';
    if ($invoice_number !== '') {
        $html .= '<div style="font-weight:bold;font-size:18px;color:#2563eb;line-height:1.35;">'
            . e($invoice_number) . '</div>';
    }

    $info = format_invoice_organization_info($company);
    if ($info !== '') {
        $html .= '<div style="color:#000000;font-size:12px;line-height:1.55;margin-top:6px;">' . $info . '</div>';
    }

    return hooks()->apply_filters('invoice_organization_header_html', $html, $invoice, $company);
}

function sync_organization_company_to_options($company)
{
    if (! $company) {
        return;
    }

    if (is_array($company)) {
        $company = (object) $company;
    }

    update_option('invoice_company_name', organization_company_row_value($company, 'name'));
    update_option('invoice_company_address', organization_company_row_value($company, 'address'));
    update_option('invoice_company_city', organization_company_row_value($company, 'city'));
    update_option('company_state', organization_company_row_value($company, 'state'));
    update_option('invoice_company_country_code', organization_company_row_value($company, 'country_code'));
    update_option('invoice_company_postal_code', organization_company_row_value($company, 'zip_code'));
    update_option('invoice_company_phonenumber', organization_company_row_value($company, 'phone'));
    update_option('company_vat', organization_company_row_value($company, 'vat'));

    $format = organization_company_row_value($company, 'company_info_format');
    if ($format !== '') {
        update_option('company_info_format', $format);
    }
}

function organization_company_info_format($company = null)
{
    if ($company === null) {
        $company = get_primary_organization_company();
    }

    if ($company && organization_company_row_value($company, 'company_info_format') !== '') {
        return organization_company_row_value($company, 'company_info_format');
    }

    return get_option('company_info_format');
}

function organization_company_option($key, $company = null)
{
    if ($company === null) {
        $company = get_primary_organization_company();
    }

    if ($company) {
        $map = [
            'invoice_company_name'          => 'name',
            'invoice_company_address'       => 'address',
            'invoice_company_city'          => 'city',
            'company_state'                 => 'state',
            'invoice_company_country_code'  => 'country_code',
            'invoice_company_postal_code'   => 'zip_code',
            'invoice_company_phonenumber'   => 'phone',
            'company_vat'                   => 'vat',
        ];

        if (isset($map[$key])) {
            return organization_company_row_value($company, $map[$key]);
        }
    }

    return get_option($key);
}

/**
 * Whether custom fields should load saved values (false = blank form for new company).
 */
function organization_company_custom_fields_has_rel_id($rel_id)
{
    return $rel_id !== false && $rel_id !== null && $rel_id !== '' && (int) $rel_id > 0;
}

/**
 * Render company custom fields for the multi-company modal (never required — each company has its own GST, etc.).
 */
function organization_company_render_custom_fields($rel_id = false)
{
    $CI = &get_instance();
    $CI->load->helper('custom_fields');

    if (! organization_company_custom_fields_has_rel_id($rel_id)) {
        $rel_id = false;
    }

    return render_custom_fields('company', $rel_id, [], [
        'optional_only' => true,
        'blank_on_new'  => true,
    ]);
}

/**
 * Get company custom field value for an organization company (migrates legacy relid 0 on read).
 */
function organization_company_get_custom_field_value($rel_id, $field_id)
{
    $rel_id   = (int) $rel_id;
    $field_id = (int) $field_id;

    if ($field_id <= 0) {
        return '';
    }

    $CI = &get_instance();
    $CI->db->where('relid', $rel_id);
    $CI->db->where('fieldid', $field_id);
    $CI->db->where('fieldto', 'company');
    $row = $CI->db->get(db_prefix() . 'customfieldsvalues')->row();

    if ($row && $row->value !== '' && $row->value !== null) {
        return $row->value;
    }

    if ($rel_id > 0) {
        $legacy = get_custom_field_value(0, $field_id, 'company', false);
        if ($legacy !== '') {
            organization_company_save_custom_fields($rel_id, [
                'company' => [$field_id => $legacy],
            ]);

            return $legacy;
        }
    }

    return '';
}

/**
 * Save company custom fields (safe wrapper — avoids fatal if field definition missing).
 */
function organization_company_save_custom_fields($rel_id, $custom_fields)
{
    if (! is_array($custom_fields) || empty($custom_fields)) {
        return;
    }

    $CI = &get_instance();
    $rel_id = (int) $rel_id;

    foreach ($custom_fields as $fieldto => $fields) {
        if (! is_array($fields)) {
            continue;
        }

        foreach ($fields as $field_id => $field_value) {
            $field_id = (int) $field_id;
            if ($field_id <= 0) {
                continue;
            }

            $CI->db->where('id', $field_id);
            $field_checker = $CI->db->get(db_prefix() . 'customfields')->row();
            if (! $field_checker || $field_checker->fieldto !== 'company') {
                continue;
            }

            if (! is_array($field_value)) {
                $field_value = trim((string) $field_value);
            }

            if ($field_checker->type === 'date_picker') {
                $field_value = to_sql_date($field_value);
            } elseif ($field_checker->type === 'date_picker_time') {
                $field_value = to_sql_date($field_value, true);
            } elseif ($field_checker->type === 'textarea') {
                $field_value = nl2br($field_value);
            } elseif ($field_checker->type === 'checkbox' || $field_checker->type === 'multiselect') {
                if (is_array($field_value)) {
                    $field_value = implode(', ', array_filter($field_value, function ($v) {
                        return $v !== 'cfk_hidden';
                    }));
                }
            }

            $CI->db->where('relid', $rel_id);
            $CI->db->where('fieldid', $field_id);
            $CI->db->where('fieldto', 'company');
            $row = $CI->db->get(db_prefix() . 'customfieldsvalues')->row();

            if ($row) {
                $CI->db->where('id', $row->id);
                $CI->db->update(db_prefix() . 'customfieldsvalues', ['value' => $field_value]);
            } elseif ($field_value !== '') {
                $CI->db->insert(db_prefix() . 'customfieldsvalues', [
                    'relid'   => $rel_id,
                    'fieldid' => $field_id,
                    'fieldto' => 'company',
                    'value'   => $field_value,
                ]);
            }
        }
    }
}
