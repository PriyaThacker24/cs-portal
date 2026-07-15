<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @property-read Authentication_model $authentication_model
 * @property-read Staff_model $staff_model
 */
class Staff extends AdminController
{
    /* List all staff members */
    public function index()
    {
        if (staff_cant('view', 'staff')) {
            access_denied('staff');
        }
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data('staff');
        }
        $data['staff_members'] = $this->staff_model->get('', ['active' => 1]);
        $data['title']         = _l('staff_members');
        $this->load->view('admin/staff/manage', $data);
    }

    /* Add new staff member or edit existing */
    public function member($id = '')
    {
        if (staff_cant('view', 'staff')) {
            access_denied('staff');
        }
        hooks()->do_action('staff_member_edit_view_profile', $id);

        $this->load->model('departments_model');
        if ($this->input->post()) {
            $data = $this->input->post();
            // Don't do XSS clean here.
            $data['email_signature'] = $this->input->post('email_signature', false);
            $data['email_signature'] = html_entity_decode($data['email_signature']);

            if ($data['email_signature'] == strip_tags($data['email_signature'])) {
                // not contains HTML, add break lines
                $data['email_signature'] = nl2br_save_html($data['email_signature']);
            }

            $data['password'] = $this->input->post('password', false);

            if ($id == '') {
                if (staff_cant('create', 'staff')) {
                    access_denied('staff');
                }
                $id = $this->staff_model->add($data);
                if ($id) {
                    handle_staff_profile_image_upload($id);
                    set_alert('success', _l('added_successfully', _l('staff_member')));
                    redirect(admin_url('staff/member/' . $id));
                }
            } else {
                if (staff_cant('edit', 'staff')) {
                    access_denied('staff');
                }
                handle_staff_profile_image_upload($id);
                $response = $this->staff_model->update($data, $id);
                if (is_array($response)) {
                    if (isset($response['cant_remove_main_admin'])) {
                        set_alert('warning', _l('staff_cant_remove_main_admin'));
                    } elseif (isset($response['cant_remove_yourself_from_admin'])) {
                        set_alert('warning', _l('staff_cant_remove_yourself_from_admin'));
                    }
                } elseif ($response == true) {
                    set_alert('success', _l('updated_successfully', _l('staff_member')));
                }
                redirect(admin_url('staff/member/' . $id));
            }
        }
        if ($id == '') {
            $title = _l('add_new', _l('staff_member'));
        } else {
            $member = $this->staff_model->get($id);
            if (!$member) {
                blank_page('Staff Member Not Found', 'danger');
            }
            $data['member']            = $member;
            $title                     = $member->firstname . ' ' . $member->lastname;
            $data['staff_departments'] = $this->departments_model->get_staff_departments($member->staffid);

            $ts_filter_data = [];
            if ($this->input->get('filter')) {
                if ($this->input->get('range') != 'period') {
                    $ts_filter_data[$this->input->get('range')] = true;
                } else {
                    $ts_filter_data['period-from'] = $this->input->get('period-from');
                    $ts_filter_data['period-to']   = $this->input->get('period-to');
                }
            } else {
                $ts_filter_data['this_month'] = true;
            }

            $data['logged_time'] = $this->staff_model->get_logged_time_data($id, $ts_filter_data);
            $data['timesheets']  = $data['logged_time']['timesheets'];
        }
        $this->load->model('currencies_model');
        $data['base_currency'] = $this->currencies_model->get_base_currency();
        $data['roles']         = $this->roles_model->get();
        $data['user_notes']    = $this->misc_model->get_notes($id, 'staff');
        $data['departments']   = $this->departments_model->get();
        $data['title']         = $title;

        $this->load->view('admin/staff/member', $data);
    }

    /* Get role permission for specific role id */
    public function role_changed($id)
    {
        if (staff_cant('view', 'staff')) {
            ajax_access_denied('staff');
        }

        echo json_encode($this->roles_model->get($id)->permissions);
    }

    public function save_dashboard_widgets_order()
    {
        hooks()->do_action('before_save_dashboard_widgets_order');

        $post_data = $this->input->post();
        foreach ($post_data as $container => $widgets) {
            if ($widgets == 'empty') {
                $post_data[$container] = [];
            }
        }
        update_staff_meta(get_staff_user_id(), 'dashboard_widgets_order', serialize($post_data));
    }

    public function save_dashboard_widgets_visibility()
    {
        hooks()->do_action('before_save_dashboard_widgets_visibility');

        $post_data = $this->input->post();
        update_staff_meta(get_staff_user_id(), 'dashboard_widgets_visibility', serialize($post_data['widgets']));
    }

    public function reset_dashboard()
    {
        update_staff_meta(get_staff_user_id(), 'dashboard_widgets_visibility', null);
        update_staff_meta(get_staff_user_id(), 'dashboard_widgets_order', null);

        redirect(admin_url());
    }

    public function save_hidden_table_columns()
    {
        hooks()->do_action('before_save_hidden_table_columns');
        $data   = $this->input->post();
        $id     = $data['id'];
        $hidden = isset($data['hidden']) ? $data['hidden'] : [];
        update_staff_meta(get_staff_user_id(), 'hidden-columns-' . $id, json_encode($hidden));
    }

    public function change_language($lang = '')
    {
        hooks()->do_action('before_staff_change_language', $lang);

        $this->db->where('staffid', get_staff_user_id());
        $this->db->update(db_prefix() . 'staff', ['default_language' => $lang]);
        
        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    public function timesheets()
    {
        // Check if user has permission to view timesheets
        if (!staff_can('view', 'timesheets') && !staff_can('view_own', 'timesheets') && !is_admin()) {
            access_denied('Timesheets');
        }

        $data['view_all'] = false;
        if (staff_can('view', 'timesheets') && $this->input->get('view') == 'all') {
            $data['staff_members_with_timesheets'] = $this->db->query('SELECT DISTINCT staff_id FROM ' . db_prefix() . 'taskstimers WHERE staff_id !=' . get_staff_user_id())->result_array();
            $data['view_all']                      = true;
        }

        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data('staff_timesheets', ['view_all' => $data['view_all']]);
        }

        if ($data['view_all'] == false) {
            unset($data['view_all']);
        }

        $data['logged_time'] = $this->staff_model->get_logged_time_data(get_staff_user_id());
        $data['title']       = '';
        $this->load->view('admin/staff/timesheets', $data);
    }

    public function delete($id = null)
    {
        // Support both the URL segment (staff/delete/{id}) and a posted id
        $id = $id !== null ? $id : $this->input->post('id');

        if (!is_admin() && is_admin($id)) {
            die('Busted, you can\'t delete administrators');
        }

        if (staff_can('delete',  'staff')) {
            // Soft delete only — deactivate the staff member, keep all data.
            $success = $this->staff_model->soft_delete($id);
            if ($success) {
                set_alert('success', _l('deleted', _l('staff_member')));
            }
        }

        redirect(admin_url('staff'));
    }

    /* When staff edit his profile */
    public function edit_profile()
    {
        hooks()->do_action('edit_logged_in_staff_profile');

        if ($this->input->post()) {
            handle_staff_profile_image_upload();
            $data = $this->input->post();
            // Don't do XSS clean here.
            $data['email_signature'] = $this->input->post('email_signature', false);
            $data['email_signature'] = html_entity_decode($data['email_signature']);

            if ($data['email_signature'] == strip_tags($data['email_signature'])) {
                // not contains HTML, add break lines
                $data['email_signature'] = nl2br_save_html($data['email_signature']);
            }

            $success = $this->staff_model->update_profile($data, get_staff_user_id());

            if ($success) {
                set_alert('success', _l('staff_profile_updated'));
            }

            redirect(admin_url('staff/edit_profile/' . get_staff_user_id()));
        }
        // Prevent caching so after save+redirect the page shows the updated profile image
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $this->output->set_header('Pragma: no-cache');
        $member = $this->staff_model->get(get_staff_user_id());
        $this->load->model('departments_model');
        $data['member']            = $member;
        $data['departments']       = $this->departments_model->get();
        $data['staff_departments'] = $this->departments_model->get_staff_departments($member->staffid);
        $data['title']             = $member->firstname . ' ' . $member->lastname;
        $this->load->view('admin/staff/profile', $data);
    }

    /* Remove staff profile image / ajax */
    public function remove_staff_profile_image($id = '')
    {
        $staff_id = get_staff_user_id();
        if (is_numeric($id) && (staff_can('create',  'staff') || staff_can('edit',  'staff'))) {
            $staff_id = $id;
        }
        hooks()->do_action('before_remove_staff_profile_image');
        $member = $this->staff_model->get($staff_id);
        if (file_exists(get_upload_path_by_type('staff') . $staff_id)) {
            delete_dir(get_upload_path_by_type('staff') . $staff_id);
        }
        $this->db->where('staffid', $staff_id);
        $this->db->update(db_prefix() . 'staff', [
            'profile_image' => null,
        ]);

        if (!is_numeric($id)) {
            redirect(admin_url('staff/edit_profile/' . $staff_id));
        } else {
            redirect(admin_url('staff/member/' . $staff_id));
        }
    }

    /* When staff change his password */
    public function change_password_profile()
    {
        if ($this->input->post()) {
            $response = $this->staff_model->change_password($this->input->post(null, false), get_staff_user_id());
            if (is_array($response) && isset($response[0]['passwordnotmatch'])) {
                set_alert('danger', _l('staff_old_password_incorrect'));
            } else {
                if ($response == true) {
                    set_alert('success', _l('staff_password_changed'));
                } else {
                    set_alert('warning', _l('staff_problem_changing_password'));
                }
            }
            redirect(admin_url('staff/edit_profile'));
        }
    }

    /* View public profile. If id passed view profile by staff id else current user*/
    public function profile($id = '')
    {
        if ($id == '') {
            $id = get_staff_user_id();
        }

        hooks()->do_action('staff_profile_access', $id);

        $data['logged_time'] = $this->staff_model->get_logged_time_data($id);
        $data['staff_p']     = $this->staff_model->get($id);

        if (!$data['staff_p']) {
            blank_page('Staff Member Not Found', 'danger');
        }

        $this->load->model('departments_model');
        $data['staff_departments'] = $this->departments_model->get_staff_departments($data['staff_p']->staffid);
        $data['departments']       = $this->departments_model->get();
        $data['title']             = _l('staff_profile_string') . ' - ' . $data['staff_p']->firstname . ' ' . $data['staff_p']->lastname;
        // notifications
        $total_notifications = total_rows(db_prefix() . 'notifications', [
            'touserid' => get_staff_user_id(),
        ]);
        $data['total_pages'] = ceil($total_notifications / $this->misc_model->get_notifications_limit());
        $this->load->view('admin/staff/myprofile', $data);
    }

    /* Change status to staff active or inactive / ajax */
    public function change_staff_status($id, $status)
    {
        if (staff_can('edit',  'staff')) {
            if ($this->input->is_ajax_request()) {
                $this->staff_model->change_staff_status($id, $status);
            }
        }
    }

    /* Logged in staff notifications*/
    public function notifications()
    {
        $this->load->model('misc_model');
        if ($this->input->post()) {
            $page   = $this->input->post('page');
            $offset = ($page * $this->misc_model->get_notifications_limit());
            $this->db->limit($this->misc_model->get_notifications_limit(), $offset);
            $this->db->where('touserid', get_staff_user_id());
            $this->db->order_by('date', 'desc');
            $notifications = $this->db->get(db_prefix() . 'notifications')->result_array();
            $i             = 0;
            foreach ($notifications as $notification) {
                if (($notification['fromcompany'] == null && $notification['fromuserid'] != 0) || ($notification['fromcompany'] == null && $notification['fromclientid'] != 0)) {
                    if ($notification['fromuserid'] != 0) {
                        $notifications[$i]['profile_image'] = '<a href="' . admin_url('staff/profile/' . $notification['fromuserid']) . '">' . staff_profile_image($notification['fromuserid'], [
                            'staff-profile-image-small',
                            'img-circle',
                            'pull-left',
                        ]) . '</a>';
                    } else {
                        $notifications[$i]['profile_image'] = '<a href="' . admin_url('clients/client/' . $notification['fromclientid']) . '">
                    <img class="client-profile-image-small img-circle pull-left" src="' . contact_profile_image_url($notification['fromclientid']) . '"></a>';
                    }
                } else {
                    $notifications[$i]['profile_image'] = '';
                    $notifications[$i]['full_name']     = '';
                }
                $additional_data = '';
                if (!empty($notification['additional_data'])) {
                    $additional_data = unserialize($notification['additional_data']);
                    $x               = 0;
                    foreach ($additional_data as $data) {
                        if (strpos($data, '<lang>') !== false) {
                            $lang = get_string_between($data, '<lang>', '</lang>');
                            $temp = _l($lang);
                            if (strpos($temp, 'project_status_') !== false) {
                                $status = get_project_status_by_id(strafter($temp, 'project_status_'));
                                $temp   = $status['name'];
                            }
                            $additional_data[$x] = $temp;
                        }
                        $x++;
                    }
                }
                $notifications[$i]['description'] = _l($notification['description'], $additional_data);
                $notifications[$i]['date']        = time_ago($notification['date']);
                $notifications[$i]['full_date']   = _dt($notification['date']);
                $i++;
            } //$notifications as $notification
            echo json_encode($notifications);
            die;
        }
    }

    public function update_two_factor()
    {
        $fail_reason = _l('set_two_factor_authentication_failed');
        if ($this->input->post()) {
            $this->load->library('form_validation');
            $this->form_validation->set_rules('two_factor_auth', _l('two_factor_auth'), 'required');

            if ($this->input->post('two_factor_auth') == 'google') {
                $this->form_validation->set_rules('google_auth_code', _l('google_authentication_code'), 'required');
            }

            if ($this->form_validation->run() !== false) {
                $two_factor_auth_mode = $this->input->post('two_factor_auth');
                $id = get_staff_user_id();
                if ($two_factor_auth_mode == 'google') {
                    $this->load->model('Authentication_model');
                    $secret = $this->input->post('secret');
                    $success = $this->authentication_model->set_google_two_factor($secret);
                    $fail_reason = _l('set_google_two_factor_authentication_failed');

                    // Generate a fresh set of recovery codes on successful enrollment
                    // and expose them once via flashdata so they can be shown/saved.
                    if ($success) {
                        $codes = $this->authentication_model->generate_backup_codes();
                        $this->authentication_model->store_backup_codes($id, $codes);
                        // Keep the plaintext codes in the session (not flashdata) so the recovery
        // codes screen persists across refreshes until the user explicitly clicks
        // "Complete". Completion clears this key; see complete_two_factor_setup().
        $this->session->set_userdata('two_factor_pending_codes', $codes);

                        // 2FA is now enabled; reset the skip allowance so it starts
                        // fresh if the staff ever disables and re-enrolls later.
                        update_staff_meta($id, 'two_factor_reminder_skips', 0);
                    }
                } elseif ($two_factor_auth_mode == 'email') {
                    $this->db->where('staffid', $id);
                    $success = $this->db->update(db_prefix() . 'staff', ['two_factor_auth_enabled' => 1]);
                    // Not a Google-authenticator enrollment; drop any pending codes.
                    $this->session->unset_userdata('two_factor_pending_codes');
                } else {
                    // Disabling 2FA — also clear any stored secret and recovery codes.
                    $this->db->where('staffid', $id);
                    $success = $this->db->update(db_prefix() . 'staff', [
                        'two_factor_auth_enabled' => 0,
                        'google_auth_secret'      => null,
                        'two_factor_backup_codes' => null,
                    ]);
                    // Clear the pending recovery codes screen state as well.
                    $this->session->unset_userdata('two_factor_pending_codes');
                    // Reset the skip allowance so a future re-enrollment starts fresh.
                    update_staff_meta($id, 'two_factor_reminder_skips', 0);
                }
                if ($success) {
                    set_alert('success', _l('set_two_factor_authentication_successful'));
                    redirect(admin_url('staff/edit_profile/' . get_staff_user_id()));
                }
            }
        }
        set_alert('danger', $fail_reason);
        redirect(admin_url('staff/edit_profile/' . get_staff_user_id()));
    }

    public function skip_two_factor_reminder()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
            die;
        }

        $id       = get_staff_user_id();
        $maxSkips = two_factor_reminder_max_skips();
        $used     = (int) get_staff_meta($id, 'two_factor_reminder_skips');

        // The "Enable" button dismisses the reminder without spending a skip
        // (it posts count=0); the "Skip for now" button consumes one skip.
        $consume = $this->input->post('count') !== '0';

        // Consume one skip if any remain. The count is persisted (staff meta) so
        // the 3-skip allowance is enforced across login sessions, not just one.
        $didConsume = false;
        if ($consume && $used < $maxSkips) {
            $used++;
            update_staff_meta($id, 'two_factor_reminder_skips', $used);
            $didConsume = true;
        }

        $left = max(0, $maxSkips - $used);

        // Dismiss the reminder for this login session whenever a skip was actually
        // spent — INCLUDING the last one, so the user can keep working after using
        // their final skip. The mandatory (disabled) state then appears on the NEXT
        // login, where 0 skips remain and the reminder is shown fresh.
        if ($didConsume || ! $consume) {
            $this->session->set_userdata('two_factor_reminder_skipped', true);
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status'        => 'success',
            'skips_left'    => $left,
            'limit_reached' => $left <= 0,
        ]);
        die;
    }

    /**
     * Regenerate the logged-in staff's Two-Factor recovery codes.
     * Only available when Google 2FA is currently enabled.
     */
    public function regenerate_backup_codes()
    {
        $id     = get_staff_user_id();
        $member = $this->staff_model->get($id);

        if (! $member || (int) $member->two_factor_auth_enabled !== 2) {
            set_alert('danger', _l('two_factor_backup_codes_regenerate_failed'));
            redirect(admin_url('staff/edit_profile/' . $id));
        }

        $this->load->model('Authentication_model');
        $codes = $this->authentication_model->generate_backup_codes();
        $this->authentication_model->store_backup_codes($id, $codes);
        // Keep the plaintext codes in the session (not flashdata) so the recovery
        // codes screen persists across refreshes until the user explicitly clicks
        // "Complete". Completion clears this key; see complete_two_factor_setup().
        $this->session->set_userdata('two_factor_pending_codes', $codes);

        set_alert('success', _l('two_factor_backup_codes_regenerated'));
        redirect(admin_url('staff/edit_profile/' . $id));
    }

    /**
     * Finalize 2FA setup after the user has saved their recovery codes.
     *
     * Clears the one-time pending recovery codes from the session so the profile
     * stops showing the recovery codes screen and displays the 2FA summary
     * instead (and keeps showing the summary on refresh). 2FA itself was already
     * enabled during verification; this is the explicit acknowledgement step.
     */
    public function complete_two_factor_setup()
    {
        $id = get_staff_user_id();

        $this->session->unset_userdata('two_factor_pending_codes');

        redirect(admin_url('staff/edit_profile/' . $id . '#two_factor_authentication'));
    }

    /**
     * Ajax: email the current staff member their 2FA recovery (backup) codes.
     *
     * Backup codes are stored hashed and only displayed once, so the plaintext
     * values cannot be read back on the server. The browser therefore posts the
     * set the user is currently viewing; the codes are always sent to the
     * logged-in staff member's OWN email address (the recipient is never taken
     * from the request) and only values matching the generated XXXX-XXXX format
     * are accepted, so the mail server cannot be used to relay arbitrary text.
     */
    public function email_backup_codes()
    {
        if (! $this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        $id     = get_staff_user_id();
        $member = $this->staff_model->get($id);

        if (! $member || empty($member->email) || (int) $member->two_factor_auth_enabled !== 2) {
            echo json_encode([
                'success' => false,
                'message' => _l('two_factor_backup_codes_email_failed'),
            ]);
            die;
        }

        $codes = $this->input->post('codes');
        if (! is_array($codes)) {
            $codes = explode("\n", (string) $codes);
        }
        $codes = array_values(array_filter(
            array_map(function ($code) {
                return trim((string) $code);
            }, $codes),
            function ($code) {
                return preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/i', $code);
            }
        ));

        if (empty($codes)) {
            echo json_encode([
                'success' => false,
                'message' => _l('two_factor_backup_codes_email_failed'),
            ]);
            die;
        }

        // Build the exact same plain-text body used by the Copy / Download actions
        // on the recovery codes screen, so all three stay identical. Wrapped in
        // <pre> so the HTML email preserves the layout (whitespace and newlines).
        $plain = "Concatstring Portal - BACKUP VERIFICATION CODES\n\n\n"
            . "Points to note\n"
            . "--------------\n"
            . "# Each code can be used only once.\n"
            . "# Do not share these codes with anyone.\n"
            . "# If you have used up all your codes or lost them, you can always generate a new set of codes.\n"
            . "# Whenever you generate a new set of codes, the old unused codes will become invalid.\n\n\n"
            . "Generated codes\n"
            . "---------------\n"
            . implode("\n", $codes) . "\n";

        $message = '<pre style="font-family:monospace;font-size:13px;line-height:1.5;">'
            . e($plain)
            . '</pre>';

        $this->load->config('email');
        $this->email->clear(true);
        $this->email->set_newline(config_item('newline'));

        $fromemail = get_option('email_from_address') != '' ? get_option('email_from_address') : get_option('smtp_email');
        $fromname  = get_option('email_from_name') != '' ? get_option('email_from_name') : get_option('companyname');

        $this->email->from($fromemail, $fromname);
        $this->email->to($member->email);
        $this->email->subject(_l('two_factor_backup_codes_email_subject'));
        $this->email->message($message);

        // Skip the queue so the user gets immediate, truthful send feedback.
        $sent = $this->email->send(true);

        echo json_encode([
            'success' => (bool) $sent,
            'message' => $sent ? _l('two_factor_backup_codes_email_sent') : _l('two_factor_backup_codes_email_failed'),
        ]);
        die;
    }

    /**
     * Admin action: reset (disable) Two-Factor Authentication for another staff
     * member who has lost access to their authenticator app. The member can then
     * log in with just their password and re-enroll.
     *
     * @param int $staff_id
     */
    public function reset_two_factor($staff_id)
    {
        if (! is_admin()) {
            access_denied('staff');
        }

        $this->load->model('Authentication_model');
        $this->authentication_model->reset_two_factor($staff_id);

        log_activity('Two Factor Authentication Reset By Admin [Staff ID: ' . $staff_id . ']');
        set_alert('success', _l('two_factor_reset_successful'));

        redirect(admin_url('staff/member/' . $staff_id));
    }

    public function verify_google_two_factor()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
            die;
        }

        if ($this->input->post()) {
            $data = $this->input->post();
            $this->load->model('authentication_model');
            $is_success = $this->authentication_model->is_google_two_factor_code_valid($data['code'],$data['secret']);
            $result = [];

            header('Content-Type: application/json');
            if ($is_success) {
                $result['status'] = 'success';
                $result['message'] = _l('google_2fa_code_valid');;

                echo json_encode($result);
                die;
            }

            $result['status'] = 'failed';
            $result['message'] = _l('google_2fa_code_invalid');;

            echo json_encode($result);
            die;
        }
    }

    public function save_completed_checklist_visibility()
    {
        hooks()->do_action('before_save_completed_checklist_visibility');

        $post_data = $this->input->post();
        if (is_numeric($post_data['task_id'])) {
            update_staff_meta(get_staff_user_id(), 'task-hide-completed-items-'. $post_data['task_id'], $post_data['hideCompleted']);
        }
    }
}
