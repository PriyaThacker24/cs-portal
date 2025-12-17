<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<link rel="stylesheet" href="<?= base_url('assets/css/project-timelog-filter.css'); ?>" />
<a href="#" onclick="new_timesheet();return false;" class="btn btn-primary tw-mb-2">
    <i class="fa-regular fa-plus tw-mr-1"></i>
    <?= _l('record_timesheet'); ?>
</a>
<?php $this->load->view('admin/projects/project_timelog_filter_panel'); ?>
<div class="panel_s">
    <div class="panel-body panel-table-full">
        <?php if (staff_can('create', 'projects')) { ?>
        <div class="_filters _hidden_inputs timesheets_filters hidden">
            <?php
            foreach ($timesheets_staff_ids as $t_staff_id) {
                echo form_hidden('staff_id_' . $t_staff_id['staff_id'], $t_staff_id['staff_id']);
            }
            ?>
        </div>
        <?php if (count($timesheets_staff_ids) > 0) { ?>
        <!-- <div class="btn-group pull-right mleft4 btn-with-tooltip-group _filter_data" data-toggle="tooltip"
            data-title="<?= _l('filter_by'); ?>">
            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true"
                aria-expanded="false">
                <i class="fa fa-filter" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-right width300">
                <?php foreach ($timesheets_staff_ids as $t_staff_id) { ?>
                <li class="active">
                    <a href="#"
                        data-cview="staff_id_<?= e($t_staff_id['staff_id']); ?>"
                        onclick="dt_custom_view(<?= e($t_staff_id['staff_id']); ?>,'.table-timesheets','staff_id_<?= e($t_staff_id['staff_id']); ?>'); return false;"><?= e(get_staff_full_name($t_staff_id['staff_id'])); ?>
                    </a>
                </li>
                <?php } ?>
            </ul>
        </div> -->
        <?php } ?>
        <?php } ?>
        <?php $table_data = [
                _l('project_timesheet_user'),
                _l('project_timesheet_task'),
                _l('status'),
                _l('project_timesheet_start_time'),
                _l('project_timesheet_end_time'),
                _l('note'),
                _l('time_h'),
                _l('time_decimal'), ];
$table_data = hooks()->apply_filters('projects_timesheets_table_columns', $table_data);
array_push($table_data, _l('options'));
render_datatable($table_data, 'timesheets'); ?>
        <?php $this->load->view('admin/projects/timesheet'); ?>
    </div>
</div>

<style>
/* Timesheet status dropdown background colors */
.timesheet-status-change.status-pending {
    background-color: #fcf8e3 !important;
    color: #8a6d3b !important;
    border-color: #f0ad4e !important;
}

.timesheet-status-change.status-approved {
    background-color: #dff0d8 !important;
    color: #3c763d !important;
    border-color: #5cb85c !important;
}

.timesheet-status-change.status-rejected {
    background-color: #f2dede !important;
    color: #a94442 !important;
    border-color: #d9534f !important;
}
</style>

<script>
// Timesheet status change handler
function initTimesheetStatusHandler() {
    if (typeof jQuery === 'undefined') {
        setTimeout(initTimesheetStatusHandler, 100);
        return;
    }
    
    jQuery(document).ready(function($) {
        // Function to update dropdown background color
        function updateDropdownColor($dropdown) {
            var status = $dropdown.val();
            $dropdown.removeClass('status-pending status-approved status-rejected');
            $dropdown.addClass('status-' + status);
        }
        
        // Initialize colors for existing dropdowns after table loads
        $('.table-timesheets').on('draw.dt', function() {
            $('.timesheet-status-change').each(function() {
                updateDropdownColor($(this));
            });
        });
        
        // Initialize colors on page load
        setTimeout(function() {
            $('.timesheet-status-change').each(function() {
                updateDropdownColor($(this));
            });
        }, 1000);
        
        // Handle status change
        $('body').on('change', '.timesheet-status-change', function(e) {
            var $select = $(this);
            var status = $select.val();
            var timesheetId = $select.data('timesheet-id');
            var originalValue = $select.data('original-value');
            
            if (!timesheetId) {
                alert_float('danger', 'Timesheet ID not found');
                return;
            }
            
            // Update color immediately
            updateDropdownColor($select);
            
            $select.prop('disabled', true);
            
            $.ajax({
                url: admin_url + 'projects/update_timesheet_status',
                type: 'POST',
                data: {
                    timesheet_id: timesheetId,
                    status: status
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert_float('success', response.message);
                        $select.data('original-value', status);
                        
                        setTimeout(function() {
                            if ($('.table-timesheets').length && $.fn.DataTable.isDataTable('.table-timesheets')) {
                                $('.table-timesheets').DataTable().ajax.reload(null, false);
                            } else {
                                location.reload();
                            }
                        }, 500);
                    } else {
                        alert_float('danger', response.message);
                        if (originalValue) {
                            $select.val(originalValue);
                            updateDropdownColor($select);
                        }
                    }
                    $select.prop('disabled', false);
                },
                error: function(xhr, status, error) {
                    alert_float('danger', 'Error updating status: ' + error);
                    if (originalValue) {
                        $select.val(originalValue);
                        updateDropdownColor($select);
                    }
                    $select.prop('disabled', false);
                }
            });
        });
    });
}

initTimesheetStatusHandler();
</script><script src="<?= base_url('assets/js/project-timelog-filter.js'); ?>"></script>
<script>
// Initialize the project timelog filter panel
if (typeof ProjectTimelogFilter !== 'undefined') {
    ProjectTimelogFilter.init();
}
</script>
