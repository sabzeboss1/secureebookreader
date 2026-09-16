<?php
/**
 * Script exécuté lors de la suppression définitive du plugin (Uninstall)
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Vérifier si l'administrateur a coché "Supprimer toutes les données lors de la désinstallation"
$settings = get_option('secure_ebook_settings', []);
$clean_uninstall = isset($settings['clean_uninstall_on_delete']) && $settings['clean_uninstall_on_delete'] === 'yes';

if ($clean_uninstall) {
    global $wpdb;

    // Suppression des tables personnalisées
    $tables = [
        $wpdb->prefix . 'secure_ebook_logs',
        $wpdb->prefix . 'secure_ebook_tokens',
        $wpdb->prefix . 'secure_ebook_reading',
        $wpdb->prefix . 'secure_ebook_access',
        $wpdb->prefix . 'secure_ebooks',
    ];

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table};");
    }

    // Suppression des options
    delete_option('secure_ebook_settings');
    delete_option('secure_ebook_db_version');
    delete_transient('secure_ebook_flush_rewrite_rules');
}
