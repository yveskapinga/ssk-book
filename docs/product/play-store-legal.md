# Play Store — checklist légale SSK Book

Package Android : `app.ssk.book`

## URLs à coller dans Play Console

| Champ Play Console | URL |
| --- | --- |
| Politique de confidentialité | https://ssk-book.yabisoo.com/legal/privacy |
| Conditions d’utilisation (recommandé) | https://ssk-book.yabisoo.com/legal/terms |
| Suppression du compte / des données | https://ssk-book.yabisoo.com/legal/delete-account |

Emplacements typiques :

1. **Présence sur le Play Store** → Politique de confidentialité  
2. **Contenu de l’application** → Sécurité des données / suppression de compte → URL web  
3. Déclaration **compte requis** + suppression **dans l’app** (Profil → Supprimer mon compte)

## Dans l’application

- Profil → liens légales (ouvrent le navigateur)
- Profil → **Supprimer mon compte** → `POST /api/auth/delete-account` `{ "confirm": "DELETE" }`

## Autres champs fréquents

- Adresse e-mail de contact développeur : `yveskapinga@gmail.com`
- Catégorie : Éducation / Livres
- Contenu : pas d’annonces ciblées, pas de vente de données (à confirmer dans le formulaire Data safety)
- Captures : `mobile/store/play-screenshots/`
- AAB : build EAS production

## Data safety (résumé suggéré)

Collecte : e-mail, nom, progression lecture, contenus utilisateur (notes/questions), identifiants d’appareil pour push (opt-in).  
Finalité : fonctionnalités de l’app, compte.  
Pas de vente. Chiffrement en transit (HTTPS).
