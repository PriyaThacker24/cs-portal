<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Project_vault_model extends App_Model
{
    /**
     * Fields whose changes are recorded in the audit history.
     * The secret ("password") is tracked as a boolean "changed" flag only —
     * its plaintext value is never written to history.
     */
    private $tracked_fields = ['title', 'type', 'url', 'username', 'notes'];

    public function __construct()
    {
        parent::__construct();
        $this->load->library('encryption');
    }

    /**
     * Centralised access guard, shared with the helper.
     *
     * @param  int $project_id
     * @return bool
     */
    public function can_access($project_id)
    {
        return project_vault_can_access($project_id);
    }

    /**
     * Get all vault entries for a project. Secrets are returned masked unless
     * $reveal is true (never expose plaintext to list views).
     *
     * @param  int  $project_id
     * @return array
     */
    public function get_entries($project_id)
    {
        $this->db->where('project_id', (int) $project_id);

        // Access filter: admins see everything. Everyone else sees only the
        // entries they created or that are explicitly shared with them.
        if (!is_admin()) {
            $staff_id = (int) get_staff_user_id();
            $this->db->group_start();
            $this->db->where('created_by', $staff_id);
            $this->db->or_where("FIND_IN_SET(" . $staff_id . ", shared_with) >", 0, false);
            $this->db->group_end();
        }

        $this->db->order_by('dateadded', 'DESC');
        $entries = $this->db->get(db_prefix() . 'project_vault_entries')->result_array();

        foreach ($entries as &$entry) {
            $entry['has_password'] = !empty($entry['password']);
            $entry['has_notes']    = !empty($entry['notes']);
            // Never send secret or notes plaintext to the listing; they are
            // fetched via the reveal endpoint.
            $entry['password']  = project_vault_mask($entry['password']);
            $entry['notes']     = '';
            $entry['files']     = $this->get_files($entry['id']);
            $entry['shared_ids'] = $this->parse_shared($entry['shared_with']);
        }

        return $entries;
    }

    /**
     * Whether the current staff user may view/manage a specific entry.
     * Admin -> always. Otherwise creator or a shared member.
     *
     * @param  object $entry
     * @return bool
     */
    public function can_access_entry($entry)
    {
        if (!$entry) {
            return false;
        }

        if (is_admin()) {
            return true;
        }

        $staff_id = (int) get_staff_user_id();

        if ((int) $entry->created_by === $staff_id) {
            return true;
        }

        return in_array($staff_id, $this->parse_shared($entry->shared_with ?? null), true);
    }

    /**
     * Get a single entry. Set $with_secret to decrypt the password.
     *
     * @param  int  $id
     * @param  bool $with_secret
     * @return object|null
     */
    public function get_entry($id, $with_secret = false)
    {
        $this->db->where('id', (int) $id);
        $entry = $this->db->get(db_prefix() . 'project_vault_entries')->row();

        if (!$entry) {
            return null;
        }

        $entry->has_password = !empty($entry->password);

        if ($with_secret) {
            $entry->password = $entry->password ? $this->decrypt($entry->password) : '';
        } else {
            $entry->password = project_vault_mask($entry->password);
        }

        $entry->files      = $this->get_files($entry->id);
        $entry->shared_ids = $this->parse_shared($entry->shared_with ?? null);

        return $entry;
    }

    /**
     * Decrypt a stored secret. Public so the controller's reveal endpoint can
     * use it without touching the raw crypto library.
     *
     * @param  int $id
     * @return string|null null if the entry doesn't exist
     */
    public function reveal_secret($id)
    {
        $this->db->select('password');
        $this->db->where('id', (int) $id);
        $row = $this->db->get(db_prefix() . 'project_vault_entries')->row();

        if (!$row) {
            return null;
        }

        return $row->password ? $this->decrypt($row->password) : '';
    }

    /**
     * Create a vault entry.
     *
     * @param  int   $project_id
     * @param  array $data raw posted data (plaintext password)
     * @return int|false new entry id
     */
    public function create($project_id, $data)
    {
        $insert = [
            'project_id'   => (int) $project_id,
            'title'        => trim($data['title'] ?? ''),
            'type'         => !empty($data['type']) ? $data['type'] : 'web_account',
            'url'          => $data['url'] ?? null,
            'username'     => $data['username'] ?? null,
            'password'     => isset($data['password']) && $data['password'] !== '' ? $this->encrypt($data['password']) : null,
            'notes'        => $data['notes'] ?? null,
            'shared_with'  => $this->sanitize_shared($project_id, $data['shared_with'] ?? []),
            'created_by'   => get_staff_user_id(),
            'dateadded'    => date('Y-m-d H:i:s'),
        ];

        $this->db->insert(db_prefix() . 'project_vault_entries', $insert);
        $id = $this->db->insert_id();

        if (!$id) {
            return false;
        }

        $this->log($id, $project_id, $insert['title'], 'created', $this->snapshot_changes([], $data));

        return $id;
    }

    /**
     * Update a vault entry.
     *
     * @param  int   $id
     * @param  int   $project_id
     * @param  array $data
     * @return bool
     */
    public function update($id, $project_id, $data)
    {
        $existing = $this->get_entry($id, true);

        if (!$existing) {
            return false;
        }

        $update = [
            'title'        => trim($data['title'] ?? ''),
            'type'         => !empty($data['type']) ? $data['type'] : 'web_account',
            'url'          => $data['url'] ?? null,
            'username'     => $data['username'] ?? null,
            'notes'        => $data['notes'] ?? null,
            'shared_with'  => $this->sanitize_shared($project_id, $data['shared_with'] ?? []),
            'updated_by'   => get_staff_user_id(),
            'datemodified' => date('Y-m-d H:i:s'),
        ];

        // Only overwrite the secret when a new one is supplied, so editing
        // other fields doesn't wipe an existing password.
        if (array_key_exists('password', $data) && $data['password'] !== '') {
            $update['password'] = $this->encrypt($data['password']);
        }

        $before = [
            'title'    => $existing->title,
            'type'     => $existing->type,
            'url'      => $existing->url,
            'username' => $existing->username,
            'notes'    => $existing->notes,
            'password' => $existing->password,
        ];

        $this->db->where('id', (int) $id);
        $this->db->update(db_prefix() . 'project_vault_entries', $update);

        $changes = $this->snapshot_changes($before, $data);

        if (!empty($changes)) {
            $this->log($id, $project_id, $update['title'], 'updated', $changes);
        }

        return true;
    }

    /**
     * Delete an entry, its attachments (rows + files) and record the history.
     *
     * @param  int $id
     * @param  int $project_id
     * @return bool
     */
    public function delete($id, $project_id)
    {
        $entry = $this->get_entry($id);

        if (!$entry) {
            return false;
        }

        foreach ($this->get_files($id) as $file) {
            $this->delete_file($file['id'], $project_id, false);
        }

        $this->db->where('id', (int) $id);
        $this->db->delete(db_prefix() . 'project_vault_entries');

        // entry_id is left NULL by log() semantics? No — keep it so history
        // rows still reference the (now gone) id; entry_title preserves context.
        $this->log($id, $project_id, $entry->title, 'deleted', []);

        return true;
    }

    /**
     * Record that a secret was revealed.
     */
    public function log_reveal($id, $project_id, $title)
    {
        $this->log($id, $project_id, $title, 'viewed_secret', []);
    }

    /* -------------------------------------------------------------------- */
    /* Attachments                                                          */
    /* -------------------------------------------------------------------- */

    public function get_files($entry_id)
    {
        $this->db->where('entry_id', (int) $entry_id);
        $this->db->order_by('dateadded', 'ASC');

        return $this->db->get(db_prefix() . 'project_vault_files')->result_array();
    }

    public function get_file($file_id)
    {
        $this->db->where('id', (int) $file_id);

        return $this->db->get(db_prefix() . 'project_vault_files')->row();
    }

    public function add_file($entry_id, $project_id, $file_name, $original_file_name, $filetype)
    {
        $this->db->insert(db_prefix() . 'project_vault_files', [
            'entry_id'           => (int) $entry_id,
            'project_id'         => (int) $project_id,
            'file_name'          => $file_name,
            'original_file_name' => $original_file_name,
            'filetype'           => $filetype,
            'staffid'            => get_staff_user_id(),
            'dateadded'          => date('Y-m-d H:i:s'),
        ]);

        return $this->db->insert_id();
    }

    /**
     * Delete a single attachment (row + file on disk).
     *
     * @param  int  $file_id
     * @param  int  $project_id
     * @param  bool $log whether to write a history row
     * @return bool
     */
    public function delete_file($file_id, $project_id, $log = true)
    {
        $file = $this->get_file($file_id);

        if (!$file) {
            return false;
        }

        $path = $this->upload_path($project_id) . $file->file_name;

        if (file_exists($path)) {
            @unlink($path);
        }

        $this->db->where('id', (int) $file_id);
        $this->db->delete(db_prefix() . 'project_vault_files');

        if ($log) {
            $this->log($file->entry_id, $project_id, $file->original_file_name, 'updated', ['file_removed' => $file->original_file_name]);
        }

        return true;
    }

    /**
     * Absolute upload directory for a project's vault attachments.
     *
     * @param  int $project_id
     * @return string
     */
    public function upload_path($project_id)
    {
        return PROJECT_ATTACHMENTS_FOLDER . (int) $project_id . '/vault/';
    }

    /* -------------------------------------------------------------------- */
    /* History                                                              */
    /* -------------------------------------------------------------------- */

    /**
     * Get audit history for a whole project or a single entry.
     *
     * @param  int      $project_id
     * @param  int|null $entry_id
     * @return array
     */
    public function get_history($project_id, $entry_id = null)
    {
        $this->db->select('h.*, CONCAT(s.firstname, " ", s.lastname) as staff_name');
        $this->db->from(db_prefix() . 'project_vault_history h');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = h.staff_id', 'left');
        $this->db->where('h.project_id', (int) $project_id);

        if ($entry_id !== null) {
            $this->db->where('h.entry_id', (int) $entry_id);
        }

        $this->db->order_by('h.dateadded', 'DESC');

        return $this->db->get()->result_array();
    }

    /* -------------------------------------------------------------------- */
    /* Internals                                                            */
    /* -------------------------------------------------------------------- */

    private function log($entry_id, $project_id, $entry_title, $action, $changes)
    {
        $this->db->insert(db_prefix() . 'project_vault_history', [
            'entry_id'      => $entry_id ? (int) $entry_id : null,
            'project_id'    => (int) $project_id,
            'entry_title'   => $entry_title,
            'staff_id'      => get_staff_user_id(),
            'action'        => $action,
            'field_changes' => !empty($changes) ? json_encode($changes) : null,
            'dateadded'     => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Build a list of changed field names, comparing a before-state to posted
     * data. The secret is reported as a boolean flag only — never its value.
     *
     * @param  array $before  ['field' => oldvalue], may include 'password' plaintext
     * @param  array $posted  raw posted data
     * @return array          list of changed field labels
     */
    private function snapshot_changes($before, $posted)
    {
        $changed = [];

        foreach ($this->tracked_fields as $field) {
            $old = $before[$field] ?? null;
            $new = isset($posted[$field]) ? $posted[$field] : null;

            if ((string) $old !== (string) $new) {
                $changed[] = $field;
            }
        }

        // Password: record only that it changed, never the value.
        $old_pw = $before['password'] ?? '';
        if (array_key_exists('password', $posted) && $posted['password'] !== '' && $posted['password'] !== $old_pw) {
            $changed[] = 'password';
        }

        return $changed;
    }

    /**
     * Parse a stored shared_with CSV into an array of int staff ids.
     */
    private function parse_shared($csv)
    {
        if (empty($csv)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $csv))));
    }

    /**
     * Validate posted shared staff ids against the project's actual non-admin
     * members and return a clean CSV to store. Prevents sharing with people who
     * aren't project members (or with admins, who see everything anyway).
     *
     * @param  int   $project_id
     * @param  array $posted_ids
     * @return string|null CSV of ids, or null when none
     */
    private function sanitize_shared($project_id, $posted_ids)
    {
        if (empty($posted_ids) || !is_array($posted_ids)) {
            return null;
        }

        $allowed = array_map(function ($id) {
            return (int) $id;
        }, project_vault_shareable_member_ids($project_id));

        $clean = array_values(array_intersect(
            array_unique(array_map('intval', $posted_ids)),
            $allowed
        ));

        return empty($clean) ? null : implode(',', $clean);
    }

    private function encrypt($value)
    {
        return $this->encryption->encrypt($value);
    }

    private function decrypt($value)
    {
        return $this->encryption->decrypt($value);
    }
}
