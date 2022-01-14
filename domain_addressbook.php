<?php

/**
 * Specialised Global Addressbook Contacts Class!
 *
 * Roundcube Plugin to create an Address Book from list of users in the SQL View.
 * Currently Natively Supporting:
 *  + iRedMail [Aliases Supported]
 *
 * @author Michael Daniel Telatynski <postmaster@webdevguru.co.uk>
 * @copyright 2015 Web Development Guru
 * @license http://bit.ly/16ABH2R
 * @license MIT
 *
 * @version 2.5.1 (by AlfnRU)
 */

require_once(__DIR__ . '/domain_backend.php');

class domain_addressbook extends rcube_plugin {

    public $task = 'mail|addressbook';
    public $rcmail;

    public function init() {
        $this->add_hook('addressbooks_list', [$this, 'address_sources']);
        $this->add_hook('addressbook_get',   [$this, 'get_address_book']);

        $this->load_config();
        $this->rcmail = rcmail::get_instance();

        if ($this->is_enabled('domain')) {
            $x[] = 'domain';
        }

        if ($this->is_enabled('global')) {
            $x[] = 'global';
        }

        $sources = $this->rcmail->config->get('autocomplete_addressbooks', array());

        foreach ($this->rcmail->config->get('_sql_supportbook', array()) as $z) {
            $c = array_shift($z);
            $d = $this->rcmail->user->get_username('domain');
            if (!in_array($d, $z, true) && !in_array($c, $sources)) {
                $sources[] = $c;
            }
        }

        foreach (['domain', 'global'] as $v) {
            if ($this->is_enabled($v) && !in_array($v, $sources)) {
                $sources[] = $v;
            }
        }

        $this->rcmail->config->set('autocomplete_addressbooks', $sources);
    }

    private function is_enabled($book) {
        if ($this->rcmail->config->get('_sql_' . $book . 'book_name', false)) {
            return $this->wlbl(substr($book, 0, 1) . 'b', $this->rcmail->user->get_username('domain'));
        }
        return false;
    }

    private function touchbook($id, $name, $groups = false) {
        return [
            'id'           => $id,
            'name'         => $name,
            'groups'       => $groups,
            'readonly'     => true,
            'autocomplete' => true,
        ];
    }

    private function wlbl($id, $domain) {
        $cf = $this->rcmail->config->get('_sql_' . $id . '_read_allowed', array('*'));
        $fc = $this->rcmail->config->get('_sql_' . $id . '_read_hidden',  array());

        if (in_array($domain, $fc)) {
            return false;
        }

        if ($cf === array('*') || in_array($domain, $cf)) {
            return true;
        }

        return false;
    }

    public static function ac($arr, $id) {
        if (function_exists('array_column')) {
            return array_column($arr, $id);
        }

        $ret = [];
        foreach ($arr as $val) {
            if (isset($val[$id])) {
                $ret[] = $val[$id];
            }
        }

        return $ret;
    }

    public function address_sources($p) {
        $dm = $this->rcmail->user->get_username('domain');
        if (($gb = $this->rcmail->config->get('_sql_globalbook_name', false)) && $this->wlbl('gb', $dm)) {
            $p['sources']['global'] = $this->touchbook('global', $gb, $this->rcmail->config->get('_sql_globalbook_gp', true));
        }

        if (($db = $this->rcmail->config->get('_sql_domainbook_name', false)) && $this->wlbl('db', $dm)) {
            $p['sources']['domain'] = $this->touchbook('domain', $db);
        }

        if ($sb = $this->rcmail->config->get('_sql_supportbook_list', array())) {
            foreach ($sb as $csb) {
                $csbn = array_shift($csb);
                if (!in_array($dm, $csb)) {
                    $p['sources'][$csbn]= $this->touchbook($csbn, $csbn);
                }
            }
        }

        return $p;
    }

    public function get_address_book($p) {
        if ($p['id'] === 'global') {
            $p['instance'] = new domain_backend('global');
            $p['instance']->groups = $this->rcmail->config->get('_sql_globalbook_gp', true);
        } elseif (in_array($p['id'], $this->ac($this->rcmail->config->get('_sql_supportbook', array()), 0))) {
            $p['instance'] = new domain_backend($p['id']);
        } elseif ($p['id'] === 'domain') {
            $p['instance'] = new domain_backend('domain');
        }

        return $p;
    }

}