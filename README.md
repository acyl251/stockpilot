# StockPilot — Gestion de Stock SaaS Multi-Tenant

> Projet académique · ISET Sousse · 2025/2026  
> **Acyl Dhifallah** · **Med Alaa Edine Boufares**

StockPilot est une application web SaaS de gestion de stock permettant à plusieurs entreprises de gérer leurs stocks de manière indépendante et sécurisée sur une infrastructure partagée. Chaque organisation dispose d'un espace totalement isolé grâce à une architecture multi-tenant.

---

## Stack technique

| Couche | Technologie |
|--------|-------------|
| Backend | Laravel 11 · PHP 8.2 |
| Authentification | tymon/jwt-auth (JWT HS256) |
| Frontend | Vue 3 · TypeScript · Vite |
| State management | Pinia |
| CSS | Tailwind CSS |
| Client HTTP | Axios |
| Base de données | SQLite (dev) · Oracle (prod) |
| IA | OpenAI GPT-4o-mini |
| Tests | Pest PHP |

---

## Démarrage rapide

```bash
# Backend (depuis backend/)
php artisan migrate:fresh --seed
php -d max_execution_time=0 artisan serve --port=8000

# Frontend (depuis frontend/)
npm install
npm run dev   # → http://localhost:5173
```

Ou lancer via le script à la racine :
```bat
lancer-stockpilot.bat
```

**Credentials de test :** `admin@test.tn` / `Password123!`  
**Base de données :** `C:\dev\stockpilot.sqlite`

---

## Architecture multi-tenant

L'isolation entre organisations est garantie par deux couches complémentaires :

```
Requête HTTP
    ↓
[1] Middleware AuthenticateTenant
    → Décode le JWT
    → Injecte current_organisation_id dans le container Laravel
    ↓
[2] TenantScope (Global Eloquent Scope)
    → Ajoute automatiquement WHERE organisation_id = ? sur toutes les requêtes
    → Si organisation_id est null → WHERE 1=0 (zéro résultat)
    ↓
Base de données
```

Côté frontend, la garde Vue Router empêche le Super Admin d'accéder aux routes tenant et vice-versa.

---

## Rôles et permissions

| Rôle | Accès |
|------|-------|
| `super_admin` | Plateforme complète — pas de données métier |
| `admin` | Organisation complète (utilisateurs, catalogue, config) |
| `gestionnaire` | Catalogue + mouvements de stock |
| `operateur` | Saisie des entrées/sorties uniquement |

Le Super Admin n'appartient à aucune organisation (`organisation_id = null`).

---

## Structure du projet

```
├── backend/
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/API/    # Contrôleurs REST
│   │   │   └── Middleware/         # AuthenticateTenant, super.admin
│   │   ├── Models/
│   │   │   ├── Scopes/TenantScope.php
│   │   │   ├── BaseModel.php       # Enregistre TenantScope + auto-inject org_id
│   │   │   ├── Organisation.php
│   │   │   ├── User.php
│   │   │   ├── Product.php
│   │   │   ├── Categorie.php
│   │   │   ├── TypeProduit.php
│   │   │   ├── StockMovement.php
│   │   │   └── DemoRequest.php
│   │   └── Services/
│   │       ├── StockService.php    # Mouvements atomiques + validation stock
│   │       └── AIService.php       # Wrapper OpenAI avec cache fichier
│   ├── database/migrations/
│   ├── config/ai.php               # Prompts GPT + TTL cache
│   ├── routes/api.php
│   └── tests/
│
├── frontend/
│   └── src/
│       ├── views/
│       │   ├── LandingView.vue     # Page d'accueil publique (parallax)
│       │   ├── LoginView.vue
│       │   ├── DashboardView.vue
│       │   ├── ProductsView.vue
│       │   ├── MovementsView.vue
│       │   ├── AlertsView.vue
│       │   ├── ConfigView.vue
│       │   ├── UsersView.vue
│       │   ├── SuperAdminView.vue
│       │   └── OnboardingView.vue
│       ├── components/
│       │   └── AppSidebar.vue
│       ├── stores/
│       │   └── auth.ts             # Pinia — initPromise, isAuthenticated, isSuperAdmin
│       ├── services/api.ts         # Instance Axios + tous les appels API
│       └── router/index.ts         # Guards d'authentification et de rôle
│
└── lancer-stockpilot.bat
```

