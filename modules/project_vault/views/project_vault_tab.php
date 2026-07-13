<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Rendered inside the Projects controller's project view.
 * $project is available from that controller. Load vault data here.
 */
$this->load->model('project_vault/project_vault_model');
$vault_entries = $this->project_vault_model->get_entries($project->id);

$cat_meta = [
    'web_account'  => ['icon' => 'fa-solid fa-globe',        'label' => _l('project_vault_type_web_account'),  'class' => 'vault-badge-web'],
    'file_storage' => ['icon' => 'fa-solid fa-folder-open',  'label' => _l('project_vault_type_file_storage'), 'class' => 'vault-badge-file'],
    'secure_note'  => ['icon' => 'fa-solid fa-note-sticky',  'label' => _l('project_vault_type_secure_note'),  'class' => 'vault-badge-note'],
];
?>

<div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
    <h4 class="tw-mt-0 tw-mb-0 tw-font-bold tw-text-lg tw-text-neutral-700">
        <i class="fa-solid fa-lock tw-mr-1"></i>
        <?= _l('project_vault'); ?>
    </h4>
    <div>
        <button type="button" class="btn btn-default" id="vault-history-btn">
            <i class="fa-solid fa-timeline tw-mr-1"></i>
            <?= _l('project_vault_history'); ?>
        </button>
        <button type="button" class="btn btn-primary" id="vault-add-btn">
            <i class="fa-regular fa-plus tw-mr-1"></i>
            <?= _l('project_vault_new_entry'); ?>
        </button>
    </div>
</div>

