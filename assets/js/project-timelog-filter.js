/**
 * Project Timelog Filter Panel - Zoho Style
 * Handles all filter panel interactions for the Project Details Timesheet tab
 */

(function() {
    // Wait for jQuery to be available
    function initWhenReady() {
        if (typeof jQuery === 'undefined') {
            setTimeout(initWhenReady, 50);
            return;
        }
        
        var $ = jQuery;

var ProjectTimelogFilter = (function() {
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
        $filterPanel = $('#projectTimelogFilterPanel');
        
        if (!$filterPanel.length) {
            return;
        }
        
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
        $(document).on('click', '#btnOpenProjectTimelogFilter', function(e) {
            e.preventDefault();
            openFilterPanel();
        });

        // Close filter panel
        $(document).on('click', '.project-timelog-filter-panel .filter-close, .project-timelog-filter-panel .filter-panel-overlay', function(e) {
            e.preventDefault();
            closeFilterPanel();
        });

        // Toggle accordion items
        $(document).on('click', '.project-timelog-filter-panel .filter-accordion-header', function(e) {
            e.preventDefault();
            var $item = $(this).closest('.filter-accordion-item');
            toggleAccordion($item);
        });

        // Filter search
        $(document).on('input', '.project-timelog-filter-panel .filter-search-input', function() {
            filterSearchItems($(this).val());
        });

        // Reset filters
        $(document).on('click', '.project-timelog-filter-reset', function(e) {
            e.preventDefault();
            resetFilters();
        });

        // Apply filters (Find button)
        $(document).on('click', '.btn-project-timelog-filter-find', function(e) {
            e.preventDefault();
            applyFilters();
        });

        // Cancel button
        $(document).on('click', '.btn-project-timelog-filter-cancel', function(e) {
            e.preventDefault();
            closeFilterPanel();
        });

        // Track value changes within accordion bodies
        $(document).on('change', '.project-timelog-filter-panel .filter-accordion-item input, .project-timelog-filter-panel .filter-accordion-item select', function() {
            markFilterAsActive($(this));
        });

        // Start Date operator change handler
        $(document).on('change', '#project_timelog_start_date_operator_select', function() {
            handleStartDateOperatorChange($(this).val());
        });
    }

    /**
     * Handle Start Date operator dropdown change
     * Shows/hides date input fields based on selected operator
     */
    function handleStartDateOperatorChange(operator) {
        $filterPanel = $('#projectTimelogFilterPanel');
        // Hide all date input groups first
        $filterPanel.find('.start-date-input-group').hide();
        
        // Clear all date inputs when operator changes
        $filterPanel.find('[name="start_date_value"], [name="start_date_from"], [name="start_date_to"]').val('');
        
        // Preset operators that don't require date input
        var presetOperators = [
            'today', 'yesterday', 'tomorrow', 'till_yesterday', 
            'this_week', 'last_week', 'next_week',
            'this_month', 'last_month', 'next_month',
            'last_7_days', 'next_30_days', 'unscheduled'
        ];
        
        // Advanced operators that require single date input
        var singleDateOperators = ['is', 'less_than', 'greater_than', 'less_than_or_equal', 'greater_than_or_equal'];
        
        // Check if it's a preset operator (no date input needed)
        if (presetOperators.indexOf(operator) !== -1) {
            // No date input needed for preset operators
            return;
        }
        
        // Check if single date input is needed
        if (singleDateOperators.indexOf(operator) !== -1) {
            $filterPanel.find('#project_timelog_start_date_single_picker').show();
            return;
        }
        
        // Between operator needs two date inputs
        if (operator === 'between') {
            $filterPanel.find('#project_timelog_start_date_range_picker').show();
            $filterPanel.find('#project_timelog_start_date_range_picker_end').show();
            return;
        }
    }

    /**
     * Open the main filter panel
     */
    function openFilterPanel() {
        $filterPanel = $('#projectTimelogFilterPanel');
        $filterPanel.addClass('active');
        $('body').css('overflow', 'hidden');
    }

    /**
     * Close the main filter panel
     */
    function closeFilterPanel() {
        $filterPanel = $('#projectTimelogFilterPanel');
        $filterPanel.removeClass('active');
        $('body').css('overflow', '');
    }

    /**
     * Ensure accordions are collapsed by default with proper aria labels
     */
    function initializeAccordionState() {
        $accordionItems = $filterPanel.find('.filter-accordion-item');
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
     * Toggle accordion open/close state
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
     * Mark filter item as active when it has a value
     */
    function markFilterAsActive($input) {
        var $accordion = $input.closest('.filter-accordion-item');
        var hasValue = false;
        
        // Check if any input/select in this filter has a value
        $accordion.find('input, select').each(function() {
            var val = $(this).val();
            if (val && val.length > 0 && val != '') {
                hasValue = true;
                return false; // break loop
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
        // Clear all inputs
        $filterPanel.find('.filter-accordion-item input[type="text"]').val('');
        $filterPanel.find('.filter-accordion-item select').each(function() {
            $(this).val('');
            if ($(this).hasClass('selectpicker') && typeof $.fn.selectpicker !== 'undefined') {
                $(this).selectpicker('refresh');
            }
        });
        
        // Hide date input groups
        $filterPanel.find('.start-date-input-group').hide();

        // Remove active indicators and collapse accordions
        $accordionItems = $filterPanel.find('.filter-accordion-item');
        $accordionItems.removeClass('has-value is-open');
        $accordionItems.find('.filter-accordion-header').attr('aria-expanded', 'false');
        $accordionItems.find('.filter-accordion-body').attr('aria-hidden', 'true').stop(true, true).slideUp(0);
        
        // Reset match condition to "any"
        $filterPanel.find('input[name="project_timelog_filter_match"][value="any"]').prop('checked', true);
        
        // Clear stored filter data
        currentFilters = {};
        localStorage.removeItem('project_timelog_filters');
        
        // Reload table without filters
        if (typeof $.fn.DataTable !== 'undefined' && $('.table-timesheets').length) {
            $('.table-timesheets').DataTable().ajax.reload();
        }
        
        alert_float('success', 'Filters reset successfully');
    }

    /**
     * Collect and apply filters
     */
    function applyFilters() {
        currentFilters = {};
        
        // Get match condition
        var matchCondition = $filterPanel.find('input[name="project_timelog_filter_match"]:checked').val();
        currentFilters.match = matchCondition;
        
        // Collect filter values from each accordion
        $filterPanel.find('.filter-accordion-item').each(function() {
            var $panel = $(this);
            var filterType = $panel.data('filter');
            var filterValue = {};
            
            // Special handling for different filter types
            if (filterType === 'log_user') {
                filterValue = collectLogUserFilterValues($panel);
            } else if (filterType === 'work_item') {
                filterValue = collectWorkItemFilterValues($panel);
            } else if (filterType === 'start_date') {
                filterValue = collectStartDateFilterValues($panel);
            } else if (filterType === 'billing_type') {
                filterValue = collectBillingTypeFilterValues($panel);
            } else if (filterType === 'approval_status') {
                filterValue = collectApprovalStatusFilterValues($panel);
            } else if (filterType === 'created_by') {
                filterValue = collectCreatedByFilterValues($panel);
            }
            
            // Only add if filter has meaningful values
            if (Object.keys(filterValue).length > 0) {
                currentFilters[filterType] = filterValue;
            }
        });
        
        console.log('Applying project timelog filters:', currentFilters);
        
        // Save filters to localStorage
        saveFilters();
        
        // Reload DataTable with filters
        reloadTableWithFilters();
        
        // Close filter panel
        closeFilterPanel();
        
        // Show success message
        var filterCount = Object.keys(currentFilters).length - 1; // -1 for match condition
        if (filterCount > 0) {
            alert_float('success', filterCount + ' filter(s) applied');
        }
    }

    /**
     * Collect log user filter values
     */
    function collectLogUserFilterValues($panel) {
        var filterValue = {};
        
        var operator = $panel.find('[name="log_user_operator"]').val();
        if (!operator) {
            return filterValue;
        }
        
        filterValue.operator = operator;
        
        var selectedValues = $panel.find('[name="log_user_value[]"]').val();
        if (selectedValues && selectedValues.length > 0) {
            filterValue.value = selectedValues;
        } else {
            return {}; // Return empty if no users selected
        }
        
        return filterValue;
    }

    /**
     * Collect work item filter values
     */
    function collectWorkItemFilterValues($panel) {
        var filterValue = {};
        
        var selectedValues = $panel.find('[name="work_item_value[]"]').val();
        if (selectedValues && selectedValues.length > 0) {
            filterValue.value = selectedValues;
        }
        
        return filterValue;
    }

    /**
     * Collect billing type filter values
     */
    function collectBillingTypeFilterValues($panel) {
        var filterValue = {};
        
        var operator = $panel.find('[name="billing_type_operator"]').val();
        if (!operator) {
            return filterValue;
        }
        
        filterValue.operator = operator;
        
        var selectedValue = $panel.find('[name="billing_type_value"]').val();
        if (selectedValue && selectedValue.length > 0) {
            filterValue.value = selectedValue;
        } else {
            return {}; // Return empty if no value selected
        }
        
        return filterValue;
    }

    /**
     * Collect approval status filter values
     */
    function collectApprovalStatusFilterValues($panel) {
        var filterValue = {};
        
        var operator = $panel.find('[name="approval_status_operator"]').val();
        if (!operator) {
            return filterValue;
        }
        
        filterValue.operator = operator;
        
        var selectedValues = $panel.find('[name="approval_status_value[]"]').val();
        if (selectedValues && selectedValues.length > 0) {
            filterValue.value = selectedValues;
        } else {
            return {}; // Return empty if no status selected
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
        
        var selectedValues = $panel.find('[name="created_by_value[]"]').val();
        if (selectedValues && selectedValues.length > 0) {
            filterValue.value = selectedValues;
        } else {
            return {}; // Return empty if no staff selected
        }
        
        return filterValue;
    }

    /**
     * Collect start date filter values based on selected operator
     * Handles preset operators (auto-calculate dates) and advanced operators (date inputs)
     */
    function collectStartDateFilterValues($panel) {
        var filterValue = {};
        
        // Get the operator value
        var operator = $panel.find('[name="start_date_operator"]').val();
        if (!operator) {
            return filterValue;
        }
        
        filterValue.operator = operator;
        
        // Preset operators that don't require date input
        var presetOperators = [
            'today', 'yesterday', 'tomorrow', 'till_yesterday', 
            'this_week', 'last_week', 'next_week',
            'this_month', 'last_month', 'next_month',
            'last_7_days', 'next_30_days', 'unscheduled'
        ];
        
        // If preset operator, return just the operator (backend will calculate dates)
        if (presetOperators.indexOf(operator) !== -1) {
            return filterValue;
        }
        
        // Advanced operators that require single date input
        var singleDateOperators = ['is', 'less_than', 'greater_than', 'less_than_or_equal', 'greater_than_or_equal'];
        
        if (singleDateOperators.indexOf(operator) !== -1) {
            var dateValue = $panel.find('[name="start_date_value"]').val();
            if (dateValue && dateValue.length > 0) {
                filterValue.value = dateValue;
            } else {
                return {}; // Return empty if no date selected
            }
        }
        
        // Between operator needs two date values
        if (operator === 'between') {
            var fromDate = $panel.find('[name="start_date_from"]').val();
            var toDate = $panel.find('[name="start_date_to"]').val();
            
            if (fromDate && fromDate.length > 0 && toDate && toDate.length > 0) {
                filterValue.from = fromDate;
                filterValue.to = toDate;
            } else {
                return {}; // Return empty if dates not complete
            }
        }
        
        return filterValue;
    }

    /**
     * Reload DataTable with current filters
     */
    function reloadTableWithFilters() {
        if (typeof $.fn.DataTable !== 'undefined' && $('.table-timesheets').length) {
            var table = $('.table-timesheets').DataTable();
            
            // Store original data function if exists
            var originalDataFn = table.settings()[0].ajax.data;
            
            // Add filter data to AJAX request
            table.settings()[0].ajax.data = function(d) {
                // Call original data function if exists
                if (typeof originalDataFn === 'function') {
                    originalDataFn(d);
                }
                // Add advanced filters as JSON string
                d.advanced_filters = JSON.stringify(currentFilters);
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
            localStorage.setItem('project_timelog_filters', JSON.stringify(currentFilters));
        } catch (e) {
            console.error('Could not save filters:', e);
        }
    }

    /**
     * Load saved filters from localStorage
     */
    function loadSavedFilters() {
        try {
            var saved = localStorage.getItem('project_timelog_filters');
            if (saved) {
                currentFilters = JSON.parse(saved);
                // Only populate UI, don't auto-apply filters on load
                setTimeout(function() {
                    populateFilterUI();
                }, 500);
            }
        } catch (e) {
            console.error('Could not load filters:', e);
            // Clear invalid filters
            localStorage.removeItem('project_timelog_filters');
            currentFilters = {};
        }
    }

    /**
     * Populate filter UI with saved values
     */
    function populateFilterUI() {
        if (!currentFilters || Object.keys(currentFilters).length === 0) {
            return;
        }
        
        // Set match condition
        if (currentFilters.match) {
            $filterPanel.find('input[name="project_timelog_filter_match"][value="' + currentFilters.match + '"]').prop('checked', true);
        }
        
        // Populate each filter
        $.each(currentFilters, function(filterType, filterValue) {
            if (filterType === 'match') return;
            
            var $panel = $filterPanel.find('.filter-accordion-item[data-filter="' + filterType + '"]');
            
            // Set operator if exists
            if (filterValue.operator) {
                var $operator = $panel.find('[name="' + filterType + '_operator"]');
                if ($operator.length) {
                    $operator.val(filterValue.operator);
                    if ($operator.hasClass('selectpicker') && typeof $.fn.selectpicker !== 'undefined') {
                        $operator.selectpicker('refresh');
                    }
                }
            }
            
            // Set values - special handling for start_date
            if (filterType === 'start_date') {
                // Handle start_date filter restoration
                if (filterValue.operator) {
                    var $operator = $panel.find('[name="start_date_operator"]');
                    if ($operator.length) {
                        $operator.val(filterValue.operator);
                        if ($operator.hasClass('selectpicker') && typeof $.fn.selectpicker !== 'undefined') {
                            $operator.selectpicker('refresh');
                        }
                        // Trigger operator change to show/hide date inputs
                        handleStartDateOperatorChange(filterValue.operator);
                    }
                }
                
                if (filterValue.value) {
                    var $valueField = $panel.find('[name="start_date_value"]');
                    if ($valueField.length) {
                        $valueField.val(filterValue.value);
                        markFilterAsActive($valueField);
                    }
                }
                
                if (filterValue.from) {
                    var $fromField = $panel.find('[name="start_date_from"]');
                    if ($fromField.length) {
                        $fromField.val(filterValue.from);
                        markFilterAsActive($fromField);
                    }
                }
                
                if (filterValue.to) {
                    var $toField = $panel.find('[name="start_date_to"]');
                    if ($toField.length) {
                        $toField.val(filterValue.to);
                        markFilterAsActive($toField);
                    }
                }
            } else if (filterValue.value) {
                var $valueField = $panel.find('[name="' + filterType + '_value[]"], [name="' + filterType + '_value"]');
                if ($valueField.length) {
                    $valueField.val(filterValue.value);
                    if ($valueField.hasClass('selectpicker') && typeof $.fn.selectpicker !== 'undefined') {
                        $valueField.selectpicker('refresh');
                    }
                    markFilterAsActive($valueField);
                }
            }

            if (Object.keys(filterValue).length > 0) {
                toggleAccordion($panel, true);
            }
        });
    }

    /**
     * Initialize all datepickers
     */
    function initializeDatepickers() {
        if ($filterPanel.length && typeof $.fn.datepicker !== 'undefined') {
            $filterPanel.find('.datepicker').datepicker({
                autoclose: true,
                format: (typeof app !== 'undefined' && app.options && app.options.date_format) ? app.options.date_format : 'yyyy-mm-dd'
            });
        }
    }

    /**
     * Initialize all selectpickers
     */
    function initializeSelectPickers() {
        if (typeof $.fn.selectpicker !== 'undefined' && $filterPanel.length) {
            $filterPanel.find('.selectpicker').selectpicker();
        }
    }

    /**
     * Get current filters (for external use)
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

// Make it globally available
window.ProjectTimelogFilter = ProjectTimelogFilter;

// Initialize if panel exists
if ($('#projectTimelogFilterPanel').length) {
    ProjectTimelogFilter.init();
}

    } // end initWhenReady
    
    initWhenReady();
})();

