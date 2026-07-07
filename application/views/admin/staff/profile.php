<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
.profile-settings-nav {
    margin: 0;
    padding: 0;
    list-style: none;
}
.profile-settings-nav li a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    color: #475569;
    text-decoration: none;
    border-left: 3px solid transparent;
    font-weight: 500;
    transition: background .15s ease, color .15s ease;
}
.profile-settings-nav li a:hover,
.profile-settings-nav li a:focus {
    background: #f8fafc;
    color: #1e293b;
}
.profile-settings-nav li a.active {
    background: #eff6ff;
    color: #2563eb;
    border-left-color: #2563eb;
}
.profile-settings-nav li a i {
    width: 18px;
    text-align: center;
    font-size: 15px;
}
html[dir="rtl"] .profile-settings-nav li a {
    border-left: 0;
    border-right: 3px solid transparent;
}
html[dir="rtl"] .profile-settings-nav li a.active {
    border-right-color: #2563eb;
}
/* Google Authenticator toggle switch */
.g2fa-switch {
    position: relative;
    display: inline-block;
    width: 46px;
    height: 26px;
    flex: none;
}
.g2fa-switch input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}
.g2fa-switch .g2fa-slider {
    position: absolute;
    inset: 0;
    cursor: pointer;
    background-color: #cbd5e1;
    border-radius: 999px;
    transition: background-color .2s ease;
}
.g2fa-switch .g2fa-slider:before {
    content: "";
    position: absolute;
    top: 3px;
    left: 3px;
    width: 20px;
    height: 20px;
    background-color: #fff;
    border-radius: 50%;
    box-shadow: 0 1px 2px rgba(0, 0, 0, .25);
    transition: transform .2s ease;
}
.g2fa-switch input:checked + .g2fa-slider {
    background-color: #2563eb;
}
.g2fa-switch input:checked + .g2fa-slider:before {
    transform: translateX(20px);
}
.g2fa-switch input:focus-visible + .g2fa-slider {
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .35);
}
html[dir="rtl"] .g2fa-switch .g2fa-slider:before {
    left: auto;
    right: 3px;
}
html[dir="rtl"] .g2fa-switch input:checked + .g2fa-slider:before {
    transform: translateX(-20px);
}
/* Skeleton placeholder shown while the QR/secret is generated on the server.
   Mirrors the real google_two_factor view: guide text on top, a centered QR
   with the secret info below it, then the code label and input + verify button. */
