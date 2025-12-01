<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php
// Get active staff members
$activeStaff = $this->staff_model->get('', ['active' => 1]);

// Get current user ID and project ID
$currentUserId = get_staff_user_id();
$currentProjectId = isset($project) ? $project->id : (isset($project_id) ? $project_id : 0);

// Determine if user can see all tasks based on permissions
$canViewAllTasks = false;

// Priority 1: Check staff-level "Task Add" permission (shows all tasks)
if (staff_can('create', 'tasks')) {
    $canViewAllTasks = true;
}

// Priority 2: Check project-level "Task Add" permission for this specific project
if (!$canViewAllTasks && $currentProjectId > 0) {
    $this->load->model('projects_model');
    if ($this->projects_model->hasProjectPermission($currentUserId, $currentProjectId, 'task_create')) {
        $canViewAllTasks = true;
    }
}

// Fetch ALL tasks for this project directly from database (not using pre-filtered $tasks)
$filterTasks = [];
if ($currentProjectId > 0) {
    $this->load->model('tasks_model');
    
    if ($canViewAllTasks) {
        // User has Task Add permission - fetch ALL tasks for this project (no status filter)
        $allProjectTasks = $this->db->select('id, name, status')
            ->from(db_prefix() . 'tasks')
            ->where('rel_type', 'project')
            ->where('rel_id', $currentProjectId)
            ->order_by('name', 'ASC')
            ->get()
            ->result_array();
        
        $filterTasks = $allProjectTasks;
    } else {
        // User does NOT have Task Add permission - fetch only assigned tasks
        $assignedTasks = $this->db->select(db_prefix() . 'tasks.id, ' . db_prefix() . 'tasks.name, ' . db_prefix() . 'tasks.status')
            ->from(db_prefix() . 'tasks')
            ->join(db_prefix() . 'task_assigned', db_prefix() . 'task_assigned.taskid = ' . db_prefix() . 'tasks.id')
            ->where(db_prefix() . 'tasks.rel_type', 'project')
            ->where(db_prefix() . 'tasks.rel_id', $currentProjectId)
            ->where(db_prefix() . 'task_assigned.staffid', $currentUserId)
            ->order_by(db_prefix() . 'tasks.name', 'ASC')
            ->get()
            ->result_array();
        
        $filterTasks = $assignedTasks;
    }
}
?>

<!-- Log User Filter -->
<div class="filter-accordion-item" data-filter="log_user">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('staff_member'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('operator'); ?></label>
            <select class="form-control selectpicker" name="log_user_operator" id="project_log_user_operator_select">
                <option value="is"><?= _l('is'); ?></option>
                <option value="is_not"><?= _l('is_not'); ?></option>
            </select>
        </div>
        <div class="form-group">
            <label><?= _l('select_staff'); ?></label>
            <select class="form-control selectpicker" multiple name="log_user_value[]" data-live-search="true" data-actions-box="true" id="project_log_user_staff_select">
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
            <select class="form-control selectpicker" multiple name="work_item_value[]" data-live-search="true" data-actions-box="true" id="project_work_item_task_select">
                <?php if (!empty($filterTasks)) : ?>
                    <?php foreach ($filterTasks as $task) { ?>
                        <option value="<?= $task['id']; ?>"><?= e($task['name']); ?></option>
                    <?php } ?>
                <?php else : ?>
                    <option value="" disabled><?= _l('no_tasks_found'); ?></option>
                <?php endif; ?>
            </select>
        </div>
    </div>
</div>

<!-- Billing Type Filter -->
<div class="filter-accordion-item" data-filter="billing_type">
    <button type="button" class="filter-accordion-header" aria-expanded="false">
        <span class="filter-label"><?= _l('billing_type'); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filter-accordion-body" aria-hidden="true">
        <div class="form-group">
            <label><?= _l('operator'); ?></label>
            <select class="form-control selectpicker" name="billing_type_operator" id="project_billing_type_operator_select">
                <option value="is"><?= _l('is'); ?></option>
                <option value="is_not"><?= _l('is_not'); ?></option>
            </select>
        </div>
        <div class="form-group">
            <label><?= _l('select_type'); ?></label>
            <select class="form-control selectpicker" name="billing_type_value" id="project_billing_type_value_select">
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
            <select class="form-control selectpicker" name="approval_status_operator" id="project_approval_status_operator_select">
                <option value="is"><?= _l('is'); ?></option>
                <option value="is_not"><?= _l('is_not'); ?></option>
            </select>
        </div>
        <div class="form-group">
            <label><?= _l('select_status'); ?></label>
            <select class="form-control selectpicker" multiple name="approval_status_value[]" data-actions-box="true" id="project_approval_status_value_select">
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
            <select class="form-control selectpicker" name="created_by_operator" id="project_timelog_created_by_operator_select">
                <option value="is"><?= _l('is'); ?></option>
                <option value="is_not"><?= _l('is_not'); ?></option>
            </select>
        </div>
        <div class="form-group">
            <label><?= _l('select_staff'); ?></label>
            <select class="form-control selectpicker" multiple name="created_by_value[]" data-live-search="true" data-actions-box="true" id="project_timelog_created_by_staff_select">
                <?php foreach ($activeStaff as $member) { ?>
                    <option value="<?= $member['staffid']; ?>"><?= e($member['firstname'] . ' ' . $member['lastname']); ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
</div>

