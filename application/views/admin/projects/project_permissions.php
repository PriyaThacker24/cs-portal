<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="panel_s">
    <div class="panel-body">
        <div class="tw-mb-4">
            <h4 class="tw-font-semibold tw-text-neutral-700 tw-text-base tw-my-0">
                <?= _l('project_permissions'); ?>
            </h4>
            <p class="tw-text-neutral-500 tw-text-sm tw-mt-1 tw-mb-0">
                <?= _l('project_permissions_description'); ?>
            </p>
        </div>

        <?php
        // Check if user can manage permissions
        $is_project_admin = ($project->addedfrom == get_staff_user_id());
        $can_manage_permissions = $is_project_admin || staff_can('edit', 'projects') || staff_can('create', 'projects');
        
        // Define all available permissions
        $all_permissions = [
            'project_edit' => 'Edit Project',
            'task_create' => 'Create Tasks',
            'task_edit' => 'Edit Tasks',
            'task_delete' => 'Delete Tasks',
            'log_create' => 'Create Logs',
            'log_edit' => 'Edit Logs',
            'log_delete' => 'Delete Logs',
            'project_log_approve' => 'Approve Logs',
            'project_log_reject' => 'Reject Logs',
        ];
        
        // Group permissions by category
        $project_perms = ['project_edit', 'project_log_approve', 'project_log_reject'];
        $task_perms = ['task_create', 'task_edit', 'task_delete'];
        $log_perms = ['log_create', 'log_edit', 'log_delete'];
        ?>

        <?php if ($can_manage_permissions): ?>
        
        <div class="tw-space-y-4">
            <!-- Add New Member Section -->
            <!-- <div class="tw-border tw-border-solid tw-border-neutral-300 tw-border-dashed tw-rounded-lg tw-p-4 tw-bg-neutral-50">
                <h5 class="tw-font-semibold tw-text-neutral-700 tw-mb-3">
                    <i class="fa-solid fa-user-plus tw-mr-2"></i>
                    <?= _l('add_new_member'); ?>
                </h5>
                <div id="add-member-form" style="display: none;">
                    <div class="tw-space-y-3">
                        <div>
                            <label class="tw-block tw-text-sm tw-font-medium tw-text-neutral-700 tw-mb-2">
                                <?= _l('select_staff_member'); ?>
                            </label>
                            <?php
                            // Get existing member IDs to exclude from the select
                            $existing_member_ids = [];
                            foreach ($members as $member) {
                                $existing_member_ids[] = $member['staff_id'];
                            }
                            // Also exclude project admin
                            $existing_member_ids[] = $project->addedfrom;
                            
                            // Filter staff to exclude existing members
                            $available_staff = [];
                            if (isset($staff) && is_array($staff)) {
                                foreach ($staff as $staff_member) {
                                    if (!in_array($staff_member['staffid'], $existing_member_ids)) {
                                        $available_staff[] = $staff_member;
                                    }
                                }
                            }
                            
                            if (count($available_staff) > 0):
                            ?>
                                <?= render_select('new_member_staff_id', $available_staff, ['staffid', ['firstname', 'lastname']], 'staff_member', '', ['id' => 'new_member_select'], [], '', '', false); ?>
                                <div id="new-member-permissions" class="tw-mt-4" style="display: none;">
                                    <label class="tw-block tw-text-sm tw-font-medium tw-text-neutral-700 tw-mb-2">
                                        <?= _l('permissions'); ?>
                                    </label>
                                    <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
                                        <?php 
                                        $all_perms_combined = array_merge($project_perms, $task_perms, $log_perms);
                                        foreach ($all_perms_combined as $perm_key): 
                                            $perm_label = $all_permissions[$perm_key];
                                            
                                            // Determine category for styling
                                            $checked_classes = '';
                                            $unchecked_classes = 'tw-text-neutral-500';
                                            if (in_array($perm_key, $project_perms)) {
                                                $checked_classes = 'tw-text-blue-700';
                                            } elseif (in_array($perm_key, $task_perms)) {
                                                $checked_classes = 'tw-text-purple-700';
                                            } else {
                                                $checked_classes = 'tw-text-green-700';
                                            }
                                        ?>
                                            <label class="permission-badge tw-inline-flex tw-items-center tw-cursor-pointer tw-px-2 tw-py-1.5 tw-transition-all tw-duration-200 hover:tw-opacity-80 tw-bg-transparent <?= $unchecked_classes; ?>" 
                                                   data-checked-classes="<?= e($checked_classes); ?> tw-font-semibold"
                                                   data-unchecked-classes="<?= e($unchecked_classes); ?>">
                                                <input type="checkbox" 
                                                    name="new_member_permissions[]" 
                                                    value="<?= e($perm_key); ?>"
                                                    class="tw-mr-3 tw-cursor-pointer tw-w-4 tw-h-4 tw-flex-shrink-0"
                                                    style="margin-top: 0; vertical-align: middle;"
                                                    onchange="
                                                        var label = this.parentElement;
                                                        var checkedClasses = label.getAttribute('data-checked-classes');
                                                        var uncheckedClasses = label.getAttribute('data-unchecked-classes');
                                                        if (this.checked) {
                                                            label.className = 'permission-badge tw-inline-flex tw-items-center tw-cursor-pointer tw-px-2 tw-py-1.5 tw-transition-all tw-duration-200 hover:tw-opacity-80 tw-bg-transparent ' + checkedClasses;
                                                        } else {
                                                            label.className = 'permission-badge tw-inline-flex tw-items-center tw-cursor-pointer tw-px-2 tw-py-1.5 tw-transition-all tw-duration-200 hover:tw-opacity-80 tw-bg-transparent ' + uncheckedClasses;
                                                        }
                                                    ">
                                                <span class="tw-text-xs tw-font-medium tw-leading-normal tw-flex tw-items-center">
                                                    <?= e($perm_label); ?>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="tw-flex tw-justify-end tw-gap-2 tw-mt-4">
                                    <button type="button" class="btn btn-default" onclick="cancelAddMember()">
                                        <?= _l('cancel'); ?>
                                    </button>
                                    <button type="button" class="btn btn-primary" onclick="addNewMember()">
                                        <i class="fa-solid fa-plus tw-mr-1"></i>
                                        <?= _l('add_member'); ?>
                                    </button>
                                </div>
                            <?php else: ?>
                                <p class="tw-text-sm tw-text-neutral-500 tw-mb-0">
                                    <?= _l('all_staff_already_members'); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-default btn-sm" id="show-add-member-btn" onclick="showAddMemberForm()">
                    <i class="fa-solid fa-user-plus tw-mr-1"></i>
                    <?= _l('add_member'); ?>
                </button>
            </div> -->
            
            <?= form_open(admin_url('projects/add_edit_members/' . $project->id), ['id' => 'permissions-form']); ?>
            
            <!-- Project Admin Section (View Only) -->
            <div class="tw-border tw-border-solid tw-border-neutral-300 tw-rounded-lg tw-p-4 tw-bg-neutral-50 tw-mb-4">
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-2">
                    <div class="tw-flex tw-items-center">
                        <?= staff_profile_image($project->addedfrom, ['tw-inline-block tw-h-10 tw-w-10 tw-rounded-full', '']); ?>
                        <div class="tw-ml-3">
                            <h5 class="tw-font-semibold tw-text-neutral-700 tw-mb-0">
                                <?= e(get_staff_full_name($project->addedfrom)); ?>
                            </h5>
                            <p class="tw-text-sm tw-text-neutral-500 tw-mb-0">
                                <?= _l('project_admin'); ?>
                            </p>
                        </div>
                    </div>
                    <span class="tw-inline-flex tw-items-center tw-px-3 tw-py-1 tw-rounded-full tw-text-xs tw-font-medium tw-bg-green-100 tw-text-green-800">
                        <i class="fa-solid fa-shield-halved tw-mr-1"></i>
                        <?= _l('full_access'); ?>
                    </span>
                </div>
                <p class="tw-text-sm tw-text-neutral-600 tw-mt-2 tw-mb-0">
                    <?= _l('project_admin_full_access_description'); ?>
                </p>
            </div>

            <!-- Project Members Permissions -->
            <?php if (count($members) > 0): ?>
                <div class="tw-space-y-4">
                    <?php foreach ($members as $member): ?>
                        <?php
                        // Skip project admin from the list
                        if ($member['staff_id'] == $project->addedfrom) {
                            continue;
                        }
                        
                        $member_permissions = isset($member['permissions']) && is_array($member['permissions']) ? $member['permissions'] : [];
                        ?>
                        <div class="tw-border tw-border-solid tw-border-neutral-300 tw-rounded-lg tw-p-4 tw-bg-white hover:tw-shadow-md tw-transition-shadow tw-mb-4">
                            <div class="tw-flex tw-items-center tw-justify-between tw-mb-3">
                                <div class="tw-flex tw-items-center">
                                    <?= staff_profile_image($member['staff_id'], ['tw-inline-block tw-h-10 tw-w-10 tw-rounded-full', '']); ?>
                                    <div class="tw-ml-3">
                                        <h5 class="tw-font-semibold tw-text-neutral-700 tw-mb-0">
                                            <?= e(get_staff_full_name($member['staff_id'])); ?>
                                        </h5>
                                        <p class="tw-text-sm tw-text-neutral-500 tw-mb-0">
                                            <?= e($member['email']); ?>
                                        </p>
                                    </div>
                                </div>
                                <input type="hidden" name="project_members[]" value="<?= e($member['staff_id']); ?>">
                            </div>

                            <!-- All Permissions in One Line -->
                            <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2 tw-pt-3 tw-border-t tw-border-neutral-200">
                                <?php 
                                $all_perms_combined = array_merge($project_perms, $task_perms, $log_perms);
                                foreach ($all_perms_combined as $perm_key): 
                                    $is_checked = in_array($perm_key, $member_permissions);
                                    $perm_label = $all_permissions[$perm_key];
                                    
                                    // Determine category for styling
                                    $checked_classes = '';
                                    $unchecked_classes = 'tw-text-neutral-500';
                                    if (in_array($perm_key, $project_perms)) {
                                        $checked_classes = 'tw-text-blue-700';
                                    } elseif (in_array($perm_key, $task_perms)) {
                                        $checked_classes = 'tw-text-purple-700';
                                    } else {
                                        $checked_classes = 'tw-text-green-700';
                                    }
                                ?>
                                    <label class="permission-badge tw-inline-flex tw-items-center tw-cursor-pointer tw-px-2 tw-py-1.5 tw-transition-all tw-duration-200 hover:tw-opacity-80 tw-bg-transparent tw-gap-1 <?= $is_checked ? $checked_classes . ' tw-font-semibold' : $unchecked_classes; ?>" 
                                           data-checked-classes="<?= e($checked_classes); ?> tw-font-semibold"
                                           data-unchecked-classes="<?= e($unchecked_classes); ?>">
                                        <input type="checkbox" 
                                            name="project_members_permissions[<?= e($member['staff_id']); ?>][]" 
                                            value="<?= e($perm_key); ?>"
                                            <?= $is_checked ? 'checked' : ''; ?>
                                            class="tw-mr-3 tw-cursor-pointer tw-w-4 tw-h-4 tw-flex-shrink-0"
                                            style="margin-top: 0; vertical-align: middle;"
                                            onchange="
                                                var label = this.parentElement;
                                                var checkedClasses = label.getAttribute('data-checked-classes');
                                                var uncheckedClasses = label.getAttribute('data-unchecked-classes');
                                                if (this.checked) {
                                                    label.className = 'permission-badge tw-inline-flex tw-items-center tw-cursor-pointer tw-px-2 tw-py-1.5 tw-transition-all tw-duration-200 hover:tw-opacity-80 tw-bg-transparent ' + checkedClasses;
                                                } else {
                                                    label.className = 'permission-badge tw-inline-flex tw-items-center tw-cursor-pointer tw-px-2 tw-py-1.5 tw-transition-all tw-duration-200 hover:tw-opacity-80 tw-bg-transparent ' + uncheckedClasses;
                                                }
                                            ">
                                        <span class="tw-text-xs tw-font-medium tw-leading-normal tw-flex tw-items-center tw-gap-1">
                                            <?= e($perm_label); ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="tw-border tw-border-solid tw-border-neutral-300 tw-rounded-lg tw-p-6 tw-text-center tw-bg-neutral-50">
                    <i class="fa-regular fa-user tw-text-4xl tw-text-neutral-400 tw-mb-3"></i>
                    <p class="tw-text-neutral-600 tw-mb-0">
                        <?= _l('no_project_members'); ?>
                    </p>
                    <p class="tw-text-sm tw-text-neutral-500 tw-mt-2 tw-mb-0">
                        <?= _l('add_members_to_assign_permissions'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Save Button -->
            <div class="tw-flex tw-justify-end tw-mt-6">
                <button type="submit" class="btn btn-primary" id="save-permissions-btn">
                    <i class="fa-regular fa-save tw-mr-1"></i>
                    <?= _l('save_permissions'); ?>
                </button>
            </div>
        </div>

        <?= form_close(); ?>
        
        <!-- Success/Error Message Container -->
        <div id="permissions-message" class="tw-mt-4" style="display: none;"></div>

        <?php else: ?>
            <div class="tw-border tw-border-solid tw-border-neutral-300 tw-rounded-lg tw-p-6 tw-text-center tw-bg-neutral-50">
                <i class="fa-solid fa-lock tw-text-4xl tw-text-neutral-400 tw-mb-3"></i>
                <p class="tw-text-neutral-600 tw-mb-0">
                    <?= _l('access_denied'); ?>
                </p>
                <p class="tw-text-sm tw-text-neutral-500 tw-mt-2 tw-mb-0">
                    <?= _l('only_project_admin_can_manage_permissions'); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function showAddMemberForm() {
    document.getElementById('add-member-form').style.display = 'block';
    document.getElementById('show-add-member-btn').style.display = 'none';
    // Initialize selectpicker if available
    if (typeof $ !== 'undefined' && $('#new_member_select').length) {
        $('#new_member_select').selectpicker();
        $('#new_member_select').on('changed.bs.select', function() {
            var selectedValue = $(this).val();
            if (selectedValue && selectedValue !== '') {
                document.getElementById('new-member-permissions').style.display = 'block';
            } else {
                document.getElementById('new-member-permissions').style.display = 'none';
            }
        });
    }
}

function cancelAddMember() {
    document.getElementById('add-member-form').style.display = 'none';
    document.getElementById('show-add-member-btn').style.display = 'inline-block';
    document.getElementById('new-member-permissions').style.display = 'none';
    // Reset form
    if (typeof $ !== 'undefined' && $('#new_member_select').length) {
        $('#new_member_select').selectpicker('val', '');
    }
    $('#add-member-form input[type="checkbox"]').prop('checked', false);
}

function addNewMember() {
    var $select = $('#new_member_select');
    var staffId = '';
    
    // Get value from Bootstrap Select if available
    if ($select.hasClass('selectpicker') && typeof $select.selectpicker !== 'undefined') {
        staffId = $select.selectpicker('val');
    } else {
        staffId = $select.val();
    }
    
    if (!staffId || staffId === '') {
        alert('<?= _l('please_select_staff_member'); ?>');
        return;
    }
    
    // Get selected permissions
    var permissions = [];
    $('#add-member-form input[name="new_member_permissions[]"]:checked').each(function() {
        permissions.push($(this).val());
    });
    
    // Submit via AJAX
    $.ajax({
        url: '<?= admin_url('projects/add_project_member/' . $project->id); ?>',
        type: 'POST',
        data: {
            staff_id: staffId,
            permissions: permissions
        },
        success: function(response) {
            var result = typeof response === 'string' ? JSON.parse(response) : response;
            if (result.success) {
                alert(result.message);
                location.reload();
            } else {
                alert(result.message);
            }
        },
        error: function() {
            alert('<?= _l('error_occurred'); ?>');
        }
    });
}

// Handle permissions form submission via AJAX
$(document).ready(function() {
    $('#permissions-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $btn = $('#save-permissions-btn');
        var originalText = $btn.html();
        
        // Disable button and show loading
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin tw-mr-1"></i> <?= _l('saving'); ?>...');
        
        // Hide previous messages
        $('#permissions-message').hide();
        
        // Ensure all existing members are included with their permissions
        // Collect all form data properly
        var members = [];
        var membersPermissions = {};
        
        // Get all project_members[] values (existing members)
        $form.find('input[name="project_members[]"]').each(function() {
            var memberId = $(this).val();
            if (memberId && memberId !== '') {
                members.push(memberId);
                // Initialize permissions array for this member
                membersPermissions[memberId] = [];
            }
        });
        
        // Get all checked permissions for each member
        // This ensures we capture ALL checked permissions, including pre-checked ones
        $form.find('input[name^="project_members_permissions["]').each(function() {
            if ($(this).is(':checked')) {
                var name = $(this).attr('name');
                var match = name.match(/project_members_permissions\[(\d+)\]/);
                if (match) {
                    var memberId = match[1];
                    var permValue = $(this).val();
                    // Ensure member is in the members array
                    if (members.indexOf(memberId) === -1) {
                        members.push(memberId);
                    }
                    // Initialize permissions array if not exists
                    if (!membersPermissions[memberId]) {
                        membersPermissions[memberId] = [];
                    }
                    membersPermissions[memberId].push(permValue);
                }
            }
        });
        
        // Build form data using jQuery's param for proper encoding
        var formDataArray = [];
        
        // Add all members
        members.forEach(function(memberId) {
            formDataArray.push({name: 'project_members[]', value: memberId});
        });
        
        // Add permissions for each member
        // IMPORTANT: Always send permissions data for ALL members in the form
        // This ensures backend knows which members to update
        members.forEach(function(memberId) {
            var perms = membersPermissions[memberId] || [];
            if (perms.length > 0) {
                // Send checked permissions
                perms.forEach(function(perm) {
                    formDataArray.push({
                        name: 'project_members_permissions[' + memberId + '][]',
                        value: perm
                    });
                });
            }
            // If member has no checked permissions, we still need to indicate this
            // Send a special marker to indicate "explicitly empty" vs "not provided"
            // We'll use an empty value to indicate all permissions were unchecked
            if (perms.length === 0) {
                // Send empty array indicator - backend will treat this as "set to empty"
                formDataArray.push({
                    name: 'project_members_permissions[' + memberId + '][]',
                    value: ''
                });
            }
        });
        
        // Add AJAX flag
        formDataArray.push({name: 'ajax_request', value: '1'});
        
        // Get CSRF token from form
        $form.find('input[type="hidden"]').each(function() {
            var name = $(this).attr('name');
            var value = $(this).val();
            if (name && value) {
                formDataArray.push({name: name, value: value});
            }
        });
        
        // Convert to URL-encoded string
        var formDataString = $.param(formDataArray);
        
        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: formDataString,
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                var result = typeof response === 'string' ? JSON.parse(response) : response;
                
                if (result.success) {
                    showMessage(result.message, 'success');
                    // Reload page after short delay to show updated permissions
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showMessage(result.message || '<?= _l('failed_to_save_permissions'); ?>', 'error');
                    $btn.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                // Try to parse response if it's JSON
                var response = xhr.responseText;
                try {
                    var result = JSON.parse(response);
                    if (result.success) {
                        showMessage(result.message, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showMessage(result.message || '<?= _l('error_occurred'); ?>', 'error');
                        $btn.prop('disabled', false).html(originalText);
                    }
                } catch (e) {
                    // If not JSON, it might be a redirect or HTML response
                    // In that case, just reload the page
                    showMessage('<?= _l('permissions_saved_successfully'); ?>', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                }
            }
        });
        
        return false;
    });
});

// Function to show success/error messages
function showMessage(message, type) {
    var $messageDiv = $('#permissions-message');
    var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    var icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    $messageDiv.html(
        '<div class="alert ' + alertClass + ' alert-dismissible" role="alert">' +
        '<i class="fa ' + icon + ' tw-mr-2"></i>' +
        '<span>' + message + '</span>' +
        '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
        '<span aria-hidden="true">&times;</span>' +
        '</button>' +
        '</div>'
    );
    $messageDiv.slideDown();
    
    // Scroll to message
    $('html, body').animate({
        scrollTop: $messageDiv.offset().top - 100
    }, 300);
    
    // Auto-hide after 5 seconds (only for success messages)
    if (type === 'success') {
        setTimeout(function() {
            $messageDiv.slideUp();
        }, 5000);
    }
}
</script>

