<?php
/**
 * Composant Carte d'Ebook pour la Bibliothèque Client
 *
 * @package Secure_Ebook_Reader
 * @var object $ebook Données de l'ebook
 */

if (!defined('ABSPATH')) {
    exit;
}

$read_url = home_url('/lecture-ebook/' . $ebook->id . '/');
$progress = isset($ebook->progress) ? floatval($ebook->progress) : 0.0;
$last_page = isset($ebook->last_page) ? absint($ebook->last_page) : 1;
$total_pages = isset($ebook->page_count) ? absint($ebook->page_count) : 0;

$cover_url = '';
if (!empty($ebook->cover_image_id)) {
    $cover_url = wp_get_attachment_image_url($ebook->cover_image_id, 'medium');
}

$is_expired = false;
$expires_label = esc_html__('Accès illimité', 'secure-ebook-reader');
if (!empty($ebook->expires_at) && $ebook->expires_at !== '0000-00-00 00:00:00' && $ebook->expires_at !== '0000-00-00') {
    $exp_time = strtotime($ebook->expires_at);
    if ($exp_time && $exp_time <= current_time('timestamp')) {
        $is_expired = true;
        $expires_label = esc_html__('Accès expiré', 'secure-ebook-reader');
    } elseif ($exp_time) {
        $expires_label = sprintf(esc_html__('Expire le %s', 'secure-ebook-reader'), date_i18n(get_option('date_format'), $exp_time));
    }
}
?>
<div class="secure-ebook-card <?php echo $is_expired ? 'is-expired' : ''; ?>">
    <div class="card-cover">
        <?php if (!empty($cover_url)) : ?>
            <img src="<?php echo esc_url($cover_url); ?>" alt="<?php echo esc_attr($ebook->title); ?>" loading="lazy" />
        <?php else : ?>
            <div class="cover-fallback">
                <div class="fallback-icon">📖</div>
                <div class="fallback-title"><?php echo esc_html($ebook->title); ?></div>
                <?php if (!empty($ebook->author)) : ?>
                    <div class="fallback-author"><?php echo esc_html($ebook->author); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="cover-badge <?php echo $is_expired ? 'badge-danger' : 'badge-success'; ?>">
            <?php echo esc_html($expires_label); ?>
        </div>
    </div>

    <div class="card-content">
        <h4 class="card-title"><?php echo esc_html($ebook->title); ?></h4>
        <?php if (!empty($ebook->author)) : ?>
            <p class="card-author"><?php echo esc_html($ebook->author); ?></p>
        <?php endif; ?>

        <!-- Barre d'avancement -->
        <div class="progress-section">
            <div class="progress-meta">
                <span class="progress-label">
                    <?php 
                    if ($progress >= 100) {
                        esc_html_e('Terminé', 'secure-ebook-reader');
                    } elseif ($progress > 0) {
                        printf(esc_html__('Page %d sur %d', 'secure-ebook-reader'), $last_page, $total_pages ?: $last_page);
                    } else {
                        esc_html_e('Non commencé', 'secure-ebook-reader');
                    }
                    ?>
                </span>
                <span class="progress-percent"><?php echo esc_html(number_format($progress, 0)); ?>%</span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" style="width: <?php echo esc_attr($progress); ?>%;"></div>
            </div>
        </div>

        <!-- Bouton d'action -->
        <div class="card-actions">
            <?php if (!$is_expired) : ?>
                <a href="<?php echo esc_url($read_url); ?>" class="btn-read" target="_blank" rel="noopener">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    <?php echo ($progress > 0 && $progress < 100) ? esc_html__('Reprendre la lecture', 'secure-ebook-reader') : esc_html__('Lire maintenant', 'secure-ebook-reader'); ?>
                </a>
            <?php else : ?>
                <button class="btn-read btn-disabled" disabled>
                    <?php esc_html_e('Accès expiré', 'secure-ebook-reader'); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>
