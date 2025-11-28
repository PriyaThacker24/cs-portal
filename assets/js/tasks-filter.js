/**
 * Tasks Filter Panel - Zoho Style
 * Handles all filter panel interactions for tasks listing
 */

var TasksFilter = (function() {
    'use strict';

    var $filterPanel;
    var $filterPanelContent;
    var $filterOverlay;
    var $accordionItems;
    var currentFilters = {};

    /**
     * Initialize the filter panel
     */
    function init() {
        $filterPanel = $('#tasksFilterPanel');
        $filterPanelContent = $filterPanel.find('.filter-panel-content');
        $filterOverlay = $filterPanel.find('.filter-panel-overlay');
        $accordionItems = $filterPanel.find('.filter-accordion-item');

        bindEvents();
        initializeAccordionState();
        initializeDatepickers();
        initializeSelectPickers();
        loadSavedFilters();
    }

    /**
     * Bind all event handlers
     */
    function bindEvents() {
        // Open filter panel
        $(document).on('click', '#btnOpenTasksFilter', function(e) {
            e.preventDefault();
            openFilterPanel();
        });

        // Close filter panel
        $(document).on('click', '.tasks-filter-panel .filter-close, .tasks-filter-panel .filter-panel-overlay', function(e) {
            e.preventDefault();
            closeFilterPanel();
        });

        // Toggle accordion items
        $(document).on('click', '.tasks-filter-panel .filter-accordion-header', function(e) {
            e.preventDefault();
            var $item = $(this).closest('.filter-accordion-item');
            toggleAccordion($item);
        });

        // Filter search
        $(document).on('input', '.tasks-filter-panel .filter-search-input', function() {
            filterSearchItems($(this).val());
        });

        // Reset filters
        $(document).on('click', '.tasks-filter-reset', function(e) {
            e.preventDefault();
            resetFilters();
        });

        // Apply filters (Find button)
        $(document).on('click', '.btn-tasks-filter-find', function(e) {
            e.preventDefault();
            applyFilters();
        });

        // Cancel button
        $(document).on('click', '.btn-tasks-filter-cancel', function(e) {
            e.preventDefault();
            closeFilterPanel();
        });

        // Track value changes within accordion bodies
        $(document).on('change', '.tasks-filter-panel .filter-accordion-item input, .tasks-filter-panel .filter-accordion-item select', function() {
            markFilterAsActive($(this));
        });

        // Start Date operator change handler
        $(document).on('change', '#tasks_start_date_operator_select', function() {
            handleStartDateOperatorChange($(this).val());
        });

        // Due Date operator change handler
        $(document).on('change', '#tasks_due_date_operator_select', function() {
            handleDueDateOperatorChange($(this).val());
        });
    }

    /**
     * Handle Start Date operator dropdown change
     */
    function handleStartDateOperatorChange(operator) {
        $('.tasks-start-date-input-group').hide();
        $('[name="start_date_value"], [name="start_date_from"], [name="start_date_to"]', $filterPanel).val('');
        
        var presetOperators = [
            'today', 'yesterday', 'tomorrow', 'till_yesterday', 
            'this_week', 'last_week', 'next_week',
            'this_month', 'last_month', 'next_month',
            'last_7_days', 'next_30_days', 'unscheduled'
        ];
        
        var singleDateOperators = ['is', 'less_than', 'greater_than', 'less_than_or_equal', 'greater_than_or_equal'];
        
        if (presetOperators.indexOf(operator) !== -1) {
            return;
        }
        
        if (singleDateOperators.indexOf(operator) !== -1) {
            $('#tasks_start_date_single_picker').show();
            return;
        }
        
        if (operator === 'between') {
            $('#tasks_start_date_range_picker').show();
            $('#tasks_start_date_range_picker_end').show();
            return;
        }
    }

    /**
     * Handle Due Date operator dropdown change
     */
    function handleDueDateOperatorChange(operator) {
        $('.tasks-due-date-input-group').hide();
        $('[name="due_date_value"], [name="due_date_from"], [name="due_date_to"]', $filterPanel).val('');
        
        var presetOperators = [
            'today', 'yesterday', 'tomorrow', 'till_yesterday', 
            'this_week', 'last_week', 'next_week',
            'this_month', 'last_month', 'next_month',
            'last_7_days', 'next_30_days', 'unscheduled'
        ];
        
        var singleDateOperators = ['is', 'less_than', 'greater_than', 'less_than_or_equal', 'greater_than_or_equal'];
        
        if (presetOperators.indexOf(operator) !== -1) {
            return;
        }
        
        if (singleDateOperators.indexOf(operator) !== -1) {
            $('#tasks_due_date_single_picker').show();
            return;
        }
        
        if (operator === 'between') {
            $('#tasks_due_date_range_picker').show();
            $('#tasks_due_date_range_picker_end').show();
            return;
        }
    }

    /**
     * Open the filter panel
     */
    function openFilterPanel() {
        $filterPanel.addClass('active');
        $('body').css('overflow', 'hidden');
    }

    /**
     * Close the filter panel
     */
    function closeFilterPanel() {
        $filterPanel.removeClass('active');
        $('body').css('overflow', '');
    }

    /**
     * Initialize accordion state
     */
    function initializeAccordionState() {
        $accordionItems.each(function() {
            var $item = $(this);
            var $header = $item.find('.filter-accordion-header');
            var $body = $item.find('.filter-accordion-body');

            if ($item.hasClass('is-open')) {
                $header.attr('aria-expanded', 'true');
                $body.attr('aria-hidden', 'false').show();
            } else {
                $header.attr('aria-expanded', 'false');
                $body.attr('aria-hidden', 'true').hide();
            }
        });
    }

    /**
     * Toggle accordion
     */
    function toggleAccordion($item, forceState) {
        var shouldOpen = typeof forceState === 'boolean' ? forceState : !$item.hasClass('is-open');
        var $header = $item.find('.filter-accordion-header');
        var $body = $item.find('.filter-accordion-body');

        if (shouldOpen) {
            $item.addClass('is-open');
            $header.attr('aria-expanded', 'true');
            $body.attr('aria-hidden', 'false').stop(true, true).slideDown(200);
        } else {
            $item.removeClass('is-open');
            $header.attr('aria-expanded', 'false');
            $body.attr('aria-hidden', 'true').stop(true, true).slideUp(200);
        }
    }

    /**
     * Filter search functionality
     */
    function filterSearchItems(searchTerm) {
        searchTerm = searchTerm.toLowerCase();
        
        $filterPanel.find('.filter-accordion-item').each(function() {
            var $item = $(this);
            var filterLabel = $item.find('.filter-label').text().toLowerCase();
            $item.toggle(filterLabel.indexOf(searchTerm) > -1);
        });
    }

    /**
     * Mark filter as active
     */
    function markFilterAsActive($input) {
        var $accordion = $input.closest('.filter-accordion-item');
        var hasValue = false;
        
        $accordion.find('input, select').each(function() {
            var val = $(this).val();
            if (val && val.length > 0 && val != '') {
                hasValue = true;
                return false;
            }
        });
        
        if (hasValue) {
            $accordion.addClass('has-value');
        } else {
            $accordion.removeClass('has-value');
        }
    }

    /**
     * Reset all filters
     */
    function resetFilters() {
        $filterPanel.find('.filter-accordion-item input[type="text"]').val('');
        $filterPanel.find('.filter-accordion-item select').val('').selectpicker('refresh');

        $accordionItems.removeClass('has-value is-open');
        $accordionItems.find('.filter-accordion-header').attr('aria-expanded', 'false');
        $accordionItems.find('.filter-accordion-body').attr('aria-hidden', 'true').stop(true, true).slideUp(0);
        
        $('input[name="tasks_filter_match"][value="any"]').prop('checked', true);
        
        currentFilters = {};
        
        if (typeof $.fn.DataTable !== 'undefined' && $('.table-tasks').length) {
            $('.table-tasks').DataTable().ajax.reload();
        }
        
        alert_float('success', 'Filters reset successfully');
    }

    /**
     * Apply filters
     */
    function applyFilters() {
        currentFilters = {};
        
        var matchCondition = $('input[name="tasks_filter_match"]:checked').val();
        currentFilters.match = matchCondition;
        
        $accordionItems.each(function() {
            var $panel = $(this);
            var filterType = $panel.data('filter');
            var filterValue = {};
            
            // Special handling for date filters
            if (filterType === 'start_date') {
                filterValue = collectDateFilterValues($panel, 'start_date');
            } else if (filterType === 'due_date') {
                filterValue = collectDateFilterValues($panel, 'due_date');
            } else if (filterType === 'assigned') {
                filterValue = collectAssignedFilterValues($panel);
            } else if (filterType === 'created_by') {
                filterValue = collectCreatedByFilterValues($panel);
            } else {
                $panel.find('input, select').each(function() {
                    var $field = $(this);
                    var fieldName = $field.attr('name');
                    var fieldValue = $field.val();
                    
                    if (fieldName && fieldValue && fieldValue.length > 0) {
                        var cleanName = fieldName.replace(filterType + '_', '');
                        cleanName = cleanName.replace('[]', '');
                        filterValue[cleanName] = fieldValue;
                    }
                });
            }
            
            if (Object.keys(filterValue).length > 0) {
                currentFilters[filterType] = filterValue;
            }
        });
        
        console.log('Applying tasks filters:', currentFilters);
        
        saveFilters();
        reloadTableWithFilters();
        closeFilterPanel();
        
        var filterCount = Object.keys(currentFilters).length - 1;
        if (filterCount > 0) {
            alert_float('success', filterCount + ' filter(s) applied');
        }
    }

    /**
     * Collect date filter values
     */
    function collectDateFilterValues($panel, filterType) {
        var filterValue = {};
        var operator = $panel.find('[name="' + filterType + '_operator"]').val();
        
        if (!operator) {
            return filterValue;
        }
        
        filterValue.operator = operator;
        
        var presetOperators = [
            'today', 'yesterday', 'tomorrow', 'till_yesterday', 
            'this_week', 'last_week', 'next_week',
            'this_month', 'last_month', 'next_month',
            'last_7_days', 'next_30_days', 'unscheduled'
        ];
        
        if (presetOperators.indexOf(operator) !== -1) {
            return filterValue;
        }
        
        var singleDateOperators = ['is', 'less_than', 'greater_than', 'less_than_or_equal', 'greater_than_or_equal'];
        
        if (singleDateOperators.indexOf(operator) !== -1) {
            var dateValue = $panel.find('[name="' + filterType + '_value"]').val();
            if (dateValue && dateValue.length > 0) {
                filterValue.value = dateValue;
            } else {
                return {};
            }
        }
        
        if (operator === 'between') {
            var fromDate = $panel.find('[name="' + filterType + '_from"]').val();
            var toDate = $panel.find('[name="' + filterType + '_to"]').val();
            
            if (fromDate && fromDate.length > 0 && toDate && toDate.length > 0) {
                filterValue.from = fromDate;
                filterValue.to = toDate;
            } else {
                return {};
            }
        }
        
        return filterValue;
    }

    /**
     * Collect assigned filter values
     */
    function collectAssignedFilterValues($panel) {
        var filterValue = {};
        
        var operator = $panel.find('[name="assigned_operator"]').val();
        if (!operator) {
            return filterValue;
        }
        
        filterValue.operator = operator;
        
        var selectedStaff = $panel.find('[name="assigned_value[]"]').val();
        if (selectedStaff && selectedStaff.length > 0) {
            filterValue.value = selectedStaff;
        } else {
            return {};
        }
        
        return filterValue;
    }

    /**
     * Collect created by filter values
     */
    function collectCreatedByFilterValues($panel) {
        var filterValue = {};
        
        var operator = $panel.find('[name="created_by_operator"]').val();
        if (!operator) {
            return filterValue;
        }
        
        filterValue.operator = operator;
        
        var selectedStaff = $panel.find('[name="created_by_value[]"]').val();
        if (selectedStaff && selectedStaff.length > 0) {
            filterValue.value = selectedStaff;
        } else {
            return {};
        }
        
        return filterValue;
    }

    /**
     * Reload DataTable with filters
     */
    function reloadTableWithFilters() {
        if (typeof $.fn.DataTable !== 'undefined' && $('.table-tasks').length) {
            var table = $('.table-tasks').DataTable();
            
            table.settings()[0].ajax.data = function(d) {
                d.tasks_filters = JSON.stringify(currentFilters);
                return d;
            };
            
            table.ajax.reload();
        }
    }

    /**
     * Save filters to localStorage
     */
    function saveFilters() {
        try {
            localStorage.setItem('tasks_filters', JSON.stringify(currentFilters));
        } catch (e) {
            console.error('Could not save filters:', e);
        }
    }

    /**
     * Load saved filters
     */
    function loadSavedFilters() {
        try {
            var saved = localStorage.getItem('tasks_filters');
            if (saved) {
                currentFilters = JSON.parse(saved);
                populateFilterUI();
            }
        } catch (e) {
            console.error('Could not load filters:', e);
        }
    }

    /**
     * Populate filter UI with saved values
     */
    function populateFilterUI() {
        if (!currentFilters || Object.keys(currentFilters).length === 0) {
            return;
        }
        
        if (currentFilters.match) {
            $('input[name="tasks_filter_match"][value="' + currentFilters.match + '"]').prop('checked', true);
        }
        
        $.each(currentFilters, function(filterType, filterValue) {
            if (filterType === 'match') return;
            
            var $panel = $filterPanel.find('.filter-accordion-item[data-filter="' + filterType + '"]');
            
            $.each(filterValue, function(fieldName, value) {
                var fullFieldName = filterType + '_' + fieldName;
                var $field = $panel.find('[name="' + fullFieldName + '"]');
                
                if ($field.length) {
                    $field.val(value);
                    
                    if ($field.hasClass('selectpicker')) {
                        $field.selectpicker('refresh');
                    }
                    
                    markFilterAsActive($field);
                }
            });

            if (Object.keys(filterValue).length > 0) {
                toggleAccordion($panel, true);
            }
        });
    }

    /**
     * Initialize datepickers
     */
    function initializeDatepickers() {
        $filterPanel.find('.datepicker').datepicker({
            autoclose: true,
            format: app.options.date_format || 'yyyy-mm-dd'
        });
    }

    /**
     * Initialize selectpickers
     */
    function initializeSelectPickers() {
        if (typeof $.fn.selectpicker !== 'undefined') {
            $filterPanel.find('.selectpicker').selectpicker();
        }
    }

    /**
     * Get current filters
     */
    function getCurrentFilters() {
        return currentFilters;
    }

    /**
     * Public API
     */
    return {
        init: init,
        open: openFilterPanel,
        close: closeFilterPanel,
        reset: resetFilters,
        getCurrentFilters: getCurrentFilters
    };

})();

// Initialize on document ready
$(document).ready(function() {
    if ($('#tasksFilterPanel').length) {
        TasksFilter.init();
    }
});

