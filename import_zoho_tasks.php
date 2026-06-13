#!/usr/bin/env php
<?php

/**
 * One-time CLI importer: Zoho Task Export CSV -> tbltasks (+ related tables)
 *
 * Usage:
 *   php import_zoho_tasks.php [path/to/task_export.csv]
 *
 * Env:
 *   TASK_IMPORT_DEFAULT_PROJECT_NAME="SYCU - Grow"  Optional project name when CSV Project Name is empty
 *
 * Email: all outbound mail is blocked via before_email_template_send hook.
 */

define('TASK_IMPORT_CLI', true);
error_reporting(E_ALL);
ini_set('display_errors', '1');

$localMysqlSocket = '/Users/concatstringsolutions/Library/Application Support/Local/run/FuxtuM4Nr/mysql/mysqld.sock';
if (file_exists($localMysqlSocket)) {
    ini_set('mysqli.default_socket', $localMysqlSocket);
}

$environment = 'development';

$system_path        = __DIR__ . DIRECTORY_SEPARATOR . 'system';
$application_folder = __DIR__ . DIRECTORY_SEPARATOR . 'application';

if (realpath($system_path) !== false) {
    $system_path = realpath($system_path) . '/';
}

$system_path = rtrim($system_path, '/') . '/';

define('BASEPATH', str_replace('\\', '/', $system_path));
define('APPPATH', $application_folder . '/');
define('VIEWPATH', APPPATH . 'views' . DIRECTORY_SEPARATOR);
define('EXT', '.php');
define('ENVIRONMENT', $environment ?: 'development');
define('FCPATH', __DIR__ . '/');

if (file_exists(APPPATH . 'config/' . ENVIRONMENT . '/constants.php')) {
    require APPPATH . 'config/' . ENVIRONMENT . '/constants.php';
} else {
    require APPPATH . 'config/constants.php';
}

require BASEPATH . 'core/Common.php';

if (file_exists(APPPATH . 'vendor/autoload.php')) {
    require_once APPPATH . 'vendor/autoload.php';
}

require_once APPPATH . 'config/hooks.php';
require_once APPPATH . 'hooks/App_Autoloader.php';

(new App_Autoloader)->register();

if (extension_loaded('mbstring')) {
    define('MB_ENABLED', true);
    mb_substitute_character('none');
} else {
    define('MB_ENABLED', false);
}

define('ICONV_ENABLED', extension_loaded('iconv'));

$GLOBALS['CFG'] = &load_class('Config', 'core');
$GLOBALS['UNI'] = &load_class('Utf8', 'core');

if (file_exists(BASEPATH . 'core/Security.php')) {
    $GLOBALS['SEC'] = &load_class('Security', 'core');
}

load_class('Router', 'core');
load_class('Input', 'core');
load_class('Lang', 'core');

require BASEPATH . 'core/Controller.php';

function &get_instance()
{
    return CI_Controller::get_instance();
}

$class    = 'CI_Controller';
$instance = new $class();
$ci       = $instance;

require_once APPPATH . 'hooks/InitHook.php';

_app_init_load();

hooks()->add_filter('before_email_template_send', static function ($hook_data) {
    if (isset($hook_data['template'])) {
        $hook_data['template']->prevent_sending = true;
    }

    return $hook_data;
});

$ci->load->database();
$ci->load->model('staff_model');
$ci->load->model('tasks_model');
$ci->load->helper(['database', 'func', 'general', 'tags', 'tasks']);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function task_import_log($message)
{
    echo $message . PHP_EOL;
}

function task_import_parse_date($value)
{
    if ($value === null || trim($value) === '' || trim($value) === '-') {
        return null;
    }

    $value   = trim($value);
    $formats = [
        'd/m/Y h:i a',
        'd/m/Y g:i a',
        'd/m/Y H:i',
        'd/m/Y',
    ];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }
    }

    return null;
}

function task_import_parse_datetime($value)
{
    $date = task_import_parse_date($value);

    if ($date === null) {
        return null;
    }

    if (preg_match('/\d{1,2}:\d{2}/', (string) $value)) {
        $formats = ['d/m/Y h:i a', 'd/m/Y g:i a', 'd/m/Y H:i'];
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, trim($value));
            if ($dt instanceof DateTime) {
                return $dt->format('Y-m-d H:i:s');
            }
        }
    }

    return $date . ' 00:00:00';
}

