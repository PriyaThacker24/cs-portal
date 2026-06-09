<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$this->load->helper('organization_companies');
if (! organization_companies_table_exists() || ! staff_can('edit', 'settings')) {
    return;
}
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
                <!-- Row 1: Company Name | Company Logo -->
                <div class="row">
                    <div class="col-md-6">
                        <?= render_input('name', 'settings_sales_company_name'); ?>
                    </div>
                    <div class="col-md-6">
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
                    </div>
                </div>

                <!-- Row 2: Email | Phone -->
                <div class="row">
                    <div class="col-md-6">
                        <?= render_input('email', 'clients_email'); ?>
                    </div>
                    <div class="col-md-6">
                        <?= render_input('phone', 'settings_sales_phonenumber'); ?>
                    </div>
                </div>

                <!-- Row 3: Address (full width) -->
                <div class="row">
                    <div class="col-md-12">
                        <?= render_input('address', 'settings_sales_address'); ?>
                    </div>
                </div>

                <!-- Row 3: City | State -->
                <div class="row">
                    <div class="col-md-6">
                        <?= render_input('city', 'settings_sales_city'); ?>
                    </div>
                    <div class="col-md-6">
                        <?= render_input('state', 'billing_state'); ?>
                    </div>
                </div>

                <!-- Row 4: Country Code | Zip Code -->
                <div class="row">
                    <div class="col-md-6">
                        <?= render_input('country_code', 'settings_sales_country_code'); ?>
                    </div>
                    <div class="col-md-6">
                        <?= render_input('zip_code', 'settings_sales_postal_code'); ?>
                    </div>
                </div>

                <!-- Row 5: VAT/GST -->
                <div class="row">
                    <div class="col-md-6">
                        <?= render_input('gst', 'company_vat_gst_number'); ?>
                    </div>
                </div>

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
