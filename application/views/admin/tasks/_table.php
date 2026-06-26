<?php

defined('BASEPATH') or exit('No direct script access allowed');

$table_data = [
    // _l('the_number_sign'),
    [
        'name'     => _l('the_number_sign'),
        'th_attrs' => ['width' => '50'],
    ],
    _l('tasks_dt_name'),
    // [
    //     'name'     => _l('tasks_dt_name'),
    //     'th_attrs' => ['width' => '300'],
    // ],
    // _l('task_status'),
    [
        'name'     => _l('task_status'),
        'th_attrs' => ['width' => '100'],
    ],
    // _l('tasks_dt_datestart'),
    [
        'name'     => _l('tasks_dt_datestart'),
        'th_attrs' => ['width' => '80', 'class' => 'project-datestart-col'],
    ],
    [
        'name'     => _l('task_duedate'),
        'th_attrs' => ['width' => '80', 'class' => 'duedate'],
    ],
    [
        'name'     => _l('task_assigned'),
        'th_attrs' => ['width' => '100', 'class' => 'project-resources-col'],
    ],
    [
        'name'     => _l('tasks_list_priority'),
        'th_attrs' => ['width' => '80', 'class' => 'project-priority-col'],
    ],
    // _l('tasks_list_priority'),
];

array_unshift($table_data, [
    'name'     => '<span class="hide"> - </span><div class="checkbox mass_select_all_wrap"><input type="checkbox" id="mass_select_all" data-to-table="tasks"><label></label></div>',
    'th_attrs' => ['class' => (isset($bulk_actions) ? '' : 'not_visible')],
]);

$custom_fields = get_custom_fields('tasks', [
    'show_on_table' => 1,
]);

foreach ($custom_fields as $field) {
    array_push($table_data, [
        'name'     => $field['name'],
        'th_attrs' => ['data-type' => $field['type'], 'data-custom-field' => 1],
    ]);
}

// Per-row Notes column (last column, not sortable)
$table_data[] = [
    'name'     => _l('notes'),
    'th_attrs' => ['width' => '260', 'class' => 'task-notes-col', 'data-orderable' => 'false', 'data-searchable' => 'false'],
];

$table_data = hooks()->apply_filters('tasks_table_columns', $table_data);

render_datatable($table_data, 'tasks', ['number-index-' . isset($bulk_actions) ? 2 : 1], [
    'data-last-order-identifier' => 'tasks',
    'data-default-order'         => get_table_last_order('tasks'),
    'id'                         => $table_id ?? 'tasks',
]);
