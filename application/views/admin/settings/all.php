<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <?php if ($this->session->flashdata('debug')) { ?>
        <div class="alert alert-warning">
            <?= $this->session->flashdata('debug'); ?>
        </div>
        <?php } ?>
        <div class="row">
            <div class="col-xs-12 col-md-12 col-lg-10">
                <div class="row">
                    <div class="col-md-4 col-lg-3">
                        <h4 class="tw-font-bold tw-mt-0 tw-text-neutral-800">
                            <?= _l('settings'); ?>
                        </h4>
                        <div class="panel_s">
                            <div class="panel-body">
                                <div class="tw-flex tw-flex-col tw-gap-6">
                                    <?php foreach ($sections as $sectionId => $section) { ?>
                                    <div>
                                        <h4 class="tw-mt-0 tw-mb-4 tw-text-sm tw-text-neutral-500 tw-font-medium">
                                            <?= $section['title']; ?>
                                        </h4>
                                        <ul class="tw-space-y-2">
                                            <?php foreach ($section['children'] as $child) { ?>
                                            <li
                                                class="settings-group-<?= e($child['id']); ?>">
                                                <a href="<?= admin_url('settings?group=' . $child['id']); ?>"
                                                    class="tw-group tw-flex tw-items-center tw-text-sm hover:tw-text-neutral-800 focus:tw-text-neutral-800 tw-font-medium tw-gap-2.5 <?= ($group['id'] === $child['id']) ? ' tw-text-neutral-800' : 'tw-text-neutral-600' ?>">
                                                    <i
                                                        class="<?= $child['icon'] ?? 'fa-regular fa-circle-question'; ?> fa-fw fa-lg tw-mr-0.5 group-hover:tw-text-neutral-800 <?= $group['id'] === $child['id'] ? 'tw-text-neutral-800' : 'tw-text-neutral-500'; ?>"></i>
                                                    <span>
                                                        <?= $child['name']; ?>
                                                    </span>
                                                    <?php if (isset($child['badge'], $child['badge']['value']) && ! empty($child['badge'])) { ?>
                                                    <span
                                                        class="badge tw-ml-auto
        <?= isset($child['badge']['type']) && $child['badge']['type'] != '' ? "bg-{$child['badge']['type']}" : 'bg-info' ?>"
                                                        <?= (isset($child['badge']['type']) && $child['badge']['type'] == '') || isset($child['badge']['color']) ? "style='background-color: {$child['badge']['color']}'" : '' ?>>
                                                        <?= $child['badge']['value'] ?>
                                                    </span>
                                                    <?php } ?>
                                                </a>
                                            </li>
                                            <?php } ?>
                                            <?php if ($sectionId === 'general') { ?>
                                            <li class="settings-group-system-update">
                                                <a href="<?= admin_url('settings?group=update'); ?>"
                                                    class="tw-group tw-flex tw-items-center tw-text-sm hover:tw-text-neutral-800 focus:tw-text-neutral-800 tw-font-medium tw-gap-2.5 <?= ($group['id'] === 'update') ? ' tw-text-neutral-800' : 'tw-text-neutral-600' ?>">
                                                    <i
                                                        class="fa-solid fa-hammer fa-fw fa-lg tw-mr-0.5 group-hover:tw-text-neutral-800 <?= $group['id'] === 'update' ? 'tw-text-neutral-800' : 'tw-text-neutral-500'; ?>"></i>
                                                    <span><?= _l('settings_update'); ?></span>
                                                </a>
                                            </li>

                                            <?php if (is_admin()) { ?>
                                            <li class="settings-group-system-info">
                                                <a href="<?= admin_url('settings?group=info'); ?>"
                                                    class="tw-group tw-flex tw-items-center tw-text-sm hover:tw-text-neutral-800 focus:tw-text-neutral-800 tw-font-medium tw-gap-2.5 <?= ($group['id'] === 'info') ? ' tw-text-neutral-800' : 'tw-text-neutral-600' ?>">
                                                    <i
                                                        class="fa-solid fa-question fa-fw fa-lg tw-mr-0.5 group-hover:tw-text-neutral-800 <?= $group['id'] === 'update' ? 'tw-text-neutral-800' : 'tw-text-neutral-500'; ?>"></i>

                                                    <span>System/Server Info</span>
                                                </a>
                                            </li>
                                            <?php } ?>
                                            <?php } ?>
                                        </ul>
                                    </div>
                                    <?php } ?>

                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8 col-lg-9">
                        <h4 class="tw-font-bold tw-mt-0 tw-text-neutral-800">
                            <?= $group['name']; ?>
                        </h4>
                        <?php
