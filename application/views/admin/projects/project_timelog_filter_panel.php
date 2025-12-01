<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<!-- Project Timelog Filter Panel - Zoho Style -->
<div id="projectTimelogFilterPanel" class="project-timelog-filter-panel">
    <div class="filter-panel-overlay"></div>
    <div class="filter-panel-content">
        <!-- Header -->
        <div class="filter-header">
            <h3><?= _l('filter'); ?></h3>
            <button type="button" class="filter-close" aria-label="<?= _l('close'); ?>">
                <i class="fa fa-times" aria-hidden="true"></i>
            </button>
        </div>

        <div class="filter-panel-body">
            <!-- Filter Search -->
            <div class="filter-search-wrapper">
                <i class="fa fa-search" aria-hidden="true"></i>
                <input type="text" class="filter-search-input" placeholder="<?= _l('filter_search'); ?>">
            </div>

            <!-- Filter Accordions -->
            <div class="filter-accordion">
                <?php $this->load->view('admin/projects/project_timelog_filter_sub_panels'); ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="filter-panel-footer">
            <div class="filter-match-conditions">
                <label class="radio-label">
                    <input type="radio" name="project_timelog_filter_match" value="any" checked>
                    <span><?= _l('any_of_these'); ?></span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="project_timelog_filter_match" value="all">
                    <span><?= _l('all_of_these'); ?></span>
                </label>
            </div>
            <div class="filter-footer-actions">
                <button type="button" class="btn btn-primary btn-project-timelog-filter-find">
                    <i class="fa fa-search"></i> <?= _l('find'); ?>
                </button>
                <button type="button" class="btn btn-default project-timelog-filter-reset">
                    <?= _l('reset'); ?>
                </button>
                <button type="button" class="btn btn-default btn-project-timelog-filter-cancel">
                    <?= _l('cancel'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Filter Trigger Button -->
<button type="button" class="btn btn-default btn-project-timelog-filter-trigger" id="btnOpenProjectTimelogFilter">
    <i class="fa fa-filter"></i> <?= _l('filters'); ?>
</button>

