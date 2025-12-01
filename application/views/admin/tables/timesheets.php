<?php

defined('BASEPATH') or exit('No direct script access allowed');

use app\services\timelog\ProjectTimelogAdvancedFilters;

// Check if status column exists before adding it to columns
$has_status_column = false;
try {
    $columns = $this->ci->db->list_fields(db_prefix() . 'taskstimers');
    $has_status_column = in_array('status', $columns);
} catch (Exception $e) {
    // If check fails, assume column doesn't exist
}

$aColumns = [
    'CONCAT(firstname, \' \', lastname) as staff',
    'task_id',
    'start_time',
    'end_time',
    'note',
    'end_time - start_time',
    'end_time - start_time',
];

// Add status column if it exists
if ($has_status_column) {
    array_splice($aColumns, 2, 0, [db_prefix() . 'taskstimers.status as timesheet_status']);
}
$sIndexColumn = 'id';
$sTable       = db_prefix() . 'taskstimers';

$aColumns = hooks()->apply_filters('projects_timesheets_table_sql_columns', $aColumns);

$join = [
    'JOIN ' . db_prefix() . 'tasks ON ' . db_prefix() . 'tasks.id = ' . db_prefix() . 'taskstimers.task_id',
    'JOIN ' . db_prefix() . 'staff ON ' . db_prefix() . 'staff.staffid = ' . db_prefix() . 'taskstimers.staff_id',
];

$join = hooks()->apply_filters('projects_timesheets_table_sql_join', $join);

$where = ['AND task_id IN (SELECT id FROM ' . db_prefix() . 'tasks WHERE rel_id="' . $this->ci->db->escape_str($project_id) . '" AND rel_type="project")'];

// Check if user can see all logs (has global permissions or project-level permissions)
$can_see_all_logs = false;

// Check staff-level global permissions
if (staff_can('edit_timesheet', 'tasks') || staff_can('delete_timesheet', 'tasks') || staff_can('create', 'projects')) {
    $can_see_all_logs = true;
}

// Check project-level permissions if staff-level permissions don't exist
if (!$can_see_all_logs) {
    $this->ci->load->model('projects_model');
    if ($this->ci->projects_model->hasProjectPermission(get_staff_user_id(), $project_id, 'log_edit') || 
        $this->ci->projects_model->hasProjectPermission(get_staff_user_id(), $project_id, 'log_delete')) {
        $can_see_all_logs = true;
    }
}

// Only filter by staff_id if user doesn't have permission to see all logs
if (!$can_see_all_logs) {
    array_push($where, 'AND ' . db_prefix() . 'taskstimers.staff_id=' . get_staff_user_id());
}

$staff_ids = $this->ci->projects_model->get_distinct_tasks_timesheets_staff($project_id);

$_staff_ids = [];

foreach ($staff_ids as $s) {
    if ($this->ci->input->post('staff_id_' . $s['staff_id'])) {
        array_push($_staff_ids, $s['staff_id']);
    }
}

if (count($_staff_ids) > 0) {
    array_push($where, 'AND ' . db_prefix() . 'taskstimers.staff_id IN (' . implode(', ', $_staff_ids) . ')');
}

// Apply advanced filters from Zoho-style filter panel
$advancedFiltersJson = $this->ci->input->post('advanced_filters');
if (!empty($advancedFiltersJson)) {
    try {
        $advancedFilters = new ProjectTimelogAdvancedFilters($advancedFiltersJson);
        $advancedWhere = $advancedFilters->buildWhereClause();
        if (!empty($advancedWhere)) {
            array_push($where, $advancedWhere);
        }
    } catch (Exception $e) {
        // Log error but don't break the table
        log_activity('Advanced filter error: ' . $e->getMessage());
    }
}

$additionalSelect = [
    db_prefix() . 'taskstimers.id',
    db_prefix() . 'tasks.name',
    'billed',
    'billable',
    db_prefix() . 'taskstimers.staff_id',
    db_prefix() . 'tasks.status',
];

// Add bill_type and status columns if they exist (for migration compatibility)
try {
    $columns = $this->ci->db->list_fields(db_prefix() . 'taskstimers');
    if (in_array('bill_type', $columns)) {
        $additionalSelect[] = db_prefix() . 'taskstimers.bill_type';
    }
    if (in_array('status', $columns)) {
        $additionalSelect[] = db_prefix() . 'taskstimers.status as timesheet_status';
    }
} catch (Exception $e) {
    // If columns don't exist, continue without them
}

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, $additionalSelect);

$output  = $result['output'];
$rResult = $result['rResult'];

// Check permissions for status dropdown visibility
$can_update_status = false;

