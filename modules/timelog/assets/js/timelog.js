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
               initTimelogDrawer();
               
               // Ensure filter panel is initialized
               if (typeof TimelogFilter !== 'undefined' && TimelogFilter.init) {
                   TimelogFilter.init();
               }
               
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

        // Filter toggle - open advanced filter panel
        $('#btn_filter').on('click', function(e) {
            e.preventDefault();
            if (typeof TimelogFilter !== 'undefined' && TimelogFilter.openFilterPanel) {
                TimelogFilter.openFilterPanel();
            } else {
                // Fallback: try to open panel directly
                $('#timelogFilterPanel').addClass('active');
                $('body').addClass('filter-panel-open');
            }
        });

        // Apply filters (legacy - kept for backward compatibility)
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

        // Add timelog button - Open drawer
        $('#btn_add_timelog').on('click', function() {
            openTimelogDrawer();
        });
        
        // Close drawer buttons
        $('#btn_close_drawer, #btn_cancel_timelog, #timelog_drawer_overlay').on('click', function(e) {
            if (e.target === this) {
                closeTimelogDrawer();
            }
        });
        
        // Initialize drawer functionality
        initTimelogDrawer();

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

        // Get advanced filters from TimelogFilter if available
        var advancedFilters = '';
        if (typeof TimelogFilter !== 'undefined' && TimelogFilter.getFilters) {
            var filterData = TimelogFilter.getFilters();
            if (filterData && Object.keys(filterData).length > 0) {
                advancedFilters = JSON.stringify(filterData);
            }
        }
        
        var data = {
            week_start: currentWeekStart,
            group_by: currentGroupBy,
            project_id: currentFilters.project_id || '',
            staff_id: currentFilters.staff_id || '',
            billing_type: currentFilters.billing_type || ''
        };
        
        // Add advanced filters if available
        if (advancedFilters) {
            data.advanced_filters = advancedFilters;
        }

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
     * Open timelog drawer
     */
    function openTimelogDrawer() {
        $('#timelog_drawer_overlay').fadeIn(300);
        $('#timelog_drawer').addClass('open');
        
        // Load projects
        loadUserProjects();
        
        // Trigger event for datepicker initialization
        $(document).trigger('drawerOpened');
    }
    
    /**
     * Close timelog drawer
     */
    function closeTimelogDrawer() {
        // Reset button text before closing
        $('#btn_add_timelog_submit').prop('disabled', false).data('submitting', false).html(typeof _l !== 'undefined' ? _l('add') : 'Add');
        
        $('#timelog_drawer').removeClass('open');
        $('#timelog_drawer_overlay').fadeOut(300);
        
        // Reset form
        resetTimelogForm();
    }
    
    /**
     * Load projects assigned to logged-in user
     */
    function loadUserProjects() {
        $.ajax({
            url: admin_url + 'timelog/get_user_projects',
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.projects) {
                    var $select = $('#timelog_project');
                    $select.empty().append('<option value="">' + (typeof _l !== 'undefined' ? _l('select_project') : 'Select Project') + '</option>');
                    
                    $.each(response.projects, function(index, project) {
                        $select.append('<option value="' + project.id + '">' + project.name + '</option>');
                    });
                    
                    $select.selectpicker('refresh');
                }
            },
            error: function() {
                alert_float('danger', 'Error loading projects');
            }
        });
    }
    
    /**
     * Load tasks for selected project
     */
    function loadProjectTasks(projectId) {
        if (!projectId) {
            var searchText = (typeof _l !== 'undefined' ? _l('search') : 'Search') + '...';
            $('#timelog_task').empty().append('<option value="">' + searchText + '</option>').selectpicker('refresh');
            return;
        }
        
        $.ajax({
            url: admin_url + 'timelog/get_project_tasks',
            type: 'POST',
            data: { project_id: projectId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.tasks) {
                    var $select = $('#timelog_task');
                    var searchText = (typeof _l !== 'undefined' ? _l('search') : 'Search') + '...';
                    $select.empty().append('<option value="">' + searchText + '</option>');
                    
                    $.each(response.tasks, function(index, task) {
                        $select.append('<option value="' + task.id + '">' + task.name + '</option>');
                    });
                    
                    $select.selectpicker('refresh');
                }
            },
            error: function() {
                alert_float('danger', 'Error loading tasks');
            }
        });
    }
    
    /**
     * Load users for selected project
     */
    function loadProjectUsers(projectId) {
        if (!projectId) {
            var selectUserText = typeof _l !== 'undefined' ? _l('select_user') : 'Select User';
            $('#timelog_user').empty().append('<option value="">' + selectUserText + '</option>').selectpicker('refresh');
            return;
        }
        
        $.ajax({
            url: admin_url + 'timelog/get_project_users',
            type: 'POST',
            data: { project_id: projectId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.users) {
                    var $select = $('#timelog_user');
                    var selectUserText = typeof _l !== 'undefined' ? _l('select_user') : 'Select User';
                    $select.empty().append('<option value="">' + selectUserText + '</option>');
                    
                    $.each(response.users, function(index, user) {
                        var selected = (user.staffid == response.current_user_id) ? 'selected' : '';
                        $select.append('<option value="' + user.staffid + '" ' + selected + '>' + user.full_name + '</option>');
                    });
                    
                    $select.selectpicker('refresh');
                }
            },
            error: function() {
                alert_float('danger', 'Error loading users');
            }
        });
    }
    
    /**
     * Initialize timelog drawer functionality
     */
    function initTimelogDrawer() {
        // Project change handler
        $(document).on('change', '#timelog_project', function() {
            var projectId = $(this).val();
            
            if (projectId) {
                // Show other fields
                $('#timelog_other_fields').slideDown();
                
                // Load tasks and users
                loadProjectTasks(projectId);
                loadProjectUsers(projectId);
                
                // Reset dependent fields
                $('#timelog_task').val('').selectpicker('refresh');
                $('#timelog_is_general_log').prop('checked', false);
                toggleGeneralLogFields();
            } else {
                // Hide other fields
                $('#timelog_other_fields').slideUp();
            }
        });
        
        // General log link handler
        $(document).on('click', '#link_enter_general_log', function(e) {
            e.preventDefault();
            toggleGeneralLogFields(true);
        });
        
        // Select task link handler (to go back to task selection)
        $(document).on('click', '#link_select_task', function(e) {
            e.preventDefault();
            toggleGeneralLogFields(false);
        });
        
        /**
         * Toggle between task dropdown and task heading input
         */
        function toggleGeneralLogFields(isGeneralLog) {
            if (isGeneralLog) {
                // Show task heading, hide task dropdown
                $('#timelog_task_group').slideUp();
                $('#timelog_task_heading_group').slideDown();
                
                // Remove required from task dropdown, add to task heading
                $('#timelog_task').removeAttr('required');
                $('#timelog_task_heading').attr('required', 'required');
                
                // Clear task selection
                $('#timelog_task').val('').selectpicker('refresh');
            } else {
                // Show task dropdown, hide task heading
                $('#timelog_task_group').slideDown();
                $('#timelog_task_heading_group').slideUp();
                
                // Add required to task dropdown, remove from task heading
                $('#timelog_task').attr('required', 'required');
                $('#timelog_task_heading').removeAttr('required');
                
                // Clear task heading value
                $('#timelog_task_heading').val('');
            }
        }
        
        // Initialize datepicker when drawer is opened (via event)
        $(document).on('drawerOpened', function() {
            if ($('#timelog_date').length) {
                // Destroy existing datepicker if any
                if ($('#timelog_date').data('xdsoft_datetimepicker')) {
                    $('#timelog_date').datetimepicker('destroy');
                }
                
                // Initialize datepicker with fixed d/m/Y format
                var $dateInput = $('#timelog_date');
                
                // Initialize with explicit d/m/Y format
                $dateInput.datetimepicker({
                    timepicker: false,
                    format: 'd/m/Y',
                    scrollInput: false,
                    lazyInit: true,
                    dayOfWeekStart: (typeof app !== 'undefined' && app.options) ? app.options.calendar_first_day : 0,
                    maxDate: new Date(), // No future dates
                    onChangeDateTime: function(dp, $input) {
                        // Ensure format is always d/m/Y
                        var value = $input.val();
                        if (value) {
                            // Parse the date and reformat to d/m/Y
                            var dateObj = dp;
                            if (dateObj && dateObj.getDate) {
                                var day = ('0' + dateObj.getDate()).slice(-2);
                                var month = ('0' + (dateObj.getMonth() + 1)).slice(-2);
                                var year = dateObj.getFullYear();
                                $input.val(day + '/' + month + '/' + year);
                            }
                        }
                    }
                });
                
                // Handle calendar icon click
                $dateInput.parents('.form-group').find('.calendar-icon').off('click').on('click', function() {
                    $dateInput.focus();
                    $dateInput.trigger('open.xdsoft');
                });
                
                // Ensure format stays d/m/Y on manual input change
                $dateInput.off('change blur').on('change blur', function() {
                    var value = $(this).val();
                    if (value && value.length > 0) {
                        // Parse and reformat to ensure d/m/Y format
                        var dateParts = value.split(/[\/\-\.]/);
                        if (dateParts.length === 3) {
                            var day, month, year;
                            
                            // Detect format and convert to d/m/Y
                            var part1 = parseInt(dateParts[0], 10);
                            var part2 = parseInt(dateParts[1], 10);
                            var part3 = parseInt(dateParts[2], 10);
                            
                            if (part1 > 12) {
                                // d/m/Y format (day > 12)
                                day = dateParts[0];
                                month = dateParts[1];
                                year = dateParts[2];
                            } else if (part2 > 12) {
                                // m/d/Y format (month > 12, so part2 is day)
                                month = dateParts[0];
                                day = dateParts[1];
                                year = dateParts[2];
                            } else if (part3 && part3 < 100) {
                                // Likely m/d/y format
                                month = dateParts[0];
                                day = dateParts[1];
                                year = '20' + dateParts[2]; // Assume 20xx
                            } else {
                                // Default: assume d/m/Y
                                day = dateParts[0];
                                month = dateParts[1];
                                year = dateParts[2];
                            }
                            
                            // Ensure 2-digit day and month, 4-digit year
                            day = ('0' + parseInt(day, 10)).slice(-2);
                            month = ('0' + parseInt(month, 10)).slice(-2);
                            if (year.length === 2) {
                                year = '20' + year;
                            }
                            
                            // Set formatted value
                            $(this).val(day + '/' + month + '/' + year);
                        }
                    }
                });
            }
            
            // Add time format mask/validation for daily log field
            $('#timelog_daily_log').off('input blur').on('input', function() {
                var value = $(this).val();
                // Remove any non-digit or colon characters
                value = value.replace(/[^\d:]/g, '');
                
                // Auto-format as user types (HH:MM)
                if (value.length > 0 && !value.includes(':')) {
                    if (value.length <= 2) {
                        $(this).val(value);
                    } else if (value.length > 2) {
                        var hours = value.substring(0, value.length - 2);
                        var minutes = value.substring(value.length - 2);
                        $(this).val(hours + ':' + minutes);
                    }
                } else if (value.includes(':')) {
                    var parts = value.split(':');
                    if (parts.length === 2) {
                        // Limit hours to 2 digits and minutes to 2 digits (0-59)
                        var hours = parts[0].substring(0, 2);
                        var minutes = parts[1].substring(0, 2);
                        if (parseInt(minutes) > 59) {
                            minutes = '59';
                        }
                        $(this).val(hours + ':' + minutes);
                    }
                }
            });
            
            // Format on blur if incomplete
            $('#timelog_daily_log').on('blur', function() {
                var value = $(this).val();
                if (value && !value.includes(':')) {
                    // If user entered just numbers, format as HH:MM
                    if (value.length <= 2) {
                        $(this).val(value.padStart(2, '0') + ':00');
                    } else if (value.length === 3) {
                        $(this).val('0' + value.substring(0, 1) + ':' + value.substring(1));
                    } else if (value.length >= 4) {
                        var hours = value.substring(0, value.length - 2);
                        var minutes = value.substring(value.length - 2);
                        $(this).val(hours.padStart(2, '0') + ':' + minutes);
                    }
                }
            });
        });
        
        // Submit form handler - use off() to prevent duplicate bindings
        $('#btn_add_timelog_submit').off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            submitTimelogForm();
        });
        
        // Prevent form native submission
        $('#timelog_form').off('submit').on('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            submitTimelogForm();
            return false;
        });
    }
    
    /**
     * Show error message below input field
     */
    function showFieldError(fieldId, errorMessage) {
        var $field = $('#' + fieldId);
        var $formGroup = $field.closest('.form-group');
        $formGroup.addClass('has-error');
        
        // Remove existing error message
        $formGroup.find('.help-block .error-message').remove();
        
        // Get or create help-block
        var $helpBlock = $formGroup.find('.help-block');
        if ($helpBlock.length === 0) {
            $helpBlock = $('<div class="help-block"></div>');
            $formGroup.append($helpBlock);
        }
        
        // Add error message at the beginning
        var $errorMsg = $('<span class="error-message" style="color: #dc3545; display: block;">' + errorMessage + '</span>');
        $helpBlock.prepend($errorMsg);
    }
    
    /**
     * Submit timelog form
     */
    function submitTimelogForm() {
        // Prevent double submission
        var $submitBtn = $('#btn_add_timelog_submit');
        if ($submitBtn.prop('disabled') || $submitBtn.data('submitting')) {
            return false;
        }
        
        // Mark as submitting
        $submitBtn.data('submitting', true);
        
        // Clear previous errors
        $('.form-group').removeClass('has-error');
        $('.help-block .error-message').remove();
        
        // Check if general log mode (task heading field is visible)
        var isGeneralLog = $('#timelog_task_heading_group').is(':visible');
        
        // Get form data
        var formData = {
            project_id: $('#timelog_project').val(),
            task_id: isGeneralLog ? '' : $('#timelog_task').val(),
            task_heading: isGeneralLog ? $('#timelog_task_heading').val() : '',
            is_general_log: isGeneralLog ? '1' : '0',
            date: $('#timelog_date').val(),
            staff_id: $('#timelog_user').val(),
            daily_log: $('#timelog_daily_log').val(),
            billing_type: $('#timelog_billing_type').val(),
            notes: $('#timelog_notes').val()
        };
        
        // Client-side validation
        var isValid = true;
        
        if (!formData.project_id) {
            isValid = false;
            showFieldError('timelog_project', typeof _l !== 'undefined' ? _l('project') + ' is required' : 'Project is required');
        }
        
        if (isGeneralLog) {
            // Validate task heading for general log
            if (!formData.task_heading || formData.task_heading.trim() === '') {
                isValid = false;
                showFieldError('timelog_task_heading', typeof _l !== 'undefined' ? _l('task_heading') + ' is required' : 'Task heading is required');
            }
        } else {
            // Validate task selection for regular log
            if (!formData.task_id) {
                isValid = false;
                showFieldError('timelog_task', typeof _l !== 'undefined' ? _l('tasks_feedback') + ' is required' : 'Task is required');
            }
        }
        
        if (!formData.date) {
            isValid = false;
            showFieldError('timelog_date', typeof _l !== 'undefined' ? _l('date') + ' is required' : 'Date is required');
        }
        
        if (!formData.staff_id) {
            isValid = false;
            showFieldError('timelog_user', typeof _l !== 'undefined' ? _l('user') + ' is required' : 'User is required');
        }
        
        // Validate time format (HH:MM)
        var timePattern = /^([0-9]{1,2}):([0-5][0-9])$/;
        if (!formData.daily_log || !timePattern.test(formData.daily_log)) {
            isValid = false;
            showFieldError('timelog_daily_log', typeof _l !== 'undefined' ? _l('invalid_time_format') : 'Invalid time format. Please use HH:MM format (e.g., 02:30)');
        } else {
            // Validate that time is not 00:00
            var timeParts = formData.daily_log.split(':');
            var hours = parseInt(timeParts[0], 10);
            var minutes = parseInt(timeParts[1], 10);
            if (hours === 0 && minutes === 0) {
                isValid = false;
                showFieldError('timelog_daily_log', 'Daily log time must be greater than 00:00');
            }
        }
        
        if (!isValid) {
            // Clear submitting flag on validation error
            $submitBtn.data('submitting', false);
            // Errors are already shown below each field
            return false;
        }
        
        // Disable submit button
        $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + (typeof _l !== 'undefined' ? _l('adding') : 'Adding') + '...');
        
        // Submit via AJAX
        $.ajax({
            url: admin_url + 'timelog/submit_timelog',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Reset button text and clear submitting flag before closing drawer
                    $submitBtn.prop('disabled', false).data('submitting', false).html(typeof _l !== 'undefined' ? _l('add') : 'Add');
                    
                    // Close drawer and reload (no success toast)
                    closeTimelogDrawer();
                    
                    // Reload timelog list
                    if (response.week_start) {
                        $('#current_week_start').val(response.week_start);
                    }
                    loadTimelogs();
                } else {
                    // Show errors below fields
                    if (response.errors) {
                        $.each(response.errors, function(field, error) {
                            showFieldError('timelog_' + field, error);
                        });
                    } else if (response.message) {
                        // Show general error message if no specific field errors
                        showFieldError('timelog_project', response.message);
                    }
                    
                    // Re-enable submit button and clear submitting flag
                    $submitBtn.prop('disabled', false).data('submitting', false).html(typeof _l !== 'undefined' ? _l('add') : 'Add');
                }
            },
            error: function(xhr, status, error) {
                // Show error below first field
                showFieldError('timelog_project', typeof _l !== 'undefined' ? _l('error_adding_timelog') : 'Error adding time log. Please try again');
                // Re-enable submit button and clear submitting flag
                $submitBtn.prop('disabled', false).data('submitting', false).html(typeof _l !== 'undefined' ? _l('add') : 'Add');
            }
        });
        
        return false;
    }
    
    /**
     * Reset timelog form
     */
    function resetTimelogForm() {
        $('#timelog_form')[0].reset();
        $('#timelog_other_fields').hide();
        $('#timelog_project, #timelog_task, #timelog_user').selectpicker('refresh');
        $('.form-group').removeClass('has-error');
        $('.help-block .error-message').remove();
        
        // Clear submitting flag
        $('#btn_add_timelog_submit').data('submitting', false).prop('disabled', false);
    }

    /**
     * Public API
     */
    return {
        init: init,
        loadTimelogs: loadTimelogs
    };

})();

