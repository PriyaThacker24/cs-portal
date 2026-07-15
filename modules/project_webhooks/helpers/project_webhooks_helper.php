<?php

defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------
 | Hook handlers
 | -------------------------------------------------------------------------*/

/**
 * Project created.
 *
 * @param int $project_id
 */
function pw_on_project_created($project_id)
{
    $project_id = (int) $project_id;

    // Full project summary + the initial member list (with role).
    pw_enqueue('project.created', $project_id, pw_project_summary($project_id), [
        'members' => pw_project_members_detailed($project_id),
    ]);

    // Seed the member snapshot silently (creation is already conveyed above).
    pw_sync_members($project_id, true);
}

/**
 * Project updated (edit form). Members may have changed here too.
 *
 * @param int $project_id
 */
function pw_on_project_updated($project_id)
{
    $project_id = (int) $project_id;

    // Reduced project object: id, name, clientid, website_url.
    pw_enqueue('project.updated', $project_id, pw_project_basic($project_id));
    pw_sync_members($project_id);
}

/**
 * Project status changed.
 *
 * @param array $data ['status' => int, 'project_id' => int]
 */
function pw_on_project_status_changed($data)
{
    if (!isset($data['project_id'])) {
        return;
    }

    $project_id = (int) $data['project_id'];
    $status     = isset($data['status']) ? (int) $data['status'] : null;

    pw_enqueue('project.status_changed', $project_id, pw_project_identity($project_id), [
        'status'      => $status,
        'status_name' => $status !== null ? _l('project_status_' . $status) : null,
    ]);
}

/**
 * Core "member added" hook (fires when an added member is flagged for email).
 * We still route through the snapshot diff so the event shape is consistent.
 *
 * @param array $data ['project_id' => int, 'new_project_members_to_receive_email' => int[]]
 */
function pw_on_member_added($data)
{
    if (!isset($data['project_id'])) {
        return;
    }

    pw_sync_members((int) $data['project_id']);
}

/* ---------------------------------------------------------------------------
 | Member snapshot / diff
 | -------------------------------------------------------------------------*/

/**
 * Compare the project's current members against the stored snapshot and
 * enqueue a project.member_added event for each newly added staff id.
 *
 * @param int  $project_id
 * @param bool $silent  When true, only seed the snapshot without emitting
 *                      events (used right after project.created, whose payload
 *                      already conveys the project's initial members).
 */
function pw_sync_members($project_id, $silent = false)
{
    $current  = pw_current_member_ids($project_id);
    $previous = pw_snapshot_get($project_id);

    if (!$silent && $previous !== null) {
        $added   = array_values(array_diff($current, $previous));
        $removed = array_values(array_diff($previous, $current));

        // One event per save, carrying all members added in that save.
        if (!empty($added)) {
            pw_enqueue('project.member_added', $project_id, pw_project_identity($project_id), [
                'members' => pw_staff_detailed($added),
            ]);
        }

        // Likewise for members removed in that save. Their details are read
        // from tblstaff directly, since they are no longer project members.
        if (!empty($removed)) {
            pw_enqueue('project.member_removed', $project_id, pw_project_identity($project_id), [
                'members' => pw_staff_detailed($removed),
            ]);
        }
    }

    pw_snapshot_set($project_id, $current);
}

/**
 * @param  int $project_id
 * @return int[]
 */
function pw_current_member_ids($project_id)
{
    $CI   = &get_instance();
    $rows = $CI->db
        ->select('staff_id')
        ->where('project_id', $project_id)
        ->get(db_prefix() . 'project_members')
        ->result_array();

    return array_map(function ($r) {
        return (int) $r['staff_id'];
    }, $rows);
}

/**
 * Full member list of a project with email, name and the staff member's role
 * (the staff role name from tblroles). Staff with no role fall back to
 * "Administrator" when they are an admin, otherwise null.
 *
 * @param  int $project_id
 * @return array[]
 */
function pw_project_members_detailed($project_id)
{
    return pw_staff_detailed(pw_current_member_ids($project_id));
}

/**
 * Detailed info for the given staff ids: email, name and the staff role name
 * (from tblroles). Staff with no role fall back to "Administrator" when they
 * are an admin, otherwise null. Order follows the given ids.
 *
 * @param  int[] $staff_ids
 * @return array[]
 */
