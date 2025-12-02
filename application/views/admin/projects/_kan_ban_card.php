<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<li data-project-id="<?= e($project['id']); ?>" class="project">
    <div class="panel-body">
        <div class="row">
            <div class="col-md-12 project-name">
                <a href="<?= admin_url('projects/view/' . $project['id']); ?>"
                    class="tw-font-medium">
                    <span
                        class="inline-block full-width mtop10 tw-truncate"><?= e($project['name']); ?></span>
                </a>
                <?php if (!empty($project['client_name'])) { ?>
                <a class="tw-text-neutral-600 tw-truncate -tw-mt-1 tw-block tw-text-sm"
                    href="<?= admin_url('clients/client/' . $project['clientid']); ?>">
                    <?= e($project['client_name']); ?>
                </a>
                <?php } ?>
            </div>
            <div class="col-md-12 mtop10 tw-text-sm tw-text-neutral-600">
                <?php if (!empty($project['start_date'])) { ?>
                <span class="mright5" data-toggle="tooltip"
                    data-title="<?= _l('project_start_date'); ?>">
                    <i class="fa fa-calendar-o"></i>
                    <?= e(_d($project['start_date'])); ?>
                </span>
                <?php } ?>
                <?php if (!empty($project['deadline'])) { ?>
                <span class="<?php if ($project['deadline'] < date('Y-m-d') && $project['status'] != 4) {
                    echo 'text-danger';
                } ?>" data-toggle="tooltip"
                    data-title="<?= _l('project_deadline'); ?>">
                    <i class="fa fa-calendar-check-o"></i>
                    <?= e(_d($project['deadline'])); ?>
                </span>
                <?php } ?>
            </div>
            <?php if (!empty($project['progress'])) { ?>
            <div class="col-md-12 mtop10">
                <div class="progress no-margin" style="height:5px;">
                    <div class="progress-bar progress-bar-success no-percent-text" role="progressbar"
                        aria-valuenow="<?= e($project['progress']); ?>" aria-valuemin="0" aria-valuemax="100"
                        style="width: <?= e($project['progress']); ?>%">
                    </div>
                </div>
                <span class="tw-text-xs tw-text-neutral-500"><?= e($project['progress']); ?>%</span>
            </div>
            <?php } ?>
            <div class="col-md-12 mtop10 tw-text-neutral-600 tw-text-sm">
                <?php if ($project['total_members'] > 0) {
                    $this->load->model('projects_model');
                    $members = $this->projects_model->get_project_members($project['id']); ?>
                <span data-toggle="tooltip" data-title="<?= _l('project_members'); ?>">
                    <i class="fa fa-users"></i>
                    <?php foreach ($members as $member) {
                        echo '<a href="' . admin_url('profile/' . $member['staff_id']) . '">' . staff_profile_image($member['staff_id'], ['staff-profile-xs-image tw-inline mright5']) . '</a>';
                    } ?>
                </span>
                <?php } ?>
            </div>
        </div>
        <?php $tags = get_tags_in($project['id'], 'project'); ?>
        <?php if (count($tags) > 0) { ?>
        <div class="kanban-tags tw-text-sm tw-inline-flex mtop10">
            <?= render_tags($tags); ?>
        </div>
        <?php } ?>
    </div>
</li>

