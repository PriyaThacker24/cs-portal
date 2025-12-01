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
}