.g2fa-skeleton {
    padding: 20px;
}
.g2fa-skeleton .g2fa-skel {
    background: #e9edf2;
    border-radius: 6px;
    position: relative;
    overflow: hidden;
}
.g2fa-skeleton .g2fa-skel::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    width: 100%;
    transform: translateX(-100%);
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .6), transparent);
    animation: g2fa-shimmer 1.2s infinite;
}
@keyframes g2fa-shimmer {
    100% { transform: translateX(100%); }
}
.g2fa-skeleton .g2fa-skel-line {
    height: 12px;
    margin-bottom: 12px;
}
/* Centered QR + secret block */
.g2fa-skeleton .g2fa-skel-qr-wrap {
    text-align: center;
    margin: 28px 0 26px;
}
.g2fa-skeleton .g2fa-skel-qr {
    height: 180px;
    width: 180px;
    margin: 0 auto 16px;
}
.g2fa-skeleton .g2fa-skel-secret {
    height: 14px;
    width: 38%;
    margin: 0 auto 10px;
}
.g2fa-skeleton .g2fa-skel-secret-sm {
    height: 10px;
    width: 55%;
    margin: 0 auto;
}
/* Code label + input group (input beside verify button) */
.g2fa-skeleton .g2fa-skel-label {
    height: 12px;
    width: 30%;
    margin-bottom: 12px;
}
.g2fa-skeleton .g2fa-skel-inputgroup {
    display: flex;
    gap: 0;
}
.g2fa-skeleton .g2fa-skel-input {
    height: 40px;
    flex: 1 1 auto;
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}
.g2fa-skeleton .g2fa-skel-btn {
    height: 40px;
    width: 96px;
    flex: 0 0 auto;
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}
</style>
<div id="wrapper">
    <div class="content">
        <div class="tw-max-w-5xl tw-mx-auto">
            <h4 class="tw-mt-0 tw-mb-4 tw-font-bold tw-text-xl tw-text-neutral-800">
                <?= _l('account_settings'); ?>
            </h4>

            <div class="row">
                <!-- Sidebar menu -->
                <div class="col-md-3">
                    <div class="panel_s">
                        <div class="panel-body tw-p-0">
                            <ul class="profile-settings-nav">
                                <li>
                                    <a href="#profile" data-tab="profile">
                                        <i class="fa-regular fa-user"></i>
                                        <span><?= _l('profile_menu_profile'); ?></span>
                                    </a>
                                </li>
                                <li>
                                    <a href="#change_password" data-tab="change_password">
                                        <i class="fa-solid fa-key"></i>
                                        <span><?= _l('profile_menu_change_password'); ?></span>
                                    </a>
                                </li>
                                <li>
                                    <a href="#two_factor_authentication" data-tab="two_factor_authentication">
                                        <i class="fa-solid fa-shield-halved"></i>
                                        <span><?= _l('profile_menu_mfa'); ?></span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Content panes -->
                <div class="col-md-9">

                    <!-- Profile pane -->
                    <div class="profile-settings-pane" id="pane-profile">
                        <?= form_open_multipart($this->uri->uri_string(), ['id' => 'staff_profile_table', 'autocomplete' => 'off']); ?>
                        <div class="panel_s">
                            <div class="panel-body">
                                <h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700 tw-mb-4">
                                    <?= _l('profile_menu_profile'); ?>
                                </h4>
                                <?php if ($member->profile_image == null) { ?>
                                <div class="form-group">
                                    <label for="profile_image"
                                        class="profile-image"><?= _l('staff_edit_profile_image'); ?></label>
                                    <input type="file" name="profile_image" class="form-control" id="profile_image"
                                        accept="image/*">
                                </div>
                                <?php } ?>
                                <?php if ($member->profile_image != null) { ?>
                                <div class="form-group">
                                    <div class="row">
                                        <div class="col-md-9">
                                            <?= staff_profile_image($member->staffid, ['img', 'img-responsive', 'staff-profile-image-thumb'], 'thumb'); ?>
                                        </div>
                                        <div class="col-md-3 text-right">
                                            <a
                                                href="<?= admin_url('staff/remove_staff_profile_image'); ?>"><i
                                                    class="fa fa-remove"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <?php } ?>
                                <div class="form-group">
                                    <label for="firstname"
                                        class="control-label"><?= _l('staff_add_edit_firstname'); ?></label>
                                    <input type="text" class="form-control" name="firstname"
                                        value="<?= isset($member) ? e($member->firstname) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="lastname"
                                        class="control-label"><?= _l('staff_add_edit_lastname'); ?></label>
                                    <input type="text" class="form-control" name="lastname"
                                        value="<?= isset($member) ? e($member->lastname) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="email"
                                        class="control-label"><?= _l('staff_add_edit_email'); ?></label>
                                    <input type="email"
                                        <?php if (staff_can('edit', 'staff')) { ?>
                                    name="email"
                                    <?php } else { ?> disabled="true"
                                    <?php } ?> class="form-control"
                                    value="<?= e($member->email); ?>"
                                    id="email">
                                </div>
                                <?php $value = (isset($member) ? $member->phonenumber : ''); ?>
                                <?= render_input('phonenumber', 'staff_add_edit_phonenumber', $value); ?>
                                <?php if (! is_language_disabled()) { ?>
                                <div class="form-group select-placeholder">
                                    <label for="default_language"
                                        class="control-label"><?= _l('localization_default_language'); ?></label>
                                    <select name="default_language" data-live-search="true" id="default_language"
                                        class="form-control selectpicker"
                                        data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
                                        <option value="">
                                            <?= _l('system_default_string'); ?>
                                        </option>
                                        <?php foreach ($this->app->get_available_languages() as $availableLanguage) {
                                            $selected = '';
                                            if (isset($member)) {
                                                if ($member->default_language == $availableLanguage) {
                                                    $selected = 'selected';
                                                }
                                            } ?>
                                        <option
                                            value="<?= e($availableLanguage); ?>"
                                            <?= e($selected); ?>>
                                            <?= e(ucfirst($availableLanguage)); ?>
                                        </option>
                                        <?php
                                        } ?>
                                    </select>
                                </div>
                                <?php } ?>
                                <div class="form-group select-placeholder">
                                    <label
                                        for="direction"><?= _l('document_direction'); ?></label>
                                    <select class="selectpicker"
                                        data-none-selected-text="<?= _l('system_default_string'); ?>"
                                        data-width="100%" name="direction" id="direction">
                                        <option value="" <?= isset($member) && empty($member->direction) ? ' selected' : '' ?>>
                                        </option>
                                        <option value="ltr" <?= isset($member) && $member->direction == 'ltr' ? ' selected' : '' ?>>
                                            LTR
                                        </option>
                                        <option value="rtl" <?= isset($member) && $member->direction == 'rtl' ? ' selected' : '' ?>>
                                            RTL
                                        </option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="facebook" class="control-label"><i class="fa-brands fa-facebook-f"></i>
                                        <?= _l('staff_add_edit_facebook'); ?></label>
                                    <input type="text" class="form-control" name="facebook"
                                        value="<?= isset($member) ? e($member->facebook) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="linkedin" class="control-label"><i class="fa-brands fa-linkedin-in"></i>
                                        <?= _l('staff_add_edit_linkedin'); ?></label>
                                    <input type="text" class="form-control" name="linkedin"
                                        value="<?= isset($member) ? e($member->linkedin) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="skype" class="control-label"><i class="fa-brands fa-skype"></i>
                                        <?= _l('staff_add_edit_skype'); ?></label>
                                    <input type="text" class="form-control" name="skype"
                                        value="<?= isset($member) ? e($member->skype) : ''; ?>">
                                </div>
                                <i class="fa-regular fa-circle-question" data-toggle="tooltip"
                                    data-title="<?= _l('staff_email_signature_help'); ?>"></i>
                                <?php $value = (isset($member) ? $member->email_signature : ''); ?>
                                <?= render_textarea('email_signature', 'settings_email_signature', $value, ['data-entities-encode' => 'true']); ?>
                                <?php if (count($staff_departments) > 0) { ?>
                                <div class="form-group">
                                    <label
                                        for="departments"><?= _l('staff_edit_profile_your_departments'); ?></label>
                                    <div class="clearfix"></div>
                                    <?php foreach ($departments as $department) { ?>
                                    <?php foreach ($staff_departments as $staff_department) { ?>
                                    <?php if ($staff_department['departmentid'] == $department['departmentid']) { ?>
                                    <div class="label label-primary">
                                        <?= e($staff_department['name']); ?>
                                    </div>
                                    <?php } ?>
                                    <?php } ?>
                                    <?php } ?>
                                </div>
                                <?php } ?>
                            </div>
                            <div class="panel-footer text-right">
                                <button type="submit" class="btn btn-primary">
                                    <?= _l('submit'); ?>
                                </button>
                            </div>
                        </div>
                        <?= form_close(); ?>
                    </div>

                    <!-- Change password pane -->
                    <div class="profile-settings-pane" id="pane-change_password" style="display:none;">
                        <?= form_open('admin/staff/change_password_profile', ['id' => 'staff_password_change_form']); ?>
                        <div class="panel_s">
                            <div class="panel-body">
                                <h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700 tw-mb-4">
                                    <?= _l('staff_edit_profile_change_your_password'); ?>
                                </h4>
                                <div class="form-group">
                                    <label for="oldpassword"
                                        class="control-label"><?= _l('staff_edit_profile_change_old_password'); ?></label>
                                    <input type="password" class="form-control" name="oldpassword" id="oldpassword">
                                </div>
                                <div class="form-group">
                                    <label for="newpassword"
                                        class="control-label"><?= _l('staff_edit_profile_change_new_password'); ?></label>
                                    <input type="password" class="form-control" id="newpassword" name="newpassword">
                                </div>
                                <div class="form-group">
                                    <label for="newpasswordr"
                                        class="control-label"><?= _l('staff_edit_profile_change_repeat_new_password'); ?></label>
                                    <input type="password" class="form-control" id="newpasswordr" name="newpasswordr">
                                </div>
                            </div>
                            <div class="panel-footer">
                                <div class="tw-flex tw-justify-between">
                                    <span>
                                        <?php if ($member->last_password_change != null) { ?>
                                        <?= _l('staff_add_edit_password_last_changed'); ?>:
                                        <span class="text-has-action" data-toggle="tooltip"
                                            data-title="<?= e(_dt($member->last_password_change)); ?>">
                                            <?= e(time_ago($member->last_password_change)); ?>
                                        </span>
                                        <?php } ?>
                                    </span>
                                    <button type="submit"
                                        class="btn btn-primary"><?= _l('submit'); ?></button>
                                </div>
                            </div>
                        </div>
                        <?= form_close(); ?>
                    </div>

                    <!-- Multi-factor authentication pane -->
                    <div class="profile-settings-pane" id="pane-two_factor_authentication" style="display:none;">
                        <?= form_open('admin/staff/update_two_factor', ['id' => 'two_factor_auth_form']); ?>
                        <div class="panel_s">
                            <div class="panel-body">
                                <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
                                    <h4 class="tw-mt-0 tw-mb-0 tw-font-bold tw-text-lg tw-text-neutral-700">
                                        <?= _l('google_two_factor_authentication_heading'); ?>
                                    </h4>
                                    <label class="g2fa-switch">
                                        <input type="checkbox" id="g2fa_toggle"
                                            <?= ($member->two_factor_auth_enabled == 2) ? 'checked' : '' ?>>
                                        <span class="g2fa-slider"></span>
                                    </label>
                                </div>
                                <input type="hidden" name="two_factor_auth" id="two_factor_auth_value"
                                    value="<?= ($member->two_factor_auth_enabled == 2) ? 'google' : 'off' ?>">
                                <?php if (! extension_loaded('imagick')) { ?>
                                <div id="imagick_error" class="alert alert-danger mtop15" style="display: none;">
                                    <i class="fa fa-exclamation-triangle"></i>
                                    <strong>Error:</strong> The PHP imagick extension is required for Google Two-Factor
                                    Authentication but is not installed on this server. Please contact your system administrator to
                                    install the imagick extension.
                                </div>
                                <?php } ?>
                                <div id="qr_image" class="mtop30 card">
                                </div>

                                <?php
                                // Pending recovery codes live in the session (not flashdata) so this
                                // screen survives refreshes until the user clicks "Complete", which
                                // clears them (see Staff::complete_two_factor_setup).
                                $flashCodes  = $this->session->userdata('two_factor_pending_codes');
                                $g2faEnabled = ($member->two_factor_auth_enabled == 2);
                                $backupCount = 0;
                                if (isset($member->two_factor_backup_codes) && ! empty($member->two_factor_backup_codes)) {
                                    $decodedCodes = json_decode($member->two_factor_backup_codes, true);
                                    $backupCount  = is_array($decodedCodes) ? count($decodedCodes) : 0;
                                }
                                ?>

                                <?php if ($flashCodes) { ?>
                                <div class="alert alert-warning mtop20" id="backup_codes_box">
                                    <h4 class="tw-font-bold tw-mt-0">
                                        <?= _l('two_factor_backup_codes_title'); ?>
                                    </h4>
                                    <p><?= _l('two_factor_backup_codes_info'); ?></p>
                                    <div class="tw-grid tw-grid-cols-2 tw-gap-2 tw-my-3" id="backup_codes_list">
                                        <?php foreach ($flashCodes as $c) { ?>
                                        <div
                                            class="tw-bg-white tw-border tw-border-solid tw-border-neutral-300 tw-rounded tw-px-3 tw-py-1 tw-text-center tw-tracking-widest tw-font-mono">
                                            <?= e($c); ?>
                                        </div>
                                        <?php } ?>
                                    </div>
                                    <button type="button" class="btn btn-default btn-sm" id="copy_backup_codes">
                                        <?= _l('two_factor_backup_codes_copy'); ?>
                                    </button>
                                    <button type="button" class="btn btn-default btn-sm" id="download_backup_codes">
                                        <?= _l('two_factor_backup_codes_download'); ?>
                                    </button>
                                    <button type="button" class="btn btn-default btn-sm" id="email_backup_codes">
                                        <i class="fa fa-envelope-o"></i> <?= _l('two_factor_backup_codes_email'); ?>
                                    </button>
                                    <hr class="tw-my-3">
                                    <div class="tw-flex tw-justify-between tw-items-center tw-gap-4">
                                        <p class="tw-mb-0 text-muted">
                                            <?= _l('two_factor_backup_codes_complete_hint'); ?>
                                        </p>
                                        <a href="<?= admin_url('staff/complete_two_factor_setup'); ?>"
                                            class="btn btn-primary tw-flex-shrink-0" id="complete_two_factor_setup">
                                            <i class="fa fa-check"></i> <?= _l('two_factor_setup_complete'); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php } elseif ($g2faEnabled) { ?>
                                <hr />
                                <h4 class="tw-font-bold tw-text-base tw-text-neutral-700">
                                    <?= _l('two_factor_backup_codes_title'); ?>
                                </h4>
                                <p class="text-muted">
                                    <?= _l('two_factor_backup_codes_remaining', $backupCount); ?>
                                </p>
                                <a href="<?= admin_url('staff/regenerate_backup_codes'); ?>"
                                    class="btn btn-default btn-sm"
                                    onclick="return confirm('<?= _l('two_factor_backup_codes_regenerate_confirm'); ?>');">
                                    <?= _l('two_factor_backup_codes_regenerate'); ?>
                                </a>
                                <?php } ?>
                            </div>
                            <!-- Save happens automatically after the code is verified (enable)
                                 or when the toggle is switched off (disable). -->
                            <div class="panel-footer text-right" style="display: none;">
                                <button id="submit_2fa" type="submit" class="btn btn-primary">
                                    <?= _l('submit'); ?>
                                </button>
                            </div>
                        </div>
                        <?= form_close(); ?>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <?php init_tail(); ?>
    <script src="<?php echo base_url('assets/js/password-toggle.js'); ?>"></script>
    <script>
        $(function() {
            // ---- Settings sidebar tab switching ----
            var validTabs = ['profile', 'change_password', 'two_factor_authentication'];

            function activateTab(tab) {
                if (validTabs.indexOf(tab) === -1) {
                    tab = 'profile';
                }
                $('.profile-settings-nav a').removeClass('active');
                $('.profile-settings-nav a[data-tab="' + tab + '"]').addClass('active');
                $('.profile-settings-pane').hide();
                $('#pane-' + tab).show();
            }

            var initialTab = (window.location.hash || '').replace('#', '');
            // Just generated/regenerated recovery codes -> land on the MFA tab.
            if ($('#backup_codes_box').length) {
                initialTab = 'two_factor_authentication';
            }
            activateTab(initialTab);

            // Avoid the browser jumping to a hash target; keep the view at the top.
            if (initialTab) {
                window.scrollTo(0, 0);
                $('html, body').scrollTop(0);
            }

            // ---- Recovery (backup) codes: copy / download ----
            var backupCodesUsername = '<?= e($member->email); ?>';
            function collectBackupCodes() {
                return $('#backup_codes_list').children().map(function() {
                    return $(this).text().trim();
                }).get().join('\n');
            }
            // Full text used for both the copied clipboard content and the
            // downloaded .txt file, so the two stay identical.
            function buildBackupCodesText() {
                return 'Concatstring Portal - BACKUP VERIFICATION CODES\n\n\n' +
                    'Points to note\n' +
                    '--------------\n' +
                    '# Each code can be used only once.\n' +
                    '# Do not share these codes with anyone.\n' +
                    '# If you have used up all your codes or lost them, you can always generate a new set of codes.\n' +
                    '# Whenever you generate a new set of codes, the old unused codes will become invalid.\n\n\n' +
                    'Generated codes\n' +
                    '---------------\n' +
                    'Username: ' + backupCodesUsername + '\n\n' +
                    collectBackupCodes() + '\n';
            }
            // Fallback copy for non-secure contexts (e.g. http://*.local) where
            // navigator.clipboard is unavailable. Selects the full text so every
            // recovery code is copied, not a partial/empty clipboard.
            function fallbackCopy(text) {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.top = '-9999px';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                ta.setSelectionRange(0, text.length);
                var ok = false;
                try {
                    ok = document.execCommand('copy');
                } catch (e) {
                    ok = false;
                }
                document.body.removeChild(ta);
                return ok;
            }
            $('#copy_backup_codes').on('click', function() {
                var text = buildBackupCodesText();
                var $btn = $(this);
                var done = function() {
                    $btn.text('<?= _l('two_factor_backup_codes_copied'); ?>');
                };
                if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(done, function() {
                        fallbackCopy(text);
                        done();
                    });
                } else if (fallbackCopy(text)) {
                    done();
                }
            });
            $('#download_backup_codes').on('click', function() {
                var blob = new Blob([buildBackupCodesText()], {
                    type: 'text/plain'
                });
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'CS Portal Recovery Codes - ' + backupCodesUsername + '.txt';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(a.href);
            });
            $('#email_backup_codes').on('click', function() {
                var $btn = $(this);
                var codes = $('#backup_codes_list').children().map(function() {
                    return $(this).text().trim();
                }).get();
                if (!codes.length || $btn.prop('disabled')) {
                    return;
                }
                var originalHtml = $btn.html();
                $btn.prop('disabled', true).text('<?= _l('two_factor_backup_codes_email_sending'); ?>');
                $.post(admin_url + 'staff/email_backup_codes', {
                    codes: codes
                }, function(response) {
                    if (response && response.success) {
                        alert_float('success', response.message);
                    } else {
                        alert_float('danger', response && response.message ?
                            response.message : '<?= _l('two_factor_backup_codes_email_failed'); ?>');
                    }
                }, 'json').fail(function() {
                    alert_float('danger', '<?= _l('two_factor_backup_codes_email_failed'); ?>');
                }).always(function() {
                    $btn.prop('disabled', false).html(originalHtml);
                });
            });

            $('.profile-settings-nav a').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                activateTab(tab);
                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', '#' + tab);
                } else {
                    window.location.hash = tab;
                }
            });

            // ---- Two factor authentication ----
            var qr_loaded = 0;
            var is_g2fa_enabled =
                "<?= $member->two_factor_auth_enabled ?>";
            var
                imagick_available = <?= extension_loaded('imagick') ? 'true' : 'false' ?> ;

            $('#g2fa_toggle').on('change', function() {
                if (this.checked) {
                    // Turning ON -> start the Google Authenticator setup process.
                    $('#two_factor_auth_value').val('google');

                    if (!imagick_available) {
                        $('#imagick_error').show();
                        $('#submit_2fa').prop("disabled", true);
                        $('#qr_image').hide();
                        return;
                    }

                    $('#imagick_error').hide();

                    if (is_g2fa_enabled == 2) {
                        // Already enrolled; no need to run setup again.
                        $('#submit_2fa').prop("disabled", false);
                        return;
                    }

                    if (qr_loaded == 0) {
                        // Generating the TOTP secret + QR image on the server takes ~2s.
                        // Show a skeleton placeholder immediately; the .load() response
                        // replaces the inner HTML (skeleton included) once it arrives.
                        var g2faSkeleton =
                            '<div class="g2fa-skeleton">' +
                                // guide text (top)
                                '<div class="g2fa-skel g2fa-skel-line" style="width:92%"></div>' +
                                '<div class="g2fa-skel g2fa-skel-line" style="width:78%"></div>' +
                                // centered QR + secret info
                                '<div class="g2fa-skel-qr-wrap">' +
                                    '<div class="g2fa-skel g2fa-skel-qr"></div>' +
                                    '<div class="g2fa-skel g2fa-skel-secret"></div>' +
                                    '<div class="g2fa-skel g2fa-skel-secret-sm"></div>' +
                                '</div>' +
                                // code label + input beside verify button
                                '<div class="g2fa-skel g2fa-skel-label"></div>' +
                                '<div class="g2fa-skel-inputgroup">' +
                                    '<div class="g2fa-skel g2fa-skel-input"></div>' +
                                    '<div class="g2fa-skel g2fa-skel-btn"></div>' +
                                '</div>' +
                            '</div>';
                        $('#qr_image').html(g2faSkeleton).show();

                        $('#qr_image').load(admin_url + 'authentication/get_qr', {}, function(response,
                            status) {
                            qr_loaded = 1;
                            $('#qr_image').show();
                        });
                    } else {
                        $('#qr_image').show();
                    }
                    $('#submit_2fa').prop("disabled", true);
                } else if (is_g2fa_enabled == 2) {
                    // Currently enabled -> confirm, then disable & save immediately.
                    if (!confirm("<?= _l('two_factor_authentication_disable_confirm'); ?>")) {
                        // User cancelled — revert the toggle back to ON.
                        $(this).prop('checked', true);
                        return;
                    }
                    $('#two_factor_auth_value').val('off');
                    $('#imagick_error').hide();
                    $('#qr_image').hide();
                    $('#two_factor_auth_form').submit();
                } else {
                    // Setup was in progress but never saved -> just cancel it locally.
                    $('#two_factor_auth_value').val('off');
                    $('#imagick_error').hide();
                    $('#qr_image').hide();
                }
            });

            // Check initial state
            if ($('#g2fa_toggle').is(':checked') && !imagick_available) {
                $('#imagick_error').show();
            }

            // Prevent form submission if Google 2FA is selected but imagick is not available
            $('#two_factor_auth_form').on('submit', function(e) {
                if ($('#two_factor_auth_value').val() === 'google' && !imagick_available) {
                    e.preventDefault();
                    $('#imagick_error').show();
                    return false;
                }
            });

            appValidateForm($('#staff_profile_table'), {
                firstname: 'required',
                lastname: 'required',
                email: 'required'
            });
            appValidateForm($('#staff_password_change_form'), {
                oldpassword: 'required',
                newpassword: 'required',
                newpasswordr: {
                    equalTo: "#newpassword"
                }
            });
            appValidateForm($('#two_factor_auth_form'), {
                two_factor_auth: 'required'
            });
        });
    </script>
    </body>

    </html>
