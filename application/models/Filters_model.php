<?php

use app\services\utilities\Arr;

defined("BASEPATH") or exit("No direct script access allowed");

class Filters_model extends App_Model
{
    public function create($data)
    {
        $isDefault = Arr::pull($data, 'is_default');
        $view = Arr::pull($data, 'view');
        $sharedWith = Arr::pull($data, 'shared_with');

        $data["builder"] = json_encode($data["builder"]);
        $this->db->insert("filters", $data);

        $filterId = $this->db->insert_id();

        if ($isDefault === true) {
            $this->delete_default($data['identifier'], $view, $data['staff_id']);
            $this->mark_as_default($filterId, $data['identifier'], $view, $data['staff_id']);
        }

        // Only modules that opt into per-member sharing pass `shared_with`.
        if ($sharedWith !== null) {
            $this->sync_shares($filterId, is_array($sharedWith) ? $sharedWith : []);
        }

        return $this->find($filterId, $view, $data['staff_id']);
    }

    public function find($id, $view, $staffId)
    {
        $filter = $this->db->where("id", $id)->get("filters")->row_array();

        $filter["builder"] = json_decode($filter["builder"], true);

        $filter = $this->merge_defaults([$filter], $view, $staffId)[0];
        $filter['shared_with'] = $this->get_shares($id);

        return $filter;
    }

    public function update($id, $data, $staffId)
    {
        $filter = $this->db->select(['identifier', 'staff_id'])->where('id', $id)->get('filters')->row_array();
        $isDefault = Arr::pull($data, 'is_default');
        $view = Arr::pull($data, 'view');
        $sharedWith = Arr::pull($data, 'shared_with');

        if ($isDefault === true) {
            $this->delete_default($filter['identifier'], $view, $filter['staff_id']);
            $this->mark_as_default($id, $filter['identifier'], $view, $filter['staff_id']);
        } else if ($isDefault === false && $this->is_default($id, $filter['identifier'], $view, $filter['staff_id'])) {
            $this->delete_default($filter['identifier'], $view, $filter['staff_id']);
        }

        $data["builder"] = json_encode($data["builder"]);

        $this->db->where("id", $id)->update("filters", $data);

        if ($sharedWith !== null) {
            $this->sync_shares($id, is_array($sharedWith) ? $sharedWith : []);
        }

        return $this->find($id, $view, $staffId);
    }

    public function delete($id)
    {
        $this->db->where('id', $id)->delete('filters');

        return true;
    }

    public function get_for_staff($identifier, $view, $staffId)
    {
        // Filter ids explicitly shared with this staff member.
        $sharedIds = $this->shared_filter_ids_for_staff($staffId);

        $this->db->where("identifier", $identifier);

        $this->db->group_start();
        $this->db->where('staff_id', $staffId);
        $this->db->or_where('is_shared', 1);
        if (! empty($sharedIds)) {
            $this->db->or_where_in('id', $sharedIds);
        }
        $this->db->group_end();

        $filters = $this->db->get("filters")->result_array();

        foreach ($filters as $key => $filter) {
            $filters[$key]["builder"] = json_decode($filter["builder"], true);
        }

        $filters = $this->merge_defaults($filters, $view, $staffId);

        foreach ($filters as $key => $filter) {
            $filters[$key]['shared_with'] = $this->get_shares($filter['id']);
        }

        return $filters;
    }

    /**
     * Staff ids a filter is shared with directly (per-member sharing).
     */
    public function get_shares($filterId)
    {
        return array_map('intval', array_column(
            $this->db->select('staff_id')->where('filter_id', $filterId)->get('filter_shares')->result_array(),
            'staff_id'
        ));
    }

    /**
     * Filter ids that have been shared directly with the given staff member.
     */
    protected function shared_filter_ids_for_staff($staffId)
    {
        return array_map('intval', array_column(
            $this->db->select('filter_id')->where('staff_id', $staffId)->get('filter_shares')->result_array(),
            'filter_id'
        ));
    }

    /**
     * Replace the per-member shares for a filter with the given staff ids.
     */
    public function sync_shares($filterId, array $staffIds)
    {
        $this->db->where('filter_id', $filterId)->delete('filter_shares');

        $staffIds = array_values(array_unique(array_filter(array_map('intval', $staffIds))));

        foreach ($staffIds as $sid) {
            $this->db->insert('filter_shares', [
                'filter_id' => $filterId,
                'staff_id'  => $sid,
            ]);
        }
    }

    public function is_default($filterId, $identifier, $view, $staffId)
    {
        return $this->db->where('staff_id', $staffId)
            ->where('identifier', $identifier)
            ->where('filter_id', $filterId)
            ->where('view', $view)
            ->count_all('filter_defaults') > 0;
    }

    public function mark_as_default($filterId, $identifier, $view, $staffId)
    {
        $this->db->insert('filter_defaults', [
            'staff_id' => $staffId,
            'filter_id' => $filterId,
            'view' => $view,
            'identifier' => $identifier,
        ]);
    }

    public function delete_default($identifier, $view, $staffId)
    {
        $this->db->where('staff_id', $staffId)
            ->where('identifier', $identifier)
            ->where('view', $view)
            ->delete('filter_defaults');
    }

    protected function merge_defaults($filters, $view, $staffId)
    {
        $filterIds = Arr::pluck($filters, 'id');

        if (count($filterIds) === 0) {
            return $filters;
        }

        $defaults = $this->db->where_in('filter_id', $filterIds)
            ->where('view', $view)
            ->get('filter_defaults')
            ->result_array();

        foreach ($filters as $key => $filter) {
            $filters[$key]['is_default'] = "0";

            foreach ($defaults as $default) {
                if ($default['staff_id'] == $staffId && $default['filter_id'] == $filter['id']) {
                    $filters[$key]['is_default'] = "1";
                }
            }
        }

        return $filters;
    }
}
