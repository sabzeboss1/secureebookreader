# Secure Ebook Reader — Architecture technique

> Plugin WordPress + WooCommerce pour la vente et la lecture protégée d'ebooks en ligne (sans téléchargement).
> Ce document répond à la Phase 0 demandée dans le prompt initial : **analyser avant de coder**.

---

## 1. Principes directeurs

| Principe | Détail |
|---|---|
| Source de vérité paiement | WooCommerce (produit, panier, checkout, commande, compte) |
| Source de vérité droits d'accès | Secure Ebook Reader (SER) |
| Fichier original | Jamais exposé publiquement, jamais servi sans vérification |
| Autorisation | Une seule méthode centrale `Secure_Ebook_Access::can_read($user_id, $ebook_id)` |
| Sécurité | Toujours côté serveur — le masquage UI (boutons cachés) n'est jamais une protection |
| Priorité | Sécurité > Contrôle d'accès > Fiabilité > Performance > Design |

---

## 2. Architecture du flux de lecture

```
Utilisateur connecté
   → GET /mon-compte/lire/ebook/{id}/
   → SER vérifie : connecté ? ebook existe ? actif ? accès valide ? non expiré ?
   → SER génère un token de lecture temporaire (lié user+ebook, courte durée, opaque)
   → Page charge PDF.js, PDF.js appelle l'endpoint REST avec le token
   → REST /wp-json/secure-ebook/v1/read/{ebook_id}?token=...
   → SER revalide le token + les droits à CHAQUE requête (y compris requêtes de pages/ranges)
   → Si OK : stream du PDF protégé (Range Requests supportées)
   → Si KO : 401 / 403, jamais le fichier
```

Le fichier ne doit **jamais** être atteignable par une URL statique (`/wp-content/uploads/...`). Stockage recommandé :

```
wp-content/secure-ebooks/{ebook_id}/document.pdf
```

