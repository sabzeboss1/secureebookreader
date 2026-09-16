<?php
/**
 * Journal d'Audit de Sécurité — Secure Ebook Reader
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('ABSPATH')) {
    exit;
}

$severity   = isset($_GET['severity']) ? sanitize_text_field(wp_unslash($_GET['severity'])) : '';
$event_type = isset($_GET['event_type']) ? sanitize_text_field(wp_unslash($_GET['event_type'])) : '';

$logs = Secure_Ebook_Logger::get_logs([
    'severity'   => $severity,
    'event_type' => $event_type,
    'limit'      => 100,
]);
?>
<div class="wrap secure-admin-wrap">
    <div class="secure-admin-header">
        <div>
            <h1><?php esc_html_e('Journal d\'Audit de Sécurité', 'secure-ebook-reader'); ?></h1>
            <p class="subtitle"><?php esc_html_e('Traçabilité des accès, détection des tentatives IDOR et historique des révocations.', 'secure-ebook-reader'); ?></p>
        </div>
    </div>

    <!-- Filtres -->
    <div class="tablenav top">
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="alignleft actions">
            <input type="hidden" name="page" value="secure-ebook-logs" />
            <select name="severity">
                <option value=""><?php esc_html_e('Toutes les gravités', 'secure-ebook-reader'); ?></option>
                <option value="danger" <?php selected($severity, 'danger'); ?>><?php esc_html_e('Danger (Attaques / Blocages)', 'secure-ebook-reader'); ?></option>
                <option value="warning" <?php selected($severity, 'warning'); ?>><?php esc_html_e('Avertissements (Révocations / Expirations)', 'secure-ebook-reader'); ?></option>
                <option value="info" <?php selected($severity, 'info'); ?>><?php esc_html_e('Informations (Attributions / Streams)', 'secure-ebook-reader'); ?></option>
            </select>

            <select name="event_type">
                <option value=""><?php esc_html_e('Tous les types d\'événements', 'secure-ebook-reader'); ?></option>
                <option value="idor_attempt_blocked" <?php selected($event_type, 'idor_attempt_blocked'); ?>><?php esc_html_e('Tentative IDOR bloquée', 'secure-ebook-reader'); ?></option>
                <option value="token_validation_failed" <?php selected($event_type, 'token_validation_failed'); ?>><?php esc_html_e('Échec de validation de token', 'secure-ebook-reader'); ?></option>
                <option value="stream_denied_invalid_token" <?php selected($event_type, 'stream_denied_invalid_token'); ?>><?php esc_html_e('Stream refusé (token invalide)', 'secure-ebook-reader'); ?></option>
                <option value="access_revoked" <?php selected($event_type, 'access_revoked'); ?>><?php esc_html_e('Accès révoqué', 'secure-ebook-reader'); ?></option>
                <option value="refund_revocation" <?php selected($event_type, 'refund_revocation'); ?>><?php esc_html_e('Révocation suite à remboursement', 'secure-ebook-reader'); ?></option>
                <option value="access_granted" <?php selected($event_type, 'access_granted'); ?>><?php esc_html_e('Accès accordé', 'secure-ebook-reader'); ?></option>
            </select>

            <input type="submit" class="button" value="<?php esc_attr_e('Filtrer', 'secure-ebook-reader'); ?>" />
        </form>
    </div>

    <!-- Tableau des logs -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 140px;"><?php esc_html_e('Date / Heure', 'secure-ebook-reader'); ?></th>
                <th style="width: 100px;"><?php esc_html_e('Gravité', 'secure-ebook-reader'); ?></th>
                <th style="width: 180px;"><?php esc_html_e('Événement', 'secure-ebook-reader'); ?></th>
                <th><?php esc_html_e('Utilisateur', 'secure-ebook-reader'); ?></th>
                <th><?php esc_html_e('Ebook', 'secure-ebook-reader'); ?></th>
                <th><?php esc_html_e('Message / Détails', 'secure-ebook-reader'); ?></th>
                <th style="width: 130px;"><?php esc_html_e('Adresse IP', 'secure-ebook-reader'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($logs)) : ?>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html(date_i18n('d/m/Y H:i:s', strtotime($log->created_at))); ?></td>
                        <td>
                            <span class="badge badge-<?php echo esc_attr($log->severity === 'danger' ? 'danger' : ($log->severity === 'warning' ? 'warning' : 'info')); ?>">
                                <?php echo esc_html(strtoupper($log->severity)); ?>
                            </span>
                        </td>
                        <td><code><?php echo esc_html($log->event_type); ?></code></td>
                        <td>
                            <?php if ($log->user_id > 0) : ?>
                                <strong><?php echo esc_html($log->user_login ?: '#' . $log->user_id); ?></strong>
                                <?php if ($log->user_email) : ?>
                                    <br><small class="text-muted"><?php echo esc_html($log->user_email); ?></small>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="text-muted"><?php esc_html_e('Anonyme', 'secure-ebook-reader'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log->ebook_id > 0) : ?>
                                <?php echo esc_html($log->ebook_title ?: '#' . $log->ebook_id); ?>
                            <?php else : ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($log->message); ?></td>
                        <td><code><?php echo esc_html($log->ip_address ?: '—'); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 30px;">
                        <?php esc_html_e('Aucun événement enregistré selon ces critères.', 'secure-ebook-reader'); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
