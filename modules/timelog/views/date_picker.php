<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="timelog-date-picker-wrapper" id="timelog_date_picker_wrapper" style="display: none;">
    <div class="timelog-date-picker">
        <!-- Header with navigation and current selection -->
        <div class="date-picker-header">
            <button type="button" class="btn-nav-date" id="date_picker_prev_range" title="<?= _l('previous'); ?>">
                <i class="fa fa-chevron-left"></i>
            </button>
            <div class="date-picker-current-selection">
                <i class="fa fa-calendar"></i>
                <span id="date_picker_selected_range">
                    <?= date('d/m/Y', strtotime($week_start)); ?> - <?= date('d/m/Y', strtotime($week_end)); ?> (<?= _l('week'); ?> <?= date('W', strtotime($week_start)); ?>)
                </span>
            </div>
            <button type="button" class="btn-nav-date" id="date_picker_next_range" title="<?= _l('next'); ?>">
                <i class="fa fa-chevron-right"></i>
            </button>
        </div>

        <!-- Filter Tabs -->
        <div class="date-picker-tabs">
            <button type="button" class="date-picker-tab" data-type="day" id="tab_day"><?= _l('day'); ?></button>
            <button type="button" class="date-picker-tab active" data-type="week" id="tab_week"><?= _l('week'); ?></button>
            <button type="button" class="date-picker-tab" data-type="month" id="tab_month"><?= _l('month'); ?></button>
            <button type="button" class="date-picker-tab" data-type="range" id="tab_range"><?= _l('range'); ?></button>
            <?php /* Project Span only makes sense in a single-project context (Project
                     Details -> Timelog). It is hidden in the main Timelog module,
                     where no single project is selected. */ ?>
            <?php if (!empty($show_project_span)) : ?>
            <button type="button" class="date-picker-tab" data-type="quick" id="tab_quick"><?= _l('project_span'); ?></button>
            <?php endif; ?>
        </div>

        <!-- Calendar View (for Day and Week tabs) -->
        <div class="date-picker-calendar" id="date_picker_calendar">
            <!-- Month/Year Navigation -->
            <div class="calendar-header">
                <button type="button" class="btn-nav-year" id="calendar_prev_year">
                    <i class="fa fa-angle-double-left"></i>
                </button>
                <button type="button" class="btn-nav-month" id="calendar_prev_month">
                    <i class="fa fa-angle-left"></i>
                </button>
                <span class="calendar-month-year" id="calendar_month_year">
                    <?= date('M Y', strtotime($week_start)); ?>
                </span>
                <button type="button" class="btn-nav-month" id="calendar_next_month">
                    <i class="fa fa-angle-right"></i>
                </button>
                <button type="button" class="btn-nav-year" id="calendar_next_year">
                    <i class="fa fa-angle-double-right"></i>
                </button>
            </div>

            <!-- Calendar Grid -->
            <div class="calendar-grid" id="calendar_grid">
                <!-- Will be populated by JavaScript -->
            </div>
        </div>

        <!-- Month Picker View (shown when Month tab is selected) -->
        <div class="date-picker-month-view" id="date_picker_month_view" style="display: none;">
            <!-- Year Navigation -->
            <div class="month-year-navigation">
                <button type="button" class="btn-nav-year-month" id="month_prev_year">
                    <i class="fa fa-chevron-left"></i>
                </button>
                <span class="month-year-display" id="month_year_display">
                    <?= date('Y', strtotime($week_start)); ?>
                </span>
                <button type="button" class="btn-nav-year-month" id="month_next_year">
                    <i class="fa fa-chevron-right"></i>
                </button>
            </div>

            <!-- Month Grid (3 rows x 4 columns) -->
            <div class="month-grid" id="month_grid">
                <!-- Will be populated by JavaScript -->
            </div>
        </div>

        <!-- Range Picker (shown when Range tab is selected) - Dual Calendar View -->
        <div class="date-picker-range" id="date_picker_range" style="display: none;">
            <!-- Calendar Navigation Header -->
            <div class="range-calendar-header">
                <button type="button" class="btn-nav-year-range" id="range_prev_year">
                    <i class="fa fa-angle-double-left"></i>
                </button>
                <button type="button" class="btn-nav-month-range" id="range_prev_month">
                    <i class="fa fa-angle-left"></i>
                </button>
                <span class="range-month-year-left" id="range_month_year_left">
                    <?= date('F Y', strtotime($week_start)); ?>
                </span>
                <span class="range-month-year-right" id="range_month_year_right">
                    <?= date('F Y', strtotime('+1 month', strtotime($week_start))); ?>
                </span>
                <button type="button" class="btn-nav-month-range" id="range_next_month">
                    <i class="fa fa-angle-right"></i>
                </button>
                <button type="button" class="btn-nav-year-range" id="range_next_year">
                    <i class="fa fa-angle-double-right"></i>
                </button>
            </div>

            <!-- Dual Calendar Grid -->
            <div class="range-calendars-container">
                <!-- Left Calendar -->
                <div class="range-calendar-left" id="range_calendar_left">
                    <div class="range-calendar-grid" id="range_calendar_grid_left">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>

                <!-- Right Calendar -->
                <div class="range-calendar-right" id="range_calendar_right">
                    <div class="range-calendar-grid" id="range_calendar_grid_right">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Project Span (shown when Project Span tab is selected) -->
        <div class="date-picker-quick" id="date_picker_quick" style="display: none;">
            <div class="project-span-content">
                <!-- Calendar Illustration -->
                <div class="project-span-calendar-illustration">
                    <?php
                    $span_img_path = module_dir_path('timelog', 'assets/images/project-span.svg');
                    $span_img_ver  = is_file($span_img_path) ? filemtime($span_img_path) : '';
                    ?>
                    <img src="<?= module_dir_url('timelog', 'assets/images/project-span.svg'); ?>?v=<?= $span_img_ver; ?>" alt="<?= _l('project_span'); ?>">
                </div>
                <!-- Descriptive Text -->
                <div class="project-span-text">
                    View all Time Logs in this project
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="date-picker-actions">
            <button type="button" class="btn-link-current" id="btn_current_week"><?= _l('current_week'); ?></button>
            <button type="button" class="btn-link-current" id="btn_current_month" style="display: none;"><?= _l('current_month'); ?></button>
            <div class="action-buttons">
                <button type="button" class="btn btn-default" id="btn_date_picker_cancel"><?= _l('cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="btn_date_picker_ok"><?= _l('ok'); ?></button>
            </div>
        </div>
    </div>
    <div class="date-picker-overlay" id="date_picker_overlay"></div>
</div>
