/**
 * Projects Filter Panel - Zoho Style
 * Handles all filter panel interactions
 */

var ProjectsFilter = (function() {
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
        $filterPanel = $('#projectsFilterPanel');
        $filterPanelContent = $('.filter-panel-content');
        $filterOverlay = $('.filter-panel-overlay');
        $accordionItems = $('.filter-accordion-item');

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
        $(document).on('click', '#btnOpenProjectFilter', function(e) {
            e.preventDefault();
            openFilterPanel();
        });

        // Close filter panel
        $(document).on('click', '.filter-close, .filter-panel-overlay', function(e) {
            e.preventDefault();
            closeFilterPanel();
        });

        // Toggle accordion items
        $(document).on('click', '.filter-accordion-header', function(e) {
            e.preventDefault();
            var $item = $(this).closest('.filter-accordion-item');
            toggleAccordion($item);
        });

        // Filter search
        $(document).on('input', '.filter-search-input', function() {
            filterSearchItems($(this).val());
        });

        // Reset filters
        $(document).on('click', '.filter-reset', function(e) {
            e.preventDefault();
            resetFilters();
        });

        // Apply filters (Find button)
        $(document).on('click', '.btn-filter-find', function(e) {
            e.preventDefault();
            applyFilters();
        });

        // Cancel button
        $(document).on('click', '.btn-filter-cancel', function(e) {
            e.preventDefault();
            closeFilterPanel();
        });

        // Track value changes within accordion bodies
        $(document).on('change', '.filter-accordion-item input, .filter-accordion-item select', function() {
            markFilterAsActive($(this));
        });

        // Owner operator change handler
        $(document).on('change', '#owner_operator_select', function() {
            handleOwnerOperatorChange($(this).val());
        });

        // Start Date operator change handler
        $(document).on('change', '#start_date_operator_select', function() {
            handleStartDateOperatorChange($(this).val());
        });

        // Due Date operator change handler
        $(document).on('change', '#due_date_operator_select', function() {
            handleDueDateOperatorChange($(this).val());
        });
    }

    /**
     * Handle Start Date operator dropdown change
     * Shows/hides date input fields based on selected operator
     */
    function handleStartDateOperatorChange(operator) {
        // Hide all date input groups first
        $('.start-date-input-group').hide();
        
        // Clear all date inputs when operator changes
        $('[name="start_date_value"], [name="start_date_from"], [name="start_date_to"]').val('');
        
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
            $('#start_date_single_picker').show();
            return;
        }
        
        // Between operator needs two date inputs
        if (operator === 'between') {
            $('#start_date_range_picker').show();
            $('#start_date_range_picker_end').show();
            return;
        }
    }

    /**
     * Handle Due Date operator dropdown change
     * Shows/hides date input fields based on selected operator
     */
    function handleDueDateOperatorChange(operator) {
        // Hide all date input groups first
        $('.due-date-input-group').hide();
        
        // Clear all date inputs when operator changes
        $('[name="due_date_value"], [name="due_date_from"], [name="due_date_to"]').val('');
        
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
            $('#due_date_single_picker').show();
            return;
        }
        
        // Between operator needs two date inputs
        if (operator === 'between') {
            $('#due_date_range_picker').show();
            $('#due_date_range_picker_end').show();
            return;
        }
    }

    /**
     * Handle Owner operator dropdown change
     * Shows/hides appropriate user dropdown based on selected operator
     */
    function handleOwnerOperatorChange(operator) {
        // Hide all user dropdowns first
        $('.owner-users-group').hide();
        
        // Clear all owner selections when operator changes
        $('#owner_active_select, #owner_deactive_select, #owner_deleted_select').val('').selectpicker('refresh');
        
        // Show appropriate dropdown based on operator
        switch(operator) {
            case 'is':
                $('#owner_active_users_group').show();
                break;
            case 'deactive_user':
                $('#owner_deactive_users_group').show();
                break;
            case 'deleted_user':
                $('#owner_deleted_users_group').show();
                break;
        }
    }

    /**
     * Open the main filter panel
     */
    function openFilterPanel() {
        $filterPanel.addClass('active');
        $('body').css('overflow', 'hidden');
    }

    /**
     * Close the main filter panel
     */
    function closeFilterPanel() {
        $filterPanel.removeClass('active');
        $('body').css('overflow', '');
    }

    /**
     * Ensure accordions are collapsed by default with proper aria labels
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
        
        $('.filter-accordion-item').each(function() {
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
        var filterType = $accordion.data('filter');
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
        $('.filter-accordion-item input[type="text"]').val('');
        $('.filter-accordion-item select').val('').selectpicker('refresh');

        // Remove active indicators and collapse accordions
        $accordionItems.removeClass('has-value is-open');
        $accordionItems.find('.filter-accordion-header').attr('aria-expanded', 'false');
        $accordionItems.find('.filter-accordion-body').attr('aria-hidden', 'true').stop(true, true).slideUp(0);
        
        // Reset match condition to "any"
        $('input[name="filter_match"][value="any"]').prop('checked', true);
        
        // Clear stored filter data
        currentFilters = {};
        
        // Reload table without filters
        if (typeof $.fn.DataTable !== 'undefined' && $('.table-projects').length) {
            $('.table-projects').DataTable().ajax.reload();
        }
        
        alert_float('success', 'Filters reset successfully');
    }

    /**
     * Collect and apply filters
     */
    function applyFilters() {
        currentFilters = {};
        
        // Get match condition
        var matchCondition = $('input[name="filter_match"]:checked').val();
        currentFilters.match = matchCondition;
        
        // Collect filter values from each accordion
        $accordionItems.each(function() {
            var $panel = $(this);
            var filterType = $panel.data('filter');
            var filterValue = {};
            
            // Special handling for owner filter - only collect from visible dropdowns
            if (filterType === 'owner') {
                filterValue = collectOwnerFilterValues($panel);
            } else if (filterType === 'start_date') {
                // Special handling for start_date filter
                filterValue = collectStartDateFilterValues($panel);
            } else if (filterType === 'due_date') {
                // Special handling for due_date filter
                filterValue = collectDueDateFilterValues($panel);
            } else if (filterType === 'created_by') {
                // Special handling for created_by filter
                filterValue = collectCreatedByFilterValues($panel);
            } else {
                // Get all inputs/selects for this filter
                $panel.find('input, select').each(function() {
                    var $field = $(this);
                    var fieldName = $field.attr('name');
                    var fieldValue = $field.val();
                    
                    if (fieldName && fieldValue && fieldValue.length > 0) {
                        // Remove filter type prefix from field name
                        var cleanName = fieldName.replace(filterType + '_', '');
                        // Remove [] suffix for cleaner key names
                        cleanName = cleanName.replace('[]', '');
                        filterValue[cleanName] = fieldValue;
                    }
                });
            }
            
            // Only add if filter has meaningful values
            if (Object.keys(filterValue).length > 0) {
                currentFilters[filterType] = filterValue;
            }
        });
        
        console.log('Applying filters:', currentFilters);
        
        // Save filters to session/localStorage
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
     * Collect owner filter values based on selected operator
     * Only collects from the visible/relevant dropdown
     */
    function collectOwnerFilterValues($panel) {
        var filterValue = {};
        
        // Get the operator value
        var operator = $panel.find('[name="owner_operator"]').val();
        if (!operator) {
            return filterValue;
        }
        
        filterValue.operator = operator;
        
        // Get values from the appropriate dropdown based on operator
        var selectedValues = [];
        
        switch(operator) {
            case 'is':
                selectedValues = $panel.find('[name="owner_value[]"]').val();
                if (selectedValues && selectedValues.length > 0) {
                    filterValue.value = selectedValues;
                }
                break;
            case 'deactive_user':
                selectedValues = $panel.find('[name="owner_deactive_value[]"]').val();
                if (selectedValues && selectedValues.length > 0) {
                    filterValue.deactive_value = selectedValues;
                }
                break;
            case 'deleted_user':
                selectedValues = $panel.find('[name="owner_deleted_value[]"]').val();
                if (selectedValues && selectedValues.length > 0) {
                    filterValue.deleted_value = selectedValues;
                }
                break;
        }
        
        // Only return if we have actual user selections (not just operator)
        if (Object.keys(filterValue).length <= 1) {
            return {}; // Return empty if only operator is set (no users selected)
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
     * Collect due date filter values based on selected operator
     * Handles preset operators (auto-calculate dates) and advanced operators (date inputs)
     */
    function collectDueDateFilterValues($panel) {
        var filterValue = {};
        
        // Get the operator value
        var operator = $panel.find('[name="due_date_operator"]').val();
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
            var dateValue = $panel.find('[name="due_date_value"]').val();
            if (dateValue && dateValue.length > 0) {
                filterValue.value = dateValue;
            } else {
                return {}; // Return empty if no date selected
            }
        }
        
        // Between operator needs two date values
        if (operator === 'between') {
            var fromDate = $panel.find('[name="due_date_from"]').val();
            var toDate = $panel.find('[name="due_date_to"]').val();
            
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
     * Collect created by filter values based on selected operator
     * Handles Is and Is Not operators
     */
    function collectCreatedByFilterValues($panel) {
        var filterValue = {};
        
        // Get the operator value
        var operator = $panel.find('[name="created_by_operator"]').val();
        if (!operator) {
            return filterValue;
        }
        
        filterValue.operator = operator;
        
        // Get selected staff values
        var selectedStaff = $panel.find('[name="created_by_value[]"]').val();
        
        if (selectedStaff && selectedStaff.length > 0) {
            filterValue.value = selectedStaff;
        } else {
            return {}; // Return empty if no staff selected
        }
        
        return filterValue;
    }

    /**
     * Reload DataTable with current filters
     */
    function reloadTableWithFilters() {
        if (typeof $.fn.DataTable !== 'undefined' && $('.table-projects').length) {
            var table = $('.table-projects').DataTable();
            
            // Add filter data to AJAX request as JSON string
            table.settings()[0].ajax.data = function(d) {
                // Send filters as JSON string for proper parsing on server
                d.filters = JSON.stringify(currentFilters);
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
            localStorage.setItem('projects_filters', JSON.stringify(currentFilters));
        } catch (e) {
            console.error('Could not save filters:', e);
        }
    }

    /**
     * Load saved filters from localStorage
     */
    function loadSavedFilters() {
        try {
            var saved = localStorage.getItem('projects_filters');
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
        
        // Set match condition
        if (currentFilters.match) {
            $('input[name="filter_match"][value="' + currentFilters.match + '"]').prop('checked', true);
        }
        
        // Populate each filter
        $.each(currentFilters, function(filterType, filterValue) {
            if (filterType === 'match') return;
            
            var $panel = $('.filter-accordion-item[data-filter="' + filterType + '"]');
            
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
     * Initialize all datepickers
     */
    function initializeDatepickers() {
        $('.datepicker').datepicker({
            autoclose: true,
            format: app.options.date_format || 'yyyy-mm-dd'
        });
    }

    /**
     * Initialize all selectpickers
     */
    function initializeSelectPickers() {
        if (typeof $.fn.selectpicker !== 'undefined') {
            $('.selectpicker').selectpicker();
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

// Initialize on document ready
$(document).ready(function() {
    if ($('#projectsFilterPanel').length) {
        ProjectsFilter.init();
    }
});


