<?php
/**
 * Gestionnaire central des droits d'accès
 *
 * Source unique de vérité : can_read(), attribution, révocation immédiate.
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('ABSPATH')) {
    exit;
}

class Secure_Ebook_Access {

    /**
     * Point central de vérification des droits de lecture
     *
     * @param int $user_id ID de l'utilisateur
     * @param int $ebook_id ID de l'ebook
     * @return bool Vrai si l'accès est légitime et actif
     */
    public static function can_read($user_id, $ebook_id) {
        $user_id = absint($user_id);
        $ebook_id = absint($ebook_id);

        if (!$user_id || !$ebook_id) {
            return false;
        }

        // Les administrateurs avec manage_options ont un droit de prévisualisation
        if (user_can($user_id, 'manage_options')) {
            $ebook = Secure_Ebook_Ebook::get($ebook_id);
            return ($ebook && $ebook->status === 'active');
        }

        // Vérifier l'existence et le statut de l'ebook
        $ebook = Secure_Ebook_Ebook::get($ebook_id);
        if (!$ebook || $ebook->status !== 'active') {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'secure_ebook_access';

        $access = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND ebook_id = %d LIMIT 1",
            $user_id,
            $ebook_id
        ));

        if (!$access) {
            return false;
        }

        // Vérification du statut accordé
        if ($access->status !== 'granted') {
            return false;
        }

        // Vérification de la date d'expiration (ignorer NULL et 0000-00-00 qui désignent un accès illimité)
        if (!empty($access->expires_at) && $access->expires_at !== '0000-00-00 00:00:00' && $access->expires_at !== '0000-00-00') {
            $current_time = current_time('mysql');
            if ($access->expires_at <= $current_time) {
                // Marquer automatiquement comme expiré
                $wpdb->update(
                    $table,
                    ['status' => 'expired'],
                    ['id' => $access->id],
                    ['%s'],
                    ['%d']
                );
                return false;
            }
        }

        return true;
    }

    /**
     * Octroie l'accès à un ebook pour un utilisateur
     *
     * @param int $user_id ID de l'utilisateur
     * @param int $ebook_id ID de l'ebook
     * @param int $order_id ID de la commande WooCommerce (0 si manuel)
     * @param string $source Origine de l'accès ('woocommerce', 'manual', 'admin')
     * @param string|null $duration Durée forcée ('permanent', '30d', '90d', '1y')
     * @return bool
     */
    public static function grant_access($user_id, $ebook_id, $order_id = 0, $source = 'woocommerce', $duration = null) {
        $user_id = absint($user_id);
        $ebook_id = absint($ebook_id);
        $order_id = absint($order_id);

        if (!$user_id || !$ebook_id) {
            return false;
        }

        $ebook = Secure_Ebook_Ebook::get($ebook_id);
        if (!$ebook) {
            return false;
        }

        // Déterminer la date d'expiration
        $effective_duration = $duration ?: $ebook->access_duration;
        $expires_at = null;

        if ($effective_duration && $effective_duration !== 'permanent') {
            $days = 0;
            switch ($effective_duration) {
                case '30d': $days = 30; break;
                case '90d': $days = 90; break;
                case '1y':  $days = 365; break;
                default:
                    if (is_numeric($effective_duration)) {
                        $days = absint($effective_duration);
                    }
            }
            if ($days > 0) {
                $expires_at = date('Y-m-d H:i:s', strtotime("+{$days} days", current_time('timestamp')));
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . 'secure_ebook_access';

        // Requête ON DUPLICATE KEY UPDATE avec gestion propre du NULL SQL
        $now = current_time('mysql');
        if (empty($expires_at)) {
            $query = $wpdb->prepare(
                "INSERT INTO {$table} (user_id, ebook_id, order_id, source, granted_at, expires_at, status)
                 VALUES (%d, %d, %d, %s, %s, NULL, 'granted')
                 ON DUPLICATE KEY UPDATE
                    order_id = VALUES(order_id),
                    source = VALUES(source),
                    granted_at = VALUES(granted_at),
                    expires_at = NULL,
                    status = 'granted'",
                $user_id,
                $ebook_id,
                $order_id,
                $source,
                $now
            );
        } else {
            $query = $wpdb->prepare(
                "INSERT INTO {$table} (user_id, ebook_id, order_id, source, granted_at, expires_at, status)
                 VALUES (%d, %d, %d, %s, %s, %s, 'granted')
                 ON DUPLICATE KEY UPDATE
                    order_id = VALUES(order_id),
                    source = VALUES(source),
                    granted_at = VALUES(granted_at),
                    expires_at = VALUES(expires_at),
                    status = 'granted'",
                $user_id,
                $ebook_id,
                $order_id,
                $source,
                $now,
                $expires_at
            );
        }

        $result = $wpdb->query($query);

        if (false !== $result) {
            Secure_Ebook_Logger::log(
                'access_granted',
                'info',
                sprintf('Accès accordé à l\'utilisateur #%d pour l\'ebook #%d (source: %s, commande: #%d)', $user_id, $ebook_id, $source, $order_id),
                $user_id,
                $ebook_id
            );

            do_action('secure_ebook_access_granted', $user_id, $ebook_id, $order_id);
            return true;
        }

        return false;
    }

    /**
     * Révoque l'accès d'un utilisateur à un ebook spécifique
     * Invalide immédiatement tous les tokens éphémères actifs
     *
     * @param int $user_id ID de l'utilisateur
     * @param int $ebook_id ID de l'ebook
     * @param string $reason Motif de révocation
     * @return bool
     */
    public static function revoke_access($user_id, $ebook_id, $reason = '') {
        $user_id = absint($user_id);
        $ebook_id = absint($ebook_id);

        if (!$user_id || !$ebook_id) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'secure_ebook_access';

        $updated = $wpdb->update(
            $table,
            ['status' => 'revoked'],
            ['user_id' => $user_id, 'ebook_id' => $ebook_id],
            ['%s'],
            ['%d', '%d']
        );

        // Invalidation immédiate de tous les tokens actifs pour ce couple
        Secure_Ebook_Security::revoke_user_ebook_tokens($user_id, $ebook_id);

        Secure_Ebook_Logger::log(
            'access_revoked',
            'warning',
            sprintf('Accès révoqué pour l\'utilisateur #%d (ebook #%d). Motif: %s', $user_id, $ebook_id, $reason ?: 'Non spécifié'),
            $user_id,
            $ebook_id
        );

        do_action('secure_ebook_access_revoked', $user_id, $ebook_id, $reason);
        return (false !== $updated);
    }

    /**
     * Révoque tous les accès liés à une commande WooCommerce spécifique (remboursement ou annulation)
     *
     * @param int $order_id ID de la commande
     * @return int Nombre d'accès révoqués
     */
    public static function revoke_by_order($order_id) {
        $order_id = absint($order_id);
        if (!$order_id) {
            return 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'secure_ebook_access';

        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, ebook_id FROM {$table} WHERE order_id = %d AND status = 'granted'",
            $order_id
        ));

        if (empty($records)) {
            return 0;
        }

        $count = 0;
        foreach ($records as $record) {
            if (self::revoke_access($record->user_id, $record->ebook_id, sprintf('Remboursement / Annulation de la commande #%d', $order_id))) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Récupère la liste de tous les ebooks accessibles pour un utilisateur
     * avec progression de lecture incluse
     *
     * @param int $user_id ID de l'utilisateur
     * @return array Liste des ebooks avec métadonnées et progression
     */
    public static function get_user_ebooks($user_id) {
        $user_id = absint($user_id);
        if (!$user_id) {
            return [];
        }

        global $wpdb;
        $table_ebooks = $wpdb->prefix . 'secure_ebooks';
        $table_access = $wpdb->prefix . 'secure_ebook_access';
        $table_reading = $wpdb->prefix . 'secure_ebook_reading';

        $now = current_time('mysql');

        // Auto-guérison des anciennes lignes où expires_at contenait '0000-00-00 00:00:00'
        $wpdb->query("UPDATE {$table_access} SET expires_at = NULL WHERE user_id = {$user_id} AND (expires_at = '0000-00-00 00:00:00' OR expires_at = '')");

        $query = $wpdb->prepare(
            "SELECT 
                e.*,
                a.granted_at,
                a.expires_at,
                a.order_id,
                COALESCE(r.last_page, 1) AS last_page,
                COALESCE(r.progress, 0.00) AS progress,
                r.last_read_at
             FROM {$table_ebooks} e
             INNER JOIN {$table_access} a ON e.id = a.ebook_id
             LEFT JOIN {$table_reading} r ON e.id = r.ebook_id AND r.user_id = %d
             WHERE a.user_id = %d
               AND a.status = 'granted'
               AND (a.expires_at IS NULL OR a.expires_at = '0000-00-00 00:00:00' OR a.expires_at = '' OR a.expires_at > %s)
               AND e.status = 'active'
             ORDER BY COALESCE(r.last_read_at, a.granted_at) DESC",
            $user_id,
            $user_id,
            $now
        );

        return $wpdb->get_results($query);
    }

    /**
     * Récupère tous les enregistrements d'accès pour l'administration
     */
    public static function get_all_accesses($args = []) {
        global $wpdb;
        $table_access = $wpdb->prefix . 'secure_ebook_access';
        $table_ebooks = $wpdb->prefix . 'secure_ebooks';
        $table_users  = $wpdb->users;

        $defaults = [
            'status'   => '',
            'ebook_id' => 0,
            'user_id'  => 0,
            'limit'    => 50,
            'offset'   => 0,
        ];
        $r = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $values = [];

        if (!empty($r['status'])) {
            $where[] = "a.status = %s";
            $values[] = sanitize_text_field($r['status']);
        }
        if (!empty($r['ebook_id'])) {
            $where[] = "a.ebook_id = %d";
            $values[] = absint($r['ebook_id']);
        }
        if (!empty($r['user_id'])) {
            $where[] = "a.user_id = %d";
            $values[] = absint($r['user_id']);
        }

        $where_sql = implode(' AND ', $where);
        $limit = absint($r['limit']);
        $offset = absint($r['offset']);

        $query = "SELECT 
                    a.*, 
                    e.title AS ebook_title, 
                    u.user_login, 
                    u.user_email, 
                    u.display_name
                  FROM {$table_access} a
                  LEFT JOIN {$table_ebooks} e ON a.ebook_id = e.id
                  LEFT JOIN {$table_users} u ON a.user_id = u.ID
                  WHERE {$where_sql}
                  ORDER BY a.granted_at DESC
                  LIMIT {$limit} OFFSET {$offset}";

        if (!empty($values)) {
            return $wpdb->get_results($wpdb->prepare($query, $values));
        }

        return $wpdb->get_results($query);
    }

    /**
     * Compte le nombre total d'accès
     */
    public static function count_accesses($status = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'secure_ebook_access';
        if (!empty($status)) {
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", $status));
        }
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }
}