function pw_staff_detailed($staff_ids)
{
    $staff_ids = array_values(array_unique(array_map('intval', (array) $staff_ids)));
    if (empty($staff_ids)) {
        return [];
    }

    $CI = &get_instance();
    $p  = db_prefix();

    $rows = $CI->db
        ->select(
            $p . 'staff.staffid,' . $p . 'staff.email,' . $p . 'staff.firstname,'
            . $p . 'staff.lastname,' . $p . 'staff.role,' . $p . 'staff.admin,'
            . $p . 'roles.name as role_name',
            false
        )
        ->join($p . 'roles', $p . 'roles.roleid = ' . $p . 'staff.role', 'left')
        ->where_in($p . 'staff.staffid', $staff_ids)
        ->get($p . 'staff')
        ->result_array();

    // Index by staff id so we can return in the requested order.
    $by_id = [];
    foreach ($rows as $r) {
        $role = $r['role_name'];
        if (($role === null || $role === '') && (int) $r['admin'] === 1) {
            $role = 'Administrator';
        }

        $by_id[(int) $r['staffid']] = [
            'staff_id' => (int) $r['staffid'],
            'email'    => $r['email'],
            'name'     => trim($r['firstname'] . ' ' . $r['lastname']),
            'role_id'  => (int) $r['role'],
            'role'     => ($role !== null && $role !== '') ? $role : null,
        ];
    }

    $result = [];
    foreach ($staff_ids as $id) {
        $result[] = isset($by_id[$id]) ? $by_id[$id] : ['staff_id' => $id];
    }

    return $result;
}

/**
 * @param  int      $project_id
 * @return int[]|null  null when no snapshot exists yet.
 */
function pw_snapshot_get($project_id)
{
    $CI  = &get_instance();
    $row = $CI->db
        ->where('project_id', $project_id)
        ->get(db_prefix() . 'project_webhook_member_snapshot')
        ->row();

    if (!$row) {
        return null;
    }

    $ids = json_decode($row->staff_ids, true);

    return is_array($ids) ? array_map('intval', $ids) : [];
}

/**
 * @param int   $project_id
 * @param int[] $ids
 */
