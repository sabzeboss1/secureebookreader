<?php
/**
 * Test Harness & Mock WordPress Environment pour tests CLI autonomes
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}
if (!defined('SECURE_EBOOK_VERSION')) {
    define('SECURE_EBOOK_VERSION', '1.0.0');
}
if (!defined('SECURE_EBOOK_PATH')) {
    define('SECURE_EBOOK_PATH', dirname(__DIR__) . '/');
}
if (!defined('SECURE_EBOOK_URL')) {
    define('SECURE_EBOOK_URL', 'http://example.com/wp-content/plugins/secure-ebook-reader/');
}
if (!defined('SECURE_EBOOK_BASENAME')) {
    define('SECURE_EBOOK_BASENAME', 'secure-ebook-reader/secure-ebook-reader.php');
}

// Fonctions WordPress simulées pour l'environnement de test
if (!function_exists('absint')) {
    function absint($val) {
        return abs((int) $val);
    }
}
if (!function_exists('current_time')) {
    function current_time($type) {
        return $type === 'mysql' ? date('Y-m-d H:i:s') : time();
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags((string) $str));
    }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        return trim(strip_tags((string) $str));
    }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($val) {
        return is_string($val) ? stripslashes($val) : $val;
    }
}
if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name($name) {
        return preg_replace('/[^a-zA-Z0-9_\.-]/', '', (string) $name);
    }
}
if (!function_exists('sanitize_title')) {
    function sanitize_title($str) {
        return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $str));
    }
}
if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []) {
        return array_merge($defaults, (array) $args);
    }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}
if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        global $mock_options;
        return isset($mock_options[$key]) ? $mock_options[$key] : $default;
    }
}
if (!function_exists('update_option')) {
    function update_option($key, $val) {
        global $mock_options;
        $mock_options[$key] = $val;
        return true;
    }
}
if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        return [
            'basedir' => sys_get_temp_dir() . '/ser-test-vault',
            'baseurl' => 'http://example.com/uploads'
        ];
    }
}
if (!function_exists('user_can')) {
    function user_can($user_id, $cap) {
        global $mock_admin_users;
        return in_array($user_id, (array) ($mock_admin_users ?? []));
    }
}
if (!function_exists('do_action')) {
    function do_action($tag, ...$args) {}
}
if (!function_exists('trailingslashit')) {
    function trailingslashit($str) {
        return rtrim($str, '/\\') . '/';
    }
}

/**
 * Mock WPDB en mémoire
 */
class MockWPDB {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $tables = [
        'wp_secure_ebooks' => [],
        'wp_secure_ebook_access' => [],
        'wp_secure_ebook_tokens' => [],
        'wp_secure_ebook_reading' => [],
        'wp_secure_ebook_logs' => [],
    ];

    public function prepare($query, ...$args) {
        if (isset($args[0]) && is_array($args[0])) {
            $args = $args[0];
        }
        foreach ($args as $arg) {
            $escaped = is_numeric($arg) ? $arg : "'" . addslashes((string)$arg) . "'";
            $query = preg_replace('/%[dfs]/', $escaped, $query, 1);
        }
        return $query;
    }

    public function insert($table, $data, $format = null) {
        if (!isset($this->tables[$table])) {
            $this->tables[$table] = [];
        }
        $data['id'] = ++$this->insert_id;
        $this->tables[$table][$data['id']] = (object) $data;
        return 1;
    }

    public function update($table, $data, $where, $format = null, $where_format = null) {
        if (!isset($this->tables[$table])) return 0;
        $updated = 0;
        foreach ($this->tables[$table] as $id => $row) {
            $match = true;
            foreach ($where as $w_k => $w_v) {
                if (!isset($row->$w_k) || (string)$row->$w_k !== (string)$w_v) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                foreach ($data as $d_k => $d_v) {
                    $row->$d_k = $d_v;
                }
                $updated++;
            }
        }
        return $updated;
    }

