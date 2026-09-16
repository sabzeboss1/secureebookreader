<?php
/**
 * Classe maîtresse d'orchestration du plugin
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('ABSPATH')) {
    exit;
}

class Secure_Ebook_Plugin {

    /**
     * Instance unique (Singleton)
     *
     * @var Secure_Ebook_Plugin|null
     */
    private static $instance = null;

    /**
     * Composants internes
     */
    public $security;
    public $access;
    public $streamer;
    public $logger;
    public $progress;
    public $api;
    public $woocommerce;
    public $admin;

    /**
     * Récupère l'instance unique
     *
     * @return Secure_Ebook_Plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructeur privé
     */
    private function __construct() {
        $this->init_components();
    }

    /**
     * Initialise les sous-modules du plugin
     */
    private function init_components() {
        $this->logger      = new Secure_Ebook_Logger();
        $this->security    = new Secure_Ebook_Security();
        $this->access      = new Secure_Ebook_Access();
        $this->streamer    = new Secure_Ebook_Streamer();
        $this->progress    = new Secure_Ebook_Progress();
        $this->api         = new Secure_Ebook_API();
        $this->woocommerce = new Secure_Ebook_WooCommerce();

        if (is_admin()) {
            $this->admin = new Secure_Ebook_Admin();
        }
    }

    /**
     * Exécute les liaisons de hooks WordPress
     */
    public function run() {
        // Chargement des traductions
        add_action('init', [$this, 'load_textdomain']);

        // Enregistrement des réécritures d'URL personnalisées
        add_action('init', [$this, 'register_rewrite_endpoints']);
        add_action('template_redirect', [$this, 'handle_reader_template_redirect']);

        // Vérification du transient de flush rewrite
        add_action('init', [$this, 'maybe_flush_rewrites'], 999);

        // Déclaration des shortcodes
        add_shortcode('secure_ebook_library', [$this, 'render_library_shortcode']);
        add_shortcode('secure_ebook_reader', [$this, 'render_reader_shortcode']);

        // Enregistrement des scripts et styles publics
        add_action('wp_enqueue_scripts', [$this, 'register_public_assets']);

        // Déléguer aux modules leurs propres hooks
        $this->api->register_hooks();
        $this->woocommerce->register_hooks();

        if (is_admin() && $this->admin) {
            $this->admin->register_hooks();
        }
    }

    /**
     * Charge le textdomain pour les traductions
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'secure-ebook-reader',
            false,
            dirname(SECURE_EBOOK_BASENAME) . '/languages/'
        );
    }

    /**
     * Enregistre les endpoints de réécriture
     */
    public function register_rewrite_endpoints() {
        $settings = get_option('secure_ebook_settings', []);
        $reader_slug = !empty($settings['custom_reader_slug']) ? sanitize_title($settings['custom_reader_slug']) : 'lecture-ebook';

        // Endpoint autonome: /lecture-ebook/{id}
        add_rewrite_rule(
            '^' . $reader_slug . '/([0-9]+)/?$',
            'index.php?secure_ebook_reader_id=$matches[1]',
            'top'
        );
        add_rewrite_tag('%secure_ebook_reader_id%', '([0-9]+)');
    }

    /**
     * Redirige ou sert le template du lecteur autonome
     */
    public function handle_reader_template_redirect() {
        $ebook_id = get_query_var('secure_ebook_reader_id');

        if ($ebook_id) {
            $ebook_id = absint($ebook_id);

            // Vérifier si l'utilisateur est connecté
            if (!is_user_logged_in()) {
                auth_redirect();
                exit;
            }

            // Vérifier les droits d'accès
            $user_id = get_current_user_id();
            if (!Secure_Ebook_Access::can_read($user_id, $ebook_id)) {
                wp_die(
                    esc_html__("Accès refusé. Vous n'avez pas les droits nécessaires pour consulter cet ouvrage.", 'secure-ebook-reader'),
                    esc_html__('Accès interdit (403)', 'secure-ebook-reader'),
                    ['response' => 403, 'back_link' => true]
                );
            }

            // Charger le template du lecteur
            $template = SECURE_EBOOK_PATH . 'templates/reader.php';
            if (file_exists($template)) {
                include $template;
                exit;
            }
        }
    }

    /**
     * Flushe les règles si un transient est actif
     */
    public function maybe_flush_rewrites() {
        if (get_transient('secure_ebook_flush_rewrite_rules')) {
            flush_rewrite_rules();
            delete_transient('secure_ebook_flush_rewrite_rules');
        }
    }

    /**
     * Enregistre les assets publics (CSS et JS)
     */
    public function register_public_assets() {
        // PDF.js Engine & Worker
        wp_register_script(
            'secure-ebook-pdfjs',
            SECURE_EBOOK_URL . 'assets/vendor/pdfjs/pdf.min.js',
            [],
            '3.11.174',
            true
        );

        // Scripts du Lecteur personnalisé
        wp_register_script(
            'secure-ebook-reader-app',
            SECURE_EBOOK_URL . 'assets/public/js/reader.js',
            ['secure-ebook-pdfjs'],
            SECURE_EBOOK_VERSION,
            true
        );

        // Styles du Lecteur
        wp_register_style(
            'secure-ebook-reader-style',
            SECURE_EBOOK_URL . 'assets/public/css/reader.css',
            [],
            SECURE_EBOOK_VERSION
        );

        // Styles et scripts de la bibliothèque Mon Compte
        wp_register_style(
            'secure-ebook-library-style',
            SECURE_EBOOK_URL . 'assets/public/css/library.css',
            [],
            SECURE_EBOOK_VERSION
        );

        wp_register_script(
            'secure-ebook-library-app',
            SECURE_EBOOK_URL . 'assets/public/js/library.js',
            ['jquery'],
            SECURE_EBOOK_VERSION,
            true
        );
    }

    /**
     * Rendu du shortcode [secure_ebook_library]
     */
    public function render_library_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="secure-ebook-notice-login"><p>' .
                sprintf(
                    esc_html__('Veuillez vous %sconnecter%s pour accéder à vos livres numériques protégés.', 'secure-ebook-reader'),
                    '<a href="' . esc_url(wp_login_url(get_permalink())) . '">',
                    '</a>'
                ) .
                '</p></div>';
        }

        wp_enqueue_style('secure-ebook-library-style');
        wp_enqueue_script('secure-ebook-library-app');

        ob_start();
        $user_id = get_current_user_id();
        $ebooks = Secure_Ebook_Access::get_user_ebooks($user_id);
        include SECURE_EBOOK_PATH . 'templates/my-ebooks.php';
        return ob_get_clean();
    }

    /**
     * Rendu du shortcode [secure_ebook_reader id="..."]
     */
    public function render_reader_shortcode($atts) {
        $atts = shortcode_atts(['id' => 0], $atts, 'secure_ebook_reader');
        $ebook_id = absint($atts['id']);

        if (!$ebook_id) {
            return '<p class="secure-ebook-error">' . esc_html__('ID d\'ebook invalide.', 'secure-ebook-reader') . '</p>';
        }

        if (!is_user_logged_in()) {
            return '<p class="secure-ebook-error">' . esc_html__('Vous devez être connecté pour lire cet ebook.', 'secure-ebook-reader') . '</p>';
        }

        $user_id = get_current_user_id();
        if (!Secure_Ebook_Access::can_read($user_id, $ebook_id)) {
            return '<p class="secure-ebook-error">' . esc_html__('Vous ne disposez pas d\'un accès valide pour cet ebook.', 'secure-ebook-reader') . '</p>';
        }

        ob_start();
        include SECURE_EBOOK_PATH . 'templates/reader.php';
        return ob_get_clean();
    }
}
