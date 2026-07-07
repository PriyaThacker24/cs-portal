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
                        <!-- Date Range Navigation -->
                        <div class="timelog-header-center">
                            <div class="timelog-date-nav">
                                <button type="button" class="btn btn-default btn-date-nav" id="btn_prev_week" title="<?= _l('previous'); ?>">
                                    <i class="fa fa-chevron-left"></i>
                                </button>
                                <button type="button" class="btn btn-default btn-date-display" id="btn_open_date_picker" title="<?= _l('select_date_range'); ?>">
                                    <i class="fa fa-calendar"></i>
                                    <span class="timelog-date-display" id="date_display">
                                        <?= date('d/m/Y', strtotime($week_start)); ?> - <?= date('d/m/Y', strtotime($week_end)); ?> 
                                        (<?= _l('week'); ?> <?= date('W', strtotime($week_start)); ?>)
                                    </span>
                                </button>
                                <button type="button" class="btn btn-default btn-date-nav" id="btn_next_week" title="<?= _l('next'); ?>">
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
                            <!-- <button type="button" class="btn btn-default" id="btn_toggle_view">
                                <i class="fa fa-list"></i> <?= _l('list_view'); ?>
                            </button> -->
                            <div class="btn-group" id="timelogExportControls">
                                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="<?= _l('export'); ?>">
                                    <i class="fa fa-download"></i> <?= _l('export'); ?> <span class="caret"></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-right">
                                    <li><a href="#" class="timelog-export-option" data-format="xlsx"><i class="fa fa-file-excel-o"></i> Excel (.xlsx)</a></li>
                                    <!-- <li><a href="#" class="timelog-export-option" data-format="csv"><i class="fa fa-file-text-o"></i> CSV (.csv)</a></li> -->
                                    <li><a href="#" class="timelog-export-option" data-format="pdf"><i class="fa fa-file-pdf-o"></i> PDF (.pdf)</a></li>
                                </ul>
                            </div>
                            <?php $current_staff_id = get_staff_user_id(); ?>
                            <div class="btn-group timelog-filter-controls" id="timelogFilterControls">
                                <button type="button" class="btn btn-default" id="btn_filter">
                                    <i class="fa fa-filter"></i> <?= _l('filters'); ?>
                                </button>
                                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" id="btnSavedTimelogFilters">
                                    <i class="fa fa-bookmark-o"></i> <?= _l('saved_filters'); ?>
                                    <span class="caret"></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-right saved-filters-menu" id="savedTimelogFiltersMenu">
                                    <li class="saved-filters-empty<?= empty($saved_filters) ? '' : ' hide'; ?>">
                                        <a href="#" onclick="return false;" class="text-muted"><?= _l('no_filters_found'); ?></a>
                                    </li>
                                    <?php foreach (($saved_filters ?? []) as $sf) :
                                        $can_manage = is_admin() || $sf['staff_id'] == $current_staff_id;
                                    ?>
                                    <li class="saved-filter-item<?= $sf['is_default'] == '1' ? ' is-default' : ''; ?>"
                                        data-id="<?= (int) $sf['id']; ?>"
                                        data-name="<?= html_escape($sf['name']); ?>"
                                        data-shared="<?= (int) $sf['is_shared']; ?>"
                                        data-default="<?= (int) ($sf['is_default'] == '1'); ?>"
                                        data-can-manage="<?= $can_manage ? 1 : 0; ?>"
                                        data-builder="<?= html_escape(json_encode($sf['builder'])); ?>">
                                        <a href="#" class="saved-filter-apply" title="<?= _l('filter_apply'); ?>">
                                            <i class="fa fa-star saved-filter-default-icon" aria-hidden="true"></i>
                                            <span class="saved-filter-name"><?= html_escape($sf['name']); ?></span>
                                            <?php if ($sf['is_shared']) : ?>
                                                <i class="fa fa-users text-muted" title="<?= _l('filter_share'); ?>" aria-hidden="true"></i>
                                            <?php endif; ?>
                                        </a>
                                        <span class="saved-filter-actions">
                                            <a href="#" class="saved-filter-default" title="<?= _l('filter_mark_as_default'); ?>"><i class="fa fa-star-o"></i></a>
                                            <?php if ($can_manage) : ?>
                                            <a href="#" class="saved-filter-edit" title="<?= _l('filter_edit'); ?>"><i class="fa fa-pencil"></i></a>
                                            <a href="#" class="saved-filter-delete" title="<?= _l('filter_delete'); ?>"><i class="fa fa-trash"></i></a>
                                            <?php endif; ?>
                                        </span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
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
                    
                    <!-- Date Picker Component -->
                    <?php $this->load->view('timelog/date_picker'); ?>
                    
                    <!-- Advanced Filter Panel (Included via view) -->
                    <?php $this->load->view('timelog/timelog_filter_panel'); ?>
                    
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
<input type="hidden" id="current_week_end" value="<?= $week_end; ?>">
<input type="hidden" id="current_date_range_type" value="week">
<input type="hidden" id="current_group_by" value="<?= $filters['group_by']; ?>">

