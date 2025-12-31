<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<!-- Timelog CSS -->
<link rel="stylesheet" href="<?= module_dir_url('timelog', 'assets/css/timelog.css'); ?>?v=<?= time(); ?>">
<!-- Project Timelog Filter CSS (for advanced filter panel) -->
<link rel="stylesheet" href="<?= base_url('assets/css/project-timelog-filter.css'); ?>?v=<?= time(); ?>">

<?php
// Load timelog module data
$this->load->model('timelog/Timelog_model', 'timelog_model');
$this->load->model('projects_model');
$this->load->model('staff_model');

// Get current week (default)
$week_start = $this->input->get('week_start');
if (empty($week_start)) {
    $week_start = date('Y-m-d', strtotime('monday this week'));
}
$week_end = date('Y-m-d', strtotime('sunday this week', strtotime($week_start)));

// Get current filters (default group_by is 'date')
$filters = [
    'project_id' => $project->id, // Always filter by current project
    'staff_id' => $this->input->get('staff_id'),
    'billing_type' => $this->input->get('billing_type'),
    'group_by' => $this->input->get('group_by') ?: 'date',
];

$projects = $this->projects_model->get('', ['status !=' => 0]);
$staff = $this->staff_model->get('', ['active' => 1]);
?>

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
            <?php if (staff_can('create', 'timesheets') || is_admin()) { ?>
            <button type="button" class="btn btn-primary" id="btn_add_timelog">
                <i class="fa fa-plus"></i> <?= _l('add_time_log'); ?>
            </button>
            <?php } ?>
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
    
    <!-- Advanced Filter Panel (Included via view) -->
    <?php 
    // Pass project context to filter panel
    $data['hide_project_filter'] = true;
    $data['project_id'] = $project->id;
    $this->load->view('timelog/timelog_filter_panel', $data); 
    ?>
    
    <!-- Loading Indicator -->
    <div id="timelog_loading" class="text-center" style="display: none;">
        <i class="fa fa-spinner fa-spin fa-2x"></i>
        <p><?= _l('loading'); ?>...</p>
    </div>
    
    <!-- Timelog Content -->
    <div id="timelog_content" class="timelog-content">
        <!-- Content will be loaded via AJAX -->
    </div>
</div>

<!-- Include Add Time Log Drawer -->
<?php $this->load->view('timelog/add_timelog_drawer'); ?>

<!-- Hidden inputs for current state -->
<input type="hidden" id="current_week_start" value="<?= $week_start; ?>">
<input type="hidden" id="current_group_by" value="<?= $filters['group_by']; ?>">
<input type="hidden" id="current_project_id" value="<?= $project->id; ?>">

<!-- Timelog Filter JavaScript -->
<script>
(function() {
    var scriptsLoaded = 0;
    var scriptsToLoad = 2;
    
    function checkAndInit() {
        scriptsLoaded++;
        if (scriptsLoaded >= scriptsToLoad) {
            initTimelogModule();
        }
    }
    
    function initTimelogModule() {
        // Check if jQuery and required modules are available
        if (typeof jQuery === 'undefined') {
            setTimeout(initTimelogModule, 100);
            return;
        }
        
        var $ = jQuery;
        
        // Wait for DOM to be ready
        $(document).ready(function() {
            // Set project filter in localStorage for filter panel initialization
            var projectId = <?= $project->id; ?>;
            if (projectId && typeof localStorage !== 'undefined') {
                var filterData = {
                    match: 'any',
                    project: {
                        operator: 'is',
                        value: [projectId]
                    }
                };
                localStorage.setItem('timelog_filters', JSON.stringify(filterData));
            }
            
            // Wait a bit for scripts to fully initialize
            setTimeout(function() {
                // Initialize timelog module (it will automatically detect #current_project_id)
                if (typeof TimelogModule !== 'undefined' && TimelogModule && typeof TimelogModule.init === 'function') {
                    TimelogModule.init();
                } else {
                    console.error('TimelogModule not available or init function missing');
                    console.log('TimelogModule:', typeof TimelogModule);
                }
                
                // Initialize filter panel if available
                if (typeof TimelogFilter !== 'undefined' && TimelogFilter && typeof TimelogFilter.init === 'function') {
                    TimelogFilter.init();
                }
            }, 100);
        });
    }
    
    // Load scripts sequentially
    var filterScript = document.createElement('script');
    filterScript.src = '<?= module_dir_url('timelog', 'assets/js/timelog-filter.js'); ?>';
    filterScript.onload = checkAndInit;
    filterScript.onerror = function() {
        console.error('Failed to load timelog-filter.js');
        checkAndInit();
    };
    document.head.appendChild(filterScript);
    
    var timelogScript = document.createElement('script');
    timelogScript.src = '<?= module_dir_url('timelog', 'assets/js/timelog.js'); ?>';
    timelogScript.onload = checkAndInit;
    timelogScript.onerror = function() {
        console.error('Failed to load timelog.js');
        checkAndInit();
    };
    document.head.appendChild(timelogScript);
})();
</script>
