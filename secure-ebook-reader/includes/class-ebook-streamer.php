<?php
/**
 * Moteur de streaming partiel RFC 7233 (HTTP Range Requests)
 *
 * Permet à PDF.js de charger les pages à la volée avec une consommation mémoire
 * quasi-nulle côté serveur, sans jamais charger l'intégralité du fichier en RAM.
 *
 * @package Secure_Ebook_Reader
 */

if (!defined('ABSPATH')) {
    exit;
}

class Secure_Ebook_Streamer {

    /**
     * Taille de buffer par défaut pour la lecture en continu (64 Ko)
     */
    const CHUNK_SIZE = 65536;

    /**
     * Stream le fichier PDF en gérant les requêtes HTTP Range
     *
     * @param string $file_path Chemin absolu du fichier PDF
     * @return void
     */
    public static function stream($file_path) {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            status_header(404);
            nocache_headers();
            wp_die(
                esc_html__('Fichier introuvable ou inaccessible.', 'secure-ebook-reader'),
                esc_html__('Erreur 404', 'secure-ebook-reader'),
                ['response' => 404]
            );
        }

        $file_size = (int) filesize($file_path);
        if ($file_size <= 0) {
            status_header(500);
            wp_die(esc_html__('Fichier vide ou corrompu.', 'secure-ebook-reader'), '', ['response' => 500]);
        }

        $start = 0;
        $end = $file_size - 1;

        // Analyse de l'en-tête Range
        if (isset($_SERVER['HTTP_RANGE'])) {
            $range_header = sanitize_text_field(wp_unslash($_SERVER['HTTP_RANGE']));

            if (preg_match('/bytes=\s*(\d*)-(\d*)/i', $range_header, $matches)) {
                $raw_start = $matches[1];
                $raw_end   = $matches[2];

                if ($raw_start === '' && $raw_end !== '') {
                    // bytes=-500 : les 500 derniers octets
                    $start = $file_size - (int) $raw_end;
                    $end   = $file_size - 1;
                } elseif ($raw_start !== '' && $raw_end === '') {
                    // bytes=500- : à partir de 500 jusqu'à la fin
                    $start = (int) $raw_start;
                    $end   = $file_size - 1;
                } elseif ($raw_start !== '' && $raw_end !== '') {
                    // bytes=500-1000 : plage spécifique
                    $start = (int) $raw_start;
                    $end   = (int) $raw_end;
                }

                // Vérification de validité de la plage
                if ($start > $end || $start >= $file_size || $end >= $file_size || $start < 0) {
                    header('HTTP/1.1 416 Range Not Satisfiable');
                    header("Content-Range: bytes */{$file_size}");
                    exit;
                }
            }
        }

        // Désactiver la limite de temps pour les connexions lentes
        if (!ini_get('safe_mode') && function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        // Envoi des en-têtes HTTP sécurisés
        Secure_Ebook_Security::send_secure_stream_headers($file_size, $start, $end);

        // Lecture et envoi par blocs de 64 Ko
        $fp = @fopen($file_path, 'rb');
        if (!$fp) {
            status_header(500);
            exit;
        }

        fseek($fp, $start);
        $bytes_to_send = $end - $start + 1;

        while ($bytes_to_send > 0 && !feof($fp) && (connection_status() === CONNECTION_NORMAL)) {
            $read_length = min(self::CHUNK_SIZE, $bytes_to_send);
            $buffer = fread($fp, $read_length);

            if ($buffer === false) {
                break;
            }

            echo $buffer;
            flush();

            $bytes_to_send -= strlen($buffer);
        }

        fclose($fp);
        exit;
    }
}
