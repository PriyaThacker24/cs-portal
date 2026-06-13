#!/usr/bin/env php
<?php

/**
 * One-time CLI importer: Zoho Portal Users CSV -> tblstaff
 *
 * Usage:
 *   php import_zoho_staff.php [path/to/portal_users.csv]
 */

define('STAFF_IMPORT_CLI', true);
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
$ci->load->helper(['database', 'func', 'general', 'user_meta']);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function staff_import_log($message)
{
    echo $message . PHP_EOL;
}

function staff_import_parse_datetime($value)
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    $value   = trim($value);
    $formats = [
        'd/m/Y h:i a',
        'd/m/Y g:i a',
        'd/m/Y H:i',
    ];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    return null;
}

function staff_import_generate_password($length = 12)
{
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$%';
    $max   = strlen($chars) - 1;
    $out   = '';

    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, $max)];
    }

    return $out;
}

function staff_import_resolve_admin($portalProfile, $role)
{
    $portalProfile = strtolower(trim($portalProfile));
    $role          = strtolower(trim($role));

    if ($portalProfile === 'portal owner') {
        return 1;
    }

    if ($portalProfile === 'admin') {
        return 1;
    }

    if ($role === 'administrator') {
        return 1;
    }

    return 0;
}

function staff_import_resolve_role_name($portalProfile, $role)
{
    $role = trim($role);

    if ($role !== '') {
        $normalized = strtolower($role);

        if ($normalized === 'administrator') {
            return 'Administrator';
        }

        if ($normalized === 'manager') {
            return 'Project Manager';
        }

        if ($normalized === 'employee') {
            return 'Employee';
        }
        if ($normalized === 'Team Leader') {
            return 'Team Leader';
        }

        return $role;
    }

    $map = [
        'Admin'        => 'Administrator',
        'Portal Owner' => 'Project Manager',
        'Manager'      => 'Project Manager',
        'Employee'     => 'Employee',
        'Team Leaders'       => 'Team Leaders',
    ];

    return $map[trim($portalProfile)] ?? 'Team Leaders';
}

function staff_import_resolve_role_id($roleName, $rolesByName, $defaultRoleId)
{
    $normalized = strtolower(trim($roleName));

    if ($normalized !== '' && isset($rolesByName[$normalized])) {
        return (int) $rolesByName[$normalized];
    }

    foreach ($rolesByName as $name => $roleId) {
        if ($normalized !== '' && (strpos($name, $normalized) !== false || strpos($normalized, $name) !== false)) {
            return (int) $roleId;
        }
    }

    return $defaultRoleId;
}

function staff_import_get_default_role_id($ci)
{
    $row = $ci->db->select('value')
        ->where('name', 'default_staff_role')
        ->get(db_prefix() . 'options')
        ->row();

    if ($row && is_numeric($row->value) && (int) $row->value > 0) {
        return (int) $row->value;
    }

    $fallback = $ci->db->select('roleid')
        ->order_by('roleid', 'ASC')
        ->limit(1)
        ->get(db_prefix() . 'roles')
        ->row();

    return $fallback ? (int) $fallback->roleid : 0;
}

function staff_import_email_exists($ci, $email)
{
    return total_rows(db_prefix() . 'staff', ['email' => $email]) > 0;
}

function staff_import_dismiss_announcements($ci, $staffId)
{
    $ci->db->select('announcementid');
    $ci->db->from(db_prefix() . 'announcements');
    $ci->db->where('showtostaff', 1);
    $announcements = $ci->db->get()->result_array();

    foreach ($announcements as $announcement) {
        $ci->db->insert(db_prefix() . 'dismissed_announcements', [
            'announcementid' => $announcement['announcementid'],
            'staff'          => 1,
            'userid'         => $staffId,
        ]);
    }
}

