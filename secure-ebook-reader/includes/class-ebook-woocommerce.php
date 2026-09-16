<?php
/**
 * Intégration WooCommerce
 *
 * Gère les métadonnées produit, les hooks de commande (attribution sur completed/processing,
 * révocation immédiate sur refunded/cancelled), et l'onglet Mon Compte "Mes Ebooks".
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('ABSPATH')) {
    exit;
}

class Secure_Ebook_WooCommerce {

    /**
     * Enregistre les hooks WooCommerce
     */
    public function register_hooks() {
        // Garde-fou : vérifie si WooCommerce est actif
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Endpoint "Mes Ebooks" dans Mon Compte
        add_action('init', [$this, 'add_my_account_endpoint']);
        add_filter('woocommerce_account_menu_items', [$this, 'add_my_account_menu_item']);
        add_action('woocommerce_account_mes-ebooks_endpoint', [$this, 'render_my_account_ebooks']);

        // Panneau d'administration produit
        add_filter('woocommerce_product_data_tabs', [$this, 'add_product_data_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'render_product_data_panel']);
        add_action('woocommerce_process_product_meta', [$this, 'save_product_meta']);

        // Écouteurs de cycle de vie des commandes
        add_action('woocommerce_order_status_completed', [$this, 'handle_order_completed'], 10, 1);
        add_action('woocommerce_order_status_processing', [$this, 'handle_order_processing'], 10, 1);
        add_action('woocommerce_order_status_refunded', [$this, 'handle_order_refunded'], 10, 1);
        add_action('woocommerce_order_status_cancelled', [$this, 'handle_order_cancelled'], 10, 1);
    }

    /**
     * Ajoute le rewrite endpoint pour Mon Compte
     */
    public function add_my_account_endpoint() {
        $settings = get_option('secure_ebook_settings', []);
        $endpoint_slug = !empty($settings['library_endpoint_slug']) ? sanitize_title($settings['library_endpoint_slug']) : 'mes-ebooks';

        add_rewrite_endpoint($endpoint_slug, EP_ROOT | EP_PAGES);
    }

    /**
     * Ajoute le lien "Mes Ebooks" dans le menu Mon Compte WooCommerce
     */
    public function add_my_account_menu_item($items) {
        $new_items = [];
        foreach ($items as $key => $value) {
            $new_items[$key] = $value;
            // Insérer après les commandes
            if ($key === 'orders') {
                $new_items['mes-ebooks'] = esc_html__('Mes Livres Numériques', 'secure-ebook-reader');
            }
        }

        if (!isset($new_items['mes-ebooks'])) {
            $new_items['mes-ebooks'] = esc_html__('Mes Livres Numériques', 'secure-ebook-reader');
        }

        return $new_items;
    }

    /**
     * Rendu de la page "Mes Ebooks" dans Mon Compte
     */
    public function render_my_account_ebooks() {
        $user_id = get_current_user_id();
        $ebooks  = Secure_Ebook_Access::get_user_ebooks($user_id);

        wp_enqueue_style('secure-ebook-library-style');
        wp_enqueue_script('secure-ebook-library-app');

        include SECURE_EBOOK_PATH . 'templates/my-ebooks.php';
    }

    /**
     * Ajoute l'onglet Secure Ebook dans l'édition produit WooCommerce
     */
    public function add_product_data_tab($tabs) {
        $tabs['secure_ebook'] = [
            'label'    => esc_html__('Ebook Sécurisé', 'secure-ebook-reader'),
            'target'   => 'secure_ebook_product_data',
            'class'    => ['show_if_simple', 'show_if_variable'],
            'priority' => 60,
        ];
        return $tabs;
    }

    /**
     * Rendu du panneau dans la fiche produit
     */
    public function render_product_data_panel() {
        global $post;
        $product_id = $post->ID;

        $linked_ebook_id = get_post_meta($product_id, '_secure_ebook_id', true);
        $duration        = get_post_meta($product_id, '_secure_ebook_duration', true) ?: 'default';

        $all_ebooks = Secure_Ebook_Ebook::get_all(['limit' => 200, 'status' => 'active']);
        ?>
        <div id="secure_ebook_product_data" class="panel woocommerce_options_panel hidden">
            <div class="options_group">
                <p class="form-field">
                    <label for="_secure_ebook_id"><?php esc_html_e('Associer un Ebook sécurisé', 'secure-ebook-reader'); ?></label>
                    <select id="_secure_ebook_id" name="_secure_ebook_id" class="select short">
                        <option value="0"><?php esc_html_e('— Aucun (Produit standard) —', 'secure-ebook-reader'); ?></option>
                        <?php foreach ($all_ebooks as $ebook) : ?>
                            <option value="<?php echo esc_attr($ebook->id); ?>" <?php selected($linked_ebook_id, $ebook->id); ?>>
                                <?php echo esc_html($ebook->title . ' (' . $ebook->page_count . ' pages)'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p class="form-field">
                    <label for="_secure_ebook_duration"><?php esc_html_e('Durée de l\'accès client', 'secure-ebook-reader'); ?></label>
                    <select id="_secure_ebook_duration" name="_secure_ebook_duration" class="select short">
                        <option value="default" <?php selected($duration, 'default'); ?>><?php esc_html_e('Selon le réglage de l\'ebook', 'secure-ebook-reader'); ?></option>
                        <option value="permanent" <?php selected($duration, 'permanent'); ?>><?php esc_html_e('Permanent (À vie)', 'secure-ebook-reader'); ?></option>
                        <option value="30d" <?php selected($duration, '30d'); ?>><?php esc_html_e('30 jours', 'secure-ebook-reader'); ?></option>
                        <option value="90d" <?php selected($duration, '90d'); ?>><?php esc_html_e('90 jours', 'secure-ebook-reader'); ?></option>
                        <option value="1y" <?php selected($duration, '1y'); ?>><?php esc_html_e('1 an', 'secure-ebook-reader'); ?></option>
                    </select>
                    <span class="description"><?php esc_html_e('Détermine la validité de l\'accès accordé à l\'acheteur après paiement.', 'secure-ebook-reader'); ?></span>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Sauvegarde des métadonnées du produit
     */
    public function save_product_meta($post_id) {
        $ebook_id = isset($_POST['_secure_ebook_id']) ? absint($_POST['_secure_ebook_id']) : 0;
        $duration = isset($_POST['_secure_ebook_duration']) ? sanitize_text_field(wp_unslash($_POST['_secure_ebook_duration'])) : 'default';

        update_post_meta($post_id, '_secure_ebook_id', $ebook_id);
        update_post_meta($post_id, '_secure_ebook_duration', $duration);

        // Mettre à jour la liaison bidirectionnelle dans la table des ebooks
        if ($ebook_id > 0) {
            Secure_Ebook_Ebook::update($ebook_id, ['product_id' => $post_id]);
        }
    }

    /**
     * Déclencheur sur commande terminée (Completed)
     */
    public function handle_order_completed($order_id) {
        $this->process_order_grant($order_id);
    }

    /**
     * Déclencheur sur commande en cours (Processing)
     */
    public function handle_order_processing($order_id) {
        $settings = get_option('secure_ebook_settings', []);
        $trigger  = !empty($settings['order_trigger_status']) ? $settings['order_trigger_status'] : 'completed';

        if ($trigger === 'both') {
            $this->process_order_grant($order_id);
        }
    }

    /**
     * Traite l'attribution des droits pour une commande
     */
    private function process_order_grant($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $customer_id = $order->get_customer_id();
        // Si commande invitée sans compte utilisateur, pas d'attribution automatique directe possible
        if (!$customer_id) {
            return;
        }

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $ebook_id   = get_post_meta($product_id, '_secure_ebook_id', true);

            // Recherche alternative par liaison directe dans la table
            if (!$ebook_id) {
                $ebook = Secure_Ebook_Ebook::get_by_product($product_id);
                if ($ebook) {
                    $ebook_id = $ebook->id;
                }
            }

            if ($ebook_id) {
                $duration_override = get_post_meta($product_id, '_secure_ebook_duration', true);
                $duration = ($duration_override && $duration_override !== 'default') ? $duration_override : null;

                Secure_Ebook_Access::grant_access(
                    $customer_id,
                    $ebook_id,
                    $order_id,
                    'woocommerce',
                    $duration
                );
            }
        }
    }

    /**
     * Révocation immédiate lors d'un remboursement (Refunded)
     */
    public function handle_order_refunded($order_id) {
        $revoked_count = Secure_Ebook_Access::revoke_by_order($order_id);

        if ($revoked_count > 0) {
            Secure_Ebook_Logger::log(
                'refund_revocation',
                'warning',
                sprintf('Commande #%d remboursée : %d accès révoqué(s) et tous les jetons actifs invalidés immédiatement.', $order_id, $revoked_count)
            );
        }
    }

    /**
     * Révocation lors d'une annulation de commande (Cancelled)
     */
    public function handle_order_cancelled($order_id) {
        Secure_Ebook_Access::revoke_by_order($order_id);
    }
}
