#!/usr/bin/env php
<?php

/**
 * One-time CLI importer: Zoho Timesheet Export CSV -> tbltaskstimers (+ tbltasks for general logs)
 *
 * Usage:
 *   php import_zoho_timesheets.php [path/to/timesheet_export.csv]
 *
 * Env:
 *   TIMESHEET_IMPORT_DEFAULT_PROJECT_NAME="China Over Prints"
 *
 * Email: all outbound mail is blocked via before_email_template_send hook.
 */

define('TIMESHEET_IMPORT_CLI', true);
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
$ci->load->model('roles_model');
$ci->load->helper(['database', 'func', 'general']);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function timesheet_import_log($message)
{
    echo $message . PHP_EOL;
}

function timesheet_import_build_staff_lookup($ci)
{
    $lookup = [];
    $staff  = $ci->staff_model->get('', []);

    foreach ($staff as $member) {
        $fullName = strtolower(trim($member['firstname'] . ' ' . $member['lastname']));
        $lookup[$fullName] = (int) $member['staffid'];
    }

    return $lookup;
}

function timesheet_import_resolve_staff_id($name, $staffLookup, $fallback = 0)
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

function timesheet_import_build_project_lookup($ci)
{
    $lookup = [];
    $rows   = $ci->db->select('id, name')->get(db_prefix() . 'projects')->result_array();

    foreach ($rows as $row) {
        $lookup[strtolower(trim($row['name']))] = (int) $row['id'];
    }

    return $lookup;
}

function timesheet_import_resolve_project_id($projectName, $projectLookup, $defaultProjectName = '')
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

function timesheet_import_parse_metadata($csvPath)
{
    $metadata = [
        'project_name' => '',
        'project_id'   => '',
    ];

    $handle = fopen($csvPath, 'r');

    if ($handle === false) {
        return $metadata;
    }

    while (($row = fgetcsv($handle)) !== false) {
        $label = trim($row[0] ?? '');

        if ($label === 'Date') {
            break;
        }

        if (stripos($label, 'PROJECT NAME') === 0) {
            $metadata['project_name'] = trim($row[1] ?? '');
        }

        if (stripos($label, 'PROJECT ID') === 0) {
            $metadata['project_id'] = trim($row[1] ?? '');
        }
    }

    fclose($handle);

    return $metadata;
}

function timesheet_import_find_header_row($csvPath)
{
    $handle = fopen($csvPath, 'r');

    if ($handle === false) {
        return null;
    }

    $lineNumber = 0;

    while (($row = fgetcsv($handle)) !== false) {
        $lineNumber++;

        if (trim($row[0] ?? '') === 'Task/General/Feedback') {
            fclose($handle);

            return ['line' => $lineNumber, 'header' => $row];
        }
    }

    fclose($handle);

    return null;
}

function timesheet_import_parse_date($value)
{
    $value = trim((string) $value);

    if ($value === '' || stripos($value, 'total') !== false) {
        return null;
    }

    $dt = DateTime::createFromFormat('d/m/Y', $value);

    if ($dt instanceof DateTime) {
        return $dt->format('Y-m-d');
    }

    return null;
}

function timesheet_import_parse_duration_hours($value)
{
    $value = trim((string) $value);

    if ($value === '' || !preg_match('/^(\d{1,2}):(\d{2})$/', $value, $matches)) {
        return null;
    }

    return (int) $matches[1] + ((int) $matches[2] / 60);
}

function timesheet_import_resolve_bill_type($value)
{
    $normalized = strtolower(trim($value));

    if ($normalized === 'non billable' || $normalized === 'non_billable') {
        return 'non_billable';
    }

    return 'billable';
}

function timesheet_import_resolve_status($value)
{
    $normalized = strtolower(trim($value));

    if ($normalized === 'approved') {
        return 'approved';
    }

    if ($normalized === 'rejected') {
        return 'rejected';
    }

    return 'pending';
}

function timesheet_import_normalize_name($value)
{
    $value = (string) $value;
    $value = str_replace(["\xE2\x80\x93", "\xE2\x80\x94", '–', '—'], '-', $value);

    return strtolower(trim(preg_replace('/\s+/', ' ', $value)));
}

