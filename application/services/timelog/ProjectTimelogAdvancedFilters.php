<?php

namespace app\services\timelog;

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Builds WHERE clauses for the Zoho-style project timelog filter panel.
 *
 * This class is intentionally standalone to avoid touching
 * existing table logic while enabling incremental filters.
 */
class ProjectTimelogAdvancedFilters
{
    /**
     * @var array
     */
    protected $filters = [];

    /**
     * @var \CI_Controller
     */
    protected $ci;

    /**
     * Escape character used inside LIKE statements.
     */
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

        if ($logUserClause = $this->buildLogUserClause()) {
            $clauses[] = $logUserClause;
        }

        if ($workItemClause = $this->buildWorkItemClause()) {
            $clauses[] = $workItemClause;
        }

        if ($billingTypeClause = $this->buildBillingTypeClause()) {
            $clauses[] = $billingTypeClause;
        }

        if ($approvalStatusClause = $this->buildApprovalStatusClause()) {
            $clauses[] = $approvalStatusClause;
        }

        if ($createdByClause = $this->buildCreatedByClause()) {
            $clauses[] = $createdByClause;
        }

        if ($startDateClause = $this->buildStartDateClause()) {
            $clauses[] = $startDateClause;
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
     * Build Log User filter clause
     * Filters timelogs by the staff member who logged the time
     */
    protected function buildLogUserClause(): string
    {
        if (
            empty($this->filters['log_user'])
            || !is_array($this->filters['log_user'])
        ) {
            return '';
        }

        $config   = $this->filters['log_user'];
        $operator = isset($config['operator']) ? strtolower(trim($config['operator'])) : 'is';
        $values   = $this->extractFilterValues($config, 'value');

        if (empty($values)) {
            return '';
        }

        $prefix     = db_prefix();
        $escapedIds = array_map(function ($id) {
            return $this->ci->db->escape($id);
        }, $values);

        $inList = implode(',', $escapedIds);

        if ($operator === 'is_not') {
            return "({$prefix}taskstimers.staff_id NOT IN ({$inList}))";
        }

        // Default: is
        return "({$prefix}taskstimers.staff_id IN ({$inList}))";
    }

    /**
     * Build Work Item (Task) filter clause
     * Filters timelogs by task_id
     */
    protected function buildWorkItemClause(): string
    {
        if (
            empty($this->filters['work_item'])
            || !is_array($this->filters['work_item'])
        ) {
            return '';
        }

        $config = $this->filters['work_item'];
        $values = $this->extractFilterValues($config, 'value');

        if (empty($values)) {
            return '';
        }

        $prefix     = db_prefix();
        $escapedIds = array_map(function ($id) {
            return $this->ci->db->escape($id);
        }, $values);

        $inList = implode(',', $escapedIds);

        return "({$prefix}taskstimers.task_id IN ({$inList}))";
    }

    /**
     * Build Billing Type filter clause
     * Filters timelogs by billable status
     */
    protected function buildBillingTypeClause(): string
    {
        if (
            empty($this->filters['billing_type'])
            || !is_array($this->filters['billing_type'])
        ) {
            return '';
        }

        $config   = $this->filters['billing_type'];
        $operator = isset($config['operator']) ? strtolower(trim($config['operator'])) : 'is';
        $value    = isset($config['value']) ? strtolower(trim($config['value'])) : '';

        if ($value === '') {
            return '';
        }

        $prefix = db_prefix();
        
        // Determine the billable value (1 for billable, 0 for non-billable)
        $billableValue = ($value === 'billable') ? 1 : 0;

        if ($operator === 'is_not') {
            return "({$prefix}tasks.billable != {$billableValue})";
        }

        // Default: is
        return "({$prefix}tasks.billable = {$billableValue})";
    }

    /**
     * Build Approval Status filter clause
     * Filters timelogs by status (pending, approved, rejected)
     */
    protected function buildApprovalStatusClause(): string
    {
        if (
            empty($this->filters['approval_status'])
            || !is_array($this->filters['approval_status'])
        ) {
            return '';
        }

        $config   = $this->filters['approval_status'];
        $operator = isset($config['operator']) ? strtolower(trim($config['operator'])) : 'is';
        $values   = $this->extractFilterValues($config, 'value');

        if (empty($values)) {
            return '';
        }

        $prefix = db_prefix();
        
        // Values are stored as strings: 'pending', 'approved', 'rejected'
        $escapedValues = array_map(function ($status) {
            return $this->ci->db->escape(strtolower(trim($status)));
        }, $values);

        $inList = implode(',', $escapedValues);

        if ($operator === 'is_not') {
            return "({$prefix}taskstimers.status NOT IN ({$inList}))";
        }

        // Default: is
        return "({$prefix}taskstimers.status IN ({$inList}))";
    }

    /**
     * Build Created By filter clause
     * Filters timelogs by the staff member who created the timer entry
     */
    protected function buildCreatedByClause(): string
    {
        if (
            empty($this->filters['created_by'])
            || !is_array($this->filters['created_by'])
        ) {
            return '';
        }

        $config   = $this->filters['created_by'];
        $operator = isset($config['operator']) ? strtolower(trim($config['operator'])) : 'is';
        $values   = $this->extractFilterValues($config, 'value');

        if (empty($values)) {
            return '';
        }

        $prefix     = db_prefix();
        $escapedIds = array_map(function ($id) {
            return $this->ci->db->escape($id);
        }, $values);

        $inList = implode(',', $escapedIds);

        if ($operator === 'is_not') {
            return "({$prefix}taskstimers.staff_id NOT IN ({$inList}))";
        }

        // Default: is
        return "({$prefix}taskstimers.staff_id IN ({$inList}))";
    }

    /**
     * Extract filter values from config array
     */
    protected function extractFilterValues(array $config, string $key): array
    {
        if (!isset($config[$key])) {
            return [];
        }

        $value = $config[$key];

        if (is_array($value)) {
            return array_filter($value, function ($v) {
                return $v !== '' && $v !== null;
            });
        }

        $trimmed = trim((string) $value);
        return $trimmed !== '' ? [$trimmed] : [];
    }

    /**
     * Case-insensitive equals check
     */
    protected function lowerEquals(string $a, string $b): bool
    {
        return strtolower(trim($a)) === strtolower(trim($b));
    }

    /**
     * Build a LIKE clause with proper escaping
     */
    protected function buildLikeClause(string $column, string $value, string $mode = 'both'): string
    {
        $escaped = str_replace(
            [self::LIKE_ESCAPE_CHAR, '%', '_'],
            [self::LIKE_ESCAPE_CHAR . self::LIKE_ESCAPE_CHAR, self::LIKE_ESCAPE_CHAR . '%', self::LIKE_ESCAPE_CHAR . '_'],
            $value
        );

        switch ($mode) {
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

        return "{$column} LIKE " . $this->ci->db->escape($pattern) . " ESCAPE '" . self::LIKE_ESCAPE_CHAR . "'";
    }

    /**
     * Build WHERE clause for timesheet start_date filter.
     *
     * Filters by the date portion of start_time (timestamp).
     * Supports preset date operators:
     * - today, yesterday, tomorrow, till_yesterday
     * - this_week, last_week, next_week
     * - this_month, last_month, next_month
     * - last_7_days, next_30_days
     * - unscheduled (NULL or empty)
     *
     * And advanced operators:
     * - is, between, less_than, greater_than
     * - less_than_or_equal, greater_than_or_equal
     *
     * @return string SQL snippet for start_date filtering
     */
    protected function buildStartDateClause(): string
    {
        if (
            empty($this->filters['start_date'])
            || !is_array($this->filters['start_date'])
        ) {
            return '';
        }

        $config = $this->filters['start_date'];
        $operator = $config['operator'] ?? '';

        if (empty($operator)) {
            return '';
        }

        $prefix = db_prefix();
        // Convert timestamp to date for comparison
        $dateColumn = "DATE(FROM_UNIXTIME({$prefix}taskstimers.start_time))";

        // Handle preset operators
        $presetResult = $this->handlePresetDateOperator($operator, $dateColumn);
        if ($presetResult !== null) {
            return $presetResult;
        }

        // Handle advanced operators
        return $this->handleAdvancedDateOperator($operator, $config, $dateColumn);
    }

    /**
     * Handle preset date operators that auto-calculate date ranges.
     *
     * @param string $operator The preset operator
     * @param string $column The database column name (can be a function like DATE(...))
     * @return string|null SQL clause or null if not a preset operator
     */
    protected function handlePresetDateOperator(string $operator, string $column): ?string
    {
        $db = $this->ci->db;
        $today = date('Y-m-d');

        switch ($operator) {
            case 'today':
                return "({$column} = " . $db->escape($today) . ")";

            case 'yesterday':
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                return "({$column} = " . $db->escape($yesterday) . ")";

            case 'tomorrow':
                $tomorrow = date('Y-m-d', strtotime('+1 day'));
                return "({$column} = " . $db->escape($tomorrow) . ")";

            case 'till_yesterday':
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                return "({$column} <= " . $db->escape($yesterday) . ")";

            case 'this_week':
                // Monday to Sunday of current week
                $monday = date('Y-m-d', strtotime('monday this week'));
                $sunday = date('Y-m-d', strtotime('sunday this week'));
                return "({$column} BETWEEN " . $db->escape($monday) . " AND " . $db->escape($sunday) . ")";

            case 'last_week':
                $lastMonday = date('Y-m-d', strtotime('monday last week'));
                $lastSunday = date('Y-m-d', strtotime('sunday last week'));
                return "({$column} BETWEEN " . $db->escape($lastMonday) . " AND " . $db->escape($lastSunday) . ")";

            case 'next_week':
                $nextMonday = date('Y-m-d', strtotime('monday next week'));
                $nextSunday = date('Y-m-d', strtotime('sunday next week'));
                return "({$column} BETWEEN " . $db->escape($nextMonday) . " AND " . $db->escape($nextSunday) . ")";

            case 'this_month':
                $firstDay = date('Y-m-01');
                $lastDay = date('Y-m-t');
                return "({$column} BETWEEN " . $db->escape($firstDay) . " AND " . $db->escape($lastDay) . ")";

            case 'last_month':
                $firstDay = date('Y-m-01', strtotime('first day of last month'));
                $lastDay = date('Y-m-t', strtotime('last day of last month'));
                return "({$column} BETWEEN " . $db->escape($firstDay) . " AND " . $db->escape($lastDay) . ")";

            case 'next_month':
                $firstDay = date('Y-m-01', strtotime('first day of next month'));
                $lastDay = date('Y-m-t', strtotime('last day of next month'));
                return "({$column} BETWEEN " . $db->escape($firstDay) . " AND " . $db->escape($lastDay) . ")";

            case 'last_7_days':
                $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
                return "({$column} BETWEEN " . $db->escape($sevenDaysAgo) . " AND " . $db->escape($today) . ")";

            case 'next_30_days':
                $thirtyDaysLater = date('Y-m-d', strtotime('+30 days'));
                return "({$column} BETWEEN " . $db->escape($today) . " AND " . $db->escape($thirtyDaysLater) . ")";

            case 'unscheduled':
                // For timestamps, check if start_time is NULL, empty, or 0
                return "({$prefix}taskstimers.start_time IS NULL OR {$prefix}taskstimers.start_time = '' OR {$prefix}taskstimers.start_time = '0' OR {$prefix}taskstimers.start_time = 0)";

            default:
                return null; // Not a preset operator
        }
    }

    /**
     * Handle advanced date operators that require user-provided dates.
     *
     * @param string $operator The advanced operator
     * @param array $config Filter configuration with date values
     * @param string $column The database column name (can be a function like DATE(...))
     * @return string SQL clause or empty string
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
                return "({$column} = " . $db->escape($dateValue) . ")";

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
                return "({$column} BETWEEN " . $db->escape($fromDate) . " AND " . $db->escape($toDate) . ")";

            case 'less_than':
                $value = $config['value'] ?? '';
                if (empty($value)) {
                    return '';
                }
                $dateValue = $this->parseDate($value);
                if (!$dateValue) {
                    return '';
                }
                return "({$column} < " . $db->escape($dateValue) . ")";

            case 'greater_than':
                $value = $config['value'] ?? '';
                if (empty($value)) {
                    return '';
                }
                $dateValue = $this->parseDate($value);
                if (!$dateValue) {
                    return '';
                }
                return "({$column} > " . $db->escape($dateValue) . ")";

            case 'less_than_or_equal':
                $value = $config['value'] ?? '';
                if (empty($value)) {
                    return '';
                }
                $dateValue = $this->parseDate($value);
                if (!$dateValue) {
                    return '';
                }
                return "({$column} <= " . $db->escape($dateValue) . ")";

            case 'greater_than_or_equal':
                $value = $config['value'] ?? '';
                if (empty($value)) {
                    return '';
                }
                $dateValue = $this->parseDate($value);
                if (!$dateValue) {
                    return '';
                }
                return "({$column} >= " . $db->escape($dateValue) . ")";

            default:
                return '';
        }
    }

    /**
     * Parse a date string and convert to Y-m-d format for database.
     * Handles various date formats.
     *
     * @param string $dateString The date string to parse
     * @return string|false Y-m-d formatted date or false on failure
     */
    protected function parseDate(string $dateString)
    {
        if (empty($dateString)) {
            return false;
        }

        // Try to parse the date
        $timestamp = strtotime($dateString);
        if ($timestamp === false) {
            return false;
        }

        return date('Y-m-d', $timestamp);
    }
}