function task_import_resolve_status($statusLabel)
{
    $normalized = strtolower(trim($statusLabel));

    $map = [
        'open'                => Tasks_model::STATUS_NOT_STARTED,
        'not started'         => Tasks_model::STATUS_NOT_STARTED,
        'awaiting for client' => Tasks_model::STATUS_AWAITING_FEEDBACK,
        'awaiting feedback'   => Tasks_model::STATUS_AWAITING_FEEDBACK,
        'to be tested'        => Tasks_model::STATUS_TESTING,
        'testing'             => Tasks_model::STATUS_TESTING,
        'in progress'         => Tasks_model::STATUS_IN_PROGRESS,
        'completed'           => Tasks_model::STATUS_COMPLETE,
        'complete'            => Tasks_model::STATUS_COMPLETE,
        'closed'              => Tasks_model::STATUS_CLOSED,
        'confirmed'           => Tasks_model::STATUS_CONFIRMED,
        'in verification'     => Tasks_model::STATUS_IN_VERIFICATION,
        'need work'           => Tasks_model::STATUS_NEED_WORK,
        'on hold'             => Tasks_model::STATUS_ON_HOLD,
        'lost'                => Tasks_model::STATUS_LOST,
        'qa verified'         => Tasks_model::STATUS_QA_VERIFIED,
        'ready for review'    => Tasks_model::STATUS_READY_FOR_REVIEW,
        'task detail'         => Tasks_model::STATUS_TASK_DETAIL,
        'task list'           => Tasks_model::STATUS_TASK_LIST,
        'win'                 => Tasks_model::STATUS_WIN,
    ];

    if ($normalized === '') {
        return Tasks_model::STATUS_NOT_STARTED;
    }

    return $map[$normalized] ?? Tasks_model::STATUS_NOT_STARTED;
}

function task_import_resolve_priority($priorityLabel)
{
    $normalized = strtolower(trim($priorityLabel));

    $map = [
        'none'   => (int) get_option('default_task_priority'),
        ''       => (int) get_option('default_task_priority'),
        'low'    => 1,
        'medium' => 2,
        'high'   => 3,
        'urgent' => 4,
    ];

    return $map[$normalized] ?? (int) get_option('default_task_priority');
}

function task_import_build_staff_lookup($ci)
{
    $lookup = [];
    $staff  = $ci->staff_model->get('', []);

    foreach ($staff as $member) {
        $fullName = strtolower(trim($member['firstname'] . ' ' . $member['lastname']));
        $lookup[$fullName] = (int) $member['staffid'];
    }

    return $lookup;
}

function task_import_resolve_staff_id($name, $staffLookup, $fallback = 0)
{
    $normalized = strtolower(trim($name));

    if ($normalized === '') {
        return $fallback;
    }

    if (isset($staffLookup[$normalized])) {
        return $staffLookup[$normalized];
    }

    foreach ($staffLookup as $staffName => $staffId) {
        if (strpos($staffName, $normalized) !== false || strpos($normalized, $staffName) !== false) {
            return $staffId;
        }
    }

    return $fallback;
}

function task_import_parse_owner_names($ownerValue)
{
    if ($ownerValue === null || trim($ownerValue) === '') {
        return [];
    }

    $names = array_map('trim', explode(',', $ownerValue));

    return array_values(array_filter($names, static function ($name) {
        return $name !== '';
    }));
}

function task_import_build_project_lookup($ci)
{
    $lookup = [];
    $rows   = $ci->db->select('id, name')->get(db_prefix() . 'projects')->result_array();

    foreach ($rows as $row) {
        $lookup[strtolower(trim($row['name']))] = (int) $row['id'];
    }

    return $lookup;
}

function task_import_resolve_project_id($projectName, $projectLookup, $defaultProjectName = '')
{
    $projectName = trim($projectName);

    if ($projectName === '') {
        $projectName = trim($defaultProjectName);
    }

    if ($projectName === '') {
        return 0;
    }

    $normalized = strtolower($projectName);

    if (isset($projectLookup[$normalized])) {
        return $projectLookup[$normalized];
    }

    foreach ($projectLookup as $name => $projectId) {
        if (strpos($name, $normalized) !== false || strpos($normalized, $name) !== false) {
            return $projectId;
        }
    }

    return 0;
}

function task_import_task_exists($ci, $taskName, $projectId)
{
    $ci->db->where('name', $taskName);

    if ($projectId > 0) {
        $ci->db->where('rel_type', 'project');
        $ci->db->where('rel_id', $projectId);
    }

    return total_rows(db_prefix() . 'tasks') > 0;
}

