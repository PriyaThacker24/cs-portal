<?php

defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Function used to get related data based on rel_id and rel_type
 * Eq in the tasks section there is field where this task is related eq invoice with number INV-0005
 * @param  string $type
 * @param  string $rel_id
 * @param  array $extra
 * @return mixed
 */
function get_relation_data($type, $rel_id = '', $extra = [])
{
    $CI = & get_instance();
    $q  = '';
    if ($CI->input->post('q')) {
        $q = $CI->input->post('q');
        $q = trim($q);
    }

    $data = [];
    if ($type == 'customer' || $type == 'customers') {
        $where_clients = '';

        if ($q && !$rel_id) {
            $where_clients .= '(company LIKE "%' . $CI->db->escape_like_str($q) . '%" ESCAPE \'!\' OR CONCAT(firstname, " ", lastname) LIKE "%' . $CI->db->escape_like_str($q) . '%" ESCAPE \'!\' OR email LIKE "%' . $CI->db->escape_like_str($q) . '%" ESCAPE \'!\') AND ' . db_prefix() . 'clients.active = 1';
        }

        $data = $CI->clients_model->get($rel_id, $where_clients);
    } elseif ($type == 'contact' || $type == 'contacts') {
        if ($rel_id != '') {
            $data = $CI->clients_model->get_contact($rel_id);
        } else {
            $where_contacts = db_prefix() . 'contacts.active=1';
            if (isset($extra['client_id']) && $extra['client_id'] != '') {
                $where_contacts .= ' AND '. db_prefix() . 'contacts.userid='. $extra['client_id'];
            }

            if ($CI->input->post('tickets_contacts')) {
                if (staff_cant('view', 'customers') && get_option('staff_members_open_tickets_to_all_contacts') == 0) {
                    $where_contacts .= ' AND ' . db_prefix() . 'contacts.userid IN (SELECT customer_id FROM ' . db_prefix() . 'customer_admins WHERE staff_id=' . get_staff_user_id() . ')';
                }
            }
            if ($CI->input->post('contact_userid')) {
                $where_contacts .= ' AND ' . db_prefix() . 'contacts.userid=' . $CI->db->escape_str($CI->input->post('contact_userid'));
            }
            $search = $CI->misc_model->_search_contacts($q, 0, $where_contacts);
            $data   = $search['result'];
        }
    } elseif ($type == 'invoice') {
        if ($rel_id != '') {
            $CI->load->model('invoices_model');
            $data = $CI->invoices_model->get($rel_id);
        } else {
            $search = $CI->misc_model->_search_invoices($q);
            $data   = $search['result'];
        }
    } elseif ($type == 'credit_note') {
        if ($rel_id != '') {
            $CI->load->model('credit_notes_model');
            $data = $CI->credit_notes_model->get($rel_id);
        } else {
            $search = $CI->misc_model->_search_credit_notes($q);
            $data   = $search['result'];
        }
    } elseif ($type == 'estimate') {
        if ($rel_id != '') {
            $CI->load->model('estimates_model');
            $data = $CI->estimates_model->get($rel_id);
        } else {
            $search = $CI->misc_model->_search_estimates($q);
            $data   = $search['result'];
        }
    } elseif ($type == 'contract' || $type == 'contracts') {
        $CI->load->model('contracts_model');

        if ($rel_id != '') {
            $CI->load->model('contracts_model');
            $data = $CI->contracts_model->get($rel_id);
        } else {
            $search = $CI->misc_model->_search_contracts($q);
            $data   = $search['result'];
        }
    } elseif ($type == 'ticket') {
        if ($rel_id != '') {
            $CI->load->model('tickets_model');
            $data = $CI->tickets_model->get($rel_id);
        } else {
            $search = $CI->misc_model->_search_tickets($q);
            $data   = $search['result'];
        }
    } elseif ($type == 'expense' || $type == 'expenses') {
        if ($rel_id != '') {
            $CI->load->model('expenses_model');
            $data = $CI->expenses_model->get($rel_id);
        } else {
            $search = $CI->misc_model->_search_expenses($q);
            $data   = $search['result'];
        }
    } elseif ($type == 'lead' || $type == 'leads') {
        if ($rel_id != '') {
            $CI->load->model('leads_model');
            $data = $CI->leads_model->get($rel_id);
        } else {
            $search = $CI->misc_model->_search_leads($q, 0, [
                'junk' => 0,
                ]);
            $data = $search['result'];
        }
    } elseif ($type == 'proposal') {
        if ($rel_id != '') {
            $CI->load->model('proposals_model');
            $data = $CI->proposals_model->get($rel_id);
        } else {
            $search = $CI->misc_model->_search_proposals($q);
            $data   = $search['result'];
        }
    } elseif ($type == 'project') {
        if ($rel_id != '') {
            $CI->load->model('projects_model');
            $data = $CI->projects_model->get($rel_id);
        } else {
            $where_projects = '';
            if ($CI->input->post('customer_id')) {
                $where_projects .= 'clientid=' . $CI->db->escape_str($CI->input->post('customer_id'));
            }
            $search = $CI->misc_model->_search_projects($q, 0, $where_projects);
            $data   = $search['result'];
        }
    } elseif ($type == 'staff') {
        if ($rel_id != '') {
            $CI->load->model('staff_model');
            $data = $CI->staff_model->get($rel_id);
        } else {
            $search = $CI->misc_model->_search_staff($q);
            $data   = $search['result'];
        }
    } elseif ($type == 'tasks' || $type == 'task') {
        // Tasks only have relation with custom fields when searching on top
        if ($rel_id != '') {
            $data = $CI->tasks_model->get($rel_id);
        }
    }

    $data = hooks()->apply_filters('get_relation_data', $data, compact('type', 'rel_id', 'extra'));

    return $data;
}
/**
 * Ger relation values eq invoice number or project name etc based on passed relation parsed results
 * from function get_relation_data
 * $relation can be object or array
 * @param  mixed $relation
 * @param  string $type
 * @return mixed
 */
