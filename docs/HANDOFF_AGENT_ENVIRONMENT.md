# SSK Book — Passation à l’agent disposant de l’environnement d’exécution

## 1. Mission de l’agent

Poursuivre l’implémentation de `ssk-book` à partir du dépôt :

- dépôt : `https://github.com/yveskapinga/ssk-book` ;
- branche : `main` ;
- dernier commit distant au moment de cette passation : `b8107324aa108d17c8137b6cfb9caa1580bdec54`.

Le projet doit devenir un MVP réellement exécutable, testable sur Android comme PWA et utilisable pour :

1. lire le livre du Prophète KADIMA ;
2. suivre sa progression ;
3. interroger le livre avec des réponses Gemini fondées sur des passages cités ;
4. passer des quiz interactifs ;
5. administrer le contenu, les versions, l’indexation et les utilisateurs.

Le PDF source n’est pas versionné dans Git. Il devra être fourni à l’environnement d’exécution et importé depuis la console administrateur.

---

## 2. Règles architecturales obligatoires

### 2.1 SQL-first

PostgreSQL est la source de vérité du domaine.

- Les tables, contraintes, index, transitions persistantes et invariants sont définis dans les migrations SQL.
- Les repositories DBAL contiennent les requêtes SQL et hydratent les résultats.
- Les services valident les commandes, contrôlent les règles métier et orchestrent les transactions.
- Les contrôleurs décodent les requêtes, appellent un service dans un `try/catch` et exposent la réponse.
- Aucun SQL dans un contrôleur.
- Aucun SQL dans un service.
- Ne pas générer le schéma depuis des entités ORM.
- Toute opération administrative sensible doit être auditée.

Le script `scripts/check-sql-first-boundaries.sh` protège ces frontières et doit rester exécuté dans la CI.

### 2.2 Frontend orienté parcours

Ne pas revenir à une collection de pages CRUD par entité.

Parcours lecteur :

1. Aperçu ;
2. Lecture ;
3. Demander au livre ;
4. Quiz ;
5. Progression.

Parcours administrateur :

1. Pilotage ;
2. Importer ;
3. Contrôler les passages ;
4. Indexer ;
5. Approuver et publier ;
6. Gérer les utilisateurs.

Les interfaces doivent conserver :

- pages à onglets ;
- grilles adaptatives ;
- formulaires multicolonnes sur écran large ;
- tableaux recherchables ;
- menus trois-points pour les actions contextuelles ;
- transformation des tableaux en cartes sur mobile ;
- navigation latérale sur ordinateur et navigation inférieure sur mobile.

---

## 3. Technologies et structure

### Backend

- Symfony 7.3 ;
- PHP 8.3 ;
- Doctrine DBAL et migrations ;
- PostgreSQL 16 ;
- extensions `vector` et `pg_trgm` ;
- Poppler `pdftotext` ;
- Gemini pour embeddings et génération.

### Frontend

- React 19 ;
- TypeScript ;
- Vite ;
- React Router ;
- TanStack Query ;
- `vite-plugin-pwa`.

### Répertoires

- `api/` : Symfony ;
- `web/` : React PWA ;
- `infrastructure/postgres/` : initialisation PostgreSQL ;
- `docs/` : contrats d’architecture et passation ;
- `storage/books/` : emplacement local ignoré par Git ;
- `.github/workflows/ci.yml` : intégration continue.

---

## 4. Ce qui est déjà implémenté

### 4.1 Fondation

- monorepo Symfony + React ;
- configuration Docker ;
- PostgreSQL avec `pgvector` et `pg_trgm` ;
- endpoint `/api/health` ;
- PWA installable ;
- CI GitHub ;
- conventions SQL-first documentées.

### 4.2 Identité

- tables `app_users`, `user_access_tokens`, `audit_logs` ;
- inscription ;
- connexion ;
- déconnexion ;
- profil courant ;
- rôles `ROLE_USER` et `ROLE_ADMIN` ;
- suspension et réactivation ;
- révocation des jetons lors de la suspension ;
- commande `app:admin:create`.

### 4.3 Livre et ingestion

- tables `books`, `book_versions`, `book_nodes`, `book_pages`, `book_chunks`, `ingestion_jobs` ;
- versionnement des sources ;
- empreintes SHA-256 ;
- import PDF limité à 50 Mo ;
- stockage hors Git ;
- extraction avec `pdftotext` ;
- conservation page par page ;
- normalisation ;
- découpage en passages ;
- suivi de l’ingestion ;
- statut final `REVIEW_REQUIRED`.

