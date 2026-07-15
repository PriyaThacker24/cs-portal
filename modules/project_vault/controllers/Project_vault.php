<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Project_vault extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('project_vault/project_vault_model');
    }

    /**
     * Guard every endpoint: only admins or assigned project members pass.
     * Never trust the tab visibility alone.
     */
    private function guard($project_id, $ajax = false)
    {
        if (!$this->project_vault_model->can_access($project_id)) {
            if ($ajax) {
                ajax_access_denied();
            }
            access_denied('project_vault');
        }
    }

    /**
     * Fetch an entry and enforce access. Returns the entry or aborts.
     *
     * @param int  $project_id
     * @param int  $id
     * @param bool $manage true = require creator/admin (edit/delete/share);
     *                     false = any accessor (view/reveal/download)
     * @param bool $ajax
     */
    private function require_entry($project_id, $id, $manage, $ajax = true)
    {
        $entry = $this->project_vault_model->get_entry($id);

        $deny = function () use ($ajax) {
            if ($ajax) {
                ajax_access_denied();
            }
            access_denied('project_vault');
        };

        if (!$entry || $entry->project_id != $project_id) {
            $deny();
        }

        if (!$this->project_vault_model->can_access_entry($entry)) {
            $deny();
        }

        if ($manage && !is_admin() && (int) $entry->created_by !== (int) get_staff_user_id()) {
            $deny();
        }

        return $entry;
    }

    /**
     * Return the add/edit form (loaded into a modal via AJAX).
     */
    public function entry($project_id, $id = '')
    {
        $this->guard($project_id, true);

        if ($id !== '') {
            // Editing: only the creator or an admin may open the form.
            $data['entry'] = $this->require_entry($project_id, $id, true);
        } else {
            $data['entry'] = null;
        }

        $data['project_id'] = (int) $project_id;
        $data['members']    = project_vault_shareable_members($project_id);

        $this->load->view('entry_form', $data);
    }

    /**
     * Create or update an entry, handling attachment uploads.
     */
    public function save($project_id)
    {
        $this->guard($project_id, true);

        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $post = $this->input->post();
        $id   = $post['id'] ?? '';
        unset($post['id']);

        if (empty(trim($post['title'] ?? ''))) {
            echo json_encode(['success' => false, 'message' => _l('project_vault_title_required')]);
            die;
        }

        // For edits, load + authorize the existing entry up front (also needed
        // for the category validation below to know about existing data).
        $existing = null;
        if ($id !== '') {
            // Only the creator or an admin may edit/share an existing entry.
            $existing = $this->require_entry($project_id, $id, true);
        }

        // Category-specific required fields.
        $category = !empty($post['type']) ? $post['type'] : 'web_account';
        $error    = $this->validate_required($category, $post, $existing);
        if ($error) {
            echo json_encode(['success' => false, 'message' => $error]);
            die;
        }

        if ($id !== '') {
            $this->project_vault_model->update($id, $project_id, $post);
            $entry_id = (int) $id;
            $message  = _l('updated_successfully', _l('project_vault_entry'));
        } else {
            $entry_id = $this->project_vault_model->create($project_id, $post);
            $message  = _l('added_successfully', _l('project_vault_entry'));
        }

        if ($entry_id) {
            $this->handle_uploads($entry_id, $project_id);
        }

        echo json_encode(['success' => (bool) $entry_id, 'message' => $message]);
    }

    /**
     * Category-specific required-field rules. Returns an error string or ''.
     *
     * - web_account : password required (unless editing and one already exists)
     * - secure_note : notes required
     * - file_storage: at least one attachment (new upload, or existing on edit)
     */
    private function validate_required($category, $post, $existing)
    {
        if ($category === 'web_account') {
            $has_existing_pw = $existing && !empty($existing->has_password);
            if (empty($post['password']) && !$has_existing_pw) {
                return _l('project_vault_password_required');
            }
        } elseif ($category === 'secure_note') {
            if (trim((string) ($post['notes'] ?? '')) === '') {
                return _l('project_vault_notes_required');
            }
        } elseif ($category === 'file_storage') {
            $has_existing_file = $existing && !empty($this->project_vault_model->get_files($existing->id));
            if (!$this->has_uploaded_files() && !$has_existing_file) {
                return _l('project_vault_file_required');
            }
        }

        return '';
    }

    /**
     * Whether the request contains at least one successfully uploaded file.
     */
    private function has_uploaded_files()
    {
        if (empty($_FILES['attachments']['name'])) {
            return false;
        }

        foreach ((array) $_FILES['attachments']['name'] as $i => $name) {
            if ($name !== '' && ($_FILES['attachments']['error'][$i] ?? 1) === UPLOAD_ERR_OK) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decrypt and return a single secret (logged as viewed_secret).
     */
    public function reveal($project_id, $id)
    {
        $this->guard($project_id, true);

        // Any accessor (creator, shared member, admin) may reveal.
        $entry = $this->require_entry($project_id, $id, false);

        $secret = $this->project_vault_model->reveal_secret($id);
        $this->project_vault_model->log_reveal($id, $project_id, $entry->title);

        echo json_encode([
            'success' => true,
            'secret'  => $secret,
            'notes'   => $entry->notes,
        ]);
    }

    /**
     * Delete an entry.
     */
    public function delete($project_id, $id)
    {
        $this->guard($project_id);

        // Only the creator or an admin may delete.
        $this->require_entry($project_id, $id, true, false);
        $this->project_vault_model->delete($id, $project_id);
        set_alert('success', _l('deleted', _l('project_vault_entry')));

        redirect(admin_url('projects/view/' . (int) $project_id . '?group=project_vault'));
    }

    /**
     * Delete a single attachment (AJAX).
     */
    public function delete_file($project_id, $file_id)
    {
        $this->guard($project_id, true);

        $file = $this->project_vault_model->get_file($file_id);

        if ($file && $file->project_id == $project_id) {
            // Removing an attachment is a management action (creator/admin).
            $this->require_entry($project_id, $file->entry_id, true);
            $this->project_vault_model->delete_file($file_id, $project_id);
            echo json_encode(['success' => true]);
            die;
        }

        echo json_encode(['success' => false]);
    }

    /**
     * Stream an attachment after an access check.
     */
    public function download($project_id, $file_id)
    {
        $this->guard($project_id);

        $file = $this->project_vault_model->get_file($file_id);

        if (!$file || $file->project_id != $project_id) {
            show_404();
        }

        // Any accessor of the parent entry may download its files.
        $this->require_entry($project_id, $file->entry_id, false, false);

        $path = $this->project_vault_model->upload_path($project_id) . $file->file_name;

        if (!file_exists($path)) {
            show_404();
        }

        $this->load->helper('download');
        force_download($file->original_file_name, file_get_contents($path));
    }

    /**
     * Render the audit history (whole project, or a single entry).
     */
    public function history($project_id, $id = '')
    {
        $this->guard($project_id, true);

        $data['project_id'] = (int) $project_id;
        $data['entry_id']   = $id !== '' ? (int) $id : null;
        $data['history']    = $this->project_vault_model->get_history($project_id, $data['entry_id']);

        $this->load->view('history', $data);
    }

    /**
     * Upload any posted files into the project's vault directory and record
     * them against the entry.
     */
    private function handle_uploads($entry_id, $project_id)
    {
        if (empty($_FILES['attachments']['name'][0]) && empty($_FILES['attachments']['name'])) {
            return;
        }

        $path = $this->project_vault_model->upload_path($project_id);

        // _maybe_create_upload_path() uses a non-recursive mkdir, so the parent
        // project folder must exist before the vault subfolder can be created.
        _maybe_create_upload_path(PROJECT_ATTACHMENTS_FOLDER . (int) $project_id);
        _maybe_create_upload_path($path);

        $this->load->library('upload');

        $names = $_FILES['attachments']['name'];
        $names = is_array($names) ? $names : [$names];

        foreach ($names as $i => $original_name) {
            if ($original_name === '' || $_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $_FILES['vault_file'] = [
                'name'     => $_FILES['attachments']['name'][$i],
                'type'     => $_FILES['attachments']['type'][$i],
                'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                'error'    => $_FILES['attachments']['error'][$i],
                'size'     => $_FILES['attachments']['size'][$i],
            ];

            $filename = unique_filename($path, $original_name);

            $config = [
                'upload_path'   => $path,
                'file_name'     => $filename,
                'allowed_types' => '*',
                'max_size'      => 0,
            ];

            $this->upload->initialize($config, true);

            if ($this->upload->do_upload('vault_file')) {
                $uploaded = $this->upload->data();
                $this->project_vault_model->add_file(
                    $entry_id,
                    $project_id,
                    $uploaded['file_name'],
                    $original_name,
                    $uploaded['file_type']
                );
            }
        }
    }
}
