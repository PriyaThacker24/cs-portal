<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<!-- Right Side Drawer for Add Time Log -->
<div class="timelog-drawer-overlay" id="timelog_drawer_overlay" style="display: none;">
    <div class="timelog-drawer" id="timelog_drawer">
        <div class="timelog-drawer-header">
            <h3><?= _l('new_time_log'); ?></h3>
            <button type="button" class="btn-close-drawer" id="btn_close_drawer">
                <i class="fa fa-times"></i>
            </button>
        </div>
        
        <div class="timelog-drawer-body">
            <!-- Time Log Restrictions -->
            <div class="timelog-restrictions-info">
                <h4>
                    <i class="fa fa-info-circle"></i>
                    <?= _l('time_log_restrictions'); ?>
                </h4>
                <ul>
                    <li><?= _l('time_log_restriction_future_dates'); ?></li>
                    <li><?= _l('time_log_restriction_hours_limit'); ?></li>
                    <li><?= _l('time_log_restriction_task_hours'); ?></li>
                    <li><?= _l('time_log_restriction_holiday'); ?></li>
                </ul>
            </div>
            
            <form id="timelog_form" method="post">
                <!-- Project Selection (Always Visible) -->
                <div class="form-group">
                    <label for="timelog_project"><?= _l('project'); ?> <span class="text-danger">*</span></label>
                    <select id="timelog_project" name="project_id" class="form-control selectpicker" data-live-search="true" required>
                        <option value=""><?= _l('select_project'); ?></option>
                        <!-- Projects will be loaded via AJAX -->
                    </select>
                    <div class="help-block"></div>
                </div>
                
                <!-- Remaining Fields (Hidden Initially) -->
                <div id="timelog_other_fields" style="display: none;">
                    <!-- Tasks/Feedback -->
                    <div class="form-group">
                        <label for="timelog_task"><?= _l('tasks_feedback'); ?> <span class="text-danger">*</span></label>
                        <select id="timelog_task" name="task_id" class="form-control selectpicker" data-live-search="true" required>
                            <option value=""><?= _l('search'); ?>...</option>
                            <!-- Tasks will be loaded via AJAX based on selected project -->
                        </select>
                        <div class="help-block">
                            <a href="javascript:void(0);" id="link_enter_general_log"><?= _l('enter_general_log'); ?></a>
                        </div>
                    </div>
                    
                    <!-- Time Log Information Section -->
                    <div class="timelog-info-section">
                        <h5 class="section-title" data-toggle="collapse" data-target="#timelog_info_collapse">
                            <i class="fa fa-chevron-down"></i>
                            <?= _l('time_log_information'); ?>
                        </h5>
                        <div id="timelog_info_collapse" class="collapse in">
                            <!-- Date -->
                            <div class="form-group">
                                <label for="timelog_date"><?= _l('date'); ?> <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" id="timelog_date" name="date" class="form-control datepicker" autocomplete="off" required value="<?= date('d/m/Y'); ?>" data-date-end-date="<?= date('Y-m-d'); ?>">
                                    <span class="input-group-addon">
                                        <i class="fa fa-calendar calendar-icon"></i>
                                    </span>
                                </div>
                                <div class="help-block"></div>
                            </div>
                            
                            <!-- User -->
                            <div class="form-group">
                                <label for="timelog_user">
                                    <?= _l('user'); ?> <span class="text-danger">*</span>
                                    <i class="fa fa-info-circle" data-toggle="tooltip" data-title="<?= _l('user_info_tooltip'); ?>"></i>
                                </label>
                                <select id="timelog_user" name="staff_id" class="form-control selectpicker" data-live-search="true" required>
                                    <option value=""><?= _l('select_user'); ?></option>
                                    <!-- Users will be loaded via AJAX based on selected project -->
                                </select>
                                <div class="help-block"></div>
                            </div>
                            
                            <!-- Daily Log -->
                            <div class="form-group">
                                <label for="timelog_daily_log"><?= _l('daily_log'); ?> <span class="text-danger">*</span></label>
                                <input type="text" id="timelog_daily_log" name="daily_log" class="form-control" placeholder="02:30" pattern="[0-9]{1,2}:[0-5][0-9]" maxlength="5" required>
                                <div class="help-block">
                                    <!-- <small><?= _l('time_format_hint'); ?> (e.g., 02:30 for 2 hours 30 minutes)</small><br> -->
                                    <a href="javascript:void(0);" id="link_set_start_end_time"><?= _l('set_start_end_time'); ?></a>
                                </div>
                            </div>
                            
                            <!-- Billing Type -->
                            <div class="form-group">
                                <label for="timelog_billing_type"><?= _l('billing_type'); ?></label>
                                <select id="timelog_billing_type" name="billing_type" class="form-control">
                                    <option value="billable"><?= _l('task_billable'); ?></option>
                                    <option value="non_billable"><?= _l('task_not_billable'); ?></option>
                                </select>
                            </div>
                            
                            <!-- Notes -->
                            <div class="form-group">
                                <label for="timelog_notes"><?= _l('notes'); ?></label>
                                <textarea id="timelog_notes" name="notes" class="form-control" rows="4"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="timelog-drawer-footer">
            <button type="button" class="btn btn-default" id="btn_cancel_timelog">
                <?= _l('cancel'); ?>
            </button>
            <button type="button" class="btn btn-primary" id="btn_add_timelog_submit">
                <?= _l('add'); ?>
            </button>
        </div>
    </div>
</div>

