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
        applyDefaultFilterOnLoad();
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

        // Open the Save Filter modal (footer button)
        $(document).on('click', '.btn-filter-save', function(e) {
            e.preventDefault();
            openSaveFilterModal();
        });

        // Submit the Save/Edit Filter modal
        $(document).on('click', '#btnSubmitSaveProjectFilter', function(e) {
            e.preventDefault();
            submitSaveFilter();
        });

        // Apply a saved filter
        $(document).on('click', '.saved-filter-apply', function(e) {
            e.preventDefault();
            applySavedFilterItem($(this).closest('.saved-filter-item'));
        });

        // Toggle a saved filter as default (keep dropdown open)
        $(document).on('click', '.saved-filter-default', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleDefaultFilter($(this).closest('.saved-filter-item'));
        });

        // Edit a saved filter (open modal in edit mode)
        $(document).on('click', '.saved-filter-edit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openSaveFilterModal($(this).closest('.saved-filter-item'));
        });

        // Delete a saved filter
        $(document).on('click', '.saved-filter-delete', function(e) {
            e.preventDefault();
            e.stopPropagation();
            deleteSavedFilter($(this).closest('.saved-filter-item'));
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
        // Clear all inputs (multi-select pickers need deselectAll, not only .val(''))
        $('.filter-accordion-item input[type="text"]').val('');
        $('.filter-accordion-item select').each(function() {
            var $select = $(this);
            if ($select.prop('multiple') && typeof $select.selectpicker === 'function') {
                $select.selectpicker('deselectAll');
            } else {
                $select.val('');
            }
            if ($select.hasClass('selectpicker')) {
                $select.selectpicker('refresh');
            }
        });

        // Remove active indicators and collapse accordions
        $accordionItems.removeClass('has-value is-open');
        $accordionItems.find('.filter-accordion-header').attr('aria-expanded', 'false');
        $accordionItems.find('.filter-accordion-body').attr('aria-hidden', 'true').stop(true, true).slideUp(0);
        
        // Reset match condition to "all" (multiple filters combine with AND by default)
        $('input[name="filter_match"][value="all"]').prop('checked', true);
        
        // Clear stored filter data (memory + localStorage so refresh does not re-apply)
        currentFilters = {};
        clearSavedFilters();
        
        // Reload table without filters
        reloadTableWithFilters();
        
        alert_float('success', 'Filters reset successfully');
    }

    /**
     * Remove persisted filter state.
     */
    function clearSavedFilters() {
        try {
            localStorage.removeItem('projects_filters');
        } catch (e) {
            console.error('Could not clear filters:', e);
        }
    }

    /**
     * Collect the current filter selections from the panel into a payload
     * object (the same shape that is sent to the server and stored as a
     * saved filter "builder").
     */
    function buildFiltersPayload() {
        var payload = {};

        // Get match condition
        payload.match = $('input[name="filter_match"]:checked').val() || 'all';

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
            } else if (filterType === 'status') {
                // Status filter with is/is_not operator
                filterValue = collectStatusFilterValues($panel);
            } else if (filterType === 'manager') {
                // Project Manager filter with is/is_not operator
                filterValue = collectManagerFilterValues($panel);
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
                payload[filterType] = filterValue;
            }
        });

        return payload;
    }

    /**
     * Count active filters in a payload (everything except the match key).
     */
    function countActiveFilters(payload) {
        return Object.keys(payload || {}).filter(function(k) {
            return k !== 'match';
        }).length;
    }

    /**
     * Collect and apply filters
     */
    function applyFilters() {
        currentFilters = buildFiltersPayload();

        console.log('Applying filters:', currentFilters);

        var activeFilterCount = Object.keys(currentFilters).filter(function(k) {
            return k !== 'match';
        }).length;

        // Persist only when at least one filter is set
        if (activeFilterCount > 0) {
            saveFilters();
        } else {
            clearSavedFilters();
        }

        // Reload DataTable with filters
        reloadTableWithFilters();
        
        // Close filter panel
        closeFilterPanel();

        // Show success message
        if (activeFilterCount > 0) {
            alert_float('success', activeFilterCount + ' filter(s) applied');
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
     * Collect Status filter values (operator + selected statuses).
     * Only returns a filter when at least one status is selected.
     */
    function collectStatusFilterValues($panel) {
        var filterValue = {};
        filterValue.operator = $panel.find('[name="status_operator"]').val() || 'is';

        var selected = $panel.find('[name="status_value[]"]').val();
        if (selected && selected.length > 0) {
            filterValue.value = selected;
        } else {
            return {}; // No statuses selected
        }

        return filterValue;
    }

    /**
     * Collect Project Manager filter values (operator + selected managers).
     * Only returns a filter when at least one manager is selected.
     */
    function collectManagerFilterValues($panel) {
        var filterValue = {};
        filterValue.operator = $panel.find('[name="manager_operator"]').val() || 'is';

        var selected = $panel.find('[name="manager_value[]"]').val();
        if (selected && selected.length > 0) {
            filterValue.value = selected;
        } else {
            return {}; // No managers selected
        }

        return filterValue;
    }

    /**
     * Reload DataTable with current filters
     */
    function reloadTableWithFilters() {
        if (typeof $.fn.DataTable !== 'undefined' && $('.table-projects').length) {
            $('.table-projects').DataTable().ajax.reload();
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
     * On a fresh page load we intentionally do NOT restore previously applied
     * filters — refreshing the projects listing should always start unfiltered.
     * Any persisted filters from a previous session are cleared here.
     */
    function loadSavedFilters() {
        currentFilters = {};
        clearSavedFilters();
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
                var $field = $panel.find('[name="' + fullFieldName + '"], [name="' + fullFieldName + '[]"]');

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

    /* ------------------------------------------------------------------ *
     *  Saved filters (create / apply / edit / delete / default)
     * ------------------------------------------------------------------ */

    // Payload captured when the Save modal is opened, used as the fallback
    // "builder" when editing a filter without overwriting its rules.
    var pendingBuilder = {};

    /**
     * Append the CSRF token to an AJAX payload (POST requests require it).
     */
    function withCsrf(data) {
        data = data || {};
        if (typeof csrfData !== 'undefined' && csrfData.token_name) {
            data[csrfData.token_name] = csrfData.hash;
        }
        return data;
    }

    function escapeHtml(str) {
        return $('<div>').text(str == null ? '' : str).html();
    }

    /**
     * Clear every input in the panel without reloading the table.
     */
    function resetFilterInputs() {
        $('.filter-accordion-item input[type="text"]').val('');
        $('.filter-accordion-item select').each(function() {
            var $select = $(this);
            if ($select.prop('multiple') && typeof $select.selectpicker === 'function') {
                $select.selectpicker('deselectAll');
            } else {
                $select.val('');
            }
            if ($select.hasClass('selectpicker')) {
                $select.selectpicker('refresh');
            }
        });
        $('.owner-users-group, .start-date-input-group, .due-date-input-group').hide();
        $accordionItems.removeClass('has-value');
    }

    /**
     * Populate the panel UI from a saved builder payload (inverse of
     * buildFiltersPayload). Operators are set first and their change handlers
     * fired so dependent inputs (owner/date groups) become visible.
     */
    function loadBuilderIntoUI(builder) {
        resetFilterInputs();

        if (!builder || typeof builder !== 'object') {
            return;
        }

        var match = builder.match || 'all';
        $('input[name="filter_match"][value="' + match + '"]').prop('checked', true);

        $.each(builder, function(filterType, filterValue) {
            if (filterType === 'match' || !filterValue || typeof filterValue !== 'object') {
                return;
            }

            var $panel = $('.filter-accordion-item[data-filter="' + filterType + '"]');
            if (!$panel.length) {
                return;
            }

            // Set the operator first and trigger its handler so conditional
            // groups are revealed before we populate their values.
            if (typeof filterValue.operator !== 'undefined') {
                var $op = $panel.find('[name="' + filterType + '_operator"]');
                if ($op.length) {
                    $op.val(filterValue.operator);
                    if ($op.hasClass('selectpicker')) {
                        $op.selectpicker('refresh');
                    }
                    $op.trigger('change');
                }
            }

            $.each(filterValue, function(key, value) {
                if (key === 'operator') {
                    return;
                }
                var base = filterType + '_' + key;
                var $field = $panel.find('[name="' + base + '"], [name="' + base + '[]"]');
                if ($field.length) {
                    $field.val(value);
                    if ($field.hasClass('selectpicker')) {
                        $field.selectpicker('refresh');
                    }
                    markFilterAsActive($field);
                }
            });

            toggleAccordion($panel, true);
        });
    }

    /**
     * Make a builder the active filter set. When reload is true the table is
     * refreshed immediately; on initial page load it is left to the table's
     * own bootstrap (the DataTable is not yet initialised).
     */
    function setActiveBuilder(builder, reload) {
        currentFilters = $.extend(true, {}, builder || {});
        loadBuilderIntoUI(builder);
        if (reload) {
            reloadTableWithFilters();
        }
    }

    /**
     * Open the Save Filter modal. Pass a saved-filter <li> to edit it,
     * or nothing to save the currently selected filters as a new one.
     */
    function openSaveFilterModal($item) {
        var isEdit = $item && $item.length;

        if (isEdit) {
            $('#save_filter_id').val($item.data('id'));
            $('#save_filter_name').val($item.data('name'));
            $('#save_filter_is_shared').prop('checked', String($item.data('shared')) === '1');
            $('#save_filter_is_default').prop('checked', String($item.data('default')) === '1');
            // Editing: rules are kept unless the user opts to overwrite them.
            $('#save_filter_update_rules').prop('checked', false);
            $('.save-filter-update-rules-wrapper').removeClass('hide');
            pendingBuilder = $item.data('builder') || {};
        } else {
            pendingBuilder = buildFiltersPayload();
            if (countActiveFilters(pendingBuilder) === 0) {
                alert_float('warning', 'Please select at least one filter before saving.');
                return;
            }
            $('#save_filter_id').val('');
            $('#save_filter_name').val('');
            $('#save_filter_is_shared').prop('checked', false);
            $('#save_filter_is_default').prop('checked', false);
            $('.save-filter-update-rules-wrapper').addClass('hide');
        }

        // Close the saved-filters dropdown and the slide-in filter panel
        // (z-index 9999) so the modal isn't layered behind them. The current
        // selections are already captured in pendingBuilder.
        $('#projectsFilterControls').removeClass('open');
        closeFilterPanel();

        $('#saveProjectFilterModal').modal('show');
    }

    /**
     * Persist the Save/Edit modal (create or update on the server).
     */
    function submitSaveFilter() {
        var id = $('#save_filter_id').val();
        var name = $.trim($('#save_filter_name').val());

        if (!name) {
            alert_float('warning', 'Please enter a filter name.');
            return;
        }

        var isEdit = !!id;
        var rules;

        if (isEdit) {
            // Only re-capture rules when the user asked to overwrite them.
            rules = $('#save_filter_update_rules').is(':checked')
                ? buildFiltersPayload()
                : pendingBuilder;
        } else {
            rules = pendingBuilder;
        }

        var data = withCsrf({
            name: name,
            rules: JSON.stringify(rules),
            is_shared: $('#save_filter_is_shared').is(':checked') ? 1 : 0,
            is_default: $('#save_filter_is_default').is(':checked') ? 1 : 0
        });

        var url = admin_url + 'projects/' + (isEdit ? 'update_filter/' + id : 'save_filter');

        var $btn = $('#btnSubmitSaveProjectFilter').prop('disabled', true);

        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: data,
            success: function(res) {
                $btn.prop('disabled', false);
                if (res && res.success && res.filter) {
                    upsertMenuItem(res.filter);
                    $('#saveProjectFilterModal').modal('hide');
                    alert_float('success', isEdit ? 'Filter updated' : 'Filter saved');
                } else {
                    alert_float('danger', (res && res.message) ? res.message : 'Could not save filter');
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                alert_float('danger', 'Could not save filter');
            }
        });
    }

    /**
     * Apply a saved filter from its <li> element.
     */
    function applySavedFilterItem($item) {
        if (!$item || !$item.length) {
            return;
        }
        var builder = $item.data('builder') || {};
        setActiveBuilder(builder, true);
        $('input[name="filter_match"][value="' + (builder.match || 'all') + '"]').prop('checked', true);
        alert_float('success', '"' + $item.data('name') + '" applied');
    }

    /**
     * Toggle a saved filter as the current user's default.
     */
    function toggleDefaultFilter($item) {
        var id = $item.data('id');
        $.ajax({
            url: admin_url + 'projects/toggle_default_filter/' + id,
            type: 'POST',
            dataType: 'json',
            data: withCsrf({}),
            success: function(res) {
                if (res && res.success) {
                    // Only one default at a time.
                    $('#savedProjectFiltersMenu .saved-filter-item')
                        .removeClass('is-default')
                        .attr('data-default', 0);

                    if (res.is_default) {
                        $item.addClass('is-default').attr('data-default', 1);
                        $item.data('default', 1);
                        alert_float('success', 'Marked as default');
                    } else {
                        $item.data('default', 0);
                        alert_float('success', 'Default removed');
                    }
                } else {
                    alert_float('danger', 'Could not update default');
                }
            },
            error: function() {
                alert_float('danger', 'Could not update default');
            }
        });
    }

    /**
     * Delete a saved filter (after confirmation).
     */
    function deleteSavedFilter($item) {
        if (!confirm('Are you sure you want to delete this filter?')) {
            return;
        }
        var id = $item.data('id');
        $.ajax({
            url: admin_url + 'projects/delete_filter/' + id,
            type: 'POST',
            dataType: 'json',
            data: withCsrf({}),
            success: function(res) {
                if (res && res.success) {
                    $item.remove();
                    toggleEmptyState();
                    alert_float('success', 'Filter deleted');
                } else {
                    alert_float('danger', (res && res.message) ? res.message : 'Could not delete filter');
                }
            },
            error: function() {
                alert_float('danger', 'Could not delete filter');
            }
        });
    }

    /**
     * Insert or update a saved-filter <li> in the dropdown from a server
     * filter object ({id, name, is_shared, is_default, staff_id, builder}).
     */
    function upsertMenuItem(filter) {
        var $menu = $('#savedProjectFiltersMenu');
        var $existing = $menu.find('.saved-filter-item[data-id="' + filter.id + '"]');
        var isDefault = String(filter.is_default) === '1';
        var isShared = String(filter.is_shared) === '1';

        var sharedIcon = isShared
            ? ' <i class="fa fa-users text-muted" aria-hidden="true"></i>'
            : '';

        var $li = $(
            '<li class="saved-filter-item">' +
                '<a href="#" class="saved-filter-apply">' +
                    '<i class="fa fa-star saved-filter-default-icon" aria-hidden="true"></i> ' +
                    '<span class="saved-filter-name"></span>' +
                '</a>' +
                '<span class="saved-filter-actions">' +
                    '<a href="#" class="saved-filter-default"><i class="fa fa-star-o"></i></a>' +
                    '<a href="#" class="saved-filter-edit"><i class="fa fa-pencil"></i></a>' +
                    '<a href="#" class="saved-filter-delete"><i class="fa fa-trash"></i></a>' +
                '</span>' +
            '</li>'
        );

        $li.attr('data-id', filter.id)
            .attr('data-name', filter.name)
            .attr('data-shared', isShared ? 1 : 0)
            .attr('data-default', isDefault ? 1 : 0)
            .attr('data-can-manage', 1)
            .attr('data-builder', JSON.stringify(filter.builder || {}));
        $li.data('builder', filter.builder || {});
        $li.toggleClass('is-default', isDefault);
        $li.find('.saved-filter-name').html(escapeHtml(filter.name) + sharedIcon);

        if (isDefault) {
            $menu.find('.saved-filter-item').removeClass('is-default').attr('data-default', 0);
        }

        if ($existing.length) {
            $existing.replaceWith($li);
        } else {
            $menu.append($li);
        }

        toggleEmptyState();
    }

    /**
     * Show/hide the "no saved filters" placeholder based on item count.
     */
    function toggleEmptyState() {
        var hasItems = $('#savedProjectFiltersMenu .saved-filter-item').length > 0;
        $('#savedProjectFiltersMenu .saved-filters-empty').toggleClass('hide', hasItems);
    }

    /**
     * On page load, apply the staff member's default saved filter (if any).
     * The table reload is handled by the listing bootstrap in manage.php.
     */
    function applyDefaultFilterOnLoad() {
        var $default = $('#savedProjectFiltersMenu .saved-filter-item.is-default').first();
        if ($default.length) {
            setActiveBuilder($default.data('builder') || {}, false);
        }
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


