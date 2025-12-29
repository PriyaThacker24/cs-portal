<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php init_head(); ?>

<div id="wrapper">
    
    <div id="content">
        <div class="page-content-wrapper">
            <div class="page-content">
                <div class="timelog-container">
                    <!-- Header -->
                    <div class="timelog-header">
                        <div class="timelog-header-left">
                            <!-- Group By Dropdowns -->
                            <div class="timelog-group-by">
                                <select id="group_by_date" class="form-control timelog-dropdown">
                                    <option value="date" <?= ($filters['group_by'] == 'date' ? 'selected' : ''); ?>><?= _l('group_by_date'); ?></option>
                                    <option value="user" <?= ($filters['group_by'] == 'user' ? 'selected' : ''); ?>><?= _l('group_by_user'); ?></option>
                                </select>
                            </div>
                            
                           
                        </div>
                         <!-- Week Navigation -->
                         <div class="timelog-header-center">
                         <div class="timelog-week-nav">
                                <button type="button" class="btn btn-default btn-week-nav" id="btn_prev_week" title="<?= _l('previous_week'); ?>">
                                    <i class="fa fa-chevron-left"></i>
                                </button>
                                <span class="timelog-week-display" id="week_display">
                                    <?= date('d/m/Y', strtotime($week_start)); ?> - <?= date('d/m/Y', strtotime($week_end)); ?> 
                                    (<?= _l('week'); ?> <?= date('W', strtotime($week_start)); ?>)
                                </span>
                                <button type="button" class="btn btn-default btn-week-nav" id="btn_next_week" title="<?= _l('next_week'); ?>">
                                    <i class="fa fa-chevron-right"></i>
                                </button>
                            </div>
</div>
                        <div class="timelog-header-right">
                            <button type="button" class="btn btn-primary" id="btn_add_timelog">
                                <i class="fa fa-plus"></i> <?= _l('add_time_log'); ?>
                            </button>
                            <!-- <button type="button" class="btn btn-default" id="btn_toggle_view">
                                <i class="fa fa-list"></i> <?= _l('list_view'); ?>
                            </button> -->
                            <button type="button" class="btn btn-default" id="btn_filter">
                                <i class="fa fa-filter"></i> <?= _l('filters'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="timelog-summary-footer" id="timelog_summary">
                        <div class="timelog-summary-row">
                            <div class="timelog-summary-item">
                                <span class="summary-label"><?= _l('total_billable_hours'); ?>:</span>
                                <span class="summary-value" id="summary_billable_hours">0.00h</span>
                            </div>
                            <div class="timelog-summary-item">
                                <span class="summary-label"><?= _l('total_non_billable_hours'); ?>:</span>
                                <span class="summary-value" id="summary_non_billable_hours">0.00h</span>
                            </div>
                            <div class="timelog-summary-item">
                                <span class="summary-label"><?= _l('total_hours'); ?>:</span>
                                <span class="summary-value" id="summary_total_hours">0.00h</span>
                            </div>
                            <div class="timelog-summary-item">
                                <span class="summary-label"><?= _l('total_records'); ?>:</span>
                                <span class="summary-value" id="summary_total_records">0</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filters Panel (Initially Hidden) -->
                    <div class="timelog-filters-panel" id="filters_panel" style="display: none;">
                        <div class="timelog-filters-content">
                            <div class="form-group">
                                <label><?= _l('project'); ?></label>
                                <select id="filter_project" class="form-control selectpicker" data-live-search="true">
                                    <option value=""><?= _l('all_projects'); ?></option>
                                    <?php foreach ($projects as $project) { ?>
                                        <option value="<?= $project['id']; ?>" <?= ($filters['project_id'] == $project['id'] ? 'selected' : ''); ?>>
                                            <?= e($project['name']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><?= _l('staff_member'); ?></label>
                                <select id="filter_staff" class="form-control selectpicker" data-live-search="true">
                                    <option value=""><?= _l('all_staff'); ?></option>
                                    <?php foreach ($staff as $member) { ?>
                                        <option value="<?= $member['staffid']; ?>" <?= ($filters['staff_id'] == $member['staffid'] ? 'selected' : ''); ?>>
                                            <?= e($member['firstname'] . ' ' . $member['lastname']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><?= _l('billing_type'); ?></label>
                                <select id="filter_billing_type" class="form-control">
                                    <option value=""><?= _l('all'); ?></option>
                                    <option value="billable" <?= ($filters['billing_type'] == 'billable' ? 'selected' : ''); ?>>
                                        <?= _l('task_billable'); ?>
                                    </option>
                                    <option value="non_billable" <?= ($filters['billing_type'] == 'non_billable' ? 'selected' : ''); ?>>
                                        <?= _l('task_not_billable'); ?>
                                    </option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <button type="button" class="btn btn-primary" id="btn_apply_filters">
                                    <?= _l('apply_filters'); ?>
                                </button>
                                <button type="button" class="btn btn-default" id="btn_clear_filters">
                                    <?= _l('clear_filters'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Loading Indicator -->
                    <div id="timelog_loading" class="text-center" style="display: none;">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                        <p><?= _l('loading'); ?>...</p>
                    </div>
                    
                    <!-- Timelog Content -->
                    <div id="timelog_content" class="timelog-content">
                        <!-- Content will be loaded via AJAX -->
                    </div>
                    
                    <!-- Summary Footer -->
                    
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Add Time Log Drawer -->
<?php $this->load->view('add_timelog_drawer'); ?>

<!-- Hidden inputs for current state -->
<input type="hidden" id="current_week_start" value="<?= $week_start; ?>">
<input type="hidden" id="current_group_by" value="<?= $filters['group_by']; ?>">

<?php init_tail(); ?>

<!-- Timelog CSS -->
<link rel="stylesheet" href="<?= module_dir_url('timelog', 'assets/css/timelog.css'); ?>">

<!-- Timelog JavaScript -->
<script src="<?= module_dir_url('timelog', 'assets/js/timelog.js'); ?>"></script>

<script>
    $(document).ready(function() {
        TimelogModule.init();
    });
</script>