<?php if (empty($vault_entries)) { ?>
<div class="panel_s">
    <div class="panel-body tw-text-center tw-py-10">
        <i class="fa-solid fa-lock tw-text-4xl tw-text-neutral-300"></i>
        <p class="text-muted tw-mt-3 tw-mb-0"><?= _l('project_vault_empty'); ?></p>
    </div>
</div>
<?php } else { ?>

<!-- Search + category filter -->
<div class="vault-toolbar">
    <div class="vault-search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="vault-search" class="form-control" placeholder="<?= _l('project_vault_search_placeholder'); ?>" autocomplete="off">
    </div>
    <div class="vault-filters">
        <button type="button" class="btn btn-default vault-filter active" data-cat="all"><?= _l('project_vault_filter_all'); ?></button>
        <?php foreach ($cat_meta as $key => $meta) { ?>
        <button type="button" class="btn btn-default vault-filter" data-cat="<?= $key; ?>">
            <i class="<?= $meta['icon']; ?> tw-mr-1"></i><?= e($meta['label']); ?>
        </button>
        <?php } ?>
    </div>
</div>

<div class="vault-grid">
    <?php foreach ($vault_entries as $entry) {
        $meta = $cat_meta[$entry['type']] ?? $cat_meta['secure_note'];
        // Searchable text (notes are intentionally excluded — they stay hidden).
        $search_parts = [$entry['title'], $entry['username'], $entry['url']];
        foreach ($entry['files'] as $f) {
            $search_parts[] = $f['original_file_name'];
        }
        $search_text = strtolower(trim(implode(' ', array_filter($search_parts))));
    ?>
    <div class="vault-card panel_s" data-cat="<?= e($entry['type']); ?>" data-search="<?= e($search_text); ?>">
        <div class="vault-card-head">
            <div class="tw-flex tw-items-center tw-min-w-0">
                <span class="vault-badge <?= $meta['class']; ?>"><i class="<?= $meta['icon']; ?>"></i></span>
                <div class="tw-ml-3 tw-min-w-0">
                    <div class="vault-card-title" title="<?= e($entry['title']); ?>"><?= e($entry['title']); ?></div>
                    <div class="vault-card-cat"><?= e($meta['label']); ?></div>
                </div>
            </div>
            <div class="tw-flex tw-items-center">
            <?php if (!empty($entry['shared_ids'])) { ?>
                <span class="vault-shared-badge" title="<?= _l('project_vault_shared'); ?>">
                    <i class="fa-solid fa-user-group"></i> <?= count($entry['shared_ids']); ?>
                </span>
            <?php } ?>
            <div class="dropdown">
                <a href="#" class="text-muted" data-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-ellipsis-vertical fa-lg"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-right">
                    <li><a href="#" class="vault-edit" data-entry="<?= e($entry['id']); ?>"><i class="fa-regular fa-pen-to-square tw-mr-1"></i><?= _l('edit'); ?></a></li>
                    <li class="divider"></li>
                    <li><a href="<?= admin_url('project_vault/delete/' . $project->id . '/' . $entry['id']); ?>" class="_delete text-danger"><i class="fa-regular fa-trash-can tw-mr-1"></i><?= _l('delete'); ?></a></li>
                </ul>
            </div>
            </div>
        </div>

        <div class="vault-card-body">
            <?php if (!empty($entry['url'])) { ?>
            <div class="vault-row">
                <span class="vault-row-label"><?= _l('project_vault_url'); ?></span>
                <span class="vault-row-value">
                    <a href="<?= e($entry['url']); ?>" target="_blank" rel="noopener noreferrer" class="vault-truncate"><?= e($entry['url']); ?></a>
                </span>
            </div>
            <?php } ?>

            <?php if (!empty($entry['username'])) { ?>
            <div class="vault-row">
                <span class="vault-row-label"><?= _l('project_vault_username'); ?></span>
                <span class="vault-row-value">
                    <span class="vault-truncate"><?= e($entry['username']); ?></span>
                    <a href="#" class="vault-copy vault-icon-btn" data-copy="<?= e($entry['username']); ?>" title="<?= _l('project_vault_copy'); ?>"><i class="fa-regular fa-copy"></i></a>
                </span>
            </div>
            <?php } ?>

            <?php if ($entry['has_password']) { ?>
            <div class="vault-row">
                <span class="vault-row-label"><?= _l('project_vault_secret'); ?></span>
                <span class="vault-row-value">
                    <span class="vault-secret-value" data-entry="<?= e($entry['id']); ?>">••••••••</span>
                    <a href="#" class="vault-reveal vault-icon-btn" data-entry="<?= e($entry['id']); ?>" title="<?= _l('project_vault_reveal'); ?>"><i class="fa-regular fa-eye"></i></a>
                    <a href="#" class="vault-copy-secret vault-icon-btn tw-hidden" data-entry="<?= e($entry['id']); ?>" title="<?= _l('project_vault_copy'); ?>"><i class="fa-regular fa-copy"></i></a>
                </span>
            </div>
            <?php } ?>

            <?php if ($entry['has_notes']) { ?>
            <div class="vault-row vault-notes-block">
                <div class="vault-notes-head">
                    <span class="vault-row-label"><?= _l('project_vault_notes'); ?></span>
                    <a href="#" class="vault-reveal vault-icon-btn" data-entry="<?= e($entry['id']); ?>" title="<?= _l('project_vault_reveal'); ?>"><i class="fa-regular fa-eye"></i></a>
                </div>
                <div class="vault-notes-value text-muted" data-entry="<?= e($entry['id']); ?>"><em><?= _l('project_vault_hidden'); ?></em></div>
            </div>
            <?php } ?>

            <?php if (!empty($entry['files'])) { ?>
            <div class="vault-files-list">
                <?php foreach ($entry['files'] as $file) { ?>
                <a href="<?= admin_url('project_vault/download/' . $project->id . '/' . $file['id']); ?>"
                    class="vault-file-item" title="<?= _l('project_vault_download'); ?> — <?= e($file['original_file_name']); ?>">
                    <span class="vault-file-name">
                        <i class="fa-regular fa-file"></i>
                        <span class="vault-truncate"><?= e($file['original_file_name']); ?></span>
                    </span>
                    <span class="vault-file-dl"><i class="fa-solid fa-download"></i></span>
                </a>
                <?php } ?>
            </div>
            <?php } ?>
        </div>
    </div>
    <?php } ?>
</div>

<div id="vault-no-results" class="panel_s tw-hidden">
    <div class="panel-body tw-text-center tw-py-10">
        <i class="fa-solid fa-magnifying-glass tw-text-4xl tw-text-neutral-300"></i>
        <p class="text-muted tw-mt-3 tw-mb-0"><?= _l('project_vault_no_results'); ?></p>
    </div>
</div>
<?php } ?>

<!-- Entry modal -->
<div class="modal fade" id="vault-entry-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" id="vault-entry-modal-content"></div>
    </div>
</div>

<!-- History modal -->
<div class="modal fade" id="vault-history-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('project_vault_history'); ?></h4>
            </div>
            <div class="modal-body" id="vault-history-body"></div>
        </div>
    </div>
</div>

<script>
    var vault_project_id = <?= (int) $project->id; ?>;
    var vault_hidden_label = "<?= _l('project_vault_hidden'); ?>";
    var vault_lang = {
        name_required: "<?= _l('project_vault_title_required'); ?>",
        password_required: "<?= _l('project_vault_password_required'); ?>",
        notes_required: "<?= _l('project_vault_notes_required'); ?>",
        file_required: "<?= _l('project_vault_file_required'); ?>"
    };
</script>