$actionUrl = $group['update_url']
                            ?? $this->uri->uri_string() . '?group=' . $group['id'] . ($this->input->get('tab') ? '&active_tab=' . $this->input->get('tab') : '');

$formAttributes = [
    'id'    => 'settings-form',
    'class' => isset($group['update_url']) ? 'custom-update-url' : '',
];

echo form_open_multipart($actionUrl, $formAttributes);
?>
                        <div class="panel_s">
                            <div class="panel-body">
                                <?php hooks()->do_action('before_settings_group_view', $group); ?>
                                <?php $this->load->view($group['view']) ?>
                                <?php hooks()->do_action('after_settings_group_view', $group); ?>
                            </div>
                            <?php if (($group['without_submit_button'] ?? false) !== true) { ?>
                            <div class="panel-footer text-right">
                                <button type="submit" class="btn btn-primary">
                                    <?= _l('settings_save'); ?>
                                </button>
                            </div>
                            <?php } ?>
                        </div>
                        <?= form_close(); ?>
                        <?php if (($group['id'] ?? '') === 'company') {
                            $this->load->view('admin/settings/includes/organization_company_modal');
                        } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="new_version"></div>
<?php init_tail(); ?>
<script>
    $(function() {
        var settingsForm = $('#settings-form');
        var slug = "<?= e($group['id']); ?>";
        $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
            if (settingsForm.hasClass('custom-update-url')) {
                return;
            }

            var tab = $(this).attr('href').slice(1);
            settingsForm.attr('action',
                '<?= site_url($this->uri->uri_string()); ?>?group=' +
                slug +
                '&active_tab=' + tab);
        });

        settingsForm.on('submit', function() {
            var emailProtocol = $('input[name="settings[email_protocol]"]:checked').val();
            if (emailProtocol === 'microsoft' || emailProtocol === 'google') {
                $('input[name="settings[smtp_password]"]').val('')
                $('input[name="settings[smtp_username]"]').val('')
            }
        });

        $('input[name="settings[mail_engine]"]').on('change', function() {
            if ($(this).val() == 'codeigniter') {
                $('.protocol-microsoft').addClass('hide');
                $('.protocol-google').addClass('hide');

                if ($('input[name="settings[email_protocol]"]:checked').val() == 'microsoft') {
                    $('#smtp').prop('checked', true)
                    $('#microsoft').trigger('change')
                }

                if ($('input[name="settings[email_protocol]"]:checked').val() == 'google') {
                    $('#smtp').prop('checked', true)
                    $('#google').trigger('change')
                }
            } else {
                $('.protocol-microsoft').removeClass('hide');
                $('.protocol-google').removeClass('hide');
            }
        });

        $('input[name="settings[email_protocol]"]').on('change', function() {
            var $inputHost = $('input[name="settings[smtp_host]"]');
            var $inputPort = $('input[name="settings[smtp_port]"]');
            var $selectEnc = $('select[name="settings[smtp_encryption]"]');

            var resetFields = function() {
                if ($selectEnc.hasClass('_modified')) {
                    $selectEnc.selectpicker('val', '');
                    $selectEnc.removeClass('_modified');
                }

                if ($inputPort.hasClass('_modified')) {
                    $inputPort.val('');
                    $inputPort.removeClass('_modified');
                }

                if ($inputHost.hasClass('_modified')) {
                    $inputHost.val('');
                    $inputHost.removeClass('_modified');
                }
            }

            if ($(this).val() == 'mail') {
                $('.xoauth-hide').addClass('hide');
                $('.smtp-fields').addClass('hide');
                $('.xoauth-microsoft-show').addClass('hide');
                $('.xoauth-google-show').addClass('hide');
                resetFields();
            } else if ($(this).val() === 'microsoft' || $(this).val() === 'google') {
                $('.smtp-fields').removeClass('hide');
                $('.xoauth-hide').addClass('hide');
                $('.xoauth-microsoft-show').addClass('hide');
                $('.xoauth-google-show').addClass('hide');

                if ($(this).val() === 'microsoft') {
                    $('.xoauth-microsoft-show').removeClass('hide');
                    if ($inputHost.val() == '') {
                        $inputHost.val('smtp.office365.com')
                        $inputHost.addClass('_modified');
                    }
                }

                if ($(this).val() === 'google') {
                    $('.xoauth-google-show').removeClass('hide');
                    if ($inputHost.val() == '') {
                        $inputHost.val('smtp.gmail.com')
                        $inputHost.addClass('_modified');
                    }
                }

                if ($inputPort.val() == '') {
                    $inputPort.val('587')
                    $inputPort.addClass('_modified');
                    if ($selectEnc.selectpicker('val') == '') {
                        $selectEnc.selectpicker('val', 'tls');
                        $selectEnc.addClass('_modified');
                    }
                }
            } else {
                $('.smtp-fields').removeClass('hide');
                $('.xoauth-hide').removeClass('hide');
                $('.xoauth-microsoft-show').addClass('hide');
                $('.xoauth-google-show').addClass('hide');
                resetFields();
            }
        });

        $('.sms_gateway_active input').on('change', function() {
            if ($(this).val() == '1') {
                $('body .sms_gateway_active').not($(this).parents('.sms_gateway_active')[0]).find(
                    'input[value="0"]').prop('checked', true);
            }
        });

        <?php if ($group['id'] == 'pusher') {
            if (get_option('desktop_notifications') == '1') { ?>
        // Let's check if the browser supports notifications
        if (!("Notification" in window)) {
            $('#pusherHelper').html(
                '<div class="alert alert-danger">Your browser does not support desktop notifications, please disable this option or use more modern browser.</div>'
            );
        } else if (Notification.permission == "denied") {
            $('#pusherHelper').html(
                '<div class="alert alert-danger">Desktop notifications not allowed in browser settings, search on Google "How to allow desktop notifications for <?= $this->agent->browser(); ?>"</div>'
            );
        }
        <?php } ?>
        <?php if (get_option('pusher_realtime_notifications') == '0') { ?>
        $('input[name="settings[desktop_notifications]"]').prop('disabled', true);
        <?php } ?>
        <?php } ?>

        $('input[name="settings[pusher_realtime_notifications]"]').on('change', function() {
            if ($(this).val() == '1') {
                $('input[name="settings[desktop_notifications]"]').prop('disabled', false);
            } else {
                $('input[name="settings[desktop_notifications]"]').prop('disabled', true);
                $('input[name="settings[desktop_notifications]"][value="0"]').prop('checked', true);
            }
        });

        $('.test_email').on('click', function() {
            var email = $('input[name="test_email"]').val();
            if (email != '') {
                $(this).attr('disabled', true);
                $.post(admin_url + 'emails/sent_smtp_test_email', {
                    test_email: email
                }).done(function(data) {
                    window.location.reload();
                });
            }
        });

        $('#update_app').on('click', function(e) {
            e.preventDefault();
            $('input[name="settings[purchase_key]"]').parents('.form-group').removeClass('has-error');
            var purchase_key = $('input[name="settings[purchase_key]"]').val();
            var latest_version = $('input[name="latest_version"]').val();
            var upgrade_function = $('input[name="upgrade_function"]:checked').val();
            var update_errors;
            if (purchase_key != '') {
                var ubtn = $(this);
                ubtn.html(
                    '<?= _l('wait_text'); ?>'
                );
                ubtn.addClass('disabled');
                $.post(admin_url + 'auto_update', {
                    purchase_key: purchase_key,
                    latest_version: latest_version,
                    auto_update: true,
                    upgrade_function: upgrade_function
                }).done(function() {
                    window.location.reload();
                }).fail(function(response) {
                    update_errors = JSON.parse(response.responseText);
                    $('#update_messages').html('<div class="alert alert-danger"></div>');
                    for (var i in update_errors) {
                        $('#update_messages .alert').append('<p>' + update_errors[i] + '</p>');
                    }
                    ubtn.removeClass('disabled');
                    ubtn.html($('.update_app_wrapper').data('original-text'));
                });
            } else {
                $('input[name="settings[purchase_key]"]').parents('.form-group').addClass('has-error');
            }
        });
    });

    $('input[name="settings[reminder_for_completed_but_not_billed_tasks]"]').on('change', function() {
        if ($(this).val() == '1') {
            $('.staff_notify_completed_but_not_billed_tasks_fields').removeClass('hide');
        } else {
            $('.staff_notify_completed_but_not_billed_tasks_fields').addClass('hide');
        }
    });
