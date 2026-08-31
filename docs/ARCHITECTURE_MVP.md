# Architecture MVP — ssk-book

## Objectif

Le MVP permet à un utilisateur authentifié de lire le livre, suivre sa progression, répondre à des quiz et poser des questions. Les réponses générées doivent être fondées sur des passages retrouvés dans le livre et accompagnées de leurs pages sources.

## Principe SQL-first obligatoire

PostgreSQL est la source de vérité du domaine. Les tables, contraintes, index, relations, états autorisés et invariants persistants sont définis par migrations SQL explicites.

- les repositories DBAL exécutent les requêtes SQL et hydratent des modèles de lecture ;
- les services applicatifs valident les commandes, orchestrent les transactions et appliquent les règles métier ;
- les contrôleurs ne portent aucune règle métier : ils décodent la requête, appellent un service et exposent la réponse ;
- les écritures liées sont atomiques et transactionnelles ;
- les erreurs de concurrence ou de contrainte provenant de PostgreSQL ne sont jamais masquées ;
- Doctrine ORM et les entités génératrices de schéma ne constituent pas la source du modèle.

## Modules

### Lecture

- livres et versions ;
- parties, chapitres, sections, pages et passages ;
- progression, favoris, surlignages et notes ;
- recherche textuelle et vectorielle.

### Conversation documentée

1. Calcul de l'embedding de la question.
2. Recherche hybride PostgreSQL : texte intégral, trigrammes et `pgvector`.
3. Sélection de passages publiés appartenant à la version active du livre.
4. Envoi de la question et des passages à Gemini.
5. Validation des références retournées par rapport au contexte fourni.
6. Conservation de la réponse, des sources, de la latence et de la consommation.

Gemini doit répondre exclusivement depuis les extraits fournis. En l'absence de preuve suffisante, la réponse attendue est que le livre ne permet pas de répondre.

### Quiz

- quiz par chapitre ou thème ;
- choix multiple, vrai/faux et réponse courte ;
- correction accompagnée du passage source ;
- tentatives, scores et progression ;
- validation administrative avant publication.

### Administration

La console React utilisera des endpoints administratifs dédiés, reposant sur les mêmes services applicatifs que le reste de l'API. Elle gérera les utilisateurs, le contenu structuré, les imports, les passages, les quiz, les réponses signalées, les quotas et l'audit.

## Sécurité

- clé Gemini disponible uniquement dans l'environnement serveur ;
- mots de passe hachés avec l'algorithme recommandé par Symfony ;
- authentification par cookie sécurisé `HttpOnly` pour un déploiement sous un même domaine ;
- rôles initiaux `ROLE_USER` et `ROLE_ADMIN` ;
- limitation du débit des connexions et des questions ;
- aucune donnée secrète dans les logs ;
- journal d'audit des opérations administratives.

## Entités initiales

`User`, `Book`, `BookVersion`, `BookPart`, `BookChapter`, `BookSection`, `BookPage`, `BookChunk`, `ChunkEmbedding`, `ReadingProgress`, `Bookmark`, `Highlight`, `ReaderNote`, `Quiz`, `QuizQuestion`, `QuizChoice`, `QuizAttempt`, `Conversation`, `ConversationMessage`, `MessageSource`, `IngestionJob`, `AiUsageLog`, `AuditLog`.

## États d'ingestion

`UPLOADED → EXTRACTING → STRUCTURING → EMBEDDING → REVIEW_REQUIRED → PUBLISHED`

Une version publiée demeure traçable. Toute réingestion produit une nouvelle version au lieu d'écraser silencieusement le contenu publié.
