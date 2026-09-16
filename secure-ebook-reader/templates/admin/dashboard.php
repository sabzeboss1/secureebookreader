<?php
/**
 * Tableau de bord d'administration — Secure Ebook Reader
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('ABSPATH')) {
    exit;
}

$count_ebooks  = Secure_Ebook_Ebook::count_all('active');
$count_access  = Secure_Ebook_Access::count_accesses('granted');
$count_logs    = Secure_Ebook_Logger::count_logs('danger');
$recent_logs   = Secure_Ebook_Logger::get_logs(['limit' => 5]);
$recent_access = Secure_Ebook_Access::get_all_accesses(['limit' => 5]);

// Diagnostic rapide du dossier hermétique
$vault_dir = Secure_Ebook_Ebook::get_vault_dir();
$vault_exists = file_exists($vault_dir);
$vault_writable = is_writable($vault_dir);
$htaccess_exists = file_exists($vault_dir . '/.htaccess');
$index_exists = file_exists($vault_dir . '/index.php');
?>
<div class="wrap secure-admin-wrap">
    <div class="secure-admin-header">
        <div>
            <h1><?php esc_html_e('Tableau de bord — Secure Ebook Reader', 'secure-ebook-reader'); ?></h1>
            <p class="subtitle"><?php esc_html_e('Surveillance de la sécurité, flux de lecture et gestion des accès.', 'secure-ebook-reader'); ?></p>
        </div>
        <div class="header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=secure-ebook-edit')); ?>" class="button button-primary">
                + <?php esc_html_e('Ajouter un Ebook Protégé', 'secure-ebook-reader'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=secure-ebook-access')); ?>" class="button">
                <?php esc_html_e('Accorder un Accès', 'secure-ebook-reader'); ?>
            </a>
        </div>
    </div>

    <!-- Grille des KPI -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon icon-blue">📚</div>
            <div class="kpi-info">
                <span class="kpi-value"><?php echo esc_html($count_ebooks); ?></span>
                <span class="kpi-label"><?php esc_html_e('Ebooks Protégés Actifs', 'secure-ebook-reader'); ?></span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon icon-green">🔑</div>
            <div class="kpi-info">
                <span class="kpi-value"><?php echo esc_html($count_access); ?></span>
                <span class="kpi-label"><?php esc_html_e('Accès Clients Accordés', 'secure-ebook-reader'); ?></span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon icon-purple">⚡</div>
            <div class="kpi-info">
                <span class="kpi-value">RFC 7233</span>
                <span class="kpi-label"><?php esc_html_e('Streaming Range Actif', 'secure-ebook-reader'); ?></span>
            </div>
        </div>

        <div class="kpi-card <?php echo $count_logs > 0 ? 'kpi-alert' : ''; ?>">
            <div class="kpi-icon icon-red">🛡️</div>
            <div class="kpi-info">
                <span class="kpi-value"><?php echo esc_html($count_logs); ?></span>
                <span class="kpi-label"><?php esc_html_e('Alertes Anti-IDOR / Risques', 'secure-ebook-reader'); ?></span>
            </div>
        </div>
    </div>

    <!-- Section Diagnostic & Statut Système -->
    <div class="system-status-box">
        <h3><?php esc_html_e('État de l\'Environnement de Sécurité', 'secure-ebook-reader'); ?></h3>
        <div class="status-items-row">
            <div class="status-item">
                <span class="status-dot <?php echo $vault_exists && $vault_writable ? 'dot-success' : 'dot-danger'; ?>"></span>
                <span>Dossier Vault hermétique : <strong><?php echo $vault_exists ? 'Opérationnel' : 'Introuvable'; ?></strong></span>
            </div>
            <div class="status-item">
                <span class="status-dot <?php echo $htaccess_exists ? 'dot-success' : 'dot-warning'; ?>"></span>
                <span>Protection Apache (.htaccess) : <strong><?php echo $htaccess_exists ? 'Actif (Deny All)' : 'Non détecté'; ?></strong></span>
            </div>
            <div class="status-item">
                <span class="status-dot <?php echo $index_exists ? 'dot-success' : 'dot-danger'; ?>"></span>
                <span>Garde-fou PHP (index.php) : <strong><?php echo $index_exists ? 'Protégé' : 'Manquant'; ?></strong></span>
            </div>
            <div class="status-item">
                <span class="status-dot dot-success"></span>
                <span>Serveur Web : <strong><?php echo esc_html($_SERVER['SERVER_SOFTWARE'] ?? 'PHP CLI'); ?></strong></span>
            </div>
        </div>
    </div>

    <div class="dashboard-columns">
        <!-- Derniers accès accordés -->
        <div class="dashboard-col">
            <div class="panel-card">
                <div class="panel-card-header">
                    <h3><?php esc_html_e('Derniers Accès Accordés', 'secure-ebook-reader'); ?></h3>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=secure-ebook-access')); ?>"><?php esc_html_e('Tout voir', 'secure-ebook-reader'); ?> &rarr;</a>
                </div>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Client', 'secure-ebook-reader'); ?></th>
                            <th><?php esc_html_e('Ebook', 'secure-ebook-reader'); ?></th>
                            <th><?php esc_html_e('Statut', 'secure-ebook-reader'); ?></th>
                            <th><?php esc_html_e('Date', 'secure-ebook-reader'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_access)) : ?>
                            <?php foreach ($recent_access as $acc) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html($acc->display_name ?: $acc->user_login); ?></strong><br><small><?php echo esc_html($acc->user_email); ?></small></td>
                                    <td><?php echo esc_html($acc->ebook_title); ?></td>
                                    <td><span class="badge badge-<?php echo esc_attr($acc->status === 'granted' ? 'success' : 'danger'); ?>"><?php echo esc_html($acc->status); ?></span></td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($acc->granted_at))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="4"><?php esc_html_e('Aucun accès enregistré pour l\'instant.', 'secure-ebook-reader'); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Derniers événements de sécurité -->
        <div class="dashboard-col">
            <div class="panel-card">
                <div class="panel-card-header">
                    <h3><?php esc_html_e('Événements de Sécurité Récents', 'secure-ebook-reader'); ?></h3>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=secure-ebook-logs')); ?>"><?php esc_html_e('Voir le journal', 'secure-ebook-reader'); ?> &rarr;</a>
                </div>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Type', 'secure-ebook-reader'); ?></th>
                            <th><?php esc_html_e('Détail', 'secure-ebook-reader'); ?></th>
                            <th><?php esc_html_e('IP', 'secure-ebook-reader'); ?></th>
                            <th><?php esc_html_e('Date', 'secure-ebook-reader'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_logs)) : ?>
                            <?php foreach ($recent_logs as $log) : ?>
                                <tr>
                                    <td><span class="badge badge-<?php echo esc_attr($log->severity === 'danger' ? 'danger' : ($log->severity === 'warning' ? 'warning' : 'info')); ?>"><?php echo esc_html($log->event_type); ?></span></td>
                                    <td><small><?php echo esc_html(wp_trim_words($log->message, 8)); ?></small></td>
                                    <td><code><?php echo esc_html($log->ip_address); ?></code></td>
                                    <td><?php echo esc_html(human_time_diff(strtotime($log->created_at), current_time('timestamp'))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="4"><?php esc_html_e('Aucun événement suspect détecté.', 'secure-ebook-reader'); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
