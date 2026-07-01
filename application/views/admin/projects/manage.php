<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<link href="<?= base_url('assets/css/projects-filter.css'); ?>" rel="stylesheet" type="text/css" />
<style>
    /* Whole-row colouring driven by the per-row Priority dropdown.
       The Status / Priority pills (a.label) keep their own inline colours — they are
       excluded via :not(.label) so they stay readable on the coloured row. */

    /* HIGH → red row, white text */
    .table-projects tr.project-row-priority-high > td {
        background-color:rgba(220, 38, 38, 0.72) !important;
        color: #ffffff !important;
    }
    .table-projects tr.project-row-priority-high > td a:not(.label),
    .table-projects tr.project-row-priority-high > td small,
    .table-projects tr.project-row-priority-high > td .text-muted,
    .table-projects tr.project-row-priority-high > td .project-table-progress-wrap > span {
        color: #ffffff !important;
    }

    /* MEDIUM → orange row, black text */
    .table-projects tr.project-row-priority-medium > td {
        background-color:rgba(245, 159, 11, 0.72) !important;
        color: #000000 !important;
    }
    .table-projects tr.project-row-priority-medium > td a:not(.label),
    .table-projects tr.project-row-priority-medium > td small,
    .table-projects tr.project-row-priority-medium > td .text-muted,
    .table-projects tr.project-row-priority-medium > td .project-table-progress-wrap > span {
        color: #000000 !important;
    }

    /* LOW → white row, black text */
    .table-projects tr.project-row-priority-low > td {
        background-color: #ffffff !important;
        color: #000000 !important;
    }
    .table-projects tr.project-row-priority-low > td a:not(.label),
    .table-projects tr.project-row-priority-low > td small,
    .table-projects tr.project-row-priority-low > td .text-muted,
    .table-projects tr.project-row-priority-low > td .project-table-progress-wrap > span {
        color: #000000 !important;
    }
