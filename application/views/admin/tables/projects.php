<?php

defined('BASEPATH') or exit('No direct script access allowed');

use app\services\projects\ProjectsAdvancedFilters;

return App_table::find('projects')
    ->outputUsing(function ($params) {
        extract($params);

        // Global permissions for backward compatibility (non-project-specific)
        $hasPermissionEditGlobal   = staff_can('edit',  'projects');
        $hasPermissionDeleteGlobal = staff_can('delete',  'projects');
        $hasPermissionCreate = staff_can('create',  'projects');

        $p = db_prefix();
        $taskProgressSubquery = '(SELECT CASE '
            . 'WHEN COUNT(*) = 0 THEN 100 '
            . 'WHEN SUM(IF(' . $p . 'tasks.status = 5, 1, 0)) >= COUNT(*) THEN 100 '
            . 'ELSE ROUND(SUM(IF(' . $p . 'tasks.status = 5, 1, 0)) * 100.0 / COUNT(*), 2) '
            . 'END FROM ' . $p . 'tasks WHERE ' . $p . "tasks.rel_type = 'project' AND " . $p . 'tasks.rel_id = ' . $p . 'projects.id)';
        $progressSelect = 'CASE '
            . 'WHEN ' . $p . 'projects.status = 4 THEN 100 '
            . 'WHEN ' . $p . 'projects.progress_from_tasks = 1 THEN ' . $taskProgressSubquery . ' '
            . 'ELSE COALESCE(' . $p . 'projects.progress, 0) '
            . 'END AS calc_progress_display';

        $aColumns = [
            db_prefix() . 'projects.id as id',
            'name',
            get_sql_select_client_company(),
            'start_date',
            $progressSelect,
            '(SELECT GROUP_CONCAT(CONCAT(firstname, \' \', lastname) SEPARATOR ",") FROM ' . db_prefix() . 'project_members JOIN ' . db_prefix() . 'staff on ' . db_prefix() . 'staff.staffid = ' . db_prefix() . 'project_members.staff_id WHERE project_id=' . db_prefix() . 'projects.id ORDER BY staff_id) as members',
            'status',
        ];


        $sIndexColumn = 'id';
        $sTable       = db_prefix() . 'projects';

        $join = [
            'JOIN ' . db_prefix() . 'clients ON ' . db_prefix() . 'clients.userid = ' . db_prefix() . 'projects.clientid',
        ];

        $where  = [];

        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        if ($clientid != '') {
            array_push($where, ' AND clientid=' . $this->ci->db->escape_str($clientid));
        }

        if (staff_cant('view', 'projects')) {
            // Show projects where user is admin, member, or has permissions
            array_push($where, ' AND (' . db_prefix() . 'projects.addedfrom=' . get_staff_user_id() . ' OR ' . db_prefix() . 'projects.id IN (SELECT project_id FROM ' . db_prefix() . 'project_members WHERE staff_id=' . get_staff_user_id() . '))');
        }

        $filtersInput = $this->ci->input->post('filters');
        if ($filtersInput === null) {
            $filtersInput = $this->ci->input->get('filters');
        }

        $advancedFiltersWhere = (new ProjectsAdvancedFilters($filtersInput))->buildWhereClause();
        if ($advancedFiltersWhere !== '') {
            $where[] = $advancedFiltersWhere;
        }

        $custom_fields = get_table_custom_fields('projects');

        foreach ($custom_fields as $key => $field) {
            $selectAs = (is_cf_date($field) ? 'date_picker_cvalue_' . $key : 'cvalue_' . $key);
            array_push($customFieldsColumns, $selectAs);
            array_push($aColumns, 'ctable_' . $key . '.value as ' . $selectAs);
            array_push($join, 'LEFT JOIN ' . db_prefix() . 'customfieldsvalues as ctable_' . $key . ' ON ' . db_prefix() . 'projects.id = ctable_' . $key . '.relid AND ctable_' . $key . '.fieldto="' . $field['fieldto'] . '" AND ctable_' . $key . '.fieldid=' . $field['id']);
        }

        $aColumns = hooks()->apply_filters('projects_table_sql_columns', $aColumns);

        // Fix for big queries. Some hosting have max_join_limit
        if (count($custom_fields) > 4) {
            @$this->ci->db->query('SET SQL_BIG_SELECTS=1');
        }

        // Check if owner_id and manager_id columns exist before adding them to query
        $additionalSelect = [
            'clientid',
            db_prefix() . 'clients.company as client_agency_name',
            '(SELECT CONCAT(TRIM(COALESCE(' . db_prefix() . 'contacts.firstname, \'\')), \' \', TRIM(COALESCE(' . db_prefix() . 'contacts.lastname, \'\'))) FROM ' . db_prefix() . 'contacts WHERE ' . db_prefix() . 'contacts.userid = ' . db_prefix() . 'projects.clientid AND ' . db_prefix() . 'contacts.is_primary = 1 LIMIT 1) as primary_contact_fullname',
            '(SELECT GROUP_CONCAT(staff_id SEPARATOR ",") FROM ' . db_prefix() . 'project_members WHERE project_id=' . db_prefix() . 'projects.id ORDER BY staff_id) as members_ids',
            db_prefix() . 'projects.addedfrom as addedfrom',
            '(SELECT COUNT(*) FROM ' . $p . 'tasks WHERE ' . $p . "tasks.rel_type = 'project' AND " . $p . 'tasks.rel_id = ' . $p . 'projects.id AND ' . $p . 'tasks.status = 5) as progress_tasks_completed',
            '(SELECT COUNT(*) FROM ' . $p . 'tasks WHERE ' . $p . "tasks.rel_type = 'project' AND " . $p . 'tasks.rel_id = ' . $p . 'projects.id AND ' . $p . 'tasks.status <> 5) as progress_tasks_remaining',
        ];
        
        // Try to add owner_id and manager_id if columns exist
        $CI = &get_instance();
        try {
            $fields = $CI->db->list_fields(db_prefix() . 'projects');
            if (in_array('owner_id', $fields)) {
                $additionalSelect[] = db_prefix() . 'projects.owner_id as owner_id';
            }
            if (in_array('manager_id', $fields)) {
                $additionalSelect[] = db_prefix() . 'projects.manager_id as manager_id';
            }
        } catch (Exception $e) {
            // If there's an error checking fields, just continue without owner_id/manager_id
        }
        
        $result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, $additionalSelect);

        $output  = $result['output'];
        $rResult = $result['rResult'];

        foreach ($rResult as $aRow) {
            $row = [];

            $link = admin_url('projects/view/' . $aRow['id']);

            $row[] = '<a href="' . $link . '" class="tw-font-medium">' . $aRow['id'] . '</a>';

            $name = '<a href="' . $link . '" class="tw-font-medium">' . e($aRow['name']) . '</a>';

            $name .= '<div class="row-options">';

            $name .= '<a href="' . $link . '">' . _l('view') . '</a>';

            if ($hasPermissionCreate && !$clientid) {
                $name .= ' | <a href="#" data-name="' . e($aRow['name']) . '" onclick="copy_project(' . $aRow['id'] . ', this);return false;">' . _l('copy_project') . '</a>';
            }

            // Check permissions per row (for project edit/delete, use priority logic)
            // Priority: Staff-level permission first, then project-level permission
            $hasPermissionEdit = can_user_project_action('edit', $aRow['id']);
            $hasPermissionDelete = can_user_project_action('delete', $aRow['id']);

            if ($hasPermissionEdit) {
                $name .= ' | <a href="' . admin_url('projects/project/' . $aRow['id']) . '">' . _l('edit') . '</a>';
            }

            if ($hasPermissionDelete) {
                $name .= ' | <a href="' . admin_url('projects/delete/' . $aRow['id']) . '" class="_delete">' . _l('delete') . '</a>';
            }

            $name .= '</div>';

            $row[] = $name;

            $agencyName   = isset($aRow['client_agency_name']) ? trim((string) $aRow['client_agency_name']) : '';
            $contactName  = isset($aRow['primary_contact_fullname']) ? trim(preg_replace('/\s+/', ' ', (string) $aRow['primary_contact_fullname'])) : '';
            $customerLink = admin_url('clients/client/' . $aRow['clientid']);
            $customerHtml = '<a href="' . $customerLink . '">';
            if ($agencyName !== '') {
                $customerHtml .= '<span class="tw-font-medium">' . e($agencyName) . '</span>';
                if ($contactName !== '') {
                    $customerHtml .= '<br /><small class="tw-text-neutral-500">' . e($contactName) . '</small>';
                }
            } else {
                $customerHtml .= '<span class="tw-font-medium">' . e($contactName !== '' ? $contactName : $aRow['company']) . '</span>';
            }
            $customerHtml .= '</a>';
            $row[] = $customerHtml;

            $row[] = e(_d($aRow['start_date']));

            $progressVal = isset($aRow['calc_progress_display']) ? (float) $aRow['calc_progress_display'] : 0;
            $progressVal = min(100, max(0, $progressVal));
            // Avoid grey sliver at the end from float SQL / rounding (e.g. 99.97%).
            $fillWidth = $progressVal;
            if ($fillWidth >= 99.5) {
                $fillWidth = 100.0;
            }
            if ($fillWidth > 0 && $fillWidth < 0.5) {
                $fillWidth = 0.0;
            }

            $percentForLabel = (int) round($progressVal >= 99.5 ? 100 : $progressVal);
            $percentLabel    = $percentForLabel . ' %';

            $tasksDone      = isset($aRow['progress_tasks_completed']) ? (int) $aRow['progress_tasks_completed'] : 0;
            $tasksRemaining = isset($aRow['progress_tasks_remaining']) ? (int) $aRow['progress_tasks_remaining'] : 0;

            $greenHex = '#22c55e';
            $greyHex  = '#e5e5e5';
            $isFull   = ($fillWidth >= 100);
            $isEmpty  = ($fillWidth <= 0);

            $trackBg = ($isFull ? $greenHex : $greyHex);
            $trackClass = 'project-table-progress-track tw-relative tw-flex-1 tw-min-w-[100px] tw-overflow-hidden';
            $trackStyle = 'height: 1rem; border-radius: 5px; background-color: ' . e($trackBg) . ';';
            $fillStyle = 'background-color: ' . $greenHex . '; width: ' . e((string) $fillWidth) . '%; height: 100%; border-radius: 5px;';
            if ($isEmpty) {
                $fillStyle = 'background-color: ' . $greenHex . '; width: 0; height: 100%; border-radius: 5px;';
            }

            $row[] = '<div class="project-table-progress-wrap tw-flex tw-items-center tw-gap-2 tw-min-w-[220px] tw-max-w-[320px]">'
                . '<span class="tw-tabular-nums tw-text-sm tw-font-medium tw-text-neutral-800 tw-shrink-0 tw-min-w-[1.25rem] tw-text-right">' . e((string) $tasksDone) . '</span>'
                . '<div class="' . $trackClass . '" style="' . $trackStyle . '">'
                . '<div class="project-table-progress-fill tw-absolute tw-top-0 tw-bottom-0 tw-left-0" style="' . $fillStyle . '" aria-hidden="true"></div>'
                . '<span class="tw-absolute tw-inset-0 tw-flex tw-items-center tw-justify-center tw-text-xs tw-font-medium tw-leading-none tw-text-neutral-900 tw-z-[1] tw-pointer-events-none">' . e($percentLabel) . '</span>'
                . '</div>'
                . '<span class="tw-tabular-nums tw-text-sm tw-font-medium tw-text-neutral-800 tw-shrink-0 tw-min-w-[1.25rem] tw-text-left">' . e((string) $tasksRemaining) . '</span>'
                . '</div>'
                . '<span class="hide">' . e($percentLabel) . ' (' . e((string) $tasksDone) . '/' . e((string) ($tasksDone + $tasksRemaining)) . ')</span>';

            $membersOutput = '<div class="tw-flex -tw-space-x-1">';
            $members       = explode(',', $aRow['members']);
            $exportMembers = '';
            foreach ($members as $key => $member) {
                if ($member != '') {
                    $members_ids = explode(',', $aRow['members_ids']);
                    $member_id   = $members_ids[$key];
                    $membersOutput .= '<a href="' . admin_url('profile/' . $member_id) . '">' .
                        staff_profile_image($member_id, [
                            'tw-inline-block tw-h-7 tw-w-7 tw-rounded-full tw-ring-2 tw-ring-white',
                        ], 'small', [
                            'data-toggle' => 'tooltip',
                            'data-title'  => $member,
                        ]) . '</a>';
                    // For exporting
                    $exportMembers .= $member . ', ';
                }
            }

            $membersOutput .= '<span class="hide">' . trim($exportMembers, ', ') . '</span>';
            $membersOutput .= '</div>';
            $row[] = $membersOutput;

            $status = get_project_status_by_id($aRow['status']);
            $row[]  = '<span class="label project-status-' . $aRow['status'] . '" style="color:' . $status['color'] . ';border:1px solid ' . adjust_hex_brightness($status['color'], 0.4) . ';background: ' . adjust_hex_brightness($status['color'], 0.04) . ';">' . e($status['name']) . '</span>';

            // Custom fields add values
            foreach ($customFieldsColumns as $customFieldColumn) {
                $row[] = (strpos($customFieldColumn, 'date_picker_') !== false ? _d($aRow[$customFieldColumn]) : $aRow[$customFieldColumn]);
            }

            $row['DT_RowClass'] = 'has-row-options';

            $row = hooks()->apply_filters('projects_table_row_data', $row, $aRow);

            $output['aaData'][] = $row;
        }
        return $output;
    })->setRules([
        App_table_filter::new('name','TextRule')->label(_l('project_name')),
        App_table_filter::new('start_date','DateRule')->label(_l('project_start_date')),
        App_table_filter::new('billing_type','SelectRule')->label(_l('project_billing_type'))->options(function($ci) {
            return [
                ['value'=>1,'label'=>_l('project_billing_type_fixed_cost')],
                ['value'=>2,'label'=>_l('project_billing_type_project_hours')],
                ['value'=>3,'label'=>_l('project_billing_type_project_task_hours_hourly_rate')],
            ];
        }),
        App_table_filter::new('status','MultiSelectRule')->label(_l('project_status'))->options(function($ci){
                return collect($ci->projects_model->get_project_statuses())->map(fn ($data) => [
                    'value' => $data['id'],
                    'label' => $data['name'],
                ])->all();
        }),

        App_table_filter::new('members', 'MultiSelectRule')->label(_l('project_resources'))
            ->isVisible(fn () => staff_can('view', 'projects'))
            ->options(function ($ci) {
                return collect($ci->projects_model->get_distinct_projects_members())->map(function ($staff) {
                    return [
                        'value' => $staff['staff_id'],
                        'label' => get_staff_full_name($staff['staff_id'])
                    ];
                })->all();
            })->raw(function ($value, $operator, $sqlOperator) {
                $dbPrefix = db_prefix();
                $sqlOperator = $sqlOperator['operator'];
                return "({$dbPrefix}projects.id IN (SELECT project_id FROM {$dbPrefix}project_members WHERE staff_id $sqlOperator ('" . implode("','", $value) . "')))";
            })
    ]);