---

## Schéma de base de données

| Table | Description |
|-------|-------------|
| `plans` | Plans d'abonnement (Starter, Pro, Enterprise) |
| `organisations` | Tenants — une ligne par entreprise cliente |
| `users` | Utilisateurs (organisation_id nullable pour super_admin) |
| `categories` | Catégories de produits colorées par organisation |
| `types_produits` | Types de produits avec attributs JSON personnalisés |
| `products` | Catalogue produits avec prix TTC calculés en PHP |
| `stock_movements` | Historique complet des entrées et sorties |
| `demo_requests` | Demandes d'accès soumises depuis la landing page |

---

## Fonctionnalités IA (GPT-4o-mini)

Accessibles uniquement si `plan.ia_activee = true`. Résultats mis en cache fichier Laravel.

| Fonctionnalité | TTL cache | Endpoint |
|----------------|-----------|----------|
| Suggestions onboarding par secteur | 6h | `POST /api/onboarding/suggest` |
| Suggestions de réapprovisionnement | 6h | `GET /api/alerts/suggestions` |
| Détection d'anomalies (règle 3σ) | 1h | `GET /api/alerts/anomalies` |
| Prévision de la demande sur 30j | 24h | `GET /api/dashboard/forecast/{id}` |
| KPIs prédictifs du tableau de bord | 6h | `GET /api/dashboard` |

Si OpenAI est indisponible, toutes les méthodes échouent silencieusement et retournent un tableau vide.

---

## Routes API principales

```
# Public
POST   /api/auth/login
POST   /api/demo-request
GET    /api/plans

# Authentifié (tenant)
GET    /api/auth/me
POST   /api/auth/refresh
GET    /api/dashboard
GET    /api/products
POST   /api/products
PATCH  /api/products/{id}
GET    /api/movements
POST   /api/movements
GET    /api/categories
POST   /api/categories
GET    /api/product-types
GET    /api/alerts/stock
GET    /api/alerts/suggestions
GET    /api/alerts/anomalies
GET    /api/users
POST   /api/users
PATCH  /api/users/{id}
DELETE /api/users/{id}         # toggle actif/inactif

# Super Admin uniquement
GET    /api/super-admin/dashboard
GET    /api/super-admin/organisations
POST   /api/super-admin/organisations
GET    /api/super-admin/users
GET    /api/super-admin/plans
GET    /api/super-admin/demo-requests
PATCH  /api/super-admin/demo-requests/{id}
```

---

## Parcours visiteur → abonné

```
Landing page
    ↓ clic "Commencer"
Formulaire de demande d'accès
    ↓ soumission
Super Admin — onglet "Demandes"
    ↓ clic "Créer société" (formulaire pré-rempli)
Organisation créée + compte admin
    ↓
Entreprise se connecte → Dashboard opérationnel
```

---

## Compatibilité SQLite / Oracle

| Différence | SQLite (dev) | Oracle (prod) |
|------------|-------------|---------------|
| Troncature date | `DATE(col)` | `TRUNC(col)` |
| Requête santé | `SELECT 1` | `SELECT 1 FROM DUAL` |
| Colonnes virtuelles | `$appends` PHP | Colonnes `GENERATED` |
| Mise à jour stock | PHP (StockService) | Trigger `trg_update_stock` + PHP |

---

## Tests

```bash
# Tous les tests
cd backend && ./vendor/bin/pest

# Suite spécifique
./vendor/bin/pest tests/Feature/AuthApiTest.php

# Test unique
./vendor/bin/pest --filter "can login"
```

---

## Commandes utiles

```bash
# Réinitialiser la base de données avec données de démo
php artisan migrate:fresh --seed

# Lister toutes les routes API
php artisan route:list

# Vider le cache (résultats IA)
php artisan cache:clear

# Build production frontend
cd frontend && npm run build
```

