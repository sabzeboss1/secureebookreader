<?php
/**
 * Liste d'administration des Ebooks Protégés
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('ABSPATH')) {
    exit;
}

$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';

$ebooks = Secure_Ebook_Ebook::get_all([
    'search' => $search,
    'status' => $status,
    'limit'  => 100,
]);
?>
<div class="wrap secure-admin-wrap">
    <div class="secure-admin-header">
        <div>
            <h1><?php esc_html_e('Livres Numériques Protégés', 'secure-ebook-reader'); ?></h1>
            <p class="subtitle"><?php esc_html_e('Gestion des documents PDF stockés dans le coffre-fort sécurisé.', 'secure-ebook-reader'); ?></p>
        </div>
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-ebook-edit')); ?>" class="button button-primary">
            + <?php esc_html_e('Ajouter un Ebook', 'secure-ebook-reader'); ?>
        </a>
    </div>

    <?php if (isset($_GET['message'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                switch ($_GET['message']) {
                    case 'created': esc_html_e('L\'ebook a été téléversé et protégé avec succès dans le coffre-fort.', 'secure-ebook-reader'); break;
                    case 'updated': esc_html_e('L\'ebook a été mis à jour.', 'secure-ebook-reader'); break;
                    case 'deleted': esc_html_e('L\'ebook et son fichier sécurisé ont été supprimés.', 'secure-ebook-reader'); break;
                }
                ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Barre de filtres et recherche -->
    <div class="tablenav top">
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="alignleft actions">
            <input type="hidden" name="page" value="secure-ebook-list" />
            <select name="status">
                <option value=""><?php esc_html_e('Tous les statuts', 'secure-ebook-reader'); ?></option>
                <option value="active" <?php selected($status, 'active'); ?>><?php esc_html_e('Actif', 'secure-ebook-reader'); ?></option>
                <option value="disabled" <?php selected($status, 'disabled'); ?>><?php esc_html_e('Désactivé', 'secure-ebook-reader'); ?></option>
            </select>
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Rechercher un titre ou un auteur...', 'secure-ebook-reader'); ?>" />
            <input type="submit" class="button" value="<?php esc_attr_e('Filtrer', 'secure-ebook-reader'); ?>" />
        </form>
    </div>

    <!-- Tableau des ebooks -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 60px;"><?php esc_html_e('Couv.', 'secure-ebook-reader'); ?></th>
                <th><?php esc_html_e('Titre & Auteur', 'secure-ebook-reader'); ?></th>
                <th><?php esc_html_e('Produit Lié (WooCommerce)', 'secure-ebook-reader'); ?></th>
                <th><?php esc_html_e('Pages / Taille', 'secure-ebook-reader'); ?></th>
                <th><?php esc_html_e('Validité par défaut', 'secure-ebook-reader'); ?></th>
                <th><?php esc_html_e('Statut', 'secure-ebook-reader'); ?></th>
                <th style="width: 180px; text-align: right;"><?php esc_html_e('Actions', 'secure-ebook-reader'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($ebooks)) : ?>
                <?php foreach ($ebooks as $ebook) : 
                    $cover_url = !empty($ebook->cover_image_id) ? wp_get_attachment_image_url($ebook->cover_image_id, 'thumbnail') : '';
                    $product = !empty($ebook->product_id) ? wc_get_product($ebook->product_id) : null;
                    $preview_url = home_url('/lecture-ebook/' . $ebook->id . '/');
                    $delete_url = wp_nonce_url(admin_url('admin-post.php?action=secure_ebook_delete_ebook&ebook_id=' . $ebook->id), 'secure_ebook_delete_action', 'secure_ebook_nonce');
                ?>
                    <tr>
                        <td>
                            <?php if ($cover_url) : ?>
                                <img src="<?php echo esc_url($cover_url); ?>" alt="" style="width: 44px; height: 60px; object-fit: cover; border-radius: 4px;" />
                            <?php else : ?>
                                <div style="width: 44px; height: 60px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; border-radius: 4px; font-size: 20px;">📄</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><a href="<?php echo esc_url(admin_url('admin.php?page=secure-ebook-edit&ebook_id=' . $ebook->id)); ?>"><?php echo esc_html($ebook->title); ?></a></strong>
                            <?php if (!empty($ebook->author)) : ?>
                                <br><small class="text-muted"><?php echo esc_html($ebook->author); ?></small>
                            <?php endif; ?>
                            <br><small style="color: #64748b; font-family: monospace;">Hash: <?php echo esc_html(substr($ebook->file_hash, 0, 16)); ?>...</small>
                        </td>
                        <td>
                            <?php if ($product) : ?>
                                <a href="<?php echo esc_url(get_edit_post_link($product->get_id())); ?>" target="_blank">
                                    <?php echo esc_html($product->get_name()); ?> &nearr;
                                </a>
                            <?php else : ?>
                                <span class="text-muted"><?php esc_html_e('Non associé', 'secure-ebook-reader'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo esc_html($ebook->page_count); ?></strong> <?php esc_html_e('pages', 'secure-ebook-reader'); ?>
                            <br><small class="text-muted"><?php echo esc_html(size_format($ebook->file_size)); ?></small>
                        </td>
                        <td>
                            <?php
                            switch ($ebook->access_duration) {
                                case 'permanent': esc_html_e('Illimité', 'secure-ebook-reader'); break;
                                case '30d': esc_html_e('30 jours', 'secure-ebook-reader'); break;
                                case '90d': esc_html_e('90 jours', 'secure-ebook-reader'); break;
                                case '1y': esc_html_e('1 an', 'secure-ebook-reader'); break;
                                default: echo esc_html($ebook->access_duration);
                            }
                            ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo esc_attr($ebook->status === 'active' ? 'success' : 'secondary'); ?>">
                                <?php echo esc_html($ebook->status === 'active' ? __('Actif', 'secure-ebook-reader') : __('Désactivé', 'secure-ebook-reader')); ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <a href="<?php echo esc_url($preview_url); ?>" class="button button-small" target="_blank" title="<?php esc_attr_e('Tester la lecture en mode administrateur', 'secure-ebook-reader'); ?>">
                                👁️ <?php esc_html_e('Lire', 'secure-ebook-reader'); ?>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=secure-ebook-edit&ebook_id=' . $ebook->id)); ?>" class="button button-small">
                                ✏️
                            </a>
                            <a href="<?php echo esc_url($delete_url); ?>" class="button button-small button-link-delete" onclick="return confirm(secureEbookAdmin.confirm_del);">
                                🗑️
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 30px;">
                        <p><?php esc_html_e('Aucun ebook trouvé.', 'secure-ebook-reader'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-ebook-edit')); ?>" class="button button-primary">
                            <?php esc_html_e('Ajouter votre premier ebook protégé', 'secure-ebook-reader'); ?>
                        </a>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