function task_import_parse_comments($jsonValue)
{
    if ($jsonValue === null || trim($jsonValue) === '') {
        return [];
    }

    $decoded = json_decode($jsonValue, true);

    if (!is_array($decoded)) {
        return [];
    }

    $comments = [];

    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }

        $content = trim($item['COMMENT'] ?? '');
        if ($content === '') {
            continue;
        }

        $comments[] = [
            'content'   => $content,
            'added_by'  => trim($item['ADDEDBY'] ?? ''),
            'dateadded' => trim($item['COMMENT_UPDATED_ON'] ?? ''),
        ];
    }

    return $comments;
}

function task_import_add_assignees($ci, $taskId, array $staffIds, $assignedFrom)
{
    $assignedFrom = (int) $assignedFrom;

    foreach (array_unique(array_filter(array_map('intval', $staffIds))) as $staffId) {
        if ($staffId <= 0) {
            continue;
        }

        if (total_rows(db_prefix() . 'task_assigned', [
            'taskid'  => $taskId,
            'staffid' => $staffId,
        ]) > 0) {
            continue;
        }

        $ci->db->insert(db_prefix() . 'task_assigned', [
            'taskid'        => $taskId,
            'staffid'       => $staffId,
            'assigned_from' => $assignedFrom > 0 ? $assignedFrom : $staffId,
        ]);
    }
}

function task_import_add_comments($ci, $taskId, array $comments, $staffLookup, $fallbackStaffId)
{
    foreach ($comments as $comment) {
        $staffId = task_import_resolve_staff_id($comment['added_by'], $staffLookup, $fallbackStaffId);
        $date    = task_import_parse_datetime($comment['dateadded']) ?: date('Y-m-d H:i:s');

        $ci->db->insert(db_prefix() . 'task_comments', [
            'taskid'     => $taskId,
            'content'    => $comment['content'],
            'staffid'    => $staffId,
            'contact_id' => 0,
            'dateadded'  => $date,
        ]);
    }
}

function task_import_insert_task($ci, array $data, array $assigneeIds, array $comments, array $tags, $staffLookup, $fallbackStaffId)
{
    $ci->db->insert(db_prefix() . 'tasks', $data);
    $taskId = (int) $ci->db->insert_id();

    if ($taskId <= 0) {
        return 0;
    }

    $addedFrom = (int) ($data['addedfrom'] ?? $fallbackStaffId);
    task_import_add_assignees($ci, $taskId, $assigneeIds, $addedFrom);
    task_import_add_comments($ci, $taskId, $comments, $staffLookup, $fallbackStaffId);

    if ($tags !== []) {
        handle_tags_save(implode(',', $tags), $taskId, 'task');
    }

    log_activity('Zoho Task Import [ID: ' . $taskId . ', ' . $data['name'] . ']');

    return $taskId;
}

