<?php
/**
 * Formulaire d'ajout et d'édition d'un Ebook sécurisé
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('ABSPATH')) {
    exit;
}

$id = isset($_GET['ebook_id']) ? absint($_GET['ebook_id']) : 0;
$ebook = $id ? Secure_Ebook_Ebook::get($id) : null;
$is_edit = (bool) $ebook;

$cover_url = '';
if ($is_edit && !empty($ebook->cover_image_id)) {
    $cover_url = wp_get_attachment_image_url($ebook->cover_image_id, 'medium');
}

// Récupérer la liste des produits WooCommerce pour association
$products = function_exists('wc_get_products') ? wc_get_products(['limit' => 200, 'status' => 'publish']) : [];
?>
<div class="wrap secure-admin-wrap">
    <h1>
        <?php echo $is_edit ? esc_html__('Modifier l\'Ebook Protégé', 'secure-ebook-reader') : esc_html__('Ajouter un Ebook Protégé', 'secure-ebook-reader'); ?>
    </h1>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="secure-form-box">
        <input type="hidden" name="action" value="secure_ebook_save_ebook" />
        <input type="hidden" name="ebook_id" value="<?php echo esc_attr($id); ?>" />
        <?php wp_nonce_field('secure_ebook_save_action', 'secure_ebook_nonce'); ?>

        <div class="form-layout-2col">
            <!-- Colonne Principale -->
            <div class="form-col-main">
                <div class="form-card">
                    <h3><?php esc_html_e('Informations Générales', 'secure-ebook-reader'); ?></h3>

                    <div class="form-group">
                        <label for="title"><?php esc_html_e('Titre de l\'ouvrage *', 'secure-ebook-reader'); ?></label>
                        <input type="text" id="title" name="title" class="regular-text full-width" required value="<?php echo esc_attr($ebook->title ?? ''); ?>" />
                    </div>

                    <div class="form-group">
                        <label for="author"><?php esc_html_e('Auteur(s)', 'secure-ebook-reader'); ?></label>
                        <input type="text" id="author" name="author" class="regular-text full-width" value="<?php echo esc_attr($ebook->author ?? ''); ?>" />
                    </div>

                    <div class="form-group">
                        <label for="description"><?php esc_html_e('Description / Résumé', 'secure-ebook-reader'); ?></label>
                        <?php 
                        wp_editor(
                            $ebook->description ?? '',
                            'description',
                            ['textarea_rows' => 8, 'media_buttons' => false, 'teeny' => true]
                        ); 
                        ?>
                    </div>
                </div>

                <div class="form-card">
                    <h3><?php esc_html_e('Fichier Source PDF (Stockage Coffre-Fort)', 'secure-ebook-reader'); ?></h3>
                    <p class="description">
                        <?php esc_html_e('Le document sera cryptographiquement renommé, doté d\'une empreinte SHA-256 et placé dans le dossier hermétique protégé contre tout accès web public direct.', 'secure-ebook-reader'); ?>
                    </p>

                    <div class="form-group">
                        <label for="ebook_file">
                            <?php echo $is_edit ? esc_html__('Remplacer le fichier PDF (Optionnel)', 'secure-ebook-reader') : esc_html__('Sélectionner le document PDF *', 'secure-ebook-reader'); ?>
                        </label>
                        <input type="file" id="ebook_file" name="ebook_file" accept="application/pdf" <?php echo !$is_edit ? 'required' : ''; ?> />
                    </div>

                    <?php if ($is_edit) : ?>
                        <div class="file-security-badge">
                            <p><strong><?php esc_html_e('Fichier actuel protégé :', 'secure-ebook-reader'); ?></strong> <code><?php echo esc_html($ebook->file_name); ?></code></p>
                            <p><strong><?php esc_html_e('Taille :', 'secure-ebook-reader'); ?></strong> <?php echo esc_html(size_format($ebook->file_size)); ?> | <strong><?php esc_html_e('Pages :', 'secure-ebook-reader'); ?></strong> <?php echo esc_html($ebook->page_count); ?> pages</p>
                            <p><strong><?php esc_html_e('Empreinte SHA-256 :', 'secure-ebook-reader'); ?></strong> <small style="font-family: monospace;"><?php echo esc_html($ebook->file_hash); ?></small></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Colonne Latérale -->
            <div class="form-col-side">
                <!-- Publication / Statut -->
                <div class="form-card">
                    <h3><?php esc_html_e('Publication', 'secure-ebook-reader'); ?></h3>
                    
                    <div class="form-group">
                        <label for="status"><?php esc_html_e('Statut de visibilité', 'secure-ebook-reader'); ?></label>
                        <select id="status" name="status" class="full-width">
                            <option value="active" <?php selected($ebook->status ?? 'active', 'active'); ?>><?php esc_html_e('Actif (Accessible aux acheteurs)', 'secure-ebook-reader'); ?></option>
                            <option value="disabled" <?php selected($ebook->status ?? '', 'disabled'); ?>><?php esc_html_e('Désactivé (Accès suspendu)', 'secure-ebook-reader'); ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="access_duration"><?php esc_html_e('Durée d\'accès par défaut', 'secure-ebook-reader'); ?></label>
                        <select id="access_duration" name="access_duration" class="full-width">
                            <option value="permanent" <?php selected($ebook->access_duration ?? 'permanent', 'permanent'); ?>><?php esc_html_e('Illimité (À vie)', 'secure-ebook-reader'); ?></option>
                            <option value="30d" <?php selected($ebook->access_duration ?? '', '30d'); ?>><?php esc_html_e('30 jours', 'secure-ebook-reader'); ?></option>
                            <option value="90d" <?php selected($ebook->access_duration ?? '', '90d'); ?>><?php esc_html_e('90 jours', 'secure-ebook-reader'); ?></option>
                            <option value="1y" <?php selected($ebook->access_duration ?? '', '1y'); ?>><?php esc_html_e('1 an', 'secure-ebook-reader'); ?></option>
                        </select>
                    </div>

                    <div class="form-group submit-row">
                        <input type="submit" class="button button-primary button-large full-width" value="<?php echo $is_edit ? esc_attr__('Mettre à jour l\'ebook', 'secure-ebook-reader') : esc_attr__('Enregistrer et protéger', 'secure-ebook-reader'); ?>" />
                    </div>
                </div>

                <!-- Association WooCommerce -->
                <div class="form-card">
                    <h3><?php esc_html_e('Produit WooCommerce Lié', 'secure-ebook-reader'); ?></h3>
                    <p class="description"><?php esc_html_e('Lorsqu\'un client achètera ce produit, l\'accès à cet ebook lui sera automatiquement ouvert.', 'secure-ebook-reader'); ?></p>

                    <div class="form-group">
                        <select id="product_id" name="product_id" class="full-width">
                            <option value="0"><?php esc_html_e('— Aucun produit associé —', 'secure-ebook-reader'); ?></option>
                            <?php foreach ($products as $p) : ?>
                                <option value="<?php echo esc_attr($p->get_id()); ?>" <?php selected($ebook->product_id ?? 0, $p->get_id()); ?>>
                                    <?php echo esc_html($p->get_name()); ?> (#<?php echo esc_html($p->get_id()); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Couverture de l'ouvrage -->
                <div class="form-card">
                    <h3><?php esc_html_e('Image de Couverture', 'secure-ebook-reader'); ?></h3>
                    <input type="hidden" id="cover_image_id" name="cover_image_id" value="<?php echo esc_attr($ebook->cover_image_id ?? 0); ?>" />
                    
                    <div id="cover-preview-wrapper" class="cover-uploader-box">
                        <?php if (!empty($cover_url)) : ?>
                            <img id="cover-img-preview" src="<?php echo esc_url($cover_url); ?>" alt="" />
                        <?php else : ?>
                            <img id="cover-img-preview" src="" alt="" style="display: none;" />
                            <div id="cover-placeholder-text">
                                <span style="font-size: 32px;">🖼️</span><br>
                                <?php esc_html_e('Aucune couverture sélectionnée', 'secure-ebook-reader'); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top: 10px;">
                        <button type="button" id="btn-select-cover" class="button"><?php esc_html_e('Choisir une image', 'secure-ebook-reader'); ?></button>
                        <button type="button" id="btn-remove-cover" class="button button-link-delete" style="<?php echo empty($cover_url) ? 'display: none;' : ''; ?>"><?php esc_html_e('Supprimer', 'secure-ebook-reader'); ?></button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