### 4.4 Revue et publication

- décisions `APPROVED` et `REJECTED` ;
- historique des revues ;
- publication conditionnée par l’approbation et l’indexation complète ;
- `books.current_version_id` ;
- protection SQL contre l’insertion, la modification ou la suppression du contenu publié ;
- audit des décisions et publications.

### 4.5 Intelligence documentaire

- colonne `VECTOR(768)` ;
- embeddings Gemini par lots de 20 ;
- reprise sur les passages non indexés ;
- recherche vectorielle cosinus ;
- recherche plein texte française ;
- fusion des rangs ;
- Gemini reçoit uniquement les passages retrouvés ;
- filtrage des identifiants de sources retournés ;
- refus d’une réponse affirmative sans citation ;
- conversations, messages et sources historisés.

### 4.6 Frontend

- authentification ;
- restauration de session ;
- routes protégées ;
- application shell par rôle ;
- tableau de bord lecteur ;
- lecteur à onglets ;
- sommaire ;
- notes et favoris en état vide ;
- écran « Demander au livre » ;
- surface Quiz ;
- surface Progression ;
- centre administrateur unique à onglets ;
- import PDF ;
- revue, indexation et publication ;
- gestion des utilisateurs ;
- tableaux recherchables et menus trois-points ;
- responsive bureau, tablette et mobile.

---

## 5. Ce qui n’a pas pu être exécuté ou validé

L’environnement de l’agent précédent ne disposait pas de PHP, Composer ni Docker.

Par conséquent, les éléments suivants sont codés mais pas encore validés dans une stack complète :

- installation Composer ;
- démarrage des conteneurs ;
- exécution réelle des migrations ;
- compilation du conteneur PHP avec Poppler ;
- création du premier administrateur ;
- import réel du PDF de 335 pages ;
- extraction réelle par l’API ;
- écriture réelle des pages et passages en PostgreSQL ;
- calcul réel des embeddings Gemini ;
- recherche hybride sur les données du livre ;
- génération réelle d’une réponse sourcée ;
- revue et publication réelles ;
- parcours E2E dans un navigateur ;
- installation et comportement PWA sur Android.

Le frontend React compile localement. Toutefois, la CI GitHub observée avant la dernière refonte était en échec sur les jobs `api` et `web`. L’agent doit consulter les nouveaux runs et corriger la cause exacte.

---

## 6. Lacunes fonctionnelles à implémenter

### 6.1 Lecture structurée

Le lecteur existe visuellement mais n’est pas encore branché sur des endpoints de lecture.

À réaliser :

- endpoints de bibliothèque et version publiée ;
- table des matières issue de `book_nodes` ;
- lecture paginée ou par passages ;
- recherche textuelle dans le livre ;
- reprise de lecture ;
- favoris ;
- surlignages ;
- notes personnelles ;
- progression globale et par chapitre ;
- cache PWA des contenus déjà téléchargés.

Important : l’ingestion actuelle remplit les pages et passages, mais ne détecte pas encore réellement les parties, chapitres et sections. `book_nodes` reste à alimenter.

### 6.2 Quiz

La surface frontend existe mais le domaine backend n’est pas encore implémenté.

Créer en SQL-first :

- `quizzes` ;
- `quiz_questions` ;
- `quiz_choices` ;
- `quiz_attempts` ;
- `quiz_answers` ;
- sources justificatives ;
- statuts `DRAFT`, `REVIEW_REQUIRED`, `PUBLISHED`, `ARCHIVED` ;
- contraintes de cohérence des choix et réponses ;
- score calculé côté service et non par le frontend ;
- historique et progression ;
- endpoints administratifs et lecteur ;
- branchement des onglets Quiz, Résultats et Classement.

Gemini peut proposer des questions, mais aucune question générée ne doit être publiée sans validation humaine.

### 6.3 Authentification

L’authentification actuelle utilise un Bearer token stocké dans `sessionStorage`.

Pour le durcissement MVP :

- migrer de préférence vers un cookie sécurisé `HttpOnly`, `Secure`, `SameSite` ;
- récupération de mot de passe ;
- limitation des tentatives de connexion ;
- expiration et renouvellement des sessions ;
- quotas de questions ;
- protection CSRF si cookie ;
- politique CORS de production ;
- validation des erreurs sans fuite d’informations.

