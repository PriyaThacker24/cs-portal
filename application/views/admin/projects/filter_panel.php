<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<!-- Projects Filter Panel - Zoho Style -->
<div id="projectsFilterPanel" class="projects-filter-panel">
    <div class="filter-panel-overlay"></div>
    <div class="filter-panel-content">
        <!-- Header -->
        <div class="filter-header">
            <h3><?= _l('filter'); ?></h3>
            <button type="button" class="filter-close" aria-label="<?= _l('close'); ?>">
                <i class="fa fa-times" aria-hidden="true"></i>
            </button>
        </div>

        <div class="filter-panel-body">
            <!-- Filter Search -->
            <div class="filter-search-wrapper">
                <i class="fa fa-search" aria-hidden="true"></i>
                <input type="text" class="filter-search-input" placeholder="<?= _l('filter_search'); ?>">
            </div>

            <!-- Filter Accordions -->
            <div class="filter-accordion">
                <?php $this->load->view('admin/projects/filter_sub_panels'); ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="filter-panel-footer">
            <div class="filter-match-conditions">
                <label class="radio-label">
                    <input type="radio" name="filter_match" value="any">
                    <span><?= _l('any_of_these'); ?></span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="filter_match" value="all" checked>
                    <span><?= _l('all_of_these'); ?></span>
                </label>
            </div>
            <div class="filter-footer-actions">
                <button type="button" class="btn btn-primary btn-filter-find">
                    <i class="fa fa-search"></i> <?= _l('find'); ?>
                </button>
                <button type="button" class="btn btn-info btn-filter-save">
                    <i class="fa fa-bookmark"></i> <?= _l('filter_save'); ?>
                </button>
                <button type="button" class="btn btn-default filter-reset">
                    <?= _l('reset'); ?>
                </button>
                <button type="button" class="btn btn-default btn-filter-cancel">
                    <?= _l('cancel'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Filter Trigger + Saved Filters -->
<?php $current_staff_id = get_staff_user_id(); ?>
<div class="btn-group projects-filter-controls" id="projectsFilterControls">
    <button type="button" class="btn btn-default btn-filter-trigger" id="btnOpenProjectFilter">
        <i class="fa fa-filter"></i> <?= _l('filters'); ?>
    </button>
    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" id="btnSavedProjectFilters">
        <i class="fa fa-bookmark-o"></i> <?= _l('saved_filters'); ?>
        <span class="caret"></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-right saved-filters-menu" id="savedProjectFiltersMenu">
        <li class="saved-filters-empty<?= empty($saved_filters) ? '' : ' hide'; ?>">
            <a href="#" onclick="return false;" class="text-muted"><?= _l('no_filters_found'); ?></a>
        </li>
        <?php foreach (($saved_filters ?? []) as $sf) :
            $can_manage = is_admin() || $sf['staff_id'] == $current_staff_id;
        ?>
        <?php $sf_shared_with = array_map('intval', $sf['shared_with'] ?? []); ?>
        <li class="saved-filter-item<?= $sf['is_default'] == '1' ? ' is-default' : ''; ?>"
            data-id="<?= (int) $sf['id']; ?>"
            data-name="<?= html_escape($sf['name']); ?>"
            data-shared="<?= (int) $sf['is_shared']; ?>"
            data-shared-with="<?= html_escape(json_encode($sf_shared_with)); ?>"
            data-default="<?= (int) ($sf['is_default'] == '1'); ?>"
            data-can-manage="<?= $can_manage ? 1 : 0; ?>"
            data-builder="<?= html_escape(json_encode($sf['builder'])); ?>">
            <a href="#" class="saved-filter-apply" title="<?= _l('filter_apply'); ?>">
                <i class="fa fa-star saved-filter-default-icon" aria-hidden="true"></i>
                <span class="saved-filter-name"><?= html_escape($sf['name']); ?></span>
                <?php if ($sf['is_shared'] || ! empty($sf_shared_with)) : ?>
                    <i class="fa fa-users text-muted" title="<?= $sf['is_shared'] ? _l('filter_share') : _l('filter_share_specific_members'); ?>" aria-hidden="true"></i>
                <?php endif; ?>
            </a>
            <span class="saved-filter-actions">
                <a href="#" class="saved-filter-default" title="<?= _l('filter_mark_as_default'); ?>"><i class="fa fa-star-o"></i></a>
                <?php if ($can_manage) : ?>
                <a href="#" class="saved-filter-edit" title="<?= _l('filter_edit'); ?>"><i class="fa fa-pencil"></i></a>
                <a href="#" class="saved-filter-delete" title="<?= _l('filter_delete'); ?>"><i class="fa fa-trash"></i></a>
                <?php endif; ?>
            </span>
        </li>
        <?php endforeach; ?>
    </ul>
</div>

<!-- Save / Edit Filter Modal -->
<div class="modal fade" id="saveProjectFilterModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="<?= _l('close'); ?>"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('filter_save'); ?></h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="save_filter_id" value="">
                <div class="form-group">
                    <label for="save_filter_name" class="control-label"><?= _l('filter_name'); ?></label>
                    <input type="text" id="save_filter_name" class="form-control" autocomplete="off">
                </div>
                <div class="checkbox checkbox-primary save-filter-update-rules-wrapper hide">
                    <input type="checkbox" id="save_filter_update_rules">
                    <label for="save_filter_update_rules"><?= _l('filter_update_rules'); ?></label>
                </div>
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" id="save_filter_is_shared">
                    <label for="save_filter_is_shared"><?= _l('filter_share'); ?></label>
                </div>
                <div class="form-group" id="save_filter_shared_with_wrapper">
                    <label for="save_filter_shared_with" class="control-label"><?= _l('filter_share_specific_members'); ?></label>
                    <select id="save_filter_shared_with" class="form-control selectpicker" multiple data-live-search="true" data-actions-box="true" data-none-selected-text="<?= _l('filter_share_select_members'); ?>" title="<?= _l('filter_share_select_members'); ?>">
                        <?php foreach (($filter_share_staff ?? []) as $member) :
                            if ($member['staffid'] == $current_staff_id) {
                                continue;
                            } ?>
                            <option value="<?= (int) $member['staffid']; ?>"><?= e($member['firstname'] . ' ' . $member['lastname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-muted"><small><?= _l('filter_share_specific_info'); ?></small></p>
                </div>
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" id="save_filter_is_default">
                    <label for="save_filter_is_default"><?= _l('filter_mark_as_default'); ?></label>
                </div>
                <p class="text-muted"><small><?= _l('default_filter_info'); ?></small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="button" class="btn btn-primary" id="btnSubmitSaveProjectFilter"><?= _l('submit'); ?></button>
            </div>
        </div>
    </div>
</div>


