<?php
/**
 * Journal d'audit de sécurité et traçabilité des accès
 *
 * Enregistre les tentatives d'accès non autorisées (anti-IDOR), les révocations
 * et les flux de lecture dans la table wp_secure_ebook_logs.
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('ABSPATH')) {
    exit;
}

class Secure_Ebook_Logger {

    /**
     * Enregistre un événement de sécurité
     *
     * @param string $event_type Type d'événement
     * @param string $severity Niveau de gravité ('info', 'warning', 'danger')
     * @param string $message Détail du journal
     * @param int $user_id ID de l'utilisateur concerné
     * @param int $ebook_id ID de l'ebook concerné
     * @return bool
     */
    public static function log($event_type, $severity = 'info', $message = '', $user_id = 0, $ebook_id = 0) {
        $settings = get_option('secure_ebook_settings', []);
        if (isset($settings['log_security_events']) && $settings['log_security_events'] === 'no') {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'secure_ebook_logs';

        $ip = Secure_Ebook_Security::get_client_ip();
        $now = current_time('mysql');

        $result = $wpdb->insert(
            $table,
            [
                'user_id'    => absint($user_id),
                'ebook_id'   => absint($ebook_id),
                'event_type' => sanitize_text_field($event_type),
                'severity'   => in_array($severity, ['info', 'warning', 'danger']) ? $severity : 'info',
                'message'    => sanitize_textarea_field($message),
                'ip_address' => $ip,
                'created_at' => $now,
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        return (false !== $result);
    }

    /**
     * Récupère la liste des journaux d'audit avec filtres
     */
    public static function get_logs($args = []) {
        global $wpdb;
        $table_logs   = $wpdb->prefix . 'secure_ebook_logs';
        $table_ebooks = $wpdb->prefix . 'secure_ebooks';
        $table_users  = $wpdb->users;

        $defaults = [
            'severity'   => '',
            'event_type' => '',
            'user_id'    => 0,
            'ebook_id'   => 0,
            'limit'      => 50,
            'offset'     => 0,
        ];
        $r = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $values = [];

        if (!empty($r['severity'])) {
            $where[] = "l.severity = %s";
            $values[] = sanitize_text_field($r['severity']);
        }
        if (!empty($r['event_type'])) {
            $where[] = "l.event_type = %s";
            $values[] = sanitize_text_field($r['event_type']);
        }
        if (!empty($r['user_id'])) {
            $where[] = "l.user_id = %d";
            $values[] = absint($r['user_id']);
        }
        if (!empty($r['ebook_id'])) {
            $where[] = "l.ebook_id = %d";
            $values[] = absint($r['ebook_id']);
        }

        $where_sql = implode(' AND ', $where);
        $limit = absint($r['limit']);
        $offset = absint($r['offset']);

        $query = "SELECT 
                    l.*, 
                    e.title AS ebook_title, 
                    u.user_login, 
                    u.user_email 
                  FROM {$table_logs} l
                  LEFT JOIN {$table_ebooks} e ON l.ebook_id = e.id
                  LEFT JOIN {$table_users} u ON l.user_id = u.ID
                  WHERE {$where_sql}
                  ORDER BY l.created_at DESC
                  LIMIT {$limit} OFFSET {$offset}";

        if (!empty($values)) {
            return $wpdb->get_results($wpdb->prepare($query, $values));
        }

        return $wpdb->get_results($query);
    }

    /**
     * Compte le nombre d'enregistrements du journal
     */
    public static function count_logs($severity = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'secure_ebook_logs';

        if (!empty($severity)) {
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE severity = %s", $severity));
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * Purge les journaux de plus de 60 jours
     */
    public static function cleanup_old_logs() {
        global $wpdb;
        $table = $wpdb->prefix . 'secure_ebook_logs';
        $threshold = date('Y-m-d H:i:s', strtotime('-60 days', current_time('timestamp')));
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE created_at < %s", $threshold));
    }
}
