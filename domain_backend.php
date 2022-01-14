<?php

/**
 * Specialised Global Addressbook Contacts Class Backend!
 *
 * @author Michael Daniel Telatynski <postmaster@webdevguru.co.uk>
 * @copyright 2015 Web Development Guru
 * @license http://bit.ly/16ABH2R
 * @license MIT
 *
 * @version 2.5.1 (by AlfnRU)
 */

class domain_backend extends rcube_addressbook {

    public $primary_key = 'ID';
    public $group_id, $groups = false;
    public $readonly = true;

    private $filter, $result, $name;

    public function __construct($name) {
        $this->ready = true;
        $this->name  = $name;
    }

    public function get_name() {
        return $this->name;
    }

    public function set_search_set($filter) {
        $this->filter = $filter;
    }

    public function get_search_set() {
        return $this->check_filter($this->filter);
    }

    public function reset() {
        $this->result = null;
        $this->filter = null;
    }

    public function get_record($id, $assoc = false) {
        $db = rcube::get_instance()->db;
        $db->query("SELECT * FROM domain_addressbook WHERE ID=?", $id);
        if ($sql_arr = $db->fetch_assoc()) {
            $sql_arr['email'] = explode(',', $sql_arr['email']);
            $this->result = new rcube_result_set(1);
            $this->result->add($sql_arr);
        }

        return $assoc && $sql_arr ? $sql_arr : $this->result;
    }

    public function list_records($cols = null, $subset = 0, $nocount = false) {
        $this->result = $this->count();
        $db = rcube::get_instance()->db;
        $rcmail = rcmail::get_instance();

        if (empty($this->group_id)) {
            switch ($this->name) {
                case 'global':
                    $cf = $rcmail->config->get('_sql_gb_data_allowed', ['*']);
                    $fc = $rcmail->config->get('_sql_gb_data_hidden', []);
                    if ($cf === ['*']) {
                        $cf = [];
                    } else {
                        $x[] = 'domain IN (' . $db->array2list($cf) . ')';
                    }
                    if ($this->filter) {
                        $x[] = '(' . $this->filter .')';
                    }
                    if (count($fc) > 0) {
                        $x[] = 'domain NOT IN (' . $db->array2list($fc) . ')';
                    }
                    $x = count($x) > 0 ? (' WHERE ' . implode(' AND ', $x)) : '';
                    $db->query("SELECT * FROM domain_addressbook" . $x);
                    break;

                case 'domain':
                    $x = $this->filter ? (' (' . $this->filter . ') AND ') : ' ';
                    $x = $this->check_filter($x);
                    $db->query("SELECT * FROM domain_addressbook WHERE" . $x . "domain=? ORDER BY name", $rcmail->user->get_username('domain'));
                    break;

                default:
                    $d = $rcmail->config->get('_sql_supportbook', []);
                    $f = array_flip(domain_addressbooks::ac($d, 0));
                    array_shift($z = $d[$f[$this->name]]);
                    if ($this->filter) {
                        $x[] = '(' . $this->filter .')';
                    }
                    if (count($z) > 0) {
                        $x[] = 'domain IN (' . $db->array2list($z) . ')';
                    }
                    $x = count($x)> 0 ? (' WHERE ' . implode(' AND ', $x)) : '';
                    $x = $this->check_filter($x);
                    $db->query("SELECT * FROM domain_addressbook" . $x);
            }

        } else {
            $x = $this->filter ? (' (' . $this->filter . ') AND ') : ' ';
            $db->query("SELECT * FROM domain_addressbook WHERE" . $x . "domain=?", $this->group_id);
        }

        while ($ret = $db->fetch_assoc()) {
            $ret['email'] = explode(',', $ret['email']);
            $this->result->add($ret);
        }

        return $this->result;
    }

    function list_groups($search = null, $mode = 0) {
        if (!$this->groups) {
            return array();
        }

        $rcmail = rcmail::get_instance();
        $cf = $rcmail->config->get('_sql_gb_data_allowed', ['*']);
        $fc = $rcmail->config->get('_sql_gb_data_hidden', []);

        if ($search) {
            switch (intval($mode)) {
                case 1:
                    $x = $rcmail->db->ilike('domain', $search);
                    break;
                case 2:
                    $x = $rcmail->db->ilike('domain', $search . '%');
                    break;
                default:
                    $x = $rcmail->db->ilike('domain', '%' . $search . '%');
            }
            $x = ' WHERE ' . $x . ' ';
        } else {
            $x = ' ';
        }

        if ($cf === ['*']) {
            $cf = [];
            $rcmail->db->query("SELECT domain FROM domain_addressbook" . $x . "GROUP BY domain");
            while ($ret = $rcmail->db->fetch_assoc()) {
                $cf[] = $ret['domain'];
            }
        }

        $co = [];
        foreach (array_diff($cf, $fc) as $v) {
            $co[] = ['ID' => $v, 'name' => $v];
        }

        return $co;
    }

