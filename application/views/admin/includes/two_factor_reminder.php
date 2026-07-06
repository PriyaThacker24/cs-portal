<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="modal fade" id="two_factor_reminder_modal" tabindex="-1" role="dialog"
    data-backdrop="static" data-keyboard="false" aria-labelledby="two_factor_reminder_title">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-body text-center tw-py-8 tw-px-6">
                <div class="tw-mb-4 tw-text-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                        stroke-linejoin="round" style="margin:0 auto;display:block;">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        <path d="m9 12 2 2 4-4"></path>
                    </svg>
                </div>
                <h4 id="two_factor_reminder_title" class="tw-font-bold tw-text-lg tw-mt-0 tw-mb-2">
                    <?= _l('two_factor_reminder_heading'); ?>
                </h4>
                <p class="text-muted tw-mb-0">
                    <?= _l('two_factor_reminder_subheading'); ?>
                </p>
            </div>
            <div class="modal-footer tw-flex tw-justify-between tw-items-center">
                <button type="button" class="btn btn-link text-muted" id="two_factor_reminder_skip">
                    <?= _l('two_factor_reminder_skip'); ?>
                </button>
                <a href="<?= admin_url('staff/edit_profile'); ?>#two_factor_authentication"
                    class="btn btn-primary" id="two_factor_reminder_enable">
                    <?= _l('two_factor_reminder_enable'); ?>
                </a>
            </div>
        </div>
    </div>
</div>
<script>
    $(function() {
        var $modal = $('#two_factor_reminder_modal');
        $modal.modal('show');

        // Persist the "reminder handled" flag for this login session.
        function dismissReminder() {
            var data = {};
            if (typeof csrfData !== 'undefined') {
                data[csrfData.token_name] = csrfData.hash;
            }
            return $.post(admin_url + 'staff/skip_two_factor_reminder', data);
        }

        $('#two_factor_reminder_skip').on('click', function() {
            dismissReminder();
            $modal.modal('hide');
        });

        // Clicking "Enable" should also stop the popup from re-appearing while
        // the staff is setting 2FA up. Wait for the flag to be saved, then go.
        $('#two_factor_reminder_enable').on('click', function(e) {
            e.preventDefault();
            var href = $(this).attr('href');
            dismissReminder().always(function() {
                window.location.href = href;
            });
        });
    });
</script>
