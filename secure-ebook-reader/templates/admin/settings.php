<?php
/**
 * Page des Réglages par Onglets — Secure Ebook Reader
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('secure_ebook_settings', []);
$active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'general';
?>
<div class="wrap secure-admin-wrap">
    <h1><?php esc_html_e('Réglages — Secure Ebook Reader', 'secure-ebook-reader'); ?></h1>

    <?php if (isset($_GET['message']) && $_GET['message'] === 'settings_saved') : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Les réglages ont été enregistrés avec succès.', 'secure-ebook-reader'); ?></p>
        </div>
    <?php endif; ?>

    <!-- Navigation par Onglets -->
    <h2 class="nav-tab-wrapper">
        <a href="?page=secure-ebook-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            ⚙️ <?php esc_html_e('Général', 'secure-ebook-reader'); ?>
        </a>
        <a href="?page=secure-ebook-settings&tab=security" class="nav-tab <?php echo $active_tab === 'security' ? 'nav-tab-active' : ''; ?>">
            🛡️ <?php esc_html_e('Sécurité & Tokens', 'secure-ebook-reader'); ?>
        </a>
        <a href="?page=secure-ebook-settings&tab=watermark" class="nav-tab <?php echo $active_tab === 'watermark' ? 'nav-tab-active' : ''; ?>">
            💧 <?php esc_html_e('Filigrane Dynamique', 'secure-ebook-reader'); ?>
        </a>
        <a href="?page=secure-ebook-settings&tab=reader" class="nav-tab <?php echo $active_tab === 'reader' ? 'nav-tab-active' : ''; ?>">
            📖 <?php esc_html_e('Lecteur PDF', 'secure-ebook-reader'); ?>
        </a>
        <a href="?page=secure-ebook-settings&tab=server" class="nav-tab <?php echo $active_tab === 'server' ? 'nav-tab-active' : ''; ?>">
            🖥️ <?php esc_html_e('Diagnostic Serveur', 'secure-ebook-reader'); ?>
        </a>
    </h2>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="secure-settings-form">
        <input type="hidden" name="action" value="secure_ebook_save_settings" />
        <?php wp_nonce_field('secure_ebook_settings_action', 'secure_ebook_nonce'); ?>

        <!-- Onglet 1 : Général -->
        <?php if ($active_tab === 'general') : ?>
            <div class="settings-card">
                <h3><?php esc_html_e('Liaison WooCommerce & Navigation', 'secure-ebook-reader'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="order_trigger_status"><?php esc_html_e('Déclencheur d\'attribution d\'accès', 'secure-ebook-reader'); ?></label></th>
                        <td>
                            <select id="order_trigger_status" name="order_trigger_status">
                                <option value="completed" <?php selected($settings['order_trigger_status'] ?? 'completed', 'completed'); ?>>
                                    <?php esc_html_e('Commande terminée uniquement (Recommandé)', 'secure-ebook-reader'); ?>
                                </option>
                                <option value="both" <?php selected($settings['order_trigger_status'] ?? '', 'both'); ?>>
                                    <?php esc_html_e('Commande terminée ET commande en cours de traitement', 'secure-ebook-reader'); ?>
                                </option>
                            </select>
                            <p class="description"><?php esc_html_e('Définit à quel moment exact de la commande WooCommerce le droit de lecture est débloqué pour le client.', 'secure-ebook-reader'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="library_endpoint_slug"><?php esc_html_e('Slug de l\'onglet Mon Compte', 'secure-ebook-reader'); ?></label></th>
                        <td>
                            <code>/mon-compte/</code><input type="text" id="library_endpoint_slug" name="library_endpoint_slug" value="<?php echo esc_attr($settings['library_endpoint_slug'] ?? 'mes-ebooks'); ?>" class="regular-text" /><code>/</code>
                            <p class="description"><?php esc_html_e('Identifiant d\'URL pour accéder à la bibliothèque client dans l\'espace Mon Compte.', 'secure-ebook-reader'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="custom_reader_slug"><?php esc_html_e('Slug de l\'URL du Lecteur', 'secure-ebook-reader'); ?></label></th>
                        <td>
                            <code>/</code><input type="text" id="custom_reader_slug" name="custom_reader_slug" value="<?php echo esc_attr($settings['custom_reader_slug'] ?? 'lecture-ebook'); ?>" class="regular-text" /><code>/{id}/</code>
                            <p class="description"><?php esc_html_e('Structure d\'URL dédiée au lecteur plein écran sécurisé.', 'secure-ebook-reader'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

        <!-- Onglet 2 : Sécurité -->
        <?php elseif ($active_tab === 'security') : ?>
            <div class="settings-card">
                <h3><?php esc_html_e('Politique de Sécurité et Anti-Piratage', 'secure-ebook-reader'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="token_ttl_minutes"><?php esc_html_e('Durée de validité des jetons (minutes)', 'secure-ebook-reader'); ?></label></th>
                        <td>
                            <input type="number" id="token_ttl_minutes" name="token_ttl_minutes" min="2" max="120" value="<?php echo esc_attr($settings['token_ttl_minutes'] ?? 15); ?>" class="small-text" /> minutes
                            <p class="description"><?php esc_html_e('Durée d\'expiration du jeton éphémère de lecture. Un heartbeat en arrière-plan le renouvelle automatiquement tant que le lecteur est ouvert et légitime.', 'secure-ebook-reader'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Mesures de protection du lecteur', 'secure-ebook-reader'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="block_print" value="yes" <?php checked($settings['block_print'] ?? 'yes', 'yes'); ?> />
                                    <?php esc_html_e('Bloquer l\'impression (Ctrl+P, Cmd+P et styles print masqués)', 'secure-ebook-reader'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="block_copy" value="yes" <?php checked($settings['block_copy'] ?? 'yes', 'yes'); ?> />
                                    <?php esc_html_e('Désactiver la sélection de texte et le clic droit (Anti-copie)', 'secure-ebook-reader'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="block_inspect" value="yes" <?php checked($settings['block_inspect'] ?? 'yes', 'yes'); ?> />
                                    <?php esc_html_e('Interdire les raccourcis de sauvegarde rapide (Ctrl+S, Cmd+S, F12)', 'secure-ebook-reader'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="log_security_events" value="yes" <?php checked($settings['log_security_events'] ?? 'yes', 'yes'); ?> />
                                    <?php esc_html_e('Activer le journal d\'audit des tentatives d\'usurpation (Anti-IDOR)', 'secure-ebook-reader'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Désinstallation propre', 'secure-ebook-reader'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="clean_uninstall_on_delete" value="yes" <?php checked($settings['clean_uninstall_on_delete'] ?? 'no', 'yes'); ?> />
                                <span style="color: #b91c1c;"><?php esc_html_e('Supprimer définitivement toutes les tables et données du plugin lors de la suppression de l\'extension.', 'secure-ebook-reader'); ?></span>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

        <!-- Onglet 3 : Filigrane Dynamique -->
        <?php elseif ($active_tab === 'watermark') : ?>
            <div class="settings-card">
                <h3><?php esc_html_e('Incrustation du Filigrane d\'Identité (Dual-Layer Watermark)', 'secure-ebook-reader'); ?></h3>
                <p class="description"><?php esc_html_e('Dissuade les captures d\'écran et les partages illégitimes en affichant l\'identité de l\'acheteur directement sur le document.', 'secure-ebook-reader'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Activer le filigrane', 'secure-ebook-reader'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="watermark_enabled" value="yes" <?php checked($settings['watermark_enabled'] ?? 'yes', 'yes'); ?> />
                                <?php esc_html_e('Activer la surimpression du filigrane d\'identité', 'secure-ebook-reader'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Incrustation Canvas (Anti-Inspecteur)', 'secure-ebook-reader'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="watermark_canvas" value="yes" <?php checked($settings['watermark_canvas'] ?? 'yes', 'yes'); ?> />
                                <strong><?php esc_html_e('Graver directement le filigrane dans les pixels du canvas PDF.js', 'secure-ebook-reader'); ?></strong>
                            </label>
                            <p class="description"><?php esc_html_e('Empêche un utilisateur averti de masquer le filigrane en inspectant les éléments HTML de la page.', 'secure-ebook-reader'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="watermark_text"><?php esc_html_e('Modèle du texte', 'secure-ebook-reader'); ?></label></th>
                        <td>
                            <input type="text" id="watermark_text" name="watermark_text" value="<?php echo esc_attr($settings['watermark_text'] ?? '{user_name} ({user_email}) — IP: {ip} — {date}'); ?>" class="large-text" />
                            <p class="description"><?php esc_html_e('Variables disponibles : {user_name}, {user_email}, {ip}, {date}', 'secure-ebook-reader'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="watermark_opacity"><?php esc_html_e('Opacité (0.05 à 0.60)', 'secure-ebook-reader'); ?></label></th>
                        <td>
                            <input type="number" id="watermark_opacity" name="watermark_opacity" step="0.01" min="0.05" max="0.60" value="<?php echo esc_attr($settings['watermark_opacity'] ?? 0.16); ?>" class="small-text" />
                            <p class="description"><?php esc_html_e('Une valeur entre 0.14 et 0.20 offre une visibilité anti-pirate sans gêner le confort de lecture.', 'secure-ebook-reader'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="watermark_color"><?php esc_html_e('Couleur du texte', 'secure-ebook-reader'); ?></label></th>
                        <td>
                            <input type="color" id="watermark_color" name="watermark_color" value="<?php echo esc_attr($settings['watermark_color'] ?? '#334155'); ?>" />
                        </td>
                    </tr>
                </table>
            </div>

        <!-- Onglet 4 : Lecteur PDF -->
        <?php elseif ($active_tab === 'reader') : ?>
            <div class="settings-card">
                <h3><?php esc_html_e('Expérience de Lecture Frontend', 'secure-ebook-reader'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="reader_default_theme"><?php esc_html_e('Thème par défaut', 'secure-ebook-reader'); ?></label></th>
                        <td>
                            <select id="reader_default_theme" name="reader_default_theme">
                                <option value="dark" <?php selected($settings['reader_default_theme'] ?? 'dark', 'dark'); ?>><?php esc_html_e('Sombre immersif (Recommandé)', 'secure-ebook-reader'); ?></option>
                                <option value="light" <?php selected($settings['reader_default_theme'] ?? '', 'light'); ?>><?php esc_html_e('Clair épuré', 'secure-ebook-reader'); ?></option>
                                <option value="sepia" <?php selected($settings['reader_default_theme'] ?? '', 'sepia'); ?>><?php esc_html_e('Sépia vintage doux', 'secure-ebook-reader'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="default_zoom"><?php esc_html_e('Zoom initial', 'secure-ebook-reader'); ?></label></th>
                        <td>
                            <select id="default_zoom" name="default_zoom">
                                <option value="page-width" <?php selected($settings['default_zoom'] ?? 'page-width', 'page-width'); ?>><?php esc_html_e('Ajuster à la largeur de l\'écran', 'secure-ebook-reader'); ?></option>
                                <option value="page-fit" <?php selected($settings['default_zoom'] ?? '', 'page-fit'); ?>><?php esc_html_e('Ajuster à la page entière', 'secure-ebook-reader'); ?></option>
                                <option value="1.0" <?php selected($settings['default_zoom'] ?? '', '1.0'); ?>><?php esc_html_e('100% (Taille réelle)', 'secure-ebook-reader'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Mémorisation de page', 'secure-ebook-reader'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="remember_last_page" value="yes" <?php checked($settings['remember_last_page'] ?? 'yes', 'yes'); ?> />
                                <?php esc_html_e('Reprendre automatiquement la lecture là où le lecteur s\'est arrêté', 'secure-ebook-reader'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="progress_save_interval"><?php esc_html_e('Fréquence de synchronisation (secondes)', 'secure-ebook-reader'); ?></label></th>
                        <td>
                            <input type="number" id="progress_save_interval" name="progress_save_interval" min="5" max="120" value="<?php echo esc_attr($settings['progress_save_interval'] ?? 15); ?>" class="small-text" /> secondes
                            <p class="description"><?php esc_html_e('Intervalle de temporisation (debounce) pour enregistrer la page lue sans surcharger le serveur.', 'secure-ebook-reader'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

        <!-- Onglet 5 : Diagnostic Serveur -->
        <?php elseif ($active_tab === 'server') : ?>
            <div class="settings-card">
                <h3><?php esc_html_e('Vérification et Directives Serveur', 'secure-ebook-reader'); ?></h3>
                
                <?php
                $vault_dir = Secure_Ebook_Ebook::get_vault_dir();
                $htaccess_file = $vault_dir . '/.htaccess';
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Chemin du Coffre-fort (Vault)', 'secure-ebook-reader'); ?></th>
                        <td>
                            <code><?php echo esc_html($vault_dir); ?></code>
                            <p class="description"><?php echo file_exists($vault_dir) ? '✅ ' . esc_html__('Dossier créé et accessible en écriture par PHP.', 'secure-ebook-reader') : '❌ ' . esc_html__('Dossier manquant.', 'secure-ebook-reader'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Directive Apache / LiteSpeed', 'secure-ebook-reader'); ?></th>
                        <td>
                            <code><?php echo esc_html($htaccess_file); ?></code>
                            <p class="description"><?php echo file_exists($htaccess_file) ? '✅ ' . esc_html__('Fichier .htaccess actif avec blocage "Require all denied".', 'secure-ebook-reader') : '⚠️ ' . esc_html__('Fichier .htaccess manquant.', 'secure-ebook-reader'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Directive Nginx (Si applicable)', 'secure-ebook-reader'); ?></th>
                        <td>
                            <p class="description"><?php esc_html_e('Si votre hébergement utilise Nginx, ajoutez ce bloc dans votre configuration de serveur virtuel :', 'secure-ebook-reader'); ?></p>
                            <pre style="background: #1e293b; color: #f8fafc; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 13px;"># Protection hermétique des PDF Secure Ebook Reader
location ^~ /wp-content/uploads/secure-ebooks-vault/ {
    deny all;
    return 403;
}</pre>
                        </td>
                    </tr>
                </table>
            </div>
        <?php endif; ?>

        <div style="margin-top: 20px;">
            <input type="submit" class="button button-primary button-large" value="<?php esc_attr_e('Enregistrer les modifications', 'secure-ebook-reader'); ?>" />
        </div>
    </form>
</div>
