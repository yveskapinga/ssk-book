# Plan de démarrage

## Lot 1 — Socle exécutable

- générer Symfony dans `api/` ;
- installer DBAL, migrations, security, validator, serializer, messenger, rate limiter et le client HTTP ;
- générer React TypeScript avec Vite dans `web/` ;
- installer le routeur, la gestion des requêtes, la validation des formulaires et le plugin PWA ;
- ajouter les Dockerfiles et le proxy local ;
- créer la migration activant `vector` et `pg_trgm` ;
- ajouter les contrôles de qualité et la CI.

## Lot 2 — Identité et administration

- inscription, connexion, déconnexion et récupération du mot de passe ;
- cookie de session sécurisé ;
- rôles utilisateur et administrateur ;
- console d'administration React sur API dédiée ;
- audit des opérations sensibles.

## Lot 3 — Livre et ingestion

- importer le PDF ;
- extraire pages et texte ;
- structurer parties, chapitres, sections et passages ;
- contrôler l'extraction avant publication ;
- calculer et stocker les embeddings ;
- versionner chaque ingestion.

## Lot 4 — Lecture PWA

- bibliothèque et table des matières ;
- lecteur responsive ;
- progression, favoris, notes et surlignage ;
- cache PWA contrôlé ;
- recherche textuelle.

## Lot 5 — Questions et réponses

- recherche vectorielle et hybride ;
- intégration Gemini côté serveur ;
- réponses avec citations vérifiables ;
- refus en cas de preuve insuffisante ;
- historique, signalement et quotas.

## Lot 6 — Quiz

- création et validation administrative ;
- session interactive, correction et score ;
- sources justificatives ;
- historique et progression.

## Critères de sortie du MVP

- installation PWA fonctionnelle sur Android ;
- lecture et progression utilisables ;
- import contrôlé du livre ;
- réponses fondées et citées sur un jeu d'évaluation ;
- quiz administrables et jouables ;
- secrets absents du dépôt ;
- tests API, frontend et parcours critique réussis.
