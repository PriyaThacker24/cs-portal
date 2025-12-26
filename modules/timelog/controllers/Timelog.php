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
        // Check permissions - allow if user can view timesheets or view tasks
        if (!staff_can('view-timesheets', 'reports') && !staff_can('view', 'tasks') && !is_admin()) {
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

        $weekStart = $this->input->post('week_start');
        if (empty($weekStart)) {
            $weekStart = date('Y-m-d', strtotime('monday this week'));
        }

        $filters = [
            'project_id' => $this->input->post('project_id'),
            'staff_id' => $this->input->post('staff_id'),
            'billing_type' => $this->input->post('billing_type'),
            'group_by' => $this->input->post('group_by') ?: 'date',
        ];

        $timelogData = $this->timelog_model->get_timelogs($weekStart, $filters);
        
        // Render the list view
        $data['timelog_data'] = $timelogData;
        $html = $this->load->view('timelog_list', $data, true);
        
        $response = [
            'html' => $html,
            'summary' => isset($timelogData['summary']) ? $timelogData['summary'] : [
                'total_billable_hours' => 0,
                'total_non_billable_hours' => 0,
                'total_hours' => 0,
                'total_records' => 0
            ],
            'week_start' => isset($timelogData['week_start']) ? $timelogData['week_start'] : $weekStart,
            'week_end' => isset($timelogData['week_end']) ? $timelogData['week_end'] : date('Y-m-d', strtotime('sunday this week', strtotime($weekStart))),
            'week_number' => isset($timelogData['week_number']) ? $timelogData['week_number'] : date('W', strtotime($weekStart))
        ];
        
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }
}