    public function search($fields, $value, $mode = 0, $select = true, $nocount = false, $required = []) {
        if (!is_array($fields)) {
            $fields = array($fields);
        }

        if (!is_array($required) && !empty($required)) {
            $required = array($required);
        }

        $db = rcube::get_instance()->db;
        $where = array();
        $mode = intval($mode);
        $WS = ' ';

        foreach ($fields as $idx => $col) {
            if ($col == 'ID' || $col == $this->primary_key) {
                $ids     = !is_array($value) ? explode(',', $value) : $value;
                $ids     = $db->array2list($ids, 'integer');
                $where[] = 'c.' . $this->primary_key.' IN ('.$ids.')';
                continue;
            } else if ($col == '*') {
                $words = array();
                foreach (explode($WS, rcube_utils::normalize_string($value)) as $word) {
                    switch ($mode) {
                        case 1: // Strict
                            $words[]='(' . $db->ilike('name', $word . '%')
                                . ' OR ' . $db->ilike('email',$word . '%')
                                . ' OR ' . $db->ilike('name', '%' . $WS . $word . $WS . '%')
                                . ' OR ' . $db->ilike('email','%' . $WS . $word . $WS . '%')
                                . ' OR ' . $db->ilike('name', '%' . $WS . $word)
                                . ' OR ' . $db->ilike('email','%' . $WS . $word). ')';
                            break;

                        case 2: // Prefix
                            $words[]='(' . $db->ilike('name', $word . '%')
                                . ' OR ' . $db->ilike('email',$word . '%')
                                . ' OR ' . $db->ilike('name', '%' . $WS . $word . '%')
                                . ' OR ' . $db->ilike('email','%' . $WS . $word . '%') . ')';
                            break;

                        default: // Partial
                            $words[]='(' . $db->ilike('name', '%' . $word . '%')
                                . ' OR ' . $db->ilike('email','%' . $word . '%') . ')';
                            break;
                    }
                }
                $where[] = '(' . join(' AND ', $words) . ')';
            } else if ($col !== 'firstname' && $col !== 'surname') {
                $val = is_array($value) ? $value[$idx] : $value;

                switch ($mode) {
                    case 1: // strict
                        $where[] = '(' . $db->quote_identifier($col) . ' = ' . $db->quote($val)
                            . ' OR ' . $db->ilike($col, $val . $AS . '%')
                            . ' OR ' . $db->ilike($col, '%' . $AS . $val . $AS . '%')
                            . ' OR ' . $db->ilike($col, '%' . $AS . $val) . ')';
                        break;
                    case 2: // prefix
                        $where[] = '(' . $db->ilike($col, $val . '%')
                            . ' OR ' . $db->ilike($col, $AS . $val . '%') . ')';
                        break;
                    default: // partial
                        $where[] = $db->ilike($col, '%' . $val . '%');
                }
            }

            if (!empty($where)) {
                $this->set_search_set(join(is_array($value) ? ' AND ' : ' OR ', $where));
            }
        }

        return $this->list_records();
    }

    function get_group($group_id) {
        return $this->groups ? array('ID' => $group_id, 'name' => $group_id) : null;
    }

    public function count() {
        return new rcube_result_set(1, ($this->list_page-1) * $this->page_size);
    }

    public function get_result() {
        return $this->result;
    }

    public function set_group($gid) {
        $this->group_id = $gid;
        $this->cache = null;
    }

    function create_group($name) {
        return false;
    }

    function delete_group($gid) {
        return false;
    }

    function rename_group($gid, $newname, &$newid) {
        return $newname;
    }

    function add_to_group($group_id, $ids) {
        return false;
    }

    function remove_from_group($group_id, $ids) {
        return false;
    }

    private function check_filter($filter = '') {
        if (!strpos($filter, 'email NOT IN')) {
            $email_hidden = rcmail::get_instance()->config->get('_sql_db_email_hidden', []);
            if (count($email_hidden) > 0) {
                $filter = ' (email NOT IN (' . rcube::get_instance()->db->array2list($email_hidden) . ')) AND ' . $filter;
            }
        }
        return $filter;
    }
}
