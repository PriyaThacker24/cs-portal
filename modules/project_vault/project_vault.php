<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Project Vault
Description: A Zoho-Vault-style secure store scoped per project. Each project gets a "Vault" tab holding credential entries (URL, username, password/secret, notes) with optional file attachments, encrypted at rest, plus a full audit history of who created / updated / deleted each entry.
Version: 1.0.0
Requires at least: 2.3.*
*/

define('PROJECT_VAULT_MODULE_NAME', 'project_vault');

require_once __DIR__ . '/helpers/project_vault_helper.php';

/**
 * Create the vault tables on activation.
 */
register_activation_hook(PROJECT_VAULT_MODULE_NAME, 'project_vault_activation_hook');

function project_vault_activation_hook()
{
    $CI = &get_instance();
    require_once __DIR__ . '/install.php';
}

/**
 * Register the module language files.
 */
register_language_files(PROJECT_VAULT_MODULE_NAME, [PROJECT_VAULT_MODULE_NAME]);

/**
 * Register the staff capabilities and the project "Vault" tab on admin init.
 */
hooks()->add_action('admin_init', 'project_vault_permissions');
hooks()->add_action('admin_init', 'project_vault_init_tab');

/**
 * Register staff capabilities so the vault shows up under Roles / permissions.
 * Effective access is still gated by project membership (see the controller).
 */
function project_vault_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
        'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
        'create' => _l('permission_create'),
        'edit'   => _l('permission_edit'),
        'delete' => _l('permission_delete'),
    ];

    register_staff_capabilities('project_vault', $capabilities, _l('project_vault'));
}

/**
 * Add the "Vault" tab to the project view. Rendered right after Files.
 * The tab is only visible to admins and staff who are members of the project.
 */
function project_vault_init_tab()
{
    $CI = &get_instance();

    // Only meaningful on the project view where a project id is present.
    $project_id = project_vault_current_project_id();

    if (!$project_id) {
        return;
    }

    $CI->load->model('projects_model');

    $visible = is_admin() || $CI->projects_model->is_member($project_id);

    $CI->app_tabs->add_project_tab('project_vault', [
        'name'     => _l('project_vault'),
        'icon'     => 'fa-solid fa-lock',
        'view'     => 'project_vault/project_vault_tab',
        'position' => 27,
        'visible'  => $visible,
    ]);

    if (!$visible) {
        return;
    }

    // Inject the vault CSS into <head> (registered now, on admin_init, so it
    // lands in the head before the page renders) and the JS into the footer.
    // Enqueuing these from inside the tab view is too late for the <head>.
    $version = function ($relative_path) {
        $absolute_path = module_dir_path('project_vault', $relative_path);
        return is_file($absolute_path) ? filemtime($absolute_path) : '';
    };

    hooks()->add_action('app_admin_head', function () use ($version) {
        echo '<link rel="stylesheet" href="' . module_dir_url('project_vault', 'assets/css/project_vault.css') . '?v=' . $version('assets/css/project_vault.css') . '">';
    });

    hooks()->add_action('app_admin_footer', function () use ($version) {
        echo '<script src="' . module_dir_url('project_vault', 'assets/js/project_vault.js') . '?v=' . $version('assets/js/project_vault.js') . '"></script>';
    });
}
