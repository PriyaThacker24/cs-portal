<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$twoFaSkipsLeft = two_factor_reminder_skips_left();
$twoFaSkipsText = $twoFaSkipsLeft == 1
    ? _l('two_factor_reminder_skips_left_one', $twoFaSkipsLeft)
    : _l('two_factor_reminder_skips_left_many', $twoFaSkipsLeft);
?>
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
                <div class="text-left">
                    <button type="button" class="btn btn-link text-muted" id="two_factor_reminder_skip"
                        <?= $twoFaSkipsLeft <= 0 ? 'disabled' : ''; ?>>
                        <?= _l('two_factor_reminder_skip'); ?>
                    </button>
                    <div class="text-muted tw-text-xs" id="two_factor_reminder_skips_left"
                        style="padding-left:15px;">
                        <?= $twoFaSkipsText; ?>
                    </div>
                </div>
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
        var $skipBtn = $('#two_factor_reminder_skip');

        $modal.modal('show');

        // Spend one skip and dismiss the reminder for this login session.
        function dismissReminder() {
            var data = {
                count: 1
            };
            if (typeof csrfData !== 'undefined') {
                data[csrfData.token_name] = csrfData.hash;
            }
            return $.post(admin_url + 'staff/skip_two_factor_reminder', data, null, 'json');
        }

        $skipBtn.on('click', function() {
            if ($skipBtn.prop('disabled')) {
                return;
            }
            // Prevent double submits while the request is in flight.
            $skipBtn.prop('disabled', true);

            dismissReminder().done(function() {
                // The skip was recorded — including the final one — so close the
                // popup and let the user continue. If that was the last skip, the
                // Skip button will be disabled the next time they log in (0 left).
                $modal.modal('hide');
            }).fail(function() {
                // Let the user try again if the request failed.
                $skipBtn.prop('disabled', false);
            });
        });

        // "Enable" navigates straight to the 2FA setup page (its own href) and
        // must NOT dismiss/skip the reminder — the reminder is simply suppressed
        // on the setup page itself.
    });
</script>
