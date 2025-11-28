<?php

namespace app\services\tasks;

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Builds WHERE clauses for the Zoho-style tasks filter panel.
 */
class TasksAdvancedFilters
{
    protected $filters = [];
    protected $ci;

    private const LIKE_ESCAPE_CHAR = '!';

    public function __construct($filters = [])
    {
        $this->ci = get_instance();

        if (is_string($filters)) {
            $decoded = json_decode($filters, true);
            $filters = is_array($decoded) ? $decoded : [];
        }

        $this->filters = is_array($filters) ? $filters : [];
    }

    /**
     * Returns a SQL snippet (including the leading AND) or an empty string.
     */
    public function buildWhereClause(): string
    {
        $clauses = [];

        if ($taskNameClause = $this->buildTaskNameClause()) {
            $clauses[] = $taskNameClause;
        }

        if ($statusClause = $this->buildStatusClause()) {
            $clauses[] = $statusClause;
        }

        if ($priorityClause = $this->buildPriorityClause()) {
            $clauses[] = $priorityClause;
        }

        if ($assignedClause = $this->buildAssignedClause()) {
            $clauses[] = $assignedClause;
        }

        if ($startDateClause = $this->buildStartDateClause()) {
            $clauses[] = $startDateClause;
        }

        if ($dueDateClause = $this->buildDueDateClause()) {
            $clauses[] = $dueDateClause;
        }

        if ($createdByClause = $this->buildCreatedByClause()) {
            $clauses[] = $createdByClause;
        }

        if (empty($clauses)) {
            return '';
        }

        $glue = $this->isMatchAll() ? 'AND' : 'OR';

        return ' AND (' . implode(' ' . $glue . ' ', $clauses) . ')';
    }

    protected function isMatchAll(): bool
    {
        return isset($this->filters['match']) && $this->filters['match'] === 'all';
    }

    /**
     * Build WHERE clause for task name filter.
     */
    protected function buildTaskNameClause(): string
    {
        if (
            empty($this->filters['task_name'])
            || ! is_array($this->filters['task_name'])
        ) {
            return '';
        }

        $config = $this->filters['task_name'];
        $value  = isset($config['value']) ? trim($config['value']) : '';

        if ($value === '') {
            return '';
        }

        $operator = $config['operator'] ?? 'contains';
        $column   = db_prefix() . 'tasks.name';

        switch ($operator) {
            case 'is':
                return $this->lowerEquals($column, $value);
            case 'is_not':
                return $this->lowerEquals($column, $value, true);
            case 'does_not_contain':
                return $this->buildLikeClause($column, $value, 'both', true);
            case 'starts_with':
                return $this->buildLikeClause($column, $value, 'after');
            case 'ends_with':
                return $this->buildLikeClause($column, $value, 'before');
            case 'contains':
            default:
                return $this->buildLikeClause($column, $value, 'both');
        }
    }

    /**
     * Build WHERE clause for status filter.
     */
    protected function buildStatusClause(): string
    {
        if (
            empty($this->filters['status'])
            || ! is_array($this->filters['status'])
        ) {
            return '';
        }

        $config = $this->filters['status'];
        $statusValues = $this->extractFilterValues($config, 'value');

        if (empty($statusValues)) {
            return '';
        }

        $sanitizedStatuses = [];
        foreach ($statusValues as $status) {
            $statusInt = (int) $status;
            if ($statusInt > 0) {
                $sanitizedStatuses[] = $statusInt;
            }
        }

        if (empty($sanitizedStatuses)) {
            return '';
        }

        $column = db_prefix() . 'tasks.status';

        if (count($sanitizedStatuses) === 1) {
            return $column . ' = ' . $sanitizedStatuses[0];
        }

        return $column . ' IN (' . implode(',', $sanitizedStatuses) . ')';
    }

    /**
     * Build WHERE clause for priority filter.
     */
    protected function buildPriorityClause(): string
    {
        if (
            empty($this->filters['priority'])
            || ! is_array($this->filters['priority'])
        ) {
            return '';
        }

        $config = $this->filters['priority'];
        $priorityValues = $this->extractFilterValues($config, 'value');

        if (empty($priorityValues)) {
            return '';
        }

        $sanitizedPriorities = [];
        foreach ($priorityValues as $priority) {
            $priorityInt = (int) $priority;
            if ($priorityInt > 0) {
                $sanitizedPriorities[] = $priorityInt;
            }
        }

        if (empty($sanitizedPriorities)) {
            return '';
        }

        $column = db_prefix() . 'tasks.priority';

        if (count($sanitizedPriorities) === 1) {
            return $column . ' = ' . $sanitizedPriorities[0];
        }

        return $column . ' IN (' . implode(',', $sanitizedPriorities) . ')';
    }