<!-- Save / Edit Filter Modal -->
<div class="modal fade" id="saveTimelogFilterModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="<?= _l('close'); ?>"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('filter_save'); ?></h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="save_timelog_filter_id" value="">
                <div class="form-group">
                    <label for="save_timelog_filter_name" class="control-label"><?= _l('filter_name'); ?></label>
                    <input type="text" id="save_timelog_filter_name" class="form-control" autocomplete="off">
                </div>
                <div class="checkbox checkbox-primary save-timelog-filter-update-rules-wrapper hide">
                    <input type="checkbox" id="save_timelog_filter_update_rules">
                    <label for="save_timelog_filter_update_rules"><?= _l('filter_update_rules'); ?></label>
                </div>
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" id="save_timelog_filter_is_shared">
                    <label for="save_timelog_filter_is_shared"><?= _l('filter_share'); ?></label>
                </div>
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" id="save_timelog_filter_is_default">
                    <label for="save_timelog_filter_is_default"><?= _l('filter_mark_as_default'); ?></label>
                </div>
                <p class="text-muted"><small><?= _l('default_filter_info'); ?></small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="button" class="btn btn-primary" id="btnSubmitSaveTimelogFilter"><?= _l('submit'); ?></button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<?php
// Cache-busting version based on each asset's last-modified time. Without this,
// browsers (especially on the live server) keep serving a stale cached copy of
// these JS/CSS files after a deploy, which is why fixes such as the week
// prev/next navigation appear to "work on local but not on live".
$timelog_asset_version = function ($relative_path) {
    $absolute_path = module_dir_path('timelog', $relative_path);
    return is_file($absolute_path) ? filemtime($absolute_path) : '';
};
?>
<!-- Timelog CSS is enqueued into the document <head> via the app_admin_head
     hook in Timelog::index() to prevent a flash of unstyled content (FOUC). -->

<!-- Timelog Filter JavaScript -->
<script src="<?= module_dir_url('timelog', 'assets/js/timelog-filter.js'); ?>?v=<?= $timelog_asset_version('assets/js/timelog-filter.js'); ?>"></script>

<!-- Timelog Date Picker JavaScript -->
<script src="<?= module_dir_url('timelog', 'assets/js/timelog-date-picker.js'); ?>?v=<?= $timelog_asset_version('assets/js/timelog-date-picker.js'); ?>"></script>

<!-- Timelog JavaScript -->
<script src="<?= module_dir_url('timelog', 'assets/js/timelog.js'); ?>?v=<?= $timelog_asset_version('assets/js/timelog.js'); ?>"></script>

<script>
    $(document).ready(function() {
        TimelogModule.init();
        TimelogDatePicker.init();
        
        // Initialize datepickers for range inputs
        if (typeof appDatepicker !== 'undefined') {
            appDatepicker();
        }
    });
</script>

