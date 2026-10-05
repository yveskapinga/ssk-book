# SSK Book — app mobile (Expo)

Lecteur offline-first, auth durable, push Expo, UI alignée sur la PWA.

## Prérequis

- Node 20+
- API distante : `https://ssk-book.yabisoo.com` (défaut / EAS)
- API locale (`docker compose` → `:8080`) : `EXPO_PUBLIC_API_BASE_URL=http://10.0.2.2:8080` (émulateur Android)

## Démarrage

```bash
cp .env.example .env
npm install
npx expo start
```

## Offline

Voir [`../docs/product/mobile-offline-invariants.md`](../docs/product/mobile-offline-invariants.md).

## APK debug

```bash
npx expo prebuild --platform android
cd android && ./gradlew assembleDebug   # Windows: gradlew.bat assembleDebug
adb install -r app/build/outputs/apk/debug/app-debug.apk
```

## Play Store (AAB)

1. Créer un projet EAS : `npx eas init` (renseigner `extra.eas.projectId` dans `app.json`).
2. Mettre l’URL API prod dans `eas.json` → `production.env.EXPO_PUBLIC_API_BASE_URL`.
3. `npx eas build -p android --profile production`
4. Soumettre : `npx eas submit -p android --profile production` (compte Play Console requis).

## Package

- Android / iOS : `app.ssk.book`
