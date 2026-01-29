<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <?= form_open($this->uri->uri_string(), ['id' => 'project_form']); ?>

        <div class="tw-max-w-4xl tw-mx-auto">
            <h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700">
                <?= e($title); ?>
            </h4>
            <div class="panel_s">
                <div class="panel-body">
                    <div class="horizontal-scrollable-tabs panel-full-width-tabs">
                        <div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
                        <div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
                        <div class="horizontal-tabs">
                            <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
                                <li role="presentation" class="active">
                                    <a href="#tab_project" aria-controls="tab_project" role="tab" data-toggle="tab">
                                        <?= _l('project'); ?>
                                    </a>
                                </li>
                                <li role="presentation">
                                    <a href="#tab_settings" aria-controls="tab_settings" role="tab" data-toggle="tab">
                                        <?= _l('project_settings'); ?>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="tab-content tw-mt-3">
                        <div role="tabpanel" class="tab-pane active" id="tab_project">


                            <?php
                        $disable_type_edit = '';
if (isset($project)) {
    if ($project->billing_type != 1) {
        if (total_rows(db_prefix() . 'tasks', ['rel_id' => $project->id, 'rel_type' => 'project', 'billable' => 1, 'billed' => 1]) > 0) {
            $disable_type_edit = 'disabled';
        }
    }
}
?>
                            <?php $value = (isset($project) ? $project->name : ''); ?>
                            <?= render_input('name', 'project_name', $value); ?>
                            <div class="form-group select-placeholder">
                                <label for="clientid"
                                    class="control-label"><?= _l('project_customer'); ?></label>
                                <div class="tw-flex tw-gap-2 tw-items-end">
                                    <div class="tw-flex-1">
                                        <select id="clientid" name="clientid" data-live-search="true" data-width="100%"
                                            class="ajax-search"
                                            data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
                                            <?php $selected = (isset($project) ? $project->clientid : '');
