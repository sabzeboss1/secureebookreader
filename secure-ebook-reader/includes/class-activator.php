<?php
/**
 * Gestionnaire d'activation du plugin
 *
 * Initialise le schéma SQL (dbDelta), les dossiers hermétiques de stockage
 * et les options par défaut.
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('ABSPATH')) {
    exit;
}

class Secure_Ebook_Activator {

    /**
     * Point d'entrée de l'activation
     */
    public static function activate() {
        self::create_tables();
        self::create_secure_vault_directory();
        self::init_default_options();

        // Enregistrer la version courante de la base de données
        update_option('secure_ebook_db_version', SECURE_EBOOK_VERSION);

        // Flusher les règles de réécriture pour les endpoints personnalisés
        set_transient('secure_ebook_flush_rewrite_rules', 1, 30);
    }

    /**
     * Crée ou met à jour les 5 tables MySQL via dbDelta()
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1. Table des Ebooks sécurisés
        $table_ebooks = $wpdb->prefix . 'secure_ebooks';
        $sql_ebooks = "CREATE TABLE {$table_ebooks} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL,
            author VARCHAR(255) NOT NULL DEFAULT '',
            description TEXT DEFAULT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_hash VARCHAR(64) NOT NULL,
            file_size BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            page_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
            access_mode VARCHAR(50) NOT NULL DEFAULT 'online_only',
            access_duration VARCHAR(50) NOT NULL DEFAULT 'permanent',
            protections LONGTEXT DEFAULT NULL,
            cover_image_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY status (status)
        ) {$charset_collate};";
        dbDelta($sql_ebooks);

        // 2. Table des droits d'accès clients
        $table_access = $wpdb->prefix . 'secure_ebook_access';
        $sql_access = "CREATE TABLE {$table_access} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            ebook_id BIGINT(20) UNSIGNED NOT NULL,
            order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            source VARCHAR(50) NOT NULL DEFAULT 'woocommerce',
            granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'granted',
            PRIMARY KEY  (id),
            UNIQUE KEY user_ebook (user_id, ebook_id),
            KEY user_id (user_id),
            KEY ebook_id (ebook_id),
            KEY order_id (order_id),
            KEY status (status)
        ) {$charset_collate};";
        dbDelta($sql_access);

        // 3. Table de suivi de lecture et reprise
        $table_reading = $wpdb->prefix . 'secure_ebook_reading';
        $sql_reading = "CREATE TABLE {$table_reading} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            ebook_id BIGINT(20) UNSIGNED NOT NULL,
            last_page INT(10) UNSIGNED NOT NULL DEFAULT 1,
            progress DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            last_read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_ebook (user_id, ebook_id),
            KEY user_id (user_id),
            KEY ebook_id (ebook_id)
        ) {$charset_collate};";
        dbDelta($sql_reading);

        // 4. Table des jetons de lecture temporaires (SHA-256)
        $table_tokens = $wpdb->prefix . 'secure_ebook_tokens';
        $sql_tokens = "CREATE TABLE {$table_tokens} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            token_hash VARCHAR(64) NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            ebook_id BIGINT(20) UNSIGNED NOT NULL,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            user_agent VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            revoked TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY token_hash (token_hash),
            KEY user_ebook (user_id, ebook_id),
            KEY expires_at (expires_at)
        ) {$charset_collate};";
        dbDelta($sql_tokens);

        // 5. Table des journaux d'audit et détection d'intrusions (anti-IDOR)
        $table_logs = $wpdb->prefix . 'secure_ebook_logs';
        $sql_logs = "CREATE TABLE {$table_logs} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            ebook_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            event_type VARCHAR(50) NOT NULL,
            severity VARCHAR(20) NOT NULL DEFAULT 'info',
            message TEXT DEFAULT NULL,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY user_id (user_id),
            KEY severity (severity),
            KEY created_at (created_at)
        ) {$charset_collate};";
        dbDelta($sql_logs);
    }

    /**
     * Crée le répertoire hermétique de stockage des fichiers PDF
     * avec protection Apache (.htaccess) et protection PHP (index.php)
     */
    public static function create_secure_vault_directory() {
        $upload_dir = wp_upload_dir();
        $vault_dir = trailingslashit($upload_dir['basedir']) . 'secure-ebooks-vault';

        if (!file_exists($vault_dir)) {
            wp_mkdir_p($vault_dir);
        }

        // Fichier index.php pour interdire le listage de répertoire
        $index_file = $vault_dir . '/index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, "<?php\n// Silence is golden.\nexit;\n");
        }

        // Fichier .htaccess ultra-strict pour Apache/LiteSpeed
        $htaccess_file = $vault_dir . '/.htaccess';
        $htaccess_content = "# Secure Ebook Reader - Vault Protection Directive\n" .
            "<IfModule mod_authz_core.c>\n" .
            "    Require all denied\n" .
            "</IfModule>\n" .
            "<IfModule !mod_authz_core.c>\n" .
            "    Order Deny,Allow\n" .
            "    Deny from all\n" .
            "</IfModule>\n" .
            "Options -Indexes -ExecCGI\n" .
            "<FilesMatch \".*\">\n" .
            "    Order Deny,Allow\n" .
            "    Deny from all\n" .
            "</FilesMatch>\n";

        file_put_contents($htaccess_file, $htaccess_content);
    }

    /**
     * Initialise les réglages par défaut s'ils n'existent pas encore
     */
    private static function init_default_options() {
        if (false === get_option('secure_ebook_settings')) {
            $defaults = [
                // Général
                'order_trigger_status'  => 'completed', // 'completed' ou 'both' (processing + completed)
                'auto_create_endpoint'  => 'yes',
                'custom_reader_slug'    => 'lecture-ebook',
                'library_endpoint_slug' => 'mes-ebooks',

                // Sécurité
                'token_ttl_minutes'     => 15,
                'heartbeat_interval_sec'=> 300, // rafraîchissement toutes les 5 min
                'block_print'           => 'yes',
                'block_copy'            => 'yes',
                'block_inspect'         => 'yes',
                'log_security_events'   => 'yes',

                // Filigrane dynamique
                'watermark_enabled'     => 'yes',
                'watermark_text'        => '{user_name} ({user_email}) — IP: {ip} — {date}',
                'watermark_opacity'     => 0.16,
                'watermark_color'       => '#334155',
                'watermark_size'        => 15,
                'watermark_canvas'      => 'yes', // incrustation directe dans les pixels canvas

                // Lecteur
                'reader_default_theme'  => 'dark', // 'dark', 'light', 'sepia'
                'remember_last_page'    => 'yes',
                'progress_save_interval'=> 15, // secondes
                'default_zoom'          => 'page-width', // 'page-fit', 'page-width', '1.0'
            ];
            update_option('secure_ebook_settings', $defaults);
        }
    }
}