function task_import_set_parent_tasks($ci, array $pendingParents, array $taskNameLookup)
{
    $resolved   = 0;
    $unresolved = [];

    foreach ($pendingParents as $item) {
        $key = strtolower($item['parentTaskName']) . '|' . $item['projectId'];

        if (isset($taskNameLookup[$key])) {
            $ci->db->where('id', $item['childTaskId']);
            $ci->db->update(db_prefix() . 'tasks', ['parent_task_id' => $taskNameLookup[$key]]);
            $resolved++;
        } else {
            $unresolved[] = $item;
        }
    }

    task_import_log("Parent tasks resolved: {$resolved}");

    foreach ($unresolved as $item) {
        task_import_log("WARN: Could not resolve parent \"{$item['parentTaskName']}\" for task id {$item['childTaskId']} (project {$item['projectId']})");
    }
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

$csvPath = $argv[1] ?? '/Users/concatstringsolutions/Downloads/task_export_2231293000004099088.csv';

if (!is_readable($csvPath)) {
    task_import_log('ERROR: CSV file not readable: ' . $csvPath);
    exit(1);
}

$staffLookup          = task_import_build_staff_lookup($ci);
$projectLookup        = task_import_build_project_lookup($ci);
$fallbackStaffId      = task_import_resolve_staff_id('Nirav Mehta', $staffLookup, 1);
$defaultProjectName   = getenv('TASK_IMPORT_DEFAULT_PROJECT_NAME') ?: 'SYCU - Grow';

task_import_log('Zoho task import started');
task_import_log('CSV: ' . $csvPath);
task_import_log('Default project name: ' . $defaultProjectName);
task_import_log('Email sending: BLOCKED');
task_import_log('Tables written: tbltasks, tbltask_assigned, tbltask_comments, tbltags, tbltaggables, tblactivity_log');
task_import_log(str_repeat('-', 60));

$handle = fopen($csvPath, 'r');

if ($handle === false) {
    task_import_log('ERROR: Unable to open CSV.');
    exit(1);
}

// Use empty escape to disable backslash escaping (RFC 4180 mode).
// Zoho CSV uses doubled-quote ("") escaping, but its comment fields
// contain \" sequences that confuse PHP's default backslash escape,
// shifting all subsequent column indices for that row.
$header = fgetcsv($handle, 0, ',', '"', '');

if ($header === false) {
    task_import_log('ERROR: CSV is empty.');
    exit(1);
}

$columnMap = [];
foreach ($header as $index => $label) {
    $columnMap[trim($label)] = $index;
}

$requiredColumns = ['Task Name', 'Owner', 'Custom Status', 'Project Name'];

foreach ($requiredColumns as $column) {
    if (!array_key_exists($column, $columnMap)) {
        task_import_log('ERROR: Missing required CSV column: ' . $column);
        exit(1);
    }
}

$created         = [];
$skipped         = [];
$failed          = [];
$rowNumber       = 1;
$taskNameLookup  = [];
$pendingParents  = [];

while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
    $rowNumber++;

    if (count(array_filter($row, static function ($value) {
        return trim((string) $value) !== '';
    })) === 0) {
        continue;
    }

    $taskName        = trim($row[$columnMap['Task Name']] ?? '');
    $ownerValue      = trim($row[$columnMap['Owner']] ?? '');
    $statusLabel     = trim($row[$columnMap['Custom Status']] ?? '');
    $tagsValue       = trim($row[$columnMap['Tags'] ?? ''] ?? '');
    $startDate       = trim($row[$columnMap['Start Date'] ?? ''] ?? '');
    $dueDate         = trim($row[$columnMap['Due Date'] ?? ''] ?? '');
    $priorityLabel   = trim($row[$columnMap['Priority'] ?? ''] ?? '');
    $createdBy       = trim($row[$columnMap['Created By'] ?? ''] ?? '');
    $commentsJson    = trim($row[$columnMap['Task Comment'] ?? ''] ?? '');
    $description     = trim($row[$columnMap['Task Description'] ?? ''] ?? '');
    $createdTime     = trim($row[$columnMap['Created Time'] ?? ''] ?? '');
    $projectName      = trim($row[$columnMap['Project Name']] ?? '');
    $parentTaskZohoId = trim($row[$columnMap['Parent Task ID'] ?? ''] ?? '');
    $parentTaskName   = trim($row[$columnMap['Parent Task Name'] ?? ''] ?? '');

    if ($taskName === '') {
        $failed[] = ['row' => $rowNumber, 'name' => $taskName, 'reason' => 'Missing task name'];
        task_import_log("FAIL row {$rowNumber}: missing task name");
        continue;
    }

    $projectId = task_import_resolve_project_id($projectName, $projectLookup, $defaultProjectName);

    if ($projectId <= 0) {
        $failed[] = ['row' => $rowNumber, 'name' => $taskName, 'reason' => 'Project not found: ' . ($projectName ?: $defaultProjectName)];
        task_import_log("FAIL row {$rowNumber}: {$taskName} project not found");
        continue;
    }

    // if (task_import_task_exists($ci, $taskName, $projectId)) {
    //     $skipped[] = ['row' => $rowNumber, 'name' => $taskName, 'project_id' => $projectId];
    //     task_import_log("SKIP row {$rowNumber}: {$taskName} already exists in project {$projectId}");
    //     continue;
    // }

    $addedFromId  = task_import_resolve_staff_id($createdBy, $staffLookup, $fallbackStaffId);
    $statusId     = task_import_resolve_status($statusLabel);
    $priorityId   = task_import_resolve_priority($priorityLabel);
    $startDateSql = task_import_parse_date($startDate) ?: task_import_parse_date($createdTime) ?: date('Y-m-d');
    $dueDateSql   = task_import_parse_date($dueDate);
    $dateAdded    = task_import_parse_datetime($createdTime) ?: date('Y-m-d H:i:s');

    $ownerNames  = task_import_parse_owner_names($ownerValue);
    $assigneeIds = [];

    foreach ($ownerNames as $ownerName) {
        $staffId = task_import_resolve_staff_id($ownerName, $staffLookup, 0);
        if ($staffId > 0) {
            $assigneeIds[] = $staffId;
        }
    }

    if ($assigneeIds === [] && $addedFromId > 0) {
        $assigneeIds[] = $addedFromId;
    }

    $insert = [
        'name'                  => $taskName,
        'description'           => $description,
        'priority'              => $priorityId,
        'dateadded'             => $dateAdded,
        'startdate'             => $startDateSql,
        'duedate'               => $dueDateSql,
        'addedfrom'             => $addedFromId,
        'status'                => $statusId,
        'rel_type'              => 'project',
        'rel_id'                => $projectId,
        'is_public'             => 0,
        'billable'              => 0,
        'billed'                => 0,
        'invoice_id'            => 0,
        'hourly_rate'           => 0,
        'milestone'             => 0,
        'kanban_order'          => 1,
        'visible_to_client'     => 0,
        'deadline_notified'     => 0,
        'is_added_from_contact' => 0,
        'recurring'             => 0,
        'repeat_every'          => null,
        'recurring_type'      => null,
        'custom_recurring'      => 0,
        'cycles'                => 0,
        'total_cycles'          => 0,
        'is_recurring_from'     => null,
        'last_recurring_date'   => null,
    ];

    if ($statusId === Tasks_model::STATUS_COMPLETE) {
        $insert['datefinished'] = task_import_parse_datetime($dueDate)
            ?: task_import_parse_datetime($createdTime)
            ?: date('Y-m-d H:i:s');
    }

    $comments = task_import_parse_comments($commentsJson);
    $tags     = array_filter(array_map('trim', explode(',', $tagsValue)));

    $taskId = task_import_insert_task(
        $ci,
        $insert,
        $assigneeIds,
        $comments,
        $tags,
        $staffLookup,
        $fallbackStaffId
    );

    if ($taskId <= 0) {
        $failed[] = ['row' => $rowNumber, 'name' => $taskName, 'reason' => 'Database insert failed'];
        task_import_log("FAIL row {$rowNumber}: {$taskName} insert failed");
        continue;
    }

    $created[] = [
        'id'         => $taskId,
        'row'        => $rowNumber,
        'name'       => $taskName,
        'project_id' => $projectId,
        'status'     => $statusId,
        'assignees'  => count($assigneeIds),
        'comments'   => count($comments),
    ];

    $taskNameLookup[strtolower($taskName) . '|' . $projectId] = $taskId;

    if ($parentTaskName !== '' && $parentTaskName !== '-') {
        $pendingParents[] = [
            'childTaskId'    => $taskId,
            'parentTaskName' => $parentTaskName,
            'projectId'      => $projectId,
        ];
    }

    task_import_log("OK   row {$rowNumber}: {$taskName} (task id {$taskId}, project {$projectId})");
}

