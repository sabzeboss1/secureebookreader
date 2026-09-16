<?php
/**
 * Contrôleur REST API (Namespace: secure-ebook/v1)
 *
 * Implémente la protection anti-IDOR avec double vérification systématique,
 * la distribution des jetons et le streaming partiel Range Request.
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('ABSPATH')) {
    exit;
}

class Secure_Ebook_API {

    /**
     * Namespace de l'API REST
     */
    const NAMESPACE = 'secure-ebook/v1';

    /**
     * Enregistre les hooks de l'API REST
     */
    public function register_hooks() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Déclaration des routes REST
     */
    public function register_routes() {
        // 1. Génération de jeton de lecture temporaire
        register_rest_route(self::NAMESPACE, '/read/(?P<id>\d+)/token', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_generate_token'],
            'permission_callback' => [$this, 'check_user_logged_in'],
            'args'                => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
            ],
        ]);

        // 2. Flux de streaming partiel du document PDF
        register_rest_route(self::NAMESPACE, '/read/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_stream_file'],
            'permission_callback' => '__return_true', // La vérification métier stricte est effectuée dans le callback pour renvoyer le code HTTP exact
            'args'                => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
            ],
        ]);

        // 3. Sauvegarde de la progression de lecture
        register_rest_route(self::NAMESPACE, '/progress/(?P<id>\d+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_save_progress'],
            'permission_callback' => [$this, 'check_user_logged_in'],
            'args'                => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'page' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param >= 1;
                    }
                ],
            ],
        ]);

        // 4. Heartbeat (renouvellement transparent du jeton)
        register_rest_route(self::NAMESPACE, '/heartbeat/(?P<id>\d+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_heartbeat'],
            'permission_callback' => [$this, 'check_user_logged_in'],
        ]);

        // 5. Bibliothèque utilisateur (JSON)
        register_rest_route(self::NAMESPACE, '/library', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_get_library'],
            'permission_callback' => [$this, 'check_user_logged_in'],
        ]);
    }

    /**
     * Vérifie si l'utilisateur est authentifié
     */
    public function check_user_logged_in() {
        return is_user_logged_in();
    }

    /**
     * Endpoint POST /read/{id}/token
     * Génère un jeton éphémère après vérification rigoureuse des droits
     */
    public function handle_generate_token($request) {
        $ebook_id = absint($request['id']);
        $user_id  = get_current_user_id();

        // Vérification anti-IDOR : l'utilisateur possède-t-il les droits sur CET ebook précis ?
        if (!Secure_Ebook_Access::can_read($user_id, $ebook_id)) {
            Secure_Ebook_Logger::log(
                'idor_attempt_blocked',
                'danger',
                sprintf('Tentative non autorisée d\'obtention de token par utilisateur #%d pour ebook #%d', $user_id, $ebook_id),
                $user_id,
                $ebook_id
            );

            return new WP_Error(
                'rest_forbidden',
                esc_html__('Accès refusé. Vous ne possédez pas les droits requis pour consulter cet ouvrage.', 'secure-ebook-reader'),
                ['status' => 403]
            );
        }

        $raw_token = Secure_Ebook_Security::generate_read_token($user_id, $ebook_id);
        $settings  = get_option('secure_ebook_settings', []);
        $ttl_sec   = (!empty($settings['token_ttl_minutes']) ? absint($settings['token_ttl_minutes']) : 15) * 60;

        return rest_ensure_response([
            'success'    => true,
            'token'      => $raw_token,
            'expires_in' => $ttl_sec,
            'ebook_id'   => $ebook_id,
        ]);
    }

    /**
     * Endpoint GET /read/{id}?token=...
     * Sert le contenu PDF avec support Range Requests
     */
    public function handle_stream_file($request) {
        $ebook_id  = absint($request['id']);
        $raw_token = sanitize_text_field($request->get_param('token'));
        $user_id   = get_current_user_id();

        // 1. Authentification requise
        if (!$user_id) {
            Secure_Ebook_Logger::log(
                'stream_denied_unauthenticated',
                'warning',
                sprintf('Tentative de stream anonyme sur l\'ebook #%d', $ebook_id),
                0,
                $ebook_id
            );
            return new WP_Error('unauthorized', esc_html__('Connexion requise pour accéder au flux de lecture.', 'secure-ebook-reader'), ['status' => 401]);
        }

        // 2. Validation stricte du jeton éphémère (Protection Anti-IDOR & Anti-Partage)
        if (!Secure_Ebook_Security::validate_token($raw_token, $user_id, $ebook_id)) {
            Secure_Ebook_Logger::log(
                'stream_denied_invalid_token',
                'danger',
                sprintf('Jeton invalide ou usurpé pour utilisateur #%d sur ebook #%d', $user_id, $ebook_id),
                $user_id,
                $ebook_id
            );
            return new WP_Error('forbidden_token', esc_html__('Jeton de lecture invalide, révoqué ou expiré.', 'secure-ebook-reader'), ['status' => 403]);
        }

        // 3. Re-vérification métier centrale des droits (Cas d'un remboursement pendant la session)
        if (!Secure_Ebook_Access::can_read($user_id, $ebook_id)) {
            Secure_Ebook_Logger::log(
                'stream_denied_access_revoked',
                'danger',
                sprintf('Accès métier révoqué pour utilisateur #%d sur ebook #%d', $user_id, $ebook_id),
                $user_id,
                $ebook_id
            );
            return new WP_Error('access_revoked', esc_html__('Vos droits d\'accès à cet ebook ont été clôturés.', 'secure-ebook-reader'), ['status' => 403]);
        }

        // 4. Récupération du fichier PDF et streaming
        $ebook = Secure_Ebook_Ebook::get($ebook_id);
        if (!$ebook) {
            return new WP_Error('not_found', esc_html__('Ebook introuvable.', 'secure-ebook-reader'), ['status' => 404]);
        }

        $full_path = Secure_Ebook_Ebook::get_full_path($ebook->file_path);

        // Lancer le flux RFC 7233
        Secure_Ebook_Streamer::stream($full_path);
        exit;
    }

    /**
     * Endpoint POST /progress/{id}
     */
    public function handle_save_progress($request) {
        $ebook_id    = absint($request['id']);
        $user_id     = get_current_user_id();
        $page        = absint($request->get_param('page'));
        $total_pages = absint($request->get_param('total_pages'));

        if (!Secure_Ebook_Access::can_read($user_id, $ebook_id)) {
            return new WP_Error('forbidden', esc_html__('Accès non autorisé.', 'secure-ebook-reader'), ['status' => 403]);
        }

        $saved = Secure_Ebook_Progress::save_progress($user_id, $ebook_id, $page, $total_pages);

        return rest_ensure_response([
            'success' => (bool) $saved,
            'page'    => $page,
        ]);
    }

    /**
     * Endpoint POST /heartbeat/{id}
     */
    public function handle_heartbeat($request) {
        $ebook_id  = absint($request['id']);
        $user_id   = get_current_user_id();
        $raw_token = sanitize_text_field($request->get_param('token'));

        // Si l'utilisateur n'a plus accès (ex: commande remboursée), signaler immédiatement au client
        if (!Secure_Ebook_Access::can_read($user_id, $ebook_id)) {
            return rest_ensure_response([
                'success' => false,
                'revoked' => true,
                'message' => esc_html__('Vos droits d\'accès ont expiré ou ont été révoqués.', 'secure-ebook-reader'),
            ]);
        }

        $renewed = Secure_Ebook_Security::renew_token($raw_token, $user_id, $ebook_id);

        return rest_ensure_response([
            'success' => (bool) $renewed,
            'revoked' => false,
        ]);
    }

    /**
     * Endpoint GET /library
     */
    public function handle_get_library() {
        $user_id = get_current_user_id();
        $ebooks  = Secure_Ebook_Access::get_user_ebooks($user_id);

        $data = [];
        foreach ($ebooks as $ebook) {
            $data[] = [
                'id'         => (int) $ebook->id,
                'title'      => $ebook->title,
                'author'     => $ebook->author,
                'page_count' => (int) $ebook->page_count,
                'last_page'  => (int) $ebook->last_page,
                'progress'   => (float) $ebook->progress,
                'read_url'   => home_url('/lecture-ebook/' . $ebook->id . '/'),
            ];
        }

        return rest_ensure_response([
            'success' => true,
            'ebooks'  => $data,
        ]);
    }
}
