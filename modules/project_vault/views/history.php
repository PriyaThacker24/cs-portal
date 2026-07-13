<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if (empty($history)) { ?>
<p class="text-muted no-margin"><?= _l('project_vault_no_history'); ?></p>
<?php } else { ?>
<div class="activity-feed">
    <?php foreach ($history as $row) {
        $changes = $row['field_changes'] ? json_decode($row['field_changes'], true) : [];
        // Build the human detail line: entry title + any changed fields.
        $detail = e($row['entry_title']);
        if (!empty($changes)) {
            $labelled = [];
            foreach ($changes as $key => $val) {
                $labelled[] = is_int($key) ? _l('project_vault_' . $val) : _l('project_vault_' . $key) . ': ' . $val;
            }
            $detail .= ' &middot; ' . e(implode(', ', $labelled));
        }
        $action_label = _l('project_vault_action_' . $row['action']);
    ?>
    <div class="feed-item">
        <div class="date">
            <span class="text-has-action" data-toggle="tooltip" data-title="<?= e(_dt($row['dateadded'])); ?>">
                <?= e(time_ago($row['dateadded'])); ?>
            </span>
        </div>
        <div class="text">
            <?php if (!empty($row['staff_id'])) { ?>
            <a href="<?= admin_url('profile/' . $row['staff_id']); ?>">
                <?= staff_profile_image($row['staff_id'], ['staff-profile-xs-image', 'pull-left mright10']); ?>
            </a>
            <?php } ?>
            <p class="tw-mb-0 tw-mt-2.5">
                <?= e($row['staff_name'] ?: '—') . ' - <b>' . e($action_label) . '</b>'; ?>
            </p>
            <p class="tw-mb-0 text-muted mleft30 tw-mt-1">
                <?= $detail; ?>
            </p>
        </div>
    </div>
    <?php } ?>
</div>
<?php } ?>
