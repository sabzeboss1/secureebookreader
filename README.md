# Secure Ebook Reader (WordPress & WooCommerce Plugin)

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%207.4-8892BF.svg)](https://php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D%205.8-21759B.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-%3E%3D%205.0-96588A.svg)](https://woocommerce.com/)
[![Tests](https://img.shields.io/badge/Tests-13%2F13%20Passed-success.svg)](./secure-ebook-reader/tests/)

> **Solution professionnelle de vente et lecture sécurisée d'ebooks PDF en ligne sans téléchargement.**

---

## 🌟 Fonctionnalités Majeures

- 🛡️ **Sécurité Zero-Trust & Anti-IDOR** : Fichiers PDF originaux stockés dans un coffre-fort hermétique (`wp-content/uploads/secure-ebooks-vault/`) avec protection `.htaccess` (`Require all denied`) et jetons éphémères SHA-256.
- ⚡ **Streaming RFC 7233 (HTTP Range Requests)** : Streaming partiel haute performance par morceaux de 64 Ko (`206 Partial Content`) pour un chargement instantané sans saturation de mémoire RAM.
- 💧 **Filigrane Dynamique Dual-Layer** : Double protection combinant une surimpression DOM et une incrustation directe dans les pixels du canvas PDF.js (l'acheteur ne peut pas masquer le filigrane en inspectant le code HTML).
- 📖 **Lecteur PDF.js Sur-Mesure** : Moteur embarqué (zéro dépendance CDN externe), thèmes Sombre/Clair/Sépia, vignettes, gestes tactiles mobiles, blocage du clic droit et de l'impression (`Ctrl+P`, `Ctrl+S`, `F12`).
- 🛒 **Intégration WooCommerce Complète** : Attribution automatique sur commande validée, révocation immédiate en cas de remboursement ou d'annulation.
- 📚 **Espace Mon Compte Client** : Onglet natif "Mes Livres Numériques" avec barres de progression et reprise automatique à la dernière page lue.
- 📊 **Tableau de Bord & Audit de Sécurité** : Suivi des lectures, alertes anti-IDOR et diagnostic serveur.

---

## 📁 Structure du Dépôt

- `secure-ebook-reader/` : Code source complet de l'extension WordPress.
- `secure-ebook-reader.zip` : Archive prête à être téléversée dans WordPress (**Extensions > Ajouter > Téléverser**).
- `build-zip.php` : Script de compilation de l'archive de production.
- `secure-ebook-reader-architecture.md` : Document d'architecture technique et spécifications de sécurité.

---

## 🧪 Tests Automatisés

Pour exécuter la suite de tests de sécurité et de conformité :
```bash
php secure-ebook-reader/tests/run-tests.php
```

---

## 📦 Compilation du Package

Pour régénérer l'archive ZIP installable :
```bash
php build-zip.php
```

---
© 2026 Antigravity. Conçu pour les exigences professionnelles les plus strictes.