    /**
     * Build WHERE clause for assigned filter.
     */
    protected function buildAssignedClause(): string
    {
        if (
            empty($this->filters['assigned'])
            || ! is_array($this->filters['assigned'])
        ) {
            return '';
        }

        $config = $this->filters['assigned'];
        $operator = $config['operator'] ?? 'is';
        $staffValues = $this->extractFilterValues($config, 'value');

        if (empty($staffValues)) {
            return '';
        }

        $sanitizedStaff = [];
        foreach ($staffValues as $staffId) {
            $staffInt = (int) $staffId;
            if ($staffInt > 0) {
                $sanitizedStaff[] = $staffInt;
            }
        }

        if (empty($sanitizedStaff)) {
            return '';
        }

        $dbPrefix = db_prefix();
        $staffList = implode(',', $sanitizedStaff);

        switch ($operator) {
            case 'is_not':
                return "{$dbPrefix}tasks.id NOT IN (SELECT taskid FROM {$dbPrefix}task_assigned WHERE staffid IN ({$staffList}))";
            case 'is':
            default:
                return "{$dbPrefix}tasks.id IN (SELECT taskid FROM {$dbPrefix}task_assigned WHERE staffid IN ({$staffList}))";
        }
    }

    /**
     * Build WHERE clause for start date filter.
     */
    protected function buildStartDateClause(): string
    {
        if (
            empty($this->filters['start_date'])
            || ! is_array($this->filters['start_date'])
        ) {
            return '';
        }

        $config = $this->filters['start_date'];
        $operator = $config['operator'] ?? '';

        if (empty($operator)) {
            return '';
        }

        $column = db_prefix() . 'tasks.startdate';

        $presetResult = $this->handlePresetDateOperator($operator, $column);
        if ($presetResult !== null) {
            return $presetResult;
        }

        return $this->handleAdvancedDateOperator($operator, $config, $column);
    }

    /**
     * Build WHERE clause for due date filter.
     */
    protected function buildDueDateClause(): string
    {
        if (
            empty($this->filters['due_date'])
            || ! is_array($this->filters['due_date'])
        ) {
            return '';
        }

        $config = $this->filters['due_date'];
        $operator = $config['operator'] ?? '';

        if (empty($operator)) {
            return '';
        }

        $column = db_prefix() . 'tasks.duedate';

        $presetResult = $this->handlePresetDateOperator($operator, $column);
        if ($presetResult !== null) {
            return $presetResult;
        }

        return $this->handleAdvancedDateOperator($operator, $config, $column);
    }

    /**
     * Build WHERE clause for created by filter.
     */
    protected function buildCreatedByClause(): string
    {
        if (
            empty($this->filters['created_by'])
            || ! is_array($this->filters['created_by'])
        ) {
            return '';
        }

        $config = $this->filters['created_by'];
        $operator = $config['operator'] ?? 'is';
        $staffValues = $this->extractFilterValues($config, 'value');

        if (empty($staffValues)) {
            return '';
        }

        $sanitizedStaff = [];
        foreach ($staffValues as $staffId) {
            $staffInt = (int) $staffId;
            if ($staffInt > 0) {
                $sanitizedStaff[] = $staffInt;
            }
        }

        if (empty($sanitizedStaff)) {
            return '';
        }

        $column = db_prefix() . 'tasks.addedfrom';
        $staffList = implode(',', $sanitizedStaff);

        switch ($operator) {
            case 'is_not':
                return "({$column} NOT IN ({$staffList}) OR {$column} IS NULL)";
            case 'is':
            default:
                return "{$column} IN ({$staffList})";
        }
    }

    /**
     * Handle preset date operators.
     */
    protected function handlePresetDateOperator(string $operator, string $column): ?string
    {
        $db = $this->ci->db;
        $today = date('Y-m-d');

        switch ($operator) {
            case 'today':
                return $column . ' = ' . $db->escape($today);

            case 'yesterday':
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                return $column . ' = ' . $db->escape($yesterday);

            case 'tomorrow':
                $tomorrow = date('Y-m-d', strtotime('+1 day'));
                return $column . ' = ' . $db->escape($tomorrow);

            case 'till_yesterday':
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                return $column . ' <= ' . $db->escape($yesterday);

            case 'this_week':
                $monday = date('Y-m-d', strtotime('monday this week'));
                $sunday = date('Y-m-d', strtotime('sunday this week'));
                return $column . ' BETWEEN ' . $db->escape($monday) . ' AND ' . $db->escape($sunday);

            case 'last_week':
                $lastMonday = date('Y-m-d', strtotime('monday last week'));
                $lastSunday = date('Y-m-d', strtotime('sunday last week'));
                return $column . ' BETWEEN ' . $db->escape($lastMonday) . ' AND ' . $db->escape($lastSunday);

            case 'next_week':
                $nextMonday = date('Y-m-d', strtotime('monday next week'));
                $nextSunday = date('Y-m-d', strtotime('sunday next week'));
                return $column . ' BETWEEN ' . $db->escape($nextMonday) . ' AND ' . $db->escape($nextSunday);

            case 'this_month':
                $firstDay = date('Y-m-01');
                $lastDay = date('Y-m-t');
                return $column . ' BETWEEN ' . $db->escape($firstDay) . ' AND ' . $db->escape($lastDay);

            case 'last_month':
                $firstDay = date('Y-m-01', strtotime('first day of last month'));
                $lastDay = date('Y-m-t', strtotime('last day of last month'));
                return $column . ' BETWEEN ' . $db->escape($firstDay) . ' AND ' . $db->escape($lastDay);

            case 'next_month':
                $firstDay = date('Y-m-01', strtotime('first day of next month'));
                $lastDay = date('Y-m-t', strtotime('last day of next month'));
                return $column . ' BETWEEN ' . $db->escape($firstDay) . ' AND ' . $db->escape($lastDay);

            case 'last_7_days':
                $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
                return $column . ' BETWEEN ' . $db->escape($sevenDaysAgo) . ' AND ' . $db->escape($today);

            case 'next_30_days':
                $thirtyDaysLater = date('Y-m-d', strtotime('+30 days'));
                return $column . ' BETWEEN ' . $db->escape($today) . ' AND ' . $db->escape($thirtyDaysLater);

            case 'unscheduled':
                return '(' . $column . ' IS NULL OR ' . $column . " = '' OR " . $column . " = '0000-00-00')";

            default:
                return null;
        }
    }