function timesheet_import_resolve_or_create_staff_id($ci, $name, &$staffLookup, $fallbackStaffId)
{
    $staffId = timesheet_import_resolve_staff_id($name, $staffLookup, 0);

    if ($staffId > 0) {
        return $staffId;
    }

    $parts = preg_split('/\s+/', trim($name), 2);
    $firstname = $parts[0] ?? '';
    $lastname  = $parts[1] ?? 'Import';

    if ($firstname === '') {
        return 0;
    }

    $slug      = slug_it($firstname . ' ' . $lastname);
    $email     = $slug . '@zoho-timesheet-import.local';
    $suffix    = 1;
    $baseEmail = $email;

    while (total_rows(db_prefix() . 'staff', ['email' => $email]) > 0) {
        $email = str_replace('@', $suffix . '@', $baseEmail);
        $suffix++;
    }

    $defaultRoleId = 0;
    $employeeRole  = $ci->db->select('roleid')->where('name', 'Employee')->get(db_prefix() . 'roles')->row();

    if ($employeeRole) {
        $defaultRoleId = (int) $employeeRole->roleid;
    } else {
        $defaultRoleId = (int) (get_option('default_staff_role') ?: 0);
    }

    $insert = [
        'firstname'       => $firstname,
        'lastname'        => $lastname,
        'email'           => $email,
        'password'        => app_hash_password(bin2hex(random_bytes(8))),
        'datecreated'     => date('Y-m-d H:i:s'),
        'active'          => 1,
        'admin'           => 0,
        'role'            => $defaultRoleId,
        'phonenumber'     => '',
        'facebook'        => '',
        'linkedin'        => '',
        'skype'           => '',
        'hourly_rate'     => 0,
        'is_not_staff'    => 0,
        'media_path_slug' => $slug,
    ];

    $ci->db->insert(db_prefix() . 'staff', $insert);
    $staffId = (int) $ci->db->insert_id();

    if ($staffId <= 0) {
        return 0;
    }

    $role = $defaultRoleId > 0 ? $ci->roles_model->get($defaultRoleId) : null;

    if ($role && !empty($role->permissions)) {
        $ci->staff_model->update_permissions($role->permissions, $staffId);
    } else {
        $ci->staff_model->update_permissions([], $staffId);
    }

    $fullName = strtolower(trim($firstname . ' ' . $lastname));
    $staffLookup[$fullName] = $staffId;

    timesheet_import_log("INFO created staff {$firstname} {$lastname} (staffid {$staffId})");

    return $staffId;
}

function timesheet_import_create_project_task($ci, $taskName, $projectId, $staffId, $logDate, $billType, $fallbackStaffId)
{
    $addedFrom = $fallbackStaffId > 0 ? $fallbackStaffId : $staffId;

    $insert = [
        'name'                  => trim($taskName),
        'description'           => '',
        'priority'              => 2,
        'dateadded'             => date('Y-m-d H:i:s'),
        'startdate'             => $logDate,
        'duedate'               => $logDate,
        'addedfrom'             => $addedFrom,
        'status'                => 5,
        'rel_type'              => 'project',
        'rel_id'                => $projectId,
        'is_public'             => 0,
        'billable'              => ($billType === 'billable') ? 1 : 0,
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
        'recurring_type'        => null,
        'custom_recurring'      => 0,
        'cycles'                => 0,
        'total_cycles'          => 0,
        'is_recurring_from'     => null,
        'last_recurring_date'   => null,
        'datefinished'          => $logDate . ' 23:59:59',
    ];

    $ci->db->insert(db_prefix() . 'tasks', $insert);
    $taskId = (int) $ci->db->insert_id();

    if ($taskId <= 0) {
        return 0;
    }

    if (total_rows(db_prefix() . 'task_assigned', ['taskid' => $taskId, 'staffid' => $staffId]) === 0) {
        $ci->db->insert(db_prefix() . 'task_assigned', [
            'taskid'        => $taskId,
            'staffid'       => $staffId,
            'assigned_from' => $addedFrom,
        ]);
    }

    timesheet_import_log("INFO created task {$taskName} (task id {$taskId})");

    return $taskId;
}

