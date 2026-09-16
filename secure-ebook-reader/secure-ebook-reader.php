<?php
/**
 * Plugin Name:       Secure Ebook Reader
 * Plugin URI:        https://github.com/secure-ebook-reader
 * Description:       Solution d'élite pour la vente et la lecture sécurisée d'ebooks PDF en ligne sans téléchargement (Streaming RFC 7233, protection anti-IDOR, filigrane dynamique dual-layer, intégration WooCommerce).
 * Version:           1.0.0
 * Author:            Antigravity
 * Author URI:        https://antigravity.ai
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       secure-ebook-reader
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité : interdire l'accès direct
}

// Définition des constantes globales du plugin
if (!defined('SECURE_EBOOK_VERSION')) {
    define('SECURE_EBOOK_VERSION', '1.0.0');
}
if (!defined('SECURE_EBOOK_FILE')) {
    define('SECURE_EBOOK_FILE', __FILE__);
}
if (!defined('SECURE_EBOOK_PATH')) {
    define('SECURE_EBOOK_PATH', plugin_dir_path(__FILE__));
}
if (!defined('SECURE_EBOOK_URL')) {
    define('SECURE_EBOOK_URL', plugin_dir_url(__FILE__));
}
if (!defined('SECURE_EBOOK_BASENAME')) {
    define('SECURE_EBOOK_BASENAME', plugin_basename(__FILE__));
}

// Autoloader robuste avec Class Map direct & résolution dynamique
spl_autoload_register(function ($class) {
    $prefix = 'Secure_Ebook_';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Mapping statique haute performance (évite tout problème de casse ou de convention de nom de fichier sur Linux)
    $class_map = [
        'Secure_Ebook_Plugin'      => SECURE_EBOOK_PATH . 'includes/class-plugin.php',
        'Secure_Ebook_Activator'   => SECURE_EBOOK_PATH . 'includes/class-activator.php',
        'Secure_Ebook_Deactivator' => SECURE_EBOOK_PATH . 'includes/class-deactivator.php',
        'Secure_Ebook_Ebook'       => SECURE_EBOOK_PATH . 'includes/class-ebook.php',
        'Secure_Ebook_Access'      => SECURE_EBOOK_PATH . 'includes/class-ebook-access.php',
        'Secure_Ebook_Security'    => SECURE_EBOOK_PATH . 'includes/class-ebook-security.php',
        'Secure_Ebook_Streamer'    => SECURE_EBOOK_PATH . 'includes/class-ebook-streamer.php',
        'Secure_Ebook_Logger'      => SECURE_EBOOK_PATH . 'includes/class-ebook-logger.php',
        'Secure_Ebook_Progress'    => SECURE_EBOOK_PATH . 'includes/class-ebook-progress.php',
        'Secure_Ebook_API'         => SECURE_EBOOK_PATH . 'includes/class-ebook-api.php',
        'Secure_Ebook_WooCommerce' => SECURE_EBOOK_PATH . 'includes/class-ebook-woocommerce.php',
        'Secure_Ebook_Admin'       => SECURE_EBOOK_PATH . 'includes/class-ebook-admin.php',
    ];

    if (isset($class_map[$class]) && file_exists($class_map[$class])) {
        require_once $class_map[$class];
        return;
    }

    // Résolution dynamique de secours
    $relative_class = substr($class, $len);
    $slug = strtolower(str_replace('_', '-', $relative_class));

    $candidates = [
        SECURE_EBOOK_PATH . 'includes/class-ebook-' . $slug . '.php',
        SECURE_EBOOK_PATH . 'includes/class-' . $slug . '.php',
    ];

    foreach ($candidates as $file_path) {
        if (file_exists($file_path)) {
            require_once $file_path;
            return;
        }
    }
});

/**
 * Hook d'activation du plugin : initialisation des tables et dossiers sécurisés
 */
function secure_ebook_activate() {
    require_once SECURE_EBOOK_PATH . 'includes/class-activator.php';
    Secure_Ebook_Activator::activate();
}
register_activation_hook(__FILE__, 'secure_ebook_activate');

/**
 * Hook de désactivation du plugin : nettoyage des rewrites et états temporaires
 */
function secure_ebook_deactivate() {
    require_once SECURE_EBOOK_PATH . 'includes/class-deactivator.php';
    Secure_Ebook_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'secure_ebook_deactivate');

/**
 * Initialisation du plugin principal
 */
function secure_ebook_init_plugin() {
    // Vérification de la compatibilité PHP minimale
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>' .
                esc_html__('Secure Ebook Reader requiert au minimum PHP 7.4 pour fonctionner en toute sécurité.', 'secure-ebook-reader') .
                '</p></div>';
        });
        return;
    }

    // Instanciation du plugin central
    $plugin = Secure_Ebook_Plugin::get_instance();
    $plugin->run();
}
add_action('plugins_loaded', 'secure_ebook_init_plugin', 10);
