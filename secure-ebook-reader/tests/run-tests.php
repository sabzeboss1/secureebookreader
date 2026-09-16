<?php
/**
 * Suite de tests de sécurité et conformité — Secure Ebook Reader
 * Exécute et valide les 10 scénarios d'attaque et de droits prévus au cahier des charges.
 *
 * @package Secure_Ebook_Reader
 */

require_once __DIR__ . '/TestCase.php';

echo "\n============================================================\n";
echo "   SECURE EBOOK READER — SUITE DE VALIDATION DE SÉCURITÉ   \n";
echo "============================================================\n\n";

$passed = 0;
$failed = 0;

function run_test($description, $assertion) {
    global $passed, $failed;
    if ($assertion) {
        echo "  [\033[32mPASS\033[0m] " . $description . "\n";
        $passed++;
    } else {
        echo "  [\033[31mFAIL\033[0m] " . $description . "\n";
        $failed++;
    }
}

// -------------------------------------------------------------
// Préparation des données de test
// -------------------------------------------------------------
global $wpdb;

// Création d'un Ebook A (ID 10) et d'un Ebook B (ID 20)
$ebook_a_id = 10;
$wpdb->tables['wp_secure_ebooks'][$ebook_a_id] = (object) [
    'id' => $ebook_a_id,
    'title' => 'Guide Stratégique de l\'Architecte Web',
    'author' => 'Alexandre Dumas',
    'file_path' => 'protected-vault/guide.pdf',
    'file_hash' => hash('sha256', 'mock_content_a'),
    'page_count' => 140,
    'access_duration' => 'permanent',
    'status' => 'active'
];

$ebook_b_id = 20;
$wpdb->tables['wp_secure_ebooks'][$ebook_b_id] = (object) [
    'id' => $ebook_b_id,
    'title' => 'Secrets de Cybersécurité Avancée',
    'author' => 'Alan Turing',
    'file_path' => 'protected-vault/secrets.pdf',
    'file_hash' => hash('sha256', 'mock_content_b'),
    'page_count' => 300,
    'access_duration' => '30d',
    'status' => 'active'
];

$user_a = 101; // Client ayant acheté l'ebook A
$user_b = 202; // Client pirate ou sans achat

// -------------------------------------------------------------
// Scénario 1 : Utilisateur non connecté sur /lire/ebook/10/
// -------------------------------------------------------------
$res1 = Secure_Ebook_Access::can_read(0, $ebook_a_id);
run_test("Scénario 1 : Utilisateur anonyme (user_id=0) rejeté", $res1 === false);

// -------------------------------------------------------------
// Scénario 2 : Utilisateur connecté mais n'ayant pas acheté
// -------------------------------------------------------------
$res2 = Secure_Ebook_Access::can_read($user_b, $ebook_a_id);
run_test("Scénario 2 : Utilisateur connecté sans achat rejeté (403)", $res2 === false);

// -------------------------------------------------------------
// Scénario 3 : Utilisateur connecté ayant acheté l'ebook A
// -------------------------------------------------------------
Secure_Ebook_Access::grant_access($user_a, $ebook_a_id, 5001, 'woocommerce', 'permanent');
$res3 = Secure_Ebook_Access::can_read($user_a, $ebook_a_id);
run_test("Scénario 3 : Utilisateur légitime ayant acheté accordé (200)", $res3 === true);

// -------------------------------------------------------------
// Scénario 4 : Protection hermétique de l'URL directe (Path Traversal)
// -------------------------------------------------------------
$clean_path = Secure_Ebook_Ebook::get_full_path('../../etc/passwd');
$has_traversal = (strpos($clean_path, '..') !== false);
run_test("Scénario 4 : Blocage des tentatives de Path Traversal (../../)", $has_traversal === false);

// -------------------------------------------------------------
// Scénario 5 : Partage de jeton temporaire d'un utilisateur A vers B
// -------------------------------------------------------------
$token_a = Secure_Ebook_Security::generate_read_token($user_a, $ebook_a_id);
$res5 = Secure_Ebook_Security::validate_token($token_a, $user_b, $ebook_a_id);
run_test("Scénario 5 : Jeton de l'utilisateur A transmis à B rejeté", $res5 === false);

// -------------------------------------------------------------
// Scénario 6 : Commande remboursée -> révocation immédiate des droits et jetons
// -------------------------------------------------------------
$revoked_count = Secure_Ebook_Access::revoke_by_order(5001);
$can_read_after_refund = Secure_Ebook_Access::can_read($user_a, $ebook_a_id);
$token_after_refund = Secure_Ebook_Security::validate_token($token_a, $user_a, $ebook_a_id);
run_test("Scénario 6 : Remboursement commande -> accès immédiatement coupé & token invalidé", ($can_read_after_refund === false && $token_after_refund === false));