    public function query($query) {
        $q = preg_replace('/\s+/', ' ', trim($query));

        if (strpos($q, 'INSERT INTO wp_secure_ebook_access') !== false) {
            if (preg_match('/VALUES \(([^)]+)\)/', $q, $m)) {
                $parts = explode(',', $m[1]);
                $user_id = trim($parts[0]);
                $ebook_id = trim($parts[1]);
                $order_id = trim($parts[2]);
                $source = trim($parts[3], " '");
                $now = trim($parts[4], " '");
                $exp = trim($parts[5], " '");
                if ($exp === 'NULL' || $exp === "''" || empty($exp)) $exp = null;

                foreach ($this->tables['wp_secure_ebook_access'] as $id => $item) {
                    if ((int)$item->user_id === (int)$user_id && (int)$item->ebook_id === (int)$ebook_id) {
                        $item->order_id = (int)$order_id;
                        $item->source = $source;
                        $item->status = 'granted';
                        $item->expires_at = $exp;
                        return 1;
                    }
                }

                $new_id = ++$this->insert_id;
                $this->tables['wp_secure_ebook_access'][$new_id] = (object) [
                    'id' => $new_id,
                    'user_id' => (int)$user_id,
                    'ebook_id' => (int)$ebook_id,
                    'order_id' => (int)$order_id,
                    'source' => $source,
                    'granted_at' => $now,
                    'expires_at' => $exp,
                    'status' => 'granted'
                ];
                return 1;
            }
        }

        if (strpos($q, 'INSERT INTO wp_secure_ebook_reading') !== false) {
            if (preg_match('/VALUES \(([^)]+)\)/', $q, $m)) {
                $parts = explode(',', $m[1]);
                $user_id = (int) trim($parts[0]);
                $ebook_id = (int) trim($parts[1]);
                $page = (int) trim($parts[2]);
                $progress = (float) trim($parts[3]);
                $now = trim($parts[4], " '");

                foreach ($this->tables['wp_secure_ebook_reading'] as $id => $item) {
                    if ((int)$item->user_id === $user_id && (int)$item->ebook_id === $ebook_id) {
                        $item->last_page = $page;
                        $item->progress = $progress;
                        $item->last_read_at = $now;
                        return 1;
                    }
                }

                $new_id = ++$this->insert_id;
                $this->tables['wp_secure_ebook_reading'][$new_id] = (object) [
                    'id' => $new_id,
                    'user_id' => $user_id,
                    'ebook_id' => $ebook_id,
                    'last_page' => $page,
                    'progress' => $progress,
                    'last_read_at' => $now
                ];
                return 1;
            }
        }

        return 1;
    }

    public function get_row($query) {
        $q = preg_replace('/\s+/', ' ', trim($query));

        if (preg_match('/FROM wp_secure_ebooks WHERE id = (\d+)/', $q, $m)) {
            $id = (int) $m[1];
            return $this->tables['wp_secure_ebooks'][$id] ?? null;
        }

        if (preg_match('/FROM wp_secure_ebook_access WHERE user_id = (\d+) AND ebook_id = (\d+)/', $q, $m)) {
            $uid = (int) $m[1];
            $eid = (int) $m[2];
            foreach ($this->tables['wp_secure_ebook_access'] as $row) {
                if ((int)$row->user_id === $uid && (int)$row->ebook_id === $eid) {
                    return $row;
                }
            }
            return null;
        }

        if (preg_match('/FROM wp_secure_ebook_tokens WHERE token_hash = \'([^\']+)\' AND user_id = (\d+) AND ebook_id = (\d+) AND revoked = 0 AND expires_at > \'([^\']+)\'/', $q, $m)) {
            $hash = $m[1];
            $uid = (int) $m[2];
            $eid = (int) $m[3];
            $time = $m[4];
            foreach ($this->tables['wp_secure_ebook_tokens'] as $row) {
                if ($row->token_hash === $hash && (int)$row->user_id === $uid && (int)$row->ebook_id === $eid && (int)$row->revoked === 0 && $row->expires_at > $time) {
                    return $row;
                }
            }
            return null;
        }

        return null;
    }

    public function get_results($query) {
        if (preg_match('/FROM wp_secure_ebook_access WHERE order_id = (\d+) AND status = \'granted\'/', $query, $m)) {
            $oid = (int) $m[1];
            $res = [];
            foreach ($this->tables['wp_secure_ebook_access'] as $row) {
                if ((int)$row->order_id === $oid && $row->status === 'granted') {
                    $res[] = $row;
                }
            }
            return $res;
        }
        return [];
    }

    public function delete($table, $where) {
        return 1;
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}
if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://example.com/wp-content/plugins/secure-ebook-reader/';
    }
}
if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return basename(dirname($file)) . '/' . basename($file);
    }
}
if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {}
}
if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback) {}
}
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
}
if (!function_exists('is_admin')) {
    function is_admin() {
        return false;
    }
}

global $wpdb;
$wpdb = new MockWPDB();

// Chargement du plugin et de son autoloader officiel
require_once dirname(__DIR__) . '/secure-ebook-reader.php';
