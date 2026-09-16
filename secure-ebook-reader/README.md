# Secure Ebook Reader (SER)

> **Solution d'élite pour la vente et la lecture sécurisée d'ebooks PDF en ligne sur WordPress & WooCommerce sans téléchargement.**

---

## 🌟 Points Forts & Architecture "Pro de chez Pro"

1. **Sécurité Zero-Trust & Anti-IDOR** :
   - Fichiers sources stockés dans un coffre-fort hermétique (`wp-content/uploads/secure-ebooks-vault/`) protégé par triple verrou (.htaccess, index.php, vérification PHP systématique).
   - Impossible de deviner ou modifier un identifiant d'ebook pour voler le contenu (anti-IDOR).
   - Point de vérité unique d'accès : `Secure_Ebook_Access::can_read($user_id, $ebook_id)`.

2. **Moteur de Streaming RFC 7233 (HTTP Range Requests)** :
   - Prise en charge native du streaming partiel `206 Partial Content`.
   - Charge instantanément les pages sans saturer la RAM du serveur (chunks de 64 Ko via buffer).

3. **Jetons Temporaires SHA-256 Révocables** :
   - Jetons cryptographiques à durée de vie courte (15 minutes), régénérés automatiquement en arrière-plan (Heartbeat).
   - En cas d'annulation ou de remboursement d'une commande WooCommerce, l'accès et les jetons sont coupés **instantanément**.

4. **Lecteur PDF.js Sur-Mesure Embarqué (Zéro CDN)** :
   - Thèmes Clair, Sombre et Sépia.
   - Mode plein écran, zoom intelligent, barre latérale avec vignettes.
   - Désactivation du clic droit, blocage des raccourcis clavier (`Ctrl+P`, `Ctrl+S`, `F12`), styles d'impression masqués.

5. **Filigrane Dynamique Dual-Layer (Canvas + DOM)** :
   - Affichage de l'identité de l'acheteur (Nom, Email, IP, Date).
   - Incrustation directe dans les pixels du canvas PDF.js pour neutraliser toute tentative de masquage via l'inspecteur d'éléments.

6. **Intégration WooCommerce & Espace Mon Compte** :
   - Onglet produit dédié pour associer un ebook.
   - Onglet client "Mes Ebooks" dans Mon Compte avec suivi de la progression et reprise automatique à la dernière page lue.

7. **Journal d'Audit de Sécurité Temps Réel** :
   - Détection et enregistrement des tentatives d'accès frauduleuses.

---

## 🚀 Installation

1. Téléchargez ou générez l'archive `secure-ebook-reader.zip`.
2. Dans votre administration WordPress, rendez-vous sur **Extensions > Ajouter une extension > Téléverser une extension**.
3. Sélectionnez `secure-ebook-reader.zip` et cliquez sur **Installer maintenant**, puis sur **Activer**.
4. Configurez vos réglages dans **Secure Ebooks > Réglages**.

---

## 🔒 Configuration Serveur

### Apache / LiteSpeed
Le plugin crée automatiquement le fichier `.htaccess` avec la directive `Require all denied` dans le dossier du coffre-fort.

### Nginx
Ajoutez la règle suivante dans votre configuration de serveur virtuel :
```nginx
location ^~ /wp-content/uploads/secure-ebooks-vault/ {
    deny all;
    return 403;
}
```

---

## 🧪 Tests de Sécurité Automatisés

Le plugin intègre une suite de tests complète validant les 10 scénarios d'attaque et de droits :
```powershell
php tests/run-tests.php
```

---
© 2026 sabzeboss. Conçu pour les exigences professionnelles les plus strictes.
