<?php
/**
 * Vue "Mes Livres Numériques" (WooCommerce Mon Compte / Shortcode)
 *
 * @package Secure_Ebook_Reader
 * @var array $ebooks Liste des ebooks autorisés pour le client
 */

if (!defined('ABSPATH')) {
    exit;
}

$total_books = count($ebooks);
$reading_now = 0;
$completed   = 0;

foreach ($ebooks as $b) {
    $p = floatval($b->progress ?? 0);
    if ($p >= 100) {
        $completed++;
    } elseif ($p > 0) {
        $reading_now++;
    }
}
?>
<div class="secure-ebook-library-wrapper">
    <!-- En-tête de la bibliothèque -->
    <div class="library-header">
        <div class="header-titles">
            <h2 class="library-title"><?php esc_html_e('Ma Bibliothèque Numérique', 'secure-ebook-reader'); ?></h2>
            <p class="library-subtitle"><?php esc_html_e('Consultez vos ouvrages protégés en haute définition sans téléchargement.', 'secure-ebook-reader'); ?></p>
        </div>

        <!-- Statistiques rapides -->
        <div class="library-stats-chips">
            <div class="stat-chip">
                <span class="stat-chip-count"><?php echo esc_html($total_books); ?></span>
                <span class="stat-chip-label"><?php esc_html_e('Ouvrages', 'secure-ebook-reader'); ?></span>
            </div>
            <div class="stat-chip">
                <span class="stat-chip-count"><?php echo esc_html($reading_now); ?></span>
                <span class="stat-chip-label"><?php esc_html_e('En cours', 'secure-ebook-reader'); ?></span>
            </div>
            <div class="stat-chip">
                <span class="stat-chip-count"><?php echo esc_html($completed); ?></span>
                <span class="stat-chip-label"><?php esc_html_e('Terminés', 'secure-ebook-reader'); ?></span>
            </div>
        </div>
    </div>

    <?php if ($total_books > 0) : ?>
        <!-- Barre de recherche locale -->
        <div class="library-search-bar">
            <div class="search-input-wrapper">
                <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="library-search-input" placeholder="<?php esc_attr_e('Rechercher un livre par titre ou auteur...', 'secure-ebook-reader'); ?>" />
            </div>
        </div>

        <!-- Grille responsive des cartes d'ebooks -->
        <div class="secure-ebook-grid" id="secure-ebook-grid">
            <?php foreach ($ebooks as $ebook) : ?>
                <?php include SECURE_EBOOK_PATH . 'templates/ebook-card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <!-- État vide (aucun livre) -->
        <div class="library-empty-state">
            <div class="empty-icon">📚</div>
            <h3><?php esc_html_e('Aucun livre numérique disponible', 'secure-ebook-reader'); ?></h3>
            <p><?php esc_html_e('Vous ne possédez aucun ebook actif pour le moment. Vos achats de livres numériques apparaîtront automatiquement ici dès la validation de votre commande.', 'secure-ebook-reader'); ?></p>
            <?php if (function_exists('wc_get_page_permalink')) : ?>
                <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="btn-browse-shop">
                    <?php esc_html_e('Découvrir la boutique', 'secure-ebook-reader'); ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
