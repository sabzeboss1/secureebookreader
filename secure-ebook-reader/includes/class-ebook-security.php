<?php
/**
 * Gestionnaire de sécurité et des jetons éphémères
 *
 * Implémente la protection anti-IDOR, les jetons cryptographiques SHA-256,
 * la limitation de débit (rate limiting) et les en-têtes HTTP hermétiques.
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('ABSPATH')) {
    exit;
}

class Secure_Ebook_Security {

    /**
     * Génère un jeton temporaire de lecture cryptographiquement sûr
     *
     * @param int $user_id ID de l'utilisateur
     * @param int $ebook_id ID de l'ebook
     * @return string Jeton brut transmis au client
     */
    public static function generate_read_token($user_id, $ebook_id) {
        $user_id = absint($user_id);
        $ebook_id = absint($ebook_id);

        // Clé cryptographique aléatoire de 32 octets (64 caractères hexadécimaux)
        $raw_token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $raw_token);

        $settings = get_option('secure_ebook_settings', []);
        $ttl_minutes = !empty($settings['token_ttl_minutes']) ? absint($settings['token_ttl_minutes']) : 15;
        if ($ttl_minutes < 1) {
            $ttl_minutes = 15;
        }

        $now = current_time('mysql');
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$ttl_minutes} minutes", current_time('timestamp')));
        $ip = self::get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 250) : '';

        global $wpdb;
        $table = $wpdb->prefix . 'secure_ebook_tokens';

        $wpdb->insert(
            $table,
            [
                'token_hash' => $token_hash,
                'user_id'    => $user_id,
                'ebook_id'   => $ebook_id,
                'ip_address' => $ip,
                'user_agent' => $user_agent,
                'created_at' => $now,
                'expires_at' => $expires_at,
                'revoked'    => 0,
            ],
            ['%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d']
        );

        return $raw_token;
    }

    /**
     * Valide un jeton de lecture contre la base de données
     *
     * @param string $raw_token Jeton brut
     * @param int $user_id ID de l'utilisateur connecté
     * @param int $ebook_id ID de l'ebook demandé
     * @return bool Vrai si le jeton est valide, non expiré et correspond exactement au couple
     */
    public static function validate_token($raw_token, $user_id, $ebook_id) {
        if (empty($raw_token) || !is_string($raw_token)) {
            return false;
        }

        $user_id = absint($user_id);
        $ebook_id = absint($ebook_id);
        $token_hash = hash('sha256', $raw_token);

        global $wpdb;
        $table = $wpdb->prefix . 'secure_ebook_tokens';
        $now = current_time('mysql');

        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE token_hash = %s 
               AND user_id = %d 
               AND ebook_id = %d 
               AND revoked = 0 
               AND expires_at > %s 
             LIMIT 1",
            $token_hash,
            $user_id,
            $ebook_id,
            $now
        ));

        if (!$record) {
            Secure_Ebook_Logger::log(
                'token_validation_failed',
                'warning',
                sprintf('Échec validation jeton pour utilisateur #%d et ebook #%d (Jeton invalide, révoqué ou expiré)', $user_id, $ebook_id),
                $user_id,
                $ebook_id
            );
            return false;
        }

        return true;
    }

    /**
     * Récupère l'enregistrement d'un jeton valide pour un ebook
     *
     * @param string $raw_token Jeton brut
     * @param int $ebook_id ID de l'ebook
     * @return object|null Enregistrement du jeton ou null
     */
    public static function get_token_record($raw_token, $ebook_id) {
        if (empty($raw_token) || !is_string($raw_token)) {
            return null;
        }

        $token_hash = hash('sha256', $raw_token);
        $ebook_id   = absint($ebook_id);

        global $wpdb;
        $table = $wpdb->prefix . 'secure_ebook_tokens';
        $now   = current_time('mysql');

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE token_hash = %s 
               AND ebook_id = %d 
               AND revoked = 0 
               AND expires_at > %s 
             LIMIT 1",
            $token_hash,
            $ebook_id,
            $now
        ));
    }

    /**
     * Prolonge la validité d'un jeton actif (Heartbeat)
     *
     * @param string $raw_token
     * @param int $user_id
     * @param int $ebook_id
     * @return bool
     */
    public static function renew_token($raw_token, $user_id, $ebook_id) {
        if (!self::validate_token($raw_token, $user_id, $ebook_id)) {
            return false;
        }

        $settings = get_option('secure_ebook_settings', []);
        $ttl_minutes = !empty($settings['token_ttl_minutes']) ? absint($settings['token_ttl_minutes']) : 15;
        $new_expires_at = date('Y-m-d H:i:s', strtotime("+{$ttl_minutes} minutes", current_time('timestamp')));
        $token_hash = hash('sha256', $raw_token);

        global $wpdb;
        $table = $wpdb->prefix . 'secure_ebook_tokens';

        $updated = $wpdb->update(
            $table,
            ['expires_at' => $new_expires_at],
            ['token_hash' => $token_hash],
            ['%s'],
            ['%s']
        );

        return (false !== $updated);
    }

    /**
     * Révoque immédiatement tous les jetons actifs d'un utilisateur pour un ebook
     */
    public static function revoke_user_ebook_tokens($user_id, $ebook_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'secure_ebook_tokens';

        return $wpdb->update(
            $table,
            ['revoked' => 1],
            ['user_id' => absint($user_id), 'ebook_id' => absint($ebook_id)],
            ['%d'],
            ['%d', '%d']
        );
    }

    /**
     * Purge les anciens jetons expirés pour maintenir la table légère
     */
    public static function cleanup_expired_tokens() {
        global $wpdb;
        $table = $wpdb->prefix . 'secure_ebook_tokens';
        $threshold = date('Y-m-d H:i:s', strtotime('-48 hours', current_time('timestamp')));

        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE expires_at < %s OR revoked = 1", $threshold));
    }

    /**
     * Envoie les en-têtes HTTP de sécurité pour le flux de streaming
     */
    public static function send_secure_stream_headers($file_size, $start, $end) {
        // Vider tout buffer de sortie PHP existant pour éviter la corruption de fichier
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="document.pdf"');
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Robots-Tag: noindex, nofollow, noarchive');

        $length = $end - $start + 1;
        header("Content-Length: {$length}");

        if ($start > 0 || $end < ($file_size - 1)) {
            header('HTTP/1.1 206 Partial Content');
            header("Content-Range: bytes {$start}-{$end}/{$file_size}");
        } else {
            header('HTTP/1.1 200 OK');
        }
    }

    /**
     * Récupère l'adresse IP client de manière fiable
     */
    public static function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', sanitize_text_field(wp_unslash($_SERVER[$key])));
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }
}
