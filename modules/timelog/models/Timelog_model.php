<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Timelog_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get timelogs grouped by week and date/user
     * 
     * @param string $weekStart Week start date (Y-m-d format)
     * @param array $filters Filter options (project_id, staff_id, billing_type, group_by)
     * @return array Grouped timelog data
     */
    public function get_timelogs($weekStart, $filters = [])
    {
        $weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($weekStart)));
        $weekNumber = date('W', strtotime($weekStart));
        
        // Check if status and bill_type columns exist
        $columns = $this->db->list_fields(db_prefix() . 'taskstimers');
        $has_status_column = in_array('status', $columns);
        $has_bill_type_column = in_array('bill_type', $columns);
        
        // Build query
        $selectFields = '
            ' . db_prefix() . 'taskstimers.id,
            ' . db_prefix() . 'taskstimers.task_id,
            ' . db_prefix() . 'taskstimers.start_time,
            ' . db_prefix() . 'taskstimers.end_time,
            ' . db_prefix() . 'taskstimers.staff_id,
            ' . db_prefix() . 'taskstimers.note,
            ' . db_prefix() . 'tasks.name as task_name,
            ' . db_prefix() . 'tasks.addedfrom as task_created_by,
            ' . db_prefix() . 'projects.name as project_name,
            ' . db_prefix() . 'projects.id as project_id,
            CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) as staff_name,
            DATE(FROM_UNIXTIME(' . db_prefix() . 'taskstimers.start_time)) as log_date,
            (' . db_prefix() . 'taskstimers.end_time - ' . db_prefix() . 'taskstimers.start_time) as duration_seconds,
            CONCAT(created_by_staff.firstname, " ", created_by_staff.lastname) as created_by_name
        ';
        
        if ($has_status_column) {
            $selectFields .= ', ' . db_prefix() . 'taskstimers.status as approval_status';
        }
        
        if ($has_bill_type_column) {
            $selectFields .= ', ' . db_prefix() . 'taskstimers.bill_type';
        }
        
        $this->db->select($selectFields);
        
        // Join tables
        $this->db->from(db_prefix() . 'taskstimers');
        $this->db->join(db_prefix() . 'tasks', db_prefix() . 'tasks.id = ' . db_prefix() . 'taskstimers.task_id', 'left');
        $this->db->join(db_prefix() . 'projects', db_prefix() . 'projects.id = ' . db_prefix() . 'tasks.rel_id AND ' . db_prefix() . 'tasks.rel_type = "project"', 'left');
        $this->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid = ' . db_prefix() . 'taskstimers.staff_id', 'left');
        $this->db->join(db_prefix() . 'staff as created_by_staff', 'created_by_staff.staffid = ' . db_prefix() . 'tasks.addedfrom', 'left');
        
        // Filter by week
        $weekStartTimestamp = strtotime($weekStart . ' 00:00:00');
        $weekEndTimestamp = strtotime($weekEnd . ' 23:59:59');
        $this->db->where(db_prefix() . 'taskstimers.start_time >=', $weekStartTimestamp);
        $this->db->where(db_prefix() . 'taskstimers.start_time <=', $weekEndTimestamp);
        $this->db->where(db_prefix() . 'taskstimers.end_time IS NOT NULL', null, false);
        
        // Apply advanced filters using ProjectTimelogAdvancedFilters class
        if (!empty($filters['advanced_filters'])) {
            try {
                $filterData = is_string($filters['advanced_filters']) ? json_decode($filters['advanced_filters'], true) : $filters['advanced_filters'];
                
                // Handle Project filter separately (not in ProjectTimelogAdvancedFilters)
                if (isset($filterData['project']) && !empty($filterData['project']['value'])) {
                    $projectIds = is_array($filterData['project']['value']) ? $filterData['project']['value'] : [$filterData['project']['value']];
                    $operator = isset($filterData['project']['operator']) ? $filterData['project']['operator'] : 'is';
                    
                    if ($operator === 'is_not') {
                        $this->db->where_not_in(db_prefix() . 'projects.id', $projectIds);
                    } else {
                        $this->db->where_in(db_prefix() . 'projects.id', $projectIds);
                    }
                }
                
                // Remove project filter from data before passing to ProjectTimelogAdvancedFilters
                $filterDataWithoutProject = $filterData;
                unset($filterDataWithoutProject['project']);
                
                // Only process if there are other filters besides project
                if (!empty($filterDataWithoutProject) && count($filterDataWithoutProject) > (isset($filterDataWithoutProject['match']) ? 1 : 0)) {
                    $advancedFilters = new \app\services\timelog\ProjectTimelogAdvancedFilters($filterDataWithoutProject);
                    $advancedWhere = $advancedFilters->buildWhereClause();
                    if (!empty($advancedWhere)) {
                        // Remove leading ' AND ' if present, then add it properly
                        $advancedWhere = ltrim($advancedWhere, ' AND ');
                        $this->db->where('(' . $advancedWhere . ')', null, false);
                    }
                }
            } catch (Exception $e) {
                // Log error but don't break the query
                log_message('error', 'Timelog advanced filter error: ' . $e->getMessage());
            }
        }
        
        // Legacy simple filters (for backward compatibility)
        if (!empty($filters['project_id']) && empty($filters['advanced_filters'])) {
            $this->db->where(db_prefix() . 'projects.id', $filters['project_id']);
        }
        
        if (!empty($filters['staff_id']) && empty($filters['advanced_filters'])) {
            $this->db->where(db_prefix() . 'taskstimers.staff_id', $filters['staff_id']);
        }
        
        // Apply billing type filter (legacy)
        if ($has_bill_type_column && !empty($filters['billing_type']) && empty($filters['advanced_filters'])) {
            if ($filters['billing_type'] == 'billable') {
                $this->db->where(db_prefix() . 'taskstimers.bill_type', 'billable');
            } elseif ($filters['billing_type'] == 'non_billable') {
                $this->db->where(db_prefix() . 'taskstimers.bill_type', 'non_billable');
            }
        }
        
        // Order by date and time
        $this->db->order_by('log_date', 'ASC');
        $this->db->order_by(db_prefix() . 'taskstimers.start_time', 'ASC');
        
        $query = $this->db->get();
        $timelogs = $query->result_array();
        
        // Get group_by from filters
        $groupBy = isset($filters['group_by']) ? $filters['group_by'] : 'date';
        
        // Process timelogs based on grouping option
        if ($groupBy == 'user') {
            return $this->process_timelogs_by_user($timelogs, $weekStart, $weekEnd, $weekNumber);
        } else {
            return $this->process_timelogs_by_date($timelogs, $weekStart, $weekEnd, $weekNumber);
        }
    }

    /**
     * Process timelogs into flat list format (similar to Project Timelog)
     */
    private function process_timelogs_flat($timelogs, $weekStart, $weekEnd, $weekNumber)
    {
        $result = [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'week_number' => $weekNumber,
            'timelogs' => [],
            'summary' => [
                'total_billable_hours' => 0,
                'total_non_billable_hours' => 0,
                'total_hours' => 0,
                'total_records' => count($timelogs)
            ]
        ];
        
        if (empty($timelogs)) {
            return $result;
        }
        
        foreach ($timelogs as $log) {
            // Calculate hours
            $durationSeconds = isset($log['duration_seconds']) ? (int)$log['duration_seconds'] : 0;
            $durationHours = $durationSeconds / 3600;
            
            // Check billing type
            $billType = isset($log['bill_type']) ? $log['bill_type'] : 'billable';
            $isBillable = ($billType == 'billable');
            
            // Get approval status
            $approvalStatus = isset($log['approval_status']) && $log['approval_status'] != '' ? $log['approval_status'] : 'pending';
            
            // Get created by
            $createdByName = isset($log['created_by_name']) ? $log['created_by_name'] : '';
            
            // Update summary
            if ($isBillable) {
                $result['summary']['total_billable_hours'] += $durationHours;
            } else {
                $result['summary']['total_non_billable_hours'] += $durationHours;
            }
            $result['summary']['total_hours'] += $durationHours;
            
            // Add log data
            $result['timelogs'][] = [
                'id' => $log['id'],
                'task_id' => $log['task_id'],
                'task_name' => $log['task_name'],
                'project_name' => $log['project_name'],
                'project_id' => $log['project_id'],
                'staff_name' => $log['staff_name'],
                'staff_id' => $log['staff_id'],
                'log_date' => $log['log_date'],
                'start_time' => $log['start_time'],
                'end_time' => $log['end_time'],
                'duration_seconds' => $durationSeconds,
                'duration_hours' => round($durationHours, 2),
                'time_period' => $this->format_time_period($log['start_time'], $log['end_time']),
                'billing_type' => $billType,
                'billing_type_label' => $isBillable ? _l('task_billable') : _l('task_not_billable'),
                'note' => $log['note'],
                'approval_status' => $approvalStatus,
                'created_by_name' => $createdByName
            ];
        }
        
        // Round summary hours
        $result['summary']['total_billable_hours'] = round($result['summary']['total_billable_hours'], 2);
        $result['summary']['total_non_billable_hours'] = round($result['summary']['total_non_billable_hours'], 2);
        $result['summary']['total_hours'] = round($result['summary']['total_hours'], 2);
        
        return $result;
    }
    
    /**
     * Process timelogs and group by week/date/user (DEPRECATED - kept for backwards compatibility)
     */
    private function process_timelogs($timelogs, $weekStart, $weekEnd, $weekNumber, $filters)
    {
        $groupBy = isset($filters['group_by']) ? $filters['group_by'] : 'date';
        
        $result = [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'week_number' => $weekNumber,
            'groups' => [],
            'summary' => [
                'total_billable_hours' => 0,
                'total_non_billable_hours' => 0,
                'total_hours' => 0,
                'total_records' => count($timelogs)
            ]
        ];
        
        if (empty($timelogs)) {
            return $result;
        }
        
        // Group by date or user
        $grouped = [];
        
        foreach ($timelogs as $log) {
            $logDate = $log['log_date'];
            $staffId = $log['staff_id'];
            $staffName = $log['staff_name'];
            
            // Calculate hours
            $durationSeconds = isset($log['duration_seconds']) ? (int)$log['duration_seconds'] : 0;
            $durationHours = $durationSeconds / 3600;
            
            // Check billing type
            $billType = isset($log['bill_type']) ? $log['bill_type'] : 'billable';
            $isBillable = ($billType == 'billable');
            
            // Update summary
            if ($isBillable) {
                $result['summary']['total_billable_hours'] += $durationHours;
            } else {
                $result['summary']['total_non_billable_hours'] += $durationHours;
            }
            $result['summary']['total_hours'] += $durationHours;
            
            // Group by date or user
            if ($groupBy == 'date') {
                $key = $logDate;
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'date' => $logDate,
                        'total_hours' => 0,
                        'logs' => []
                    ];
                }
            } else { // group_by user
                $key = $staffId;
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'user_id' => $staffId,
                        'user_name' => $staffName,
                        'total_hours' => 0,
                        'logs' => []
                    ];
                }
            }
            
            // Get approval status
            $approvalStatus = isset($log['approval_status']) && $log['approval_status'] != '' ? $log['approval_status'] : 'pending';
            
            // Get created by
            $createdByName = isset($log['created_by_name']) ? $log['created_by_name'] : '';
            
            // Add log data
            $logData = [
                'id' => $log['id'],
                'task_id' => $log['task_id'],
                'task_name' => $log['task_name'],
                'project_name' => $log['project_name'],
                'project_id' => $log['project_id'],
                'staff_name' => $staffName,
                'staff_id' => $staffId,
                'log_date' => $logDate,
                'start_time' => $log['start_time'],
                'end_time' => $log['end_time'],
                'duration_seconds' => $durationSeconds,
                'duration_hours' => round($durationHours, 2),
                'time_period' => $this->format_time_period($log['start_time'], $log['end_time']),
                'billing_type' => $billType,
                'billing_type_label' => $isBillable ? _l('task_billable') : _l('task_not_billable'),
                'note' => $log['note'],
                'approval_status' => $approvalStatus,
                'created_by_name' => $createdByName
            ];
            
            $grouped[$key]['logs'][] = $logData;
            $grouped[$key]['total_hours'] += $durationHours;
        }
        
        // Convert to array and sort
        $result['groups'] = array_values($grouped);
        
        // Sort groups
        usort($result['groups'], function($a, $b) use ($groupBy) {
            if ($groupBy == 'date') {
                return strcmp($a['date'], $b['date']);
            } else {
                return strcmp($a['user_name'], $b['user_name']);
            }
        });
        
        // Round summary hours
        $result['summary']['total_billable_hours'] = round($result['summary']['total_billable_hours'], 2);
        $result['summary']['total_non_billable_hours'] = round($result['summary']['total_non_billable_hours'], 2);
        $result['summary']['total_hours'] = round($result['summary']['total_hours'], 2);
        
        return $result;
    }

    /**
     * Process timelogs grouped by date
     */
    private function process_timelogs_by_date($timelogs, $weekStart, $weekEnd, $weekNumber)
    {
        $result = [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'week_number' => $weekNumber,
            'group_by' => 'date',
            'groups' => [],
            'summary' => [
                'total_billable_hours' => 0,
                'total_non_billable_hours' => 0,
                'total_hours' => 0,
                'total_records' => count($timelogs)
            ]
        ];
        
        if (empty($timelogs)) {
            return $result;
        }
        
        // Group by date (based on start_time)
        $grouped = [];
        
        foreach ($timelogs as $log) {
            // Ensure we're using the date from start_time (always use start_time for grouping)
            if (!isset($log['start_time']) || empty($log['start_time'])) {
                // Skip logs without a valid start_time
                continue;
            }
            
            // Extract date from start_time (Unix timestamp)
            $logDate = date('Y-m-d', $log['start_time']);
            
            if (!isset($grouped[$logDate])) {
                $grouped[$logDate] = [
                    'date' => $logDate,
                    'total_hours' => 0,
                    'total_billable_hours' => 0,
                    'total_non_billable_hours' => 0,
                    'total_records' => 0,
                    'logs' => []
                ];
            }
            
            // Calculate hours
            $durationSeconds = isset($log['duration_seconds']) ? (int)$log['duration_seconds'] : 0;
            $durationHours = $durationSeconds / 3600;
            
            // Check billing type
            $billType = isset($log['bill_type']) ? $log['bill_type'] : 'billable';
            $isBillable = ($billType == 'billable');
            
            // Get approval status
            $approvalStatus = isset($log['approval_status']) && $log['approval_status'] != '' ? $log['approval_status'] : 'pending';
            
            // Get created by
            $createdByName = isset($log['created_by_name']) ? $log['created_by_name'] : '';
            
            // Update group totals
            $grouped[$logDate]['total_hours'] += $durationHours;
            if ($isBillable) {
                $grouped[$logDate]['total_billable_hours'] += $durationHours;
            } else {
                $grouped[$logDate]['total_non_billable_hours'] += $durationHours;
            }
            $grouped[$logDate]['total_records']++;
            
            // Update summary
            if ($isBillable) {
                $result['summary']['total_billable_hours'] += $durationHours;
            } else {
                $result['summary']['total_non_billable_hours'] += $durationHours;
            }
            $result['summary']['total_hours'] += $durationHours;
            
            // Add log data
            $grouped[$logDate]['logs'][] = [
                'id' => $log['id'],
                'task_id' => $log['task_id'],
                'task_name' => $log['task_name'],
                'project_name' => $log['project_name'],
                'project_id' => $log['project_id'],
                'staff_name' => $log['staff_name'],
                'staff_id' => $log['staff_id'],
                'log_date' => $logDate,
                'start_time' => $log['start_time'],
                'end_time' => $log['end_time'],
                'duration_seconds' => $durationSeconds,
                'duration_hours' => round($durationHours, 2),
                'time_period' => $this->format_time_period($log['start_time'], $log['end_time']),
                'billing_type' => $billType,
                'billing_type_label' => $isBillable ? _l('task_billable') : _l('task_not_billable'),
                'note' => $log['note'],
                'approval_status' => $approvalStatus,
                'created_by_name' => $createdByName
            ];
        }
        
        // Convert to array and sort by date (ascending - oldest first)
        $result['groups'] = array_values($grouped);
        usort($result['groups'], function($a, $b) {
            return strcmp($a['date'], $b['date']); // Ascending order (oldest first)
        });
        
        // Round totals
        foreach ($result['groups'] as &$group) {
            $group['total_hours'] = round($group['total_hours'], 2);
            $group['total_billable_hours'] = round($group['total_billable_hours'], 2);
            $group['total_non_billable_hours'] = round($group['total_non_billable_hours'], 2);
        }
        
        $result['summary']['total_billable_hours'] = round($result['summary']['total_billable_hours'], 2);
        $result['summary']['total_non_billable_hours'] = round($result['summary']['total_non_billable_hours'], 2);
        $result['summary']['total_hours'] = round($result['summary']['total_hours'], 2);
        
        return $result;
    }
    
    /**
     * Process timelogs grouped by user
     */
    private function process_timelogs_by_user($timelogs, $weekStart, $weekEnd, $weekNumber)
    {
        $result = [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'week_number' => $weekNumber,
            'group_by' => 'user',
            'groups' => [],
            'summary' => [
                'total_billable_hours' => 0,
                'total_non_billable_hours' => 0,
                'total_hours' => 0,
                'total_records' => count($timelogs)
            ]
        ];
        
        if (empty($timelogs)) {
            return $result;
        }
        
        // Group by user
        $grouped = [];
        
        foreach ($timelogs as $log) {
            $staffId = $log['staff_id'];
            $staffName = $log['staff_name'];
            
            if (!isset($grouped[$staffId])) {
                $grouped[$staffId] = [
                    'user_id' => $staffId,
                    'user_name' => $staffName,
                    'total_hours' => 0,
                    'total_billable_hours' => 0,
                    'total_non_billable_hours' => 0,
                    'total_records' => 0,
                    'logs' => []
                ];
            }
            
            // Calculate hours
            $durationSeconds = isset($log['duration_seconds']) ? (int)$log['duration_seconds'] : 0;
            $durationHours = $durationSeconds / 3600;
            
            // Check billing type
            $billType = isset($log['bill_type']) ? $log['bill_type'] : 'billable';
            $isBillable = ($billType == 'billable');
            
            // Get approval status
            $approvalStatus = isset($log['approval_status']) && $log['approval_status'] != '' ? $log['approval_status'] : 'pending';
            
            // Get created by
            $createdByName = isset($log['created_by_name']) ? $log['created_by_name'] : '';
            
            // Update group totals
            $grouped[$staffId]['total_hours'] += $durationHours;
            if ($isBillable) {
                $grouped[$staffId]['total_billable_hours'] += $durationHours;
            } else {
                $grouped[$staffId]['total_non_billable_hours'] += $durationHours;
            }
            $grouped[$staffId]['total_records']++;
            
            // Update summary
            if ($isBillable) {
                $result['summary']['total_billable_hours'] += $durationHours;
            } else {
                $result['summary']['total_non_billable_hours'] += $durationHours;
            }
            $result['summary']['total_hours'] += $durationHours;
            
            // Add log data
            $grouped[$staffId]['logs'][] = [
                'id' => $log['id'],
                'task_id' => $log['task_id'],
                'task_name' => $log['task_name'],
                'project_name' => $log['project_name'],
                'project_id' => $log['project_id'],
                'staff_name' => $staffName,
                'staff_id' => $staffId,
                'log_date' => $log['log_date'],
                'start_time' => $log['start_time'],
                'end_time' => $log['end_time'],
                'duration_seconds' => $durationSeconds,
                'duration_hours' => round($durationHours, 2),
                'time_period' => $this->format_time_period($log['start_time'], $log['end_time']),
                'billing_type' => $billType,
                'billing_type_label' => $isBillable ? _l('task_billable') : _l('task_not_billable'),
                'note' => $log['note'],
                'approval_status' => $approvalStatus,
                'created_by_name' => $createdByName
            ];
        }
        
        // Convert to array and sort by user name (ascending)
        $result['groups'] = array_values($grouped);
        usort($result['groups'], function($a, $b) {
            return strcmp($a['user_name'], $b['user_name']); // Ascending order
        });
        
        // Round totals
        foreach ($result['groups'] as &$group) {
            $group['total_hours'] = round($group['total_hours'], 2);
            $group['total_billable_hours'] = round($group['total_billable_hours'], 2);
            $group['total_non_billable_hours'] = round($group['total_non_billable_hours'], 2);
        }
        
        $result['summary']['total_billable_hours'] = round($result['summary']['total_billable_hours'], 2);
        $result['summary']['total_non_billable_hours'] = round($result['summary']['total_non_billable_hours'], 2);
        $result['summary']['total_hours'] = round($result['summary']['total_hours'], 2);
        
        return $result;
    }

    /**
     * Format time period (e.g., "09:00 AM - 05:00 PM")
     */
    private function format_time_period($startTime, $endTime)
    {
        $start = date('h:i A', $startTime);
        $end = date('h:i A', $endTime);
        return $start . ' - ' . $end;
    }

    /**
     * Format hours to readable format (e.g., "8.5h")
     */
    public function format_hours($hours)
    {
        return number_format($hours, 2) . 'h';
    }
}


