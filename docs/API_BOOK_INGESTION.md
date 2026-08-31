# API d'ingestion du livre

Tous les endpoints nécessitent `ROLE_ADMIN`.

## Importer une version

`POST /api/admin/books/imports` en `multipart/form-data` :

- `file` : PDF obligatoire, 50 Mo maximum ;
- `title` : titre du livre ;
- `label` : libellé de la version ;
- `description` : facultative.

La réponse `202` contient `bookId`, `versionId`, `versionNumber` et `jobId`. Une source identique ne peut pas être importée deux fois pour un même livre.

## Démarrer l'extraction

`POST /api/admin/books/ingestion-jobs/{jobId}/run`

Le service utilise `pdftotext`, conserve la correspondance avec les pages du PDF, normalise le texte, produit les passages puis place la version en `REVIEW_REQUIRED`.

## Consulter le traitement

`GET /api/admin/books/ingestion-jobs/{jobId}`

La réponse expose le statut, l'étape, la progression, les erreurs et les métriques `pages` et `chunks`.

## Limite du MVP

L'exécution est synchrone. La table `ingestion_jobs` et la séparation entre création et exécution permettent de déplacer ultérieurement le traitement vers Symfony Messenger sans modifier le contrat métier ni le schéma des contenus.
