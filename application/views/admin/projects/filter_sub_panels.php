<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php
// Get active staff members
$activeStaff = $this->staff_model->get('', ['active' => 1]);

// Project statuses for the Status filter — use the same list as the project Add/Edit form
$this->load->model('projects_model');
$projectStatuses = $this->projects_model->get_project_statuses();

// Get deactive (inactive) staff members
$deactiveStaff = $this->staff_model->get('', ['active' => 0]);

// Orphaned owner_id values (project owner field) — not project members or manager
$deletedStaff = [];
if ($this->db->field_exists('owner_id', db_prefix() . 'projects')) {
    $deletedStaffQuery = $this->db->query(
        'SELECT DISTINCT p.owner_id AS staff_id, CONCAT("Deleted User #", p.owner_id) AS full_name
         FROM ' . db_prefix() . 'projects p
         LEFT JOIN ' . db_prefix() . 'staff s ON s.staffid = p.owner_id
         WHERE p.owner_id IS NOT NULL AND p.owner_id > 0 AND s.staffid IS NULL'
    );
    $deletedStaff = $deletedStaffQuery->result_array();
}
?>

<!-- Project Name -->
<div class="filter-accordion-item" data-filter="project_name">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('project_name'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('operator'); ?></label>
            <select class="form-control selectpicker" name="project_name_operator">
                <option value="is"><?= _l('is'); ?></option>
                <option value="is_not"><?= _l('is_not'); ?></option>
                <option value="contains"><?= _l('contains'); ?></option>
                <option value="does_not_contain"><?= _l('does_not_contain'); ?></option>
                <option value="starts_with"><?= _l('starts_with'); ?></option>
                <option value="ends_with"><?= _l('ends_with'); ?></option>
            </select>
        </div>
        <div class="form-group">
            <label><?= _l('value'); ?></label>
            <input type="text" class="form-control" name="project_name_value" placeholder="<?= _l('project_name'); ?>">
        </div>
    </div>
</div>

<!-- Status -->
<div class="filter-accordion-item" data-filter="status">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('status'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('operator'); ?></label>
            <select class="form-control selectpicker" name="status_operator" id="status_operator_select">
                <option value="is"><?= _l('is'); ?></option>
                <option value="is_not"><?= _l('is_not'); ?></option>
            </select>
        </div>
        <div class="form-group">
            <label><?= _l('select_status'); ?></label>
            <select class="form-control selectpicker" multiple name="status_value[]" data-actions-box="true">
                <?php foreach ($projectStatuses as $st) { ?>
                    <option value="<?= e($st['id']); ?>"><?= e($st['name']); ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
</div>

<!-- Owner -->
<div class="filter-accordion-item" data-filter="owner">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('project_owner'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('operator'); ?></label>
            <select class="form-control selectpicker" name="owner_operator" id="owner_operator_select">
                <option value="is"><?= _l('is'); ?></option>
                <option value="deactive_user"><?= _l('deactive_user'); ?></option>
                <option value="deleted_user"><?= _l('deleted_user'); ?></option>
            </select>
        </div>
        <!-- Active Users Dropdown (shown when "Is" is selected) -->
        <div class="form-group owner-users-group" id="owner_active_users_group">
            <label><?= _l('select_owner'); ?></label>
            <select class="form-control selectpicker" multiple name="owner_value[]" data-live-search="true" data-actions-box="true" id="owner_active_select">
                <?php foreach ($activeStaff as $member) { ?>
                    <option value="<?= $member['staffid']; ?>"><?= e($member['firstname'] . ' ' . $member['lastname']); ?></option>
                <?php } ?>
            </select>
        </div>
        <!-- Deactive Users Dropdown (shown when "Deactive User" is selected) -->
        <div class="form-group owner-users-group" id="owner_deactive_users_group" style="display:none;">
            <label><?= _l('select_deactive_user'); ?></label>
            <select class="form-control selectpicker" multiple name="owner_deactive_value[]" data-live-search="true" data-actions-box="true" id="owner_deactive_select">
                <?php if (!empty($deactiveStaff)) : ?>
                    <?php foreach ($deactiveStaff as $member) { ?>
                        <option value="<?= $member['staffid']; ?>"><?= e($member['firstname'] . ' ' . $member['lastname']); ?></option>
                    <?php } ?>
                <?php else : ?>
                    <option value="" disabled><?= _l('no_deactive_users_found'); ?></option>
                <?php endif; ?>
            </select>
        </div>
        <!-- Deleted Users Dropdown (shown when "Deleted User" is selected) -->
        <div class="form-group owner-users-group" id="owner_deleted_users_group" style="display:none;">
            <label><?= _l('select_deleted_user'); ?></label>
            <select class="form-control selectpicker" multiple name="owner_deleted_value[]" data-live-search="true" data-actions-box="true" id="owner_deleted_select">
                <?php if (!empty($deletedStaff)) : ?>
                    <?php foreach ($deletedStaff as $member) { ?>
                        <option value="<?= $member['staff_id']; ?>"><?= e($member['full_name']); ?></option>
                    <?php } ?>
                <?php else : ?>
                    <option value="" disabled><?= _l('no_deleted_users_found'); ?></option>
                <?php endif; ?>
            </select>
        </div>
    </div>
</div>

<!-- Project Manager -->
<div class="filter-accordion-item" data-filter="manager">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('project_manager'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('operator'); ?></label>
            <select class="form-control selectpicker" name="manager_operator" id="manager_operator_select">
                <option value="is"><?= _l('is'); ?></option>
                <option value="is_not"><?= _l('is_not'); ?></option>
            </select>
        </div>
        <div class="form-group">
            <label><?= _l('select_manager'); ?></label>
            <select class="form-control selectpicker" multiple name="manager_value[]" data-live-search="true" data-actions-box="true" id="manager_filter_select">
                <?php foreach ($activeStaff as $member) { ?>
                    <option value="<?= $member['staffid']; ?>"><?= e($member['firstname'] . ' ' . $member['lastname']); ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
</div>

<!-- Start Date -->
<div class="filter-accordion-item" data-filter="start_date">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('project_start_date'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('operator'); ?></label>
            <select class="form-control selectpicker" name="start_date_operator" id="start_date_operator_select">
                <option value=""><?= _l('none'); ?></option>
                <!-- Preset Date Operators -->
                <optgroup label="<?= _l('preset_dates'); ?>">
                    <option value="today"><?= _l('today'); ?></option>
                    <option value="yesterday"><?= _l('yesterday'); ?></option>
                    <option value="tomorrow"><?= _l('tomorrow'); ?></option>
                    <option value="till_yesterday"><?= _l('till_yesterday'); ?></option>
                    <option value="this_week"><?= _l('this_week'); ?></option>
                    <option value="last_week"><?= _l('last_week'); ?></option>
                    <option value="next_week"><?= _l('next_week'); ?></option>
                    <option value="this_month"><?= _l('this_month'); ?></option>
                    <option value="last_month"><?= _l('last_month'); ?></option>
                    <option value="next_month"><?= _l('next_month'); ?></option>
                    <option value="last_7_days"><?= _l('last_7_days'); ?></option>
                    <option value="next_30_days"><?= _l('next_30_days'); ?></option>
                    <option value="unscheduled"><?= _l('unscheduled'); ?></option>
                </optgroup>
                <!-- Advanced Operators -->
                <optgroup label="<?= _l('advanced'); ?>">
                    <option value="is"><?= _l('is'); ?></option>
                    <option value="between"><?= _l('between'); ?></option>
                    <option value="less_than"><?= _l('less_than'); ?></option>
                    <option value="greater_than"><?= _l('greater_than'); ?></option>
                    <option value="less_than_or_equal"><?= _l('less_than_or_equal'); ?></option>
                    <option value="greater_than_or_equal"><?= _l('greater_than_or_equal'); ?></option>
                </optgroup>
            </select>
        </div>
        <!-- Single Date Picker (shown for is, less_than, greater_than, etc.) -->
        <div class="form-group start-date-input-group" id="start_date_single_picker" style="display:none;">
            <label><?= _l('date'); ?></label>
            <input type="text" class="form-control datepicker" name="start_date_value" autocomplete="off">
        </div>
        <!-- Date Range Pickers (shown for between) -->
        <div class="form-group start-date-input-group" id="start_date_range_picker" style="display:none;">
            <label><?= _l('from_date'); ?></label>
            <input type="text" class="form-control datepicker" name="start_date_from" autocomplete="off">
        </div>
        <div class="form-group start-date-input-group" id="start_date_range_picker_end" style="display:none;">
            <label><?= _l('to_date'); ?></label>
            <input type="text" class="form-control datepicker" name="start_date_to" autocomplete="off">
        </div>
    </div>
</div>

<!-- Due Date (Deadline) -->
<div class="filter-accordion-item" data-filter="due_date">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('project_deadline'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('operator'); ?></label>
            <select class="form-control selectpicker" name="due_date_operator" id="due_date_operator_select">
                <option value=""><?= _l('none'); ?></option>
                <!-- Preset Date Operators -->
                <optgroup label="<?= _l('preset_dates'); ?>">
                    <option value="today"><?= _l('today'); ?></option>
                    <option value="yesterday"><?= _l('yesterday'); ?></option>
                    <option value="tomorrow"><?= _l('tomorrow'); ?></option>
                    <option value="till_yesterday"><?= _l('till_yesterday'); ?></option>
                    <option value="this_week"><?= _l('this_week'); ?></option>
                    <option value="last_week"><?= _l('last_week'); ?></option>
                    <option value="next_week"><?= _l('next_week'); ?></option>
                    <option value="this_month"><?= _l('this_month'); ?></option>
                    <option value="last_month"><?= _l('last_month'); ?></option>
                    <option value="next_month"><?= _l('next_month'); ?></option>
                    <option value="last_7_days"><?= _l('last_7_days'); ?></option>
                    <option value="next_30_days"><?= _l('next_30_days'); ?></option>
                    <option value="unscheduled"><?= _l('unscheduled'); ?></option>
                </optgroup>
                <!-- Advanced Operators -->
                <optgroup label="<?= _l('advanced'); ?>">
                    <option value="is"><?= _l('is'); ?></option>
                    <option value="between"><?= _l('between'); ?></option>
                    <option value="less_than"><?= _l('less_than'); ?></option>
                    <option value="greater_than"><?= _l('greater_than'); ?></option>
                    <option value="less_than_or_equal"><?= _l('less_than_or_equal'); ?></option>
                    <option value="greater_than_or_equal"><?= _l('greater_than_or_equal'); ?></option>
                </optgroup>
            </select>
        </div>
        <!-- Single Date Picker (shown for is, less_than, greater_than, etc.) -->
        <div class="form-group due-date-input-group" id="due_date_single_picker" style="display:none;">
            <label><?= _l('date'); ?></label>
            <input type="text" class="form-control datepicker" name="due_date_value" autocomplete="off">
        </div>
        <!-- Date Range Pickers (shown for between) -->
        <div class="form-group due-date-input-group" id="due_date_range_picker" style="display:none;">
            <label><?= _l('from_date'); ?></label>
            <input type="text" class="form-control datepicker" name="due_date_from" autocomplete="off">
        </div>
        <div class="form-group due-date-input-group" id="due_date_range_picker_end" style="display:none;">
            <label><?= _l('to_date'); ?></label>
            <input type="text" class="form-control datepicker" name="due_date_to" autocomplete="off">
        </div>
    </div>
</div>

<!-- Created By -->
<div class="filter-accordion-item" data-filter="created_by">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('created_by'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('operator'); ?></label>
            <select class="form-control selectpicker" name="created_by_operator" id="created_by_operator_select">
                <option value="is"><?= _l('is'); ?></option>
                <option value="is_not"><?= _l('is_not'); ?></option>
            </select>
        </div>
        <div class="form-group">
            <label><?= _l('select_staff'); ?></label>
            <select class="form-control selectpicker" multiple name="created_by_value[]" data-live-search="true" data-actions-box="true" id="created_by_staff_select">
                <?php foreach ($activeStaff as $member) { ?>
                    <option value="<?= $member['staffid']; ?>"><?= e($member['firstname'] . ' ' . $member['lastname']); ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
</div>

<script>
function toggleDateRange(select) {
    var $rangeGroup = $(select).closest('.filter-accordion-body').find('.date-range-group');
    if ($(select).val() === 'between') {
        $rangeGroup.slideDown();
    } else {
        $rangeGroup.slideUp();
    }
}
</script>

