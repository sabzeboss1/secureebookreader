<?php
/**
 * Gestionnaire de désactivation du plugin
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('ABSPATH')) {
    exit;
}

class Secure_Ebook_Deactivator {

    /**
     * Nettoyage lors de la désactivation (règles de réécriture, transients)
     */
    public static function deactivate() {
        // Supprimer les transients
        delete_transient('secure_ebook_flush_rewrite_rules');

        // Réinitialiser les règles de réécriture WordPress
        flush_rewrite_rules();
    }
}
