/**
 * Timelog Filter Panel - Zoho Style
 * Handles all filter panel interactions for the Timelog module
 * Based on project-timelog-filter.js but adapted for timelog module
 */

(function() {
    'use strict';
    
    // Wait for jQuery to be available
    function initWhenReady() {
        if (typeof jQuery === 'undefined') {
            setTimeout(initWhenReady, 50);
            return;
        }
    }

var TimelogFilter = (function() {
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
        // Prevent double initialization
        if ($('#timelogFilterPanel').data('initialized')) {
            console.log('TimelogFilter: Already initialized, skipping');
            return;
        }
        
        $filterPanel = $('#timelogFilterPanel');
        
        if (!$filterPanel.length) {
            console.warn('TimelogFilter: Filter panel not found in DOM');
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
        
        // Mark as initialized
        $filterPanel.data('initialized', true);
        
        console.log('TimelogFilter: Initialized successfully');
    }

    /**
     * Bind all event handlers
     */
    function bindEvents() {
        // Close filter panel
        $(document).on('click', '#timelogFilterPanel .filter-close, #timelogFilterPanel .filter-panel-overlay', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('TimelogFilter: Close button clicked');
            closeFilterPanel();
        });

        // Toggle accordion items
        $(document).on('click', '#timelogFilterPanel .filter-accordion-header', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('TimelogFilter: Accordion header clicked');
            var $item = $(this).closest('.filter-accordion-item');
            toggleAccordion($item);
        });

        // Filter search
        $(document).on('input', '#timelogFilterPanel .filter-search-input', function() {
            filterSearchItems($(this).val());
        });

        // Reset filters
        $(document).on('click', '#timelogFilterPanel .timelog-filter-reset', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('TimelogFilter: Reset button clicked');
            resetFilters();
        });

        // Apply filters (Find button)
        $(document).on('click', '#timelogFilterPanel .btn-timelog-filter-find', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('TimelogFilter: Find button clicked');
            applyFilters();
        });

        // Cancel button
        $(document).on('click', '#timelogFilterPanel .btn-timelog-filter-cancel', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('TimelogFilter: Cancel button clicked');
            closeFilterPanel();
        });

        // Track value changes within accordion bodies
        $(document).on('change', '#timelogFilterPanel .filter-accordion-item input, #timelogFilterPanel .filter-accordion-item select', function() {
            markFilterAsActive($(this));
        });

        // Start Date operator change handler
        $(document).on('change', '#timelog_start_date_operator_select', function() {
            handleStartDateOperatorChange($(this).val());
        });
        
        // Debug: Log when events are bound
        console.log('TimelogFilter: Events bound successfully');
    }

    /**
     * Open filter panel
     */
    function openFilterPanel() {
        // Re-initialize if panel not found
        if (!$filterPanel || !$filterPanel.length) {
            init();
        }
        
        if ($filterPanel && $filterPanel.length) {
            $filterPanel.addClass('active');
            $('body').addClass('filter-panel-open');
            console.log('TimelogFilter: Panel opened');
        } else {
            console.error('TimelogFilter: Cannot open panel - not found');
        }
    }

    /**
     * Close filter panel
     */
    function closeFilterPanel() {
        if ($filterPanel && $filterPanel.length) {
            $filterPanel.removeClass('active');
            $('body').removeClass('filter-panel-open');
            console.log('TimelogFilter: Panel closed');
        }
    }

    /**
     * Toggle accordion item
     */
    function toggleAccordion($item) {
        var $header = $item.find('.filter-accordion-header');
        var $body = $item.find('.filter-accordion-body');
        var isExpanded = $header.attr('aria-expanded') === 'true';

        if (isExpanded) {
            $header.attr('aria-expanded', 'false');
            $body.attr('aria-hidden', 'true').stop(true, true).slideUp(300);
            $item.removeClass('is-open');
        } else {
            $header.attr('aria-expanded', 'true');
            $body.attr('aria-hidden', 'false').stop(true, true).slideDown(300);
            $item.addClass('is-open');
        }
    }

    /**
     * Initialize accordion state
     */
    function initializeAccordionState() {
        if ($accordionItems && $accordionItems.length) {
            $accordionItems.each(function() {
                var $item = $(this);
                var $header = $item.find('.filter-accordion-header');
                var $body = $item.find('.filter-accordion-body');
                
                $header.attr('aria-expanded', 'false');
                $body.attr('aria-hidden', 'true').hide();
            });
        }
    }

    /**
     * Initialize datepickers
     */
    function initializeDatepickers() {
        if (typeof init_datepicker !== 'undefined') {
            $('#timelogFilterPanel .datepicker').each(function() {
                init_datepicker($(this));
            });
        } else if (typeof $.fn.datepicker !== 'undefined') {
            // Fallback to jQuery datepicker if init_datepicker not available
            $('#timelogFilterPanel .datepicker').datepicker({
                autoclose: true,
                format: 'dd/mm/yyyy'
            });
        }
    }

    /**
     * Initialize selectpickers
     */
    function initializeSelectPickers() {
        if (typeof $.fn.selectpicker !== 'undefined') {
            $('#timelogFilterPanel .selectpicker').selectpicker();
        }
    }

    /**
     * Handle start date operator change
     */
    function handleStartDateOperatorChange(operator) {
        $('#timelog_start_date_single_picker, #timelog_start_date_range_picker, #timelog_start_date_range_picker_end').hide();
        
        if (operator === 'between') {
            $('#timelog_start_date_range_picker, #timelog_start_date_range_picker_end').show();
        } else if (['is', 'less_than', 'greater_than', 'less_than_or_equal', 'greater_than_or_equal'].indexOf(operator) !== -1) {
            $('#timelog_start_date_single_picker').show();
        }
        // Preset dates don't need date pickers
    }

    /**
     * Filter search functionality
     */
    function filterSearchItems(searchTerm) {
        searchTerm = searchTerm.toLowerCase();
        
        $('#timelogFilterPanel .filter-accordion-item').each(function() {
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
        console.log('TimelogFilter: Resetting filters...');
        
        // Clear all inputs
        $('#timelogFilterPanel .filter-accordion-item input[type="text"]').val('');
        $('#timelogFilterPanel .filter-accordion-item select').each(function() {
            $(this).val('');
            if ($(this).hasClass('selectpicker') && typeof $.fn.selectpicker !== 'undefined') {
                $(this).selectpicker('refresh');
            }
        });
        
        // Hide date input groups
        $('#timelogFilterPanel .start-date-input-group').hide();

        // Remove active indicators and collapse accordions
        $accordionItems = $('#timelogFilterPanel .filter-accordion-item');
        $accordionItems.removeClass('has-value is-open');
        $accordionItems.find('.filter-accordion-header').attr('aria-expanded', 'false');
        $accordionItems.find('.filter-accordion-body').attr('aria-hidden', 'true').stop(true, true).slideUp(0);
        
        // Reset match condition to "any"
        $('#timelogFilterPanel input[name="timelog_filter_match"][value="any"]').prop('checked', true);
        
        // Clear stored filter data
        currentFilters = {};
        localStorage.removeItem('timelog_filters');
        
        // Reload timelogs without filters
        if (typeof TimelogModule !== 'undefined' && TimelogModule.loadTimelogs) {
            TimelogModule.loadTimelogs();
        } else {
            console.error('TimelogFilter: TimelogModule.loadTimelogs not available');
        }
        
        if (typeof alert_float !== 'undefined') {
            alert_float('success', typeof _l !== 'undefined' ? _l('filters_reset') : 'Filters reset successfully');
        }
    }

    /**
     * Collect filter values from each accordion panel
     */
    function collectFilterValues() {
        var filters = {};
        
        // Get match condition
        var matchCondition = $('#timelogFilterPanel input[name="timelog_filter_match"]:checked').val();
        filters.match = matchCondition || 'any';
        
        // Collect filter values from each accordion
        $('#timelogFilterPanel .filter-accordion-item').each(function() {
            var $panel = $(this);
            var filterType = $panel.data('filter');
            var filterValue = {};
            
            // Special handling for different filter types
            if (filterType === 'project') {
                filterValue = collectProjectFilterValues($panel);
            } else if (filterType === 'log_user') {
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
                filters[filterType] = filterValue;
            }
        });
        
        return filters;
    }
    
    /**
     * Collect Project filter values
     */
    function collectProjectFilterValues($panel) {
        var filterValue = {};
        var operator = $panel.find('[name="project_operator"]').val();
        if (!operator) {
            return filterValue;
        }
        filterValue.operator = operator;
        var selectedValues = $panel.find('[name="project_value[]"]').val();
        if (selectedValues && selectedValues.length > 0) {
            filterValue.value = selectedValues;
        } else {
            return {}; // Return empty if no projects selected
        }
        return filterValue;
    }
    
    /**
     * Collect Log User filter values
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
     * Collect Work Item filter values
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
     * Collect Start Date filter values
     */
    function collectStartDateFilterValues($panel) {
        var filterValue = {};
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
     * Collect Billing Type filter values
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
     * Collect Approval Status filter values
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
     * Collect Created By filter values
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
     * Apply filters
     */
    function applyFilters() {
        console.log('TimelogFilter: Applying filters...');
        currentFilters = collectFilterValues();
        
        console.log('TimelogFilter: Collected filters:', currentFilters);
        
        // Save filters to localStorage
        localStorage.setItem('timelog_filters', JSON.stringify(currentFilters));
        
        // Close filter panel
        closeFilterPanel();
        
        // Reload timelogs with filters
        if (typeof TimelogModule !== 'undefined' && TimelogModule.loadTimelogs) {
            TimelogModule.loadTimelogs();
        } else {
            console.error('TimelogFilter: TimelogModule.loadTimelogs not available');
        }
    }

    /**
     * Get current filters
     */
    function getFilters() {
        return currentFilters;
    }

    /**
     * Load saved filters
     */
    function loadSavedFilters() {
        var savedFilters = localStorage.getItem('timelog_filters');
        if (savedFilters) {
            try {
                currentFilters = JSON.parse(savedFilters);
                // Apply saved filters to form (optional - can be implemented if needed)
            } catch (e) {
                console.error('Error loading saved filters:', e);
            }
        }
    }

    /**
     * Public API
     */
    return {
        init: init,
        openFilterPanel: openFilterPanel,
        closeFilterPanel: closeFilterPanel,
        applyFilters: applyFilters,
        resetFilters: resetFilters,
        getFilters: getFilters
    };
})();

// Make TimelogFilter globally available
window.TimelogFilter = TimelogFilter;

// Initialize when DOM is ready (but don't auto-init, let TimelogModule handle it)
// The init will be called from TimelogModule.init() to ensure proper order
if (typeof jQuery !== 'undefined') {
    var $ = jQuery;
    // Wait a bit to ensure DOM is ready, but don't auto-init
    $(document).ready(function() {
        // Only init if panel exists and hasn't been initialized yet
        if ($('#timelogFilterPanel').length && typeof TimelogFilter !== 'undefined') {
            // Check if already initialized by checking if events are bound
            setTimeout(function() {
                if (!$('#timelogFilterPanel').data('initialized')) {
                    TimelogFilter.init();
                    $('#timelogFilterPanel').data('initialized', true);
                }
            }, 100);
        }
    });
} else {
    initWhenReady();
}

})();

