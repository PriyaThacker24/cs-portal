<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <?php if (! isset($view_all)) { ?>
        <?php $this->load->view('admin/staff/stats'); ?>
        <?php } ?>
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <?php if (staff_can('view-timesheets', 'reports')) { ?>
                        <a href="<?= site_url($this->uri->uri_string() . (! isset($view_all) ? '?view=all' : '')); ?>"
                            class="btn btn-primary tw-capitalize"><i class="fa-regular fa-clock"></i>
                            <?= isset($view_all) ? _l('my_timesheets') : _l('view_members_timesheets');
                            ?>
                        </a>
                        <hr />
                        <?php } ?>
                        <canvas id="timesheetsChart" style="max-height:400px;" width="350" height="350"></canvas>
                        <hr />
                        <div class="clearfix"></div>
                        <div class="row">
                            <div class="col-md-5ths">
                                <div class="select-placeholder">
                                    <select name="range" id="range" class="selectpicker" data-width="100%">
                                        <option value="today" selected>
                                            <?= _l('today'); ?>
                                        </option>
                                        <option value="this_month">
                                            <?= _l('staff_stats_this_month_total_logged_time'); ?>
                                        </option>
                                        <option value="last_month">
                                            <?= _l('staff_stats_last_month_total_logged_time'); ?>
                                        </option>
                                        <option value="this_week">
                                            <?= _l('staff_stats_this_week_total_logged_time'); ?>
                                        </option>
                                        <option value="last_week">
                                            <?= _l('staff_stats_last_week_total_logged_time'); ?>
                                        </option>
                                        <option value="period">
                                            <?= _l('period_datepicker'); ?>
                                        </option>
                                    </select>
                                </div>
                                <div class="row mtop15">
                                    <div class="col-md-12 period hide">
                                        <?= render_date_input('period-from'); ?>
                                    </div>
                                    <div class="col-md-12 period hide">
                                        <?= render_date_input('period-to'); ?>
                                    </div>
                                </div>
                            </div>
                            <?php if (isset($view_all)) { ?>
                            <div class="col-md-5ths">
                                <div class="select-placeholder">
                                    <select name="staff_id" id="staff_id" class="selectpicker" data-width="100%">
                                        <option value="">
                                            <?= _l('all_staff_members'); ?>
                                        </option>
                                        <option
                                            value="<?= get_staff_user_id(); ?>">
                                            <?= e(get_staff_full_name(get_staff_user_id())); ?>
                                        </option>
                                        <?php foreach ($staff_members_with_timesheets as $staff) { ?>
                                        <option
                                            value="<?= e($staff['staff_id']); ?>">
                                            <?= e(get_staff_full_name($staff['staff_id'])); ?>
                                        </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <?php } ?>
                            <div class="col-md-5ths">
                                <div class="select-placeholder">
                                    <select id="clientid" name="clientid" data-live-search="true" data-width="100%"
                                        class="ajax-search"
                                        data-empty-title="<?= _l('client'); ?>"
                                        data-none-selected-text="<?= _l('client'); ?>">
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-5ths">
                                <div class="select-placeholder projects-wrapper">
                                    <div id="project_ajax_search_wrapper">
                                        <select
                                            data-empty-title="<?= _l('project'); ?>"
                                            multiple="true" name="project_id[]" id="project_id"
                                            class="projects ajax-search" data-live-search="true" data-width="100%">
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5ths">
                                <a href="#" id="apply_filters_timesheets"
                                    class="btn btn-primary pull-left"><?= _l('apply'); ?></a>
                            </div>
                            <div class="mtop10 hide relative pull-right" id="group_by_tasks_wrapper">
                                <span><?= _l('group_by_task'); ?></span>
                                <div class="onoffswitch">
                                    <input type="checkbox" name="group_by_task" class="onoffswitch-checkbox"
                                        id="group_by_task">
                                    <label class="onoffswitch-label" for="group_by_task"></label>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <hr class="no-mtop" />
                            </div>
                        </div>
                        <div class="clearfix"></div>
                        <table class="table table-timesheets-report">
                            <thead>
                                <tr>
                                    <?php if (isset($view_all)) { ?>
                                    <th><?= _l('staff_member'); ?>
                                    </th>
                                    <?php } ?>
                                    <th><?= _l('project_timesheet_task'); ?>
                                    </th>
                                    <th><?= _l('timesheet_tags'); ?>
                                    </th>
                                    <?php if (get_option('round_off_task_timer_option') == 0) { ?>
                                    <th class="t-start-time">
                                        <?= _l('project_timesheet_start_time'); ?>
                                    </th>
                                    <th class="t-end-time">
                                        <?= _l('project_timesheet_end_time'); ?>
                                    </th>
                                    <?php } ?>
                                    <th width="150px;">
                                        <?= _l('note'); ?>
                                    </th>
                                    <th><?= _l('task_relation'); ?>
                                    </th>
                                    <th><?= _l('time_h'); ?>
                                    </th>
                                    <th><?= _l('time_decimal'); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot>
                                <tr>
                                    <?php if (isset($view_all)) { ?>
                                    <td></td>
                                    <?php } ?>
                                    <td></td>
                                    <td></td>
                                    <?php if (get_option('round_off_task_timer_option') == 0) { ?>
                                    <td></td>
                                    <td></td>
                                    <?php } ?>
                                    <td></td>
                                    <td></td>
                                    <td class="total_logged_time_timesheets_staff_h"></td>
                                    <td class="total_logged_time_timesheets_staff_d"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    var staff_member_select = $('select[name="staff_id"]');
    $(function() {

        init_ajax_projects_search();
        var ctx = document.getElementById("timesheetsChart");
        var chartOptions = {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: '',
                    data: [],
                    backgroundColor: [],
                    borderColor: [],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                tooltips: {
                    enabled: true,
                    mode: 'single',
                    callbacks: {
                        label: function(tooltipItems, data) {
                            return decimalToHM(tooltipItems.yLabel);
                        }
                    }
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            min: 0,
                            userCallback: function(label, index, labels) {
                                return decimalToHM(label);
                            },
                        }
                    }]
                },
            }
        };

        var timesheetsTable = $('.table-timesheets-report');
        var timesheetsTableApi = null;
        
        $('#apply_filters_timesheets').on('click', function(e) {
            e.preventDefault();
            if (timesheetsTableApi) {
                timesheetsTableApi.ajax.reload();
            }
        });

        $('body').on('change', '#group_by_task', function() {
            if (!timesheetsTableApi) {
                timesheetsTableApi = timesheetsTable.DataTable();
            }
            <?php if (get_option('round_off_task_timer_option') == 0) { ?>
            var tApi = timesheetsTableApi;
            var visible = $(this).prop('checked') == false;
            var tEndTimeIndex = $('.t-end-time').index();
            var tStartTimeIndex = $('.t-start-time').index();
            if (tEndTimeIndex == -1 && tStartTimeIndex == -1) {
                tStartTimeIndex = $(this).attr('data-start-time-index');
                tEndTimeIndex = $(this).attr('data-end-time-index');
            } else {
                $(this).attr('data-start-time-index', tStartTimeIndex);
                $(this).attr('data-end-time-index', tEndTimeIndex);
            }
            tApi.column(tEndTimeIndex).visible(visible, false).columns.adjust();
            tApi.column(tStartTimeIndex).visible(visible, false).columns.adjust();
            tApi.ajax.reload();
            <?php } else { ?>
            timesheetsTableApi.ajax.reload();
            <?php } ?>
        });

        var timesheetsChart;
        var Timesheets_ServerParams = {};
        Timesheets_ServerParams['range'] = '[name="range"]';
        Timesheets_ServerParams['period-from'] = '[name="period-from"]';
        Timesheets_ServerParams['period-to'] = '[name="period-to"]';
        Timesheets_ServerParams['staff_id'] = '[name="staff_id"]';
        Timesheets_ServerParams['project_id'] = 'select#project_id';
        Timesheets_ServerParams['clientid'] = 'select#clientid';
        Timesheets_ServerParams['group_by_task'] = '[name="group_by_task"]:checked';
        
        // Ensure range has a default value before DataTable initialization
        // Wait for selectpicker to initialize if it exists
        var rangeSelect = $('select[name="range"]');
        var rangeValue = rangeSelect.val();
        
        // If selectpicker is used, get value from selectpicker
        if (rangeSelect.hasClass('selectpicker') || typeof rangeSelect.selectpicker !== 'undefined') {
            try {
                rangeValue = rangeSelect.selectpicker('val');
            } catch(e) {
                rangeValue = rangeSelect.val();
            }
        }
        
        if (!rangeValue || rangeValue === '' || rangeValue === null) {
            rangeSelect.val('today');
            if (typeof rangeSelect.selectpicker !== 'undefined') {
                try {
                    rangeSelect.selectpicker('refresh');
                } catch(e) {
                    // Selectpicker might not be initialized yet
                }
            }
        }
        
        // Add error handler before initialization
        timesheetsTable.on('error.dt', function(e, settings, techNote, message) {
            console.error('DataTable AJAX error:', message);
            console.error('Technical details:', techNote);
            // Hide loading indicator
            $('.dataTables_processing').hide();
            // Show error message
            var tbody = timesheetsTable.find('tbody');
            if (tbody.length && (tbody.html().trim() === '' || tbody.find('tr').length === 0)) {
                var colCount = timesheetsTable.find('thead th').length;
                tbody.html('<tr><td colspan="' + colCount + '" class="text-center text-danger">Error loading timesheet data. Please refresh the page.</td></tr>');
            }
        });
        
        // Add timeout to prevent infinite loading
        var loadingTimeout = setTimeout(function() {
            if ($('.dataTables_processing').is(':visible')) {
                console.warn('DataTable loading timeout - forcing reload');
                $('.dataTables_processing').hide();
                if (timesheetsTableApi) {
                    timesheetsTableApi.ajax.reload(null, false);
                }
            }
        }, 30000); // 30 second timeout
        
        // Add preDraw event to handle loading
        timesheetsTable.on('preDraw.dt', function() {
            // Clear timeout on successful draw
            clearTimeout(loadingTimeout);
            // Ensure loading indicator is visible
            $('.dataTables_processing').show();
        });
        
        // Initialize DataTable
        timesheetsTableApi = initDataTable('.table-timesheets-report', window.location.href, undefined, undefined,
            Timesheets_ServerParams, [
                <?php if (isset($view_all)) {
                    echo 3;
                } else {
                    echo 2;
                } ?>
                , 'desc'
            ]);
        
        // Store reference for later use
        if (timesheetsTableApi) {
            // Add AJAX error handler
            timesheetsTableApi.on('xhr.dt', function(e, settings, json, xhr) {
                clearTimeout(loadingTimeout);
                if (xhr.status !== 200) {
                    console.error('AJAX request failed with status:', xhr.status);
                    console.error('Response:', xhr.responseText);
                    $('.dataTables_processing').hide();
                }
            });
            
            // Clear timeout on successful draw
            timesheetsTableApi.on('draw.dt', function() {
                clearTimeout(loadingTimeout);
            });
        }

        init_ajax_project_search_by_customer_id();

        $('#clientid').on('change', function() {
            var projectAjax = $('select#project_id');
            var clonedProjectsAjaxSearchSelect = projectAjax.html('').clone();
            var projectsWrapper = $('.projects-wrapper');
            projectAjax.selectpicker('destroy').remove();
            projectAjax = clonedProjectsAjaxSearchSelect;
            $('#project_ajax_search_wrapper').append(clonedProjectsAjaxSearchSelect);
            init_ajax_project_search_by_customer_id();
        });

        timesheetsTable.on('init.dt', function() {
            var $dtFilter = $('body').find('.dataTables_filter');
            var $gr = $('#group_by_tasks_wrapper').clone()
            $('#group_by_tasks_wrapper').remove();
            $gr.removeClass('hide');
            $gr.find('span').css('position', 'absolute');
            $gr.find('span').css('top', '1px');
            $gr.find('span').css((isRTL == 'true' ? 'right' : 'left'), '-110px');
            $dtFilter.before($gr, '<div class="clearfix"></div>');
            $dtFilter.addClass('mtop15');
        });

        timesheetsTable.on('draw.dt', function() {
            try {
                var TimesheetsTable = $(this).DataTable();
                var ajaxJson = TimesheetsTable.ajax.json();
                
                // Check if AJAX response is valid
                if (!ajaxJson) {
                    console.error('No AJAX response from timesheets table');
                    return;
                }
                
                // Update footer with logged time data
                if (ajaxJson.logged_time) {
                    var logged_time = ajaxJson.logged_time;
                    $(this).find('tfoot').addClass('bold');
                    $(this).find('tfoot td.total_logged_time_timesheets_staff_h').html(
                        "<?= _l('total_logged_hours_by_staff'); ?>: " +
                        (logged_time.total_logged_time_h || '00:00'));
                    $(this).find('tfoot td.total_logged_time_timesheets_staff_d').html(
                        "<?= _l('total_logged_hours_by_staff'); ?>: " +
                        (logged_time.total_logged_time_d || '0.00'));
                }
                
                // Update chart if chart data is available
                if (ajaxJson.chart && ajaxJson.chart_type) {
                    var chartResponse = ajaxJson.chart;
                    var chartType = ajaxJson.chart_type;
                    
                    if (typeof(timesheetsChart) !== 'undefined') {
                        timesheetsChart.destroy();
                    }
                    
                    if (chartType != 'month') {
                        chartOptions.data.labels = chartResponse.labels || [];
                    } else {
                        chartOptions.data.labels = [];
                        if (chartResponse.labels && chartResponse.labels.length > 0) {
                            for (var i in chartResponse.labels) {
                                chartOptions.data.labels.push(moment(chartResponse.labels[i]).format("MMM Do YY"));
                            }
                        }
                    }
                    chartOptions.data.datasets[0].data = [];
                    chartOptions.data.datasets[0].backgroundColor = [];
                    chartOptions.data.datasets[0].borderColor = [];
                    
                    if (chartResponse.data && chartResponse.data.length > 0) {
                        for (var i in chartResponse.data) {
                            chartOptions.data.datasets[0].data.push(chartResponse.data[i]);
                            if (chartResponse.data[i] == 0) {
                                chartOptions.data.datasets[0].backgroundColor.push('rgba(167, 167, 167, 0.6)');
                                chartOptions.data.datasets[0].borderColor.push('rgba(167, 167, 167, 1)');
                            } else {
                                chartOptions.data.datasets[0].backgroundColor.push('rgba(132, 197, 41, 0.6)');
                                chartOptions.data.datasets[0].borderColor.push('rgba(132, 197, 41, 1)');
                            }
                        }
                    }

                    var selected_staff_member = staff_member_select.val();
                    var selected_staff_member_name = staff_member_select.find('option:selected').text();
                    chartOptions.data.datasets[0].label = $('select[name="range"] option:selected').text() + (
                        selected_staff_member != '' && selected_staff_member != undefined ? ' - ' +
                        selected_staff_member_name : '');
                    setTimeout(function() {
                        timesheetsChart = new Chart(ctx, chartOptions);
                    }, 30);
                }
                
                do_timesheets_title();
            } catch (e) {
                console.error('Error in timesheets table draw event:', e);
            }
        });
        
        // Add error handler for DataTable AJAX requests
        timesheetsTable.on('error.dt', function(e, settings, techNote, message) {
            console.error('DataTable AJAX error:', message);
            console.error('Technical details:', techNote);
        });
    });

    function do_timesheets_title() {
        var _temp;
        var range = $('select[name="range"]');
        var _range_heading = range.find('option:selected').text();
        if (range.val() != 'period') {
            _temp = _range_heading;
        } else {
            _temp = _range_heading + ' (' + $('input[name="period-from"]').val() + ' - ' + $('input[name="period-to"]')
                .val() + ') ';
        }
        $('head title').html(_temp + (staff_member_select.find('option:selected').text() != '' ? ' - ' +
            staff_member_select
            .find('option:selected').text() : ''));
    }
</script>
</body>

</html>