// Priority 1: Check staff-level permissions (Global)
if (staff_can('approve_timesheet', 'tasks') || staff_can('reject_timesheet', 'tasks')) {
    $can_update_status = true;
} else {
    // Priority 2: Check project-level permissions
    if (isset($project_id) && !empty($project_id) && is_numeric($project_id)) {
        if (!isset($this->ci->projects_model)) {
            $this->ci->load->model('projects_model');
        }
        // Check project-level approve/reject permissions
        if ($this->ci->projects_model->hasProjectPermission(get_staff_user_id(), $project_id, 'project_log_approve') || 
            $this->ci->projects_model->hasProjectPermission(get_staff_user_id(), $project_id, 'project_log_reject')) {
            $can_update_status = true;
        }
    }
}

foreach ($rResult as $aRow) {
    $row = [];

    for ($i = 0; $i < count($aColumns); $i++) {
        if (strpos($aColumns[$i], 'as') !== false && ! isset($aRow[$aColumns[$i]])) {
            $_data = $aRow[strafter($aColumns[$i], 'as ')];
        } else {
            $_data = $aRow[$aColumns[$i]];
        }

        $user_removed_as_assignee = (total_rows(db_prefix() . 'task_assigned', ['staffid' => $aRow['staff_id'], 'taskid' => $aRow['task_id']]) == 0 ? true : false);

        // Staff full name
        if ($i == 0) {
            $_data = '<div class="mtop5">';
            $_data .= '<a href="' . admin_url('staff/profile/' . $aRow['staff_id']) . '"> ' . staff_profile_image($aRow['staff_id'], [
                'staff-profile-image-xs mright5',
            ]) . '</a>';

            if (staff_can('edit', 'staff')) {
                $_data .= ' <a href="' . admin_url('staff/member/' . $aRow['staff_id']) . '"> ' . e($aRow['staff']) . '</a>';
            } else {
                $_data .= e($aRow['staff']);
            }

            if ($user_removed_as_assignee == 1) {
                $_data .= '<span class="hidden"> - </span> <span class="mtop5 pull-right" data-toggle="tooltip" data-title="' . _l('project_activity_task_assignee_removed') . '"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i></span>';
            }
            $_data .= '</div>';
        } elseif ($aColumns[$i] == 'task_id') {
            $_data = '<a href="' . admin_url('tasks/view/' . $aRow['task_id']) . '" class="mtop5 inline-block" onclick="init_task_modal(' . $aRow['task_id'] . '); return false;">' . e($aRow['name']) . '</a>';
        } elseif ($has_status_column && $i == 2) {
            // Status column - Check permissions
            $timesheet_status = isset($aRow['timesheet_status']) && $aRow['timesheet_status'] != '' ? $aRow['timesheet_status'] : 'pending';
            
            // Set status colors
            $status_class = '';
            $status_text = '';
            
            switch ($timesheet_status) {
                case 'approved':
                    $status_class = 'label-success'; // Green
                    $status_text = _l('approved');
                    break;
                case 'rejected':
                    $status_class = 'label-danger'; // Red
                    $status_text = _l('rejected');
                    break;
                default:
                    $status_class = 'label-warning'; // Yellow
                    $status_text = _l('pending');
                    break;
            }
            
            // Show dropdown if user has permission, otherwise show label
            if ($can_update_status) {
                // User has permission - show dropdown with colored background
                $_data = '<select name="timesheet_status" class="form-control timesheet-status-change status-' . $timesheet_status . '" style="width: auto; display: inline-block; padding: 3px 8px; height: auto; font-size: 12px;" data-timesheet-id="' . $aRow['id'] . '" data-original-value="' . $timesheet_status . '">';
                $_data .= '<option value="pending" ' . ($timesheet_status == 'pending' ? 'selected' : '') . '>' . _l('pending') . '</option>';
                $_data .= '<option value="approved" ' . ($timesheet_status == 'approved' ? 'selected' : '') . '>' . _l('approved') . '</option>';
                $_data .= '<option value="rejected" ' . ($timesheet_status == 'rejected' ? 'selected' : '') . '>' . _l('rejected') . '</option>';
                $_data .= '</select>';
            } else {
                // User does not have permission - show label only
                $_data = '<span class="label ' . $status_class . '">' . $status_text . '</span>';
            }
        } elseif ($aColumns[$i] == 'start_time' || $aColumns[$i] == 'end_time') {
            if ($aColumns[$i] == 'end_time' && $_data == null) {
                $_data = '';
            } else {
                $_data = e(_dt($_data, true));
            }
        } else {
            // Time columns - adjust indices based on whether status column exists
            $time_h_index = $has_status_column ? 6 : 5;
            $time_d_index = $has_status_column ? 7 : 6;
            
            if ($i == $time_h_index) {
                if ($_data == null) {
                    $_data = e(seconds_to_time_format(time() - $aRow['start_time']));
                } else {
                    $_data = e(seconds_to_time_format($_data));
                }
            } elseif ($i == $time_d_index) {
                if ($_data == null) {
                    $_data = e(sec2qty(time() - $aRow['start_time']));
                } else {
                    $_data = e(sec2qty($_data));
                }
            }
        }
        $row[] = $_data;
    }
    $task_is_billed = $this->ci->tasks_model->is_task_billed($aRow['task_id']);

    $options = '<div class="tw-flex tw-items-center tw-space-x-2">';

    // Check edit permission with staff-level and project-level fallback
    if (can_user_timesheet_action('edit', $aRow['staff_id'], $project_id)) {
        if ($aRow['end_time'] !== null) {
            $attrs = [
                'class'                   => 'tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700',
                'onclick'                 => 'edit_timesheet(this,' . $aRow['id'] . ');return false',
                'data-start_time'         => e(_dt($aRow['start_time'], true)),
                'data-timesheet_task_id'  => $aRow['task_id'],
                'data-timesheet_staff_id' => $aRow['staff_id'],
                'data-note'               => $aRow['note'] ? htmlspecialchars(clear_textarea_breaks($aRow['note']), ENT_COMPAT) : '',
                'data-bill_type'          => isset($aRow['bill_type']) && $aRow['bill_type'] != '' ? $aRow['bill_type'] : 'billable',
                'data-status'             => isset($aRow['timesheet_status']) && $aRow['timesheet_status'] != '' ? $aRow['timesheet_status'] : 'pending',
            ];

            $task_status = isset($aRow['status']) ? $aRow['status'] : null;
            if ($task_status == Tasks_model::STATUS_COMPLETE || $user_removed_as_assignee == true) {
                $attrs['class'] .= ' tw-pointer-events-none tw-opacity-60';
            }

            $attrs['data-end_time'] = e(_dt($aRow['end_time'], true));

            $editAction = '<a href="#" ' . _attributes_to_string($attrs) . '>
                <i class="fa-regular fa-pen-to-square fa-lg"></i>
            </a>';

            if ($task_status == Tasks_model::STATUS_COMPLETE) {
                $editAction = '<span data-toggle="tooltip" data-title="' . _l('task_edit_delete_timesheet_notice', [($task_is_billed ? _l('task_billed') : _l('task_status_5')), _l('edit')]) . '">' . $editAction . '</span>';
            }
            $options .= $editAction;
        }
    }

    if (! $task_is_billed) {
        if ($aRow['end_time'] == null && ($aRow['staff_id'] == get_staff_user_id() || is_admin())) {
            $adminStop = $aRow['staff_id'] != get_staff_user_id() ? 1 : 0;

            $options .= '<a href="#"
                    class="tw-text-danger-500 hover:tw-text-danger-700 focus:tw-text-danger-700"
                    data-toggle="popover"
                    data-placement="bottom"
                    data-html="true"
                    data-trigger="manual"
                    data-title="' . _l('note') . "\"
                    data-content='" . render_textarea('timesheet_note') . '
                    <button type="button"
                    onclick="timer_action(this, ' . $aRow['task_id'] . ', ' . $aRow['id'] . ', ' . $adminStop . ');"
                    class="btn btn-primary btn-sm">' . _l('save')
                    . "</button>'
                    class=\"text-danger\"
                    onclick=\"return false;\">
                    <span data-toggle=\"tooltip\" data-title='" . _l('timesheet_stop_timer') . "'>
                          <i class=\"fa-regular fa-clock fa-lg\"></i>
                          </span>
                    </a>'";
        }
    }

    // Check delete permission with staff-level and project-level fallback
    if (can_user_timesheet_action('delete', $aRow['staff_id'], $project_id)) {
        $attrs = [
            'class' => 'tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700 _delete',
            'href'  => admin_url('tasks/delete_timesheet/' . $aRow['id']),
        ];

        if ($task_is_billed) {
            $attrs['class'] .= ' tw-pointer-events-none tw-opacity-60';
        }

        $deleteAction = ' <a ' . _attributes_to_string($attrs) . '>
            <i class="fa-regular fa-trash-can fa-lg"></i>
        </a>';

        if ($task_is_billed) {
            $icon_btn = '<span data-toggle="tooltip" data-title="' . _l('task_edit_delete_timesheet_notice', [
                _l('task_billed'),
                _l('delete'), ]) . '">' . $deleteAction . '</span>';
        }

        $options .= $deleteAction;
    }

    $options .= '</div>';

    $row[] = $options;

    $output['aaData'][] = $row;
}
