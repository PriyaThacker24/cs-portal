<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Project Task Sync
Description: When a project's status is set to Completed, automatically marks all of its tasks as Completed (Closed tasks are left untouched).
Version: 1.1.0
Requires at least: 2.3.*
*/

define('PROJECT_TASK_SYNC_MODULE_NAME', 'project_task_sync');

/**
 * Project status id that represents "Completed" (project_status_4).
 */
define('PROJECT_TASK_SYNC_PROJECT_COMPLETED_STATUS', 4);

/**
 * Task status id that represents "Completed" (task_status_5 / Tasks_model::STATUS_COMPLETE).
 */
define('PROJECT_TASK_SYNC_TASK_COMPLETED_STATUS', 5);

/**
 * Task status id that represents "Closed" (task_status_6 / Tasks_model::STATUS_CLOSED).
 * Closed tasks are intentionally excluded from auto-completion.
 */
define('PROJECT_TASK_SYNC_TASK_CLOSED_STATUS', 6);

/**
 * Register language files so the activity log key resolves via _l().
 */
register_language_files(PROJECT_TASK_SYNC_MODULE_NAME, [PROJECT_TASK_SYNC_MODULE_NAME]);

/**
 * Fires whenever a project's status changes (both the edit form and the
 * inline status dropdown trigger this hook in Projects_model).
 */
hooks()->add_action('project_status_changed', 'project_task_sync_complete_tasks');

/**
 * Mark every task of a project as Completed when the project itself is
 * marked as Completed.
 *
 * Uses a single bulk UPDATE (instead of Tasks_model::mark_as() per task) so
 * completing a project with many tasks stays fast and does not fire a
 * notification email for every task.
 *
 * @param array $data ['status' => int, 'project_id' => int]
 */
function project_task_sync_complete_tasks($data)
{
    if (!isset($data['status'], $data['project_id'])) {
        return;
    }

    if ((int) $data['status'] !== PROJECT_TASK_SYNC_PROJECT_COMPLETED_STATUS) {
        return;
    }

    $CI         = &get_instance();
    $project_id = (int) $data['project_id'];

    // Project tasks that are neither already Completed nor Closed.
    // Closed tasks are intentionally left as-is.
    $tasks = $CI->db
        ->select('id')
        ->where('rel_type', 'project')
        ->where('rel_id', $project_id)
        ->where_not_in('status', [
            PROJECT_TASK_SYNC_TASK_COMPLETED_STATUS,
            PROJECT_TASK_SYNC_TASK_CLOSED_STATUS,
        ])
        ->get(db_prefix() . 'tasks')
        ->result_array();

    if (empty($tasks)) {
        return;
    }

    $task_ids = array_map(function ($t) {
        return (int) $t['id'];
    }, $tasks);

    // Stop any running timers for the affected tasks.
    $CI->db->where('end_time IS NULL', null, false);
    $CI->db->where_in('task_id', $task_ids);
    $CI->db->update(db_prefix() . 'taskstimers', ['end_time' => time()]);

    // Bulk-complete the tasks in a single query.
    $CI->db->where_in('id', $task_ids);
    $CI->db->update(db_prefix() . 'tasks', [
        'status'       => PROJECT_TASK_SYNC_TASK_COMPLETED_STATUS,
        'datefinished' => date('Y-m-d H:i:s'),
    ]);

    // One project activity log entry instead of a per-task email/notification.
    $CI->load->model('projects_model');
    $CI->projects_model->log_activity(
        $project_id,
        'project_task_sync_activity_tasks_completed',
        count($task_ids) . ' task(s)',
        0
    );
}