// -------------------------------------------------------------
// Scénario 7 : Commande annulée -> accès refusé
// -------------------------------------------------------------
Secure_Ebook_Access::grant_access($user_a, $ebook_a_id, 5002, 'woocommerce');
Secure_Ebook_Access::revoke_by_order(5002);
$res7 = Secure_Ebook_Access::can_read($user_a, $ebook_a_id);
run_test("Scénario 7 : Commande annulée -> droit de lecture révoqué", $res7 === false);

// -------------------------------------------------------------
// Scénario 8 : Utilisateur a l'ebook A mais pas l'ebook B
// -------------------------------------------------------------
Secure_Ebook_Access::grant_access($user_a, $ebook_a_id, 5003, 'woocommerce');
$can_read_a = Secure_Ebook_Access::can_read($user_a, $ebook_a_id);
$can_read_b = Secure_Ebook_Access::can_read($user_a, $ebook_b_id);
run_test("Scénario 8 : Cloisonnement strict (Ebook A accessible, Ebook B refusé)", ($can_read_a === true && $can_read_b === false));

// -------------------------------------------------------------
// Scénario 9 : Accès expiré (durée limitée dépassée)
// -------------------------------------------------------------
// Attribution avec expiration passée hier
$yesterday = date('Y-m-d H:i:s', strtotime('-1 day', time()));
$wpdb->tables['wp_secure_ebook_access'][] = (object) [
    'id' => 999,
    'user_id' => $user_a,
    'ebook_id' => $ebook_b_id,
    'order_id' => 5004,
    'source' => 'woocommerce',
    'granted_at' => date('Y-m-d H:i:s', strtotime('-31 days', time())),
    'expires_at' => $yesterday,
    'status' => 'granted'
];
$res9 = Secure_Ebook_Access::can_read($user_a, $ebook_b_id);
run_test("Scénario 9 : Accès à durée limitée expiré refusé", $res9 === false);

// -------------------------------------------------------------
// Scénario 10 : Test Anti-IDOR explicite (permutation d'ebook_id avec un jeton)
// -------------------------------------------------------------
$token_valid_for_a = Secure_Ebook_Security::generate_read_token($user_a, $ebook_a_id);
// L'attaquant tente d'utiliser le token de l'ebook A pour ouvrir l'ebook B
$idor_attempt = Secure_Ebook_Security::validate_token($token_valid_for_a, $user_a, $ebook_b_id);
run_test("Scénario 10 : Attaque IDOR bloquée (Jeton d'un ebook utilisé sur un autre ebook)", $idor_attempt === false);

// -------------------------------------------------------------
// Scénario 11 : Progression de lecture et mémorisation
// -------------------------------------------------------------
Secure_Ebook_Progress::save_progress($user_a, $ebook_a_id, 42, 140);
$reading_entry = reset($wpdb->tables['wp_secure_ebook_reading']);
$progress_ok = ($reading_entry && $reading_entry->last_page == 42 && $reading_entry->progress == 30.00);
run_test("Scénario 11 : Suivi de lecture (Page 42 sur 140 = 30.00%)", $progress_ok === true);

// -------------------------------------------------------------
// Scénario 12 : Heartbeat et renouvellement de jeton
// -------------------------------------------------------------
$renewed = Secure_Ebook_Security::renew_token($token_valid_for_a, $user_a, $ebook_a_id);
run_test("Scénario 12 : Heartbeat de maintien de session sécurisée", $renewed === true);

// -------------------------------------------------------------
// Scénario 13 : Validation de l'Autoloader pour toutes les classes (Linux compatible)
// -------------------------------------------------------------
$classes_to_test = [
    'Secure_Ebook_Plugin',
    'Secure_Ebook_Activator',
    'Secure_Ebook_Deactivator',
    'Secure_Ebook_Ebook',
    'Secure_Ebook_Access',
    'Secure_Ebook_Security',
    'Secure_Ebook_Streamer',
    'Secure_Ebook_Logger',
    'Secure_Ebook_Progress',
    'Secure_Ebook_API',
    'Secure_Ebook_WooCommerce',
    'Secure_Ebook_Admin',
];
$all_loaded = true;
foreach ($classes_to_test as $cls) {
    if (!class_exists($cls)) {
        $all_loaded = false;
        break;
    }
}
run_test("Scénario 13 : Résolution de l'Autoloader sur Linux (Class Map direct)", $all_loaded === true);

echo "\n------------------------------------------------------------\n";
echo sprintf("RÉSULTAT : %d tests réussis sur %d (%d échecs)\n", $passed, ($passed + $failed), $failed);
echo "------------------------------------------------------------\n\n";

exit($failed > 0 ? 1 : 0);
