<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<link href="<?= base_url('assets/css/projects-filter.css'); ?>" rel="stylesheet" type="text/css" />
<div id="wrapper">
    <div class="content">
        <div id="vueApp">
            <div class="row">
                <div class="col-md-12">
                    <div class="tw-block md:tw-hidden">
                        <?php $this->load->view('admin/projects/stats'); ?>
                    </div>
                    <div class="_buttons">
                        <div class="md:tw-flex md:tw-items-center">
                            <?php if (staff_can('create', 'projects')) { ?>
                            <a href="<?= admin_url('projects/project'); ?>"
                                class="btn btn-primary pull-left display-block mright5">
                                <i class="fa-regular fa-plus tw-mr-1"></i>
                                <?= _l('new_project'); ?>
                            </a>
                            <?php } ?>
                            <a href="<?= admin_url('projects/gantt'); ?>"
                                data-toggle="tooltip"
                                data-title="<?= _l('project_gant'); ?>"
                                class="btn btn-default btn-with-tooltip sm:!tw-px-3">
                                <i class="fa fa-align-left" aria-hidden="true"></i>
                            </a>
                            <a href="<?= admin_url('projects/switch_kanban/' . ($switch_kanban ? 0 : 1)); ?>"
                                class="btn btn-default tw-ml-1 btn-with-tooltip sm:!tw-px-3"
                                data-toggle="tooltip"
                                data-placement="top"
                                data-title="<?= $switch_kanban ? _l('switch_to_list_view') : _l('leads_switch_to_kanban'); ?>">
                                <?php if ($switch_kanban) { ?>
                                <i class="fa-solid fa-table-list"></i>
                                <?php } else { ?>
                                <i class="fa-solid fa-grip-vertical"></i>
                                <?php } ?>
                            </a>
                            <div class="tw-hidden md:tw-block md:tw-ml-6 rtl:md:tw-mr-6">
                                <?php $this->load->view('admin/projects/stats'); ?>
                            </div>
                            <div class="ltr:tw-ml-auto rtl:tw-mr-auto tw-flex tw-items-center tw-gap-2">
                                <!-- Zoho-Style Filter Button -->
                                <?php $this->load->view('admin/projects/filter_panel'); ?>
                                <!-- <app-filters
                                    id="<?= $table->id(); ?>"
                                    view="<?= $table->viewName(); ?>"
                                    :rules="extra.projectsRules || <?= app\services\utilities\Js::from($this->input->get('status') ? $table->findRule('status')->setValue([(int) $this->input->get('status')]) : []); ?>"
                                    :saved-filters="<?= $table->filtersJs(); ?>"
                                    :available-rules="<?= $table->rulesJs(); ?>">
                                </app-filters> -->
                            </div>
                        </div>
                        <div class="clearfix"></div>
                    </div>

                    <?php if ($switch_kanban) { ?>
                    <!-- Kanban View -->
                    <div class="kan-ban-tab tw-mt-6" id="kan-ban-tab" style="overflow:auto;">
                        <div class="row">
                            <div id="kanban-params"></div>
                            <div class="container-fluid">
                                <div id="kan-ban"></div>
                            </div>
                        </div>
                    </div>
                    <?php } else { ?>
                    <!-- Table View -->
                    <div class="panel_s tw-mt-2">
                        <div class="panel-body">
                            <div class="panel-table-full">
                                <?= form_hidden('custom_view'); ?>
                                <?php $this->load->view('admin/projects/table_html'); ?>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $this->load->view('admin/projects/copy_settings'); ?>
<?php init_tail(); ?>
<?php if (!$switch_kanban) { ?>
<script src="<?= base_url('assets/js/projects-filter.js'); ?>"></script>
<script>
    $(function() {
        initDataTable('.table-projects', admin_url + 'projects/table', undefined, undefined, {},
            <?= hooks()->apply_filters('projects_table_default_order', json_encode([5, 'asc'])); ?>
        );

        init_ajax_search('customer', '#clientid_copy_project.ajax-search');
    });
</script>
<?php } else { ?>
<script src="<?= base_url('assets/js/projects' . (ENVIRONMENT === 'production' ? '.min' : '') . '.js'); ?>"></script>
<script>
    $(function() {
        projects_kanban();
        init_ajax_search('customer', '#clientid_copy_project.ajax-search');
    });
</script>
<?php } ?>
</body>

</html>