<?php
/**
 * Modèle de données Ebook sécurisé
 *
 * Gère le CRUD des ebooks, le stockage hermétique, le calcul de hachage sha256,
 * et la vérification d'intégrité.
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('ABSPATH')) {
    exit;
}

class Secure_Ebook_Ebook {

    /**
     * Récupère un ebook par son ID
     *
     * @param int $id ID de l'ebook
     * @return object|null Données de l'ebook
     */
    public static function get($id) {
        global $wpdb;
        $id = absint($id);
        if (!$id) {
            return null;
        }

        $table = $wpdb->prefix . 'secure_ebooks';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    /**
     * Récupère un ebook lié à un produit WooCommerce
     *
     * @param int $product_id ID du produit WooCommerce
     * @return object|null Données de l'ebook
     */
    public static function get_by_product($product_id) {
        global $wpdb;
        $product_id = absint($product_id);
        if (!$product_id) {
            return null;
        }

        $table = $wpdb->prefix . 'secure_ebooks';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE product_id = %d AND status = 'active' LIMIT 1", $product_id));
    }

    /**
     * Récupère la liste des ebooks avec filtres
     *
     * @param array $args Paramètres de recherche
     * @return array Liste des ebooks
     */
    public static function get_all($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'secure_ebooks';

        $defaults = [
            'status'  => '',
            'search'  => '',
            'orderby' => 'id',
            'order'   => 'DESC',
            'limit'   => 50,
            'offset'  => 0,
        ];
        $r = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $values = [];

        if (!empty($r['status'])) {
            $where[] = "status = %s";
            $values[] = sanitize_text_field($r['status']);
        }

        if (!empty($r['search'])) {
            $where[] = "(title LIKE %s OR author LIKE %s)";
            $like = '%' . $wpdb->esc_like(sanitize_text_field($r['search'])) . '%';
            $values[] = $like;
            $values[] = $like;
        }

        $where_sql = implode(' AND ', $where);
        $order_by = in_array(strtolower($r['orderby']), ['id', 'title', 'author', 'created_at', 'page_count']) ? $r['orderby'] : 'id';
        $order = strtoupper($r['order']) === 'ASC' ? 'ASC' : 'DESC';
        $limit = absint($r['limit']);
        $offset = absint($r['offset']);

        $query = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$order_by} {$order} LIMIT {$limit} OFFSET {$offset}";

        if (!empty($values)) {
            return $wpdb->get_results($wpdb->prepare($query, $values));
        }

        return $wpdb->get_results($query);
    }

    /**
     * Compte le nombre total d'ebooks
     */
    public static function count_all($status = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'secure_ebooks';

        if (!empty($status)) {
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", $status));
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * Crée un nouvel enregistrement d'ebook
     *
     * @param array $data Données brutes
     * @return int|false ID de l'ebook créé ou false
     */
    public static function create($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'secure_ebooks';

        $insert_data = [
            'product_id'      => isset($data['product_id']) ? absint($data['product_id']) : 0,
            'title'           => sanitize_text_field($data['title']),
            'author'          => isset($data['author']) ? sanitize_text_field($data['author']) : '',
            'description'     => isset($data['description']) ? wp_kses_post($data['description']) : '',
            'file_name'       => sanitize_file_name($data['file_name']),
            'file_path'       => sanitize_text_field($data['file_path']),
            'file_hash'       => sanitize_text_field($data['file_hash']),
            'file_size'       => isset($data['file_size']) ? absint($data['file_size']) : 0,
            'page_count'      => isset($data['page_count']) ? absint($data['page_count']) : 0,
            'access_mode'     => !empty($data['access_mode']) ? sanitize_text_field($data['access_mode']) : 'online_only',
            'access_duration' => !empty($data['access_duration']) ? sanitize_text_field($data['access_duration']) : 'permanent',
            'protections'     => isset($data['protections']) ? wp_json_encode($data['protections']) : '',
            'cover_image_id'  => isset($data['cover_image_id']) ? absint($data['cover_image_id']) : 0,
            'status'          => !empty($data['status']) ? sanitize_text_field($data['status']) : 'active',
            'created_at'      => current_time('mysql'),
            'updated_at'      => current_time('mysql'),
        ];

        $format = ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s'];

        $result = $wpdb->insert($table, $insert_data, $format);
        if ($result) {
            $ebook_id = $wpdb->insert_id;
            do_action('secure_ebook_created', $ebook_id, $insert_data);
            return $ebook_id;
        }

        return false;
    }

    /**
     * Met à jour un ebook existant
     *
     * @param int $id ID de l'ebook
     * @param array $data Données à modifier
     * @return bool
     */
    public static function update($id, $data) {
        global $wpdb;
        $id = absint($id);
        if (!$id) {
            return false;
        }

        $table = $wpdb->prefix . 'secure_ebooks';
        $update_data = ['updated_at' => current_time('mysql')];
        $format = ['%s'];

        $allowed_fields = [
            'product_id'      => '%d',
            'title'           => '%s',
            'author'          => '%s',
            'description'     => '%s',
            'file_name'       => '%s',
            'file_path'       => '%s',
            'file_hash'       => '%s',
            'file_size'       => '%d',
            'page_count'      => '%d',
            'access_mode'     => '%s',
            'access_duration' => '%s',
            'protections'     => '%s',
            'cover_image_id'  => '%d',
            'status'          => '%s',
        ];

        foreach ($allowed_fields as $field => $field_format) {
            if (array_key_exists($field, $data)) {
                if ($field === 'protections' && is_array($data[$field])) {
                    $update_data[$field] = wp_json_encode($data[$field]);
                } elseif ($field_format === '%d') {
                    $update_data[$field] = absint($data[$field]);
                } else {
                    $update_data[$field] = sanitize_text_field($data[$field]);
                }
                $format[] = $field_format;
            }
        }

        $result = $wpdb->update($table, $update_data, ['id' => $id], $format, ['%d']);
        if (false !== $result) {
            do_action('secure_ebook_updated', $id, $update_data);
            return true;
        }

        return false;
    }

    /**
     * Supprime définitivement un ebook et son fichier sécurisé
     *
     * @param int $id ID de l'ebook
     * @return bool
     */
    public static function delete($id) {
        global $wpdb;
        $id = absint($id);
        $ebook = self::get($id);
        if (!$ebook) {
            return false;
        }

        // Supprimer le fichier physique sécurisé
        $full_path = self::get_full_path($ebook->file_path);
        if (file_exists($full_path)) {
            @unlink($full_path);
        }

        // Supprimer les accès associés
        $wpdb->delete($wpdb->prefix . 'secure_ebook_access', ['ebook_id' => $id], ['%d']);
        // Supprimer la progression de lecture
        $wpdb->delete($wpdb->prefix . 'secure_ebook_reading', ['ebook_id' => $id], ['%d']);
        // Supprimer les tokens
        $wpdb->delete($wpdb->prefix . 'secure_ebook_tokens', ['ebook_id' => $id], ['%d']);

        // Supprimer l'enregistrement de l'ebook
        $wpdb->delete($wpdb->prefix . 'secure_ebooks', ['id' => $id], ['%d']);

        do_action('secure_ebook_deleted', $id);
        return true;
    }

    /**
     * Retourne le chemin absolu du dossier sécurisé (Vault)
     */
    public static function get_vault_dir() {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['basedir']) . 'secure-ebooks-vault';
    }

    /**
     * Retourne le chemin absolu d'un fichier à partir de son chemin relatif
     */
    public static function get_full_path($relative_path) {
        // Empêcher toute tentative de Path Traversal
        $relative_path = str_replace(['../', '..\\'], '', $relative_path);
        $relative_path = ltrim($relative_path, '/\\');
        return self::get_vault_dir() . '/' . $relative_path;
    }

    /**
     * Enregistre un fichier PDF téléversé de façon sécurisée
     * Renomme le fichier avec un identifiant cryptographique aléatoire
     *
     * @param array $file Tableau $_FILES['ebook_file']
     * @return array|WP_Error Informations sur le fichier ou erreur
     */
    public static function handle_secure_upload($file) {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('no_file', esc_html__('Aucun fichier téléversé.', 'secure-ebook-reader'));
        }

        // Vérification de la taille (ex: max 200 Mo)
        $max_size = 200 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', esc_html__('Le fichier dépasse la taille maximale autorisée (200 Mo).', 'secure-ebook-reader'));
        }

        // Vérification MIME & Magic Bytes (Doit commencer par %PDF-)
        $handle = fopen($file['tmp_name'], 'rb');
        $header = fread($handle, 5);
        fclose($handle);

        if ($header !== '%PDF-') {
            return new WP_Error('invalid_pdf', esc_html__('Le fichier n\'est pas un document PDF valide.', 'secure-ebook-reader'));
        }

        $vault_dir = self::get_vault_dir();
        if (!file_exists($vault_dir)) {
            Secure_Ebook_Activator::create_secure_vault_directory();
        }

        // Générer un nom opaque impossible à deviner (UUIDv4 sha256)
        $random_token = wp_generate_password(32, false);
        $safe_filename = hash('sha256', $file['name'] . $random_token . microtime(true)) . '.pdf';
        $destination = $vault_dir . '/' . $safe_filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return new WP_Error('upload_failed', esc_html__('Impossible de déplacer le fichier dans le dossier sécurisé.', 'secure-ebook-reader'));
        }

        // Fixer des permissions restreintes
        @chmod($destination, 0640);

        // Calculer l'empreinte sha256 d'intégrité
        $file_hash = hash_file('sha256', $destination);
        $file_size = filesize($destination);
        $page_count = self::extract_pdf_page_count($destination);

        return [
            'original_name' => sanitize_file_name($file['name']),
            'file_name'     => $safe_filename,
            'file_path'     => $safe_filename,
            'file_hash'     => $file_hash,
            'file_size'     => $file_size,
            'page_count'    => $page_count,
        ];
    }

    /**
     * Tente d'extraire le nombre total de pages d'un PDF sans dépendance lourde
     */
    public static function extract_pdf_page_count($file_path) {
        if (!file_exists($file_path)) {
            return 0;
        }

        $fp = @fopen($file_path, 'rb');
        if (!$fp) {
            return 0;
        }

        $page_count = 0;
        while (!feof($fp)) {
            $buffer = fread($fp, 65536);
            if (preg_match_all("/\/Type\s*\/Page\b/", $buffer, $matches)) {
                $page_count += count($matches[0]);
            }
        }
        fclose($fp);

        return max(1, $page_count);
    }
}
