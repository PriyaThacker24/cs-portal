/**
 * Timelog Date Picker JavaScript
 * Handles date range selection with Day/Week/Month/Range/Quick filters
 */

var TimelogDatePicker = (function() {
    'use strict';

    var currentDateRange = {
        type: 'week', // day, week, month, range
        start: null,
        end: null
    };
    var currentCalendarMonth = new Date();
    var currentRangeLeftMonth = new Date();
    var currentRangeRightMonth = new Date();
    var selectedDates = {
        start: null,
        end: null
    };
    var rangeSelectionState = {
        selecting: false,
        startDate: null
    };

    /**
     * Initialize the date picker
     */
    function init() {
        // Get current date range from hidden inputs
        var weekStart = $('#current_week_start').val();
        var weekEnd = $('#current_week_end').val();
        var rangeType = $('#current_date_range_type').val() || 'week';

        if (weekStart) {
            currentDateRange.start = new Date(weekStart);
            currentDateRange.end = weekEnd ? new Date(weekEnd) : new Date(weekStart);
            currentDateRange.type = rangeType;
            currentCalendarMonth = new Date(currentDateRange.start);
            currentRangeLeftMonth = new Date(currentDateRange.start);
            currentRangeRightMonth = new Date(currentDateRange.start);
            currentRangeRightMonth.setMonth(currentRangeRightMonth.getMonth() + 1);
            
            // Initialize selected dates
            selectedDates.start = new Date(currentDateRange.start);
            selectedDates.end = new Date(currentDateRange.end);
        } else {
            // Default to current week
            var today = new Date();
            currentDateRange.start = getMonday(today);
            currentDateRange.end = getSunday(today);
            currentDateRange.type = 'week';
            currentCalendarMonth = new Date(today);
            currentRangeLeftMonth = new Date(today);
            currentRangeRightMonth = new Date(today);
            currentRangeRightMonth.setMonth(currentRangeRightMonth.getMonth() + 1);
            
            // Initialize selected dates
            selectedDates.start = new Date(currentDateRange.start);
            selectedDates.end = new Date(currentDateRange.end);
        }

        // Set initial mode class
        $('.timelog-date-picker').addClass('mode-' + currentDateRange.type);

        bindEvents();
        updateDateDisplay();
    }

    /**
     * Bind all event handlers
     */
    function bindEvents() {
        // Open date picker
        $('#btn_open_date_picker').on('click', function() {
            openDatePicker();
        });

        // Close date picker
        $('#btn_date_picker_cancel, #date_picker_overlay').on('click', function() {
            closeDatePicker();
        });

        // Tab switching
        $('.date-picker-tab').on('click', function() {
            switchTab($(this).data('type'));
        });

        // Calendar navigation
        $('#calendar_prev_year').on('click', function() {
            navigateCalendar('year', 'prev');
        });
        $('#calendar_next_year').on('click', function() {
            navigateCalendar('year', 'next');
        });
        $('#calendar_prev_month').on('click', function() {
            navigateCalendar('month', 'prev');
        });
        $('#calendar_next_month').on('click', function() {
            navigateCalendar('month', 'next');
        });

        // Month view year navigation
        $('#month_prev_year').on('click', function() {
            navigateMonthYear('prev');
        });
        $('#month_next_year').on('click', function() {
            navigateMonthYear('next');
        });

        // Day / Week calendar day selection.
        // NOTE: these are delegated from `.timelog-date-picker` (not document)
        // on purpose. The grids are re-rendered on every navigation, so we need
        // delegation; but `.timelog-date-picker` has a click handler that calls
        // e.stopPropagation(), so a document-level delegated handler would never
        // fire (the event is stopped before it reaches document). Delegating
        // from the picker element itself runs the handler before that stop.
        $('.timelog-date-picker').on('click', '#calendar_grid .calendar-day', function() {
            handleDayClick($(this));
        });

        // Month selection
        $('.timelog-date-picker').on('click', '.month-item', function() {
            handleMonthClick($(this));
        });

        // Range navigation (prev/next range based on current type)
        $('#date_picker_prev_range').on('click', function() {
            navigateRange('prev');
        });
        $('#date_picker_next_range').on('click', function() {
            navigateRange('next');
        });

        // Quick filter buttons
        $('.btn-quick-filter').on('click', function() {
            applyQuickFilter($(this).data('filter'));
        });

        // OK button
        $('#btn_date_picker_ok').on('click', function() {
            applyDateRange();
        });

        // Current week button
        $('#btn_current_week').on('click', function() {
            setCurrentWeek();
        });

        // Current month button
        $('#btn_current_month').on('click', function() {
            setCurrentMonth();
        });

        // Range calendar navigation
        $('#range_prev_year').on('click', function() {
            navigateRangeCalendar('year', 'prev');
        });
        $('#range_next_year').on('click', function() {
            navigateRangeCalendar('year', 'next');
        });
        $('#range_prev_month').on('click', function() {
            navigateRangeCalendar('month', 'prev');
        });
        $('#range_next_month').on('click', function() {
            navigateRangeCalendar('month', 'next');
        });

        // Range calendar day click
        // Delegated from the picker element (see note on the calendar-day
        // handler above) so the inner-picker stopPropagation does not swallow it.
        $('.timelog-date-picker').on('click', '.range-calendar-day', function() {
            handleRangeDayClick($(this));
        });

        // Range hover preview: after the start date is picked (and before the
        // end date), hovering a day previews the tentative range so the
        // selection animates as the pointer moves.
        $('.timelog-date-picker').on('mouseenter', '.range-calendar-day', function() {
            if (!rangeSelectionState.selecting || !rangeSelectionState.startDate) {
                return;
            }
            var dateStr = $(this).data('date');
            if (!dateStr) {
                return;
            }
            previewRangeTo(parseDate(dateStr));
        });

        // Clear the preview when the pointer leaves the range calendars.
        $('.timelog-date-picker').on('mouseleave', '#date_picker_range', function() {
            clearRangePreview();
        });

        // Prevent closing when clicking inside picker
        $('.timelog-date-picker').on('click', function(e) {
            e.stopPropagation();
        });
    }

    /**
     * Open date picker
     */
    function openDatePicker() {
        // Get the active tab
        var activeTab = $('.date-picker-tab.active').data('type');

        // Reflect the currently-applied range in the picker so reopening it
        // shows what is actually loaded (Zoho-style). We intentionally do NOT
        // reset to the current week or mutate the hidden inputs here — that is
        // only done when the user actually selects a new range.
        if (currentDateRange.start && currentDateRange.end) {
            selectedDates.start = new Date(currentDateRange.start);
            selectedDates.end = new Date(currentDateRange.end);
            currentCalendarMonth = new Date(currentDateRange.start);
        } else {
            // No applied range yet — default to the current week
            var today = new Date();
            selectedDates.start = getMonday(today);
            selectedDates.end = getSunday(today);
            currentCalendarMonth = new Date(today);
        }
        
        $('#timelog_date_picker_wrapper').fadeIn(200);
        
        // Render appropriate view based on current tab
        if (activeTab === 'month') {
            renderMonthGrid();
        } else if (activeTab === 'range') {
            // Initialize range calendars properly
            var initDate = selectedDates.start || new Date();
            currentRangeLeftMonth = new Date(initDate);
            currentRangeLeftMonth.setDate(1);
            currentRangeRightMonth = new Date(currentRangeLeftMonth);
            currentRangeRightMonth.setMonth(currentRangeRightMonth.getMonth() + 1);
            renderRangeCalendars();
        } else {
            renderCalendar();
        }
        
        updateSelectedRangeDisplay();
    }

    /**
     * Close date picker
     */
    function closeDatePicker() {
        $('#timelog_date_picker_wrapper').fadeOut(200);
        // Reset selection if cancelled
        selectedDates.start = new Date(currentDateRange.start);
        selectedDates.end = new Date(currentDateRange.end);
    }

    /**
     * Switch tabs
     */
    function switchTab(type) {
        $('.date-picker-tab').removeClass('active');
        $('#tab_' + type).addClass('active');

        // Update mode class for CSS styling
        $('.timelog-date-picker').removeClass('mode-day mode-week mode-month mode-range');
        if (type !== 'quick') {
            $('.timelog-date-picker').addClass('mode-' + type);
        }

        // Show/hide appropriate sections
        $('#date_picker_calendar').hide();
        $('#date_picker_month_view').hide();
        $('#date_picker_range').hide();
        $('#date_picker_quick').hide();

        if (type === 'range') {
            $('.timelog-date-picker').addClass('mode-range');
            $('#date_picker_range').show();
            // Show/hide appropriate buttons
            $('#btn_current_week').hide();
            $('#btn_current_month').show();
            
            // Initialize range dates if not set
            if (!selectedDates.start || !selectedDates.end) {
                if (currentDateRange.start && currentDateRange.end) {
                    selectedDates.start = new Date(currentDateRange.start);
                    selectedDates.end = new Date(currentDateRange.end);
                } else {
                    var today = new Date();
                    selectedDates.start = new Date(today.getFullYear(), today.getMonth(), 1);
                    selectedDates.end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                }
            }
            
            // Always initialize range calendars based on selected dates or current month
            var initDate = selectedDates.start || new Date();
            currentRangeLeftMonth = new Date(initDate);
            currentRangeLeftMonth.setDate(1);
            currentRangeRightMonth = new Date(currentRangeLeftMonth);
            currentRangeRightMonth.setMonth(currentRangeRightMonth.getMonth() + 1);
            
            // Reset selection state
            rangeSelectionState.selecting = false;
            rangeSelectionState.startDate = null;
            
            // Initialize range calendars
            renderRangeCalendars();
            updateSelectedRangeDisplay();
        } else if (type === 'quick') {
            // Project Span — select everything from the project's start date to
            // today. The start date is provided by the project context via the
            // #project_start_date hidden input.
            $('#date_picker_quick').show();
            $('#btn_current_week').hide();
            $('#btn_current_month').hide();

            var projectStartVal = $('#project_start_date').val();
            var spanStart = projectStartVal ? parseDate(projectStartVal) : null;
            if (!spanStart) {
                // No project start date available — fall back to the current
                // range start (or today) so the selection stays valid.
                spanStart = currentDateRange.start ? new Date(currentDateRange.start) : new Date();
            }
            var spanEnd = new Date();
            spanEnd.setHours(0, 0, 0, 0);

            selectedDates.start = spanStart;
            selectedDates.end = spanEnd;
            currentDateRange.type = 'project_span';

            updateSelectedRangeDisplay();
        } else if (type === 'month') {
            // Month view - show month grid instead of calendar
            $('#date_picker_month_view').show();
            currentDateRange.type = type;
            
            // Show/hide appropriate buttons
            $('#btn_current_week').hide();
            $('#btn_current_month').show();
            
            // When switching to month, update selected dates based on current range
            if (selectedDates.start) {
                // Convert to month containing the start date
                selectedDates.start = new Date(selectedDates.start.getFullYear(), selectedDates.start.getMonth(), 1);
                selectedDates.end = new Date(selectedDates.start.getFullYear(), selectedDates.start.getMonth() + 1, 0);
            }
            
            // Update calendar month to match selected month
            if (selectedDates.start) {
                currentCalendarMonth = new Date(selectedDates.start);
            }
            
            renderMonthGrid();
            updateSelectedRangeDisplay();
        } else {
            // Day or Week view - show calendar
            $('#date_picker_calendar').show();
            currentDateRange.type = type;
            
            // Show/hide appropriate buttons
            $('#btn_current_week').show();
            $('#btn_current_month').hide();
            
            // When switching to day/week, update selected dates based on current range
            if (type === 'day' && selectedDates.start) {
                // Keep the start date, make it single day
                selectedDates.end = new Date(selectedDates.start);
            } else if (type === 'week') {
                // Highlight the week containing the currently-selected date (or the
                // current week if nothing is selected yet). We only update the
                // highlight here — the actual filter is applied when the user
                // clicks a day (handleDayClick -> commitDateRange). We must NOT
                // mutate the hidden inputs here, otherwise switching tabs without
                // selecting would desync the loaded list from the inputs.
                var baseDate = selectedDates.start ? new Date(selectedDates.start) : new Date();
                selectedDates.start = getMonday(baseDate);
                selectedDates.end = getSunday(baseDate);

                // Update calendar month to show the selected week's month
                currentCalendarMonth = new Date(selectedDates.start);
            }
            
            renderCalendar();
            updateSelectedRangeDisplay();
        }
    }

    /**
     * Render calendar
     */
    function renderCalendar() {
        var year = currentCalendarMonth.getFullYear();
        var month = currentCalendarMonth.getMonth();
        
        // Update month/year display
        var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $('#calendar_month_year').text(monthNames[month] + ' ' + year);

        // Get first day of month and number of days
        var firstDay = new Date(year, month, 1);
        var lastDay = new Date(year, month + 1, 0);
        var daysInMonth = lastDay.getDate();
        var startingDayOfWeek = firstDay.getDay();
        
        // Adjust for Monday start (0 = Sunday, 1 = Monday, etc.)
        if (startingDayOfWeek === 0) startingDayOfWeek = 6;
        else startingDayOfWeek -= 1;

        // Calendar grid container
        var html = '';
        
        // Header row with week number column and day headers
        html += '<div class="calendar-week-row">';
        html += '<div class="calendar-week-number"></div>'; // Empty cell for week number column header
        var dayHeaders = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];
        dayHeaders.forEach(function(day, index) {
            var weekendClass = (index >= 5) ? 'weekend' : '';
            html += '<div class="calendar-day-header ' + weekendClass + '">' + day + '</div>';
        });
        html += '</div>';

        // Calendar days
        var currentWeek = 1;
        html += '<div class="calendar-week-row">';
        html += '<div class="calendar-week-number">' + getWeekNumber(new Date(year, month, 1)) + '</div>';

        // Previous month days
        var prevMonth = new Date(year, month, 0);
        var prevMonthDays = prevMonth.getDate();
        for (var i = startingDayOfWeek - 1; i >= 0; i--) {
            var day = prevMonthDays - i;
            var prevDate = new Date(year, month - 1, day);
            var dayOfWeek = prevDate.getDay();
            var weekendClass = (dayOfWeek === 0 || dayOfWeek === 6) ? ' weekend' : '';
            var prevDayClasses = 'other-month' + weekendClass;
            
            // Check if this previous month day is in the selected range (for week mode)
            if (selectedDates.start && selectedDates.end) {
                var dateCopy = new Date(prevDate);
                dateCopy.setHours(0, 0, 0, 0);
                var start = new Date(selectedDates.start);
                var end = new Date(selectedDates.end);
                start.setHours(0, 0, 0, 0);
                end.setHours(0, 0, 0, 0);
                
                if (dateCopy >= start && dateCopy <= end) {
                    var activeTab = $('.date-picker-tab.active').data('type');
                    if (activeTab === 'week') {
                        prevDayClasses += ' in-range';
                    } else if (dateCopy.getTime() === start.getTime() || dateCopy.getTime() === end.getTime()) {
                        prevDayClasses += ' selected';
                    } else if (dateCopy >= start && dateCopy <= end) {
                        prevDayClasses += ' in-range';
                    }
                }
            }
            
            html += '<div class="calendar-day ' + prevDayClasses + '" data-date="' + formatDate(prevDate) + '">' + day + '</div>';
        }

        // Current month days
        for (var day = 1; day <= daysInMonth; day++) {
            if ((startingDayOfWeek + day - 1) % 7 === 0 && day > 1) {
                html += '</div><div class="calendar-week-row">';
                html += '<div class="calendar-week-number">' + getWeekNumber(new Date(year, month, day)) + '</div>';
                currentWeek++;
            }

            var date = new Date(year, month, day);
            var dayClasses = getDayClasses(date);
            
            // Add weekend class for Saturdays and Sundays
            var dayOfWeek = date.getDay();
            if (dayOfWeek === 0 || dayOfWeek === 6) {
                dayClasses += ' weekend';
            }
            
            html += '<div class="calendar-day ' + dayClasses + '" data-date="' + formatDate(date) + '">' + day + '</div>';
        }

        // Next month days
        var remainingDays = 7 - ((startingDayOfWeek + daysInMonth) % 7);
        if (remainingDays < 7) {
            for (var day = 1; day <= remainingDays; day++) {
                var nextDate = new Date(year, month + 1, day);
                var dayOfWeek = nextDate.getDay();
                var weekendClass = (dayOfWeek === 0 || dayOfWeek === 6) ? ' weekend' : '';
                var nextDayClasses = 'other-month' + weekendClass;
                
                // Check if this next month day is in the selected range (for week mode)
                if (selectedDates.start && selectedDates.end) {
                    var dateCopy = new Date(nextDate);
                    dateCopy.setHours(0, 0, 0, 0);
                    var start = new Date(selectedDates.start);
                    var end = new Date(selectedDates.end);
                    start.setHours(0, 0, 0, 0);
                    end.setHours(0, 0, 0, 0);
                    
                    if (dateCopy >= start && dateCopy <= end) {
                        var activeTab = $('.date-picker-tab.active').data('type');
                        if (activeTab === 'week') {
                            nextDayClasses += ' in-range';
                        } else if (dateCopy.getTime() === start.getTime() || dateCopy.getTime() === end.getTime()) {
                            nextDayClasses += ' selected';
                        } else if (dateCopy >= start && dateCopy <= end) {
                            nextDayClasses += ' in-range';
                        }
                    }
                }
                
                html += '<div class="calendar-day ' + nextDayClasses + '" data-date="' + formatDate(nextDate) + '">' + day + '</div>';
            }
        }
        html += '</div>';

        $('#calendar_grid').html(html);
    }

    /**
     * Get CSS classes for a day
     */
    function getDayClasses(date) {
        var classes = [];
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var dateCopy = new Date(date);
        dateCopy.setHours(0, 0, 0, 0);

        if (dateCopy.getTime() === today.getTime()) {
            classes.push('today');
        }

        // Get active tab to determine selection style
        var activeTab = $('.date-picker-tab.active').data('type');
        
        if (selectedDates.start && selectedDates.end) {
            var start = new Date(selectedDates.start);
            var end = new Date(selectedDates.end);
            start.setHours(0, 0, 0, 0);
            end.setHours(0, 0, 0, 0);
            dateCopy = new Date(date);
            dateCopy.setHours(0, 0, 0, 0);

            if (activeTab === 'day') {
                // Day mode: Only highlight the exact selected day (solid blue circle)
                if (dateCopy.getTime() === start.getTime() && dateCopy.getTime() === end.getTime()) {
                    classes.push('selected');
                }
            } else if (activeTab === 'week') {
                // Week mode: First day (Monday) and last day (Sunday) are 'selected' (dark), others are 'in-range' (lighter)
                if (dateCopy.getTime() === start.getTime() || dateCopy.getTime() === end.getTime()) {
                    classes.push('selected');
                } else if (dateCopy >= start && dateCopy <= end) {
                    classes.push('in-range');
                }
            } else if (activeTab === 'month') {
                // Month mode: Highlight the entire month range
                if (dateCopy.getTime() === start.getTime() || dateCopy.getTime() === end.getTime()) {
                    classes.push('selected');
                } else if (dateCopy >= start && dateCopy <= end) {
                    classes.push('in-range');
                }
            } else {
                // Range mode: Show start/end and in-between
                if (dateCopy.getTime() === start.getTime() || dateCopy.getTime() === end.getTime()) {
                    classes.push('selected');
                } else if (dateCopy >= start && dateCopy <= end) {
                    classes.push('in-range');
                }
            }
        } else if (selectedDates.start) {
            // Only start date selected (range mode)
            var start = new Date(selectedDates.start);
            start.setHours(0, 0, 0, 0);
            dateCopy = new Date(date);
            dateCopy.setHours(0, 0, 0, 0);
            if (dateCopy.getTime() === start.getTime()) {
                classes.push('selected');
            }
        }

        return classes.join(' ');
    }

    /**
     * Handle day click
     */
    function handleDayClick($day) {
        var dateStr = $day.data('date');
        if (!dateStr) return;

        var clickedDate = new Date(dateStr);
        clickedDate.setHours(0, 0, 0, 0);

        // Get active tab type
        var activeTab = $('.date-picker-tab.active').data('type');
        
        if (activeTab === 'day') {
            // Day mode: Select only the clicked day (like first image - single day selection)
            selectedDates.start = clickedDate;
            selectedDates.end = clickedDate;
            currentDateRange.type = 'day';
        } else if (activeTab === 'week') {
            // Week mode: Select the entire week containing the clicked day (like second image - week range highlighted)
            selectedDates.start = getMonday(clickedDate);
            selectedDates.end = getSunday(clickedDate);
            currentDateRange.type = 'week';
        } else if (activeTab === 'month') {
            // Month mode: Select the entire month containing the clicked day
            selectedDates.start = new Date(clickedDate.getFullYear(), clickedDate.getMonth(), 1);
            selectedDates.end = new Date(clickedDate.getFullYear(), clickedDate.getMonth() + 1, 0);
            currentDateRange.type = 'month';
        } else if (activeTab === 'range') {
            // Range mode - toggle selection (start/end date selection)
            if (!selectedDates.start || (selectedDates.start && selectedDates.end)) {
                selectedDates.start = clickedDate;
                selectedDates.end = null;
            } else {
                if (clickedDate < selectedDates.start) {
                    selectedDates.end = selectedDates.start;
                    selectedDates.start = clickedDate;
                } else {
                    selectedDates.end = clickedDate;
                }
            }
            currentDateRange.type = 'range';
        }

        renderCalendar();
        updateSelectedRangeDisplay();
        // Selection only highlights here — the filter is applied when the user
        // clicks the OK button (applyDateRange -> commitDateRange).
    }

    /**
     * Navigate calendar
     */
    function navigateCalendar(unit, direction) {
        if (unit === 'year') {
            currentCalendarMonth.setFullYear(
                currentCalendarMonth.getFullYear() + (direction === 'next' ? 1 : -1)
            );
        } else {
            currentCalendarMonth.setMonth(
                currentCalendarMonth.getMonth() + (direction === 'next' ? 1 : -1)
            );
        }
        renderCalendar();
    }

    /**
     * Sync the picker's internal state from the hidden inputs (the single source
     * of truth read by TimelogModule.loadTimelogs). Called before navigation so
     * stepping is correct even when the range/type was changed elsewhere (header
     * navigation, a saved filter, etc.) since the picker was last opened.
     */
    function syncFromInputs() {
        var startVal = $('#current_week_start').val();
        var endVal = $('#current_week_end').val();
        var typeVal = $('#current_date_range_type').val();

        if (startVal) {
            currentDateRange.start = parseDate(startVal) || new Date(startVal);
        }
        if (endVal) {
            currentDateRange.end = parseDate(endVal) || new Date(endVal);
        }
        if (typeVal) {
            currentDateRange.type = typeVal;
        }
        if (currentDateRange.start) {
            selectedDates.start = new Date(currentDateRange.start);
            currentCalendarMonth = new Date(currentDateRange.start);
        }
        if (currentDateRange.end) {
            selectedDates.end = new Date(currentDateRange.end);
        }
    }

    /**
     * Navigate range (prev/next based on current type) - Public method.
     * Steps by the selected unit: day (+/-1 day), week (+/-1 week), month
     * (+/-1 month). Date Range and Project Span are fixed custom ranges and are
     * not stepped.
     */
    function navigateRange(direction) {
        // Re-sync from the inputs so we step from the currently-applied range,
        // regardless of what changed the range last.
        syncFromInputs();

        var type = currentDateRange.type;

        // Fixed custom ranges: nothing to step.
        if (type === 'range' || type === 'project_span') {
            return;
        }

        var step = direction === 'next' ? 1 : -1;
        var start = new Date(currentDateRange.start);
        var end;

        if (type === 'day') {
            start.setDate(start.getDate() + step);
            end = new Date(start);
        } else if (type === 'month') {
            start = new Date(start.getFullYear(), start.getMonth() + step, 1);
            end = new Date(start.getFullYear(), start.getMonth() + 1, 0);
        } else {
            // Week (default)
            start.setDate(start.getDate() + step * 7);
            start = getMonday(start);
            end = getSunday(start);
        }

        currentDateRange.start = start;
        currentDateRange.end = end;
        selectedDates.start = new Date(start);
        selectedDates.end = new Date(end);
        currentCalendarMonth = new Date(start);

        // Update hidden inputs (read by loadTimelogs and the header display)
        $('#current_week_start').val(formatDate(start));
        $('#current_week_end').val(formatDate(end));
        $('#current_date_range_type').val(type);

        updateDateDisplay();
        updateSelectedRangeDisplay();

        // Keep whichever calendar view is on screen in sync.
        if (type === 'month') {
            renderMonthGrid();
        } else {
            renderCalendar();
        }

        // Reload timelogs if picker is closed (header navigation). When the
        // picker is open, the selection updates live and applies on OK.
        if (!$('#timelog_date_picker_wrapper').is(':visible')) {
            if (typeof TimelogModule !== 'undefined' && TimelogModule.loadTimelogs) {
                TimelogModule.loadTimelogs();
            }
        }
    }

    /**
     * Apply quick filter
     */
    function applyQuickFilter(filter) {
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var start, end;

        switch(filter) {
            case 'today':
                start = new Date(today);
                end = new Date(today);
                currentDateRange.type = 'day';
                break;
            case 'yesterday':
                start = new Date(today);
                start.setDate(start.getDate() - 1);
                end = new Date(start);
                currentDateRange.type = 'day';
                break;
            case 'this_week':
                start = getMonday(today);
                end = getSunday(today);
                currentDateRange.type = 'week';
                break;
            case 'last_week':
                start = getMonday(today);
                start.setDate(start.getDate() - 7);
                end = getSunday(start);
                currentDateRange.type = 'week';
                break;
            case 'this_month':
                start = new Date(today.getFullYear(), today.getMonth(), 1);
                end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                currentDateRange.type = 'month';
                break;
            case 'last_month':
                start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                end = new Date(today.getFullYear(), today.getMonth(), 0);
                currentDateRange.type = 'month';
                break;
            case 'this_year':
                start = new Date(today.getFullYear(), 0, 1);
                end = new Date(today.getFullYear(), 11, 31);
                currentDateRange.type = 'range';
                break;
            case 'last_year':
                start = new Date(today.getFullYear() - 1, 0, 1);
                end = new Date(today.getFullYear() - 1, 11, 31);
                currentDateRange.type = 'range';
                break;
        }

        currentDateRange.start = start;
        currentDateRange.end = end;
        selectedDates.start = new Date(start);
        selectedDates.end = new Date(end);
        
        // Switch to appropriate tab
        if (currentDateRange.type === 'range') {
            switchTab('range');
            $('#range_from_date').val(formatDateForInput(start));
            $('#range_to_date').val(formatDateForInput(end));
        } else {
            switchTab(currentDateRange.type);
        }
        
        renderCalendar();
        updateSelectedRangeDisplay();
    }

    /**
     * Apply date range
     */
    function applyDateRange() {
        var start, end;
        
        if ($('#tab_range').hasClass('active')) {
            // Range is chosen via the dual-calendar grid, which stores the
            // selection in selectedDates (there are no from/to text inputs).
            if (!selectedDates.start || !selectedDates.end) {
                alert_float('warning', 'Please select a date range');
                return;
            }

            start = new Date(selectedDates.start);
            end = new Date(selectedDates.end);

            // Normalize order in case end was picked before start
            if (start > end) {
                var tmpDate = start;
                start = end;
                end = tmpDate;
            }

            currentDateRange.type = 'range';
        } else if ($('#tab_quick').hasClass('active')) {
            // Project Span — a fixed custom range covering the project's span.
            // Dates were already set in applyQuickFilter; tag the type so the
            // prev/next navigation is disabled for it.
            start = selectedDates.start;
            end = selectedDates.end;
            currentDateRange.type = 'project_span';
        } else {
            start = selectedDates.start;
            end = selectedDates.end;
        }

        if (!start || !end) {
            alert_float('warning', 'Please select a date range');
            return;
        }

        commitDateRange(start, end, currentDateRange.type);
    }

    /**
     * Commit a selected date range: persist it to the hidden inputs, update the
     * display, close the picker and reload the timelog list. This is the single
     * place that "applies the filter", shared by the OK button and by the
     * auto-apply-on-selection behavior (Zoho-style — selecting a day/week/month
     * or completing a range filters immediately, no separate OK click needed).
     */
    function commitDateRange(start, end, type) {
        if (!start || !end) {
            return;
        }

        // Normalize order in case end was picked before start
        if (start > end) {
            var tmpDate = start;
            start = end;
            end = tmpDate;
        }

        currentDateRange.start = start;
        currentDateRange.end = end;
        if (type) {
            currentDateRange.type = type;
        }

        // Update hidden inputs (read back by TimelogModule.loadTimelogs and the
        // header prev/next navigation)
        $('#current_week_start').val(formatDate(start));
        $('#current_week_end').val(formatDate(end));
        $('#current_date_range_type').val(currentDateRange.type);

        // Update display
        updateDateDisplay();

        // Close picker
        closeDatePicker();

        // Reload timelogs
        if (typeof TimelogModule !== 'undefined' && TimelogModule.loadTimelogs) {
            TimelogModule.loadTimelogs();
        }
    }

    /**
     * Set current week
     */
    function setCurrentWeek() {
        var today = new Date();
        currentDateRange.start = getMonday(today);
        currentDateRange.end = getSunday(today);
        currentDateRange.type = 'week';
        selectedDates.start = new Date(currentDateRange.start);
        selectedDates.end = new Date(currentDateRange.end);
        
        switchTab('week');
        renderCalendar();
        updateSelectedRangeDisplay();
    }

    /**
     * Set current month
     */
    function setCurrentMonth() {
        var today = new Date();
        var activeTab = $('.date-picker-tab.active').data('type');
        
        if (activeTab === 'range') {
            // For range view, set to current month range and update calendars
            currentDateRange.start = new Date(today.getFullYear(), today.getMonth(), 1);
            currentDateRange.end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            currentDateRange.type = 'range';
            selectedDates.start = new Date(currentDateRange.start);
            selectedDates.end = new Date(currentDateRange.end);
            currentRangeLeftMonth = new Date(today);
            currentRangeRightMonth = new Date(today);
            currentRangeRightMonth.setMonth(currentRangeRightMonth.getMonth() + 1);
            renderRangeCalendars();
        } else {
            // For month view
            currentDateRange.start = new Date(today.getFullYear(), today.getMonth(), 1);
            currentDateRange.end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            currentDateRange.type = 'month';
            selectedDates.start = new Date(currentDateRange.start);
            selectedDates.end = new Date(currentDateRange.end);
            currentCalendarMonth = new Date(today);
            renderMonthGrid();
        }
        
        updateSelectedRangeDisplay();
    }

    /**
     * Update date display
     */
    function updateDateDisplay() {
        if (!currentDateRange.start || !currentDateRange.end) return;
        
        var startStr = formatDateDisplay(currentDateRange.start);
        var endStr = formatDateDisplay(currentDateRange.end);
        var displayText = startStr + ' - ' + endStr;
        
        if (currentDateRange.type === 'week') {
            var weekNum = getWeekNumber(currentDateRange.start);
            displayText += ' (' + (typeof _l !== 'undefined' ? _l('week') : 'Week') + ' ' + weekNum + ')';
        } else if (currentDateRange.type === 'month') {
            displayText += ' (' + (typeof _l !== 'undefined' ? _l('month') : 'Month') + ')';
        } else if (currentDateRange.type === 'project_span') {
            displayText += ' (' + (typeof _l !== 'undefined' ? _l('project_span') : 'Project Span') + ')';
        }

        $('#date_display').text(displayText);
    }

    /**
     * Update selected range display in picker
     */
    function updateSelectedRangeDisplay() {
        if (!selectedDates.start || !selectedDates.end) return;
        
        var startStr = formatDateDisplay(selectedDates.start);
        var endStr = formatDateDisplay(selectedDates.end);
        var displayText = startStr + ' to ' + endStr;
        
        // Get active tab to determine display format
        var activeTab = $('.date-picker-tab.active').data('type');
        
        if (activeTab === 'week') {
            var weekNum = getWeekNumber(selectedDates.start);
            displayText += ' (' + (typeof _l !== 'undefined' ? _l('week') : 'Week') + ' - ' + weekNum + ')';
        } else if (activeTab === 'day') {
            // For day mode, show just the date
            displayText = startStr;
        } else if (activeTab === 'month') {
            displayText += ' (' + (typeof _l !== 'undefined' ? _l('month') : 'Month') + ')';
        } else if (activeTab === 'quick') {
            displayText += ' (' + (typeof _l !== 'undefined' ? _l('project_span') : 'Project Span') + ')';
        }

        $('#date_picker_selected_range').text(displayText);
    }

    /**
     * Validate range dates
     */
    function validateRangeDates() {
        var fromStr = $('#range_from_date').val();
        var toStr = $('#range_to_date').val();
        
        if (fromStr && toStr) {
            var from = parseDateInput(fromStr);
            var to = parseDateInput(toStr);
            
            if (from > to) {
                $('#range_to_date').css('border-color', '#dc3545');
            } else {
                $('#range_to_date').css('border-color', '');
            }
        }
    }

    /**
     * Helper functions
     */
    function getMonday(date) {
        var d = new Date(date);
        var day = d.getDay();
        var diff = d.getDate() - day + (day === 0 ? -6 : 1);
        return new Date(d.setDate(diff));
    }

    function getSunday(date) {
        var monday = getMonday(date);
        var sunday = new Date(monday);
        sunday.setDate(sunday.getDate() + 6);
        return sunday;
    }

    function getWeekNumber(date) {
        var d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
        var dayNum = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
        var yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
        return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
    }

    function formatDate(date) {
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function formatDateDisplay(date) {
        var day = String(date.getDate()).padStart(2, '0');
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var year = date.getFullYear();
        return day + '/' + month + '/' + year;
    }

    function formatDateForInput(date) {
        return formatDateDisplay(date);
    }

    function parseDateInput(dateStr) {
        // Parse d/m/Y format
        var parts = dateStr.split('/');
        if (parts.length === 3) {
            return new Date(parseInt(parts[2]), parseInt(parts[1]) - 1, parseInt(parts[0]));
        }
        return new Date(dateStr);
    }

    /**
     * Render month grid (3 rows x 4 columns)
     */
    function renderMonthGrid() {
        var year = currentCalendarMonth.getFullYear();
        
        // Update year display
        $('#month_year_display').text(year);
        
        var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var html = '';
        
        // Render 12 months in 3 rows x 4 columns
        for (var i = 0; i < 12; i++) {
            var monthDate = new Date(year, i, 1);
            var monthName = monthNames[i];
            var isSelected = false;
            
            // Check if this month is selected
            if (selectedDates.start && selectedDates.end) {
                var selectedMonth = selectedDates.start.getMonth();
                var selectedYear = selectedDates.start.getFullYear();
                if (i === selectedMonth && year === selectedYear) {
                    isSelected = true;
                }
            }
            
            var monthClass = isSelected ? 'month-item selected' : 'month-item';
            html += '<div class="' + monthClass + '" data-month="' + i + '" data-year="' + year + '">' + monthName + '</div>';
        }
        
        $('#month_grid').html(html);
    }

    /**
     * Navigate month year
     */
    function navigateMonthYear(direction) {
        if (direction === 'next') {
            currentCalendarMonth.setFullYear(currentCalendarMonth.getFullYear() + 1);
        } else {
            currentCalendarMonth.setFullYear(currentCalendarMonth.getFullYear() - 1);
        }
        renderMonthGrid();
    }

    /**
     * Handle month click
     */
    function handleMonthClick($month) {
        var month = parseInt($month.data('month'));
        var year = parseInt($month.data('year'));
        
        // Set selected month
        selectedDates.start = new Date(year, month, 1);
        selectedDates.end = new Date(year, month + 1, 0);
        currentDateRange.type = 'month';

        // Update display (selection only — applied on OK button click)
        renderMonthGrid();
        updateSelectedRangeDisplay();
    }

    /**
     * Render dual calendars for range view
     */
    function renderRangeCalendars() {
        renderRangeCalendar('left', currentRangeLeftMonth);
        renderRangeCalendar('right', currentRangeRightMonth);
    }

    /**
     * Render a single calendar for range view
     */
    function renderRangeCalendar(side, monthDate) {
        var year = monthDate.getFullYear();
        var month = monthDate.getMonth();
        var firstDay = new Date(year, month, 1);
        var lastDay = new Date(year, month + 1, 0);
        var startDate = new Date(firstDay);
        
        // Get Monday of the week containing the first day
        var dayOfWeek = firstDay.getDay();
        var mondayOffset = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
        startDate.setDate(firstDay.getDate() + mondayOffset);
        
        var html = '';
        
        // Day headers - Remove "Wk" header, keep empty cell for week number column
        html += '<div class="range-calendar-day-header"></div>';
        var dayNames = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];
        for (var i = 0; i < 7; i++) {
            var isWeekend = (i === 5 || i === 6);
            var headerClass = isWeekend ? 'range-calendar-day-header weekend' : 'range-calendar-day-header';
            html += '<div class="' + headerClass + '">' + dayNames[i] + '</div>';
        }
        
        // Calendar days - Dynamic rows for both calendars to show all dates
        var currentDate = new Date(startDate);
        var lastDayOfMonth = lastDay.getDate();
        
        // Calculate how many weeks are needed to display all dates of the month
        // We need to show from startDate (Monday of week containing 1st) 
        // until we've covered the last day of the month
        var lastDateOfMonth = new Date(year, month, lastDayOfMonth);
        lastDateOfMonth.setHours(0, 0, 0, 0);
        
        // Calculate how many days from startDate to the last day
        var daysFromStart = Math.floor((lastDateOfMonth.getTime() - startDate.getTime()) / (1000 * 60 * 60 * 24));
        
        // Calculate which week row the last day is in (0-indexed)
        var weekOfLastDay = Math.floor(daysFromStart / 7);
        
        // We need to show that week + 1 (since it's 0-indexed)
        var maxWeeks = weekOfLastDay + 1;
        
        // Ensure we show at least 5 weeks, but allow more if needed (up to 6-7 for months that need it)
        maxWeeks = Math.max(5, maxWeeks);
        
        for (var week = 0; week < maxWeeks; week++) {
            var weekStart = new Date(currentDate);
            var weekNumber = getWeekNumber(weekStart);
            
            // Week number
            html += '<div class="range-calendar-week-number">' + weekNumber + '</div>';
            
            // Days of the week
            for (var day = 0; day < 7; day++) {
                var dateCopy = new Date(currentDate);
                var isCurrentMonth = dateCopy.getMonth() === month;
                var isWeekend = (day === 5 || day === 6);
                
                // Skip dates from next month in the last row for both calendars
                if (week === maxWeeks - 1 && !isCurrentMonth && dateCopy.getMonth() > month) {
                    // Add empty cell for next month dates in last row
                    html += '<div class="range-calendar-day"></div>';
                    currentDate.setDate(currentDate.getDate() + 1);
                    continue;
                }
                
                var classes = ['range-calendar-day'];
                if (!isCurrentMonth) {
                    classes.push('other-month');
                }
                if (isWeekend) {
                    classes.push('weekend');
                }

                // Range selection highlight: the start and end days get
                // 'selected' (solid) and the days in between get 'in-range'
                // (light). The 0.2s CSS transition on .range-calendar-day
                // animates the fill as the selection changes. Without these
                // classes the range picker gave no visual feedback at all.
                var inSelection = false;
                if (selectedDates.start) {
                    var cellDate = new Date(dateCopy);
                    cellDate.setHours(0, 0, 0, 0);
                    var rangeStart = new Date(selectedDates.start);
                    rangeStart.setHours(0, 0, 0, 0);
                    var rangeEnd = new Date(selectedDates.end || selectedDates.start);
                    rangeEnd.setHours(0, 0, 0, 0);
                    if (rangeStart > rangeEnd) {
                        var tmpDate = rangeStart;
                        rangeStart = rangeEnd;
                        rangeEnd = tmpDate;
                    }

                    if (cellDate.getTime() === rangeStart.getTime() || cellDate.getTime() === rangeEnd.getTime()) {
                        classes.push('selected');
                        inSelection = true;
                    } else if (cellDate > rangeStart && cellDate < rangeEnd) {
                        classes.push('in-range');
                        inSelection = true;
                    }
                }

                // Only highlight today's date when it is not part of the current
                // selection (so the range fill is visible), and only in the
                // calendar that represents the current month (to avoid showing
                // a dark highlight on the same day number in the next month)
                var today = new Date();
                today.setHours(0, 0, 0, 0);
                var dateCopy2 = new Date(dateCopy);
                dateCopy2.setHours(0, 0, 0, 0);

                var isToday =
                    dateCopy2.getTime() === today.getTime() &&
                    monthDate.getFullYear() === today.getFullYear() &&
                    monthDate.getMonth() === today.getMonth();

                if (isToday && !inSelection) {
                    classes.push('today');
                }

                html += '<div class="' + classes.join(' ') + '" data-date="' + formatDate(dateCopy) + '">' + dateCopy.getDate() + '</div>';
                
                currentDate.setDate(currentDate.getDate() + 1);
            }
        }
        
        var gridId = side === 'left' ? 'range_calendar_grid_left' : 'range_calendar_grid_right';
        $('#' + gridId).html(html);
        
        // Update month/year display
        var monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        if (side === 'left') {
            $('#range_month_year_left').text(monthNames[month] + ' ' + year);
        } else {
            $('#range_month_year_right').text(monthNames[month] + ' ' + year);
        }
    }

    /**
     * Navigate range calendars
     */
    function navigateRangeCalendar(unit, direction) {
        if (unit === 'year') {
            if (direction === 'next') {
                currentRangeLeftMonth.setFullYear(currentRangeLeftMonth.getFullYear() + 1);
                currentRangeRightMonth.setFullYear(currentRangeRightMonth.getFullYear() + 1);
            } else {
                currentRangeLeftMonth.setFullYear(currentRangeLeftMonth.getFullYear() - 1);
                currentRangeRightMonth.setFullYear(currentRangeRightMonth.getFullYear() - 1);
            }
        } else if (unit === 'month') {
            if (direction === 'next') {
                currentRangeLeftMonth.setMonth(currentRangeLeftMonth.getMonth() + 1);
                currentRangeRightMonth.setMonth(currentRangeRightMonth.getMonth() + 1);
            } else {
                currentRangeLeftMonth.setMonth(currentRangeLeftMonth.getMonth() - 1);
                currentRangeRightMonth.setMonth(currentRangeRightMonth.getMonth() - 1);
            }
        }
        renderRangeCalendars();
    }

    /**
     * Handle range day click
     */
    function handleRangeDayClick($day) {
        var dateStr = $day.data('date');
        if (!dateStr) return;
        
        var clickedDate = parseDate(dateStr);
        if (!clickedDate) return;
        
        clickedDate.setHours(0, 0, 0, 0);

        // If clicking the same date that's already selected as start, treat as new selection
        if (rangeSelectionState.selecting && rangeSelectionState.startDate) {
            var startTime = rangeSelectionState.startDate.getTime();
            var clickedTime = clickedDate.getTime();

            if (startTime === clickedTime) {
                // Same date clicked - reset selection
                rangeSelectionState.selecting = false;
                rangeSelectionState.startDate = null;
                selectedDates.start = new Date(clickedDate);
                selectedDates.end = new Date(clickedDate);
            } else {
                // Complete selection
                var start = rangeSelectionState.startDate;
                var end = clickedDate;

                if (end < start) {
                    // Swap if end is before start
                    selectedDates.start = new Date(end);
                    selectedDates.end = new Date(start);
                } else {
                    selectedDates.start = new Date(start);
                    selectedDates.end = new Date(end);
                }

                rangeSelectionState.selecting = false;
                rangeSelectionState.startDate = null;
            }
        } else {
            // Start new selection
            rangeSelectionState.selecting = true;
            rangeSelectionState.startDate = new Date(clickedDate);
            selectedDates.start = new Date(clickedDate);
            selectedDates.end = new Date(clickedDate);
        }

        currentDateRange.type = 'range';
        currentDateRange.start = new Date(selectedDates.start);
        currentDateRange.end = new Date(selectedDates.end);

        renderRangeCalendars();
        updateSelectedRangeDisplay();
        // Selection only — the range is applied when the user clicks OK.
    }

    /**
     * Preview the tentative range from the picked start date to the hovered
     * date. Applied as lightweight CSS classes (no re-render) so the highlight
     * animates smoothly as the pointer moves across the calendars.
     */
    function previewRangeTo(hoverDate) {
        if (!hoverDate || !rangeSelectionState.startDate) return;

        var start = new Date(rangeSelectionState.startDate);
        start.setHours(0, 0, 0, 0);
        var end = new Date(hoverDate);
        end.setHours(0, 0, 0, 0);
        if (start > end) {
            var tmp = start;
            start = end;
            end = tmp;
        }

        $('.range-calendar-day').each(function() {
            var $cell = $(this);
            var dateStr = $cell.data('date');
            $cell.removeClass('preview-in-range preview-end');
            if (!dateStr) return;
            var cell = parseDate(dateStr);
            if (!cell) return;
            if (cell.getTime() === start.getTime() || cell.getTime() === end.getTime()) {
                $cell.addClass('preview-end');
            } else if (cell > start && cell < end) {
                $cell.addClass('preview-in-range');
            }
        });
    }

    /**
     * Remove any range hover-preview highlight.
     */
    function clearRangePreview() {
        $('.range-calendar-day').removeClass('preview-in-range preview-end');
    }

    /**
     * Parse date string (format: YYYY-MM-DD)
     */
    function parseDate(dateStr) {
        if (!dateStr) return null;
        var parts = dateStr.split('-');
        if (parts.length !== 3) return null;
        var date = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        date.setHours(0, 0, 0, 0);
        return date;
    }

    /**
     * Public API
     */
    return {
        init: init,
        openDatePicker: openDatePicker,
        closeDatePicker: closeDatePicker,
        navigateRange: navigateRange,
        syncFromInputs: syncFromInputs
    };

})();

// Make globally available
if (typeof window !== 'undefined') {
    window.TimelogDatePicker = TimelogDatePicker;
}