function timesheet_import_find_task_id($ci, $taskName, $projectId)
{
    $normalized = timesheet_import_normalize_name($taskName);

    if ($normalized === '') {
        return 0;
    }

    $ci->db->select('id, name');
    $ci->db->where('rel_type', 'project');
    $ci->db->where('rel_id', $projectId);
    $tasks = $ci->db->get(db_prefix() . 'tasks')->result_array();

    foreach ($tasks as $task) {
        if (timesheet_import_normalize_name($task['name']) === $normalized) {
            return (int) $task['id'];
        }
    }

    foreach ($tasks as $task) {
        $dbName = timesheet_import_normalize_name($task['name']);
        if (strpos($dbName, $normalized) !== false || strpos($normalized, $dbName) !== false) {
            return (int) $task['id'];
        }
    }

    return 0;
}

function timesheet_import_get_hourly_rate($ci, $staffId)
{
    $ci->db->select('hourly_rate');
    $ci->db->where('staffid', $staffId);
    $staff = $ci->db->get(db_prefix() . 'staff')->row();

    return $staff ? (float) ($staff->hourly_rate ?: 0) : 0.0;
}

function timesheet_import_build_note($notes)
{
    $notes = trim((string) $notes);

    if ($notes === '' || $notes === '-') {
        return null;
    }

    return nl2br($notes);
}

function timesheet_import_timer_exists($ci, $staffId, $taskId, $startTime, $endTime)
{
    return total_rows(db_prefix() . 'taskstimers', [
        'staff_id'   => $staffId,
        'task_id'    => $taskId,
        'start_time' => $startTime,
        'end_time'   => $endTime,
    ]) > 0;
}

