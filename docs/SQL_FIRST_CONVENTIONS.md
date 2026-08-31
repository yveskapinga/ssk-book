# Conventions SQL-first

## Responsabilités

### PostgreSQL

Définit la structure, les types, les contraintes, les index, l'intégrité référentielle et les invariants qui doivent rester vrais quelle que soit l'origine d'une écriture.

### Repository DBAL

Contient uniquement le SQL et la conversion entre lignes SQL et modèles PHP. Il ne décide pas d'une règle métier et ne produit pas de réponse HTTP.

### Service applicatif

Valide les entrées métier, contrôle les autorisations contextuelles, ouvre les transactions, appelle les repositories, orchestre les opérations et écrit l'audit.

### Contrôleur

Extrait les paramètres HTTP dans un `try/catch`, appelle un service et transforme son résultat ou son exception en réponse HTTP. Aucun SQL et aucune décision métier n'y sont admis.

## Interdictions

- génération automatique du schéma depuis des entités ORM ;
- requête SQL dans un contrôleur ;
- validation métier limitée au frontend ;
- suppression physique d'une donnée auditée sans décision explicite ;
- changement d'état sans contrainte, service et trace d'audit.
