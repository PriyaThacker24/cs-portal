<?php

defined('BASEPATH') or exit('No direct script access allowed');

$table_data = [
   _l('the_number_sign'),
    [
         'name'     => _l('project_name'),
         'th_attrs' => ['width' => '230', 'class' => 'project-name-col'],
    ],
    [
         'name'     => _l('project_customer'),
         'th_attrs' => ['width' => '160', 'class' => isset($client) ? 'not_visible' : ''],
    ],
    [
      'name'     => _l('project_start_date'),
      'th_attrs' => ['width' => '100'],
     ],
  //  _l('project_start_date'),
   _l('project_progress'),
    [
         'name'     => _l('project_resources'),
         'th_attrs' => ['width' => '100', 'class' => 'project-resources-col'],
    ],
//    _l('project_status'),
    [
     'name'     => _l('project_status'),
     'th_attrs' => ['width' => '100', 'class' => 'project-status-col'],
    ],
];

$custom_fields = get_custom_fields('projects', ['show_on_table' => 1]);
foreach ($custom_fields as $field) {
    array_push($table_data, [
     'name'     => $field['name'],
     'th_attrs' => ['data-type' => $field['type'], 'data-custom-field' => 1],
 ]);
}

// Per-row Notes column (last column, not sortable)
$table_data[] = [
    'name'     => _l('notes'),
    'th_attrs' => ['width' => '260', 'class' => 'project-notes-col', 'data-orderable' => 'false', 'data-searchable' => 'false'],
];

$table_data = hooks()->apply_filters('projects_table_columns', $table_data);

render_datatable($table_data, isset($class) ?  $class : 'projects', ['number-index-1'], [
  'data-last-order-identifier' => 'projects',
  'data-default-order'         => get_table_last_order('projects'),
  'id'=>$table_id ?? 'projects',
]);