<?php

namespace app\services\projects;

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Builds WHERE clauses for the Zoho-style projects filter panel.
 *
 * This class is intentionally standalone to avoid touching
 * existing table logic while enabling incremental filters.
 */
class ProjectsAdvancedFilters
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

        if ($projectNameClause = $this->buildProjectNameClause()) {
            $clauses[] = $projectNameClause;
        }

        if ($statusClause = $this->buildStatusClause()) {
            $clauses[] = $statusClause;
        }

        if ($ownerClause = $this->buildOwnerClause()) {
            $clauses[] = $ownerClause;
        }

        if ($startDateClause = $this->buildStartDateClause()) {
            $clauses[] = $startDateClause;
        }

        if ($deadlineClause = $this->buildDeadlineClause()) {
            $clauses[] = $deadlineClause;
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

    protected function buildProjectNameClause(): string
    {
        if (
            empty($this->filters['project_name'])
            || ! is_array($this->filters['project_name'])
        ) {
            return '';
        }

        $config = $this->filters['project_name'];
        $value  = isset($config['value']) ? trim($config['value']) : '';

        if ($value === '') {
            return '';
        }

        $operator = $config['operator'] ?? 'contains';
        $column   = db_prefix() . 'projects.name';

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

    /**
     * Build WHERE clause for project status filter.
     *
     * Supported status values:
     * - 2: In Progress
     * - 3: On Hold
     * - 4: Finished
     * - 5: Cancelled
     *
     * @return string SQL snippet for status filtering
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

        // Get the status values using the helper method
        $statusValues = $this->extractFilterValues($config, 'value');

        if (empty($statusValues)) {
            return '';
        }

        // Allowed status values: In Progress (2), On Hold (3), Finished (4), Cancelled (5)
        $allowedStatuses = [2, 3, 4, 5];

        // Filter and sanitize status values
        $sanitizedStatuses = [];
        foreach ($statusValues as $status) {
            $statusInt = (int) $status;
            if (in_array($statusInt, $allowedStatuses, true)) {
                $sanitizedStatuses[] = $statusInt;
            }
        }

        if (empty($sanitizedStatuses)) {
            return '';
        }

        $column = db_prefix() . 'projects.status';

        // For status filtering, use IN clause for multiple values
        // Note: A project can only have one status at a time, so "all of these"
        // with multiple statuses would technically return no results.
        // We use IN clause which effectively acts as "any of these" for single-value fields.
        if (count($sanitizedStatuses) === 1) {
            return $column . ' = ' . $sanitizedStatuses[0];
        }

        return $column . ' IN (' . implode(',', $sanitizedStatuses) . ')';
    }

    /**
     * Build WHERE clause for project owner filter.
     *
     * Supports three operator types:
     * - is: Filter by active users (project members or creator)
     * - deactive_user: Filter by deactive/inactive users
     * - deleted_user: Filter by deleted users (orphaned staff references)
     *
     * @return string SQL snippet for owner filtering
     */
    protected function buildOwnerClause(): string
    {
        if (
            empty($this->filters['owner'])
            || ! is_array($this->filters['owner'])
        ) {
            return '';
        }

        $config = $this->filters['owner'];
        $operator = $config['operator'] ?? 'is';

        // Determine which value array to use based on operator
        $ownerValues = [];

        switch ($operator) {
            case 'deactive_user':
                $ownerValues = $this->extractFilterValues($config, 'deactive_value');
                break;
            case 'deleted_user':
                $ownerValues = $this->extractFilterValues($config, 'deleted_value');
                break;
            case 'is':
            default:
                $ownerValues = $this->extractFilterValues($config, 'value');
                break;
        }

        if (empty($ownerValues)) {
            return '';
        }

        // Sanitize owner IDs - must be numeric
        $sanitizedOwners = [];
        foreach ($ownerValues as $ownerId) {
            $ownerInt = (int) $ownerId;
            if ($ownerInt > 0) {
                $sanitizedOwners[] = $ownerInt;
            }
        }

        if (empty($sanitizedOwners)) {
            return '';
        }

        $dbPrefix = db_prefix();
        $ownerList = implode(',', $sanitizedOwners);

        // Build the clause to match projects where:
        // 1. The project was created by (addedfrom) any of the selected owners, OR
        // 2. Any of the selected owners is a project member
        $clause = "(
            {$dbPrefix}projects.addedfrom IN ({$ownerList})
            OR {$dbPrefix}projects.id IN (
                SELECT project_id FROM {$dbPrefix}project_members 
                WHERE staff_id IN ({$ownerList})
            )
        )";

        return $clause;
    }

    /**
     * Extract filter values from config array.
     * Supports multiple array formats: 'key', 'key[]', or direct array.
     *
     * @param array $config The filter configuration array
     * @param string $key The key to extract values for
     * @return array The extracted values
     */
    protected function extractFilterValues(array $config, string $key): array
    {
        // Try 'key[]' format first (common in form submissions)
        if (isset($config[$key . '[]']) && is_array($config[$key . '[]'])) {
            return $config[$key . '[]'];
        }

        // Try direct 'key' format
        if (isset($config[$key])) {
            if (is_array($config[$key])) {
                return $config[$key];
            }
            // Single value
            return [$config[$key]];
        }

        return [];
    }

    /**
     * Build WHERE clause for project start_date filter.
     *
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
            || ! is_array($this->filters['start_date'])
        ) {
            return '';
        }

        $config = $this->filters['start_date'];
        $operator = $config['operator'] ?? '';

        if (empty($operator)) {
            return '';
        }

        $db = $this->ci->db;
        $column = db_prefix() . 'projects.start_date';

        // Handle preset operators
        $presetResult = $this->handlePresetDateOperator($operator, $column);
        if ($presetResult !== null) {
            return $presetResult;
        }

        // Handle advanced operators
        return $this->handleAdvancedDateOperator($operator, $config, $column);
    }

    /**
     * Handle preset date operators that auto-calculate date ranges.
     *
     * @param string $operator The preset operator
     * @param string $column The database column name
     * @return string|null SQL clause or null if not a preset operator
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
                // Monday to Sunday of current week
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
                return null; // Not a preset operator
        }
    }

    /**
     * Handle advanced date operators that require user-provided dates.
     *
     * @param string $operator The advanced operator
     * @param array $config Filter configuration with date values
     * @param string $column The database column name
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

    /**
     * Build WHERE clause for project deadline filter.
     *
     * Supports the same preset and advanced operators as start_date:
     * - Preset: today, yesterday, tomorrow, till_yesterday, this_week, etc.
     * - Advanced: is, between, less_than, greater_than, etc.
     *
     * @return string SQL snippet for deadline filtering
     */
    protected function buildDeadlineClause(): string
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

        $db = $this->ci->db;
        $column = db_prefix() . 'projects.deadline';

        // Handle preset operators (reuse the same logic as start_date)
        $presetResult = $this->handlePresetDateOperator($operator, $column);
        if ($presetResult !== null) {
            return $presetResult;
        }

        // Handle advanced operators (reuse the same logic as start_date)
        return $this->handleAdvancedDateOperator($operator, $config, $column);
    }

    /**
     * Build WHERE clause for created_by filter.
     *
     * Supports two operators:
     * - is: Show projects created by selected staff
     * - is_not: Exclude projects created by selected staff
     *
     * @return string SQL snippet for created_by filtering
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

        // Get the staff values
        $staffValues = $this->extractFilterValues($config, 'value');

        if (empty($staffValues)) {
            return '';
        }

        // Sanitize staff IDs - must be numeric
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

        $column = db_prefix() . 'projects.addedfrom';
        $staffList = implode(',', $sanitizedStaff);

        // Build clause based on operator
        switch ($operator) {
            case 'is_not':
                // Exclude projects created by selected staff
                if (count($sanitizedStaff) === 1) {
                    return $column . ' != ' . $sanitizedStaff[0];
                }
                return $column . ' NOT IN (' . $staffList . ')';

            case 'is':
            default:
                // Include only projects created by selected staff
                if (count($sanitizedStaff) === 1) {
                    return $column . ' = ' . $sanitizedStaff[0];
                }
                return $column . ' IN (' . $staffList . ')';
        }
    }
}


