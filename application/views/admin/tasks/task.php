<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php echo form_open_multipart(admin_url('tasks/task' . ($id ? '/' . $id : '')), ['id' => 'task-form']); ?>
<div class="modal fade<?php if (isset($task)) {
    echo ' edit';
} ?>" id="_task_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" <?php if ($this->input->get('opened_from_lead_id')) {
    echo 'data-lead-id=' . $this->input->get('opened_from_lead_id');
} ?>>
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel">
                    <?php echo e($title); ?>
                </h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <?php
                  $rel_type = '';
                  $rel_id   = '';
                  if (isset($task) || ($this->input->get('rel_id') && $this->input->get('rel_type'))) {
                      $rel_id   = isset($task) ? $task->rel_id : $this->input->get('rel_id');
                      $rel_type = isset($task) ? $task->rel_type : $this->input->get('rel_type');
                  }
                   if (isset($task) && $task->billed == 1) {
                       echo '<div class="alert alert-success text-center no-margin">' . _l('task_is_billed', '<a href="' . admin_url('invoices/list_invoices/' . $task->invoice_id) . '" target="_blank" class="alert-link">' . e(format_invoice_number($task->invoice_id))) . '</a></div><br />';
                   }
                  ?>
                        <?php if (isset($task)) { ?>
                        <div class="pull-right mbot10 task-single-menu task-menu-options">
                            <div class="content-menu hide">
                                <ul>
                                    <?php if (staff_can('create',  'tasks')) { ?>
                                    <?php
                           $copy_template = '';
                           if (total_rows(db_prefix() . 'task_assigned', ['taskid' => $task->id]) > 0) {
                               $copy_template .= "<div class='checkbox checkbox-primary'><input type='checkbox' name='copy_task_assignees' id='copy_task_assignees' checked><label for='copy_task_assignees'>" . _l('task_single_assignees') . '</label></div>';
                           }
                           if (total_rows(db_prefix() . 'task_followers', ['taskid' => $task->id]) > 0) {
                               $copy_template .= "<div class='checkbox checkbox-primary'><input type='checkbox' name='copy_task_followers' id='copy_task_followers' checked><label for='copy_task_followers'>" . _l('task_single_followers') . '</label></div>';
                           }
                           if (total_rows(db_prefix() . 'task_checklist_items', ['taskid' => $task->id]) > 0) {
                               $copy_template .= "<div class='checkbox checkbox-primary'><input type='checkbox' name='copy_task_checklist_items' id='copy_task_checklist_items' checked><label for='copy_task_checklist_items'>" . _l('task_checklist_items') . '</label></div>';
                           }
                           if (total_rows(db_prefix() . 'files', ['rel_id' => $task->id, 'rel_type' => 'task']) > 0) {
                               $copy_template .= "<div class='checkbox checkbox-primary'><input type='checkbox' name='copy_task_attachments' id='copy_task_attachments'><label for='copy_task_attachments'>" . _l('task_view_attachments') . '</label></div>';
                           }

                           $copy_template .= '<p>' . _l('task_status') . '</p>';
                           $task_copy_statuses = hooks()->apply_filters('task_copy_statuses', $task_statuses);
                           foreach ($task_copy_statuses as $copy_status) {
                               $copy_template .= "<div class='radio radio-primary'><input type='radio' value='" . $copy_status['id'] . "' name='copy_task_status' id='copy_task_status_" . $copy_status['id'] . "'" . ($copy_status['id'] == hooks()->apply_filters('copy_task_default_status', 1) ? ' checked' : '') . "><label for='copy_task_status_" . $copy_status['id'] . "'>" . $copy_status['name'] . '</label></div>';
                           }

                           $copy_template .= "<div class='text-center'>";
                           $copy_template .= "<button type='button' data-task-copy-from='" . $task->id . "' class='btn btn-success copy_task_action'>" . _l('copy_task_confirm') . '</button>';
                           $copy_template .= '</div>';
                           ?>
                                    <li> <a href="#" onclick="return false;" data-placement="bottom"
                                            data-toggle="popover"
                                            data-content="<?php echo htmlspecialchars($copy_template); ?>"
                                            data-html="true"><?php echo _l('task_copy'); ?></span></a>
                                    </li>
                                    <?php } ?>
                                    <?php if (staff_can('delete',  'tasks')) { ?>
                                    <li>
                                        <a href="<?php echo admin_url('tasks/delete_task/' . $task->id); ?>"
                                            class="_delete task-delete">
                                            <?php echo _l('task_single_delete'); ?>
                                        </a>
                                    </li>
                                    <?php } ?>
                                </ul>
                            </div>
                            <?php if (staff_can('delete',  'tasks') || staff_can('create',  'tasks')) { ?>
                            <a href="#" onclick="return false;" class="trigger manual-popover mright5">
                                <i class="fa-regular fa-circle fa-sm"></i>
                                <i class="fa-regular fa-circle fa-sm"></i>
                                <i class="fa-regular fa-circle fa-sm"></i>
                            </a>
                            <?php } ?>
                        </div>
                        <?php } ?>
                        <div class="checkbox checkbox-primary checkbox-inline task-add-edit-public tw-pt-2">
                            <input type="checkbox" id="task_is_public" name="is_public" <?php if (isset($task)) {
                               if ($task->is_public == 1) {
                                   echo 'checked';
                               }
                           }; ?>>
                            <label for="task_is_public" data-toggle="tooltip" data-placement="bottom"
                                title="<?php echo _l('task_public_help'); ?>"><?php echo _l('task_public'); ?></label>
                        </div>
                        <div class="checkbox checkbox-primary checkbox-inline task-add-edit-billable tw-pt-2">
                            <input type="checkbox" id="task_is_billable" name="billable" <?php if ((isset($task) && $task->billable == 1) || (!isset($task) && get_option('task_biillable_checked_on_creation') == 1)) {
                               echo ' checked';
                           }?>>
                            <label for="task_is_billable"><?php echo _l('task_billable'); ?></label>
                        </div>
                        <div class="task-visible-to-customer tw-pt-2 checkbox checkbox-inline checkbox-primary<?php if ((isset($task) && $task->rel_type != 'project') || !isset($task) || (isset($task) && $task->rel_type == 'project' && total_rows(db_prefix() . 'project_settings', ['project_id' => $task->rel_id, 'name' => 'view_tasks', 'value' => 0]) > 0)) {
                               echo ' hide';
                           } ?>">
                            <input type="checkbox" id="task_visible_to_client" name="visible_to_client" <?php if (isset($task)) {
                               if ($task->visible_to_client == 1) {
                                   echo 'checked';
                               }
                           } ?>>
                            <label for="task_visible_to_client"><?php echo _l('task_visible_to_client'); ?></label>
                        </div>
                        <?php if (!isset($task)) {
                            // Attachments have been moved to the bottom of the modal and
                            // redesigned as a drag-and-drop dropzone (see below). The
                            // ticket_to_task hidden field stays here where it originally was.
                            if ($this->input->get('ticket_to_task')) {
                                echo form_hidden('ticket_to_task', $rel_id);
                            }
                        } ?>
                        <hr class="-tw-mx-3.5" />
                        <?php $value = (isset($task) ? $task->name : ''); ?>
                        <?php echo render_input('name', 'task_add_edit_subject', $value); ?>
                        <div class="task-hours<?php if (isset($task) && $task->rel_type == 'project' && total_rows(db_prefix() . 'projects', ['id' => $task->rel_id, 'billing_type' => 3]) == 0) {
                            echo ' hide';
                          } ?>">
                            <?php $value = (isset($task) ? $task->hourly_rate : 0); ?>
                            <?php echo render_input('hourly_rate', 'task_hourly_rate', $value); ?>
                        </div>
                        <div class="project-details<?php if ($rel_type != 'project') {
                            echo ' hide';
                          } ?>">
                            <div class="form-group">
                                <label for="milestone"><?php echo _l('task_milestone'); ?></label>
                                <select name="milestone" id="milestone" class="selectpicker" data-width="100%"
                                    data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
                                    <option value=""></option>
                                    <?php foreach ($milestones as $milestone) { ?>
                                    <option value="<?php echo e($milestone['id']); ?>" <?php if (isset($task) && $task->milestone == $milestone['id']) {
                      echo 'selected';
                  } ?>><?php echo e($milestone['name']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <?php if (isset($task)) {
                      $value = _d($task->startdate);
                  } elseif (isset($start_date)) {
                      $value = $start_date;
                  } else {
                      $value = _d(date('Y-m-d'));
                  }
                        $date_attrs = [];
                        if (isset($task) && $task->recurring > 0 && $task->last_recurring_date != null) {
                            $date_attrs['disabled'] = true;
                        }
                        ?>
                                <?php echo render_date_input('startdate', 'task_add_edit_start_date', $value, $date_attrs); ?>
                            </div>
                            <div class="col-md-6">
                                <?php $value = (isset($task) ? _d($task->duedate) : ''); ?>
                                <?php echo render_date_input('duedate', 'task_add_edit_due_date', $value, $project_end_date_attrs); ?>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="priority"
                                        class="control-label"><?php echo _l('task_add_edit_priority'); ?></label>
                                    <select name="priority" class="selectpicker" id="priority" data-width="100%"
                                        data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
                                        <?php foreach (get_tasks_priorities() as $priority) { ?>
                                        <option value="<?php echo e($priority['id']); ?>" <?php if (isset($task) && $task->priority == $priority['id'] || !isset($task) && get_option('default_task_priority') == $priority['id']) {
                            echo ' selected';
                        } ?>><?php echo e($priority['name']); ?></option>
                                        <?php } ?>
                                        <?php hooks()->do_action('task_priorities_select', (isset($task) ? $task : 0)); ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="repeat_every"
                                        class="control-label"><?php echo _l('task_repeat_every'); ?></label>
                                    <select name="repeat_every" id="repeat_every" class="selectpicker" data-width="100%"
                                        data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
                                        <option value=""></option>
                                        <option value="1-week" <?php if (isset($task) && $task->repeat_every == 1 && $task->recurring_type == 'week') {
                            echo 'selected';
                        } ?>><?php echo _l('week'); ?></option>
                                        <option value="2-week" <?php if (isset($task) && $task->repeat_every == 2 && $task->recurring_type == 'week') {
                            echo 'selected';
                        } ?>>2 <?php echo _l('weeks'); ?></option>
                                        <option value="1-month" <?php if (isset($task) && $task->repeat_every == 1 && $task->recurring_type == 'month') {
                            echo 'selected';
                        } ?>>1 <?php echo _l('month'); ?></option>
                                        <option value="2-month" <?php if (isset($task) && $task->repeat_every == 2 && $task->recurring_type == 'month') {
                            echo 'selected';
                        } ?>>2 <?php echo _l('months'); ?></option>
                                        <option value="3-month" <?php if (isset($task) && $task->repeat_every == 3 && $task->recurring_type == 'month') {
                            echo 'selected';
                        } ?>>3 <?php echo _l('months'); ?></option>
                                        <option value="6-month" <?php if (isset($task) && $task->repeat_every == 6 && $task->recurring_type == 'month') {
                            echo 'selected';
                        } ?>>6 <?php echo _l('months'); ?></option>
                                        <option value="1-year" <?php if (isset($task) && $task->repeat_every == 1 && $task->recurring_type == 'year') {
                            echo 'selected';
                        } ?>>1 <?php echo _l('year'); ?></option>
                                        <option value="custom" <?php if (isset($task) && $task->custom_recurring == 1) {
                            echo 'selected';
                        } ?>><?php echo _l('recurring_custom'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="recurring_custom <?php if ((isset($task) && $task->custom_recurring != 1) || (!isset($task))) {
                            echo 'hide';
                        } ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <?php $value = (isset($task) && $task->custom_recurring == 1 ? $task->repeat_every : 1); ?>
                                    <?php echo render_input('repeat_every_custom', '', $value, 'number', ['min' => 1]); ?>
                                </div>
                                <div class="col-md-6">
                                    <select name="repeat_type_custom" id="repeat_type_custom" class="selectpicker"
                                        data-width="100%"
                                        data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
                                        <option value="day" <?php if (isset($task) && $task->custom_recurring == 1 && $task->recurring_type == 'day') {
                            echo 'selected';
                        } ?>><?php echo _l('task_recurring_days'); ?></option>
                                        <option value="week" <?php if (isset($task) && $task->custom_recurring == 1 && $task->recurring_type == 'week') {
                            echo 'selected';
                        } ?>><?php echo _l('task_recurring_weeks'); ?></option>
                                        <option value="month" <?php if (isset($task) && $task->custom_recurring == 1 && $task->recurring_type == 'month') {
                            echo 'selected';
                        } ?>><?php echo _l('task_recurring_months'); ?></option>
                                        <option value="year" <?php if (isset($task) && $task->custom_recurring == 1 && $task->recurring_type == 'year') {
                            echo 'selected';
                        } ?>><?php echo _l('task_recurring_years'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div id="cycles_wrapper" class="<?php if (!isset($task) || (isset($task) && $task->recurring == 0)) {
                            echo ' hide';
                        }?>">
                            <?php $value = (isset($task) ? $task->cycles : 0); ?>
                            <div class="form-group recurring-cycles">
                                <label for="cycles"><?php echo _l('recurring_total_cycles'); ?>
                                    <?php if (isset($task) && $task->total_cycles > 0) {
                            echo '<small>' . e(_l('cycles_passed', $task->total_cycles)) . '</small>';
                        }
                        ?>
                                </label>
                                <div class="input-group">
                                    <input type="number" class="form-control" <?php if ($value == 0) {
                            echo ' disabled';
                        } ?> name="cycles" id="cycles" value="<?php echo e($value); ?>" <?php if (isset($task) && $task->total_cycles > 0) {
                            echo 'min="' . e($task->total_cycles) . '"';
                        } ?>>
                                    <div class="input-group-addon">
                                        <div class="checkbox">
                                            <input type="checkbox" <?php if ($value == 0) {
                            echo ' checked';
                        } ?> id="unlimited_cycles">
                                            <label for="unlimited_cycles"><?php echo _l('cycles_infinity'); ?></label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                        // When adding a plain task (not editing, and not launched from a
                        // specific relation context such as a project/invoice), the
                        // "Related To" selector is hidden and defaults to Project (see the
                        // JS below). The select stays in the DOM so the existing rel_type
                        // change logic keeps working.
                        $hide_task_rel_type = !isset($task) && !$this->input->get('rel_type');
                        ?>
                        <div class="row">
                            <div class="col-md-6"<?php if ($hide_task_rel_type) { echo ' style="display:none;"'; } ?>>
                                <div class="form-group">
                                    <label for="rel_type"
                                        class="control-label"><?php echo _l('task_related_to'); ?></label>
                                    <select name="rel_type" class="selectpicker" id="rel_type" data-width="100%"
                                        data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
                                        <option value=""></option>
                                        <option value="project" <?php if (isset($task) || $this->input->get('rel_type')) {
                            if ($rel_type == 'project') {
                                echo 'selected';
                            }
                        } ?>><?php echo _l('project'); ?></option>
                                        <option value="invoice" <?php if (isset($task) || $this->input->get('rel_type')) {
                            if ($rel_type == 'invoice') {
                                echo 'selected';
                            }
                        } ?>>
                                            <?php echo _l('invoice'); ?>
                                        </option>
                                        <option value="customer" <?php if (isset($task) || $this->input->get('rel_type')) {
                            if ($rel_type == 'customer') {
                                echo 'selected';
                            }
                        } ?>>
                                            <?php echo _l('client'); ?>
                                        </option>
                                        <option value="estimate" <?php if (isset($task) || $this->input->get('rel_type')) {
                            if ($rel_type == 'estimate') {
                                echo 'selected';
                            }
                        } ?>>
                                            <?php echo _l('estimate'); ?>
                                        </option>
                                        <option value="contract" <?php if (isset($task) || $this->input->get('rel_type')) {
                            if ($rel_type == 'contract') {
                                echo 'selected';
                            }
                        } ?>>
                                            <?php echo _l('contract'); ?>
                                        </option>
                                        <option value="ticket" <?php if (isset($task) || $this->input->get('rel_type')) {
                            if ($rel_type == 'ticket') {
                                echo 'selected';
                            }
                        } ?>>
                                            <?php echo _l('ticket'); ?>
                                        </option>
                                        <option value="expense" <?php if (isset($task) || $this->input->get('rel_type')) {
                            if ($rel_type == 'expense') {
                                echo 'selected';
                            }
                        } ?>>
                                            <?php echo _l('expense'); ?>
                                        </option>
                                        <option value="lead" <?php if (isset($task) || $this->input->get('rel_type')) {
                            if ($rel_type == 'lead') {
                                echo 'selected';
                            }
                        } ?>>
                                            <?php echo _l('lead'); ?>
                                        </option>
                                        <option value="proposal" <?php if (isset($task) || $this->input->get('rel_type')) {
                            if ($rel_type == 'proposal') {
                                echo 'selected';
                            }
                        } ?>>
                                            <?php echo _l('proposal'); ?>
                                        </option>
                                        <?php
                                hooks()->do_action('task_modal_rel_type_select', ['task' => (isset($task) ? $task : 0), 'rel_type' => $rel_type]);
                            ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group<?= $rel_id == '' ? ' hide' : ''; ?>" id="rel_id_wrapper">
                                    <label for="rel_id" class="control-label"><span class="rel_id_label"></span></label>
                                    <div id="rel_id_select">
                                        <select name="rel_id" id="rel_id" class="ajax-sesarch" data-width="100%"
                                            data-live-search="true"
                                            data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
                                            <?php if ($rel_id != '' && $rel_type != '') {
                                $rel_data = get_relation_data($rel_type, $rel_id);
                                $rel_val  = get_relation_values($rel_data, $rel_type);
                                echo '<option value="' . $rel_val['id'] . '" selected>' . $rel_val['name'] . '</option>';
                            } ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if (!isset($task)) { ?>
                        <div class="row">
                            <div class="col-md-6">
                            <div class="form-group select-placeholder>">
                                    <label for="assignees"><?php echo _l('task_single_assignees'); ?></label>
                                    <select name="assignees[]" id="assignees" class="selectpicker" data-width="100%"
                                        data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>"
                                        multiple data-live-search="true">
                                        <?php foreach ($members as $member) { ?>
                                        <option value="<?php echo e($member['staffid']); ?>" <?= (get_option('new_task_auto_assign_current_member') == '1') && get_staff_user_id() == $member['staffid'] ? 'selected' : ''; ?>>
                                            <?php echo e($member['firstname'] . ' ' . $member['lastname']); ?>
                                        </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <?php
                     $follower = (get_option('new_task_auto_follower_current_member') == '1') ? [get_staff_user_id()] : '';
                     echo render_select('followers[]', $members, ['staffid', ['firstname', 'lastname']], 'task_single_followers', $follower, ['multiple' => true], [], '', '', false);
                     ?>
                            </div>
                        </div>
                        <?php } ?>

                        <?php
                  if (isset($task)
                     && $task->status == Tasks_model::STATUS_COMPLETE
                     && (staff_can('create', 'tasks') || staff_can('edit', 'tasks'))) {
                      echo render_datetime_input('datefinished', 'task_finished', _dt($task->datefinished));
                  }
               ?>
                        <div class="form-group checklist-templates-wrapper<?php if (count($checklistTemplates) == 0 || isset($task)) {
                   echo ' hide';
               }  ?>">
                            <label for="checklist_items"><?php echo _l('insert_checklist_templates'); ?></label>
                            <select id="checklist_items" name="checklist_items[]"
                                class="selectpicker checklist-items-template-select" multiple="1"
                                data-none-selected-text="<?php echo _l('dropdown_non_selected_tex') ?>"
                                data-width="100%" data-live-search="true" data-actions-box="true">
                                <option value="" class="hide"></option>
                                <?php foreach ($checklistTemplates as $chkTemplate) { ?>
                                <option value="<?php echo e($chkTemplate['id']); ?>">
                                    <?php echo e($chkTemplate['description']); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <div id="inputTagsWrapper">
                                <label for="tags" class="control-label"><i class="fa fa-tag" aria-hidden="true"></i>
                                    <?php echo _l('tags'); ?></label>
                                <input type="text" class="tagsinput" id="tags" name="tags"
                                    value="<?php echo(isset($task) ? prep_tags_input(get_tags_in($task->id, 'task')) : ''); ?>"
                                    data-role="tagsinput">
                            </div>
                        </div>
                        <?php $rel_id_custom_field = (isset($task) ? $task->id : false); ?>
                        <?php echo render_custom_fields('tasks', $rel_id_custom_field); ?>
                        <hr />
                        <p class="bold"><?php echo _l('task_add_edit_description'); ?></p>
                        <?php
               // onclick and onfocus used for convert ticket to task too
               echo render_textarea('description', '', (isset($task) ? $task->description : ''), ['rows' => 6, 'placeholder' => _l('task_add_description'), 'data-task-ae-editor' => true, !is_mobile() ? 'onclick' : 'onfocus' => (!isset($task) || isset($task) && $task->description == '' ? 'init_editor(\'.tinymce-task\', {height:200, auto_focus: true});' : '')], [], 'no-mbot', 'tinymce-task'); ?>
                        <hr />
                        <div class="form-group task-dropzone-wrapper">
                            <label class="control-label"><?php echo _l('add_task_attachments'); ?></label>
                            <div id="task-attachments-dropzone" class="task-dropzone">
                                <input type="file" id="task_attachments_input" name="attachments[]" multiple
                                    extension="<?php echo str_replace('.', '', get_option('allowed_files')); ?>"
                                    filesize="<?php echo file_upload_max_size(); ?>">
                                <div class="task-dropzone-message">
                                    <i class="fa fa-cloud-upload task-dropzone-icon" aria-hidden="true"></i>
                                    <span class="task-dropzone-text"><?php echo _l('drop_files_here_to_upload'); ?></span>
                                    <small class="task-dropzone-hint"><?php echo _l('add_task_attachments'); ?></small>
                                </div>
                            </div>
                            <!-- Single grid holds both already-uploaded attachments (edit mode,
                                 server-rendered below) and newly selected files (added by JS),
                                 so create and edit lay images out identically, side by side. -->
                            <div class="task-dropzone-files" id="task_attachments_files">
                                <?php if (isset($task) && !empty($task_attachments)) { ?>
                                <?php foreach ($task_attachments as $attachment) {
                                    $is_external = !empty($attachment['external']);
                                    $att_path    = get_upload_path_by_type('task') . $task->id . '/' . $attachment['file_name'];
                                    $is_image    = !$is_external ? is_image($att_path) : false;
                                    $href_url    = $is_external
                                        ? $attachment['external_link']
                                        : site_url('download/file/taskattachment/' . $attachment['attachment_key']);
                                    $img_url     = $is_image
                                        ? site_url('download/preview_image?path=' . protected_file_url_by_path($att_path, true) . '&type=' . $attachment['filetype'])
                                        : '';
                                ?>
                                <div class="task-file-square" data-task-attachment-id="<?php echo e($attachment['id']); ?>">
                                    <a href="<?php echo e($href_url); ?>" target="_blank" class="task-file-thumb<?php echo $is_image ? '' : ' task-file-thumb-icon'; ?>">
                                        <?php if ($is_image) { ?>
                                        <img src="<?php echo e($img_url); ?>" alt="">
                                        <?php } else { ?>
                                        <i class="fa fa-file-o" aria-hidden="true"></i>
                                        <?php } ?>
                                    </a>
                                    <span class="task-file-name" title="<?php echo e($attachment['file_name']); ?>"><?php echo e($attachment['file_name']); ?></span>
                                    <?php if ($attachment['staffid'] == get_staff_user_id() || is_admin()) { ?>
                                    <button type="button" class="task-file-remove" aria-label="<?php echo _l('remove'); ?>"
                                        onclick="remove_task_attachment(this, <?php echo e($attachment['id']); ?>); return false;">&times;</button>
                                    <?php } ?>
                                </div>
                                <?php } ?>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
            </div>
        </div>
    </div>
    <?php echo form_close(); ?>
    <script>
    var _rel_id = $('#rel_id'),
        _rel_type = $('#rel_type'),
        _rel_id_wrapper = $('#rel_id_wrapper'),
        _current_member = undefined,
        data = {};

    var _milestone_selected_data;
    _milestone_selected_data = undefined;
    
    // Task form context for project dropdown filtering
    var _is_task_form = true;
    var _is_edit_task = <?php echo isset($task) && !empty($id) ? 'true' : 'false'; ?>;

    <?php if (get_option('new_task_auto_assign_current_member') == '1') { ?>
    _current_member = "<?php echo get_staff_user_id(); ?>";
    <?php } ?>
    $(function() {

        $("body").off("change", "#rel_id");

        var inner_popover_template =
            '<div class="popover"><div class="arrow"></div><div class="popover-inner"><h3 class="popover-title"></h3><div class="popover-content"></div></div></div>';

        $('#_task_modal .task-menu-options .trigger').popover({
            html: true,
            placement: "bottom",
            trigger: 'click',
            title: "<?php echo _l('actions'); ?>",
            content: function() {
                return $('body').find('#_task_modal .task-menu-options .content-menu').html();
            },
            template: inner_popover_template
        });

        custom_fields_hyperlink();

        appValidateForm($('#task-form'), {
            name: 'required',
            startdate: 'required',
            repeat_every_custom: {
                min: 1
            },
        }, task_form_handler);

        $('.rel_id_label').html(_rel_type.find('option:selected').text());

        _rel_type.on('change', function() {

            var clonedSelect = _rel_id.html('').clone();
            _rel_id.selectpicker('destroy').remove();
            _rel_id = clonedSelect;
            $('#rel_id_select').append(clonedSelect);
            $('.rel_id_label').html(_rel_type.find('option:selected').text());

            task_rel_select();
            if ($(this).val() != '') {
                _rel_id_wrapper.removeClass('hide');
            } else {
                _rel_id_wrapper.addClass('hide');
            }
            init_project_details(_rel_type.val());
        });

        init_datepicker();
        init_color_pickers();
        init_selectpicker();
        task_rel_select();

        <?php if ($hide_task_rel_type) { ?>
        // Default a plain new task to a Project relation and reveal the Project
        // dropdown by reusing the standard rel_type change flow. If no project is
        // ultimately selected, the server treats it as a standalone task as before.
        _rel_type.val('project').selectpicker('refresh').trigger('change');
        <?php } ?>

        var _allAssigneeSelect = $("#assignees").html();

        $('body').on('change', '#rel_id', function() {
            if ($(this).val() != '') {
                if (_rel_type.val() == 'project') {
                    $.get(admin_url + 'projects/get_rel_project_data/' + $(this).val() + '/' + taskid,
                        function(project) {
                            $("select[name='milestone']").html(project.milestones);
                            if (typeof(_milestone_selected_data) != 'undefined') {
                                $("select[name='milestone']").val(_milestone_selected_data.id);
                                $('input[name="duedate"]').val(_milestone_selected_data.due_date)
                            }
                            $("select[name='milestone']").selectpicker('refresh');

                            $("#assignees").html(project.assignees);
                            if (typeof(_current_member) != 'undefined') {
                                $("#assignees").val(_current_member);
                            }
                            $("#assignees").selectpicker('refresh')
                            if (project.billing_type == 3) {
                                $('.task-hours').addClass('project-task-hours');
                            } else {
                                $('.task-hours').removeClass('project-task-hours');
                            }

                            if (project.deadline) {
                                var $duedate = $('#_task_modal #duedate');
                                var currentSelectedTaskDate = $duedate.val();
                                $duedate.attr('data-date-end-date', project.deadline);
                                $duedate.datetimepicker('destroy');
                                init_datepicker($duedate);

                                if (currentSelectedTaskDate) {
                                    var dateTask = new Date(unformat_date(currentSelectedTaskDate));
                                    var projectDeadline = new Date(project.deadline);
                                    if (dateTask > projectDeadline) {
                                        $duedate.val(project.deadline_formatted);
                                    }
                                }
                            } else {
                                reset_task_duedate_input();
                            }
                            init_project_details(_rel_type.val(), project.allow_to_view_tasks);
                        }, 'json');



                } else {
                    reset_task_duedate_input();
                }
            }
        });

        <?php if (!isset($task) && $rel_id != '') { ?>
        _rel_id.change();
        <?php } ?>

        _rel_type.on('changed.bs.select', function(e, clickedIndex, isSelected, previousValue) {
            if (previousValue == 'project') {
                $("#assignees").html(_allAssigneeSelect);
                if (typeof(_current_member) != 'undefined') {
                    $("#assignees").val(_current_member);
                }
                $("#assignees").selectpicker('refresh')
            }
        });

        // Task attachments dropzone. The file input fills the drop area (opacity 0),
        // so both clicking to browse and dropping files onto it are handled natively
        // by the browser. Selected files are kept in a DataTransfer that is the single
        // source of truth: it lets us render a removable square per file and keep the
        // input's FileList in sync so everything still submits as attachments[].
        (function() {
            var $dz = $('#task-attachments-dropzone');
            if (!$dz.length || typeof DataTransfer === 'undefined') {
                return;
            }
            var $input = $('#task_attachments_input');
            var input = $input[0];
            var $files = $('#task_attachments_files');
            var store = new DataTransfer();
            var objectUrls = [];

            function syncInput() {
                input.files = store.files;
            }

            function fileKey(f) {
                return f.name + '|' + f.size + '|' + f.lastModified;
            }

            function removeAt(index) {
                var next = new DataTransfer();
                for (var i = 0; i < store.files.length; i++) {
                    if (i !== index) {
                        next.items.add(store.files[i]);
                    }
                }
                store = next;
                syncInput();
                render();
            }

            function render() {
                objectUrls.forEach(function(url) { URL.revokeObjectURL(url); });
                objectUrls = [];
                // Only clear the JS-managed squares; server-rendered existing
                // attachments stay so both flow together in the same grid.
                $files.find('.task-file-square-new').remove();

                for (var i = 0; i < store.files.length; i++) {
                    (function(file, index) {
                        var $square = $('<div class="task-file-square task-file-square-new"></div>');
                        var $thumb;
                        if (file.type && file.type.indexOf('image/') === 0) {
                            var url = URL.createObjectURL(file);
                            objectUrls.push(url);
                            $thumb = $('<div class="task-file-thumb"></div>')
                                .append($('<img alt="">').attr('src', url));
                        } else {
                            $thumb = $('<div class="task-file-thumb task-file-thumb-icon"></div>')
                                .append($('<i class="fa fa-file-o" aria-hidden="true"></i>'));
                        }
                        var $remove = $('<button type="button" class="task-file-remove" aria-label="remove">&times;</button>')
                            .on('click', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                removeAt(index);
                            });
                        $square.append($thumb)
                            .append($('<span class="task-file-name"></span>').text(file.name).attr('title', file.name))
                            .append($remove);
                        $files.append($square);
                    })(store.files[i], i);
                }

                $dz.toggleClass('has-files', $files.children('.task-file-square').length > 0);
            }

            // Reflect any server-rendered existing attachments in the initial state.
            $dz.toggleClass('has-files', $files.children('.task-file-square').length > 0);

            $input.on('dragenter dragover', function() {
                $dz.addClass('dragover');
            }).on('dragleave dragend drop', function() {
                $dz.removeClass('dragover');
            });

            // Native browse/drop replaces the input's FileList with the new selection,
            // so merge those into the store (skipping duplicates) and re-sync.
            $input.on('change', function() {
                var existing = {};
                for (var j = 0; j < store.files.length; j++) {
                    existing[fileKey(store.files[j])] = true;
                }
                var picked = this.files || [];
                for (var i = 0; i < picked.length; i++) {
                    if (!existing[fileKey(picked[i])]) {
                        store.items.add(picked[i]);
                    }
                }
                syncInput();
                render();
            });
        })();

    });

    <?php if (isset($_milestone_selected_data)) { ?>
    _milestone_selected_data = '<?php echo json_encode($_milestone_selected_data); ?>';
    _milestone_selected_data = JSON.parse(_milestone_selected_data);
    <?php } ?>

    function task_rel_select() {
        var serverData = {};
        serverData.rel_id = _rel_id.val();
        serverData.task_form = _is_task_form ? '1' : '0';
        serverData.is_edit_task = _is_edit_task ? '1' : '0';
        data.type = _rel_type.val();
        init_ajax_search(_rel_type.val(), _rel_id, serverData);
    }

    function init_project_details(type, tasks_visible_to_customer) {
        var wrap = $('.non-project-details');
        var wrap_task_hours = $('.task-hours');
        if (type == 'project') {
            if (wrap_task_hours.hasClass('project-task-hours') == true) {
                wrap_task_hours.removeClass('hide');
            } else {
                wrap_task_hours.addClass('hide');
            }
            wrap.addClass('hide');
            $('.project-details').removeClass('hide');
        } else {
            wrap_task_hours.removeClass('hide');
            wrap.removeClass('hide');
            $('.project-details').addClass('hide');
            $('.task-visible-to-customer').addClass('hide').prop('checked', false);
        }
        if (typeof(tasks_visible_to_customer) != 'undefined') {
            if (tasks_visible_to_customer == 1) {
                $('.task-visible-to-customer').removeClass('hide');
                $('.task-visible-to-customer input').prop('checked', true);
            } else {
                $('.task-visible-to-customer').addClass('hide')
                $('.task-visible-to-customer input').prop('checked', false);
            }
        }
    }

    function reset_task_duedate_input() {
        var $duedate = $('#_task_modal #duedate');
        $duedate.removeAttr('data-date-end-date');
        $duedate.datetimepicker('destroy');
        init_datepicker($duedate);
    }
    </script>