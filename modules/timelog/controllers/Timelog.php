<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Timelog extends AdminController
{
    /**
     * Identifier/view used to scope saved timelog filters in the generic
     * filters tables (shared with the Filters_model used by other modules).
     */
    const FILTER_IDENTIFIER = 'timelog';
    const FILTER_VIEW       = 'timelog';

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

        // Saved filters for the current staff member (personal + shared)
        $this->load->model('filters_model');
        $data['saved_filters'] = $this->filters_model->get_for_staff(
            self::FILTER_IDENTIFIER,
            self::FILTER_VIEW,
            get_staff_user_id()
        );

        // Load view - CodeIgniter will automatically look in module views folder
        $this->load->view('index', $data);
    }

    /**
     * Persist a new saved filter for the timelog listing.
     * Stores the Zoho-style filter payload as the filter "builder".
     */
    public function save_filter()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $name  = trim((string) $this->input->post('name'));
        $rules = $this->input->post('rules');

        if ($name === '' || empty($rules)) {
            echo json_encode(['success' => false, 'message' => _l('filter_name') . ' / ' . _l('filter') . ' required']);
            return;
        }

        $this->load->model('filters_model');

        $filter = $this->filters_model->create([
            'name'       => $name,
            'identifier' => self::FILTER_IDENTIFIER,
            'builder'    => json_decode($rules, true),
            'is_shared'  => filter_var($this->input->post('is_shared'), FILTER_VALIDATE_BOOL),
            'is_default' => filter_var($this->input->post('is_default'), FILTER_VALIDATE_BOOL),
            'view'       => self::FILTER_VIEW,
            'staff_id'   => get_staff_user_id(),
        ]);

        echo json_encode(['success' => true, 'filter' => $filter]);
    }

    /**
     * Update an existing saved filter (rename, sharing, default, and
     * optionally overwrite its rules with the currently selected ones).
     */
    public function update_filter($id)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $this->load->model('filters_model');
        $staffId = get_staff_user_id();

        $filter = $this->db->where('id', $id)->get('filters')->row_array();

        if (!$filter || $filter['identifier'] !== self::FILTER_IDENTIFIER) {
            echo json_encode(['success' => false, 'message' => _l('not_found')]);
            return;
        }

        if (!is_admin() && $filter['staff_id'] != $staffId) {
            ajax_access_denied();
        }

        $rules = $this->input->post('rules');
        // When no rules are posted (rename/share/default only) keep the stored ones.
        $builder = !empty($rules) ? json_decode($rules, true) : json_decode($filter['builder'], true);

        $name = trim((string) $this->input->post('name'));

        $updated = $this->filters_model->update($id, [
            'name'       => $name !== '' ? $name : $filter['name'],
            'is_shared'  => filter_var($this->input->post('is_shared'), FILTER_VALIDATE_BOOL),
            'is_default' => filter_var($this->input->post('is_default'), FILTER_VALIDATE_BOOL),
            'view'       => self::FILTER_VIEW,
            'builder'    => $builder,
        ], $staffId);

        echo json_encode(['success' => true, 'filter' => $updated]);
    }

    /**
     * Delete a saved timelog filter (owner or admin only).
     */
    public function delete_filter($id)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $this->load->model('filters_model');
        $filter = $this->db->where('id', $id)->get('filters')->row_array();

        if (!$filter || $filter['identifier'] !== self::FILTER_IDENTIFIER) {
            echo json_encode(['success' => false, 'message' => _l('not_found')]);
            return;
        }

        if (!is_admin() && $filter['staff_id'] != get_staff_user_id()) {
            ajax_access_denied();
        }

        $this->filters_model->delete($id);

        echo json_encode(['success' => true]);
    }

    /**
     * Toggle a saved filter as the current staff member's default for the
     * timelog listing. Default is always per-account.
     */
    public function toggle_default_filter($id)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $this->load->model('filters_model');
        $staffId = get_staff_user_id();

        // Only one default per account/view — clear any existing one first.
        $isDefault = $this->filters_model->is_default($id, self::FILTER_IDENTIFIER, self::FILTER_VIEW, $staffId);
        $this->filters_model->delete_default(self::FILTER_IDENTIFIER, self::FILTER_VIEW, $staffId);

        if (!$isDefault) {
            $this->filters_model->mark_as_default($id, self::FILTER_IDENTIFIER, self::FILTER_VIEW, $staffId);
        }

        echo json_encode(['success' => true, 'is_default' => !$isDefault]);
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

        $currentStaffId = get_staff_user_id();
        $isGlobal       = is_admin() || staff_can('view', 'timesheets');

        $filters = [
            'project_id'               => $this->input->post('project_id'),
            'staff_id'                 => $this->input->post('staff_id'),
            'billing_type'             => $this->input->post('billing_type'),
            'group_by'                 => $this->input->post('group_by') ?: 'date',
            'date_start'               => $dateStart,
            'date_end'                 => $dateEnd,
            'date_range_type'          => $dateRangeType,
            'advanced_filters'         => $advancedFiltersJson,
            // Permission filters — non-admin/non-global users only see their own
            // timelogs from their assigned projects.
            'own_staff_id'             => $isGlobal ? null : $currentStaffId,
            'assigned_projects_staff_id' => is_admin() ? null : $currentStaffId,
        ];

        try {
            // Log the query parameters for debugging
            log_message('debug', 'Timelog get_data called with date_start: ' . $dateStart . ', date_end: ' . $dateEnd . ', filters: ' . json_encode($filters));
            
            $timelogData = $this->timelog_model->get_timelogs($dateStart, $filters);
            
            // Log the result count for debugging
            $totalRecords = isset($timelogData['summary']['total_records']) ? $timelogData['summary']['total_records'] : 0;
            $groupsCount = isset($timelogData['groups']) ? count($timelogData['groups']) : 0;
            log_message('debug', 'Timelog query returned ' . $totalRecords . ' records in ' . $groupsCount . ' groups');
            
            // Ensure timelogData has required structure
            if (!isset($timelogData['groups'])) {
                $timelogData['groups'] = [];
            }
            if (!isset($timelogData['summary'])) {
                $timelogData['summary'] = [
                    'total_billable_hours' => 0,
                    'total_non_billable_hours' => 0,
                    'total_hours' => 0,
                    'total_records' => 0
                ];
            }
            
            // Render the list view
            $data['timelog_data'] = $timelogData;
            $html = $this->load->view('timelog_list', $data, true);
            
            // Ensure HTML is not empty (should always return something from view)
            if (empty($html)) {
                $html = '<div class="timelog-empty-state text-center" style="padding: 40px;"><i class="fa fa-clock-o fa-3x" style="color: #ccc;"></i><p style="margin-top: 20px; color: #999;">' . _l('no_timelogs_found') . '</p></div>';
            }
            
            // Calculate week number for display (if week type)
            $weekNumber = null;
            if ($dateRangeType === 'week') {
                $weekNumber = date('W', strtotime($dateStart));
            }
            
            $response = [
                'html' => $html,
                'summary' => $timelogData['summary'],
                'week_start' => $dateStart,
                'week_end' => $dateEnd,
                'week_number' => $weekNumber,
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'date_range_type' => $dateRangeType
            ];
            
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($response));
        } catch (Exception $e) {
            // Log error
            log_message('error', 'Timelog get_data error: ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            
            // Return error response with empty state
            $errorHtml = '<div class="alert alert-danger">Error loading timelogs. Please refresh the page.</div>';
            
            $response = [
                'html' => $errorHtml,
                'summary' => [
                    'total_billable_hours' => 0,
                    'total_non_billable_hours' => 0,
                    'total_hours' => 0,
                    'total_records' => 0
                ],
                'week_start' => $dateStart,
                'week_end' => $dateEnd,
                'week_number' => null,
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'date_range_type' => $dateRangeType,
                'error' => true,
                'error_message' => $e->getMessage()
            ];
            
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($response));
        }
    }
    
    /**
     * Export the currently filtered timelogs.
     *
     * Mirrors the filtering/permission logic of get_data() but reads the
     * filters from the query string (so it can be triggered as a normal file
     * download). Supports CSV, XLSX and PDF.
     *
     * @param string $format csv|xlsx|pdf
     */
    public function export($format = 'csv')
    {
        // Same access rule as the listing page.
        if (!staff_can('view', 'timesheets') && !staff_can('view_own', 'timesheets') && !is_admin()) {
            access_denied('Timelog');
        }

        $format  = in_array($format, ['csv', 'xlsx', 'pdf'], true) ? $format : 'csv';
        $dataset = $this->build_export_dataset();

        $filenameBase = 'timelog-' . $dataset['date_start'] . '_to_' . $dataset['date_end'];

        if ($format === 'xlsx') {
            $this->export_xlsx($dataset, $filenameBase . '.xlsx');
        } elseif ($format === 'pdf') {
            $this->export_pdf($dataset, $filenameBase . '.pdf');
        } else {
            $this->export_csv($dataset, $filenameBase . '.csv');
        }
    }

    /**
     * Build the flat dataset (headers + rows + summary) used by every export
     * format, applying the same filters/permissions as the listing.
     */
    private function build_export_dataset()
    {
        $dateStart     = $this->input->get('date_start') ?: $this->input->get('week_start');
        $dateEnd       = $this->input->get('date_end');
        $dateRangeType = $this->input->get('date_range_type') ?: 'week';

        if (empty($dateStart)) {
            $dateStart = date('Y-m-d', strtotime('monday this week'));
        }

        if (empty($dateEnd)) {
            if ($dateRangeType === 'day') {
                $dateEnd = $dateStart;
            } elseif ($dateRangeType === 'month') {
                $dateEnd = date('Y-m-t', strtotime($dateStart));
            } else {
                $dateEnd = date('Y-m-d', strtotime('sunday this week', strtotime($dateStart)));
            }
        }

        $currentStaffId = get_staff_user_id();
        $isGlobal       = is_admin() || staff_can('view', 'timesheets');

        $filters = [
            'project_id'                  => $this->input->get('project_id'),
            'staff_id'                    => $this->input->get('staff_id'),
            'billing_type'                => $this->input->get('billing_type'),
            'group_by'                    => $this->input->get('group_by') ?: 'date',
            'date_start'                  => $dateStart,
            'date_end'                    => $dateEnd,
            'date_range_type'             => $dateRangeType,
            'advanced_filters'            => $this->input->get('advanced_filters'),
            'own_staff_id'                => $isGlobal ? null : $currentStaffId,
            'assigned_projects_staff_id'  => is_admin() ? null : $currentStaffId,
        ];

        $timelogData = $this->timelog_model->get_timelogs($dateStart, $filters);

        // Flatten the grouped structure back into individual rows.
        $rows = [];
        foreach (($timelogData['groups'] ?? []) as $group) {
            foreach (($group['logs'] ?? []) as $log) {
                $status = !empty($log['approval_status']) ? $log['approval_status'] : 'pending';

                $rows[] = [
                    _d($log['log_date']),
                    $log['project_name'] ?: '-',
                    $log['task_name'] ?: '-',
                    $log['staff_name'],
                    seconds_to_time_format((int) $log['duration_seconds']),
                    $log['billing_type_label'],
                    _l($status),
                    trim(html_entity_decode(strip_tags((string) $log['note']))),
                    $log['created_by_name'],
                ];
            }
        }

        $summary = $timelogData['summary'] ?? [
            'total_billable_hours'     => 0,
            'total_non_billable_hours' => 0,
            'total_hours'              => 0,
            'total_records'            => 0,
        ];

        return [
            'title'      => _l('timelog_export_title'),
            'period'     => _d($dateStart) . ' - ' . _d($dateEnd),
            'date_start' => $dateStart,
            'date_end'   => $dateEnd,
            'headers'    => [
                _l('date'),
                _l('project'),
                _l('task'),
                _l('user'),
                _l('total_hours'),
                _l('billing_type'),
                _l('status'),
                _l('note'),
                _l('created_by'),
            ],
            'rows'       => $rows,
            'summary'    => $summary,
            'meta'       => $this->build_export_meta($dateStart, $dateEnd, $dateRangeType, $filters, $summary),
        ];
    }

    /**
     * Build the dynamic header metadata shown at the top of every export
     * (company, title, project, view, custom view name, date range, export
     * timestamp and the billable/non-billable/total summary).
     */
    private function build_export_meta($dateStart, $dateEnd, $dateRangeType, $filters, $summary)
    {
        // Resolve the project label from the active filters.
        $projectIds = [];
        if (!empty($filters['advanced_filters'])) {
            $decoded = json_decode($filters['advanced_filters'], true);
            if (isset($decoded['project']['value']) && !empty($decoded['project']['value'])) {
                $projectIds = is_array($decoded['project']['value'])
                    ? $decoded['project']['value']
                    : [$decoded['project']['value']];
            }
        }
        if (empty($projectIds) && !empty($filters['project_id'])) {
            $projectIds = [$filters['project_id']];
        }

        $projectLabel = _l('all_projects');
        if (!empty($projectIds)) {
            $names = [];
            foreach ($projectIds as $pid) {
                $pid = (int) $pid;
                if ($pid > 0) {
                    $names[] = get_project_name_by_id($pid);
                }
            }
            $names = array_filter($names);
            if (!empty($names)) {
                $projectLabel = implode(', ', $names);
            }
        }

        // View label (matches the on-screen "Group By" control).
        $viewLabel = ($filters['group_by'] === 'user') ? _l('group_by_user') : _l('group_by_date');

        // Custom view name = the saved filter the user applied (passed from the
        // client); defaults to "All Time Logs" when none is active.
        $viewName = trim((string) $this->input->get('view_name'));
        if ($viewName === '') {
            $viewName = _l('all_time_logs');
        }

        // Date label with week number when viewing a week.
        $dateLabel = _d($dateStart) . ' to ' . _d($dateEnd);
        if ($dateRangeType === 'week') {
            $dateLabel .= ' (' . _l('week') . ' - ' . date('W', strtotime($dateStart)) . ')';
        }

        // Format decimal hours as HH:MM (e.g. 26:00 h).
        $hms = function ($hours) {
            return seconds_to_time_format((int) round(((float) $hours) * 3600)) . ' h';
        };

        return [
            'company_name'      => get_option('companyname'),
            'logo'              => pdf_logo_url(),
            'project_name'      => $projectLabel,
            'view_label'        => $viewLabel,
            'view_name'         => $viewName,
            'date_label'        => $dateLabel,
            'exported_on'       => date('d/m/Y h:i A'),
            'billable_hours'    => $hms($summary['total_billable_hours']),
            'non_billable_hours' => $hms($summary['total_non_billable_hours']),
            'total_hours'       => $hms($summary['total_hours']),
        ];
    }

    /**
     * Summary lines shown at the bottom of each export (label => value).
     */
    private function export_summary_lines($summary)
    {
        return [
            _l('total_billable_hours')     => number_format((float) $summary['total_billable_hours'], 2),
            _l('total_non_billable_hours') => number_format((float) $summary['total_non_billable_hours'], 2),
            _l('total_hours')              => number_format((float) $summary['total_hours'], 2),
            _l('total_records')            => (string) (int) $summary['total_records'],
        ];
    }

    /**
     * Stream the dataset as CSV.
     */
    private function export_csv($dataset, $filename)
    {
        // Native header() is used because we exit before CodeIgniter's output
        // stage, so queued output headers would never be sent.
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $out = fopen('php://output', 'w');

        // UTF-8 BOM so Excel reads accented characters correctly.
        fwrite($out, "\xEF\xBB\xBF");

        // Dynamic header block.
        $m = $dataset['meta'];
        fputcsv($out, [$m['company_name']]);
        fputcsv($out, [$dataset['title']]);
        fputcsv($out, [_l('timelog_export_project'), $m['project_name']]);
        fputcsv($out, [_l('timelog_export_view'), $m['view_label']]);
        fputcsv($out, [_l('timelog_export_custom_view'), $m['view_name']]);
        fputcsv($out, [_l('timelog_export_date'), $m['date_label']]);
        fputcsv($out, [_l('timelog_export_exported_on'), $m['exported_on']]);
        fputcsv($out, [_l('billable'), $m['billable_hours'], _l('non_billable'), $m['non_billable_hours'], _l('total'), $m['total_hours']]);
        fputcsv($out, []);

        fputcsv($out, $dataset['headers']);

        foreach ($dataset['rows'] as $row) {
            fputcsv($out, $row);
        }

        // Blank line + summary.
        fputcsv($out, []);
        foreach ($this->export_summary_lines($dataset['summary']) as $label => $value) {
            fputcsv($out, [$label, $value]);
        }

        fclose($out);
        exit;
    }

    /**
     * Stream the dataset as a real .xlsx workbook. Built with ZipArchive +
     * raw OOXML so no third-party spreadsheet library is required.
     */
    private function export_xlsx($dataset, $filename)
    {
        $headers   = $dataset['headers'];
        $rows      = $dataset['rows'];
        $colCount  = count($headers);
        $lastCol   = $this->xlsx_col_letter($colCount - 1);

        // Sensible per-column widths (Date, Project, Task, User, Hours, Billing, Status, Note, Created by).
        // Column A is also used for the red info labels in the header, so it is a touch wider.
        $widths = [20, 26, 32, 22, 10, 14, 12, 40, 22];

        $cols = '<cols>';
        for ($i = 0; $i < $colCount; $i++) {
            $w = isset($widths[$i]) ? $widths[$i] : 16;
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        $cols .= '</cols>';

        $m = $dataset['meta'];

        $sheetRows = '';
        $r = 1;

        // Dynamic header block: company name, then the centered title.
        $sheetRows .= '<row r="' . $r . '" ht="18" customHeight="1">' . $this->xlsx_cell('A' . $r, $m['company_name'], 4) . '</row>';
        $r++;
        $sheetRows .= '<row r="' . $r . '" ht="24" customHeight="1">' . $this->xlsx_cell('A' . $r, $dataset['title'], 2) . '</row>';
        $r++;

        $r++; // spacer

        // Info fields on a single line (red bold labels + values), matching the PDF.
        $infoFields = [
            [_l('timelog_export_project'),     $m['project_name']],
            [_l('timelog_export_view'),        $m['view_label']],
            [_l('timelog_export_custom_view'), $m['view_name']],
            [_l('timelog_export_date'),        $m['date_label']],
            [_l('timelog_export_exported_on'), $m['exported_on']],
        ];
        $infoRuns = [];
        $lastIdx  = count($infoFields) - 1;
        foreach ($infoFields as $idx => $f) {
            $infoRuns[] = ['t' => $f[0] . ': ', 'b' => true, 'c' => 'FFE74C3C'];
            $infoRuns[] = ['t' => $f[1] . ($idx < $lastIdx ? '        ' : ''), 'b' => false, 'c' => null];
        }
        $infoRowIndex = $r;
        $sheetRows .= '<row r="' . $r . '">' . $this->xlsx_rich_cell('A' . $r, $infoRuns, 0) . '</row>';
        $r++;

        $r++; // spacer

        // Billable / non-billable / total on a single line, matching the PDF bar.
        $summaryRuns = [
            ['t' => _l('billable') . ' ',     'b' => false, 'c' => 'FF999999'],
            ['t' => $m['billable_hours'] . '        ',     'b' => true, 'c' => 'FF2980B9'],
            ['t' => _l('non_billable') . ' ', 'b' => false, 'c' => 'FF999999'],
            ['t' => $m['non_billable_hours'] . '        ', 'b' => true, 'c' => 'FFE67E22'],
            ['t' => _l('total') . ' ',        'b' => false, 'c' => 'FF999999'],
            ['t' => $m['total_hours'],        'b' => true, 'c' => 'FF222222'],
        ];
        $summaryRowIndex = $r;
        $sheetRows .= '<row r="' . $r . '">' . $this->xlsx_rich_cell('A' . $r, $summaryRuns, 0) . '</row>';
        $r++;

        $r++; // spacer before the table

        $headerRowIndex = $r;
        $cells = '';
        for ($i = 0; $i < $colCount; $i++) {
            $cells .= $this->xlsx_cell($this->xlsx_col_letter($i) . $r, (string) $headers[$i], 1);
        }
        $sheetRows .= '<row r="' . $r . '">' . $cells . '</row>';
        $r++;

        foreach ($rows as $row) {
            $cells = '';
            for ($i = 0; $i < $colCount; $i++) {
                $cells .= $this->xlsx_cell($this->xlsx_col_letter($i) . $r, (string) ($row[$i] ?? ''), 3);
            }
            $sheetRows .= '<row r="' . $r . '">' . $cells . '</row>';
            $r++;
        }

        // Merge the company, title, info and summary rows across all columns
        // so each shows on its own single line.
        $merges = [
            'A1:' . $lastCol . '1',
            'A2:' . $lastCol . '2',
            'A' . $infoRowIndex . ':' . $lastCol . $infoRowIndex,
            'A' . $summaryRowIndex . ':' . $lastCol . $summaryRowIndex,
        ];
        $mergeXml = '<mergeCells count="' . count($merges) . '">';
        foreach ($merges as $ref) {
            $mergeXml .= '<mergeCell ref="' . $ref . '"/>';
        }
        $mergeXml .= '</mergeCells>';

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $headerRowIndex . '" topLeftCell="A' . ($headerRowIndex + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . $cols
            . '<sheetData>' . $sheetRows . '</sheetData>'
            . $mergeXml
            . '</worksheet>';

        $files = [
            '[Content_Types].xml' =>
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '</Types>',
            '_rels/.rels' =>
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' =>
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="Timelog" sheetId="1" r:id="rId1"/></sheets>'
                . '</workbook>',
            'xl/_rels/workbook.xml.rels' =>
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>',
            'xl/styles.xml' => $this->xlsx_styles_xml(),
            'xl/worksheets/sheet1.xml' => $sheetXml,
        ];

        $tmp = tempnam(sys_get_temp_dir(), 'tlxlsx');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: no-store, no-cache, must-revalidate');

        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    /**
     * The shared styles for the xlsx workbook.
     */
    private function xlsx_styles_xml()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="5">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'                                       // 0 normal
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'             // 1 header (bold white)
            . '<font><b/><sz val="15"/><color rgb="FF222222"/><name val="Calibri"/></font>'             // 2 title (bold large dark)
            . '<font><b/><sz val="12"/><color rgb="FF222222"/><name val="Calibri"/></font>'             // 3 company (bold dark)
            . '<font><b/><sz val="11"/><color rgb="FFE74C3C"/><name val="Calibri"/></font>'             // 4 label (bold red)
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF4F81BD"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFBFBFBF"/></left><right style="thin"><color rgb="FFBFBFBF"/></right><top style="thin"><color rgb="FFBFBFBF"/></top><bottom style="thin"><color rgb="FFBFBFBF"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="6">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                                                                              // 0 normal
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="left" vertical="center"/></xf>' // 1 header
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'         // 2 title (centered)
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf>'                                  // 3 data
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                                                                  // 4 company (bold dark)
            . '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                                                                  // 5 label (bold red)
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /**
     * Build one inline-string xlsx cell.
     */
    private function xlsx_cell($ref, $value, $styleIndex = 0)
    {
        $escaped = htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return '<c r="' . $ref . '" s="' . (int) $styleIndex . '" t="inlineStr"><is><t xml:space="preserve">' . $escaped . '</t></is></c>';
    }

    /**
     * Build a rich-text xlsx cell from multiple runs so a single cell can mix
     * colours/weights (e.g. red bold "Label:" followed by a normal value),
     * matching the one-line header used in the PDF.
     *
     * @param array $runs each: ['t' => text, 'b' => bool bold, 'c' => 'FFRRGGBB'|null]
     */
    private function xlsx_rich_cell($ref, array $runs, $styleIndex = 0)
    {
        $is = '';
        foreach ($runs as $run) {
            $rpr = '';
            if (!empty($run['b'])) {
                $rpr .= '<b/>';
            }
            if (!empty($run['c'])) {
                $rpr .= '<color rgb="' . $run['c'] . '"/>';
            }
            $rpr .= '<sz val="11"/><rFont val="Calibri"/>';

            $text = htmlspecialchars((string) $run['t'], ENT_QUOTES | ENT_XML1, 'UTF-8');
            $is .= '<r><rPr>' . $rpr . '</rPr><t xml:space="preserve">' . $text . '</t></r>';
        }

        return '<c r="' . $ref . '" s="' . (int) $styleIndex . '" t="inlineStr"><is>' . $is . '</is></c>';
    }

    /**
     * Column index (0-based) to its spreadsheet letter (A, B, ... Z, AA ...).
     */
    private function xlsx_col_letter($index)
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $mod    = ($index - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $index  = (int) (($index - $mod) / 26);
        }

        return $letter;
    }

    /**
     * Stream the dataset as a PDF (TCPDF, landscape) with a formatted table.
     */
    private function export_pdf($dataset, $filename)
    {
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Timelog');
        $pdf->SetAuthor(get_option('companyname'));
        $pdf->SetTitle($dataset['title']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 12, 10);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 8);

        $headerCells = '';
        foreach ($dataset['headers'] as $h) {
            $headerCells .= '<th style="background-color:#4F81BD;color:#FFFFFF;font-weight:bold;">'
                . htmlspecialchars((string) $h, ENT_QUOTES, 'UTF-8') . '</th>';
        }

        $bodyRows = '';
        if (empty($dataset['rows'])) {
            $bodyRows = '<tr><td colspan="' . count($dataset['headers']) . '" align="center">'
                . _l('no_timelogs_found') . '</td></tr>';
        } else {
            foreach ($dataset['rows'] as $row) {
                $cells = '';
                foreach ($row as $cell) {
                    $cells .= '<td>' . htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8') . '</td>';
                }
                $bodyRows .= '<tr>' . $cells . '</tr>';
            }
        }

        $m   = $dataset['meta'];
        $esc = function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };
        $field = function ($label, $value) use ($esc) {
            return '<span style="color:#e74c3c;font-weight:bold;">' . $esc($label) . ':</span> ' . $esc($value);
        };

        // Top band: company name (left), title (center), logo (right).
        $headerBand = '<table cellpadding="2" cellspacing="0" style="width:100%;">'
            . '<tr>'
            . '<td width="33%" style="font-size:11px;font-weight:bold;">' . $esc($m['company_name']) . '</td>'
            . '<td width="34%" align="center" style="font-size:15px;font-weight:bold;">' . $esc($dataset['title']) . '</td>'
            . '<td width="33%" align="right">' . $m['logo'] . '</td>'
            . '</tr></table>';

        // Dynamic info on a single line. All fields live in one full-width
        // cell with nobr="true" so nothing wraps to a second line.
        $infoGap  = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
        $infoLine = $field(_l('timelog_export_project'), $m['project_name']) . $infoGap
            . $field(_l('timelog_export_view'), $m['view_label']) . $infoGap
            . $field(_l('timelog_export_custom_view'), $m['view_name']) . $infoGap
            . $field(_l('timelog_export_date'), $m['date_label']) . $infoGap
            . $field(_l('timelog_export_exported_on'), $m['exported_on']);
        $infoRow = '<table cellpadding="4" cellspacing="0" style="width:100%;font-size:9px;">'
            . '<tr><td nobr="true">' . $infoLine . '</td></tr></table>';

        // Billable / non-billable / total summary bar (extra spacing between groups).
        $sumGap     = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
        $summaryBar = '<table cellpadding="5" cellspacing="0" style="width:100%;font-size:10px;"><tr><td nobr="true">'
            . '<span style="color:#999999;">' . _l('billable') . '</span> '
            . '<span style="color:#2980b9;font-weight:bold;">' . $esc($m['billable_hours']) . '</span>'
            . $sumGap . '<span style="color:#999999;">' . _l('non_billable') . '</span> '
            . '<span style="color:#e67e22;font-weight:bold;">' . $esc($m['non_billable_hours']) . '</span>'
            . $sumGap . '<span style="color:#999999;">' . _l('total') . '</span> '
            . '<span style="font-weight:bold;">' . $esc($m['total_hours']) . '</span>'
            . '</td></tr></table>';

        // Spacers between each section for breathing room.
        $spacer = '<br>';

        $html = $headerBand
            . $spacer
            . $infoRow
            . $spacer
            . '<hr style="color:#dddddd;">'
            . $spacer
            . $summaryBar
            . $spacer . $spacer
            . '<table border="0.5" cellpadding="5" cellspacing="0" style="font-size:8px;">'
            . '<thead><tr>' . $headerCells . '</tr></thead>'
            . '<tbody>' . $bodyRows . '</tbody>'
            . '</table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        // 'D' forces a download; TCPDF sends its own headers.
        $pdf->Output($filename, 'D');
        exit;
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
        $this->db->where_not_in(db_prefix() . 'tasks.status', [5, 6]);
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

        // Global users (admin / view global) can log time for anyone assigned
        // to the project; own-permission users can only log for themselves.
        $isGlobal = is_admin() || staff_can('view', 'timesheets');

        $users = [];
        if ($isGlobal) {
            // Get all project members
            $members = $this->projects_model->get_project_members($projectId, true);

            foreach ($members as $member) {
                $users[] = [
                    'staffid' => $member['staff_id'],
                    'full_name' => trim($member['firstname'] . ' ' . $member['lastname']),
                    'firstname' => $member['firstname'],
                    'lastname' => $member['lastname']
                ];
            }
        } else {
            // Own-permission user: only show the logged-in user
            $this->load->model('staff_model');
            $currentStaff = $this->staff_model->get($staffId);

            if ($currentStaff) {
                $users[] = [
                    'staffid' => $currentStaff->staffid,
                    'full_name' => trim($currentStaff->firstname . ' ' . $currentStaff->lastname),
                    'firstname' => $currentStaff->firstname,
                    'lastname' => $currentStaff->lastname
                ];
            }
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

            // Own-permission users can only log time for themselves
            $isGlobal = is_admin() || staff_can('view', 'timesheets');
            if (!$isGlobal) {
                $staffId = get_staff_user_id();
            }
            
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
            
            // For general log, store heading directly — no task row created
            if ($isGeneralLog) {
                if (empty($taskHeading) || trim($taskHeading) === '') {
                    $this->output
                        ->set_content_type('application/json')
                        ->set_output(json_encode(['success' => false, 'message' => _l('task_heading') . ' is required']));
                    return;
                }
                $taskId = 0;
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
            
            // Build insert data using existing columns only to avoid SQL errors
            $columns = $this->db->list_fields(db_prefix() . 'taskstimers');
            
            $insertData = [
                'start_time'  => $startTime,
                'end_time'    => $endTime,
                'staff_id'    => $staffId,
                'task_id'     => $taskId,
                'hourly_rate' => $hourlyRate,
                'note'        => $noteContent,
            ];

            if (in_array('project_id', $columns)) {
                $insertData['project_id'] = (int) $projectId;
            }

            if ($isGeneralLog && in_array('task_name', $columns)) {
                $insertData['task_name'] = trim($taskHeading);
            }

            if (in_array('bill_type', $columns)) {
                $insertData['bill_type'] = $billType;
            }

            if (in_array('status', $columns)) {
                $insertData['status'] = 'pending';
            }
            
            $this->db->insert(db_prefix() . 'taskstimers', $insertData);
            
            if ($this->db->affected_rows() > 0) {
                $insertId = $this->db->insert_id();
                
                // Return the actual date used for the timelog so the frontend can reload correctly
                $logDateFormatted = date('Y-m-d', $logDate);
                
                // Calculate week start (Monday) and week end (Sunday) correctly
                // Get the day of week (0=Sunday, 1=Monday, etc.)
                $dayOfWeek = date('w', $logDate); // 0 (Sunday) to 6 (Saturday)
                // Calculate days to subtract to get to Monday
                $daysToMonday = ($dayOfWeek == 0) ? 6 : ($dayOfWeek - 1);
                $weekStartTimestamp = $logDate - ($daysToMonday * 86400); // Subtract days in seconds
                $weekStart = date('Y-m-d', $weekStartTimestamp);
                
                // Week end is 6 days after week start
                $weekEndTimestamp = $weekStartTimestamp + (6 * 86400);
                $weekEnd = date('Y-m-d', $weekEndTimestamp);
                
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => true,
                        'message' => _l('timelog_added_successfully'),
                        'insert_id' => $insertId,
                        'date' => $logDateFormatted,
                        'week_start' => $weekStart,
                        'week_end' => $weekEnd
                    ]));
            } else {
                // If insert failed, include database error message for easier debugging
                $dbError = $this->db->error();
                $errorMessage = _l('error_adding_timelog');
                if (!empty($dbError['message'])) {
                    $errorMessage .= ': ' . $dbError['message'];
                }
                
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['success' => false, 'message' => $errorMessage]));
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
    
    /**
     * Get timelog data for editing
     */
    public function get_timelog()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        
        $timelog_id = $this->input->post('timelog_id');
        
        if (empty($timelog_id)) {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'message' => _l('timelog_id_required')]));
            return;
        }
        
        // Get timelog data — use COALESCE so general logs (task_id=0) return
        // their task_name and project_id from the taskstimers columns directly.
        $tt   = db_prefix() . 'taskstimers';
        $tk   = db_prefix() . 'tasks';
        $cols = $this->db->list_fields($tt);
        $has_task_name  = in_array('task_name',  $cols);
        $has_project_id = in_array('project_id', $cols);

        $task_name_sel  = $has_task_name  ? "COALESCE({$tk}.name, {$tt}.task_name)"          : "{$tk}.name";
        $project_id_sel = $has_project_id ? "COALESCE({$tk}.rel_id, {$tt}.project_id)"        : "{$tk}.rel_id";

        $this->db->select("
            {$tt}.id,
            {$tt}.task_id,
            {$tt}.start_time,
            {$tt}.end_time,
            {$tt}.staff_id,
            {$tt}.note,
            {$tt}.bill_type,
            {$tt}.status,
            {$project_id_sel} as project_id,
            {$task_name_sel}   as task_name
        ");
        $this->db->from($tt);
        $this->db->join($tk, "{$tk}.id = {$tt}.task_id", 'left');
        $this->db->where("{$tt}.id", $timelog_id);
        $timelog = $this->db->get()->row();

        if (!$timelog) {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'message' => _l('timelog_not_found')]));
            return;
        }

        // Only pending timelogs can be edited
        if ($timelog->status !== 'pending') {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'message' => _l('timelog_not_editable_status')]));
            return;
        }

        // Global users (admin / view global) can edit anyone's timelog;
        // Own-permission users can only edit their own.
        $isGlobal = is_admin() || staff_can('view', 'timesheets');
        $can_edit = $isGlobal || ($timelog->staff_id == get_staff_user_id());

        if (!$can_edit) {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'message' => _l('access_denied')]));
            return;
        }

        // Calculate duration in hours
        $duration  = $timelog->end_time - $timelog->start_time;
        $hours     = floor($duration / 3600);
        $minutes   = floor(($duration % 3600) / 60);
        $daily_log = sprintf('%02d:%02d', $hours, $minutes);

        // Format date
        $date = date('Y-m-d', $timelog->start_time);

        // General logs are identified by task_id = 0 (new approach).
        // Legacy entries may still carry the "General Log:" prefix in task_name.
        $is_general_log = ($timelog->task_id == 0)
            || (strpos((string) $timelog->task_name, 'General Log:') === 0);
        $task_heading = '';
        if ($is_general_log) {
            $task_heading = ltrim(preg_replace('/^General Log:\s*/u', '', (string) $timelog->task_name));
        }
        
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => true,
                'data' => [
                    'id' => $timelog->id,
                    'project_id' => $timelog->project_id,
                    'task_id' => $timelog->task_id,
                    'task_heading' => $task_heading,
                    'is_general_log' => $is_general_log ? '1' : '0',
                    'date' => $date,
                    'staff_id' => $timelog->staff_id,
                    'daily_log' => $daily_log,
                    'billing_type' => $timelog->bill_type ?: 'billable',
                    'notes' => $timelog->note ? strip_tags($timelog->note) : ''
                ]
            ]));
    }
    
    /**
     * Update timelog
     */
    public function update_timelog()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        
        try {
            // Load necessary libraries and models
            $this->load->library('form_validation');
            $this->load->model('tasks_model');
            
            $timelog_id = $this->input->post('timelog_id');
            
            if (empty($timelog_id)) {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['success' => false, 'message' => _l('timelog_id_required')]));
                return;
            }
            
            // Get existing timelog
            $this->db->where('id', $timelog_id);
            $existing_timelog = $this->db->get(db_prefix() . 'taskstimers')->row();
            
            if (!$existing_timelog) {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['success' => false, 'message' => _l('timelog_not_found')]));
                return;
            }

            // Only pending timelogs can be updated
            if ($existing_timelog->status !== 'pending') {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['success' => false, 'message' => _l('timelog_not_editable_status')]));
                return;
            }

            // Global users (admin / view global) can update anyone's timelog;
            // Own-permission users can only update their own.
            $isGlobal = is_admin() || staff_can('view', 'timesheets');
            $can_edit = $isGlobal || ($existing_timelog->staff_id == get_staff_user_id());

            if (!$can_edit) {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['success' => false, 'message' => _l('access_denied')]));
                return;
            }
            
            // Check if general log
            $isGeneralLog = $this->input->post('is_general_log') == '1';
            
            // Validate required fields
            $this->form_validation->set_rules('project_id', _l('project'), 'required|numeric');
            
            if ($isGeneralLog) {
                $this->form_validation->set_rules('task_heading', _l('task_heading'), 'required');
            } else {
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
            $dailyLogTime = $this->input->post('daily_log');
            $billingType = $this->input->post('billing_type') ?: 'billable';
            $notes = $this->input->post('notes');

            // Own-permission users can only log time for themselves
            if (!$isGlobal) {
                $staffId = get_staff_user_id();
            }
            
            // Convert time format (HH:MM) to decimal hours
            $timeParts = explode(':', $dailyLogTime);
            $hours = intval($timeParts[0]);
            $minutes = intval($timeParts[1]);
            $dailyLogHours = $hours + ($minutes / 60);
            
            // Parse date
            $logDate = strtotime($date);
            if ($logDate === false) {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['success' => false, 'message' => _l('invalid_date_format')]));
                return;
            }
            
            // For general log, keep task_id = 0; heading is stored in task_name column
            if ($isGeneralLog) {
                $taskId = 0;
            }
            
            // Calculate start_time and end_time
            $startHour = 9;
            $startMinute = 0;
            $startTime = mktime($startHour, $startMinute, 0, date('n', $logDate), date('j', $logDate), date('Y', $logDate));
            $endTime = $startTime + ($dailyLogHours * 3600);
            
            // Prepare billing type
            $billType = ($billingType == 'non_billable') ? 'non_billable' : 'billable';
            
            // Prepare note
            $noteContent = !empty($notes) ? nl2br(e($notes)) : null;
            
            // Update timelog
            $timerColumns = $this->db->list_fields(db_prefix() . 'taskstimers');

            $updateData = [
                'start_time' => $startTime,
                'end_time'   => $endTime,
                'staff_id'   => $staffId,
                'task_id'    => $taskId,
                'note'       => $noteContent,
                'bill_type'  => $billType,
            ];

            if (in_array('project_id', $timerColumns)) {
                $updateData['project_id'] = (int) $projectId;
            }

            if ($isGeneralLog && in_array('task_name', $timerColumns)) {
                $updateData['task_name'] = trim($taskHeading);
            }
            
            $this->db->where('id', $timelog_id);
            $this->db->update(db_prefix() . 'taskstimers', $updateData);
            
            if ($this->db->affected_rows() >= 0) {
                $logDateFormatted = date('Y-m-d', $logDate);
                $weekStart = date('Y-m-d', strtotime('monday this week', $logDate));
                $weekEnd = date('Y-m-d', strtotime('sunday this week', $logDate));
                
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => true,
                        'message' => _l('timelog_updated_successfully'),
                        'date' => $logDateFormatted,
                        'week_start' => $weekStart,
                        'week_end' => $weekEnd
                    ]));
            } else {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['success' => false, 'message' => _l('error_updating_timelog')]));
            }
        } catch (Exception $e) {
            log_message('error', 'Timelog update error: ' . $e->getMessage());
            
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => _l('error_updating_timelog') . ': ' . $e->getMessage()
                ]));
        }
    }
}