function get_relation_values($relation, $type)
{
    if ($relation == '') {
        return [
            'name'      => '',
            'id'        => '',
            'link'      => '',
            'addedfrom' => 0,
            'subtext'   => '',
            ];
    }

    $addedfrom = 0;
    $name      = '';
    $id        = '';
    $link      = '';
    $subtext   = '';

    if ($type == 'customer' || $type == 'customers') {
        if (is_array($relation)) {
            $id   = $relation['userid'];
            $name = $relation['company'];
        } else {
            $id   = $relation->userid;
            $name = $relation->company;
        }
        $link = admin_url('clients/client/' . $id);
    } elseif ($type == 'contact' || $type == 'contacts') {
        if (is_array($relation)) {
            $userid = isset($relation['userid']) ? $relation['userid'] : $relation['relid'];
            $id     = $relation['id'];
            $name   = $relation['firstname'] . ' ' . $relation['lastname'];
        } else {
            $userid = $relation->userid;
            $id     = $relation->id;
            $name   = $relation->firstname . ' ' . $relation->lastname;
        }
        $subtext = get_company_name($userid);
        $link    = admin_url('clients/client/' . $userid . '?contactid=' . $id);
    } elseif ($type == 'invoice') {
        if (is_array($relation)) {
            $id        = $relation['id'];
            $addedfrom = $relation['addedfrom'];
        } else {
            $id        = $relation->id;
            $addedfrom = $relation->addedfrom;
        }
        $name = format_invoice_number($id);
        $link = admin_url('invoices/list_invoices/' . $id);
    } elseif ($type == 'credit_note') {
        if (is_array($relation)) {
            $id        = $relation['id'];
            $addedfrom = $relation['addedfrom'];
        } else {
            $id        = $relation->id;
            $addedfrom = $relation->addedfrom;
        }
        $name = format_credit_note_number($id);
        $link = admin_url('credit_notes/list_credit_notes/' . $id);
    } elseif ($type == 'estimate') {
        if (is_array($relation)) {
            $id        = $relation['estimateid'];
            $addedfrom = $relation['addedfrom'];
        } else {
            $id        = $relation->id;
            $addedfrom = $relation->addedfrom;
        }
        $name = format_estimate_number($id);
        $link = admin_url('estimates/list_estimates/' . $id);
    } elseif ($type == 'contract' || $type == 'contracts') {
        if (is_array($relation)) {
            $id        = $relation['id'];
            $name      = $relation['subject'];
            $addedfrom = $relation['addedfrom'];
        } else {
            $id        = $relation->id;
            $name      = $relation->subject;
            $addedfrom = $relation->addedfrom;
        }
        $link = admin_url('contracts/contract/' . $id);
    } elseif ($type == 'ticket') {
        if (is_array($relation)) {
            $id   = $relation['ticketid'];
            $name = '#' . $relation['ticketid'];
            $name .= ' - ' . $relation['subject'];
        } else {
            $id   = $relation->ticketid;
            $name = '#' . $relation->ticketid;
            $name .= ' - ' . $relation->subject;
        }
        $link = admin_url('tickets/ticket/' . $id);
    } elseif ($type == 'expense' || $type == 'expenses') {
        if (is_array($relation)) {
            $id        = $relation['expenseid'];
            $name      = $relation['category_name'];
            $addedfrom = $relation['addedfrom'];

            if (!empty($relation['expense_name'])) {
                $name .= ' (' . $relation['expense_name'] . ')';
            }
        } else {
            $id        = $relation->expenseid;
            $name      = $relation->category_name;
            $addedfrom = $relation->addedfrom;
            if (!empty($relation->expense_name)) {
                $name .= ' (' . $relation->expense_name . ')';
            }
        }
        $link = admin_url('expenses/list_expenses/' . $id);
    } elseif ($type == 'lead' || $type == 'leads') {
        if (is_array($relation)) {
            $id   = $relation['id'];
            $name = $relation['name'];
            if ($relation['email'] != '') {
                $name .= ' - ' . $relation['email'];
            }
        } else {
            $id   = $relation->id;
            $name = $relation->name;
            if ($relation->email != '') {
                $name .= ' - ' . $relation->email;
            }
        }
        $link = admin_url('leads/index/' . $id);
    } elseif ($type == 'proposal') {
        if (is_array($relation)) {
            $id        = $relation['id'];
            $addedfrom = $relation['addedfrom'];
            if (!empty($relation['subject'])) {
                $name .= ' - ' . $relation['subject'];
            }
        } else {
            $id        = $relation->id;
            $addedfrom = $relation->addedfrom;
            if (!empty($relation->subject)) {
                $name .= ' - ' . $relation->subject;
            }
        }
        $name = format_proposal_number($id);
        $link = admin_url('proposals/list_proposals/' . $id);
    } elseif ($type == 'tasks' || $type == 'task') {
        if (is_array($relation)) {
            $id   = $relation['id'];
            $name = $relation['name'];
        } else {
            $id   = $relation->id;
            $name = $relation->name;
        }
        $link = admin_url('tasks/view/' . $id);
    } elseif ($type == 'staff') {
        if (is_array($relation)) {
            $id   = $relation['staffid'];
            $name = $relation['firstname'] . ' ' . $relation['lastname'];
        } else {
            $id   = $relation->staffid;
            $name = $relation->firstname . ' ' . $relation->lastname;
        }
        $link = admin_url('profile/' . $id);
    } elseif ($type == 'project') {
        if (is_array($relation)) {
            $id       = $relation['id'];
            $name     = $relation['name'];
            $clientId = $relation['clientid'];
        } else {
            $id       = $relation->id;
            $name     = $relation->name;
            $clientId = $relation->clientid;
        }

        $name = '#' . $id . ' - ' . $name . ' - ' . get_company_name($clientId);

        $link = admin_url('projects/view/' . $id);
    }

    return hooks()->apply_filters('relation_values', [
        'id'        => $id,
        'name'      => $name,
        'link'      => $link,
        'addedfrom' => $addedfrom,
        'subtext'   => $subtext,
        'type'      => $type,
        'relation'  => $relation,
    ]);
}

