<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php
// Get active staff members
$activeStaff = $this->staff_model->get('', ['active' => 1]);

// Get all tasks across all projects for timelog filter
$this->load->model('tasks_model');
$allTasks = $this->db->select('id, name, status, rel_id')
    ->from(db_prefix() . 'tasks')
    ->where('rel_type', 'project')
    ->order_by('name', 'ASC')
    ->get()
    ->result_array();
?>

<!-- Project Filter -->
<div class="filter-accordion-item" data-filter="project">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('project'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('operator'); ?></label>
            <select class="form-control selectpicker" name="project_operator" id="timelog_project_operator_select">
                <option value="is"><?= _l('is'); ?></option>
                <option value="is_not"><?= _l('is_not'); ?></option>
            </select>
        </div>
        <div class="form-group">
            <label><?= _l('select_project'); ?></label>
            <select class="form-control selectpicker" multiple name="project_value[]" data-live-search="true" data-actions-box="true" id="timelog_project_select">
                <?php foreach ($projects as $project) { ?>
                    <option value="<?= $project['id']; ?>"><?= e($project['name']); ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
</div>

<!-- Log User Filter -->
<div class="filter-accordion-item" data-filter="log_user">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('staff_member'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('operator'); ?></label>
            <select class="form-control selectpicker" name="log_user_operator" id="timelog_log_user_operator_select">
                <option value="is"><?= _l('is'); ?></option>
                <option value="is_not"><?= _l('is_not'); ?></option>
            </select>
        </div>
        <div class="form-group">
            <label><?= _l('select_staff'); ?></label>
            <select class="form-control selectpicker" multiple name="log_user_value[]" data-live-search="true" data-actions-box="true" id="timelog_log_user_staff_select">
                <?php foreach ($activeStaff as $member) { ?>
                    <option value="<?= $member['staffid']; ?>"><?= e($member['firstname'] . ' ' . $member['lastname']); ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
</div>

<!-- Work Item (Task) Filter -->
<div class="filter-accordion-item" data-filter="work_item">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('work_item'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('select_task'); ?></label>
            <select class="form-control selectpicker" multiple name="work_item_value[]" data-live-search="true" data-actions-box="true" id="timelog_work_item_task_select">
                <?php if (!empty($allTasks)) : ?>
                    <?php foreach ($allTasks as $task) { ?>
                        <option value="<?= $task['id']; ?>"><?= e($task['name']); ?></option>
                    <?php } ?>
                <?php else : ?>
                    <option value="" disabled><?= _l('no_tasks_found'); ?></option>
                <?php endif; ?>
            </select>
        </div>
    </div>
</div>

<!-- Start Date Filter -->
<!-- <div class="filter-accordion-item" data-filter="start_date">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('task_single_start_date'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('operator'); ?></label>
            <select class="form-control selectpicker" name="start_date_operator" id="timelog_start_date_operator_select">
              
                <optgroup label="<?= _l('preset_dates'); ?>">
                    <option value="today"><?= _l('today'); ?></option>
                    <option value="till_yesterday"><?= _l('till_yesterday'); ?></option>
                    <option value="unscheduled"><?= _l('unscheduled'); ?></option>
                    <option value="yesterday"><?= _l('yesterday'); ?></option>
                    <option value="tomorrow"><?= _l('tomorrow'); ?></option>
                    <option value="this_week"><?= _l('this_week'); ?></option>
                    <option value="this_month"><?= _l('this_month'); ?></option>
                    <option value="last_week"><?= _l('last_week'); ?></option>
                    <option value="last_month"><?= _l('last_month'); ?></option>
                    <option value="last_7_days"><?= _l('last_7_days'); ?></option>
                    <option value="next_week"><?= _l('next_week'); ?></option>
                    <option value="next_month"><?= _l('next_month'); ?></option>
                    <option value="next_30_days"><?= _l('next_30_days'); ?></option>
                </optgroup>
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
        <div class="form-group start-date-input-group" id="timelog_start_date_single_picker" style="display:none;">
            <label><?= _l('date'); ?></label>
            <input type="text" class="form-control datepicker" name="start_date_value" autocomplete="off">
        </div>
        <div class="form-group start-date-input-group" id="timelog_start_date_range_picker" style="display:none;">
            <label><?= _l('from_date'); ?></label>
            <input type="text" class="form-control datepicker" name="start_date_from" autocomplete="off">
        </div>
        <div class="form-group start-date-input-group" id="timelog_start_date_range_picker_end" style="display:none;">
            <label><?= _l('to_date'); ?></label>
            <input type="text" class="form-control datepicker" name="start_date_to" autocomplete="off">
        </div>
    </div>
</div> -->

<!-- Billing Type Filter -->
<div class="filter-accordion-item" data-filter="billing_type">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('billing_type'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('operator'); ?></label>
            <select class="form-control selectpicker" name="billing_type_operator" id="timelog_billing_type_operator_select">
                <option value="is"><?= _l('is'); ?></option>
                <option value="is_not"><?= _l('is_not'); ?></option>
            </select>
        </div>
        <div class="form-group">
            <label><?= _l('select_type'); ?></label>
            <select class="form-control selectpicker" name="billing_type_value" id="timelog_billing_type_value_select">
                <option value="billable"><?= _l('task_billable'); ?></option>
                <option value="non_billable"><?= _l('task_not_billable'); ?></option>
            </select>
        </div>
    </div>
</div>

<!-- Approval Status Filter -->
<div class="filter-accordion-item" data-filter="approval_status">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('approval_status'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('operator'); ?></label>
            <select class="form-control selectpicker" name="approval_status_operator" id="timelog_approval_status_operator_select">
                <option value="is"><?= _l('is'); ?></option>
                <option value="is_not"><?= _l('is_not'); ?></option>
            </select>
        </div>
        <div class="form-group">
            <label><?= _l('select_status'); ?></label>
            <select class="form-control selectpicker" multiple name="approval_status_value[]" data-actions-box="true" id="timelog_approval_status_value_select">
                <option value="pending"><?= _l('pending'); ?></option>
                <option value="approved"><?= _l('approved'); ?></option>
                <option value="rejected"><?= _l('rejected'); ?></option>
            </select>
        </div>
    </div>
</div>

<!-- Created By Filter -->
<div class="filter-accordion-item" data-filter="created_by">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('created_by'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('operator'); ?></label>
            <select class="form-control selectpicker" name="created_by_operator" id="timelog_created_by_operator_select">
                <option value="is"><?= _l('is'); ?></option>
                <option value="is_not"><?= _l('is_not'); ?></option>
            </select>
        </div>
        <div class="form-group">
            <label><?= _l('select_staff'); ?></label>
            <select class="form-control selectpicker" multiple name="created_by_value[]" data-live-search="true" data-actions-box="true" id="timelog_created_by_staff_select">
                <?php foreach ($activeStaff as $member) { ?>
                    <option value="<?= $member['staffid']; ?>"><?= e($member['firstname'] . ' ' . $member['lastname']); ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
</div>

