# StockPilot — Documentation des fonctionnalités

> Récapitulatif complet des modules ajoutés (caisse, crédit client, factures, rentabilité, WhatsApp).
> Projet : StockPilot — SaaS multi-tenant de gestion de stock (ISET Sousse 2025/2026).
> Stack : Laravel 11 (backend) + Vue 3 / Vite (frontend) — SQLite en local, MySQL en prod (Railway).

---

## Sommaire
1. [Caisse (POS)](#1-caisse-pos)
2. [Ventes & historique](#2-ventes--historique)
3. [Clients & crédit (ardoise)](#3-clients--crédit-ardoise)
4. [Factures PDF légales](#4-factures-pdf-légales)
5. [Rentabilité / marge](#5-rentabilité--marge)
6. [Alertes WhatsApp](#6-alertes-whatsapp)
7. [Super-admin unique](#7-super-admin-unique)
8. [Infos de facturation de l'organisation](#8-infos-de-facturation-de-lorganisation)
9. [Déploiement (Railway)](#9-déploiement-railway)
10. [Tests](#10-tests)

---

## 1. Caisse (POS)

Interface d'encaissement accessible via le menu **Caisse** (espace société uniquement, jamais en super-admin).

**Fonctions :**
- Recherche produit + **grille cliquable** ; ajout au panier.
- **Code-barres / scanner** : champ dédié, saisie + `Entrée` → ajoute le produit (référence exacte).
- **Panier multi-produits** : +/−, suppression, total.
- **Remise** : en pourcentage ou en montant (plafonnée au total).
- **Modes de paiement** : Espèces (calcul du rendu), Carte, **Crédit / plus tard** (→ rattaché à un client).
- **Reçu imprimable** après encaissement (fenêtre d'impression dédiée).
- Le **stock est décrémenté automatiquement** (mouvement `sortie`) à chaque vente.

**Fichiers clés :**
- Frontend : `frontend/src/views/CaisseView.vue`
- Backend : `app/Services/SaleService.php`, `app/Http/Controllers/API/SaleController.php`
- Tables : `sales`, `sale_items`

**API :** `POST /api/sales`

---

## 2. Ventes & historique

Menu **Ventes** : liste paginée des tickets.

**Fonctions :**
- Filtres par date (boutons « Aujourd'hui » / « Tout »).
- Cartes récap : nombre de ventes, nombre d'annulées, **chiffre d'affaires TTC** (hors annulées).
- Colonnes : ticket, date, client, articles, mode de paiement, statut de paiement, total.
- **Annulation d'une vente** → remet le stock (mouvement `entree`) et passe la vente en `annulee`.
- **Export CSV** (rapport de caisse, compatible Excel avec BOM UTF-8).
- **Facture PDF** par ticket (voir §4).
- **Détail du ticket** avec ré-impression du reçu.

**API :**
- `GET /api/sales` (liste + résumé)
- `GET /api/sales/{id}` (détail)
- `POST /api/sales/{id}/cancel` (annulation)
- `GET /api/sales/export` (CSV)
- `GET /api/sales/{id}/invoice` (PDF)

**Fichiers :** `frontend/src/views/VentesView.vue`

---

## 3. Clients & crédit (ardoise)

Menu **Clients** : gestion des comptes clients et du **crédit (paiement plus tard)**.

**Concept :**
- Une vente en mode **crédit** est rattachée à un client (existant ou créé à la volée), avec un **acompte optionnel**.
- Le **solde dû** d'un client est calculé automatiquement (somme des restes à payer, hors ventes annulées).
- Si le client reprend des produits sans payer, le montant **s'ajoute à son ancien solde**.

**Fonctions de la page Clients :**
- Recherche par **nom ou téléphone**, filtre « débiteurs uniquement ».
- Détail client : solde dû, liste des tickets (avec reste / statut), historique des paiements.
- **Encaisser un paiement** (espèces / carte, total ou partiel) → imputé aux **plus anciennes ventes en premier (FIFO)**. On ne peut jamais encaisser plus que le solde dû.
- **Relancer (WhatsApp)** un client débiteur (voir §6).

**Statuts de paiement d'une vente :** `paye`, `partiel`, `impaye`, `annulee`.

**API :**
- `GET /api/clients` (liste + solde), `POST /api/clients`, `GET /api/clients/{id}`, `PATCH /api/clients/{id}`
- `POST /api/clients/{id}/pay` (encaissement)
- `POST /api/clients/{id}/remind` (relance WhatsApp)

**Fichiers :** `frontend/src/views/ClientsView.vue`, `app/Services/ClientService.php`, `app/Http/Controllers/API/ClientController.php`
**Tables :** `clients`, `client_payments`, colonnes `sales.client_id`, `sales.montant_regle`

---

## 4. Factures PDF légales

Génération d'une **facture conforme** (PDF) depuis n'importe quelle vente. Lib : `barryvdh/laravel-dompdf`.

**Contenu :**
- En-tête émetteur : raison sociale, **matricule fiscal**, adresse, téléphone, email.
- Bloc client.
- Tableau des lignes (désignation, quantité, PU HT, TVA, montant HT).
- Totaux : Total HT, **TVA regroupée par taux**, remise, Total TTC.
- **Numéro de facture séquentiel** `FAC-AAAA-NNNN` (attribué à la 1re génération, puis stable).
- **Montant en toutes lettres** (dinars / millimes, règles françaises).
- Bloc crédit (reste à payer) si applicable.

**API :** `GET /api/sales/{id}/invoice`
**Fichiers :** `app/Services/InvoiceService.php`, `resources/views/invoices/facture.blade.php`
**Colonne :** `sales.numero_facture`

---

## 5. Rentabilité / marge

Bloc **« Rentabilité — ce mois »** sur le tableau de bord.

**Indicateurs :**
- **Bénéfice (marge brute)** = ventes HT − coût d'achat.
- **Taux de marge %**.
- **Top 5 des produits les plus rentables** (marge générée).

**Point important :** le **coût d'achat est figé** dans chaque ligne de vente au moment de l'encaissement (`sale_items.prix_achat_unitaire`). Ainsi, modifier le prix d'achat d'un produit plus tard **ne fausse pas** la marge des ventes passées.

**Fichiers :** `app/Http/Controllers/API/DashboardController.php`, `frontend/src/views/DashboardView.vue`

> À noter : le CA caisse du dashboard compte le total des ventes (crédit inclus), pas seulement l'encaissé.

---

## 6. Alertes WhatsApp

Service à **2 modes**, sans coût obligatoire :
- **`log`** (par défaut) : n'envoie rien réellement (écrit dans les logs) — idéal démo.
- **`twilio`** : envoi réel automatique si les clés Twilio sont configurées.

Dans tous les cas, l'API renvoie un **lien `wa.me`** : le frontend ouvre WhatsApp (Web/mobile) avec le **message pré-rempli** → fonctionne immédiatement et gratuitement.

**Deux usages :**
1. **Relance client** débiteur : `POST /api/clients/{id}/remind` (message avec le solde dû). Bouton « 📲 Relancer (WhatsApp) » dans la page Clients.
2. **Alerte stock bas** : `POST /api/alerts/notify` (liste des produits sous le seuil, envoyée au téléphone de la boutique). Bouton « 📲 Alerter par WhatsApp » dans Alertes & IA.

**Normalisation des numéros :** local tunisien (8 chiffres) → préfixe `216` (ex. `29 123 456` → `21629123456`).

**Configuration (`.env`, optionnel) :**
```
WHATSAPP_DRIVER=twilio
TWILIO_SID=...
TWILIO_TOKEN=...
TWILIO_WHATSAPP_FROM=+14155238886
```

**Fichiers :** `app/Services/WhatsAppService.php`, `config/whatsapp.php`

---

## 7. Super-admin unique

Un **seul compte `super_admin`** est autorisé sur toute la plateforme :
- Garde au niveau du modèle `User` (rejette la création d'un 2e super-admin).
- Seed mis à jour (vérifie le rôle, pas seulement l'email).

L'espace super-admin n'affiche **que** « Plateforme » (pas de catalogue / caisse / ventes) — verrouillé côté sidebar **et** routeur.

**Fichiers :** `app/Models/User.php`, `app/Console/Commands/SeedAdminCommand.php`

---

## 8. Infos de facturation de l'organisation

Écran **« Informations de facturation »** en haut de la page **Configuration** (modifiable **uniquement par un admin**) :
- Raison sociale, **matricule fiscal**, téléphone, adresse.
- Ces infos apparaissent sur les factures PDF.

**API :** `GET /api/organisation`, `PATCH /api/organisation`
**Fichiers :** `app/Http/Controllers/API/OrganisationController.php`, `frontend/src/views/ConfigView.vue`

---

## 9. Déploiement (Railway)

**Important — le frontend est pré-compilé** dans `backend/public` et servi par Laravel.
➡️ **Toute modification de `frontend/src` nécessite `npm run build` AVANT le commit**, sinon la prod garde l'ancien bundle.

**Procédure de déploiement :**
```bash
# 1. Rebuild du frontend (depuis frontend/)
npm run build            # génère backend/public/index.html + assets/

# 2. Commit + push (Railway redéploie automatiquement)
git add -A && git commit -m "..." && git push origin main

# 3. Sur Railway : vérifier que les migrations tournent
php artisan migrate --force

# 4. Dans le navigateur : Ctrl + F5 (vider le cache du bundle)
```

**Variables d'env utiles en prod :**
- **Email** (si besoin un jour) : `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`.
- **WhatsApp auto** (optionnel) : voir §6.

**Note `composer install` :** DomPDF est installé ; si Railway bloque sur `ext-oci8` / `ext-sodium`, ajouter `--ignore-platform-req=ext-oci8 --ignore-platform-req=ext-sodium`.

---

## 10. Tests

Suite Pest (backend), **76 tests** :
- `SaleCreditTest` — ventes à crédit, solde cumulé, paiement FIFO, plafonnement.
- `InvoiceTest` — génération PDF, numérotation séquentielle, droits d'accès.
- `MarginTest` — marge brute, taux, coût figé.
- `WhatsAppTest` — normalisation des numéros, relance client, alerte stock.
- + suites existantes (auth, produits, mouvements, multi-tenant…).

```bash
# Depuis backend/
./vendor/bin/pest                 # toute la suite
./vendor/bin/pest --compact
```

---

## Historique des modules (ordre de construction)
1. Module Caisse (POS) + crédit client + factures + vérification email
2. Historique des ventes
3. Annulation, remise, CA caisse au dashboard, export CSV, code-barres
4. Compte client / crédit (ardoise) + paiements FIFO
5. Facture PDF légale (matricule fiscal, TVA, montant en lettres)
6. Rentabilité / marge (coût figé à la vente)
7. Alertes WhatsApp (relance client + stock bas)
8. ~~Vérification d'email à l'inscription~~ — **retirée** à la demande (connexion directe)

> _La vérification d'email avait été implémentée puis retirée : la connexion est désormais directe, sans code de confirmation._