fclose($handle);

if (count($pendingParents) > 0) {
    task_import_log('Resolving parent task relationships...');
    task_import_set_parent_tasks($ci, $pendingParents, $taskNameLookup);
}

$reportFile = FCPATH . 'uploads/task_import_report_' . date('Y-m-d_His') . '.csv';
$reportDir  = dirname($reportFile);

if (!is_dir($reportDir)) {
    mkdir($reportDir, 0755, true);
}

$reportHandle = fopen($reportFile, 'w');

if ($reportHandle !== false) {
    fputcsv($reportHandle, ['task_id', 'name', 'project_id', 'status', 'assignees', 'comments', 'csv_row']);

    foreach ($created as $item) {
        fputcsv($reportHandle, [
            $item['id'],
            $item['name'],
            $item['project_id'],
            $item['status'],
            $item['assignees'],
            $item['comments'],
            $item['row'],
        ]);
    }

    fclose($reportHandle);
}

task_import_log(str_repeat('-', 60));
task_import_log('Import complete');
task_import_log('Created: ' . count($created));
task_import_log('Skipped: ' . count($skipped));
task_import_log('Failed:  ' . count($failed));

if (count($created) > 0) {
    task_import_log('Report saved to: ' . $reportFile);
}

if (count($skipped) > 0) {
    task_import_log('Skipped: ' . implode(', ', array_column($skipped, 'name')));
}

if (count($failed) > 0) {
    foreach ($failed as $fail) {
        task_import_log('Failed row ' . $fail['row'] . ' (' . $fail['name'] . '): ' . $fail['reason']);
    }
    exit(1);
}

exit(0);
