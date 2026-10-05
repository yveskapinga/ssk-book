# Invariants offline — app mobile SSK Book

## Auth

- Login / inscription : **online only**.
- Après succès : jeton Bearer + snapshot utilisateur en SecureStore.
- Le jeton serveur est **durable** (fin via logout, révocation, ou compte suspendu — pas d’expiration courte).
- Cold start offline : restaurer la session depuis SecureStore **sans** appeler `/me`.
- Clear session uniquement sur 401/403 (révoqué / suspendu), jamais sur erreur réseau.

## Toujours offline (après bootstrap)

- Lecture du livre déjà téléchargé (TOC + **tous les passages** via `/api/books/{slug}/reading/pack`).
- Navigation Précédent / Suivant / Sommaire sur le pack local (frontière de déverrouillage respectée).
- Progression de lecture (écrit local + file `pending`, flush au retour online).
- Favoris (POST mis en file hors ligne).
- Accueil / bibliothèque / bookmarks / notes si cache présent.
- Historique local des notifications reçues.

## Online only (gate claire)

- Questions au livre (Gemini).
- Quiz (démarrage / soumission).
- Enregistrement du token push Expo.
- Flush de la file `pending`.
- Affichage des figures IMAGE (URL authentifiée).

## Bootstrap 1er online

Après login online : télécharger et persister `GET /api/library`, **pack lecture complet** (`/reading/pack`), dashboard, bookmarks, notes. Ne jamais bloquer l’UI indéfiniment (timeouts).

## Hors scope mobile v1

- Console admin.
- Offline pour Gemini / quiz.
