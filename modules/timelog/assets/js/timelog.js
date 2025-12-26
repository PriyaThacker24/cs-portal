/**
 * Timelog Module JavaScript
 * Handles filtering, grouping, week navigation, and interactions
 */

var TimelogModule = (function() {
    'use strict';

    var currentWeekStart;
    var currentGroupBy;
    var currentFilters = {};

    /**
     * Initialize the module
     */
    function init() {
        currentWeekStart = $('#current_week_start').val();
        currentGroupBy = $('#current_group_by').val();

        bindEvents();
        loadTimelogs();
    }

    /**
     * Bind all event handlers
     */
    function bindEvents() {
        // Week navigation
        $('#btn_prev_week').on('click', function() {
            navigateWeek('prev');
        });

        $('#btn_next_week').on('click', function() {
            navigateWeek('next');
        });

        // Group by change
        $('#group_by_date').on('change', function() {
            currentGroupBy = $(this).val();
            $('#current_group_by').val(currentGroupBy);
            loadTimelogs();
        });

        // Filter toggle
        $('#btn_filter').on('click', function() {
            $('#filters_panel').slideToggle();
        });

        // Apply filters
        $('#btn_apply_filters').on('click', function() {
            applyFilters();
        });

        // Clear filters
        $('#btn_clear_filters').on('click', function() {
            clearFilters();
        });

        // Toggle group (collapse/expand)
        $(document).on('click', '.btn-toggle-group', function(e) {
            e.stopPropagation();
            var $button = $(this);
            var $headerRow = $button.closest('.timelog-group-header-row');
            var $bodyRows = $headerRow.nextUntil('.timelog-group-header-row', '.timelog-group-body-row');
            var $icon = $button.find('i');
            var isExpanded = $button.attr('aria-expanded') === 'true';

            if (isExpanded) {
                $bodyRows.hide();
                $icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
                $button.attr('aria-expanded', 'false');
            } else {
                $bodyRows.show();
                $icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
                $button.attr('aria-expanded', 'true');
            }
        });
        
        // Select all checkboxes in a group
        $(document).on('change', '.select-all-timelogs', function() {
            var isChecked = $(this).is(':checked');
            $('.timelog-checkbox').prop('checked', isChecked);
        });

        // Add timelog button
        $('#btn_add_timelog').on('click', function() {
            // Redirect to timesheet page or open modal
            window.location.href = admin_url + 'staff/timesheets';
        });

        // Toggle view button
        $('#btn_toggle_view').on('click', function() {
            // Future: Toggle between list and grid view
            alert_float('info', 'List/Grid view toggle coming soon');
        });
    }

    /**
     * Navigate to previous/next week
     */
    function navigateWeek(direction) {
        // Get current week start from input field (always use latest value)
        var currentValue = $('#current_week_start').val();
        if (!currentValue) {
            console.error('No current week start value found');
            return;
        }
        currentWeekStart = currentValue;
        
        // Parse the current week start date (YYYY-MM-DD format)
        var dateParts = currentWeekStart.split('-');
        if (dateParts.length !== 3) {
            console.error('Invalid date format:', currentWeekStart);
            return;
        }
        
        var currentDate = new Date(parseInt(dateParts[0]), parseInt(dateParts[1]) - 1, parseInt(dateParts[2]));
        
        // Add or subtract 7 days
        var daysToAdd = direction === 'next' ? 7 : -7;
        currentDate.setDate(currentDate.getDate() + daysToAdd);

        // Ensure we get Monday of the week
        var day = currentDate.getDay();
        var diff = currentDate.getDate() - day + (day === 0 ? -6 : 1); // Adjust when day is Sunday
        var mondayDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), diff);

        currentWeekStart = formatDate(mondayDate);
        $('#current_week_start').val(currentWeekStart);
        
        // Load new week data (server will return correct week number)
        loadTimelogs();
    }

    /**
     * Load timelogs via AJAX
     */
    function loadTimelogs() {
        var $loading = $('#timelog_loading');
        var $content = $('#timelog_content');

        $loading.show();
        $content.hide();

        var data = {
            week_start: currentWeekStart,
            group_by: currentGroupBy,
            project_id: currentFilters.project_id || '',
            staff_id: currentFilters.staff_id || '',
            billing_type: currentFilters.billing_type || ''
        };

        $.ajax({
            url: admin_url + 'timelog/get_data',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                $loading.hide();
                
                if (response && response.html) {
                    $content.html(response.html).show();
                    
                    // Always update week display and summary from response
                    if (response.week_start && response.week_end && response.week_number) {
                        updateWeekDisplay(response.week_start, response.week_end, response.week_number);
                    }
                    if (response.summary) {
                        updateSummary(response.summary);
                    }
                } else {
                    // Show empty state message
                    var emptyMessage = (typeof _l !== 'undefined' && typeof _l('no_timelogs_found') !== 'undefined') ? _l('no_timelogs_found') : 'No timelog available for this week';
                    $content.html('<div class="timelog-empty-state text-center" style="padding: 40px;"><i class="fa fa-clock-o fa-3x" style="color: #ccc;"></i><p style="margin-top: 20px; color: #999;">' + emptyMessage + '</p></div>').show();
                    
                    // Still update week display
                    if (response && response.week_start && response.week_end && response.week_number) {
                        updateWeekDisplay(response.week_start, response.week_end, response.week_number);
                    }
                }
            },
            error: function(xhr, status, error) {
                $loading.hide();
                $content.html('<div class="alert alert-danger">Error loading timelogs: ' + error + '</div>').show();
                console.error('Error loading timelogs:', error);
                console.error('Response:', xhr.responseText);
            }
        });
    }

    /**
     * Apply filters
     */
    function applyFilters() {
        currentFilters = {
            project_id: $('#filter_project').val(),
            staff_id: $('#filter_staff').val(),
            billing_type: $('#filter_billing_type').val()
        };

        loadTimelogs();
        $('#filters_panel').slideUp();
    }

    /**
     * Clear filters
     */
    function clearFilters() {
        $('#filter_project').val('').selectpicker('refresh');
        $('#filter_staff').val('').selectpicker('refresh');
        $('#filter_billing_type').val('');

        currentFilters = {};
        loadTimelogs();
    }

    /**
     * Update week display
     */
    function updateWeekDisplay(weekStart, weekEnd, weekNumber) {
        var startDate = formatDateDisplay(weekStart);
        var endDate = formatDateDisplay(weekEnd);
        $('#week_display').text(startDate + ' - ' + endDate + ' (' + (typeof _l !== 'undefined' ? _l('week') : 'Week') + ' ' + weekNumber + ')');
    }

    /**
     * Update summary footer
     */
    function updateSummary(summary) {
        if (summary) {
            // Convert hours to seconds then format (matching server-side format)
            var billableSeconds = (summary.total_billable_hours || 0) * 3600;
            var nonBillableSeconds = (summary.total_non_billable_hours || 0) * 3600;
            var totalSeconds = (summary.total_hours || 0) * 3600;
            
            $('#summary_billable_hours').text(secondsToTimeFormat(billableSeconds));
            $('#summary_non_billable_hours').text(secondsToTimeFormat(nonBillableSeconds));
            $('#summary_total_hours').text(secondsToTimeFormat(totalSeconds));
            $('#summary_total_records').text(summary.total_records || 0);
            $('#timelog_summary').show();
        }
    }

    /**
     * Format date to YYYY-MM-DD
     */
    function formatDate(date) {
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    /**
     * Format date for display (DD/MM/YYYY)
     */
    function formatDateDisplay(dateString) {
        var date = new Date(dateString);
        var day = String(date.getDate()).padStart(2, '0');
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var year = date.getFullYear();
        return day + '/' + month + '/' + year;
    }

    /**
     * Format seconds to time format (HH:MM)
     */
    function secondsToTimeFormat(seconds) {
        var hours = Math.floor(seconds / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        
        hours = (hours < 10) ? '0' + hours : hours;
        minutes = (minutes < 10) ? '0' + minutes : minutes;
        
        return hours + ':' + minutes;
    }
    
    /**
     * Format hours (kept for backwards compatibility)
     */
    function formatHours(hours) {
        return parseFloat(hours).toFixed(2) + 'h';
    }

    /**
     * Public API
     */
    return {
        init: init,
        loadTimelogs: loadTimelogs
    };

})();

