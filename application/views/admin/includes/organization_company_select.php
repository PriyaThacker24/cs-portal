<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$this->load->helper('organization_companies');
if (! organization_companies_table_exists()) {
    return;
}
if (! organization_company_client_column_exists() && ! organization_company_invoice_column_exists()) {
    return;
}
$field_name       = $field_name ?? 'organization_company_id';
$selected         = isset($selected) ? (int) $selected : 0;
$wrapper_class    = $wrapper_class ?? 'form-group select-placeholder';
$options          = organization_company_select_options();
$primary          = get_primary_organization_company();
if ($selected <= 0 && $primary) {
    $selected = (int) $primary->id;
}
?>
<div class="<?= e($wrapper_class); ?>" id="organization-company-select-wrap">
    <label for="<?= e($field_name); ?>" class="control-label">
        <?= _l($label_key ?? 'organization_company_for_customer'); ?>
    </label>
    <select name="<?= e($field_name); ?>" id="<?= e($field_name); ?>" class="selectpicker" data-width="100%"
        data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
        <?php foreach ($options as $option) { ?>
        <option value="<?= (int) $option['id']; ?>" <?= $selected === (int) $option['id'] ? 'selected' : ''; ?>>
            <?= e($option['name']); ?>
        </option>
        <?php } ?>
    </select>
    <p class="text-muted tw-mb-0 tw-mt-1 tw-text-sm"><?= _l('organization_company_invoice_hint'); ?></p>
</div>