---

## Déploiement (alwaysdata)

L'hébergement cible est **alwaysdata** : Apache + PHP natif + MySQL (pas de conteneur, pas de Procfile). Laravel sert l'API **et** le SPA Vue compilé depuis `backend/public`.

### 1. Base de données
Dans le panel alwaysdata → **Bases de données → MySQL**, créer une base (ex. `compte_stockpilot`). Noter l'hôte (`mysql-compte.alwaysdata.net`), le nom, l'utilisateur et le mot de passe.

### 2. Code source
Envoyer le projet sur le serveur (Git ou SFTP), par exemple dans `~/www/stockpilot/`.

### 3. Backend
```bash
cd ~/www/stockpilot/backend
composer install --no-dev --optimize-autoloader

# Configuration
cp .env.production.example .env      # puis renseigner DB_*, APP_URL, OPENAI_API_KEY…
php artisan key:generate             # APP_KEY
php artisan jwt:secret               # JWT_SECRET

# Base de données
php artisan migrate --force
php artisan db:seed --force          # crée le super-admin (une seule fois)
```

### 4. Frontend (SPA compilé dans `backend/public`)
```bash
cd ~/www/stockpilot/frontend
npm ci
npm run build        # Vite émet index.html + assets/ dans ../backend/public
```
> Alternative : compiler en local et envoyer le contenu de `backend/public` par SFTP.

### 5. Configuration du site alwaysdata
Dans le panel → **Sites → ajouter/modifier un site** :
- **Type** : PHP
- **Version PHP** : 8.2 (ou +)
- **Document root / chemin** : `~/www/stockpilot/backend/public`

Le fichier [`backend/public/.htaccess`](backend/public/.htaccess) (fourni) gère la réécriture : les fichiers réels (assets, favicon) sont servis tels quels, tout le reste passe par `index.php`. Le **fallback catch-all** côté Laravel ([`routes/web.php`](backend/routes/web.php)) renvoie le SPA pour les routes Vue (`/login`, `/app/...`), y compris au rechargement.

### Notes
- Le code est compatible **MySQL / SQLite / Oracle** : la sélection se fait via `DB_CONNECTION` ([`config/database.php`](backend/config/database.php)). Les troncatures de date utilisent `DATE()` (MySQL/SQLite) ou `TRUNC()` (Oracle) automatiquement.
- Sans clé `OPENAI_API_KEY` valide, l'IA bascule proprement sur les catalogues de secours hors-ligne par secteur.
- Après modification du `.env` en prod : `php artisan config:clear` (et `cache:clear` pour réinitialiser les résultats IA mis en cache).

---

## Perspectives (évolutions futures)

StockPilot gère aujourd'hui l'inventaire (matières premières et produits finis) ainsi que les variantes de produits via le système de **types + attributs dynamiques**. Pour couvrir pleinement les secteurs de **transformation/fabrication** (menuiserie, ébénisterie, métallerie, agroalimentaire…), trois évolutions sont envisagées :

1. **Nomenclature (BOM — Bill of Materials)**
   Définir la composition d'un produit fini à partir de ses matières premières.
   *Exemple : 1 porte = 2,5 m² de bois + 3 charnières + 1 serrure + 0,5 L de vernis.*
   Une table `nomenclatures` relierait chaque produit fini à la liste de ses composants et leurs quantités.

2. **Ordres de fabrication**
   Un nouveau type de mouvement `production` qui, en une seule transaction atomique :
   vérifie la disponibilité des matières, les décrémente du stock, puis incrémente le stock du produit fini.
   Fini la double saisie manuelle (sortie matières + entrée produit fini) non reliée.

3. **Coût de revient automatique**
   Calcul du prix d'achat réel d'un produit fini à partir du coût des matières consommées selon sa nomenclature,
   au lieu d'une saisie manuelle. Base d'un calcul de marge fiable sur les produits fabriqués.

> Ces fonctionnalités transformeraient StockPilot d'un outil de **gestion de stock** en une solution de **gestion de production** légère pour PME.
