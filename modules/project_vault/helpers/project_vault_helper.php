<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Resolve the project id currently being viewed from the request URI.
 *
 * The vault tab is registered on admin_init (before the Projects controller
 * runs), so we detect the id from the URL: admin/projects/{view|gantt}/{id}.
 *
 * @return int project id, or 0 when not on a project view
 */
function project_vault_current_project_id()
{
    $CI = &get_instance();

    $segments = $CI->uri->segment_array();

    foreach ($segments as $i => $segment) {
        if ($segment === 'projects' && isset($segments[$i + 1]) && in_array($segments[$i + 1], ['view', 'gantt'])) {
            $id = isset($segments[$i + 2]) ? $segments[$i + 2] : 0;

            return (int) $id;
        }
    }

    return 0;
}

/**
 * Whether the current staff user may access a project's vault.
 *
 * Access = admin OR assigned project member. Kept here so the controller,
 * the model and the tab registration all share one definition.
 *
 * @param  int $project_id
 * @return bool
 */
function project_vault_can_access($project_id)
{
    $project_id = (int) $project_id;

    if (!$project_id) {
        return false;
    }

    if (is_admin()) {
        return true;
    }

    $CI = &get_instance();
    $CI->load->model('projects_model');

    return (bool) $CI->projects_model->is_member($project_id);
}

/**
 * Mask a secret for display (e.g. in tables) without revealing its length.
 *
 * @param  string $secret
 * @return string
 */
function project_vault_mask($secret)
{
    return $secret === '' || $secret === null ? '' : str_repeat('•', 8);
}

/**
 * Assigned project members eligible to share a vault with: all project members
 * EXCEPT admins (admins can already see every vault) and the current user.
 *
 * @param  int $project_id
 * @return array list of ['staff_id' => int, 'name' => string]
 */
function project_vault_shareable_members($project_id)
{
    $CI = &get_instance();
    $CI->load->model('projects_model');

    $members = $CI->projects_model->get_project_members((int) $project_id, true);
    $current = (int) get_staff_user_id();
    $out     = [];

    foreach ($members as $m) {
        $sid = (int) $m['staff_id'];
        if ($sid === $current || is_admin($sid)) {
            continue;
        }
        $out[] = [
            'staff_id' => $sid,
            'name'     => trim($m['firstname'] . ' ' . $m['lastname']),
        ];
    }

    return $out;
}

/**
 * Just the staff ids from project_vault_shareable_members().
 *
 * @param  int $project_id
 * @return array of int
 */
function project_vault_shareable_member_ids($project_id)
{
    return array_map(function ($m) {
        return (int) $m['staff_id'];
    }, project_vault_shareable_members($project_id));
}
