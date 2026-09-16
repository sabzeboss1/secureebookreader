<?php
/**
 * Gestion des Accès Clients — Secure Ebook Reader
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('ABSPATH')) {
    exit;
}

$status   = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
$ebook_id = isset($_GET['ebook_id']) ? absint($_GET['ebook_id']) : 0;

$accesses = Secure_Ebook_Access::get_all_accesses([
    'status'   => $status,
    'ebook_id' => $ebook_id,
    'limit'    => 100,
]);

$all_ebooks = Secure_Ebook_Ebook::get_all(['limit' => 200]);
$users = get_users(['number' => 200, 'orderby' => 'display_name']);
?>
<div class="wrap secure-admin-wrap">
    <div class="secure-admin-header">
        <div>
            <h1><?php esc_html_e('Gestion des Accès Clients', 'secure-ebook-reader'); ?></h1>
            <p class="subtitle"><?php esc_html_e('Contrôle des autorisations de lecture, octroi manuel et révocation immédiate.', 'secure-ebook-reader'); ?></p>
        </div>
        <button type="button" id="btn-open-grant-modal" class="button button-primary">
            + <?php esc_html_e('Accorder un Accès Manuel', 'secure-ebook-reader'); ?>
        </button>
    </div>

    <?php if (isset($_GET['message'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                switch ($_GET['message']) {
                    case 'granted': esc_html_e('L\'accès a été accordé au client avec succès.', 'secure-ebook-reader'); break;
                    case 'revoked': esc_html_e('L\'accès a été révoqué immédiatement et tous ses jetons actifs ont été détruits.', 'secure-ebook-reader'); break;
                }
                ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Filtres -->
    <div class="tablenav top">
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="alignleft actions">
            <input type="hidden" name="page" value="secure-ebook-access" />
            <select name="status">
                <option value=""><?php esc_html_e('Tous les statuts', 'secure-ebook-reader'); ?></option>
                <option value="granted" <?php selected($status, 'granted'); ?>><?php esc_html_e('Actif (Accordé)', 'secure-ebook-reader'); ?></option>
                <option value="revoked" <?php selected($status, 'revoked'); ?>><?php esc_html_e('Révoqué', 'secure-ebook-reader'); ?></option>
                <option value="expired" <?php selected($status, 'expired'); ?>><?php esc_html_e('Expiré', 'secure-ebook-reader'); ?></option>
            </select>

            <select name="ebook_id">
                <option value="0"><?php esc_html_e('Tous les ebooks', 'secure-ebook-reader'); ?></option>
                <?php foreach ($all_ebooks as $b) : ?>
                    <option value="<?php echo esc_attr($b->id); ?>" <?php selected($ebook_id, $b->id); ?>>
                        <?php echo esc_html($b->title); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="submit" class="button" value="<?php esc_attr_e('Filtrer', 'secure-ebook-reader'); ?>" />
        </form>
    </div>

    <!-- Tableau des accès -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Utilisateur / Client', 'secure-ebook-reader'); ?></th>
                <th><?php esc_html_e('Ebook', 'secure-ebook-reader'); ?></th>
                <th><?php esc_html_e('Origine / Commande', 'secure-ebook-reader'); ?></th>
                <th><?php esc_html_e('Accordé le', 'secure-ebook-reader'); ?></th>
                <th><?php esc_html_e('Expire le', 'secure-ebook-reader'); ?></th>
                <th><?php esc_html_e('Statut', 'secure-ebook-reader'); ?></th>
                <th style="width: 120px; text-align: right;"><?php esc_html_e('Actions', 'secure-ebook-reader'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($accesses)) : ?>
                <?php foreach ($accesses as $acc) : 
                    $revoke_url = wp_nonce_url(
                        admin_url('admin-post.php?action=secure_ebook_revoke_access&user_id=' . $acc->user_id . '&ebook_id=' . $acc->ebook_id),
                        'secure_ebook_revoke_action',
                        'secure_ebook_nonce'
                    );
                ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($acc->display_name ?: $acc->user_login); ?></strong>
                            <br><small class="text-muted"><?php echo esc_html($acc->user_email); ?></small>
                        </td>
                        <td>
                            <strong><?php echo esc_html($acc->ebook_title); ?></strong>
                        </td>
                        <td>
                            <?php if ($acc->source === 'woocommerce' && !empty($acc->order_id)) : ?>
                                <span class="badge badge-info">WooCommerce #<?php echo esc_html($acc->order_id); ?></span>
                            <?php else : ?>
                                <span class="badge badge-secondary"><?php echo esc_html(ucfirst($acc->source)); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' H:i', strtotime($acc->granted_at))); ?></td>
                        <td>
                            <?php if (!empty($acc->expires_at) && $acc->expires_at !== '0000-00-00 00:00:00') : ?>
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($acc->expires_at))); ?>
                            <?php else : ?>
                                <span class="text-muted"><?php esc_html_e('Illimité', 'secure-ebook-reader'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo esc_attr($acc->status === 'granted' ? 'success' : ($acc->status === 'revoked' ? 'danger' : 'warning')); ?>">
                                <?php echo esc_html($acc->status); ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <?php if ($acc->status === 'granted') : ?>
                                <a href="<?php echo esc_url($revoke_url); ?>" class="button button-small button-link-delete" onclick="return confirm(secureEbookAdmin.confirm_rev);" title="<?php esc_attr_e('Révoquer l\'accès immédiatement', 'secure-ebook-reader'); ?>">
                                    <?php esc_html_e('Révoquer', 'secure-ebook-reader'); ?>
                                </a>
                            <?php else : ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 30px;">
                        <?php esc_html_e('Aucun droit d\'accès trouvé selon ces critères.', 'secure-ebook-reader'); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Modal d'attribution manuelle -->
    <div id="modal-grant-access" class="secure-modal" style="display: none;">
        <div class="secure-modal-content">
            <div class="secure-modal-header">
                <h3><?php esc_html_e('Accorder un Accès Manuel', 'secure-ebook-reader'); ?></h3>
                <button type="button" class="btn-close-modal">&times;</button>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="secure_ebook_grant_access" />
                <?php wp_nonce_field('secure_ebook_grant_action', 'secure_ebook_nonce'); ?>

                <div class="form-group">
                    <label for="grant_user_id"><?php esc_html_e('Sélectionner l\'utilisateur *', 'secure-ebook-reader'); ?></label>
                    <select id="grant_user_id" name="user_id" class="full-width" required>
                        <option value=""><?php esc_html_e('— Choisir un compte utilisateur —', 'secure-ebook-reader'); ?></option>
                        <?php foreach ($users as $u) : ?>
                            <option value="<?php echo esc_attr($u->ID); ?>">
                                <?php echo esc_html($u->display_name . ' (' . $u->user_email . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="grant_ebook_id"><?php esc_html_e('Sélectionner l\'Ebook *', 'secure-ebook-reader'); ?></label>
                    <select id="grant_ebook_id" name="ebook_id" class="full-width" required>
                        <option value=""><?php esc_html_e('— Choisir un ebook protégé —', 'secure-ebook-reader'); ?></option>
                        <?php foreach ($all_ebooks as $b) : ?>
                            <option value="<?php echo esc_attr($b->id); ?>">
                                <?php echo esc_html($b->title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="grant_duration"><?php esc_html_e('Durée de l\'accès', 'secure-ebook-reader'); ?></label>
                    <select id="grant_duration" name="duration" class="full-width">
                        <option value="permanent"><?php esc_html_e('Illimité (À vie)', 'secure-ebook-reader'); ?></option>
                        <option value="30d"><?php esc_html_e('30 jours', 'secure-ebook-reader'); ?></option>
                        <option value="90d"><?php esc_html_e('90 jours', 'secure-ebook-reader'); ?></option>
                        <option value="1y"><?php esc_html_e('1 an', 'secure-ebook-reader'); ?></option>
                    </select>
                </div>

                <div class="form-group" style="text-align: right; margin-top: 20px;">
                    <button type="button" class="button btn-close-modal"><?php esc_html_e('Annuler', 'secure-ebook-reader'); ?></button>
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e('Accorder l\'accès', 'secure-ebook-reader'); ?>" />
                </div>
            </form>
        </div>
    </div>
</div>