</script>
<?php if (($group['id'] ?? '') === 'company' && organization_companies_table_exists() && staff_can('edit', 'settings')) { ?>
<script>
var organizationCompanyDefaultFormat = <?= json_encode(clear_textarea_breaks(get_option('company_info_format'))); ?>;

function organization_company_parse_json(response) {
    if (typeof response === 'object') {
        return response;
    }
    try {
        return JSON.parse(response);
    } catch (e) {
        return null;
    }
}

function organization_company_post_data(extra) {
    var data = extra || {};
    if (typeof csrfData !== 'undefined') {
        data[csrfData.token_name] = csrfData.hash;
    }
    return data;
}

function reset_organization_company_form() {
    var $form = $('#organization_company_form');
    $form[0].reset();
    $form.find('input[name="id"]').val('');
    $('#organization_company_is_primary').prop('checked', false).prop('disabled', false);
    $('#organization_company_info_format').val(organizationCompanyDefaultFormat);
    $('#organization_company_modal .add-title').removeClass('hide');
    $('#organization_company_modal .edit-title').addClass('hide');
    $('#organization-company-logo-preview-wrap').addClass('hide');
    $('#organization-company-logo-preview').attr('src', '');
    $('#organization-company-logo-remove-btn').addClass('hide').data('id', '');
    $('#organization_company_logo').val('');
    $('#organization-company-custom-fields').find('input, textarea, select').each(function() {
        if ($(this).is(':checkbox') || $(this).is(':radio')) {
            $(this).prop('checked', false);
        } else if ($(this).hasClass('selectpicker')) {
            $(this).selectpicker('val', '');
        } else {
            $(this).val('');
        }
    });
}

function organization_company_make_custom_fields_optional() {
    var $container = $('#organization-company-custom-fields');
    $container.find('[data-custom-field-required]').removeAttr('data-custom-field-required');
    $container.find('label .req').remove();
}

function load_organization_company_custom_fields(companyId, callback) {
    $.get(admin_url + 'organization_companies/custom_fields_html/' + companyId)
        .done(function(html) {
            $('#organization-company-custom-fields').html(html);
            organization_company_make_custom_fields_optional();
            if (typeof init_selectpicker === 'function') {
                init_selectpicker();
            }
            if (typeof init_tags_inputs === 'function') {
                init_tags_inputs();
            }
            if (typeof callback === 'function') {
                callback();
            }
        })
        .fail(function() {
            if (typeof callback === 'function') {
                callback();
            }
        });
}

function organization_company_find_custom_field_input(fieldId) {
    var $container = $('#organization-company-custom-fields');
    var $input = $container.find('[data-fieldid="' + fieldId + '"][data-fieldto="company"]');
    if ($input.length === 0) {
        $input = $container.find('[name="custom_fields[company][' + fieldId + ']"]');
    }
    if ($input.length === 0) {
        $input = $container.find('[name="custom_fields[company][' + fieldId + '][]"]');
    }
    return $input;
}

function organization_company_decode_custom_field_value(value) {
    if (typeof value !== 'string') {
        return value;
    }
    return value.replace(/<br\s*\/?>/gi, '\n');
}

function fill_organization_company_custom_fields(values) {
    if (!values) {
        return;
    }
    $.each(values, function(fieldId, value) {
        if (value === null || value === undefined) {
            return;
        }
        var $input = organization_company_find_custom_field_input(fieldId);
        if ($input.length === 0) {
            return;
        }
        if (value === '' && $input.val() !== '') {
            return;
        }
        value = organization_company_decode_custom_field_value(value);
        if ($input.is(':checkbox')) {
            $input.prop('checked', false);
            if (value) {
                var selected = value.toString().split(',');
                $input.each(function() {
                    if (selected.indexOf($(this).val()) !== -1) {
                        $(this).prop('checked', true);
                    }
                });
            }
        } else if ($input.hasClass('selectpicker')) {
            $input.selectpicker('val', value);
            $input.selectpicker('refresh');
        } else {
            $input.val(value);
        }
    });
}

function fill_organization_company_form(c) {
    var $form = $('#organization_company_form');
    $form.find('input[name="id"]').val(c.id);
    $form.find('input[name="name"]').val(c.name || '');
    $form.find('[name="address"]').val(c.address || '');
    $form.find('input[name="city"]').val(c.city || '');
    $form.find('input[name="state"]').val(c.state || '');
    $form.find('input[name="country_code"]').val(c.country_code || '');
    $form.find('input[name="zip_code"]').val(c.zip_code || '');
    $form.find('input[name="phone"]').val(c.phone || '');
    $form.find('input[name="email"]').val(c.email || '');
    $form.find('input[name="vat"]').val(c.vat || '');
    $form.find('input[name="gst"]').val(c.gst || '');
    if (c.logo_url) {
        $('#organization-company-logo-preview').attr('src', c.logo_url);
        $('#organization-company-logo-preview-wrap').removeClass('hide');
        $('#organization-company-logo-remove-btn').removeClass('hide').data('id', c.id);
    } else {
        $('#organization-company-logo-preview-wrap').addClass('hide');
        $('#organization-company-logo-preview').attr('src', '');
        $('#organization-company-logo-remove-btn').addClass('hide').data('id', c.id || '');
    }
    $('#organization_company_info_format').val(c.company_info_format || organizationCompanyDefaultFormat);
    $('#organization_company_is_primary').prop('checked', c.is_primary === 1);
    if (c.is_primary === 1) {
        $('#organization_company_is_primary').prop('disabled', true);
    } else {
        $('#organization_company_is_primary').prop('disabled', false);
    }
}

function manage_organization_company(form) {
    $('#organization_company_is_primary').prop('disabled', false);
    var formData = new FormData(form);
    if (typeof csrfData !== 'undefined') {
        formData.set(csrfData.token_name, csrfData.hash);
    }
    $.ajax({
        url: form.action,
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        dataType: 'json'
    }).done(function(response) {
        response = organization_company_parse_json(response);
        if (response && response.success) {
            alert_float('success', response.message);
            $('#organization_company_modal').modal('hide');
            window.location.reload();
        } else if (response) {
            alert_float('danger', response.message);
        } else {
            alert_float('danger', '<?= _l('organization_company_save_failed'); ?>');
        }
    }).fail(function(xhr) {
        var message = '<?= _l('organization_company_save_failed'); ?>';
        if (xhr.responseJSON && xhr.responseJSON.message) {
            message = xhr.responseJSON.message;
        }
        alert_float('danger', message);
    });
    return false;
}

$(function() {
    if ($('#organization_company_form').length === 0) {
        return;
    }

    organization_company_make_custom_fields_optional();

    appValidateForm($('#organization_company_form'), {
        name: 'required'
    }, manage_organization_company);

    $('#organization-company-add-btn').on('click', function(e) {
        e.preventDefault();
        reset_organization_company_form();
        load_organization_company_custom_fields(0, function() {
            $('#organization_company_modal').modal('show');
        });
    });

    $('body').on('click', '.organization-company-edit', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var id = $(this).data('id');
        $.get(admin_url + 'organization_companies/get/' + id, null, null, 'json').done(function(response) {
            response = organization_company_parse_json(response);
            if (!response || !response.success) {
                alert_float('danger', '<?= _l('organization_company_load_failed'); ?>');
                return;
            }
            $('#organization_company_modal .add-title').addClass('hide');
            $('#organization_company_modal .edit-title').removeClass('hide');
            fill_organization_company_form(response.company);
            load_organization_company_custom_fields(response.company.id, function() {
                fill_organization_company_custom_fields(response.company.custom_fields);
                $('#organization_company_modal').modal('show');
            });
        }).fail(function() {
            alert_float('danger', '<?= _l('organization_company_load_failed'); ?>');
        });
    });

    $('body').on('click', '.organization-company-delete', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var id = $(this).data('id');
        var name = $(this).data('name') || '';
        var message = '<?= _l('organization_company_delete_confirm'); ?>';
        if (name) {
            message += '\n\n' + name;
        }
        if (!confirm(message)) {
            return;
        }
        $.post(admin_url + 'organization_companies/delete/' + id, organization_company_post_data(), null, 'json')
            .done(function(response) {
                response = organization_company_parse_json(response);
                if (response && response.success) {
                    alert_float('success', response.message);
                    window.location.reload();
                } else if (response) {
                    alert_float('danger', response.message);
                } else {
                    alert_float('danger', '<?= _l('problem_deleting', _l('organization_company')); ?>');
                }
            }).fail(function() {
                alert_float('danger', '<?= _l('problem_deleting', _l('organization_company')); ?>');
            });
    });

    $('#organization_company_logo').on('change', function() {
        var file = this.files && this.files[0];
        if (!file) {
            return;
        }
        var reader = new FileReader();
        reader.onload = function(e) {
            $('#organization-company-logo-preview').attr('src', e.target.result);
            $('#organization-company-logo-preview-wrap').removeClass('hide');
            $('#organization-company-logo-remove-btn').addClass('hide');
        };
        reader.readAsDataURL(file);
    });

    $('#organization-company-logo-remove-btn').on('click', function(e) {
        e.preventDefault();
        var companyId = $(this).data('id');
        if (!companyId) {
            return;
        }
        if (!confirm('<?= _l('settings_general_company_remove_logo_tooltip'); ?>')) {
            return;
        }
        $.post(admin_url + 'organization_companies/remove_logo/' + companyId, organization_company_post_data(), null, 'json')
            .done(function(response) {
                response = organization_company_parse_json(response);
                if (response && response.success) {
                    $('#organization-company-logo-preview-wrap').addClass('hide');
                    $('#organization-company-logo-preview').attr('src', '');
                    $('#organization-company-logo-remove-btn').addClass('hide');
                    $('#organization_company_logo').val('');
                    alert_float('success', response.message);
                } else {
                    alert_float('danger', response && response.message ? response.message : '<?= _l('problem_deleting', _l('settings_general_company_logo')); ?>');
                }
            }).fail(function() {
                alert_float('danger', '<?= _l('problem_deleting', _l('settings_general_company_logo')); ?>');
            });
    });
});
</script>
<?php } ?>
<?php hooks()->do_action('settings_group_end', $group); ?>
</body>

</html>