# Publication, embeddings et réponses sourcées

## Cycle éditorial

Une version structurée demeure en `REVIEW_REQUIRED`. Un administrateur inspecte les passages, lance l'indexation, puis enregistre une décision `APPROVED` ou `REJECTED`. La publication est refusée tant que la dernière décision n'est pas `APPROVED` ou qu'un passage ne possède pas son embedding.

Lors de la publication, `books.current_version_id` désigne atomiquement la version courante. Les pages, nœuds et passages d'une version `PUBLISHED` sont protégés par des triggers PostgreSQL et deviennent immuables.

## Embeddings

- modèle configurable par `GEMINI_EMBEDDING_MODEL` ;
- dimension contractuelle : 768 ;
- traitement par lots de 20 passages ;
- reprise possible : seuls les passages sans embedding sont envoyés ;
- modèle et date conservés avec chaque vecteur ;
- clé Gemini transmise dans l'en-tête serveur `x-goog-api-key`, jamais au frontend.

## Recherche hybride

La question est transformée en embedding `RETRIEVAL_QUERY`. PostgreSQL combine :

- similarité cosinus `pgvector` ;
- recherche plein texte française ;
- fusion des rangs des deux résultats.

Gemini reçoit uniquement les passages retenus. Les identifiants de sources retournés sont filtrés contre cette liste. Une réponse non déclarée comme insuffisante est rejetée si elle ne cite aucun passage retrouvé.

## Endpoints

- `GET /api/admin/book-versions` ;
- `GET /api/admin/book-versions/{id}/chunks` ;
- `POST /api/admin/book-versions/{id}/embeddings` ;
- `POST /api/admin/book-versions/{id}/review` ;
- `POST /api/admin/book-versions/{id}/publish` ;
- `POST /api/books/{slug}/questions`.
