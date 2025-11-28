<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php
// Get active staff members
$activeStaff = $this->staff_model->get('', ['active' => 1]);

// Get task statuses
$taskStatuses = $this->tasks_model->get_statuses();

// Get task priorities
$taskPriorities = get_tasks_priorities();
?>

<!-- Task Name -->
<div class="filter-accordion-item" data-filter="task_name">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('tasks_dt_name'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('operator'); ?></label>
            <select class="form-control selectpicker" name="task_name_operator">
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
            <input type="text" class="form-control" name="task_name_value" placeholder="<?= _l('tasks_dt_name'); ?>">
        </div>
    </div>
</div>

<!-- Status -->
<div class="filter-accordion-item" data-filter="status">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('task_status'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('select_status'); ?></label>
            <select class="form-control selectpicker" multiple name="status_value[]" data-actions-box="true">
                <?php foreach ($taskStatuses as $status) { ?>
                    <option value="<?= $status['id']; ?>"><?= e($status['name']); ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
</div>

<!-- Priority -->
<div class="filter-accordion-item" data-filter="priority">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('tasks_list_priority'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('select_priority'); ?></label>
            <select class="form-control selectpicker" multiple name="priority_value[]" data-actions-box="true">
                <?php foreach ($taskPriorities as $priority) { ?>
                    <option value="<?= $priority['id']; ?>"><?= e($priority['name']); ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
</div>

<!-- Assigned To -->
<div class="filter-accordion-item" data-filter="assigned">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('task_assigned'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('operator'); ?></label>
            <select class="form-control selectpicker" name="assigned_operator">
                <option value="is"><?= _l('is'); ?></option>
                <option value="is_not"><?= _l('is_not'); ?></option>
            </select>
        </div>
        <div class="form-group">
            <label><?= _l('select_staff'); ?></label>
            <select class="form-control selectpicker" multiple name="assigned_value[]" data-live-search="true" data-actions-box="true">
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
        <span class="filter-label"><?= _l('tasks_dt_datestart'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('operator'); ?></label>
            <select class="form-control selectpicker" name="start_date_operator" id="tasks_start_date_operator_select">
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
        <!-- Single Date Picker -->
        <div class="form-group tasks-start-date-input-group" id="tasks_start_date_single_picker" style="display:none;">
            <label><?= _l('date'); ?></label>
            <input type="text" class="form-control datepicker" name="start_date_value" autocomplete="off">
        </div>
        <!-- Date Range Pickers -->
        <div class="form-group tasks-start-date-input-group" id="tasks_start_date_range_picker" style="display:none;">
            <label><?= _l('from_date'); ?></label>
            <input type="text" class="form-control datepicker" name="start_date_from" autocomplete="off">
        </div>
        <div class="form-group tasks-start-date-input-group" id="tasks_start_date_range_picker_end" style="display:none;">
            <label><?= _l('to_date'); ?></label>
            <input type="text" class="form-control datepicker" name="start_date_to" autocomplete="off">
        </div>
    </div>
</div>

<!-- Due Date -->
<div class="filter-accordion-item" data-filter="due_date">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('task_duedate'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('operator'); ?></label>
            <select class="form-control selectpicker" name="due_date_operator" id="tasks_due_date_operator_select">
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
        <!-- Single Date Picker -->
        <div class="form-group tasks-due-date-input-group" id="tasks_due_date_single_picker" style="display:none;">
            <label><?= _l('date'); ?></label>
            <input type="text" class="form-control datepicker" name="due_date_value" autocomplete="off">
        </div>
        <!-- Date Range Pickers -->
        <div class="form-group tasks-due-date-input-group" id="tasks_due_date_range_picker" style="display:none;">
            <label><?= _l('from_date'); ?></label>
            <input type="text" class="form-control datepicker" name="due_date_from" autocomplete="off">
        </div>
        <div class="form-group tasks-due-date-input-group" id="tasks_due_date_range_picker_end" style="display:none;">
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
            <select class="form-control selectpicker" name="created_by_operator" id="tasks_created_by_operator_select">
                <option value="is"><?= _l('is'); ?></option>
                <option value="is_not"><?= _l('is_not'); ?></option>
            </select>
        </div>
        <div class="form-group">
            <label><?= _l('select_staff'); ?></label>
            <select class="form-control selectpicker" multiple name="created_by_value[]" data-live-search="true" data-actions-box="true" id="tasks_created_by_staff_select">
                <?php foreach ($activeStaff as $member) { ?>
                    <option value="<?= $member['staffid']; ?>"><?= e($member['firstname'] . ' ' . $member['lastname']); ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
</div>

