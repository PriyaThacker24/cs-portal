#!/usr/bin/env php
<?php

/**
 * One-time CLI importer: Zoho Project Export CSV -> tblprojects (+ related tables)
 *
 * Usage:
 *   php import_zoho_projects.php [path/to/project_export.csv]
 *
 * Env:
 *   PROJECT_IMPORT_DEFAULT_CLIENT_ID=123  Optional existing tblclients.userid
 */

define('PROJECT_IMPORT_CLI', true);
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
$ci->load->model('projects_model');
$ci->load->model('staff_model');
$ci->load->helper(['database', 'func', 'general', 'tags']);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function project_import_log($message)
{
    echo $message . PHP_EOL;
}

function project_import_parse_date($value)
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

function project_import_parse_datetime($value)
{
    $date = project_import_parse_date($value);

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

function project_import_resolve_status($statusLabel)
{
    $normalized = strtolower(trim($statusLabel));

    $map = [
        'planning'    => 1,
        'not started' => 1,
        'active'      => 6,
        'in progress' => 2,
        'on track'    => 9,
        'approved'    => 7,
        'in testing'  => 8,
        'testing'     => 8,
        'on hold'     => 3,
        'hold'        => 3,
        'completed'   => 4,
        'invoiced'    => 5,
    ];

    return $map[$normalized] ?? 2;
}

function project_import_build_staff_lookup($ci)
{
    $lookup = [];
    $staff  = $ci->staff_model->get('', []);

    foreach ($staff as $member) {
        $fullName = strtolower(trim($member['firstname'] . ' ' . $member['lastname']));
        $lookup[$fullName] = (int) $member['staffid'];
    }

    return $lookup;
}

function project_import_resolve_staff_id($name, $staffLookup, $fallback = 0)
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

function project_import_get_or_create_default_client($ci)
{
    $envClientId = getenv('PROJECT_IMPORT_DEFAULT_CLIENT_ID');
    if ($envClientId !== false && is_numeric($envClientId) && (int) $envClientId > 0) {
        return (int) $envClientId;
    }

    $companyName = 'ConcatString Internal';
    $existing    = $ci->db->select('userid')
        ->where('company', $companyName)
        ->get(db_prefix() . 'clients')
        ->row();

    if ($existing) {
        return (int) $existing->userid;
    }

    $ci->db->insert(db_prefix() . 'clients', [
        'company'     => $companyName,
        'datecreated' => date('Y-m-d H:i:s'),
        'addedfrom'   => 0,
        'active'      => 1,
    ]);

    return (int) $ci->db->insert_id();
}

function project_import_resolve_client_id($ci, $customerName, $defaultClientId)
{
    $customerName = trim($customerName);

    if ($customerName === '' || $customerName === '-') {
        return $defaultClientId;
    }

    $client = $ci->db->select('userid')
        ->like('company', $customerName)
        ->get(db_prefix() . 'clients')
        ->row();

    if ($client) {
        return (int) $client->userid;
    }

    return $defaultClientId;
}

function project_import_name_exists($ci, $name)
{
    return total_rows(db_prefix() . 'projects', ['name' => $name]) > 0;
}

function project_import_zoho_id_exists($ci, $zohoProjectId)
{
    if ($zohoProjectId === '') {
        return false;
    }

    $ci->db->select(db_prefix() . 'taggables.rel_id');
    $ci->db->from(db_prefix() . 'taggables');
    $ci->db->join(db_prefix() . 'tags', db_prefix() . 'tags.id = ' . db_prefix() . 'taggables.tag_id');
    $ci->db->where(db_prefix() . 'taggables.rel_type', 'project');
    $ci->db->where(db_prefix() . 'tags.name', 'zoho:' . $zohoProjectId);
    $ci->db->limit(1);

    return $ci->db->get()->num_rows() > 0;
}

function project_import_add_members($ci, $projectId, array $memberIds)
{
    foreach (array_unique(array_filter(array_map('intval', $memberIds))) as $staffId) {
        if ($staffId <= 0) {
            continue;
        }

        if (total_rows(db_prefix() . 'project_members', [
            'project_id' => $projectId,
            'staff_id'   => $staffId,
        ]) > 0) {
            continue;
        }

        $ci->db->insert(db_prefix() . 'project_members', [
            'project_id' => $projectId,
            'staff_id'   => $staffId,
        ]);
    }
}

function project_import_insert_settings($ci, $projectId)
{
    $settings     = $ci->projects_model->get_settings();
    $lastSettings = $ci->projects_model->get_last_project_settings();
    $lastByName   = [];

    foreach ($lastSettings as $setting) {
        $lastByName[$setting['name']] = $setting['value'];
    }

    foreach ($settings as $setting) {
        $ci->db->insert(db_prefix() . 'project_settings', [
            'project_id' => $projectId,
            'name'       => $setting,
            'value'      => $lastByName[$setting] ?? 0,
        ]);
    }
}

function project_import_insert_project($ci, array $data, array $memberIds, $zohoProjectId, $extraTags = [])
{
    $ci->db->insert(db_prefix() . 'projects', $data);
    $projectId = (int) $ci->db->insert_id();

    if ($projectId <= 0) {
        return 0;
    }

    $memberIds = array_values(array_unique(array_filter(array_map('intval', $memberIds))));

    project_import_add_members($ci, $projectId, $memberIds);

    project_import_insert_settings($ci, $projectId);

    $salesPersonId = $memberIds[0] ?? (int) $data['addedfrom'];
    $formatTag     = $data['name'] . ' – ' . get_company_name($data['clientid']) . ' – ' . get_staff_full_name($salesPersonId);
    $tags          = array_filter(array_merge([$formatTag], $extraTags));

    if ($zohoProjectId !== '') {
        $tags[] = 'zoho:' . $zohoProjectId;
    }

    handle_tags_save(implode(',', $tags), $projectId, 'project');

    $ci->projects_model->log_activity($projectId, 'project_activity_created');
    log_activity('Zoho Project Import [ID: ' . $projectId . ', ' . $data['name'] . ']');

    return $projectId;
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

$csvPath = $argv[1] ?? '/Users/concatstringsolutions/Downloads/project_export_2231293000004099085.csv';

if (!is_readable($csvPath)) {
    project_import_log('ERROR: CSV file not readable: ' . $csvPath);
    exit(1);
}

$staffLookup      = project_import_build_staff_lookup($ci);
$defaultClientId  = project_import_get_or_create_default_client($ci);
$fallbackStaffId  = project_import_resolve_staff_id('Nirav Mehta', $staffLookup, 1);

project_import_log('Zoho project import started');
project_import_log('CSV: ' . $csvPath);
project_import_log('Default client ID: ' . $defaultClientId);
project_import_log('Tables written: tblprojects, tblproject_members, tblproject_settings, tbltags, tbltaggables, tblproject_activity, tblactivity_log');
if ($defaultClientId && total_rows(db_prefix() . 'clients', ['userid' => $defaultClientId]) > 0) {
    $clientRow = $ci->db->select('company')->where('userid', $defaultClientId)->get(db_prefix() . 'clients')->row();
    project_import_log('Default client: ' . ($clientRow->company ?? 'unknown'));
}
project_import_log(str_repeat('-', 60));

$handle = fopen($csvPath, 'r');

if ($handle === false) {
    project_import_log('ERROR: Unable to open CSV.');
    exit(1);
}

$header = fgetcsv($handle);

if ($header === false) {
    project_import_log('ERROR: CSV is empty.');
    exit(1);
}

$columnMap = [];
foreach ($header as $index => $label) {
    $columnMap[trim($label)] = $index;
}

$requiredColumns = ['PROJECT ID', 'PROJECT NAME', 'OWNER', 'STATUS'];

foreach ($requiredColumns as $column) {
    if (!array_key_exists($column, $columnMap)) {
        project_import_log('ERROR: Missing required CSV column: ' . $column);
        exit(1);
    }
}

$created   = [];
$skipped   = [];
$failed    = [];
$rowNumber = 1;

while (($row = fgetcsv($handle)) !== false) {
    $rowNumber++;

    if (count(array_filter($row, static function ($value) {
        return trim((string) $value) !== '';
    })) === 0) {
        continue;
    }

    $zohoProjectId  = trim($row[$columnMap['PROJECT ID']] ?? '');
    $projectName    = trim($row[$columnMap['PROJECT NAME']] ?? '');
    $primaryCustomer = trim($row[$columnMap['PRIMARY CUSTOMER'] ?? ''] ?? '');
    $ownerName      = trim($row[$columnMap['OWNER']] ?? '');
    $statusLabel    = trim($row[$columnMap['STATUS']] ?? 'Active');
    $startDate      = trim($row[$columnMap['START DATE'] ?? ''] ?? '');
    $endDate        = trim($row[$columnMap['END DATE'] ?? ''] ?? '');
    $createdTime    = trim($row[$columnMap['CREATED TIME'] ?? ''] ?? '');
    $createdBy      = trim($row[$columnMap['CREATED BY'] ?? ''] ?? '');
    $lastModified   = trim($row[$columnMap['LAST MODIFIED TIME'] ?? ''] ?? '');

    if ($projectName === '') {
        $failed[] = ['row' => $rowNumber, 'name' => $projectName, 'reason' => 'Missing project name'];
        project_import_log("FAIL row {$rowNumber}: missing project name");
        continue;
    }

    if (project_import_name_exists($ci, $projectName) || project_import_zoho_id_exists($ci, $zohoProjectId)) {
        $skipped[] = ['row' => $rowNumber, 'name' => $projectName, 'zoho_id' => $zohoProjectId];
        project_import_log("SKIP row {$rowNumber}: {$projectName} already exists");
        continue;
    }

    $clientId    = project_import_resolve_client_id($ci, $primaryCustomer, $defaultClientId);
    $managerId   = project_import_resolve_staff_id($ownerName, $staffLookup, $fallbackStaffId);
    $addedFrom   = project_import_resolve_staff_id($createdBy, $staffLookup, $managerId ?: $fallbackStaffId);
    $statusId    = project_import_resolve_status($statusLabel);
    $startDateSql = project_import_parse_date($startDate) ?: project_import_parse_date($createdTime) ?: date('Y-m-d');
    $deadlineSql  = project_import_parse_date($endDate);
    $createdDate  = project_import_parse_date($createdTime) ?: date('Y-m-d');

    $insert = [
        'name'                 => $projectName,
        'clientid'             => $clientId,
        'billing_type'         => 2,
        'start_date'           => $startDateSql,
        'project_created'      => $createdDate,
        'addedfrom'            => $addedFrom,
        'status'               => $statusId,
        'progress'             => 0,
        'progress_from_tasks'  => 1,
        'project_cost'         => null,
        'project_rate_per_hour'=> 0,
        'estimated_hours'      => null,
        'description'          => '',
    ];

    if ($deadlineSql !== null) {
        $insert['deadline'] = $deadlineSql;
    }

    if ($statusId === 4) {
        $insert['date_finished'] = project_import_parse_datetime($lastModified)
            ?: project_import_parse_datetime($createdTime)
            ?: date('Y-m-d H:i:s');
    }

    if ($ci->db->field_exists('manager_id', db_prefix() . 'projects')) {
        $insert['manager_id'] = $managerId > 0 ? $managerId : null;
    }

    if ($ci->db->field_exists('owner_id', db_prefix() . 'projects')) {
        $insert['owner_id'] = null;
    }

    $memberIds = array_filter([$managerId, $addedFrom]);
    $projectId = project_import_insert_project($ci, $insert, $memberIds, $zohoProjectId);

    if ($projectId <= 0) {
        $failed[] = ['row' => $rowNumber, 'name' => $projectName, 'reason' => 'Database insert failed'];
        project_import_log("FAIL row {$rowNumber}: {$projectName} insert failed");
        continue;
    }

    $created[] = [
        'id'         => $projectId,
        'row'        => $rowNumber,
        'zoho_id'    => $zohoProjectId,
        'name'       => $projectName,
        'clientid'   => $clientId,
        'manager_id' => $managerId,
        'status'     => $statusId,
    ];

    project_import_log("OK   row {$rowNumber}: {$zohoProjectId} {$projectName} (project id {$projectId})");
}

fclose($handle);

$reportFile = FCPATH . 'uploads/project_import_report_' . date('Y-m-d_His') . '.csv';
$reportDir  = dirname($reportFile);

if (!is_dir($reportDir)) {
    mkdir($reportDir, 0755, true);
}

$reportHandle = fopen($reportFile, 'w');

if ($reportHandle !== false) {
    fputcsv($reportHandle, ['project_id', 'zoho_project_id', 'name', 'clientid', 'manager_id', 'status', 'csv_row']);

    foreach ($created as $item) {
        fputcsv($reportHandle, [
            $item['id'],
            $item['zoho_id'],
            $item['name'],
            $item['clientid'],
            $item['manager_id'],
            $item['status'],
            $item['row'],
        ]);
    }

    fclose($reportHandle);
}

project_import_log(str_repeat('-', 60));
project_import_log('Import complete');
project_import_log('Created: ' . count($created));
project_import_log('Skipped: ' . count($skipped));
project_import_log('Failed:  ' . count($failed));

if (count($created) > 0) {
    project_import_log('Report saved to: ' . $reportFile);
}

if (count($skipped) > 0) {
    project_import_log('Skipped: ' . implode(', ', array_column($skipped, 'name')));
}

if (count($failed) > 0) {
    foreach ($failed as $fail) {
        project_import_log('Failed row ' . $fail['row'] . ' (' . $fail['name'] . '): ' . $fail['reason']);
    }
    exit(1);
}

exit(0);