function pw_snapshot_set($project_id, $ids)
{
    $CI   = &get_instance();
    $data = [
        'project_id' => $project_id,
        'staff_ids'  => json_encode(array_values(array_map('intval', $ids))),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    if ($CI->db->where('project_id', $project_id)->count_all_results(db_prefix() . 'project_webhook_member_snapshot') > 0) {
        $CI->db->where('project_id', $project_id)->update(db_prefix() . 'project_webhook_member_snapshot', [
            'staff_ids'  => $data['staff_ids'],
            'updated_at' => $data['updated_at'],
        ]);
    } else {
        $CI->db->insert(db_prefix() . 'project_webhook_member_snapshot', $data);
    }
}

/* ---------------------------------------------------------------------------
 | Payload + enqueue
 | -------------------------------------------------------------------------*/

/**
 * Build the envelope and insert it into the delivery queue.
 *
 * @param string $event      e.g. 'project.status_changed'
 * @param int    $project_id
 * @param array  $project    the project object for this event's payload
 * @param array  $data       event-specific extra data
 */
function pw_enqueue($event, $project_id, $project, $data = [])
{
    $CI = &get_instance();

    $payload = [
        'event'        => $event,
        'occurred_at'  => date('c'),
        'project'      => $project,
        'data'         => $data,
        'triggered_by' => pw_triggered_by(),
    ];

    $CI->db->insert(db_prefix() . 'project_webhook_queue', [
        'event'           => $event,
        'project_id'      => $project_id,
        'payload'         => json_encode($payload),
        'status'          => 'pending',
        'attempts'        => 0,
        'next_attempt_at' => null,
        'created_at'      => date('Y-m-d H:i:s'),
    ]);
}

/**
 * Lightweight project summary (not the full project object).
 *
 * @param  int $project_id
 * @return array|null
 */
function pw_project_summary($project_id)
{
    $CI = &get_instance();
    $p  = db_prefix();

    $row = $CI->db
        ->select(
            $p . 'projects.id,' . $p . 'projects.name,' . $p . 'projects.status,'
            . $p . 'projects.clientid,' . $p . 'projects.start_date,' . $p . 'projects.deadline,'
            . get_sql_select_client_company(),
            false
        )
        ->join($p . 'clients', $p . 'clients.userid = ' . $p . 'projects.clientid', 'left')
        ->where($p . 'projects.id', $project_id)
        ->get($p . 'projects')
        ->row_array();

    if (!$row) {
        return null;
    }

    $CI->load->helper('custom_fields');

    // Explicit key order to match the documented payload shape.
    return [
        'id'          => (int) $row['id'],
        'name'        => $row['name'],
        'website_url' => pw_project_website_url($project_id),
        'clientid'    => (int) $row['clientid'],
        'status'      => (int) $row['status'],
        'status_name' => _l('project_status_' . (int) $row['status']),
        'company'     => $row['company'],
        'start_date'  => $row['start_date'],
        'deadline'    => $row['deadline'],
    ];
}

/**
 * Reduced project object for project.updated: id, name, clientid, website_url.
 *
 * @param  int $project_id
 * @return array|null
 */
function pw_project_basic($project_id)
{
    $CI = &get_instance();
    $p  = db_prefix();

    $row = $CI->db
        ->select('id, name, clientid')
        ->where('id', $project_id)
        ->get($p . 'projects')
        ->row_array();

    if (!$row) {
        return null;
    }

    return [
        'id'          => (int) $row['id'],
        'name'        => $row['name'],
        'clientid'    => (int) $row['clientid'],
        'website_url' => pw_project_website_url($project_id),
    ];
}

/**
 * Minimal project identity for project.status_changed: id, name.
 *
 * @param  int $project_id
 * @return array|null
 */
function pw_project_identity($project_id)
{
    $CI  = &get_instance();
    $row = $CI->db
        ->select('id, name')
        ->where('id', $project_id)
        ->get(db_prefix() . 'projects')
        ->row_array();

    return $row ? ['id' => (int) $row['id'], 'name' => $row['name']] : null;
}

/**
 * Value of the project's website_url column (null when unset).
 *
 * @param  int $project_id
 * @return string|null
 */
function pw_project_website_url($project_id)
{
    $CI = &get_instance();

    if (!in_array('website_url', $CI->db->list_fields(db_prefix() . 'projects'))) {
        return null;
    }

    $row = $CI->db
        ->select('website_url')
        ->where('id', $project_id)
        ->get(db_prefix() . 'projects')
        ->row();

    return ($row && $row->website_url !== '' && $row->website_url !== null) ? $row->website_url : null;
}

/**
 * Who triggered the change (staff, client contact, or cron).
 *
 * @return array
 */
function pw_triggered_by()
{
    if (defined('CRON')) {
        return ['type' => 'cron', 'id' => 0, 'name' => '[CRON]'];
    }

    if (function_exists('is_staff_logged_in') && is_staff_logged_in()) {
        return [
            'type' => 'staff',
            'id'   => (int) get_staff_user_id(),
            'name' => get_staff_full_name(get_staff_user_id()),
        ];
    }

    if (function_exists('is_client_logged_in') && is_client_logged_in()) {
        return [
            'type' => 'contact',
            'id'   => (int) get_contact_user_id(),
            'name' => get_contact_full_name(get_contact_user_id()),
        ];
    }

    return ['type' => 'system', 'id' => 0, 'name' => 'System'];
}

/* ---------------------------------------------------------------------------
 | Delivery (cron)
 | -------------------------------------------------------------------------*/

/**
 * Deliver queued webhooks. Registered on the after_cron_run hook.
 */
function pw_process_queue()
{
    if (!defined('PROJECT_WEBHOOK_URL') || PROJECT_WEBHOOK_URL === '') {
        return;
    }

    $CI  = &get_instance();
    $now = date('Y-m-d H:i:s');

    $rows = $CI->db
        ->where('status', 'pending')
        ->group_start()
            ->where('next_attempt_at IS NULL', null, false)
            ->or_where('next_attempt_at <=', $now)
        ->group_end()
        ->order_by('id', 'asc')
        ->limit(PROJECT_WEBHOOK_BATCH_SIZE)
        ->get(db_prefix() . 'project_webhook_queue')
        ->result_array();

    foreach ($rows as $row) {
        pw_deliver($row);
    }
}

/**
 * POST a single queued row and update its delivery state.
 *
 * @param array $row
 */
function pw_deliver($row)
{
    $CI       = &get_instance();
    $attempts = (int) $row['attempts'] + 1;

    $verify_ssl = defined('PROJECT_WEBHOOK_VERIFY_SSL') ? PROJECT_WEBHOOK_VERIFY_SSL : true;

    $ch = curl_init(PROJECT_WEBHOOK_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $row['payload'],
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Webhook-Event: ' . $row['event'],
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => $verify_ssl,
        CURLOPT_SSL_VERIFYHOST => $verify_ssl ? 2 : 0,
    ]);

    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    $success = ($code >= 200 && $code < 300);

    $update = [
        'attempts'      => $attempts,
        'response_code' => $code ?: null,
        'response_body' => $err !== '' ? mb_substr($err, 0, 1000) : mb_substr((string) $body, 0, 1000),
    ];

    if ($success) {
        $update['status']  = 'sent';
        $update['sent_at'] = date('Y-m-d H:i:s');
    } elseif ($attempts >= PROJECT_WEBHOOK_MAX_ATTEMPTS) {
        $update['status'] = 'failed';
    } else {
        // Exponential backoff: 2^attempts minutes.
        $update['status']          = 'pending';
        $update['next_attempt_at'] = date('Y-m-d H:i:s', time() + (int) pow(2, $attempts) * 60);
    }

    $CI->db->where('id', $row['id'])->update(db_prefix() . 'project_webhook_queue', $update);
}
