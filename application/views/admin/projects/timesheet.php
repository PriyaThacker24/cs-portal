<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!-- Timesheet Modal -->
<div class="modal fade" id="timesheet" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <?php echo form_open(admin_url('projects/timesheet'), ['id' => 'timesheet_form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    <span class="add-title"><?php echo _l('record_timesheet'); ?></span>
                </h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <?php echo form_hidden('project_id', $project->id); ?>
                        <?php echo form_hidden('timer_id'); ?>
                        <div id="additional"></div>
                        <div class="row">
                        <div class="col-md-12">
                               <div class="form-group">
                            <label for="tags" class="control-label"><i class="fa fa-tag" aria-hidden="true"></i> <?php echo _l('tags'); ?></label>
                            <input type="text" class="tagsinput" id="tags" name="tags" value="" data-role="tagsinput">
                            <hr class="no-mtop" />
                        </div>
                        </div>
                        <div class="timesheet-start-end-time">
                            <div class="col-md-12">
                                <div class="form-group no-mbot">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="control-label" for="start_time"><?php echo _l('project_timesheet_start_time'); ?></label>
                                    </div>
                                    <div class="col-md-9">
                                        <?php echo render_datetime_input('start_time'); ?>
                                    </div>
                                </div>
                                </div>
                            </div>

                           <div class="col-md-12">
                                <div class="form-group no-mbot">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="control-label" for="end_time"><?php echo _l('project_timesheet_end_time'); ?></label>
                                    </div>
                                    <div class="col-md-9">
                                        <?php echo render_datetime_input('end_time'); ?>
                                    </div>
                                </div>
                                </div>
                            </div>
                        </div>
                        <div class="timesheet-duration hide">
                         <div class="col-md-12">
                            <div class="form-group no-mbot">
                                <div class="row">
                                    <div class="col-md-3 popover-250">
                                        <label class="control-label" for="timesheet_duration">
                                              <?php echo _l('project_timesheet_time_spend'); ?>
                                        </label>
                                         <i class="fa-regular fa-circle-question pointer" data-toggle="popover" data-html="true" data-content="
                                         :15 - 15 <?php echo _l('minutes'); ?><br />
                                         2 - 2 <?php echo _l('hours'); ?><br />
                                         5:5 - 5 <?php echo _l('hours'); ?> & 5 <?php echo _l('minutes'); ?><br />
                                         2:50 - 2 <?php echo _l('hours'); ?> & 50 <?php echo _l('minutes'); ?><br />
                                         "></i>
                                    </div>
                                    <div class="col-md-9">
                                        <?php echo render_input('timesheet_duration', '', '', 'text', ['placeholder' => 'HH:MM']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-9 col-md-offset-3 mbot15 mntop15">
                           <a href="#" class="timesheet-toggle-enter-type">
                             <span class="timesheet-duration-toggler-text switch-to">
                               <?php echo _l('timesheet_duration_instead'); ?>
                           </span>
                           <span class="timesheet-date-toggler-text hide ">
                               <?php echo _l('timesheet_date_instead'); ?>
                           </span>
                       </a>
                   </div>
                     </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label for="timesheet_task_id"><?php echo _l('project_timesheet_task'); ?></label>
                                    </div>
                                    <div class="col-md-9">
                                         <div class="form-group">
                                                <select name="timesheet_task_id" id="timesheet_task_id" class="selectpicker" data-live-search="true" data-width="100%" data-none-selected-text="-">
                                            <option value=""></option>
                                            <?php 
                                            // Check permissions: staff-level "Task Add" OR project-level "Task Add"
                                            $currentUserId = get_staff_user_id();
                                            $currentProjectId = $project->id;
                                            $canViewAllTasks = false;
                                            
                                            // Priority 1: Staff-level "Task Add" permission
                                            if (staff_can('create', 'tasks')) {
                                                $canViewAllTasks = true;
                                            }
                                            
                                            // Priority 2: Project-level "Task Add" permission
                                            if (!$canViewAllTasks && $currentProjectId > 0) {
                                                $this->load->model('projects_model');
                                                if ($this->projects_model->hasProjectPermission($currentUserId, $currentProjectId, 'task_create')) {
                                                    $canViewAllTasks = true;
                                                }
                                            }
                                            
                                            // Fetch ALL tasks for this project directly (not relying on pre-filtered $tasks)
                                            $this->load->model('tasks_model');
                                            
                                            if ($canViewAllTasks) {
                                                // User has Task Add permission - fetch ALL tasks (no status filter)
                                                $allProjectTasks = $this->db->select('id, name, status')
                                                    ->from(db_prefix() . 'tasks')
                                                    ->where('rel_type', 'project')
                                                    ->where('rel_id', $currentProjectId)
                                                    ->order_by('name', 'ASC')
                                                    ->get()
                                                    ->result_array();
                                                
                                                foreach ($allProjectTasks as $task) {
                                                    echo '<option value="' . $task['id'] . '">' . e($task['name']) . '</option>';
                                                }
                                            } else {
                                                // User does NOT have Task Add permission - show only assigned tasks
                                                $assignedTasks = $this->db->select(db_prefix() . 'tasks.id, ' . db_prefix() . 'tasks.name, ' . db_prefix() . 'tasks.status')
                                                    ->from(db_prefix() . 'tasks')
                                                    ->join(db_prefix() . 'task_assigned', db_prefix() . 'task_assigned.taskid = ' . db_prefix() . 'tasks.id')
                                                    ->where(db_prefix() . 'tasks.rel_type', 'project')
                                                    ->where(db_prefix() . 'tasks.rel_id', $currentProjectId)
                                                    ->where(db_prefix() . 'task_assigned.staffid', $currentUserId)
                                                    ->order_by(db_prefix() . 'tasks.name', 'ASC')
                                                    ->get()
                                                    ->result_array();
                                                
                                                foreach ($assignedTasks as $task) {
                                                    echo '<option value="' . $task['id'] . '">' . e($task['name']) . '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                         </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php 
                        // Get current logged-in user info
                        $currentStaffId = get_staff_user_id();
                        $currentStaffName = get_staff_full_name($currentStaffId);
                        ?>
                        <div class="row mtop15">
                            <div class="col-md-12">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label for="timesheet_staff_id"><?php echo _l('project_timesheet_user'); ?></label>
                                    </div>
                                    <div class="col-md-9">
                                      <div class="form-group">
                                            <select name="timesheet_staff_id" id="timesheet_staff_id" class="selectpicker" data-live-search="true" data-width="100%" data-none-selected-text="-">
                                            <option value="<?php echo $currentStaffId; ?>" selected><?php echo e($currentStaffName); ?></option>
                                        </select>
                                        <input type="hidden" id="current_staff_id" value="<?php echo $currentStaffId; ?>">
                                        <input type="hidden" id="current_staff_name" value="<?php echo e($currentStaffName); ?>">
                                      </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mtop15">
                            <div class="col-md-12">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label for="bill_type"><?php echo _l('billing_type'); ?></label>
                                    </div>
                                    <div class="col-md-9">
                                      <div class="form-group">
                                            <select name="bill_type" id="bill_type" class="selectpicker" data-width="100%" data-none-selected-text="-">
                                            <option value="billable" selected><?php echo _l('billable'); ?></option>
                                            <option value="non_billable"><?php echo _l('non_billable'); ?></option>
                                        </select>
                                      </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                          <div class="row mtop15">
                            <div class="col-md-12">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label for="note"><?php echo _l('note'); ?></label>
                                    </div>
                                    <div class="col-md-9">
                                      <?php echo render_textarea('note'); ?>
                                    </div>
                                </div>
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
        <!-- /.modal-content -->
        <?php echo form_close(); ?>
    </div>
    <!-- /.modal-dialog -->
</div>
<!-- /.modal -->
<!-- Timesheet Modal End -->
