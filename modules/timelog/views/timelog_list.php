<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php
/**
 * Partial view for rendering timelog list with grouping (by date or user)
 * This will be loaded via AJAX
 */

$groupBy = isset($timelog_data['group_by']) ? $timelog_data['group_by'] : 'date';

if (empty($timelog_data) || empty($timelog_data['groups'])) {
    ?>
    <div class="timelog-empty-state text-center" style="padding: 40px;">
        <i class="fa fa-clock-o fa-3x" style="color: #ccc;"></i>
        <p style="margin-top: 20px; color: #999;"><?= _l('no_timelogs_found'); ?></p>
    </div>
    <?php
    return;
}
?>

<div class="timelog-grouped-list">
    <div class="table-responsive">
        <table class="table table-timelogs">
            <thead>
                <tr>
                    <th width="30"><input type="checkbox" class="select-all-timelogs"></th>
                    <th><?= _l('log_title'); ?></th>
                    <th><?= _l('project'); ?></th>
                    <th><?= _l('daily_log_hours'); ?></th>
                    <th><?= _l('time_period'); ?></th>
                    <th><?= _l('user'); ?></th>
                    <th><?= _l('billing_type'); ?></th>
                    <th><?= _l('approval_status'); ?></th>
                    <th><?= _l('notes'); ?></th>
                    <!-- <th><?= _l('created_by'); ?></th> -->
                </tr>
            </thead>
            <tbody>
                <?php foreach ($timelog_data['groups'] as $group) { ?>
                    <!-- Group Header Row -->
                    <tr class="timelog-group-header-row">
                        <td colspan="<?= ($groupBy == 'user') ? '11' : '10'; ?>" class="timelog-group-header-cell" style="background: #f8f9fa; border-bottom: 1px solid #e0e0e0;">
                            <?php if ($groupBy == 'date') { ?>
                                <!-- Date Group Header -->
                                <div class="timelog-group-header" data-date="<?= $group['date']; ?>">
                                    <div class="group-header-content">
                                        <button type="button" class="btn-toggle-group" aria-expanded="true">
                                            <i class="fa fa-chevron-down"></i>
                                        </button>
                                        <div class="group-header-info">
                                            <i class="fa fa-calendar"></i>
                                            <span class="group-title"><?= date('d/m/Y', strtotime($group['date'])); ?></span>
                                        </div>
                                        <div class="group-header-totals">
                                            <span class="group-total-hours"><?= seconds_to_time_format($group['total_hours'] * 3600); ?></span>
                                            <?php if ($group['total_billable_hours'] > 0 || $group['total_non_billable_hours'] > 0) { ?>
                                                <span class="group-billable-hours"><?= seconds_to_time_format($group['total_billable_hours'] * 3600); ?></span>
                                                <span class="group-non-billable-hours"><?= seconds_to_time_format($group['total_non_billable_hours'] * 3600); ?></span>
                                            <?php } ?>
                                            <!-- <span class="group-records-count"><?= $group['total_records']; ?></span> -->
                                        </div>
                                    </div>
                                </div>
                            <?php } else { ?>
                                <!-- User Group Header -->
                                <div class="timelog-group-header" data-user-id="<?= $group['user_id']; ?>">
                                    <div class="group-header-content">
                                        <button type="button" class="btn-toggle-group" aria-expanded="true">
                                            <i class="fa fa-chevron-down"></i>
                                        </button>
                                        <div class="group-header-info">
                                            <i class="fa fa-user"></i>
                                            <span class="group-title"><?= e($group['user_name']); ?></span>
                                        </div>
                                        <div class="group-header-totals">
                                            <span class="group-total-hours"><?= seconds_to_time_format($group['total_hours'] * 3600); ?></span>
                                            <?php if ($group['total_billable_hours'] > 0 || $group['total_non_billable_hours'] > 0) { ?>
                                                <span class="group-billable-hours"><?= seconds_to_time_format($group['total_billable_hours'] * 3600); ?></span>
                                                <span class="group-non-billable-hours"><?= seconds_to_time_format($group['total_non_billable_hours'] * 3600); ?></span>
                                            <?php } ?>
                                            <!-- <span class="group-records-count"><?= $group['total_records']; ?></span> -->
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </td>
                    </tr>
                    <!-- Group Data Rows -->
                    <?php foreach ($group['logs'] as $log) { ?>
                        <tr class="timelog-row timelog-group-body-row">
                            <td><input type="checkbox" class="timelog-checkbox" value="<?= $log['id']; ?>"></td>
                            <td class="log-title">
                                <a href="<?= admin_url('tasks/view/' . $log['task_id']); ?>" onclick="init_task_modal(<?= $log['task_id']; ?>); return false;">
                                    <?= e($log['task_name']); ?>
                                </a>
                            </td>
                            <td class="project-name">
                                    <?php if (!empty($log['project_id'])) { ?>
                                        <a href="<?= admin_url('projects/view/' . $log['project_id']); ?>">
                                            <?= e($log['project_name']); ?>
                                        </a>
                                    <?php } else { ?>
                                        <?= e($log['project_name']); ?>
                                    <?php } ?>
                                </td>
                            <td class="log-hours">
                                <?= seconds_to_time_format($log['duration_seconds']); ?>
                            </td>
                            <td class="time-period">
                                <?php if ($groupBy == 'user') { ?>
                                    <?= e(date('d/m/Y', strtotime($log['log_date'])) . ' - ' . $log['time_period']); ?>
                                <?php } else { ?>
                                    <?= e($log['time_period']); ?>
                                <?php } ?>
                            </td>
                            <!-- <?php if ($groupBy == 'user') { ?> -->
                              
                                <td class="staff-name">
                                    <a href="<?= admin_url('staff/profile/' . $log['staff_id']); ?>">
                                        <?= e($log['staff_name']); ?>
                                    </a>
                                </td>
                            <!-- <?php } ?> -->
                            <?php if ($groupBy == 'date') { ?>
                                <td class="staff-name">
                                    <a href="<?= admin_url('staff/profile/' . $log['staff_id']); ?>">
                                        <?= e($log['staff_name']); ?>
                                    </a>
                                </td>
                            <?php } ?>
                            <td class="billing-type">
                                <?php if ($log['billing_type'] == 'billable') { ?>
                                    <span class="label label-info"><?= _l('task_billable'); ?></span>
                                <?php } else { ?>
                                    <span class="label label-warning"><?= _l('task_not_billable'); ?></span>
                                <?php } ?>
                            </td>
                            <td class="approval-status">
                                <?php
                                $status = $log['approval_status'];
                                $statusClass = 'label-warning';
                                $statusText = _l('pending');
                                
                                if ($status == 'approved') {
                                    $statusClass = 'label-success';
                                    $statusText = _l('approved');
                                } elseif ($status == 'rejected') {
                                    $statusClass = 'label-danger';
                                    $statusText = _l('rejected');
                                }
                                ?>
                                <span class="label <?= $statusClass; ?>"><?= $statusText; ?></span>
                            </td>
                            <td class="notes">
                                <?php if (!empty($log['note'])) { ?>
                                    <span data-toggle="tooltip" data-title="">
                                        <i class="fa fa-info-circle"></i>
                                    </span>
                                <?php } else { ?>
                                    -
                                <?php } ?>
                            </td>
                            <!-- <td class="created-by">
                                <?= !empty($log['created_by_name']) ? e($log['created_by_name']) : '-'; ?>
                            </td> -->
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>