function staff_import_apply_role_permissions($ci, $staffId, $roleId, $isAdmin)
{
    if ($isAdmin) {
        $ci->staff_model->update_permissions([], $staffId);

        return;
    }

    $role = $ci->roles_model->get($roleId);

    if (!$role || empty($role->permissions)) {
        $ci->staff_model->update_permissions([], $staffId);

        return;
    }

    $ci->staff_model->update_permissions($role->permissions, $staffId);
}

function staff_import_insert_staff($ci, array $data, $roleId, $isAdmin, $zohoSystemId)
{
    $ci->db->insert(db_prefix() . 'staff', $data);
    $staffId = (int) $ci->db->insert_id();

    if ($staffId <= 0) {
        return 0;
    }

    staff_import_apply_role_permissions($ci, $staffId, $roleId, $isAdmin);
    staff_import_dismiss_announcements($ci, $staffId);

    if ($zohoSystemId !== '') {
        add_staff_meta($staffId, 'zoho_user_system_id', $zohoSystemId);
    }

    log_activity('Zoho Staff Import [ID: ' . $staffId . ', ' . $data['firstname'] . ' ' . $data['lastname'] . ']');

    return $staffId;
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

$csvPath = $argv[1] ?? '/Users/concatstringsolutions/Downloads/portal_users-2231293000004094002.csv';

if (!is_readable($csvPath)) {
    staff_import_log('ERROR: CSV file not readable: ' . $csvPath);
    exit(1);
}

$rolesRows   = $ci->db->get(db_prefix() . 'roles')->result_array();
$rolesByName = [];
$rolesById   = [];

foreach ($rolesRows as $roleRow) {
    $rolesByName[strtolower(trim($roleRow['name']))] = (int) $roleRow['roleid'];
    $rolesById[(int) $roleRow['roleid']]              = $roleRow['name'];
}

$defaultRoleId = staff_import_get_default_role_id($ci);

staff_import_log('Zoho staff import started');
staff_import_log('CSV: ' . $csvPath);
staff_import_log('Available roles: ' . implode(', ', array_column($rolesRows, 'name')));
staff_import_log('Default role ID: ' . $defaultRoleId);
staff_import_log(str_repeat('-', 60));

$handle = fopen($csvPath, 'r');

if ($handle === false) {
    staff_import_log('ERROR: Unable to open CSV.');
    exit(1);
}

$header = fgetcsv($handle);

if ($header === false) {
    staff_import_log('ERROR: CSV is empty.');
    exit(1);
}

$columnMap = [];

foreach ($header as $index => $label) {
    $columnMap[trim($label)] = $index;
}

$requiredColumns = ['First Name', 'Last Name', 'Email ID', 'Portal Profile', 'Role', 'User Status'];

foreach ($requiredColumns as $column) {
    if (!array_key_exists($column, $columnMap)) {
        staff_import_log('ERROR: Missing required CSV column: ' . $column);
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

    $firstname     = trim($row[$columnMap['First Name']] ?? '');
    $lastname      = trim($row[$columnMap['Last Name']] ?? '');
    $email         = strtolower(trim($row[$columnMap['Email ID']] ?? ''));
    $portalProfile = trim($row[$columnMap['Portal Profile']] ?? '');
    $roleLabel     = trim($row[$columnMap['Role']] ?? '');
    $userStatus    = trim($row[$columnMap['User Status']] ?? 'Active');
    $createdTime   = trim($row[$columnMap['Created Time'] ?? ''] ?? '');
    $lastAccessed  = trim($row[$columnMap['Last Accessed On'] ?? ''] ?? '');
    $zohoSystemId  = trim($row[$columnMap['User System ID'] ?? ''] ?? '');

    if ($firstname === '' || $lastname === '' || $email === '') {
        $failed[] = [
            'row'    => $rowNumber,
            'email'  => $email,
            'reason' => 'Missing first name, last name, or email',
        ];
        staff_import_log("FAIL row {$rowNumber}: missing required fields");
        continue;
    }

    if (staff_import_email_exists($ci, $email)) {
        $skipped[] = [
            'row'   => $rowNumber,
            'email' => $email,
            'name'  => $firstname . ' ' . $lastname,
        ];
        staff_import_log("SKIP row {$rowNumber}: {$email} already exists");
        continue;
    }

    $plainPassword = staff_import_generate_password();
    $roleName        = staff_import_resolve_role_name($portalProfile, $roleLabel);
    $roleId          = staff_import_resolve_role_id($roleName, $rolesByName, $defaultRoleId);
    $isAdmin         = staff_import_resolve_admin($portalProfile, $roleLabel);
    $isActive        = strtolower($userStatus) === 'active' ? 1 : 0;
    $dateCreated     = staff_import_parse_datetime($createdTime) ?: date('Y-m-d H:i:s');
    $lastLogin       = staff_import_parse_datetime($lastAccessed);

    $insert = [
        'firstname'       => $firstname,
        'lastname'        => $lastname,
        'email'           => $email,
        'password'        => app_hash_password($plainPassword),
        'datecreated'     => $dateCreated,
        'active'          => $isActive,
        'admin'           => $isAdmin,
        'role'            => $roleId,
        'phonenumber'     => '',
        'facebook'        => '',
        'linkedin'        => '',
        'skype'           => '',
        'hourly_rate'     => 0,
        'is_not_staff'    => 0,
        'media_path_slug' => slug_it($firstname . ' ' . $lastname),
    ];

    if ($lastLogin !== null) {
        $insert['last_login'] = $lastLogin;
    }

    $staffId = staff_import_insert_staff($ci, $insert, $roleId, $isAdmin, $zohoSystemId);

    if ($staffId <= 0) {
        $failed[] = [
            'row'    => $rowNumber,
            'email'  => $email,
            'reason' => 'Database insert failed',
        ];
        staff_import_log("FAIL row {$rowNumber}: {$email} insert failed");
        continue;
    }

    $created[] = [
        'staffid'  => $staffId,
        'row'      => $rowNumber,
        'name'     => $firstname . ' ' . $lastname,
        'email'    => $email,
        'password' => $plainPassword,
        'active'   => $isActive,
        'admin'    => $isAdmin,
        'role_id'  => $roleId,
        'role'     => $rolesById[$roleId] ?? $roleName,
    ];

    staff_import_log("OK   row {$rowNumber}: {$email} (staffid {$staffId})");
}

fclose($handle);

$credentialsFile = FCPATH . 'uploads/staff_import_credentials_' . date('Y-m-d_His') . '.csv';
$credentialsDir  = dirname($credentialsFile);

if (!is_dir($credentialsDir)) {
    mkdir($credentialsDir, 0755, true);
}

$credentialsHandle = fopen($credentialsFile, 'w');

if ($credentialsHandle !== false) {
    fputcsv($credentialsHandle, ['staffid', 'name', 'email', 'password', 'active', 'admin', 'role_id', 'role_name']);

    foreach ($created as $item) {
        fputcsv($credentialsHandle, [
            $item['staffid'],
            $item['name'],
            $item['email'],
            $item['password'],
            $item['active'],
            $item['admin'],
            $item['role_id'],
            $item['role'],
        ]);
    }

    fclose($credentialsHandle);
}

staff_import_log(str_repeat('-', 60));
staff_import_log('Import complete');
staff_import_log('Created: ' . count($created));
staff_import_log('Skipped: ' . count($skipped));
staff_import_log('Failed:  ' . count($failed));

if (count($created) > 0) {
    staff_import_log('Credentials saved to: ' . $credentialsFile);
}

if (count($skipped) > 0) {
    staff_import_log('Skipped emails: ' . implode(', ', array_column($skipped, 'email')));
}

if (count($failed) > 0) {
    foreach ($failed as $fail) {
        staff_import_log('Failed row ' . $fail['row'] . ' (' . $fail['email'] . '): ' . $fail['reason']);
    }
    exit(1);
}

exit(0);
