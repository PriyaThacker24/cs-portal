<?php defined('BASEPATH') or exit('No direct script access allowed');

$this->load->model('projects_model');
$project_statuses = $this->projects_model->get_project_statuses();

foreach ($project_statuses as $status) {
    $kanBan = new app\services\projects\ProjectsKanban($status['id']);
    $kanBan->search($this->input->get('search'))
        ->sortBy($this->input->get('sort_by'), $this->input->get('sort'));

    if ($this->input->get('refresh')) {
        $kanBan->refresh($this->input->get('refresh')[$status['id']] ?? null);
    }

    $projects       = $kanBan->get();
    $total_projects = count($projects);
    $total_pages = $kanBan->totalPages(); ?>
<ul class="kan-ban-col projects-kanban"
    data-col-status-id="<?= e($status['id']); ?>"
    data-total-pages="<?= e($total_pages); ?>"
    data-total="<?= e($total_projects); ?>">
    <li class="kan-ban-col-wrapper">
        <div class="border-right panel_s">
            <div class="panel-heading tw-font-medium"
                style="background:<?= e($status['color']); ?>;border-color:<?= e($status['color']); ?>;color:#fff;"
                data-status-id="<?= e($status['id']); ?>">

                <?= e($status['name']); ?>
                -
                <span class="tw-text-sm">
                    <?= $kanBan->countAll() . ' ' . _l('projects') ?>
                </span>

            </div>
            <div class="kan-ban-content-wrapper">
                <div class="kan-ban-content">
                    <ul class="status projects-status sortable relative"
                        data-project-status-id="<?= e($status['id']); ?>">
                        <?php
              foreach ($projects as $project) {
                  if ($project['status'] == $status['id']) {
                      $this->load->view('admin/projects/_kan_ban_card', ['project' => $project, 'status' => $status['id']]);
                  }
              } ?>
                        <?php if ($total_projects > 0) { ?>
                        <li class="text-center not-sortable kanban-load-more"
                            data-load-status="<?= e($status['id']); ?>">
                            <a href="#" class="btn btn-default btn-block<?php if ($total_pages <= 1 || $kanBan->getPage() == $total_pages) {
                                echo ' disabled';
                            } ?>"
                                data-page="<?= $kanBan->getPage(); ?>"
                                onclick="kanban_load_more(<?= e($status['id']); ?>,this,'projects/projects_kanban_load_more',265,360); return false;"
                                ;><?= _l('load_more'); ?></a>
                        </li>
                        <?php } ?>
                        <li class="text-center not-sortable mtop30 kanban-empty<?php if ($total_projects > 0) {
                            echo ' hide';
                        } ?>">
                            <h4>
                                <i class="fa-solid fa-circle-notch" aria-hidden="true"></i><br /><br />
                                <?= _l('no_projects_found'); ?>
                            </h4>
                        </li>
                    </ul>
                </div>
            </div>
    </li>
</ul>
<?php } ?>

