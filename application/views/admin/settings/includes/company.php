<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$this->load->helper('organization_companies');
$this->load->model('organization_companies_model');
$organization_companies = organization_companies_table_exists()
    ? $this->organization_companies_model->get()
    : [];
?>
<div role="tabpanel" class="tab-pane" id="company_info">
    <div class="alert alert-info">
        <?= _l('settings_sales_company_info_note'); ?>
    </div>

    <div class="tw-mb-4 tw-flex tw-justify-between tw-items-center">
        <h4 class="tw-mt-0 tw-mb-0 tw-font-semibold tw-text-neutral-700">
            <?= _l('organization_companies'); ?>
        </h4>
        <?php if (staff_can('edit', 'settings') && organization_companies_table_exists()) { ?>
        <button type="button" class="btn btn-primary" id="organization-company-add-btn">
            <i class="fa-regular fa-plus tw-mr-1"></i>
            <?= _l('organization_company_add'); ?>
        </button>
        <?php } ?>
    </div>

    <?php if (! organization_companies_table_exists()) { ?>
    <div class="alert alert-warning">
        <?= _l('organization_companies_migration_required'); ?>
    </div>
    <?php } else { ?>
    <div class="row" id="organization-companies-grid">
        <?php foreach ($organization_companies as $company) { ?>
        <div class="col-md-6 col-lg-4 tw-mb-4 organization-company-card-wrap"
            data-company-id="<?= (int) $company['id']; ?>">
            <div
                class="panel_s tw-h-full tw-border tw-border-solid tw-border-neutral-200 tw-rounded-lg tw-overflow-hidden">
                <div class="panel-body">
                    <div class="tw-flex tw-justify-between tw-items-start tw-mb-3">
                        <h4 class="tw-mt-0 tw-mb-0 tw-font-bold tw-text-neutral-800 tw-text-base company-card-name">
                            <?= e($company['name']); ?>
                        </h4>
                        <?php if ((int) $company['is_primary'] === 1) { ?>
                        <span class="label label-primary"><?= _l('organization_company_primary'); ?></span>
                        <?php } ?>
                    </div>
                    <ul class="tw-list-none tw-pl-0 tw-mb-0 tw-space-y-1.5 tw-text-sm tw-text-neutral-600">
                        <?php if (! empty($company['address'])) { ?>
                        <li><i class="fa-regular fa-location-dot tw-mr-1 tw-text-neutral-400"></i>
                            <?= e($company['address']); ?></li>
                        <?php } ?>
                        <?php
                        $location = array_filter([$company['city'], $company['state'], $company['zip_code']]);
            if (count($location) > 0) { ?>
                        <li><?= e(implode(', ', $location)); ?></li>
                        <?php } ?>
                        <?php if (! empty($company['country_code'])) { ?>
                        <li><?= _l('settings_sales_country_code'); ?>:
                            <?= e($company['country_code']); ?></li>
                        <?php } ?>
                        <?php if (! empty($company['phone'])) { ?>
                        <li><i class="fa-regular fa-phone tw-mr-1 tw-text-neutral-400"></i>
                            <?= e($company['phone']); ?></li>
                        <?php } ?>
                        <?php if (! empty($company['vat'])) { ?>
                        <li><?= _l('company_vat_number'); ?>:
                            <?= e($company['vat']); ?></li>
                        <?php } ?>
                    </ul>
                </div>
                <?php if (staff_can('edit', 'settings')) { ?>
                <div
                    class="panel-footer tw-bg-neutral-50 tw-flex tw-gap-2 tw-justify-end tw-border-t tw-border-neutral-200">
                    <button type="button"
                        class="btn btn-default btn-sm organization-company-edit"
                        data-id="<?= (int) $company['id']; ?>">
                        <i class="fa-regular fa-pen-to-square"></i>
                        <?= _l('edit'); ?>
                    </button>
                    <?php if ((int) $company['is_primary'] !== 1) { ?>
                    <button type="button"
                        class="btn btn-danger btn-sm organization-company-delete"
                        data-id="<?= (int) $company['id']; ?>"
                        data-name="<?= e($company['name']); ?>">
                        <i class="fa-regular fa-trash-can"></i>
                        <?= _l('delete'); ?>
                    </button>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
        </div>
        <?php } ?>
    </div>

    <?php if (count($organization_companies) === 0) { ?>
    <div class="alert alert-default" id="organization-companies-empty">
        <?= _l('organization_companies_empty'); ?>
    </div>
    <?php } ?>
    <?php } ?>
</div>