/**
 * Function used to render <option> for relation
 * This function will do all the necessary checking and return the options
 * @param  mixed $data
 * @param  string $type   rel_type
 * @param  string $rel_id rel_id
 * @return string
 */
function init_relation_options($data, $type, $rel_id = '', $extra = [])
{
    $_data = [];

    $has_permission_projects_view  = staff_can('view',  'projects');
    $has_permission_customers_view = staff_can('view',  'customers');
    $has_permission_contracts_view = staff_can('view',  'contracts');
    $has_permission_invoices_view  = staff_can('view',  'invoices');
    $has_permission_estimates_view = staff_can('view',  'estimates');
    $has_permission_expenses_view  = staff_can('view',  'expenses');
    $has_permission_proposals_view = staff_can('view',  'proposals');
    $is_admin                      = is_admin();
    $CI                            = & get_instance();
    $CI->load->model('projects_model');

    foreach ($data as $relation) {
        $relation_values = get_relation_values($relation, $type);
        if ($type == 'project') {
            // Special handling for task form project dropdown (Add Task and Edit Task)
            // Check if this is being called from task form (when adding/editing a task)
            $is_task_form_context = false;
            $is_edit_task = false;
            
            // Check $extra parameter first (most reliable for AJAX requests)
            if ((isset($extra['task_form']) && $extra['task_form'] === true) || $CI->input->post('task_form') == '1') {
                $is_task_form_context = true;
                // Check if it's Edit Task form
                if ((isset($extra['is_edit_task']) && $extra['is_edit_task'] === true) || $CI->input->post('is_edit_task') == '1') {
                    $is_edit_task = true;
                }
            } else {
                // Fallback: Check URI segments and HTTP_REFERER for non-AJAX contexts
                $is_task_form_context = (
                    $CI->uri->segment(1) == 'admin' && $CI->uri->segment(2) == 'tasks' && $CI->uri->segment(3) == 'task'
                    || $CI->input->post('rel_type') == 'project'
                    || ($CI->input->get('rel_type') == 'project' && $CI->uri->segment(2) == 'tasks')
                );
                
                if ($is_task_form_context) {
                    // Check HTTP_REFERER to see if it contains a task ID
                    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
                    
                    // Check if referer contains /tasks/task/ followed by a number (Edit Task)
                    if (preg_match('#/tasks/task/(\d+)#', $referer, $matches)) {
                        $is_edit_task = true;
                    } else {
                        // Also check URI segment 4 as fallback
                        $task_id_segment = $CI->uri->segment(4);
                        if (!empty($task_id_segment) && is_numeric($task_id_segment)) {
                            $is_edit_task = true;
                        }
                    }
                }
            }
            
            if ($is_task_form_context) {
                
                // Check staff-level permissions first (priority logic)
                $has_staff_task_create = staff_can('create', 'tasks');
                $has_staff_task_edit = staff_can('edit', 'tasks');
                
                // If user has staff-level permission, show all projects (no filtering)
                if ($is_edit_task && $has_staff_task_edit) {
                    // User has staff-level edit permission - show all projects
                    // Don't skip, continue to add this project
                } elseif (!$is_edit_task && $has_staff_task_create) {
                    // User has staff-level create permission - show all projects
                    // Don't skip, continue to add this project
                } else {
                    // No staff-level permission: filter by project-level permission
                    $project_id = $relation_values['id'];
                    $user_id = get_staff_user_id();
                    
                    // Check if user is project admin (project creator has full access)
                    $project = $CI->projects_model->get($project_id);
                    $is_project_admin = $project && $project->addedfrom == $user_id;
                    
                    if ($is_project_admin) {
                        // Project admin has full access - show this project
                        // Don't skip, continue to add this project
                    } else {
                        // Check project-level permission
                        if ($is_edit_task) {
                            // Edit Task form: check task_edit permission
                            $has_project_permission = $CI->projects_model->hasProjectPermission($user_id, $project_id, 'task_edit');
                        } else {
                            // Add Task form: check task_create permission
                            $has_project_permission = $CI->projects_model->hasProjectPermission($user_id, $project_id, 'task_create');
                        }
                        
                        if (!$has_project_permission) {
                            // User doesn't have required project-level permission - skip this project
                            continue;
                        }
                    }
                }
                // If we reach here, user has permission - add this project (don't skip)
            } else {
                // Regular project visibility check (for other contexts)
                if (!$has_permission_projects_view) {
                    if (!$CI->projects_model->is_member($relation_values['id']) && $rel_id != $relation_values['id']) {
                        continue;
                    }
                }
            }
        } elseif ($type == 'lead') {
            if (staff_cant('view', 'leads')) {
                if ($relation['assigned'] != get_staff_user_id() && $relation['addedfrom'] != get_staff_user_id() && $relation['is_public'] != 1 && $rel_id != $relation_values['id']) {
                    continue;
                }
            }
        } elseif ($type == 'customer') {
            if (!$has_permission_customers_view && !have_assigned_customers() && $rel_id != $relation_values['id']) {
                continue;
            } elseif (have_assigned_customers() && $rel_id != $relation_values['id'] && !$has_permission_customers_view) {
                if (!is_customer_admin($relation_values['id'])) {
                    continue;
                }
            }
        } elseif ($type == 'contract') {
            if (!$has_permission_contracts_view && $rel_id != $relation_values['id'] && $relation_values['addedfrom'] != get_staff_user_id()) {
                continue;
            }
        } elseif ($type == 'invoice') {
            if (!$has_permission_invoices_view && $rel_id != $relation_values['id'] && $relation_values['addedfrom'] != get_staff_user_id()) {
                continue;
            }
        } elseif ($type == 'estimate') {
            if (!$has_permission_estimates_view && $rel_id != $relation_values['id'] && $relation_values['addedfrom'] != get_staff_user_id()) {
                continue;
            }
        } elseif ($type == 'expense') {
            if (!$has_permission_expenses_view && $rel_id != $relation_values['id'] && $relation_values['addedfrom'] != get_staff_user_id()) {
                continue;
            }
        } elseif ($type == 'proposal') {
            if (!$has_permission_proposals_view && $rel_id != $relation_values['id'] && $relation_values['addedfrom'] != get_staff_user_id()) {
                continue;
            }
        }

        $_data[] = $relation_values;
        //  echo '<option value="' . $relation_values['id'] . '"' . $selected . '>' . $relation_values['name'] . '</option>';
    }

    $_data = hooks()->apply_filters('init_relation_options', $_data, compact('data', 'type', 'rel_id'));

    return $_data;
}
