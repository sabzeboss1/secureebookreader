<?php
/**
 * Administration WordPress pour Secure Ebook Reader
 *
 * Gère le tableau de bord KPI, la bibliothèque d'ebooks, l'octroi/révocation des accès,
 * les journaux d'audit et les réglages de sécurité.
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('ABSPATH')) {
    exit;
}

class Secure_Ebook_Admin {

    /**
     * Enregistre les hooks d'administration
     */
    public function register_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Traitement des requêtes POST d'administration
        add_action('admin_post_secure_ebook_save_ebook', [$this, 'handle_save_ebook']);
        add_action('admin_post_secure_ebook_delete_ebook', [$this, 'handle_delete_ebook']);
        add_action('admin_post_secure_ebook_grant_access', [$this, 'handle_grant_access']);
        add_action('admin_post_secure_ebook_revoke_access', [$this, 'handle_revoke_access']);
        add_action('admin_post_secure_ebook_save_settings', [$this, 'handle_save_settings']);
    }

    /**
     * Déclaration des menus dans l'administration WordPress
     */
    public function add_admin_menus() {
        $parent_slug = 'secure-ebook-reader';

        add_menu_page(
            __('Secure Ebook Reader', 'secure-ebook-reader'),
            __('Secure Ebooks', 'secure-ebook-reader'),
            'manage_options',
            $parent_slug,
            [$this, 'render_dashboard_page'],
            'dashicons-book-alt',
            56
        );

        add_submenu_page(
            $parent_slug,
            __('Tableau de bord — Secure Ebook', 'secure-ebook-reader'),
            __('Tableau de bord', 'secure-ebook-reader'),
            'manage_options',
            $parent_slug,
            [$this, 'render_dashboard_page']
        );

        add_submenu_page(
            $parent_slug,
            __('Livres Numériques Protégés', 'secure-ebook-reader'),
            __('Tous les Ebooks', 'secure-ebook-reader'),
            'manage_options',
            'secure-ebook-list',
            [$this, 'render_ebooks_page']
        );

        add_submenu_page(
            $parent_slug,
            __('Ajouter un Ebook Protégé', 'secure-ebook-reader'),
            __('Ajouter un Ebook', 'secure-ebook-reader'),
            'manage_options',
            'secure-ebook-edit',
            [$this, 'render_ebook_edit_page']
        );

        add_submenu_page(
            $parent_slug,
            __('Gestion des Accès Clients', 'secure-ebook-reader'),
            __('Gestion des Accès', 'secure-ebook-reader'),
            'manage_options',
            'secure-ebook-access',
            [$this, 'render_access_page']
        );

        add_submenu_page(
            $parent_slug,
            __('Journal d\'Audit de Sécurité', 'secure-ebook-reader'),
            __('Journaux Sécurité', 'secure-ebook-reader'),
            'manage_options',
            'secure-ebook-logs',
            [$this, 'render_logs_page']
        );

        add_submenu_page(
            $parent_slug,
            __('Réglages — Secure Ebook Reader', 'secure-ebook-reader'),
            __('Réglages', 'secure-ebook-reader'),
            'manage_options',
            'secure-ebook-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Charge les feuilles de style et scripts d'administration
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'secure-ebook') === false && strpos($hook, 'post.php') === false && strpos($hook, 'post-new.php') === false) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'secure-ebook-admin-style',
            SECURE_EBOOK_URL . 'assets/admin/css/admin.css',
            [],
            SECURE_EBOOK_VERSION
        );

        wp_enqueue_script(
            'secure-ebook-admin-script',
            SECURE_EBOOK_URL . 'assets/admin/js/admin.js',
            ['jquery'],
            SECURE_EBOOK_VERSION,
            true
        );

        wp_localize_script('secure-ebook-admin-script', 'secureEbookAdmin', [
            'ajax_url'     => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('secure_ebook_admin_nonce'),
            'confirm_del'  => esc_html__('Êtes-vous sûr de vouloir supprimer définitivement cet ebook ? Le fichier physique sera également détruit.', 'secure-ebook-reader'),
            'confirm_rev'  => esc_html__('Révoquer immédiatement l\'accès de ce client ?', 'secure-ebook-reader'),
            'media_title'  => esc_html__('Sélectionner la couverture de l\'ebook', 'secure-ebook-reader'),
            'media_button' => esc_html__('Utiliser cette image', 'secure-ebook-reader'),
        ]);
    }

    /**
     * Rendu des pages de vue d'administration
     */
    public function render_dashboard_page() {
        include SECURE_EBOOK_PATH . 'templates/admin/dashboard.php';
    }

    public function render_ebooks_page() {
        include SECURE_EBOOK_PATH . 'templates/admin/ebooks-list.php';
    }

    public function render_ebook_edit_page() {
        include SECURE_EBOOK_PATH . 'templates/admin/ebook-edit.php';
    }

    public function render_access_page() {
        include SECURE_EBOOK_PATH . 'templates/admin/access-list.php';
    }

    public function render_logs_page() {
        include SECURE_EBOOK_PATH . 'templates/admin/logs.php';
    }

    public function render_settings_page() {
        include SECURE_EBOOK_PATH . 'templates/admin/settings.php';
    }

    /**
     * Traite l'enregistrement d'un ebook (Création ou Mise à jour)
     */
    public function handle_save_ebook() {
        check_admin_referer('secure_ebook_save_action', 'secure_ebook_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Droits insuffisants.', 'secure-ebook-reader'));
        }

        $id = isset($_POST['ebook_id']) ? absint($_POST['ebook_id']) : 0;
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $author = sanitize_text_field(wp_unslash($_POST['author'] ?? ''));
        $description = wp_kses_post(wp_unslash($_POST['description'] ?? ''));
        $product_id = absint($_POST['product_id'] ?? 0);
        $access_duration = sanitize_text_field(wp_unslash($_POST['access_duration'] ?? 'permanent'));
        $cover_image_id = absint($_POST['cover_image_id'] ?? 0);
        $status = sanitize_text_field(wp_unslash($_POST['status'] ?? 'active'));

        if (empty($title)) {
            wp_redirect(add_query_arg(['page' => 'secure-ebook-edit', 'error' => 'missing_title'], admin_url('admin.php')));
            exit;
        }

        // Cas de la création : un fichier PDF est obligatoire
        if (!$id) {
            if (empty($_FILES['ebook_file']['tmp_name'])) {
                wp_redirect(add_query_arg(['page' => 'secure-ebook-edit', 'error' => 'missing_file'], admin_url('admin.php')));
                exit;
            }

            $upload_result = Secure_Ebook_Ebook::handle_secure_upload($_FILES['ebook_file']);
            if (is_wp_error($upload_result)) {
                wp_die(esc_html($upload_result->get_error_message()));
            }

            $ebook_data = [
                'title'           => $title,
                'author'          => $author,
                'description'     => $description,
                'product_id'      => $product_id,
                'file_name'       => $upload_result['file_name'],
                'file_path'       => $upload_result['file_path'],
                'file_hash'       => $upload_result['file_hash'],
                'file_size'       => $upload_result['file_size'],
                'page_count'      => $upload_result['page_count'],
                'access_duration' => $access_duration,
                'cover_image_id'  => $cover_image_id,
                'status'          => $status,
            ];

            $new_id = Secure_Ebook_Ebook::create($ebook_data);
            if ($new_id && $product_id > 0) {
                update_post_meta($product_id, '_secure_ebook_id', $new_id);
            }

            wp_redirect(add_query_arg(['page' => 'secure-ebook-list', 'message' => 'created'], admin_url('admin.php')));
            exit;
        } else {
            // Modification
            $update_data = [
                'title'           => $title,
                'author'          => $author,
                'description'     => $description,
                'product_id'      => $product_id,
                'access_duration' => $access_duration,
                'cover_image_id'  => $cover_image_id,
                'status'          => $status,
            ];

            // Si un nouveau fichier PDF a été soumis
            if (!empty($_FILES['ebook_file']['tmp_name'])) {
                $upload_result = Secure_Ebook_Ebook::handle_secure_upload($_FILES['ebook_file']);
                if (is_wp_error($upload_result)) {
                    wp_die(esc_html($upload_result->get_error_message()));
                }

                $update_data['file_name']  = $upload_result['file_name'];
                $update_data['file_path']  = $upload_result['file_path'];
                $update_data['file_hash']  = $upload_result['file_hash'];
                $update_data['file_size']  = $upload_result['file_size'];
                $update_data['page_count'] = $upload_result['page_count'];
            }

            Secure_Ebook_Ebook::update($id, $update_data);

            if ($product_id > 0) {
                update_post_meta($product_id, '_secure_ebook_id', $id);
            }

            wp_redirect(add_query_arg(['page' => 'secure-ebook-list', 'message' => 'updated'], admin_url('admin.php')));
            exit;
        }
    }

    /**
     * Traite la suppression définitive d'un ebook
     */
    public function handle_delete_ebook() {
        check_admin_referer('secure_ebook_delete_action', 'secure_ebook_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Droits insuffisants.', 'secure-ebook-reader'));
        }

        $id = isset($_GET['ebook_id']) ? absint($_GET['ebook_id']) : 0;
        if ($id) {
            Secure_Ebook_Ebook::delete($id);
        }

        wp_redirect(add_query_arg(['page' => 'secure-ebook-list', 'message' => 'deleted'], admin_url('admin.php')));
        exit;
    }

    /**
     * Traite l'octroi manuel d'un accès
     */
    public function handle_grant_access() {
        check_admin_referer('secure_ebook_grant_action', 'secure_ebook_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Droits insuffisants.', 'secure-ebook-reader'));
        }

        $user_id  = absint($_POST['user_id'] ?? 0);
        $ebook_id = absint($_POST['ebook_id'] ?? 0);
        $duration = sanitize_text_field(wp_unslash($_POST['duration'] ?? 'permanent'));

        if ($user_id && $ebook_id) {
            Secure_Ebook_Access::grant_access($user_id, $ebook_id, 0, 'manual', $duration);
        }

        wp_redirect(add_query_arg(['page' => 'secure-ebook-access', 'message' => 'granted'], admin_url('admin.php')));
        exit;
    }

    /**
     * Traite la révocation manuelle d'un accès
     */
    public function handle_revoke_access() {
        check_admin_referer('secure_ebook_revoke_action', 'secure_ebook_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Droits insuffisants.', 'secure-ebook-reader'));
        }

        $user_id  = absint($_GET['user_id'] ?? 0);
        $ebook_id = absint($_GET['ebook_id'] ?? 0);

        if ($user_id && $ebook_id) {
            Secure_Ebook_Access::revoke_access($user_id, $ebook_id, esc_html__('Révocation manuelle par l\'administrateur', 'secure-ebook-reader'));
        }

        wp_redirect(add_query_arg(['page' => 'secure-ebook-access', 'message' => 'revoked'], admin_url('admin.php')));
        exit;
    }

    /**
     * Traite l'enregistrement des réglages du plugin
     */
    public function handle_save_settings() {
        check_admin_referer('secure_ebook_settings_action', 'secure_ebook_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Droits insuffisants.', 'secure-ebook-reader'));
        }

        $settings = [
            // Général
            'order_trigger_status'    => sanitize_text_field(wp_unslash($_POST['order_trigger_status'] ?? 'completed')),
            'custom_reader_slug'      => sanitize_title(wp_unslash($_POST['custom_reader_slug'] ?? 'lecture-ebook')),
            'library_endpoint_slug'   => sanitize_title(wp_unslash($_POST['library_endpoint_slug'] ?? 'mes-ebooks')),

            // Sécurité
            'token_ttl_minutes'       => max(1, absint($_POST['token_ttl_minutes'] ?? 15)),
            'block_print'             => isset($_POST['block_print']) ? 'yes' : 'no',
            'block_copy'              => isset($_POST['block_copy']) ? 'yes' : 'no',
            'block_inspect'           => isset($_POST['block_inspect']) ? 'yes' : 'no',
            'log_security_events'     => isset($_POST['log_security_events']) ? 'yes' : 'no',
            'clean_uninstall_on_delete' => isset($_POST['clean_uninstall_on_delete']) ? 'yes' : 'no',

            // Filigrane
            'watermark_enabled'       => isset($_POST['watermark_enabled']) ? 'yes' : 'no',
            'watermark_text'          => sanitize_text_field(wp_unslash($_POST['watermark_text'] ?? '{user_name} ({user_email}) — IP: {ip} — {date}')),
            'watermark_opacity'       => max(0.05, min(1.0, floatval($_POST['watermark_opacity'] ?? 0.16))),
            'watermark_color'         => sanitize_hex_color($_POST['watermark_color'] ?? '#334155'),
            'watermark_size'          => max(10, min(36, absint($_POST['watermark_size'] ?? 15))),
            'watermark_canvas'        => isset($_POST['watermark_canvas']) ? 'yes' : 'no',

            // Lecteur
            'reader_default_theme'    => sanitize_text_field(wp_unslash($_POST['reader_default_theme'] ?? 'dark')),
            'remember_last_page'      => isset($_POST['remember_last_page']) ? 'yes' : 'no',
            'progress_save_interval'  => max(5, min(120, absint($_POST['progress_save_interval'] ?? 15))),
            'default_zoom'            => sanitize_text_field(wp_unslash($_POST['default_zoom'] ?? 'page-width')),
        ];

        update_option('secure_ebook_settings', $settings);

        // Flusher les réécritures au cas où les slugs ont changé
        flush_rewrite_rules();

        wp_redirect(add_query_arg(['page' => 'secure-ebook-settings', 'message' => 'settings_saved'], admin_url('admin.php')));
        exit;
    }
}
