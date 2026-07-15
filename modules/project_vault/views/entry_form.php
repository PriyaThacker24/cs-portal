<?php defined('BASEPATH') or exit('No direct script access allowed');
$is_edit = $entry !== null;
$current_type = $is_edit ? $entry->type : 'web_account';
?>
<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <h4 class="modal-title"><?= $is_edit ? _l('project_vault_edit_entry') : _l('project_vault_new_entry'); ?></h4>
</div>
<?= form_open_multipart(admin_url('project_vault/save/' . $project_id), [
    'id'                => 'vault-entry-form',
    'data-has-password' => $is_edit && $entry->has_password ? 1 : 0,
    'data-has-files'    => $is_edit && !empty($entry->files) ? 1 : 0,
]); ?>
<div class="modal-body">
    <input type="hidden" name="id" value="<?= $is_edit ? e($entry->id) : ''; ?>">

    <!-- Category first: drives which fields below are shown -->
    <div class="form-group">
        <label for="type" class="control-label"><?= _l('project_vault_category'); ?></label>
        <select name="type" id="vault-category" class="form-control">
            <?php foreach ([
                'file_storage' => _l('project_vault_type_file_storage'),
                'secure_note'  => _l('project_vault_type_secure_note'),
                'web_account'  => _l('project_vault_type_web_account'),
            ] as $value => $label) { ?>
            <option value="<?= $value; ?>" <?= $current_type === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
            <?php } ?>
        </select>
    </div>

    <!-- Name: always shown & always required. No native "required" attribute
         so validation is handled by our JS toast, consistent with the others. -->
    <div class="form-group">
        <label for="title" class="control-label"><?= _l('project_vault_name'); ?> <span class="text-danger">*</span></label>
        <input type="text" name="title" id="title" class="form-control" value="<?= $is_edit ? e($entry->title) : ''; ?>">
    </div>

    <!-- Web Account only -->
    <div class="vault-cat-field" data-cat="web_account">
        <?= render_input('username', 'project_vault_username', $is_edit ? $entry->username : '', 'text'); ?>

        <div class="form-group">
            <label for="vault-password" class="control-label"><?= _l('project_vault_secret'); ?> <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="password" name="password" id="vault-password" class="form-control" autocomplete="new-password"
                    placeholder="<?= $is_edit ? _l('project_vault_secret_keep_placeholder') : ''; ?>">
                <span class="input-group-addon vault-toggle-password" style="cursor:pointer;"><i class="fa-regular fa-eye"></i></span>
            </div>
            <?php if ($is_edit && $entry->has_password) { ?>
            <span class="help-block"><?= _l('project_vault_secret_leave_blank'); ?></span>
            <?php } ?>
        </div>

        <?= render_input('url', 'project_vault_url', $is_edit ? $entry->url : '', 'text'); ?>
    </div>

    <!-- File Storage only -->
    <div class="vault-cat-field" data-cat="file_storage">
        <div class="form-group">
            <label class="control-label"><?= _l('project_vault_attachments'); ?> <span class="text-danger">*</span></label>
            <input type="file" name="attachments[]" class="form-control" multiple>
        </div>

        <?php if ($is_edit && !empty($entry->files)) { ?>
        <div class="form-group">
            <label class="control-label"><?= _l('project_vault_existing_files'); ?></label>
            <ul class="list-unstyled">
                <?php foreach ($entry->files as $file) { ?>
                <li class="tw-flex tw-items-center tw-space-x-2 tw-mb-1">
                    <a href="<?= admin_url('project_vault/download/' . $project_id . '/' . $file['id']); ?>">
                        <i class="fa-regular fa-file tw-mr-1"></i><?= e($file['original_file_name']); ?>
                    </a>
                    <a href="#" class="text-danger vault-delete-file" data-file="<?= e($file['id']); ?>" title="<?= _l('delete'); ?>">
                        <i class="fa-regular fa-trash-can"></i>
                    </a>
                </li>
                <?php } ?>
            </ul>
        </div>
        <?php } ?>
    </div>

    <!-- Notes: always shown; required only for Secure Note, so the asterisk
         is toggled by category in JS (initial state matches current type). -->
    <div class="form-group">
        <label for="notes" class="control-label">
            <?= _l('project_vault_notes'); ?>
            <span class="text-danger vault-req-notes"<?= $current_type === 'secure_note' ? '' : ' style="display:none;"'; ?>>*</span>
        </label>
        <textarea name="notes" id="notes" class="form-control" rows="4"><?= $is_edit ? e($entry->notes) : ''; ?></textarea>
    </div>

    <!-- Share with project members (admins excluded; they see everything) -->
    <?php $shared_ids = $is_edit ? $entry->shared_ids : []; ?>
    <div class="form-group">
        <label for="vault-shared-with" class="control-label"><?= _l('project_vault_share_with'); ?></label>
        <?php if (empty($members)) { ?>
        <p class="text-muted tw-mb-0"><?= _l('project_vault_no_members'); ?></p>
        <?php } else { ?>
        <select name="shared_with[]" id="vault-shared-with" class="selectpicker" multiple data-live-search="true"
            data-width="100%" data-none-selected-text="<?= _l('project_vault_share_none'); ?>"
            title="<?= _l('project_vault_share_none'); ?>">
            <?php foreach ($members as $member) { ?>
            <option value="<?= (int) $member['staff_id']; ?>" <?= in_array((int) $member['staff_id'], $shared_ids, true) ? 'selected' : ''; ?>>
                <?= e($member['name']); ?>
            </option>
            <?php } ?>
        </select>
        <span class="help-block tw-mt-1"><?= _l('project_vault_share_help'); ?></span>
        <?php } ?>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
    <button type="submit" class="btn btn-primary"><?= _l('submit'); ?></button>
</div>
<?= form_close(); ?>
