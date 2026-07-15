<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
// Per-row listing Notes behaviour, shared by the main tasks list and the relation
// tasks tables (project/customer/lead tabs). Delegated on the unique
// .task-listing-notes wrapper so it is table-agnostic, and bound once per page.
// IMPORTANT: this must be loaded OUTSIDE any Vue mount element (e.g. #vueApp),
// otherwise Vue treats it as a template and never executes the <script>.
(function() {
    if (window._taskListingNotesBound) { return; }

    // This partial can render mid-page (e.g. the project Tasks tab), before jQuery is
    // loaded in the footer. Wait until jQuery is available, then bind once.
    function boot() {
        if (typeof window.jQuery === 'undefined') {
            return setTimeout(boot, 50);
        }
        if (window._taskListingNotesBound) { return; }
        window._taskListingNotesBound = true;
        var $ = window.jQuery;
        $(function() {
        // Enforce one-at-a-time visibility per notes cell: text when it has content,
        // textarea when empty. Runs after every DataTable draw so it never shows both.
        function enforceTaskNoteCellState() {
            $('.task-listing-notes').each(function() {
                var $cell = $(this);
                var $ta   = $cell.find('.task-note-input');
                if (!$ta.length || $ta.is(':focus')) { return; } // don't disturb the cell being edited
                var $display = $cell.find('.task-note-display');
                if ($.trim($ta.val()) !== '') {
                    $ta[0].style.setProperty('display', 'none', 'important');
                    $display[0].style.setProperty('display', 'block', 'important');
                } else {
                    $display[0].style.setProperty('display', 'none', 'important');
                    $ta[0].style.setProperty('display', 'block', 'important');
                }
            });
        }
        $(document).on('draw.dt', enforceTaskNoteCellState);
        enforceTaskNoteCellState();

        // Keep clicks inside the notes cell from triggering any row-level handlers
        $(document).on('click', '.task-listing-notes', function(e) {
            e.stopPropagation();
        });

        // Remember the value when editing starts
        $(document).on('focus', '.task-listing-notes .task-note-input', function() {
            $(this).data('orig', $(this).val());
        });

        // Click the note text → hide text, show the textarea with the current value
        $(document).on('click', '.task-listing-notes .task-note-display', function() {
            var $cell = $(this).closest('.task-listing-notes');
            var $ta   = $cell.find('.task-note-input');
            this.style.setProperty('display', 'none', 'important');
            $ta[0].style.setProperty('display', 'block', 'important');
            $ta.focus();
            // place cursor at the end
            var v = $ta.val();
            $ta.val('').val(v);
        });

        // Blur the textarea → show text (when it has content) and save if it changed
        $(document).on('blur', '.task-listing-notes .task-note-input', function() {
            var $ta      = $(this);
            var $cell    = $ta.closest('.task-listing-notes');
            var $display = $cell.find('.task-note-display');
            var $stamp   = $cell.find('.task-note-updated');
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

            var noteData = { task_id: $cell.data('task-id'), notes: val };
            // CSRF token is required on POST requests (csrf_protection is enabled)
            if (typeof csrfData !== 'undefined') {
                noteData[csrfData.token_name] = csrfData.hash;
            }

            $stamp.text('<?= _l('saving'); ?>');
            $.ajax({
                url: admin_url + 'tasks/save_listing_notes',
                type: 'POST',
                dataType: 'json',
                data: noteData,
                success: function(res) {
                    if (res && res.success) {
                        $stamp.text(res.updated_text || '');
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
    }
    boot();
})();
</script>
