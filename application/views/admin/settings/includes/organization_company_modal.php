<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$this->load->helper('organization_companies');
if (! organization_companies_table_exists() || ! staff_can('edit', 'settings')) {
    return;
}
$default_format = clear_textarea_breaks(get_option('company_info_format'));
$company_custom_field_definitions = get_custom_fields('company');
?>
<div class="modal fade" id="organization_company_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    <span class="add-title"><?= _l('organization_company_add'); ?></span>
                    <span class="edit-title hide"><?= _l('organization_company_edit'); ?></span>
                </h4>
            </div>
            <?= form_open_multipart(admin_url('organization_companies/manage'), ['id' => 'organization_company_form']); ?>
            <?= form_hidden('id', ''); ?>
            <div class="modal-body">
                <?= render_input('name', 'settings_sales_company_name'); ?>
                <?= render_input('address', 'settings_sales_address'); ?>
                <?= render_input('city', 'settings_sales_city'); ?>
                <?= render_input('state', 'billing_state'); ?>
                <?= render_input('country_code', 'settings_sales_country_code'); ?>
                <?= render_input('zip_code', 'settings_sales_postal_code'); ?>
                <?= render_input('phone', 'settings_sales_phonenumber'); ?>
                <?= render_input('vat', 'company_vat_number'); ?>
                <div class="form-group">
                    <label for="organization_company_logo" class="control-label"><?= _l('settings_general_company_logo'); ?></label>
                    <input type="file"
                        name="company_logo"
                        id="organization_company_logo"
                        class="form-control"
                        accept=".jpg,.jpeg,.png,.gif,.svg"
                        data-toggle="tooltip"
                        title="<?= _l('settings_general_company_logo_tooltip'); ?>">
                </div>
                <div id="organization-company-logo-preview-wrap" class="hide mtop10 mbot10">
                    <div class="tw-flex tw-items-center tw-gap-3">
                        <img id="organization-company-logo-preview" src="" alt="Company logo" style="max-width:180px;max-height:70px;">
                        <button type="button" class="btn btn-default btn-sm hide" id="organization-company-logo-remove-btn">
                            <i class="fa fa-remove"></i> <?= _l('delete'); ?>
                        </button>
                    </div>
                </div>

                <div id="organization-company-custom-fields">
                    <?= organization_company_render_custom_fields(false); ?>
                </div>

                <hr />

                <?= render_textarea('company_info_format', 'company_info_format', $default_format, ['rows' => 8, 'style' => 'line-height:20px;', 'id' => 'organization_company_info_format']); ?>
                <p>
                    <a href="#" class="settings-textarea-merge-field" data-to="organization_company_info_format">{company_name}</a>
                    <a href="#" class="settings-textarea-merge-field" data-to="organization_company_info_format">{address}</a>,
                    <a href="#" class="settings-textarea-merge-field" data-to="organization_company_info_format">{city}</a>,
                    <a href="#" class="settings-textarea-merge-field" data-to="organization_company_info_format">{state}</a>,
                    <a href="#" class="settings-textarea-merge-field" data-to="organization_company_info_format">{zip_code}</a>,
                    <a href="#" class="settings-textarea-merge-field" data-to="organization_company_info_format">{country_code}</a>,
                    <a href="#" class="settings-textarea-merge-field" data-to="organization_company_info_format">{phone}</a>,
                    <a href="#" class="settings-textarea-merge-field" data-to="organization_company_info_format">{vat_number}</a>,
                    <a href="#" class="settings-textarea-merge-field" data-to="organization_company_info_format">{vat_number_with_label}</a>
                </p>
                <?php if (count($company_custom_field_definitions) > 0) { ?>
                <hr />
                <p class="font-medium"><b><?= _l('custom_fields'); ?></b></p>
                <ul class="list-group">
                    <?php foreach ($company_custom_field_definitions as $field) { ?>
                    <li class="list-group-item">
                        <b><?= e($field['name']); ?></b>:
                        <a href="#" class="settings-textarea-merge-field" data-to="organization_company_info_format">{cf_<?= (int) $field['id']; ?>}</a>
                    </li>
                    <?php } ?>
                </ul>
                <hr />
                <?php } ?>

                <div class="checkbox checkbox-primary">
                    <input type="checkbox" name="is_primary" id="organization_company_is_primary" value="1">
                    <label for="organization_company_is_primary"><?= _l('organization_company_set_primary'); ?></label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?= _l('settings_save'); ?></button>
            </div>
            <?= form_close(); ?>
        </div>
    </div>
</div>
