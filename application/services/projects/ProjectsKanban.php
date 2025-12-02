<?php

namespace app\services\projects;

use app\services\AbstractKanban;

class ProjectsKanban extends AbstractKanban
{
    protected function table(): string
    {
        return 'projects';
    }

    public function defaultSortDirection(): string
    {
        return 'DESC';
    }

    public function defaultSortColumn(): string
    {
        return 'id';
    }

    public function limit()
    {
        return 5;
    }

    protected function applySearchQuery($q): self
    {
        if (!startsWith($q, '#')) {
            $q = $this->ci->db->escape_like_str($q);
            $this->ci->db->where('(' . db_prefix() . 'projects.name LIKE "%' . $q . '%" ESCAPE \'!\'  OR ' . db_prefix() . 'projects.description LIKE "%' . $q . '%" ESCAPE \'!\')');
        } else {
            $this->ci->db->where(db_prefix() . 'projects.id IN
                (SELECT rel_id FROM ' . db_prefix() . 'taggables WHERE tag_id IN
                (SELECT id FROM ' . db_prefix() . 'tags WHERE name="' . $this->ci->db->escape_str(strafter($q, '#')) . '")
                AND ' . db_prefix() . 'taggables.rel_type=\'project\' GROUP BY rel_id HAVING COUNT(tag_id) = 1)
                ');
        }

        return $this;
    }

    protected function initiateQuery(): self
    {
        $has_permission_view = staff_can('view', 'projects');

        $this->ci->db->select(db_prefix() . 'projects.*, (SELECT GROUP_CONCAT(name SEPARATOR ",") FROM ' . db_prefix() . 'taggables JOIN ' . db_prefix() . 'tags ON ' . db_prefix() . 'taggables.tag_id = ' . db_prefix() . 'tags.id WHERE rel_id = ' . db_prefix() . 'projects.id and rel_type="project" ORDER BY tag_order ASC) as tags');

        $this->ci->db->select('(SELECT COUNT(id) FROM ' . db_prefix() . 'project_members WHERE project_id=' . db_prefix() . 'projects.id) as total_members');

        $this->ci->db->select('(SELECT company FROM ' . db_prefix() . 'clients WHERE userid=' . db_prefix() . 'projects.clientid) as client_name');

        $this->ci->db->from(db_prefix() . 'projects');
        $this->ci->db->where('status', $this->status);

        if (!$has_permission_view) {
            $this->ci->db->where('id IN (SELECT project_id FROM ' . db_prefix() . 'project_members WHERE staff_id=' . get_staff_user_id() . ')');
        }

        return $this;
    }
}

