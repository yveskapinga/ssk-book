# Parcours frontend par rôle

Le frontend n'est pas organisé par pages CRUD d'entités. Chaque espace suit l'ordre réel des actions du rôle.

## Lecteur

1. **Aperçu** : progression, prochaine action et reprise de lecture.
2. **Lecture** : lecteur, sommaire et notes dans un même espace à onglets.
3. **Demander** : question, réponse et passages sources.
4. **Quiz** : quiz disponibles, résultats et classement.
5. **Progression** : parcours, favoris et notes.

## Administrateur

1. **Pilotage** : indicateurs, alertes et opérations récentes.
2. **Importer** : métadonnées compactes sur deux colonnes, PDF et résultat d'ingestion.
3. **Contrôler et publier** : recherche des versions, inspection des passages, embeddings, approbation, rejet et publication.
4. **Utilisateurs** : recherche, activité, suspension et réactivation.

## Comportement responsive

- grille adaptative de quatre à deux puis une carte ;
- formulaires en deux colonnes sur grand écran et une colonne sur mobile ;
- lecteur en deux panneaux puis en pile ;
- navigation latérale sur bureau et barre inférieure sur mobile ;
- tableaux transformés en cartes sur petit écran ;
- colonnes secondaires masquées uniquement lorsque l'espace devient insuffisant ;
- actions accessibles par un menu trois-points au clavier et au toucher ;
- onglets horizontalement défilables sans casser la mise en page.
