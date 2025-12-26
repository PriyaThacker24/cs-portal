<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Timelog
Description: Zoho-style timelog management module for viewing and managing all timelogs across projects
Version: 1.0.0
Requires at least: 2.3.*
*/

define('TIMELOG_MODULE_NAME', 'timelog');

$CI = &get_instance();

/**
 * Register activation module hook
 */
register_activation_hook(TIMELOG_MODULE_NAME, 'timelog_activation_hook');

function timelog_activation_hook()
{
    // Module activation logic if needed
}

/**
 * Register language files
 */
register_language_files(TIMELOG_MODULE_NAME, [TIMELOG_MODULE_NAME]);

/**
 * Add menu item to sidebar
 */
hooks()->add_action('admin_init', 'timelog_init_menu_items');

function timelog_init_menu_items()
{
    $CI = &get_instance();
    
    // Check if user has permission to view timesheets or is admin
    // Allow access if user can view timesheets in reports, view tasks, or is admin
    // if (staff_can('view-timesheets', 'reports') || staff_can('view', 'tasks') || is_admin()) {
        $CI->app_menu->add_sidebar_menu_item('timelog', [
            'name'     => _l('timelog_menu'),
            'href'     => admin_url('timelog'),
            'position' => 55,
            'icon'     => 'fa-regular fa-clock',
            'badge'    => [],
        ]);
    // }
}

