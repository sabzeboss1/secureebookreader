<?php
/**
 * Template du Lecteur Immersif Sécurisé (PDF.js Engine)
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_id  = get_current_user_id();
$ebook_id = isset($ebook_id) ? absint($ebook_id) : (isset($_GET['id']) ? absint($_GET['id']) : 0);

if (!$ebook_id || !Secure_Ebook_Access::can_read($user_id, $ebook_id)) {
    wp_die(
        esc_html__('Accès non autorisé à cet ouvrage protégé.', 'secure-ebook-reader'),
        esc_html__('Erreur 403', 'secure-ebook-reader'),
        ['response' => 403, 'back_link' => true]
    );
}

$ebook = Secure_Ebook_Ebook::get($ebook_id);
if (!$ebook) {
    wp_die(esc_html__('Ebook introuvable.', 'secure-ebook-reader'), '', ['response' => 404]);
}

$progress = Secure_Ebook_Progress::get_progress($user_id, $ebook_id);
$initial_page = ($progress && $progress->last_page > 0) ? (int) $progress->last_page : 1;

$user = wp_get_current_user();
$settings = get_option('secure_ebook_settings', []);

// Préparation des métadonnées du filigrane
$watermark_template = !empty($settings['watermark_text']) ? $settings['watermark_text'] : '{user_name} ({user_email}) — {ip} — {date}';
$watermark_text = str_replace(
    ['{user_name}', '{user_email}', '{ip}', '{date}'],
    [
        $user->display_name ?: $user->user_login,
        $user->user_email,
        Secure_Ebook_Security::get_client_ip(),
        date_i18n(get_option('date_format') . ' H:i')
    ],
    $watermark_template
);

$library_url = wc_get_account_endpoint_url('mes-ebooks');
if (!$library_url || strpos($library_url, 'mes-ebooks') === false) {
    $library_url = home_url('/');
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="secure-ebook-html">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo esc_html($ebook->title); ?> — <?php esc_html_e('Lecture Sécurisée', 'secure-ebook-reader'); ?></title>
    
    <link rel="stylesheet" href="<?php echo esc_url(SECURE_EBOOK_URL . 'assets/public/css/reader.css?ver=' . SECURE_EBOOK_VERSION); ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">

    <script src="<?php echo esc_url(SECURE_EBOOK_URL . 'assets/vendor/pdfjs/pdf.min.js?ver=3.11.174'); ?>"></script>
    <script>
        window.SECURE_EBOOK_CONFIG = {
            ebookId: <?php echo (int) $ebook->id; ?>,
            restUrl: '<?php echo esc_url_raw(rest_url('secure-ebook/v1/')); ?>',
            nonce: '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>',
            workerSrc: '<?php echo esc_url(SECURE_EBOOK_URL . 'assets/vendor/pdfjs/pdf.worker.min.js?ver=3.11.174'); ?>',
            initialPage: <?php echo (int) $initial_page; ?>,
            totalPages: <?php echo (int) $ebook->page_count; ?>,
            theme: '<?php echo esc_js($settings['reader_default_theme'] ?? 'dark'); ?>',
            saveInterval: <?php echo (int) ($settings['progress_save_interval'] ?? 15); ?>,
            watermark: {
                enabled: <?php echo (!empty($settings['watermark_enabled']) && $settings['watermark_enabled'] === 'yes') ? 'true' : 'false'; ?>,
                text: <?php echo wp_json_encode($watermark_text); ?>,
                opacity: <?php echo floatval($settings['watermark_opacity'] ?? 0.16); ?>,
                color: '<?php echo esc_js($settings['watermark_color'] ?? '#334155'); ?>',
                size: <?php echo (int) ($settings['watermark_size'] ?? 15); ?>,
                canvas: <?php echo (!empty($settings['watermark_canvas']) && $settings['watermark_canvas'] === 'yes') ? 'true' : 'false'; ?>
            },
            strings: {
                loading: '<?php echo esc_js(__('Chargement du document sécurisé...', 'secure-ebook-reader')); ?>',
                page: '<?php echo esc_js(__('Page', 'secure-ebook-reader')); ?>',
                of: '<?php echo esc_js(__('sur', 'secure-ebook-reader')); ?>',
                protectedNotice: '<?php echo esc_js(__('Document protégé contre la copie et l\'impression.', 'secure-ebook-reader')); ?>',
                sessionExpired: '<?php echo esc_js(__('Votre session de lecture a expiré. Veuillez actualiser la page.', 'secure-ebook-reader')); ?>',
                accessRevoked: '<?php echo esc_js(__('Votre accès à cet ouvrage a été clôturé.', 'secure-ebook-reader')); ?>',
                resumePrompt: '<?php echo esc_js(__('Reprendre la lecture à la page %d ?', 'secure-ebook-reader')); ?>'
            }
        };
    </script>
</head>
<body class="secure-reader-body theme-<?php echo esc_attr($settings['reader_default_theme'] ?? 'dark'); ?>" oncontextmenu="return false;" onselectstart="return false;" ondragstart="return false;">

    <!-- Barre d'outils supérieure -->
    <header class="reader-toolbar">
        <div class="toolbar-left">
            <a href="<?php echo esc_url($library_url); ?>" class="btn-icon" title="<?php esc_attr_e('Retour à la bibliothèque', 'secure-ebook-reader'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            </a>
            <button id="btn-toggle-sidebar" class="btn-icon" title="<?php esc_attr_e('Vignettes des pages', 'secure-ebook-reader'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            </button>
            <div class="book-meta">
                <span class="book-title"><?php echo esc_html($ebook->title); ?></span>
                <?php if (!empty($ebook->author)) : ?>
                    <span class="book-author"><?php echo esc_html($ebook->author); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="toolbar-center">
            <button id="btn-prev-page" class="btn-icon" title="<?php esc_attr_e('Page précédente (Flèche Gauche)', 'secure-ebook-reader'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <div class="page-controls">
                <input type="number" id="page-input" value="1" min="1" max="9999" />
                <span class="page-total">/ <span id="total-pages-count">--</span></span>
            </div>
            <button id="btn-next-page" class="btn-icon" title="<?php esc_attr_e('Page suivante (Flèche Droite)', 'secure-ebook-reader'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>

        <div class="toolbar-right">
            <!-- Zoom -->
            <div class="zoom-group">
                <button id="btn-zoom-out" class="btn-icon" title="<?php esc_attr_e('Zoom arrière (-)', 'secure-ebook-reader'); ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                </button>
                <span id="zoom-level">100%</span>
                <button id="btn-zoom-in" class="btn-icon" title="<?php esc_attr_e('Zoom avant (+)', 'secure-ebook-reader'); ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                </button>
                <button id="btn-zoom-fit-width" class="btn-icon" title="<?php esc_attr_e('Ajuster à la largeur', 'secure-ebook-reader'); ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="14" y1="10" x2="21" y2="3"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                </button>
            </div>

            <!-- Sélecteur de thème -->
            <div class="theme-switcher">
                <button class="btn-theme" data-theme="dark" title="<?php esc_attr_e('Thème Sombre', 'secure-ebook-reader'); ?>">🌙</button>
                <button class="btn-theme" data-theme="light" title="<?php esc_attr_e('Thème Clair', 'secure-ebook-reader'); ?>">☀️</button>
                <button class="btn-theme" data-theme="sepia" title="<?php esc_attr_e('Thème Sépia', 'secure-ebook-reader'); ?>">📜</button>
            </div>

            <!-- Plein écran -->
            <button id="btn-fullscreen" class="btn-icon" title="<?php esc_attr_e('Plein écran', 'secure-ebook-reader'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
            </button>
        </div>
    </header>

    <!-- Zone principale -->
    <div class="reader-main">
        <!-- Barre latérale vignettes -->
        <aside id="reader-sidebar" class="reader-sidebar">
            <div class="sidebar-header">
                <h3><?php esc_html_e('Vignettes', 'secure-ebook-reader'); ?></h3>
                <button id="btn-close-sidebar" class="btn-icon">&times;</button>
            </div>
            <div id="thumbnails-container" class="thumbnails-container"></div>
        </aside>

        <!-- Espace de visualisation du document -->
        <main id="viewer-container" class="viewer-container">
            <!-- Spinner de chargement -->
            <div id="reader-loader" class="reader-loader">
                <div class="spinner"></div>
                <p id="loader-text"><?php esc_html_e('Chargement sécurisé du document...', 'secure-ebook-reader'); ?></p>
            </div>

            <!-- Conteneur de la page active -->
            <div id="page-wrapper" class="page-wrapper">
                <canvas id="pdf-canvas"></canvas>
                <!-- Filigrane CSS dual-layer -->
                <div id="watermark-overlay" class="watermark-overlay"></div>
            </div>
        </main>
    </div>

    <!-- Barre de progression inférieure -->
    <div class="reading-progress-bar">
        <div id="progress-indicator" class="progress-indicator" style="width: 0%;"></div>
    </div>

    <!-- Toast de notification -->
    <div id="reader-toast" class="reader-toast"></div>

    <script src="<?php echo esc_url(SECURE_EBOOK_URL . 'assets/public/js/reader.js?ver=' . SECURE_EBOOK_VERSION); ?>"></script>
</body>
</html>