Protection en profondeur (défense multicouche, aucune n'est suffisante seule) :
1. Dossier hors de tout point de montage public exécutable, ou protégé par règle serveur.
2. `.htaccess` (Apache) refusant tout accès direct — *documenté*, pas une garantie sur tous les hébergeurs.
3. Règles Nginx équivalentes — *documentées séparément*.
4. Vérification PHP systématique avant tout `readfile`/stream, indépendamment du serveur.
5. Idéalement : stockage hors `wp-content` (ex. dossier privé hors webroot) si l'hébergement le permet — à documenter comme option avancée.

---

## 3. Schéma de base de données

### `wp_secure_ebooks`
| Colonne | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| product_id | BIGINT | FK WooCommerce product |
| title | VARCHAR | |
| author | VARCHAR | nullable |
| file_path | VARCHAR | chemin relatif protégé |
| file_hash | VARCHAR | intégrité / anti-remplacement silencieux |
| page_count | INT | nullable, calculé à l'import |
| access_mode | ENUM | `online_only` (V1 unique valeur) |
| access_duration | ENUM | `permanent`, `30d`, `90d`, `1y` |
| protections | JSON | bloquer_download, bloquer_print, url_protection, require_purchase |
| status | ENUM | `active`, `disabled` |
| created_at / updated_at | DATETIME | |

### `wp_secure_ebook_access`
| Colonne | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| user_id | BIGINT | FK wp_users |
| ebook_id | BIGINT | FK wp_secure_ebooks |
| order_id | BIGINT | nullable si `source = manual` |
| source | ENUM | `woocommerce`, `manual` |
| granted_at | DATETIME | |
| expires_at | DATETIME | nullable si permanent |
| status | ENUM | `granted`, `revoked`, `expired` |

Index unique conseillé : `(user_id, ebook_id)` avec gestion de ré-attribution si revente.

### `wp_secure_ebook_reading`
| Colonne | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| user_id | BIGINT | |
| ebook_id | BIGINT | |
| last_page | INT | |
| progress | DECIMAL(5,2) | pourcentage |
| last_read_at | DATETIME | |

Écritures avec **debounce côté client** (ex. toutes les 5-10 pages ou toutes les 15-30s), jamais à chaque `pagechange`.

### `wp_secure_ebook_tokens` (recommandé, non listé dans le prompt mais nécessaire)
| Colonne | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| token_hash | VARCHAR | jamais stocké en clair |
| user_id | BIGINT | |
| ebook_id | BIGINT | |
| expires_at | DATETIME | courte durée (ex. 5–15 min, renouvelable) |
| revoked | BOOLEAN | |

Alternative sans table dédiée : JWT signé côté serveur contenant `user_id`, `ebook_id`, `exp`, `nonce` — mais une table permet la **révocation immédiate**, ce que le prompt exige (remboursement → accès coupé tout de suite). Recommandation : table dédiée pour la V1.

---

## 4. Hooks WooCommerce nécessaires

| Hook | Usage |
|---|---|
| `woocommerce_order_status_completed` / `woocommerce_order_status_processing` | Attribution d'accès (selon réglage) |
| `woocommerce_order_status_refunded` | Révocation d'accès |
| `woocommerce_order_status_cancelled` | Révocation / non-attribution |
| `woocommerce_order_status_failed` | Aucune attribution |
| `woocommerce_process_product_meta` | Sauvegarde des métadonnées "ce produit est un ebook" |
| `woocommerce_product_data_tabs` / `woocommerce_product_data_panels` | Onglet "Secure Ebook Reader" dans l'édition produit |
| `woocommerce_account_menu_items` | Ajout "Mes ebooks" au menu Mon compte |
| `woocommerce_account_{endpoint}_endpoint` | Rendu de la page Mes ebooks |
| `init` | `add_rewrite_endpoint()` pour l'endpoint mon-compte |
| `woocommerce_init` ou `plugins_loaded` (vérif WooCommerce actif) | Garde-fou dépendance |

---

## 5. Endpoints REST

Namespace : `secure-ebook/v1`

| Route | Méthode | Rôle | Vérifications |
|---|---|---|---|
| `/read/{ebook_id}/token` | POST | Génère un token de lecture | connecté, `can_read()`, nonce |
| `/read/{ebook_id}` | GET | Sert le contenu (stream, Range) | token valide, non expiré, non révoqué, `can_read()` re-vérifié |
| `/progress/{ebook_id}` | POST | Sauvegarde progression | connecté, propriétaire du token, throttling serveur en plus du debounce client |
| `/library` | GET | Liste des ebooks possédés (optionnel si rendu PHP direct) | connecté |

Toutes les routes : `permission_callback` minimum `is_user_logged_in()`, puis vérification métier explicite dans le handler (ne jamais se fier uniquement au `permission_callback` générique — c'est la protection anti-IDOR).

---

## 6. Protection anti-IDOR (point critique du prompt)

- `ebook_id` **jamais** suffisant seul pour accéder à un fichier.
- Chaque requête au fichier revalide `can_read($user_id, $ebook_id)`, pas seulement à la génération du token.
- Le token est lié `user_id + ebook_id` ; changer l'`ebook_id` dans l'URL sans un token valide pour *cet* ebook → 403.
- Ne jamais faire confiance à un `order_id` ou `ebook_id` transmis par le client sans le recroiser en base.

---

## 7. Architecture PDF.js

- PDF.js embarqué dans le plugin (`/pdfjs/`), pas chargé depuis un CDN externe (cohérence avec la politique de sécurité et disponibilité).
- Le viewer PDF.js pointe vers l'endpoint REST `/read/{ebook_id}` (pas vers un fichier statique).
- Désactivation dans l'UI du viewer :
  - bouton téléchargement
  - bouton impression
  - lien "ouvrir dans un nouvel onglet"
- Streaming progressif via HTTP Range Requests supportées côté endpoint PHP (`Accept-Ranges`, `Content-Range`) pour éviter de charger tout le PDF en mémoire.
- Headers de réponse : `Cache-Control: private, no-store`, `X-Content-Type-Options: nosniff` — à tester sur Chrome/Firefox/Edge/Safari pour ne pas casser le rendu progressif.
- Overlay watermark (nom + email) rendu côté client par-dessus le canvas PDF.js ; prévoir un point d'extension pour un watermark serveur (fusionné dans le flux PDF) en V2.

---

## 8. Risques de sécurité identifiés et mitigation

| Risque | Mitigation |
|---|---|
| Accès direct au fichier stocké | Dossier protégé + vérification PHP systématique, jamais de dépendance unique au `.htaccess` |
| IDOR sur `ebook_id` | `can_read()` centralisé, revalidé à chaque requête fichier |
| Partage d'URL/token entre utilisateurs | Token lié au `user_id`, courte durée, vérifié en base à chaque appel |
| Accès après remboursement | Hook `order_status_refunded` → révocation immédiate, token existants invalidés |
| Injection SQL | `$wpdb->prepare()` systématique |
| XSS | `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` sur toute sortie |
| CSRF sur actions admin/AJAX | Nonces WordPress sur toutes les actions d'écriture |
| Upload de fichier malveillant | Vérification extension + MIME réel, renommage aléatoire, dossier non exécutable |
| Fuite par cache navigateur/proxy | `Cache-Control: private, no-store` sur les réponses fichier |
| Répudiation de l'accès accordé manuellement | Champ `source = manual` distinct de `source = woocommerce` |

Rappel à afficher dans le README : le système empêche le téléchargement et le partage d'URL, mais **ne prétend pas empêcher la capture d'écran ou la photo de l'écran**.

---

## 9. Arborescence du plugin

```
secure-ebook-reader/
├── secure-ebook-reader.php
├── uninstall.php
├── readme.txt
├── README.md
├── includes/
│   ├── class-plugin.php
│   ├── class-activator.php
│   ├── class-deactivator.php
│   ├── class-ebook.php
│   ├── class-ebook-access.php        # can_read() centralisé ici
│   ├── class-ebook-security.php      # tokens, headers, protection fichiers
│   ├── class-ebook-reader.php
│   ├── class-ebook-library.php
│   ├── class-ebook-api.php           # routes REST
│   ├── class-ebook-admin.php
│   ├── class-ebook-woocommerce.php   # hooks commande/produit
│   └── class-ebook-progress.php
├── admin/{css,js}/
├── public/{css,js}/
├── templates/
│   ├── my-ebooks.php
│   ├── ebook-card.php
│   └── reader.php
├── pdfjs/
├── server-config/
│   ├── nginx-example.conf
│   └── htaccess-example.txt
└── languages/
```

---

## 10. Réglages admin (Ebooks > Réglages)

- **Général** : page bibliothèque, page lecteur, authentification obligatoire
- **Sécurité** : blocage accès direct, blocage téléchargement, blocage impression, token temporaire, vérification commande, vérification utilisateur
- **Watermark** : activer, contenu (nom/email), position, opacité
- **Lecture** : mémoriser progression, reprendre à la dernière page

---

## 11. Plan de développement en phases (repris et affiné)

1. **Architecture** — squelette plugin, activation, garde-fou WooCommerce, tables DB, logs
2. **Ebooks** — CRUD ebook, association produit, upload sécurisé, admin
3. **WooCommerce** — hooks commande, attribution, révocation, remboursement
4. **Mon compte** — endpoint, bibliothèque, cartes ebooks
5. **Reader** — page lecture, PDF.js, endpoint REST, token temporaire
6. **Sécurité** — tests IDOR, hotlink, accès direct, token, remboursement (voir §12)
7. **Progression** — dernière page, %, reprise
8. **Watermark** — identité utilisateur en overlay
9. **Administration** — dashboard, stats, gestion des accès
10. **Optimisation** — mobile/desktop, gros fichiers, charge, cache, mémoire

Chaque phase = livrable testable avant de passer à la suivante.

---

## 12. Plan de test

| # | Scénario | Résultat attendu |
|---|---|---|
| 1 | Utilisateur non connecté sur `/lire/ebook/10/` | Accès refusé |
| 2 | Connecté mais n'a pas acheté | 403 |
| 3 | Connecté et a acheté | Lecteur visible |
| 4 | URL directe du fichier PDF | Accès refusé |
| 5 | Token partagé d'un utilisateur A vers B | Accès refusé pour B |
| 6 | Commande remboursée | Accès révoqué immédiatement |
| 7 | Commande annulée | Accès refusé |
| 8 | Utilisateur a l'ebook A mais pas B | A accessible, B refusé |
| 9 | Accès expiré (durée limitée) | Accès refusé |
| 10 | Modification manuelle de `ebook_id` dans une requête | 403 (test IDOR explicite) |

---

## 13. Livrables attendus

- Plugin complet, ZIP installable
- `README.md` (vue d'ensemble)
- Doc installation, configuration, sécurité, développement
- Procédure de test (repris du §12)
- Exemples de config Nginx/Apache (`server-config/`)
- Tests automatisés sur `can_read()` et les routes REST au minimum

---

## 14. Questions ouvertes à trancher avant codage

- Génération du watermark : overlay client (V1, plus simple) vs fusion serveur dans le PDF (V2, plus robuste mais coûteux en CPU) ?
- Durée de vie du token de lecture et politique de renouvellement automatique pendant une session de lecture longue ?
- Politique par défaut : attribution d'accès sur `processing` ou seulement `completed` ?
- Faut-il logguer les tentatives d'accès refusées (pour audit / détection d'abus) dès la V1 ?