### 6.4 Administration

- formulaire explicite de motif de rejet au lieu du motif frontend statique ;
- progression asynchrone de l’import et des embeddings ;
- déplacement des traitements longs vers Symfony Messenger ;
- possibilité de reprendre un job échoué ;
- journal d’audit consultable ;
- détail de l’activité d’un utilisateur ;
- statistiques de consommation Gemini ;
- quotas configurables ;
- signalement et revue des réponses problématiques.

### 6.5 Frontend

Les parcours existent, mais Lecture, Quiz, Progression, Notes et Favoris affichent encore des données de présentation ou des états vides.

L’agent doit :

- remplacer toutes les données représentatives par les réponses API ;
- ajouter chargement, erreur, état vide et succès pour chaque opération ;
- ajouter pagination réelle aux tableaux ;
- conserver les filtres dans l’URL ;
- ajouter confirmations pour les actions sensibles ;
- créer les formulaires de notes, surlignage, rejet et quiz ;
- ajouter feedback utilisateur après chaque action ;
- vérifier clavier, lecteur d’écran, zoom 200 % et cibles tactiles ;
- tester les tailles 360, 390, 768, 1024, 1366 et 1920 pixels.

---

## 7. Risques techniques connus à vérifier

1. **Migrations PostgreSQL** : exécuter toutes les migrations sur une base vide et vérifier les triggers.
2. **Dimension vectorielle** : confirmer que `gemini-embedding-001` accepte `outputDimensionality = 768` avec la clé utilisée.
3. **Volume Gemini** : environ plusieurs centaines de passages ; gérer quotas, retries, backoff et reprise.
4. **Traitements synchrones** : l’import et l’indexation peuvent dépasser le délai HTTP. Les passer en jobs asynchrones.
5. **Slug du livre** : le frontend des questions utilise actuellement `le-prophete-kadima-bakenge`. Vérifier le slug obtenu lors de l’import.
6. **Correspondance des pages** : comparer les pages extraites avec le PDF original.
7. **Qualité du découpage** : le découpage actuel cible environ 2 400 caractères et ne tient pas encore compte des chapitres.
8. **Citations** : constituer un jeu d’évaluation et contrôler que les pages citées correspondent réellement aux affirmations.
9. **CI** : les deux jobs ont été observés en échec ; ne pas considérer `main` comme stable avant correction.
10. **Données sensibles** : aucune clé Gemini ne doit être commise ou envoyée au frontend.

---

## 8. Ordre recommandé de reprise

### Tranche A — Rendre la stack exécutable

1. Cloner `main`.
2. Copier `.env.example` vers `.env`.
3. Définir un `APP_SECRET`, les identifiants PostgreSQL et la clé Gemini.
4. Lancer `docker compose up --build`.
5. Corriger toutes les erreurs Composer, Symfony, Docker et PostgreSQL.
6. Exécuter les migrations.
7. Corriger la CI jusqu’à obtenir les jobs API et frontend verts.

### Tranche B — Premier parcours réel administrateur

1. Créer le premier administrateur.
2. Se connecter depuis React.
3. Importer le vrai PDF.
4. Suivre le job d’ingestion.
5. Contrôler le nombre de pages et de passages.
6. Comparer des pages choisies avec le PDF.
7. Calculer les embeddings.
8. Approuver puis publier.

### Tranche C — Premier parcours réel lecteur

1. Exposer la version publiée.
2. Alimenter le lecteur avec les données API.
3. Construire et exposer le sommaire.
4. Ajouter progression, favoris, notes et surlignages.
5. Poser les questions de référence.
6. Vérifier réponse, extraits et pages.

### Tranche D — Quiz

1. Ajouter le modèle SQL-first.
2. Ajouter repositories, services et contrôleurs.
3. Créer et publier un premier quiz administratif.
4. Brancher le parcours lecteur.
5. Vérifier score, correction et historique.

### Tranche E — Durcissement MVP

1. Traitements asynchrones.
2. Authentification sécurisée.
3. Quotas et rate limiting.
4. Tests automatisés.
5. PWA Android.
6. Déploiement pilote.

---

## 9. Commandes initiales attendues

À adapter selon l’environnement :