</style>
<div id="wrapper">
    <div class="content">
        <div id="vueApp">
            <div class="row">
                <div class="col-md-12">
                    <div class="tw-block md:tw-hidden">
                        <?php // $this->load->view('admin/projects/stats'); // status bar hidden ?>
                    </div>
                    <div class="_buttons">
                        <div class="md:tw-flex md:tw-items-center">
                            <?php if (staff_can('create', 'projects')) { ?>
                            <a href="<?= admin_url('projects/project-new'); ?>"
                                class="btn btn-primary pull-left display-block mright5">
                                <i class="fa-regular fa-plus tw-mr-1"></i>
                                <?= _l('new_project'); ?>
                            </a>
                            <?php } ?>
                            <a href="<?= admin_url('projects/gantt'); ?>"
                                data-toggle="tooltip"
                                data-title="<?= _l('project_gant'); ?>"
                                class="btn btn-default btn-with-tooltip sm:!tw-px-3">
                                <i class="fa fa-align-left" aria-hidden="true"></i>
                            </a>
                            <a href="<?= admin_url('projects/switch_kanban/' . ($switch_kanban ? 0 : 1)); ?>"
                                class="btn btn-default tw-ml-1 btn-with-tooltip sm:!tw-px-3"
                                data-toggle="tooltip"
                                data-placement="top"
                                data-title="<?= $switch_kanban ? _l('switch_to_list_view') : _l('leads_switch_to_kanban'); ?>">
                                <?php if ($switch_kanban) { ?>
                                <i class="fa-solid fa-table-list"></i>
                                <?php } else { ?>
                                <i class="fa-solid fa-grip-vertical"></i>
                                <?php } ?>
                            </a>
                            <div class="tw-hidden md:tw-block md:tw-ml-6 rtl:md:tw-mr-6 tw-min-w-0 tw-flex-1">
                                <?php // $this->load->view('admin/projects/stats'); // status bar hidden ?>
                            </div>
                            <div class="ltr:tw-ml-auto rtl:tw-mr-auto tw-flex tw-items-center tw-gap-2">
                                <!-- Zoho-Style Filter Button -->
                                <?php $this->load->view('admin/projects/filter_panel'); ?>
                                <!-- <app-filters
                                    id="<?= $table->id(); ?>"
                                    view="<?= $table->viewName(); ?>"
                                    :rules="extra.projectsRules || <?= app\services\utilities\Js::from($this->input->get('status') ? $table->findRule('status')->setValue([(int) $this->input->get('status')]) : []); ?>"
                                    :saved-filters="<?= $table->filtersJs(); ?>"
                                    :available-rules="<?= $table->rulesJs(); ?>">
                                </app-filters> -->
                            </div>
                        </div>
                        <div class="clearfix"></div>
                    </div>

                    <?php if ($switch_kanban) { ?>
                    <!-- Kanban View -->
                    <div class="kan-ban-tab tw-mt-6" id="kan-ban-tab" style="overflow:auto;">
                        <div class="row">
                            <div id="kanban-params"></div>
                            <div class="container-fluid">
                                <div id="kan-ban"></div>
                            </div>
                        </div>
                    </div>
                    <?php } else { ?>
                    <!-- Table View -->
                    <div class="panel_s tw-mt-2">
                        <div class="panel-body">
                            <div class="panel-table-full">
                                <?= form_hidden('custom_view'); ?>
                                <?php $this->load->view('admin/projects/table_html'); ?>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $this->load->view('admin/projects/copy_settings'); ?>
<?php init_tail(); ?>
<?php if (!$switch_kanban) { ?>
<script src="<?= base_url('assets/js/projects-filter.js'); ?>"></script>
<script>
    // Inline project status change from the list view (called from the status dropdown).
    // Global so the inline onclick on each menu item can reach it.
    function project_mark_as(status_id, project_id) {
        var postData = {
            project_id: project_id,
            status_id: status_id,
            notify_project_members_status_change: 0,
            mark_all_tasks_as_completed: 0
        };

        $('body').append('<div class="dt-loader"></div>');
        $.ajax({
            url: admin_url + 'projects/mark_as',
            type: 'POST',
            dataType: 'json',
            data: postData,
            success: function(res) {
                $('body').find('.dt-loader').remove();
                if (res && res.success) {
                    $('.table-projects').DataTable().ajax.reload(null, false);
                    // alert_float('success', res.message);
                } else {
                    alert_float('danger', (res && res.message) ? res.message : 'Error changing status');
                }
            },
            error: function() {
                $('body').find('.dt-loader').remove();
                alert_float('danger', 'Error changing status');
            }
        });
    }

    // Inline project priority change from the list view (called from the priority dropdown).
    // Global so the inline onclick on each menu item can reach it.
    function project_set_priority(priority, project_id) {
        var postData = {
            project_id: project_id,
            priority: priority
        };
        // CSRF token is required on POST requests (csrf_protection is enabled)
        if (typeof csrfData !== 'undefined') {
            postData[csrfData.token_name] = csrfData.hash;
        }

        $('body').append('<div class="dt-loader"></div>');
        $.ajax({
            url: admin_url + 'projects/save_listing_priority',
            type: 'POST',
            dataType: 'json',
            data: postData,
            success: function(res) {
                $('body').find('.dt-loader').remove();
                if (res && res.success) {
                    $('.table-projects').DataTable().ajax.reload(null, false);
                } else {
                    alert_float('danger', (res && res.message) ? res.message : 'Error changing priority');
                }
            },
            error: function() {
                $('body').find('.dt-loader').remove();
                alert_float('danger', 'Error changing priority');
            }
        });
    }

    $(function() {
        var table = initDataTable('.table-projects', admin_url + 'projects/table', undefined, undefined, {},
            <?= hooks()->apply_filters('projects_table_default_order', json_encode([0, 'asc'])); ?>
        );

        if (table && typeof ProjectsFilter !== 'undefined') {
            var settings = table.settings()[0];
            var originalAjaxData = settings.ajax.data;
            settings.ajax.data = function(d) {
                if (typeof originalAjaxData === 'function') {
                    originalAjaxData(d);
                }
                var cf = ProjectsFilter.getCurrentFilters() || {};
                var hasActiveFilters = Object.keys(cf).some(function(k) {
                    return k !== 'match';
                });
                if (hasActiveFilters) {
                    var payload = $.extend({ match: cf.match || 'all' }, cf);
                    d.filters = JSON.stringify(payload);
                } else {
                    delete d.filters;
                }
                return d;
            };
            var cf0 = ProjectsFilter.getCurrentFilters() || {};
            if (Object.keys(cf0).some(function(k) { return k !== 'match'; })) {
                table.ajax.reload(null, false);
            }
        }

        // Override language settings to use "projects" instead of "entries"
        if (table) {
            // Update language settings
            var settings = table.settings()[0];
            if (settings && settings.oLanguage) {
                var originalInfo = settings.oLanguage.sInfo;
                var originalInfoEmpty = settings.oLanguage.sInfoEmpty;
                var originalInfoFiltered = settings.oLanguage.sInfoFiltered;
                
                settings.oLanguage.sInfo = originalInfo.replace(/entries/g, 'projects');
                settings.oLanguage.sInfoEmpty = originalInfoEmpty.replace(/entries/g, 'projects');
                settings.oLanguage.sInfoFiltered = originalInfoFiltered.replace(/entries/g, 'projects');
            }
            
            // Update the info text in the DOM whenever table is drawn
            table.on('draw.dt', function() {
                var infoElement = $('.table-projects').closest('.dataTables_wrapper').find('.dataTables_info');
                if (infoElement.length) {
                    var currentText = infoElement.text();
                    if (currentText.indexOf('entries') !== -1) {
                        infoElement.text(currentText.replace(/entries/g, 'projects'));
                    }
                }
            });
        }

        init_ajax_search('customer', '#clientid_copy_project.ajax-search');

        // Enforce one-at-a-time visibility per notes cell: text when it has content,
        // textarea when empty. Runs after every DataTable draw so it never shows both.
        function enforceNoteCellState() {
            $('.table-projects .project-listing-notes').each(function() {
                var $cell = $(this);
                var $ta   = $cell.find('.project-note-input');
                if ($ta.is(':focus')) { return; } // don't disturb the cell being edited
                var $display = $cell.find('.project-note-display');
                if ($.trim($ta.val()) !== '') {
                    $ta[0].style.setProperty('display', 'none', 'important');
                    $display[0].style.setProperty('display', 'block', 'important');
                } else {
                    $display[0].style.setProperty('display', 'none', 'important');
                    $ta[0].style.setProperty('display', 'block', 'important');
                }
            });
        }
        if (table) {
            table.on('draw.dt', enforceNoteCellState);
        }

        // Live "last updated" timestamp under each note. Shows a relative time
        // (e.g. "last updated 25 seconds ago") that self-refreshes on an interval,
        // and switches to an absolute date once the note is older than 6 days.
        var NOTE_UPDATED_LABEL = "<?= _l('last_updated'); ?>";
        function renderNoteTimestamp($el) {
            var raw = $el.attr('data-updated');
            if (!raw || typeof moment === 'undefined') { return; }
            var m = moment(raw);
            if (!m.isValid()) { $el.text(''); return; }
            var text = moment().diff(m, 'days', true) > 6
                ? m.format('MMMM D, YYYY [at] HH:mm')
                : m.fromNow();
            $el.text(NOTE_UPDATED_LABEL + ' ' + text);
        }
        function refreshNoteTimestamps() {
            $('.table-projects .project-note-updated').each(function() {
                renderNoteTimestamp($(this));
            });
        }
        if (table) {
            table.on('draw.dt', refreshNoteTimestamps);
        }
        // Keep relative times current without a page reload.
        setInterval(refreshNoteTimestamps, 30000);

        // Keep clicks inside the notes cell from triggering any row-level handlers
        $(document).on('click', '.table-projects .project-listing-notes', function(e) {
            e.stopPropagation();
        });

        // Remember the value when editing starts (covers both click-to-edit and
        // the initially-shown textarea for empty notes)
        $(document).on('focus', '.table-projects .project-note-input', function() {
            $(this).data('orig', $(this).val());
        });

        // Click the note text → hide text, show the textarea with the current value
        $(document).on('click', '.table-projects .project-note-display', function() {
            var $cell = $(this).closest('.project-listing-notes');
            var $ta   = $cell.find('.project-note-input');
            this.style.setProperty('display', 'none', 'important');
            $ta[0].style.setProperty('display', 'block', 'important');
            $ta.focus();
            // place cursor at the end
            var v = $ta.val();
            $ta.val('').val(v);
        });

        // Blur the textarea → show text (when it has content) and save if it changed
        $(document).on('blur', '.table-projects .project-note-input', function() {
            var $ta      = $(this);
            var $cell    = $ta.closest('.project-listing-notes');
            var $display = $cell.find('.project-note-display');
            var $stamp   = $cell.find('.project-note-updated');
            var val      = $ta.val();

            if ($.trim(val) !== '') {
                // Has content → show text, hide textarea
                $display.text(val);
                $ta[0].style.setProperty('display', 'none', 'important');
                $display[0].style.setProperty('display', 'block', 'important');
            } else {
                // Empty → keep the textarea visible
                $display[0].style.setProperty('display', 'none', 'important');
                $ta[0].style.setProperty('display', 'block', 'important');
            }

            // Nothing changed — don't save / don't bump the timestamp
            if (val === $ta.data('orig')) {
                return;
            }

            var noteData = { project_id: $cell.data('project-id'), notes: val };
            // CSRF token is required on POST requests (csrf_protection is enabled)
            if (typeof csrfData !== 'undefined') {
                noteData[csrfData.token_name] = csrfData.hash;
            }

            $stamp.text('<?= _l('saving') . '...'; ?>');
            $.ajax({
                url: admin_url + 'projects/save_listing_notes',
                type: 'POST',
                dataType: 'json',
                data: noteData,
                success: function(res) {
                    if (res && res.success) {
                        $stamp.attr('data-updated', res.updated_at || '');
                        renderNoteTimestamp($stamp);
                    } else {
                        $stamp.text('');
                        alert_float('danger', (res && res.message) ? res.message : 'Error saving notes');
                    }
                },
                error: function() {
                    $stamp.text('');
                    alert_float('danger', 'Error saving notes');
                }
            });
        });
    });
</script>
<?php } else { ?>
<script src="<?= base_url('assets/js/projects' . (ENVIRONMENT === 'production' ? '.min' : '') . '.js'); ?>"></script>
<script>
    $(function() {
        projects_kanban();
        init_ajax_search('customer', '#clientid_copy_project.ajax-search');
    });
</script>
<?php } ?>
</body>

</html>