    /**
     * Handle advanced date operators.
     */
    protected function handleAdvancedDateOperator(string $operator, array $config, string $column): string
    {
        $db = $this->ci->db;

        switch ($operator) {
            case 'is':
                $value = $config['value'] ?? '';
                if (empty($value)) {
                    return '';
                }
                $dateValue = $this->parseDate($value);
                if (!$dateValue) {
                    return '';
                }
                return $column . ' = ' . $db->escape($dateValue);

            case 'between':
                $from = $config['from'] ?? '';
                $to = $config['to'] ?? '';
                if (empty($from) || empty($to)) {
                    return '';
                }
                $fromDate = $this->parseDate($from);
                $toDate = $this->parseDate($to);
                if (!$fromDate || !$toDate) {
                    return '';
                }
                return $column . ' BETWEEN ' . $db->escape($fromDate) . ' AND ' . $db->escape($toDate);

            case 'less_than':
                $value = $config['value'] ?? '';
                if (empty($value)) {
                    return '';
                }
                $dateValue = $this->parseDate($value);
                if (!$dateValue) {
                    return '';
                }
                return $column . ' < ' . $db->escape($dateValue);

            case 'greater_than':
                $value = $config['value'] ?? '';
                if (empty($value)) {
                    return '';
                }
                $dateValue = $this->parseDate($value);
                if (!$dateValue) {
                    return '';
                }
                return $column . ' > ' . $db->escape($dateValue);

            case 'less_than_or_equal':
                $value = $config['value'] ?? '';
                if (empty($value)) {
                    return '';
                }
                $dateValue = $this->parseDate($value);
                if (!$dateValue) {
                    return '';
                }
                return $column . ' <= ' . $db->escape($dateValue);

            case 'greater_than_or_equal':
                $value = $config['value'] ?? '';
                if (empty($value)) {
                    return '';
                }
                $dateValue = $this->parseDate($value);
                if (!$dateValue) {
                    return '';
                }
                return $column . ' >= ' . $db->escape($dateValue);

            default:
                return '';
        }
    }

    protected function parseDate(string $dateString)
    {
        if (empty($dateString)) {
            return false;
        }

        $timestamp = strtotime($dateString);
        if ($timestamp === false) {
            return false;
        }

        return date('Y-m-d', $timestamp);
    }

    protected function lowerEquals(string $column, string $value, bool $negate = false): string
    {
        $db        = $this->ci->db;
        $lowered   = mb_strtolower($value, 'UTF-8');
        $operator  = $negate ? '!=' : '=';

        return 'LOWER(' . $column . ') ' . $operator . ' ' . $db->escape($lowered);
    }

    protected function buildLikeClause(
        string $column,
        string $value,
        string $position = 'both',
        bool $negate = false
    ): string {
        $db      = $this->ci->db;
        $lowered = mb_strtolower($value, 'UTF-8');
        $escaped = $db->escape_like_str($lowered);

        switch ($position) {
            case 'before':
                $pattern = '%' . $escaped;
                break;
            case 'after':
                $pattern = $escaped . '%';
                break;
            case 'both':
            default:
                $pattern = '%' . $escaped . '%';
                break;
        }

        $likeOperator = $negate ? 'NOT LIKE' : 'LIKE';

        return 'LOWER(' . $column . ') ' . $likeOperator . ' '
            . $db->escape($pattern)
            . " ESCAPE '" . self::LIKE_ESCAPE_CHAR . "'";
    }

    protected function extractFilterValues(array $config, string $key): array
    {
        if (isset($config[$key . '[]']) && is_array($config[$key . '[]'])) {
            return $config[$key . '[]'];
        }

        if (isset($config[$key])) {
            if (is_array($config[$key])) {
                return $config[$key];
            }
            return [$config[$key]];
        }

        return [];
    }
}