```bash
git clone https://github.com/yveskapinga/ssk-book.git
cd ssk-book
cp .env.example .env
docker compose up --build
docker compose exec api php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec api php bin/console app:admin:create admin@example.com "Administrateur"
```

Contrôles :

```bash
curl http://localhost:8080/api/health
sh scripts/check-sql-first-boundaries.sh
docker compose exec api php bin/phpunit
docker compose exec web npm run build
```

Ne jamais mettre le mot de passe administrateur ni la clé Gemini dans une commande conservée dans l’historique du shell.

---

## 10. Jeu d’évaluation initial pour les réponses

Créer au minimum 30 questions réparties entre :

- faits simples ;
- personnes ;
- dates ;
- lieux ;
- habitudes ;
- événements ;
- questions nécessitant plusieurs passages ;
- questions auxquelles le livre ne répond pas ;
- formulations synonymiques ;
- questions contenant `SSK`, `Souverain Sacrificateur` ou `Prophète KADIMA`.

Question de référence obligatoire :

> Le SSK aimait manger quel aliment ?

La réponse doit conserver la nuance du livre : il est difficile d’identifier un plat préféré unique ; son alimentation était semi-végétarienne, avec notamment légumes, poissons, bidia ou fufu, bananes plantain et fruits. Les pages 68–69 doivent être vérifiées dans le PDF original.

Mesurer :

- exactitude ;
- fidélité aux sources ;
- pages correctes ;
- refus en cas de preuve insuffisante ;
- absence d’informations externes inventées ;
- temps de réponse ;
- consommation Gemini.

---

## 11. Tests minimaux avant de déclarer le MVP

### SQL et backend

- migrations sur base vide ;
- contraintes et triggers ;
- transactions et concurrence ;
- permissions utilisateur/administrateur ;
- suspension et révocation ;
- import PDF ;
- reprise d’un job ;
- embeddings ;
- publication ;
- immutabilité ;
- recherche hybride ;
- conversations et citations.

### Frontend

- inscription, connexion et déconnexion ;
- parcours lecteur complet ;
- parcours administrateur complet ;
- recherche dans les tableaux ;
- menus trois-points ;
- formulaires multicolonnes ;
- erreurs réseau ;
- états vides ;
- responsive ;
- clavier et focus ;
- installation PWA.

### E2E

1. Admin importe le PDF.
2. Le système extrait 335 pages attendues, à confirmer selon le résultat réel.
3. Admin inspecte, indexe, approuve et publie.
4. Lecteur ouvre le livre et reprend sa progression.
5. Lecteur pose une question et ouvre une source.
6. Lecteur passe un quiz et consulte sa correction.

---

## 12. Critères de sortie

Le MVP peut être déclaré prêt pour pilote seulement lorsque :

- la CI est verte ;
- la stack démarre depuis un clone propre ;
- les migrations passent sur une base vide ;
- le vrai livre est importé et vérifié ;
- le lecteur utilise les données publiées ;
- les embeddings sont complets ;
- le jeu d’évaluation des réponses est accepté ;
- un quiz complet est administrable et jouable ;
- les parcours critiques sont couverts par des tests E2E ;
- la PWA est installable et utilisable sur Android ;
- aucun secret n’est présent dans Git ou le frontend.

---

## 13. Instruction courte à donner à l’agent

> Reprends le projet `yveskapinga/ssk-book` sur `main`. Lis d’abord `docs/HANDOFF_AGENT_ENVIRONMENT.md`, `docs/SQL_FIRST_CONVENTIONS.md`, `docs/ARCHITECTURE_MVP.md` et `docs/FRONTEND_WORKFLOWS.md`. Respecte strictement l’architecture SQL-first : migrations et contraintes dans PostgreSQL, SQL dans les repositories DBAL, validation et orchestration dans les services, contrôleurs minces avec `try/catch`. Commence par rendre Docker, Symfony, PostgreSQL et la CI entièrement opérationnels, puis exécute le parcours réel import PDF → extraction → embeddings → revue → publication → question sourcée. Ensuite, branche les parcours Lecture et Progression sur l’API et implémente le domaine Quiz complet. Itère jusqu’à satisfaction de tous les critères de sortie définis dans le document de passation. Ne remplace pas les parcours frontend par des pages CRUD et ne publie aucun contenu généré sans validation humaine.