function timesheet_import_insert_timer($ci, array $data)
{
    $columns = $ci->db->list_fields(db_prefix() . 'taskstimers');

    $insert = [
        'start_time'  => $data['start_time'],
        'end_time'    => $data['end_time'],
        'staff_id'    => $data['staff_id'],
        'task_id'     => $data['task_id'],
        'hourly_rate' => $data['hourly_rate'],
        'note'        => $data['note'],
    ];

    if (in_array('bill_type', $columns, true)) {
        $insert['bill_type'] = $data['bill_type'];
    }

    if (in_array('status', $columns, true)) {
        $insert['status'] = $data['status'];
    }

    if (in_array('task_name', $columns, true) && !empty($data['task_name'])) {
        $insert['task_name'] = $data['task_name'];
    }

    if (in_array('project_id', $columns, true) && isset($data['project_id'])) {
        $insert['project_id'] = (int) $data['project_id'];
    }

    $ci->db->insert(db_prefix() . 'taskstimers', $insert);

    return (int) $ci->db->insert_id();
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

$csvPath = $argv[1] ?? '/Users/concatstringsolutions/Downloads/timesheet_2231293000004099102.csv';

if (!is_readable($csvPath)) {
    timesheet_import_log('ERROR: CSV file not readable: ' . $csvPath);
    exit(1);
}

$metadata             = timesheet_import_parse_metadata($csvPath);
$headerInfo           = timesheet_import_find_header_row($csvPath);
$staffLookup          = timesheet_import_build_staff_lookup($ci);
$projectLookup        = timesheet_import_build_project_lookup($ci);
$fallbackStaffId      = timesheet_import_resolve_staff_id('Jaymin Patel', $staffLookup, 1);
$defaultProjectName   = getenv('TIMESHEET_IMPORT_DEFAULT_PROJECT_NAME') ?: ($metadata['project_name'] ?: 'China Over Prints');
$projectId            = timesheet_import_resolve_project_id($metadata['project_name'], $projectLookup, $defaultProjectName);
$timerColumns         = $ci->db->list_fields(db_prefix() . 'taskstimers');

if ($headerInfo === null) {
    timesheet_import_log('ERROR: Could not find CSV header row starting with Date.');
    exit(1);
}

if ($projectId <= 0) {
    timesheet_import_log('ERROR: Project not found: ' . $defaultProjectName);
    exit(1);
}

timesheet_import_log('Zoho timesheet import started');
timesheet_import_log('CSV: ' . $csvPath);
timesheet_import_log('Project: ' . $defaultProjectName . ' (id ' . $projectId . ')');
timesheet_import_log('Email sending: BLOCKED');
timesheet_import_log('Tables written: tbltaskstimers, tbltasks (missing task rows only), tbltask_assigned, tblstaff (missing users only)');
timesheet_import_log('');
timesheet_import_log('Column mapping:');
timesheet_import_log('  CSV Date                  -> tbltaskstimers.start_time / end_time (9:00 AM start on log date + Daily Log duration)');
timesheet_import_log('  CSV Task/General/Feedback -> tbltaskstimers.task_name (type=general) OR matched tbltasks.id (type=task)');
timesheet_import_log('  CSV Daily Log             -> duration used to calculate tbltaskstimers.end_time');
timesheet_import_log('  CSV User                  -> tbltaskstimers.staff_id (matched via tblstaff firstname + lastname)');
timesheet_import_log('  CSV Billing Type          -> tbltaskstimers.bill_type (Billable / Non Billable)');
timesheet_import_log('  CSV Approval Status       -> tbltaskstimers.status (Approved / Pending / Rejected)');
timesheet_import_log('  CSV Notes                 -> tbltaskstimers.note');
timesheet_import_log('  CSV Type                  -> import logic: task = task timer, general = timer with task_id=0 + task_name + project_id');
timesheet_import_log('  CSV Approval By           -> not stored (informational only)');
timesheet_import_log('  CSV metadata PROJECT NAME -> tblprojects.id lookup');
timesheet_import_log(str_repeat('-', 60));

$columnMap = [];
foreach ($headerInfo['header'] as $index => $label) {
    $columnMap[trim($label)] = $index;
}

$requiredColumns = ['Date', 'Task/General/Feedback', 'Daily Log', 'User', 'Billing Type', 'Type'];

foreach ($requiredColumns as $column) {
    if (!array_key_exists($column, $columnMap)) {
        timesheet_import_log('ERROR: Missing required CSV column: ' . $column);
        exit(1);
    }
}

$handle = fopen($csvPath, 'r');

if ($handle === false) {
    timesheet_import_log('ERROR: Unable to open CSV.');
    exit(1);
}

for ($i = 1; $i < $headerInfo['line']; $i++) {
    fgetcsv($handle);
}

$header = fgetcsv($handle);

$created   = [];
$skipped   = [];
$failed    = [];
$rowNumber = $headerInfo['line'];

while (($row = fgetcsv($handle)) !== false) {
    $rowNumber++;

    if (count(array_filter($row, static function ($value) {
        return trim((string) $value) !== '';
    })) === 0) {
        continue;
    }

    $dateValue       = trim($row[$columnMap['Date']] ?? '');
    $taskName        = trim($row[$columnMap['Task/General/Feedback']] ?? '');
    $dailyLog        = trim($row[$columnMap['Daily Log']] ?? '');
    $userName        = trim($row[$columnMap['User']] ?? '');
    $billingType     = trim($row[$columnMap['Billing Type']] ?? '');
    $approvalStatus  = trim($row[$columnMap['Approval Status'] ?? ''] ?? '');
    $notes           = trim($row[$columnMap['Notes'] ?? ''] ?? '');
    $typeValue       = strtolower(trim($row[$columnMap['Type']] ?? ''));
    $approvalBy      = trim($row[$columnMap['Approval By'] ?? ''] ?? '');
    $rowProjectName  = trim($row[$columnMap['Project Name'] ?? ''] ?? '');
    $rowProjectId    = $rowProjectName !== ''
        ? timesheet_import_resolve_project_id($rowProjectName, $projectLookup, '')
        : 0;
    if ($rowProjectId <= 0) {
        $rowProjectId = $projectId;
    }

    if (stripos($dateValue, 'total log hours') !== false || stripos($taskName, 'total log hours') !== false) {
        continue;
    }

    $logDate = timesheet_import_parse_date($dateValue);

    if ($logDate === null) {
        continue;
    }

    if ($taskName === '') {
        $failed[] = ['row' => $rowNumber, 'task' => $taskName, 'reason' => 'Missing task/general name'];
        timesheet_import_log("FAIL row {$rowNumber}: missing task/general name");
        continue;
    }

    $durationHours = timesheet_import_parse_duration_hours($dailyLog);

    if ($durationHours === null || $durationHours <= 0) {
        $failed[] = ['row' => $rowNumber, 'task' => $taskName, 'reason' => 'Invalid daily log duration: ' . $dailyLog];
        timesheet_import_log("FAIL row {$rowNumber}: {$taskName} invalid duration ({$dailyLog})");
        continue;
    }

    $staffId = timesheet_import_resolve_or_create_staff_id($ci, $userName, $staffLookup, $fallbackStaffId);

    if ($staffId <= 0) {
        $failed[] = ['row' => $rowNumber, 'task' => $taskName, 'reason' => 'Staff not found: ' . $userName];
        timesheet_import_log("FAIL row {$rowNumber}: {$taskName} staff not found ({$userName})");
        continue;
    }

    $billType = timesheet_import_resolve_bill_type($billingType);
    $status   = timesheet_import_resolve_status($approvalStatus);
    $isGeneral = ($typeValue === 'general');

    if ($isGeneral) {
        $taskId = 0;
    } else {
        $taskId = timesheet_import_find_task_id($ci, $taskName, $rowProjectId);

        if ($taskId <= 0) {
            $taskId = timesheet_import_create_project_task(
                $ci,
                $taskName,
                $rowProjectId,
                $staffId,
                $logDate,
                $billType,
                $fallbackStaffId
            );

            if ($taskId <= 0) {
                $failed[] = ['row' => $rowNumber, 'task' => $taskName, 'reason' => 'Task not found and auto-create failed'];
                timesheet_import_log("FAIL row {$rowNumber}: {$taskName} task create failed");
                continue;
            }
        }
    }

    $dateParts = explode('-', $logDate);
    $startTime = mktime(9, 0, 0, (int) $dateParts[1], (int) $dateParts[2], (int) $dateParts[0]);
    $endTime   = $startTime + (int) round($durationHours * 3600);

    // if (timesheet_import_timer_exists($ci, $staffId, $taskId, $startTime, $endTime)) {
    //     $skipped[] = ['row' => $rowNumber, 'task' => $taskName, 'reason' => 'Duplicate timer'];
    //     timesheet_import_log("SKIP row {$rowNumber}: {$taskName} duplicate timer");
    //     continue;
    // }

    $timerId = timesheet_import_insert_timer($ci, [
        'start_time'  => $startTime,
        'end_time'    => $endTime,
        'staff_id'    => $staffId,
        'task_id'     => $taskId,
        'task_name'   => $isGeneral ? trim($taskName) : null,
        'project_id'  => $rowProjectId,
        'hourly_rate' => timesheet_import_get_hourly_rate($ci, $staffId),
        'note'        => timesheet_import_build_note($notes),
        'bill_type'   => $billType,
        'status'      => $status,
    ]);

    if ($timerId <= 0) {
        $failed[] = ['row' => $rowNumber, 'task' => $taskName, 'reason' => 'Timer insert failed'];
        timesheet_import_log("FAIL row {$rowNumber}: {$taskName} timer insert failed");
        continue;
    }

    $created[] = [
        'timer_id'     => $timerId,
        'task_id'      => $taskId,
        'row'          => $rowNumber,
        'task'         => $taskName,
        'type'         => $typeValue,
        'staff'        => $userName,
        'date'         => $logDate,
        'duration'     => $dailyLog,
        'approval_by'  => $approvalBy,
    ];

    timesheet_import_log("OK   row {$rowNumber}: {$taskName} (timer {$timerId}, task {$taskId}, {$typeValue})");
}

fclose($handle);

$reportFile = FCPATH . 'uploads/timesheet_import_report_' . date('Y-m-d_His') . '.csv';
$reportDir  = dirname($reportFile);

if (!is_dir($reportDir)) {
    mkdir($reportDir, 0755, true);
}

$reportHandle = fopen($reportFile, 'w');

if ($reportHandle !== false) {
    fputcsv($reportHandle, ['timer_id', 'task_id', 'task_name', 'type', 'staff', 'date', 'duration', 'csv_row']);

    foreach ($created as $item) {
        fputcsv($reportHandle, [
            $item['timer_id'],
            $item['task_id'],
            $item['task'],
            $item['type'],
            $item['staff'],
            $item['date'],
            $item['duration'],
            $item['row'],
        ]);
    }

    fclose($reportHandle);
}

timesheet_import_log(str_repeat('-', 60));
timesheet_import_log('Import complete');
timesheet_import_log('Created: ' . count($created));
timesheet_import_log('Skipped: ' . count($skipped));
timesheet_import_log('Failed:  ' . count($failed));

if (count($created) > 0) {
    timesheet_import_log('Report saved to: ' . $reportFile);
}

if (count($failed) > 0) {
    foreach ($failed as $fail) {
        timesheet_import_log('Failed row ' . $fail['row'] . ' (' . $fail['task'] . '): ' . $fail['reason']);
    }
    exit(1);
}

exit(0);