if ($selected == '') {
    $selected = ($customer_id ?? '');
}
if ($selected != '') {
    $rel_data = get_relation_data('customer', $selected);
    $rel_val  = get_relation_values($rel_data, 'customer');
    echo '<option value="' . $rel_val['id'] . '" selected>' . $rel_val['name'] . '</option>';
} ?>
                                        </select>
                                    </div>
                                    <?php if (staff_can('create', 'customers')) { ?>
                                    <button type="button" id="add-customer-btn" class="btn btn-default" 
                                        onclick="open_add_customer_modal(); return false;" 
                                        data-toggle="tooltip" 
                                        data-title="<?= _l('new_client'); ?>">
                                        <i class="fa-regular fa-plus"></i>
                                    </button>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="checkbox">
                                    <input type="checkbox" <?php if ((isset($project) && $project->progress_from_tasks == 1) || ! isset($project)) {
                                        echo 'checked';
                                    } ?> name="progress_from_tasks" id="progress_from_tasks">
                                    <label
                                        for="progress_from_tasks"><?= _l('calculate_progress_through_tasks'); ?></label>
                                </div>
                            </div>
                            <?php
                    if (isset($project) && $project->progress_from_tasks == 1) {
                        $value = $this->projects_model->calc_progress_by_tasks($project->id);
                    } elseif (isset($project) && $project->progress_from_tasks == 0) {
                        $value = $project->progress;
                    } else {
                        $value = 0;
                    }
?>
                            <label
                                for=""><?= _l('project_progress'); ?>
                                <span
                                    class="label_progress"><?= e($value); ?>%</span></label>
                            <?= form_hidden('progress', $value); ?>
                            <div class="project_progress_slider project_progress_slider_horizontal mbot15"></div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group select-placeholder">
                                        <label
                                            for="owner_id"><?= _l('owner'); ?></label>
                                        <div class="clearfix"></div>
                                        <?php
                                        // Get owner_id from project: use saved value when editing, no default when adding
                                        $owner_selected = '';
                                        if (isset($project) && property_exists($project, 'owner_id') && $project->owner_id !== null && $project->owner_id !== '' && $project->owner_id > 0) {
                                            $owner_selected = $project->owner_id;
                                        } elseif (isset($project)) {
                                            // Editing existing project but no owner set: default to Nirav Mehta only for backward compatibility
                                            foreach ($staff as $staff_member) {
                                                $first_name = is_array($staff_member) ? $staff_member['firstname'] : $staff_member->firstname;
                                                $last_name  = is_array($staff_member) ? $staff_member['lastname'] : $staff_member->lastname;
                                                $staff_id   = is_array($staff_member) ? $staff_member['staffid'] : $staff_member->staffid;
                                                $full_name = trim($first_name . ' ' . $last_name);
                                                if (stripos($full_name, 'Nirav Mehta') !== false || stripos($full_name, 'Nirav Maheta') !== false) {
                                                    $owner_selected = $staff_id;
                                                    break;
                                                }
                                            }
                                        }

                                        // Restrict OWNER dropdown to specific staff only
                                        $allowed_owner_names = ['Nirav Mehta', 'Adarsh Verma', 'Kakshak Kalaria'];
                                        $owner_staff         = [];

                                        foreach ($staff as $staff_member) {
                                            $first_name = is_array($staff_member) ? $staff_member['firstname'] : $staff_member->firstname;
                                            $last_name  = is_array($staff_member) ? $staff_member['lastname'] : $staff_member->lastname;
                                            $full_name  = trim($first_name . ' ' . $last_name);
                                            foreach ($allowed_owner_names as $allowed_name) {
                                                if (stripos($full_name, $allowed_name) !== false) {
                                                    $owner_staff[] = $staff_member;
                                                    break;
                                                }
                                            }
                                        }

                                        // Ensure the currently selected owner is still available in edit mode
                                        if (!empty($owner_selected)) {
                                            $owner_exists_in_list = false;
                                            foreach ($owner_staff as $owner_staff_member) {
                                                $staff_id = is_array($owner_staff_member) ? $owner_staff_member['staffid'] : $owner_staff_member->staffid;
                                                if ($staff_id == $owner_selected) {
                                                    $owner_exists_in_list = true;
                                                    break;
                                                }
                                            }

                                            if (!$owner_exists_in_list) {
                                                foreach ($staff as $staff_member) {
                                                    $staff_id = is_array($staff_member) ? $staff_member['staffid'] : $staff_member->staffid;
                                                    if ($staff_id == $owner_selected) {
                                                        $owner_staff[] = $staff_member;
                                                        break;
                                                    }
                                                }
                                            }
                                        }

                                        echo render_select('owner_id', $owner_staff, ['staffid', ['firstname', 'lastname']], '', $owner_selected, ['data-width' => '100%', 'data-none-selected-text' => _l('dropdown_non_selected_tex'), 'data-live-search' => 'true', 'data-size' => '10'], [], '', '', true);
                                        ?>
                                        <input type="hidden" name="owner_id" id="project_owner_id_submit" value="<?= e($owner_selected); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group select-placeholder">
                                        <label
                                            for="manager_id">Manager</label>
                                        <div class="clearfix"></div>
                                        <?php
                                        // Get manager_id from project: use saved value when editing, no default when adding
                                        $manager_selected = '';
                                        if (isset($project) && property_exists($project, 'manager_id') && $project->manager_id !== null && $project->manager_id !== '' && $project->manager_id > 0) {
                                            $manager_selected = $project->manager_id;
                                        } elseif (isset($project)) {
                                            // Editing existing project but no manager set: default to Nirav Mehta only for backward compatibility
                                            foreach ($staff as $staff_member) {
                                                $first_name = is_array($staff_member) ? $staff_member['firstname'] : $staff_member->firstname;
                                                $last_name  = is_array($staff_member) ? $staff_member['lastname'] : $staff_member->lastname;
                                                $staff_id   = is_array($staff_member) ? $staff_member['staffid'] : $staff_member->staffid;
                                                $full_name = trim($first_name . ' ' . $last_name);
                                                if (stripos($full_name, 'Nirav Mehta') !== false || stripos($full_name, 'Nirav Maheta') !== false) {
                                                    $manager_selected = $staff_id;
                                                    break;
                                                }
                                            }
                                        }

                                        // Remove specific names from MANAGER dropdown
                                        // Be deliberately generous with matching to ensure these never appear:
                                        // - Jaimin/Jaymin Patel
                                        // - Parth (any last name containing "sangh")
                                        // - PM Designer (or similar)
                                        $manager_staff = [];

                                        foreach ($staff as $staff_member) {
                                            $first_name = is_array($staff_member) ? $staff_member['firstname'] : $staff_member->firstname;
                                            $last_name  = is_array($staff_member) ? $staff_member['lastname'] : $staff_member->lastname;
                                            $full_name  = trim($first_name . ' ' . $last_name);
                                            $name_lc    = mb_strtolower($full_name);

                                            $exclude_staff = false;

                                            // Match Jaimin/Jaymin Patel
                                            if (preg_match('/ja[yi]min\s+patel/i', $full_name)) {
                                                $exclude_staff = true;
                                            }

                                            // Match any "Parth" with a Sangh* last name variant
                                            if (!$exclude_staff && strpos($name_lc, 'parth') !== false && preg_match('/sangh/i', $full_name)) {
                                                $exclude_staff = true;
                                            }

                                            // Match any PM Designer variants
                                            if (
                                                !$exclude_staff
                                                && (
                                                    strpos($name_lc, 'pm designer') !== false
                                                    || (strpos($name_lc, 'pm') === 0 && strpos($name_lc, 'designer') !== false)
                                                )
                                            ) {
                                                $exclude_staff = true;
                                            }

                                            if (!$exclude_staff) {
                                                $manager_staff[] = $staff_member;
                                            }
                                        }

                                        // Ensure the currently selected manager is still available in edit mode
                                        if (!empty($manager_selected)) {
                                            $manager_exists_in_list = false;
                                            foreach ($manager_staff as $manager_staff_member) {
                                                $staff_id = is_array($manager_staff_member) ? $manager_staff_member['staffid'] : $manager_staff_member->staffid;
                                                if ($staff_id == $manager_selected) {
                                                    $manager_exists_in_list = true;
                                                    break;
                                                }
                                            }

                                            if (!$manager_exists_in_list) {
                                                foreach ($staff as $staff_member) {
                                                    $staff_id = is_array($staff_member) ? $staff_member['staffid'] : $staff_member->staffid;
                                                    if ($staff_id == $manager_selected) {
                                                        $manager_staff[] = $staff_member;
                                                        break;
                                                    }
                                                }
                                            }
                                        }

                                        echo render_select('manager_id', $manager_staff, ['staffid', ['firstname', 'lastname']], '', $manager_selected, ['data-width' => '100%', 'data-none-selected-text' => _l('dropdown_non_selected_tex'), 'data-live-search' => 'true', 'data-size' => '10'], [], '', '', true);
                                        ?>
                                        <input type="hidden" name="manager_id" id="project_manager_id_submit" value="<?= e($manager_selected); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group select-placeholder">
                                        <label
                                            for="billing_type"><?= _l('project_billing_type'); ?></label>
                                        <div class="clearfix"></div>
                                        <select name="billing_type" class="selectpicker" id="billing_type"
                                            data-width="100%"
                                            <?= $disable_type_edit; ?>
                                            data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
                                            <option value=""></option>
                                            <option value="1" <?php if (isset($project) && $project->billing_type == 1 || ! isset($project) && $auto_select_billing_type && $auto_select_billing_type->billing_type == 1) {
                                                echo 'selected';
                                            } ?>><?= _l('project_billing_type_fixed_cost'); ?>
                                            </option>
                                            <option value="2" <?php if (isset($project) && $project->billing_type == 2 || ! isset($project) && $auto_select_billing_type && $auto_select_billing_type->billing_type == 2) {
                                                echo 'selected';
                                            } ?>><?= _l('project_billing_type_project_hours'); ?>
                                            </option>
                                            <!-- <option value="3"
                                                data-subtext="<?= _l('project_billing_type_project_task_hours_hourly_rate'); ?>"
                                                <?php if (isset($project) && $project->billing_type == 3 || ! isset($project) && $auto_select_billing_type && $auto_select_billing_type->billing_type == 3) {
                                                    echo 'selected';
                                                } ?>><?= _l('project_billing_type_project_task_hours'); ?>
                                            </option> -->
                                        </select>
                                        <?php if ($disable_type_edit != '') {
                                            echo '<p class="text-danger tw-mt-1">' . _l('cant_change_billing_type_billed_tasks_found') . '</p>';
                                        } ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group select-placeholder">
                                        <label
                                            for="status"><?= _l('project_status'); ?></label>
                                        <div class="clearfix"></div>
                                        <select name="status" id="status" class="selectpicker" data-width="100%"
                                            data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
                                            <?php foreach ($statuses as $status) { ?>
                                            <option
                                                value="<?= e($status['id']); ?>"
                                                <?php if (isset($project) && $project->status == $status['id']) {
                                                    echo 'selected';
                                                } ?>><?= e($status['name']); ?>
                                            </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <?php if (isset($project) && project_has_recurring_tasks($project->id)) { ?>
                            <div class="alert alert-warning recurring-tasks-notice hide"></div>
                            <?php } ?>
                            <?php if (is_email_template_active('project-finished-to-customer')) { ?>
                            <div class="form-group project_marked_as_finished hide">
                                <div class="checkbox checkbox-primary">
                                    <input type="checkbox" name="project_marked_as_finished_email_to_contacts"
                                        id="project_marked_as_finished_email_to_contacts">
                                    <label
                                        for="project_marked_as_finished_email_to_contacts"><?= _l('project_marked_as_finished_to_contacts'); ?></label>
                                </div>
                            </div>
                            <?php } ?>
                            <?php if (isset($project)) { ?>
                            <div class="form-group mark_all_tasks_as_completed hide">
                                <div class="checkbox checkbox-primary">
                                    <input type="checkbox" name="mark_all_tasks_as_completed"
                                        id="mark_all_tasks_as_completed">
                                    <label
                                        for="mark_all_tasks_as_completed"><?= _l('project_mark_all_tasks_as_completed'); ?></label>
                                </div>
                            </div>
                            <div class="notify_project_members_status_change hide">
                                <div class="checkbox checkbox-primary">
                                    <input type="checkbox" name="notify_project_members_status_change"
                                        id="notify_project_members_status_change">
                                    <label
                                        for="notify_project_members_status_change"><?= _l('notify_project_members_status_change'); ?></label>
                                </div>
                                <hr />
                            </div>
                            <?php } ?>
                            <?php
                    $input_field_hide_class_total_cost = '';
if (! isset($project)) {
    if ($auto_select_billing_type && $auto_select_billing_type->billing_type != 1 || ! $auto_select_billing_type) {
        $input_field_hide_class_total_cost = 'hide';
    }
} elseif (isset($project) && $project->billing_type != 1) {
    $input_field_hide_class_total_cost = 'hide';
}
?>
                            <div id="project_cost"
                                class="<?= e($input_field_hide_class_total_cost); ?>">
                                <?php $value = (isset($project) ? $project->project_cost : ''); ?>
                                <?= render_input('project_cost', 'project_total_cost', $value, 'number'); ?>
                            </div>
                            <?php
$input_field_hide_class_rate_per_hour = '';
if (! isset($project)) {
    if ($auto_select_billing_type && $auto_select_billing_type->billing_type != 2 || ! $auto_select_billing_type) {
        $input_field_hide_class_rate_per_hour = 'hide';
    }
} elseif (isset($project) && $project->billing_type != 2) {
    $input_field_hide_class_rate_per_hour = 'hide';
}
?>
                            <div id="project_rate_per_hour"
                                class="<?= e($input_field_hide_class_rate_per_hour); ?>">
                                <?php $value = (isset($project) ? $project->project_rate_per_hour : ''); ?>
                                <?php
    $input_disable = [];
if ($disable_type_edit != '') {
    $input_disable['disabled'] = true;
}
?>
                                <?= render_input('project_rate_per_hour', 'project_rate_per_hour', $value, 'number', $input_disable); ?>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <?= render_input('estimated_hours', 'estimated_hours', isset($project) ? $project->estimated_hours : '', 'number'); ?>
                                </div>
                                <div class="col-md-6">
                                    <?php
 $selected = [];
if (isset($project_members)) {
    foreach ($project_members as $member) {
        array_push($selected, $member['staff_id']);
    }
} else {
    array_push($selected, get_staff_user_id());
}
echo render_select('project_members[]', $staff, ['staffid', ['firstname', 'lastname']], 'project_members', $selected, ['multiple' => true, 'data-actions-box' => true], [], '', '', false);
?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <?php $value = (isset($project) ? _d($project->start_date) : _d(date('Y-m-d'))); ?>
                                    <?= render_date_input('start_date', 'project_start_date', $value); ?>
                                </div>
                                <div class="col-md-6">
                                    <?php 
                                    $value = (isset($project) ? _d($project->deadline) : '');
                                    $start_date_value = (isset($project) ? $project->start_date : date('Y-m-d'));
                                    $deadline_attrs = [
                                        'data-date-min-date' => $start_date_value, 
                                        'data-date-start-date-ref' => 'start_date',
                                        'placeholder' => _l('project_deadline')
                                    ];
                                    ?>
                                    <?= render_date_input('deadline', 'project_deadline', $value, $deadline_attrs); ?>
                                </div>
                            </div>
                            <?php if (isset($project) && $project->date_finished != null && $project->status == 4) { ?>
                            <?= render_datetime_input('date_finished', 'project_completed_date', _dt($project->date_finished)); ?>
                            <?php } ?>
                            <div class="form-group">
                                <label for="tags" class="control-label"><i class="fa fa-tag" aria-hidden="true"></i>
                                    <?= _l('tags'); ?></label>
                                <?php
                                $tags_value = '';
                                if (isset($project)) {
                                    // Format: Project name – Customer name – Sales person name
                                    // Use first project member, fallback to addedfrom
                                    $sales_person_id = $project->addedfrom; // Default fallback
                                    if (isset($project_members) && !empty($project_members) && is_array($project_members)) {
                                        // Use first member from form data
                                        $first_member_id = reset($project_members);
                                        if (!empty($first_member_id) && is_numeric($first_member_id)) {
                                            $sales_person_id = (int) $first_member_id;
                                        }
                                    } else {
                                        // Get first member from database
                                        $members = $this->projects_model->get_project_members($project->id);
                                        if (!empty($members) && isset($members[0]['staff_id'])) {
                                            $sales_person_id = (int) $members[0]['staff_id'];
                                        }
                                    }
                                    $format_tag = $project->name . ' – ' . get_company_name($project->clientid) . ' – ' . get_staff_full_name($sales_person_id);
                                    $existing_tags = get_tags_in($project->id, 'project');
                                    $existing_tags = array_values(array_filter($existing_tags, function($t) use ($format_tag) { return $t !== $format_tag; }));
                                    array_unshift($existing_tags, $format_tag);
                                    $tags_value = prep_tags_input($existing_tags);
                                }
                                ?>
                                <input type="text" class="tagsinput" id="tags" name="tags"
                                    value="<?= $tags_value; ?>"
                                    data-role="tagsinput">
                            </div>
                            <?php $rel_id_custom_field = (isset($project) ? $project->id : false); ?>
                            <?= render_custom_fields('projects', $rel_id_custom_field); ?>
                            <div class="form-group">
                                <div class="tw-flex tw-items-center tw-justify-between tw-mb-2">
                                    <label class="bold tw-mb-0" style="margin-bottom: 0;">
                                        <?= _l('project_description'); ?>
                                    </label>
                                    <button type="button" id="attach-files-btn" class="btn btn-default btn-sm" style="display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; flex-shrink: 0;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="flex-shrink: 0;">
                                            <path d="M16.5 6v11.5c0 3.31-2.69 6-6 6s-6-2.69-6-6V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v12.5c0 .55-.45 1-1 1s-1-.45-1-1V6H9v11.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-3.31-2.69-6-6-6S2 1.69 2 5v12.5c0 4.42 3.58 8 8 8s8-3.58 8-8V6h-1.5z" stroke="#6B7280" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M16.5 6v11.5c0 3.31-2.69 6-6 6s-6-2.69-6-6V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v12.5c0 .55-.45 1-1 1s-1-.45-1-1V6H9v11.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-3.31-2.69-6-6-6S2 1.69 2 5v12.5c0 4.42 3.58 8 8 8s8-3.58 8-8V6h-1.5z" stroke="#D97706" stroke-width="0.8" fill="none" stroke-linecap="round" stroke-linejoin="round" opacity="0.6"/>
                                        </svg>
                                        <span>Attach Files</span>
                                    </button>
                                </div>
                            </div>
                            <?php $contents = '';
if (isset($project)) {
    $contents = $project->description;
} ?>
                            <?= render_textarea('description', '', $contents, [], [], '', 'tinymce'); ?>
                            
                            <!-- Hidden file input for attachments -->
                            <input type="file" 
                                   id="project-description-file-input" 
                                   style="display: none;" 
                                   multiple 
                                   accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar">

                            <?php if (isset($estimate)) {?>
                            <hr class="hr-panel-separator" />
                            <h5 class="font-medium">
                                <?= _l('estimate_items_convert_to_tasks') ?>
                            </h5>
                            <input type="hidden" name="estimate_id"
                                value="<?= $estimate->id ?>">
                            <div class="row">
                                <?php foreach ($estimate->items as $item) { ?>
                                <div class="col-md-8 border-right">
                                    <div class="checkbox mbot15">
                                        <input type="checkbox" name="items[]"
                                            value="<?= $item['id'] ?>"
                                            checked
                                            id="item-<?= $item['id'] ?>">
                                        <label
                                            for="item-<?= $item['id'] ?>">
                                            <h5 class="no-mbot no-mtop text-uppercase">
                                                <?= $item['description'] ?>
                                            </h5>
                                            <span
                                                class="text-muted"><?= $item['long_description'] ?></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div data-toggle="tooltip"
                                        title="<?= _l('task_single_assignees_select_title'); ?>">
                                        <?= render_select('items_assignee[]', $staff, ['staffid', ['firstname', 'lastname']], '', get_staff_user_id(), ['data-actions-box' => true], [], '', '', false); ?>
                                    </div>
                                </div>
                                <?php } ?>
                            </div>
                            <?php } ?>
                            <hr class="hr-panel-separator" />

                            <?php if (is_email_template_active('assigned-to-project')) { ?>
                            <div class="checkbox checkbox-primary tw-mb-0">
                                <input type="checkbox" name="send_created_email" id="send_created_email">
                                <label
                                    for="send_created_email"><?= _l('project_send_created_email'); ?></label>
                            </div>
                            <?php } ?>
                        </div>
                        <div role="tabpanel" class="tab-pane" id="tab_settings">
                            <div id="project-settings-area">
                                <div class="form-group select-placeholder">
                                    <label for="contact_notification" class="control-label">
                                        <span class="text-danger">*</span>
                                        <?= _l('projects_send_contact_notification'); ?>
                                    </label>
                                    <select name="contact_notification" id="contact_notification"
                                        class="form-control selectpicker"
                                        data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>"
                                        required>
                                        <?php
                    $options = [
                        ['id' => 1, 'name' => _l('project_send_all_contacts_with_notifications_enabled')],
                        ['id' => 2, 'name' => _l('project_send_specific_contacts_with_notification')],
                        ['id' => 0, 'name' => _l('project_do_not_send_contacts_notifications')],
                    ];

foreach ($options as $option) { ?>
                                        <option
                                            value="<?= e($option['id']); ?>"
                                            <?php if ((isset($project) && $project->contact_notification == $option['id'])) {
                                                echo ' selected';
                                            } ?>><?= e($option['name']); ?>
                                        </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <!-- hide class -->
                                <div class="form-group select-placeholder <?= (isset($project) && $project->contact_notification == 2) ? '' : 'hide' ?>"
                                    id="notify_contacts_wrapper">
                                    <label for="notify_contacts" class="control-label"><span
                                            class="text-danger">*</span>
                                        <?= _l('project_contacts_to_notify') ?></label>
                                    <select name="notify_contacts[]" data-id="notify_contacts" id="notify_contacts"
                                        class="ajax-search" data-width="100%" data-live-search="true"
                                        data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>"
                                        multiple>
                                        <?php
                                        $notify_contact_ids = ($project->notify_contacts ?? null) ?
                                            unserialize($project->notify_contacts) :
                                                [];
?>
                                        <?php foreach ($notify_contact_ids as $contact_id) { ?>
                                        <?php $rel_data = get_relation_data('contact', $contact_id); ?>
                                        <?php $rel_val  = get_relation_values($rel_data, 'contact'); ?>
                                        <option
                                            value="<?= $rel_val['id']; ?>"
                                            selected>
                                            <?= $rel_val['name']; ?>
                                        </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <?php foreach ($settings as $setting) {
                                    $checked = ' checked';
                                    if (isset($project)) {
                                        if ($project->settings->{$setting} == 0) {
                                            $checked = '';
                                        }
                                    } else {
                                        foreach ($last_project_settings as $last_setting) {
                                            if ($setting == $last_setting['name']) {
                                                // hide_tasks_on_main_tasks_table is not applied on most used settings to prevent confusions
                                                if ($last_setting['value'] == 0 || $last_setting['name'] == 'hide_tasks_on_main_tasks_table') {
                                                    $checked = '';
                                                }
                                            }
                                        }
                                        if (count($last_project_settings) == 0 && $setting == 'hide_tasks_on_main_tasks_table') {
                                            $checked = '';
                                        }
                                    } ?>
                                <?php if ($setting != 'available_features') { ?>
                                <div class="checkbox">
                                    <input type="checkbox"
                                        name="settings[<?= e($setting); ?>]"
                                        <?= e($checked); ?>
                                    id="<?= e($setting); ?>">
                                    <label for="<?= e($setting); ?>">
                                        <?php if ($setting == 'hide_tasks_on_main_tasks_table') { ?>
                                        <?= _l('hide_tasks_on_main_tasks_table'); ?>
                                        <?php } else { ?>
                                        <?= e(_l('project_allow_client_to', _l('project_setting_' . $setting))); ?>
                                        <?php } ?>
                                    </label>
                                </div>
                                <?php } else { ?>
                                <div class="form-group mtop15 select-placeholder project-available-features">
                                    <label
                                        for="available_features"><?= _l('visible_tabs'); ?></label>
                                    <select
                                        name="settings[<?= e($setting); ?>][]"
                                        id="<?= e($setting); ?>"
                                        multiple="true" class="selectpicker" id="available_features" data-width="100%"
                                        data-actions-box="true" data-hide-disabled="true">
                                        <?php foreach (get_project_tabs_admin() as $tab) {
                                            $selected = '';
                                            if (isset($tab['collapse'])) { ?>
                                        <optgroup
                                            label="<?= e($tab['name']); ?>">
                                            <?php foreach ($tab['children'] as $tab_dropdown) {
                                                $selected = '';
                                                if (isset($project) && (
                                                    (isset($project->settings->available_features[$tab_dropdown['slug']])
                                                                && $project->settings->available_features[$tab_dropdown['slug']] == 1)
                                                            || ! isset($project->settings->available_features[$tab_dropdown['slug']])
                                                )) {
                                                    $selected = ' selected';
                                                } elseif (! isset($project) && count($last_project_settings) > 0) {
                                                    foreach ($last_project_settings as $last_project_setting) {
                                                        if ($last_project_setting['name'] == $setting) {
                                                            if (isset($last_project_setting['value'][$tab_dropdown['slug']])
                                                                    && $last_project_setting['value'][$tab_dropdown['slug']] == 1) {
                                                                $selected = ' selected';
                                                            }
                                                        }
                                                    }
                                                } elseif (! isset($project)) {
                                                    $selected = ' selected';
                                                } ?>
                                            <option
                                                value="<?= e($tab_dropdown['slug']); ?>"
                                                <?= e($selected); ?><?php if (isset($tab_dropdown['linked_to_customer_option']) && is_array($tab_dropdown['linked_to_customer_option']) && count($tab_dropdown['linked_to_customer_option']) > 0) { ?>
                                                data-linked-customer-option="<?= implode(',', $tab_dropdown['linked_to_customer_option']); ?>"
                                                <?php } ?>><?= e($tab_dropdown['name']); ?>
                                            </option>
                                            <?php
                                            } ?>
                                        </optgroup>
                                        <?php } else {
                                            if (isset($project) && (
                                                (isset($project->settings->available_features[$tab['slug']])
                             && $project->settings->available_features[$tab['slug']] == 1)
                            || ! isset($project->settings->available_features[$tab['slug']])
                                            )) {
                                                $selected = ' selected';
                                            } elseif (! isset($project) && count($last_project_settings) > 0) {
                                                foreach ($last_project_settings as $last_project_setting) {
                                                    if ($last_project_setting['name'] == $setting) {
                                                        if (isset($last_project_setting['value'][$tab['slug']])
                                    && $last_project_setting['value'][$tab['slug']] == 1) {
                                                            $selected = ' selected';
                                                        }
                                                    }
                                                }
                                            } elseif (! isset($project)) {
                                                $selected = ' selected';
                                            } ?>
                                        <option
                                            value="<?= e($tab['slug']); ?>"
                                            <?php if ($tab['slug'] == 'project_overview') {
                                                echo ' disabled selected';
                                            } ?>
                                            <?= e($selected); ?>
                                            <?php if (isset($tab['linked_to_customer_option']) && is_array($tab['linked_to_customer_option']) && count($tab['linked_to_customer_option']) > 0) { ?>
                                            data-linked-customer-option="<?= implode(',', $tab['linked_to_customer_option']); ?>"
                                            <?php } ?>>
                                            <?= e($tab['name']); ?>
                                        </option>
                                        <?php } ?>
                                        <?php } ?>
                                    </select>
                                </div>
                                <?php } ?>
                                <hr class="tw-my-3 -tw-mx-8" />
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="panel-footer text-right">
                    <button type="submit" data-form="#project_form" class="btn btn-primary" autocomplete="off"
                        data-loading-text="<?= _l('wait_text'); ?>">
                        <?= _l('submit'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>
<?php init_tail(); ?>
<script>
    <?php if (isset($project)) { ?>
    var original_project_status = '<?= e($project->status); ?>';
    <?php } ?>

    $(function() {

        $contacts_select = $('#notify_contacts'),
            $contacts_wrapper = $('#notify_contacts_wrapper'),
            $clientSelect = $('#clientid'),
            $contact_notification_select = $('#contact_notification');

        // Owner and Manager: use hidden inputs for submit so selected value is always sent (selectpicker often does not sync to native select)
        // Run after selectpicker is initialized (next tick)
        setTimeout(function() {
            var $ownerSelect = $('select#owner_id');
            var $managerSelect = $('select#manager_id');
            if ($ownerSelect.length) {
                $ownerSelect.removeAttr('name');
                $ownerSelect.on('changed.bs.select', function() {
                    var v = $(this).selectpicker('val');
                    v = (v != null && Array.isArray(v)) ? (v[0] || '') : (v || '');
                    $('#project_owner_id_submit').val(v);
                });
            }
            if ($managerSelect.length) {
                $managerSelect.removeAttr('name');
                $managerSelect.on('changed.bs.select', function() {
                    var v = $(this).selectpicker('val');
                    v = (v != null && Array.isArray(v)) ? (v[0] || '') : (v || '');
                    $('#project_manager_id_submit').val(v);
                });
            }
        }, 0);

        init_ajax_search('contacts', $contacts_select, {
            rel_id: $contacts_select.val(),
            type: 'contacts',
            extra: {
                client_id: function() {
                    return $clientSelect.val();
                }
            }
        });

        if ($clientSelect.val() == '') {
            $contacts_select.prop('disabled', true);
            $contacts_select.selectpicker('refresh');
        } else {
            $contacts_select.siblings().find('input[type="search"]').val(' ').trigger('keyup');
        }

        $clientSelect.on('changed.bs.select', function() {
            if ($clientSelect.selectpicker('val') == '') {
                $contacts_select.prop('disabled', true);
            } else {
                $contacts_select.siblings().find('input[type="search"]').val(' ').trigger('keyup');
                $contacts_select.prop('disabled', false);
            }
            deselect_ajax_search($contacts_select[0]);
            $contacts_select.find('option').remove();
            $contacts_select.selectpicker('refresh');
        });

        $contact_notification_select.on('changed.bs.select', function() {
            if ($contact_notification_select.selectpicker('val') == 2) {
                $contacts_select.siblings().find('input[type="search"]').val(' ').trigger('keyup');
                $contacts_wrapper.removeClass('hide');
            } else {
                $contacts_wrapper.addClass('hide');
                deselect_ajax_search($contacts_select[0]);
            }
        });

        $('select[name="billing_type"]').on('change', function() {
            var type = $(this).val();
            if (type == 1) {
                $('#project_cost').removeClass('hide');
                $('#project_rate_per_hour').addClass('hide');
            } else if (type == 2) {
                $('#project_cost').addClass('hide');
                $('#project_rate_per_hour').removeClass('hide');
            } else {
                $('#project_cost').addClass('hide');
                $('#project_rate_per_hour').addClass('hide');
            }
        });

        // Update deadline min date when start date changes
        var $startDate = $('#start_date');
        var $deadline = $('#deadline');
        
        function updateDeadlineMinDate() {
            var startDateValue = $startDate.val();
            if (startDateValue) {
                // Convert to YYYY-MM-DD format if needed
                var dateParts = startDateValue.split(/[-\/]/);
                if (dateParts.length === 3) {
                    // Handle different date formats
                    var year = dateParts[0].length === 4 ? dateParts[0] : dateParts[2];
                    var month = dateParts[0].length === 4 ? dateParts[1] : dateParts[0];
                    var day = dateParts[0].length === 4 ? dateParts[2] : dateParts[1];
                    var formattedDate = year + '-' + month.padStart(2, '0') + '-' + day.padStart(2, '0');
                    
                    // Update the data attribute and reinitialize datepicker
                    $deadline.attr('data-date-min-date', formattedDate);
                    
                    // Destroy and reinitialize datepicker with new min date
                    if ($deadline.data('xdsoft_datetimepicker')) {
                        $deadline.data('xdsoft_datetimepicker').destroy();
                    }
                    
                    var deadlineOpts = {
                        timepicker: false,
                        scrollInput: false,
                        lazyInit: true,
                        format: app.options.date_format,
                        dayOfWeekStart: app.options.calendar_first_day,
                        minDate: formattedDate
                    };
                    
                    $deadline.datetimepicker(deadlineOpts);
                }
            }
        }
        
        // Initialize deadline min date on page load
        if ($startDate.val()) {
            updateDeadlineMinDate();
        }
        
        // Update deadline min date when start date changes
        $startDate.on('change', function() {
            updateDeadlineMinDate();
            
            // If deadline is set and is before new start date, clear it
            var deadlineValue = $deadline.val();
            if (deadlineValue) {
                var startDateValue = $startDate.val();
                if (startDateValue && deadlineValue) {
                    var startDateObj = new Date(startDateValue);
                    var deadlineObj = new Date(deadlineValue);
                    if (deadlineObj < startDateObj) {
                        $deadline.val('');
                    }
                }
            }
        });


        appValidateForm($('form'), {
            name: 'required',
            clientid: 'required',
            start_date: 'required',
            billing_type: 'required',
            deadline: {
                deadlineAfterStartDate: true
            },
            'notify_contacts[]': {
                required: {
                    depends: function() {
                        return !$contacts_wrapper.hasClass('hide');
                    }
                }
            },
        });

        $('select[name="status"]').on('change', function() {
            var status = $(this).val();
            var mark_all_tasks_completed = $('.mark_all_tasks_as_completed');
            var notify_project_members_status_change = $('.notify_project_members_status_change');
            mark_all_tasks_completed.removeClass('hide');
            if (typeof(original_project_status) != 'undefined') {
                if (original_project_status != status) {

                    mark_all_tasks_completed.removeClass('hide');
                    notify_project_members_status_change.removeClass('hide');

                    if (status == 4 || status == 5 || status == 3) {
                        $('.recurring-tasks-notice').removeClass('hide');
                        var notice =
                            "<?= _l('project_changing_status_recurring_tasks_notice'); ?>";
                        notice = notice.replace('{0}', $(this).find('option[value="' + status + '"]')
                            .text()
                            .trim());
                        $('.recurring-tasks-notice').html(notice);
                        $('.recurring-tasks-notice').append(
                            '<input type="hidden" name="cancel_recurring_tasks" value="true">');
                        mark_all_tasks_completed.find('input').prop('checked', true);
                    } else {
                        $('.recurring-tasks-notice').html('').addClass('hide');
                        mark_all_tasks_completed.find('input').prop('checked', false);
                    }
                } else {
                    mark_all_tasks_completed.addClass('hide');
                    mark_all_tasks_completed.find('input').prop('checked', false);
                    notify_project_members_status_change.addClass('hide');
                    $('.recurring-tasks-notice').html('').addClass('hide');
                }
            }

            if (status == 4) {
                $('.project_marked_as_finished').removeClass('hide');
            } else {
                $('.project_marked_as_finished').addClass('hide');
                $('.project_marked_as_finished').prop('checked', false);
            }
        });

        $('form').on('submit', function() {
            // Ensure only hidden inputs are used for Owner/Manager (remove name from selects so they are not submitted)
            var $ownerSelect = $('select#owner_id');
            var $managerSelect = $('select#manager_id');
            $ownerSelect.removeAttr('name');
            $managerSelect.removeAttr('name');
            // Copy current Owner/Manager from selectpicker (or native select) into hidden inputs
            function getSelectValue($sel) {
                if (!$sel.length) return '';
                var v = (typeof $sel.selectpicker === 'function') ? $sel.selectpicker('val') : null;
                if (v != null) return (Array.isArray(v) ? (v[0] || '') : v);
                var opt = $sel.find('option:selected');
                return (opt.length && opt.val()) ? opt.val() : '';
            }
            $('#project_owner_id_submit').val(getSelectValue($ownerSelect));
            $('#project_manager_id_submit').val(getSelectValue($managerSelect));
            $('select[name="billing_type"]').prop('disabled', false);
            $('#available_features,#available_features option').prop('disabled', false);
            $('input[name="project_rate_per_hour"]').prop('disabled', false);
        });

        var progress_input = $('input[name="progress"]');
        var progress_from_tasks = $('#progress_from_tasks');
        var progress = progress_input.val();

        $('.project_progress_slider').slider({
            min: 0,
            max: 100,
            value: progress,
            disabled: progress_from_tasks.prop('checked'),
            slide: function(event, ui) {
                progress_input.val(ui.value);
                $('.label_progress').html(ui.value + '%');
            }
        });

        progress_from_tasks.on('change', function() {
            var _checked = $(this).prop('checked');
            $('.project_progress_slider').slider({
                disabled: _checked
            });
        });

        $('#project-settings-area input').on('change', function() {
            if ($(this).attr('id') == 'view_tasks' && $(this).prop('checked') == false) {
                $('#create_tasks').prop('checked', false).prop('disabled', true);
                $('#edit_tasks').prop('checked', false).prop('disabled', true);
                $('#view_task_comments').prop('checked', false).prop('disabled', true);
                $('#comment_on_tasks').prop('checked', false).prop('disabled', true);
                $('#view_task_attachments').prop('checked', false).prop('disabled', true);
                $('#view_task_checklist_items').prop('checked', false).prop('disabled', true);
                $('#upload_on_tasks').prop('checked', false).prop('disabled', true);
                $('#view_task_total_logged_time').prop('checked', false).prop('disabled', true);
            } else if ($(this).attr('id') == 'view_tasks' && $(this).prop('checked') == true) {
                $('#create_tasks').prop('disabled', false);
                $('#edit_tasks').prop('disabled', false);
                $('#view_task_comments').prop('disabled', false);
                $('#comment_on_tasks').prop('disabled', false);
                $('#view_task_attachments').prop('disabled', false);
                $('#view_task_checklist_items').prop('disabled', false);
                $('#upload_on_tasks').prop('disabled', false);
                $('#view_task_total_logged_time').prop('disabled', false);
            }
        });

        // Auto adjust customer permissions based on selected project visible tabs
        // Eq Project creator disable TASKS tab, then this function will auto turn off customer project option Allow customer to view tasks

        $('#available_features').on('change', function() {
            $("#available_features option").each(function() {
                if ($(this).data('linked-customer-option') && !$(this).is(':selected')) {
                    var opts = $(this).data('linked-customer-option').split(',');
                    for (var i = 0; i < opts.length; i++) {
                        var project_option = $('#' + opts[i]);
                        project_option.prop('checked', false);
                        if (opts[i] == 'view_tasks') {
                            project_option.trigger('change');
                        }
                    }
                }
            });
        });
        $("#view_tasks").trigger('change');
        <?php if (! isset($project)) { ?>
        $('#available_features').trigger('change');
        <?php } ?>

      });
  </script>
<?php if (staff_can('create', 'customers')) { ?>
<?php if (is_admin() || get_option('staff_members_create_inline_customer_groups') == '1') { ?>
<?php $this->load->view('admin/clients/client_group'); ?>
<?php } ?>
<script>
    function open_add_customer_modal() {
        if ($('#client-modal').length === 0) {
            requestGet('clients/form_client').done(function(response) {
                $('body').append(response);
                
                // Initialize form elements
                init_selectpicker();
                init_datepicker();
                custom_fields_hyperlink();
                
                // Set up validation immediately after form is added
                setTimeout(function() {
                    validate_client_form();
                }, 100);
                
                // Reset form when modal is hidden
                $('#client-modal').on('hidden.bs.modal', function() {
                    $('#client-form')[0].reset();
                    $('#client-form').find('.has-error').removeClass('has-error');
                    $('#client-form').find('.text-danger').remove();
                    $('#client-form').find('.alert-danger').remove();
                });
                
                $('#client-modal').modal('show');
            });
        } else {
            // Clear any previous errors
            $('#client-form').find('.alert-danger').remove();
            // Re-initialize validation
            validate_client_form();
            $('#client-modal').modal('show');
        }
    }

    function validate_client_form() {
        var $form = $('#client-form');
        
        // Remove any existing validation to avoid duplicates
        if ($form.data('validator')) {
            $form.data('validator').destroy();
        }
        
        var vRules = {};
        if (app.options.company_is_required == 1) {
            vRules = {
                company: 'required',
            }
        }
        
        // Set up validation with submit handler
        appValidateForm($form, vRules, clientFormHandler);
    }

    function clientFormHandler(form) {
        var $form = $(form);
        var formURL = $form.attr("action");
        var formData = new FormData(form);

        // Show loading state on submit button
        $form.find('button[type="submit"]').button('loading');

        $.ajax({
            type: 'POST',
            data: formData,
            mimeType: "multipart/form-data",
            contentType: false,
            cache: false,
            processData: false,
            url: formURL
        }).done(function(response) {
            try {
                response = typeof response === 'string' ? JSON.parse(response) : response;
            } catch(e) {
                alert_float('danger', 'Invalid response from server');
                return;
            }
            
            if (response.success) {
                // Auto-select the newly created customer first
                if (response.client_id && response.client_name) {
                    var $clientSelect = $('#clientid');
                    
                    // Check if it's an ajax-search select
                    if ($clientSelect.hasClass('ajax-search')) {
                        // For ajax-search, we need to add the option and trigger the select
                        var option = new Option(response.client_name, response.client_id, true, true);
                        $clientSelect.append(option);
                        $clientSelect.val(response.client_id).trigger('change');
                    } else {
                        // For regular selectpicker
                        var option = new Option(response.client_name, response.client_id, true, true);
                        $clientSelect.append(option);
                        $clientSelect.selectpicker('val', response.client_id);
                        $clientSelect.selectpicker('refresh');
                        $clientSelect.trigger('change');
                    }
                }
                
                // Close popup automatically after a short delay to ensure selection is set
                setTimeout(function() {
                    $('#client-modal').modal('hide');
                }, 100);
            } else {
                // Display validation errors inline like standard form
                if (response.message) {
                    // Remove any previous error alerts
                    $('#client-form').find('.alert-danger').remove();
                    
                    // Parse validation errors and display them inline
                    var errorHtml = response.message;
                    if (errorHtml.indexOf('<') !== -1 || errorHtml.indexOf('<p>') !== -1) {
                        // HTML errors from validation_errors()
                        $('#client-form .modal-body').prepend('<div class="alert alert-danger">' + errorHtml + '</div>');
                    } else {
                        alert_float('danger', response.message);
                    }
                }
            }
        }).fail(function(xhr) {
            var errorMsg = 'An error occurred';
            try {
                var errorResponse = typeof xhr.responseText === 'string' ? JSON.parse(xhr.responseText) : xhr.responseText;
                if (errorResponse && errorResponse.message) {
                    errorMsg = errorResponse.message;
                    // Remove any previous error alerts
                    $('#client-form').find('.alert-danger').remove();
                    // Display validation errors inline
                    if (errorResponse.message.indexOf('<') !== -1 || errorResponse.message.indexOf('<p>') !== -1) {
                        $('#client-form .modal-body').prepend('<div class="alert alert-danger">' + errorResponse.message + '</div>');
                    } else {
                        alert_float('danger', errorMsg);
                    }
                } else {
                    // Show full error response for debugging
                    console.error('Server Error:', xhr.status, xhr.responseText);
                    if (xhr.status === 500) {
                        errorMsg = 'Server error occurred. Please check the console for details.';
                    }
                    alert_float('danger', errorMsg);
                }
            } catch(e) {
                console.error('Error parsing response:', e, xhr.responseText);
                if (xhr.responseText) {
                    errorMsg = 'Server error: ' + xhr.responseText.substring(0, 200);
                }
                alert_float('danger', errorMsg);
            }
        }).always(function() {
            // Re-enable submit button
            $('#client-form').find('button[type="submit"]').button('reset');
        });
        
        // Always return false to prevent form submission
        return false;
    }
    
    // Handle "Attach Files" button click
    $('#attach-files-btn').on('click', function() {
        $('#project-description-file-input').click();
    });
    
    // Handle file selection and upload
    $('#project-description-file-input').on('change', function(e) {
        var files = this.files;
        if (files.length === 0) {
            return;
        }
        
        // Disable the attach button while uploading
        var $attachBtn = $('#attach-files-btn');
        var originalText = $attachBtn.html();
        $attachBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Uploading...');
        
        var uploadPromises = [];
        var successCount = 0;
        var errorCount = 0;
        var totalFiles = files.length;
        
        // Upload each file
        for (var i = 0; i < files.length; i++) {
            (function(file, index) {
                var promise = uploadFileToDescription(file, function(success) {
                    if (success) {
                        successCount++;
                    } else {
                        errorCount++;
                    }
                    
                    // Check if all uploads are done
                    if (successCount + errorCount === totalFiles) {
                        // Re-enable button
                        $attachBtn.prop('disabled', false).html(originalText);
                        
                        // Show summary
                        if (errorCount > 0) {
                            alert_float('warning', successCount + ' file(s) uploaded, ' + errorCount + ' failed');
                        } else if (successCount > 0) {
                            // Success message already shown per file
                        }
                    }
                });
                uploadPromises.push(promise);
            })(files[i], i);
        }
        
        // Reset input
        $(this).val('');
    });
    
    function uploadFileToDescription(file, callback) {
        // Validate file type
        var allowedTypes = ['image/', 'video/', 'application/pdf', 'application/msword', 
                          'application/vnd.openxmlformats-officedocument', 'text/', 'application/zip', 'application/x-rar-compressed'];
        var isAllowed = false;
        for (var j = 0; j < allowedTypes.length; j++) {
            if (file.type.indexOf(allowedTypes[j]) === 0) {
                isAllowed = true;
                break;
            }
        }
        
        // Also check by extension
        var fileName = file.name.toLowerCase();
        var allowedExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.pdf', '.doc', '.docx', '.xls', '.xlsx', '.txt', '.zip', '.rar', '.mp4', '.avi', '.mov'];
        var hasAllowedExtension = allowedExtensions.some(function(ext) {
            return fileName.endsWith(ext);
        });
        
        if (!isAllowed && !hasAllowedExtension) {
            var errorMsg = 'File type not allowed: ' + file.name;
            alert_float('danger', errorMsg);
            if (callback) callback(false);
            return $.Deferred().reject(errorMsg).promise();
        }
        
        // Create FormData
        var formData = new FormData();
        formData.append('file', file);
        
        // Get CSRF token - try multiple methods for reliability
        var csrfTokenName = null;
        var csrfTokenHash = null;
        
        // Method 1: Get from form hidden input (CodeIgniter form_open adds this automatically)
        var $csrfInput = $('#project_form').find('input[type="hidden"]').filter(function() {
            return this.name && this.name.toLowerCase().indexOf('csrf') !== -1;
        });
        if ($csrfInput.length > 0) {
            csrfTokenName = $csrfInput.attr('name');
            csrfTokenHash = $csrfInput.val();
        }
        
        // Method 2: Try global csrfData variable (set by csrf_jquery_token())
        if ((!csrfTokenName || !csrfTokenHash) && typeof window.csrfData !== 'undefined' && window.csrfData) {
            if (window.csrfData.token_name && window.csrfData.hash) {
                csrfTokenName = window.csrfData.token_name;
                csrfTokenHash = window.csrfData.hash;
            } else if (window.csrfData.formatted && typeof window.csrfData.formatted === 'object') {
                // Extract from formatted object
                var keys = Object.keys(window.csrfData.formatted);
                if (keys.length > 0) {
                    csrfTokenName = keys[0];
                    csrfTokenHash = window.csrfData.formatted[keys[0]];
                }
            }
        }
        
        // Method 3: Try global csrfData without window prefix (some setups)
        if ((!csrfTokenName || !csrfTokenHash) && typeof csrfData !== 'undefined' && csrfData) {
            if (csrfData.token_name && csrfData.hash) {
                csrfTokenName = csrfData.token_name;
                csrfTokenHash = csrfData.hash;
            } else if (csrfData.formatted && typeof csrfData.formatted === 'object') {
                var keys = Object.keys(csrfData.formatted);
                if (keys.length > 0) {
                    csrfTokenName = keys[0];
                    csrfTokenHash = csrfData.formatted[keys[0]];
                }
            }
        }
        
        // Add CSRF token to FormData if we have it
        if (csrfTokenName && csrfTokenHash) {
            formData.append(csrfTokenName, csrfTokenHash);
            console.log('CSRF token added:', csrfTokenName);
        } else {
            console.error('CSRF token not found. Available methods:', {
                formInput: $csrfInput.length,
                windowCsrfData: typeof window.csrfData,
                csrfData: typeof csrfData
            });
            alert_float('danger', 'CSRF token missing. Please refresh the page and try again.');
            if (callback) callback(false);
            return $.Deferred().reject('CSRF token missing').promise();
        }
        
        // Return promise for upload
        return $.ajax({
            url: admin_url + 'misc/upload_media_file',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                // Handle both string and object responses
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch(e) {
                        console.error('Failed to parse response:', response);
                        alert_float('danger', 'Invalid server response for: ' + file.name);
                        if (callback) callback(false);
                        return;
                    }
                }
                
                if (response && response.success && response.url) {
                    // Insert into TinyMCE editor
                    var editor = tinymce.get('description');
                    if (editor) {
                        // Determine if it's an image
                        if (file.type.indexOf('image/') === 0) {
                            // Insert as image
                            editor.insertContent('<img src="' + response.url + '" alt="' + file.name + '" style="max-width: 100%; height: auto;" />');
                        } else {
                            // Insert as link
                            editor.insertContent('<p><a href="' + response.url + '" target="_blank">' + file.name + '</a></p>');
                        }
                    } else {
                        // Fallback: append to textarea if editor not available
                        var $textarea = $('#description');
                        var currentContent = $textarea.val();
                        if (file.type.indexOf('image/') === 0) {
                            $textarea.val(currentContent + '\n<img src="' + response.url + '" alt="' + file.name + '" />');
                        } else {
                            $textarea.val(currentContent + '\n<a href="' + response.url + '">' + file.name + '</a>');
                        }
                    }
                    if (callback) callback(true);
                } else {
                    var errorMsg = 'Upload failed: ' + file.name;
                    if (response && response.message) {
                        errorMsg = file.name + ': ' + response.message;
                    }
                    alert_float('danger', errorMsg);
                    if (callback) callback(false);
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = 'Error uploading file: ' + file.name;
                console.error('Upload error for', file.name, ':', status, error, xhr);
                
                // Handle 419 Page Expired (CSRF token issue)
                if (xhr.status === 419) {
                    errorMsg = 'Page expired (419). Please refresh the page and try again.';
                    alert_float('warning', errorMsg);
                    // Try to refresh CSRF token from form for next attempt
                    var $csrfInput = $('#project_form').find('input[type="hidden"]').filter(function() {
                        return this.name && this.name.toLowerCase().indexOf('csrf') !== -1;
                    });
                    if ($csrfInput.length > 0 && typeof window.csrfData !== 'undefined') {
                        window.csrfData.hash = $csrfInput.val();
                        if (window.csrfData.formatted) {
                            var tokenName = $csrfInput.attr('name');
                            window.csrfData.formatted[tokenName] = $csrfInput.val();
                        }
                        if (window.csrfData.token_name) {
                            window.csrfData.hash = $csrfInput.val();
                        }
                    }
                    if (callback) callback(false);
                    return;
                }
                
                try {
                    if (xhr.responseText) {
                        var errorResponse = typeof xhr.responseText === 'string' ? JSON.parse(xhr.responseText) : xhr.responseText;
                        if (errorResponse && errorResponse.message) {
                            errorMsg = file.name + ': ' + errorResponse.message;
                        } else if (typeof xhr.responseText === 'string' && xhr.responseText.length < 500 && !xhr.responseText.includes('<html')) {
                            errorMsg = file.name + ': ' + xhr.responseText;
                        }
                    }
                } catch(e) {
                    console.error('Error parsing response:', e, xhr.responseText);
                    if (xhr.status === 404) {
                        errorMsg = file.name + ': Upload endpoint not found (404). Please check the server configuration.';
                    } else if (xhr.status === 500) {
                        errorMsg = file.name + ': Server error (500). Please check server logs.';
                    } else if (xhr.status === 403) {
                        errorMsg = file.name + ': Permission denied (403). Please check file permissions.';
                    } else if (xhr.status === 0) {
                        errorMsg = file.name + ': Network error. Please check your connection.';
                    }
                }
                alert_float('danger', errorMsg);
                if (callback) callback(false);
            }
        });
    }
</script>
<?php } ?>
</body>

</html>