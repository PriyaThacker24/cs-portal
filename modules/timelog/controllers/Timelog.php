<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Timelog extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('timelog/Timelog_model', 'timelog_model');
    }

    /**
     * Main listing page
     */
    public function index()
    {
        // Check permissions - allow if user can view timesheets
        if (!staff_can('view', 'timesheets') && !staff_can('view_own', 'timesheets') && !is_admin()) {
            access_denied('Timelog');
        }

        $data['title'] = _l('timelog_title');
        
        // Load necessary models
        $this->load->model('projects_model');
        $this->load->model('staff_model');
        
        // Get filter options
        $data['projects'] = $this->projects_model->get('', ['status !=' => 0]);
        $data['staff'] = $this->staff_model->get('', ['active' => 1]);
        
        // Get current week (default)
        $weekStart = $this->input->get('week_start');
        if (empty($weekStart)) {
            $weekStart = date('Y-m-d', strtotime('monday this week'));
        }
        $data['week_start'] = $weekStart;
        $data['week_end'] = date('Y-m-d', strtotime('sunday this week', strtotime($weekStart)));
        
        // Get current filters (default group_by is 'date')
        $data['filters'] = [
            'project_id' => $this->input->get('project_id'),
            'staff_id' => $this->input->get('staff_id'),
            'billing_type' => $this->input->get('billing_type'),
            'group_by' => $this->input->get('group_by') ?: 'date', // Default to 'date'
        ];
        
        // Load view - CodeIgniter will automatically look in module views folder
        $this->load->view('index', $data);
    }

    /**
     * Get timelog data (AJAX)
     */
    public function get_data()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        // Get date range (supports day, week, month, range)
        $dateStart = $this->input->post('date_start') ?: $this->input->post('week_start');
        $dateEnd = $this->input->post('date_end');
        $dateRangeType = $this->input->post('date_range_type') ?: 'week';
        
        if (empty($dateStart)) {
            $dateStart = date('Y-m-d', strtotime('monday this week'));
        }
        
        // If no end date provided, calculate based on range type
        if (empty($dateEnd)) {
            if ($dateRangeType === 'day') {
                $dateEnd = $dateStart;
            } elseif ($dateRangeType === 'week') {
                $dateEnd = date('Y-m-d', strtotime('sunday this week', strtotime($dateStart)));
            } elseif ($dateRangeType === 'month') {
                $dateEnd = date('Y-m-t', strtotime($dateStart));
            } else {
                // Default to week
                $dateEnd = date('Y-m-d', strtotime('sunday this week', strtotime($dateStart)));
            }
        }

        // Get advanced filters JSON
        $advancedFiltersJson = $this->input->post('advanced_filters');
        
        $filters = [
            'project_id' => $this->input->post('project_id'),
            'staff_id' => $this->input->post('staff_id'),
            'billing_type' => $this->input->post('billing_type'),
            'group_by' => $this->input->post('group_by') ?: 'date',
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'date_range_type' => $dateRangeType,
            'advanced_filters' => $advancedFiltersJson, // Pass advanced filters JSON
        ];

        $timelogData = $this->timelog_model->get_timelogs($dateStart, $filters);
        
        // Render the list view
        $data['timelog_data'] = $timelogData;
        $html = $this->load->view('timelog_list', $data, true);
        
        // Calculate week number for display (if week type)
        $weekNumber = null;
        if ($dateRangeType === 'week') {
            $weekNumber = date('W', strtotime($dateStart));
        }
        
        $response = [
            'html' => $html,
            'summary' => isset($timelogData['summary']) ? $timelogData['summary'] : [
                'total_billable_hours' => 0,
                'total_non_billable_hours' => 0,
                'total_hours' => 0,
                'total_records' => 0
            ],
            'week_start' => $dateStart, // Keep for backward compatibility
            'week_end' => $dateEnd,
            'week_number' => $weekNumber,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'date_range_type' => $dateRangeType
        ];
        
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }
    
    /**
     * Get projects assigned to logged-in user (AJAX)
     */
    public function get_user_projects()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        
        $this->load->model('projects_model');
        $staffId = get_staff_user_id();
        
        // Get projects where user is assigned (as member or creator)
        $this->db->select(db_prefix() . 'projects.id, ' . db_prefix() . 'projects.name');
        $this->db->from(db_prefix() . 'projects');
        $this->db->where(db_prefix() . 'projects.status !=', 0); // Active projects only
        $this->db->group_start();
        $this->db->where(db_prefix() . 'projects.addedfrom', $staffId);
        $this->db->or_where(db_prefix() . 'projects.id IN (SELECT project_id FROM ' . db_prefix() . 'project_members WHERE staff_id=' . $this->db->escape_str($staffId) . ')', null, false);
        $this->db->group_end();
        $this->db->order_by(db_prefix() . 'projects.name', 'ASC');
        
        $projects = $this->db->get()->result_array();
        
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(['success' => true, 'projects' => $projects]));
    }
    
    /**
     * Get tasks for selected project assigned to logged-in user (AJAX)
     */
    public function get_project_tasks()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        
        $projectId = $this->input->post('project_id');
        if (empty($projectId)) {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'message' => 'Project ID required']));
            return;
        }
        
        $this->load->model('projects_model');
        $staffId = get_staff_user_id();
        
        // Get tasks for the project assigned to the logged-in user
        $this->db->select(db_prefix() . 'tasks.id, ' . db_prefix() . 'tasks.name');
        $this->db->from(db_prefix() . 'tasks');
        $this->db->where(db_prefix() . 'tasks.rel_id', $projectId);
        $this->db->where(db_prefix() . 'tasks.rel_type', 'project');
        $this->db->where(db_prefix() . 'tasks.id IN (SELECT taskid FROM ' . db_prefix() . 'task_assigned WHERE staffid=' . $this->db->escape_str($staffId) . ')', null, false);
        $this->db->order_by(db_prefix() . 'tasks.name', 'ASC');
        
        $tasks = $this->db->get()->result_array();
        
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(['success' => true, 'tasks' => $tasks]));
    }
    
    /**
     * Get users assigned to selected project (AJAX)
     */
    public function get_project_users()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        
        $projectId = $this->input->post('project_id');
        if (empty($projectId)) {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'message' => 'Project ID required']));
            return;
        }
        
        $this->load->model('projects_model');
        $staffId = get_staff_user_id();
        
        // Get project members
        $members = $this->projects_model->get_project_members($projectId, true);
        
        $users = [];
        foreach ($members as $member) {
            $users[] = [
                'staffid' => $member['staff_id'],
                'full_name' => trim($member['firstname'] . ' ' . $member['lastname']),
                'firstname' => $member['firstname'],
                'lastname' => $member['lastname']
            ];
        }
        
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(['success' => true, 'users' => $users, 'current_user_id' => $staffId]));
    }
    
    /**
     * Submit timelog form (AJAX)
     */
    public function submit_timelog()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        
        try {
            // Load necessary libraries and models
            $this->load->library('form_validation');
            $this->load->model('tasks_model');
            $this->load->model('staff_model');
            
            // Check if general log
            $isGeneralLog = $this->input->post('is_general_log') == '1';
            
            // Validate required fields
            $this->form_validation->set_rules('project_id', _l('project'), 'required|numeric');
            
            if ($isGeneralLog) {
                // For general log, validate task heading instead of task_id
                $this->form_validation->set_rules('task_heading', _l('task_heading'), 'required');
            } else {
                // For regular log, validate task_id
                $this->form_validation->set_rules('task_id', _l('tasks_feedback'), 'required|numeric');
            }
            
            $this->form_validation->set_rules('date', _l('date'), 'required');
            $this->form_validation->set_rules('staff_id', _l('user'), 'required|numeric');
            $this->form_validation->set_rules('daily_log', _l('daily_log'), 'required|callback_validate_time_format');
            
            if ($this->form_validation->run() == false) {
                $errors = [];
                foreach ($this->form_validation->error_array() as $key => $error) {
                    $errors[$key] = $error;
                }
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['success' => false, 'errors' => $errors, 'message' => _l('validation_error')]));
                return;
            }
            
            // Get form data
            $projectId = $this->input->post('project_id');
            $taskId = $this->input->post('task_id');
            $taskHeading = $this->input->post('task_heading');
            $date = $this->input->post('date');
            $staffId = $this->input->post('staff_id');
            $dailyLogTime = $this->input->post('daily_log'); // Format: HH:MM
            $billingType = $this->input->post('billing_type') ?: 'billable';
            
            // Convert time format (HH:MM) to decimal hours
            $timeParts = explode(':', $dailyLogTime);
            $hours = intval($timeParts[0]);
            $minutes = intval($timeParts[1]);
            $dailyLogHours = $hours + ($minutes / 60); // Convert to decimal hours
            $notes = $this->input->post('notes');
            
            // Validate date format and ensure it's not a future date
            $dateParts = explode('/', $date);
            if (count($dateParts) == 3) {
                // Convert dd/mm/yyyy to yyyy-mm-dd
                $dateFormatted = $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0];
            } else {
                $dateFormatted = $date;
            }
            
            $logDate = strtotime($dateFormatted);
            if ($logDate === false) {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['success' => false, 'message' => _l('invalid_date_format')]));
                return;
            }
            
            // Check if date is in the future
            if ($logDate > time()) {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['success' => false, 'message' => _l('time_log_restriction_future_dates')]));
                return;
            }
            
            // For general log, create task first
            if ($isGeneralLog) {
                if (empty($taskHeading) || trim($taskHeading) === '') {
                    $this->output
                        ->set_content_type('application/json')
                        ->set_output(json_encode(['success' => false, 'message' => _l('task_heading') . ' is required']));
                    return;
                }
                
                // Load tasks model
                $this->load->model('tasks_model');
                
                // Create task in the project
                $taskData = [
                    'name' => $taskHeading,
                    'rel_type' => 'project',
                    'rel_id' => $projectId,
                    'startdate' => date('Y-m-d'),
                    'duedate' => date('Y-m-d'),
                    'status' => 1, // Not Started
                    'priority' => 2, // Medium
                    'billable' => ($billingType == 'billable') ? 1 : 0,
                    'assignees' => [$staffId], // Assign to the selected user
                ];
                
                $taskId = $this->tasks_model->add($taskData);
                
                if (!$taskId) {
                    $this->output
                        ->set_content_type('application/json')
                        ->set_output(json_encode(['success' => false, 'message' => _l('error_creating_task')]));
                    return;
                }
            } else {
                // Validate task belongs to project
                $this->db->where('id', $taskId);
                $this->db->where('rel_id', $projectId);
                $this->db->where('rel_type', 'project');
                $task = $this->db->get(db_prefix() . 'tasks')->row();
                
                if (!$task) {
                    $this->output
                        ->set_content_type('application/json')
                        ->set_output(json_encode(['success' => false, 'message' => _l('invalid_task')]));
                    return;
                }
            }
            
            // Validate staff is assigned to project
            $this->db->where('project_id', $projectId);
            $this->db->where('staff_id', $staffId);
            $member = $this->db->get(db_prefix() . 'project_members')->row();
            
            if (!$member && $projectId) {
                // Check if user is project creator
                $this->db->where('id', $projectId);
                $this->db->where('addedfrom', $staffId);
                $project = $this->db->get(db_prefix() . 'projects')->row();
                
                if (!$project) {
                    $this->output
                        ->set_content_type('application/json')
                        ->set_output(json_encode(['success' => false, 'message' => _l('staff_not_assigned_to_project')]));
                    return;
                }
            }
            
            // Convert daily log hours to start_time and end_time
            // Default: start at 9:00 AM on the selected date, end after the specified hours
            $startHour = 9; // 9 AM default
            $startMinute = 0;
            
            // Calculate start_time (timestamp for the selected date at startHour:startMinute)
            $startTime = mktime($startHour, $startMinute, 0, date('n', $logDate), date('j', $logDate), date('Y', $logDate));
            
            // Calculate end_time (start_time + hours in seconds)
            $endTime = $startTime + ($dailyLogHours * 3600);
            
            // Get hourly rate for the staff member
            $this->db->select('hourly_rate');
            $this->db->where('staffid', $staffId);
            $staff = $this->db->get(db_prefix() . 'staff')->row();
            $hourlyRate = $staff ? ($staff->hourly_rate ?: 0) : 0;
            
            // Prepare billing type
            $billType = ($billingType == 'non_billable') ? 'non_billable' : 'billable';
            
            // Prepare note
            $noteContent = !empty($notes) ? nl2br(e($notes)) : null;
            
            // Insert into tbltaskstimers
            $insertData = [
                'start_time'  => $startTime,
                'end_time'    => $endTime,
                'staff_id'    => $staffId,
                'task_id'     => $taskId, // Created task_id for general log, existing task_id for regular log
                'hourly_rate' => $hourlyRate,
                'note'        => $noteContent,
                'bill_type'   => $billType,
                'status'      => 'pending',
            ];
            
            $this->db->insert(db_prefix() . 'taskstimers', $insertData);
            
            if ($this->db->affected_rows() > 0) {
                $insertId = $this->db->insert_id();
                
                // Reload timelog data for current week
                $weekStart = date('Y-m-d', strtotime('monday this week', $logDate));
                
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => true,
                        'message' => _l('timelog_added_successfully'),
                        'insert_id' => $insertId,
                        'week_start' => $weekStart
                    ]));
            } else {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['success' => false, 'message' => _l('error_adding_timelog')]));
            }
        } catch (Exception $e) {
            // Log the error for debugging
            log_message('error', 'Timelog submit error: ' . $e->getMessage());
            
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => _l('error_adding_timelog') . ': ' . $e->getMessage()
                ]));
        }
    }
    
    /**
     * Custom validation callback for time format (HH:MM)
     */
    public function validate_time_format($time)
    {
        if (empty($time)) {
            $this->form_validation->set_message('validate_time_format', _l('daily_log') . ' is required');
            return false;
        }
        
        // Check format HH:MM
        if (!preg_match('/^([0-9]{1,2}):([0-5][0-9])$/', $time)) {
            $this->form_validation->set_message('validate_time_format', _l('invalid_time_format'));
            return false;
        }
        
        // Check that time is not 00:00
        $parts = explode(':', $time);
        $hours = intval($parts[0]);
        $minutes = intval($parts[1]);
        
        if ($hours == 0 && $minutes == 0) {
            $this->form_validation->set_message('validate_time_format', _l('daily_log') . ' must be greater than 00:00');
            return false;
        }
        
        return true;
    }

    /**
     * Update timelog approval status
     */
    public function update_status()
    {
        if (!$this->input->is_ajax_request()) {
            access_denied('Timelog');
        }

        // Check permissions
        if (!staff_can('approve', 'timesheets') && !staff_can('reject', 'timesheets') && !is_admin()) {
            echo json_encode([
                'success' => false,
                'message' => _l('access_denied')
            ]);
            exit;
        }

        if ($this->input->post()) {
            $timelog_id = $this->input->post('timelog_id');
            $status = $this->input->post('status');
            
            if (!in_array($status, ['pending', 'approved', 'rejected'])) {
                echo json_encode([
                    'success' => false,
                    'message' => _l('invalid_status')
                ]);
                exit;
            }
            
            $this->db->select('id, task_id, staff_id');
            $this->db->where('id', $timelog_id);
            $timelog = $this->db->get(db_prefix() . 'taskstimers')->row();
            
            if (!$timelog) {
                echo json_encode([
                    'success' => false,
                    'message' => _l('timesheet_not_found')
                ]);
                exit;
            }
            
            $this->db->where('id', $timelog_id);
            $update_result = $this->db->update(db_prefix() . 'taskstimers', ['status' => $status]);
            
            $status_label = '';
            switch ($status) {
                case 'approved':
                    $status_label = _l('approved');
                    break;
                case 'rejected':
                    $status_label = _l('rejected');
                    break;
                default:
                    $status_label = _l('pending');
                    break;
            }
            
            if ($update_result) {
                echo json_encode([
                    'success' => true,
                    'message' => _l('timesheet_status_updated_to', $status_label)
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => _l('failed_to_update_timesheet')
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => _l('invalid_request')
            ]);
        }
    }
}
