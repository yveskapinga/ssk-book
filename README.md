# ssk-book

PWA de lecture interactive, quiz et conversation documentée autour du livre du Souverain Sacrificateur KADIMA.

## Architecture cible

- `api/` : Symfony API, authentification, ingestion, quiz, administration et appels Gemini.
- `web/` : React + TypeScript + Vite PWA.
- `mobile/` : app Expo (lecteur offline, push, Play Store) — voir [`mobile/README.md`](mobile/README.md).
- PostgreSQL avec l'extension `pgvector`.
- Gemini appelé exclusivement depuis l'API.

## Démarrage local

1. Copier `.env.example` vers `.env` et renseigner les secrets.
2. Lancer `docker compose up --build`.
3. Ouvrir `http://localhost:5173`.
4. Vérifier l'API sur `http://localhost:8080/api/health`.

Créer le premier administrateur après les migrations :

```bash
docker compose exec api php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec api php bin/console app:admin:create admin@example.com "Administrateur"
```

Le dépôt ne contient aucune clé Gemini. Le PDF source ne doit pas être publié dans Git sans autorisation explicite du détenteur des droits.

## Documentation

- [Architecture MVP](docs/ARCHITECTURE_MVP.md)
- [Plan de démarrage](docs/BOOTSTRAP.md)
- [Invariants offline mobile](docs/product/mobile-offline-invariants.md)
- [App mobile Expo](mobile/README.md)
