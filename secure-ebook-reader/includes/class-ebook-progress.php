<?php
/**
 * Gestionnaire de progression et mémorisation de lecture
 *
 * Enregistre la dernière page consultée et le pourcentage d'avancement
 * avec système anti-flood côté serveur.
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('ABSPATH')) {
    exit;
}

class Secure_Ebook_Progress {

    /**
     * Récupère la progression de lecture d'un utilisateur pour un ebook
     *
     * @param int $user_id
     * @param int $ebook_id
     * @return object|null
     */
    public static function get_progress($user_id, $ebook_id) {
        $user_id = absint($user_id);
        $ebook_id = absint($ebook_id);

        if (!$user_id || !$ebook_id) {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'secure_ebook_reading';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND ebook_id = %d LIMIT 1",
            $user_id,
            $ebook_id
        ));
    }

    /**
     * Enregistre la progression de lecture (page courante et %)
     *
     * @param int $user_id
     * @param int $ebook_id
     * @param int $page Page actuelle
     * @param int $total_pages Total de pages de l'ouvrage
     * @return bool
     */
    public static function save_progress($user_id, $ebook_id, $page, $total_pages = 0) {
        $user_id = absint($user_id);
        $ebook_id = absint($ebook_id);
        $page = max(1, absint($page));
        $total_pages = absint($total_pages);

        if (!$user_id || !$ebook_id) {
            return false;
        }

        // Si le total de pages n'est pas passé, le chercher dans l'ebook
        if ($total_pages <= 0) {
            $ebook = Secure_Ebook_Ebook::get($ebook_id);
            $total_pages = $ebook ? $ebook->page_count : 1;
        }

        $percentage = 0.00;
        if ($total_pages > 0) {
            $percentage = min(100.00, round(($page / $total_pages) * 100, 2));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'secure_ebook_reading';
        $now = current_time('mysql');

        $query = $wpdb->prepare(
            "INSERT INTO {$table} (user_id, ebook_id, last_page, progress, last_read_at)
             VALUES (%d, %d, %d, %f, %s)
             ON DUPLICATE KEY UPDATE
                last_page = VALUES(last_page),
                progress = VALUES(progress),
                last_read_at = VALUES(last_read_at)",
            $user_id,
            $ebook_id,
            $page,
            $percentage,
            $now
        );

        $result = $wpdb->query($query);
        return (false !== $result);
    }
}
