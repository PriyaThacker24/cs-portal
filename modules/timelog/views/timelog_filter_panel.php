<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<!-- Timelog Filter Panel - Zoho Style -->
<div id="timelogFilterPanel" class="project-timelog-filter-panel">
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
                <?php 
                // Pass context to sub panels (for project-specific filtering)
                // Extract variables that were passed to this view and pass them to sub panels
                $sub_panel_data = [];
                if (isset($hide_project_filter)) {
                    $sub_panel_data['hide_project_filter'] = $hide_project_filter;
                }
                if (isset($project_id)) {
                    $sub_panel_data['project_id'] = $project_id;
                }
                $this->load->view('timelog/timelog_filter_sub_panels', $sub_panel_data); 
                ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="filter-panel-footer">
            <div class="filter-match-conditions">
                <label class="radio-label">
                    <input type="radio" name="timelog_filter_match" value="any" checked>
                    <span><?= _l('any_of_these'); ?></span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="timelog_filter_match" value="all">
                    <span><?= _l('all_of_these'); ?></span>
                </label>
            </div>
            <div class="filter-footer-actions">
                <button type="button" class="btn btn-primary btn-timelog-filter-find">
                    <i class="fa fa-search"></i> <?= _l('find'); ?>
                </button>
                <button type="button" class="btn btn-default timelog-filter-reset">
                    <?= _l('reset'); ?>
                </button>
                <button type="button" class="btn btn-default btn-timelog-filter-cancel">
                    <?= _l('cancel'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

