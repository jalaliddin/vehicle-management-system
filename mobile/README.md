# UTG Transport — mobil ilova

Android app for field staff of the UTG transport system: mechanics and
doctors work their inspection queues, dispatchers create and move waybills,
HQ approves. It talks to the Laravel API in `../backend`; admin screens stay
in the web app.

## Run

```bash
flutter pub get

# Against a local backend from the Android emulator (10.0.2.2 = host machine)
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api

# Against a phone on the same network
flutter run --dart-define=API_BASE_URL=http://<PC-IP>:8000/api
```

Without `API_BASE_URL` the app uses `https://vms.utg.uz/api`. Plain HTTP is
allowed in debug builds only.

Seeded logins (password `password`): `mechanic@utg.uz`, `doctor@utg.uz`,
`dispatcher@utg.uz`, `hq@utg.uz`.

## Test

```bash
flutter analyze
flutter test
```

## Release APK

1. Create a keystore and `android/key.properties` (both git-ignored):

   ```properties
   storePassword=...
   keyPassword=...
   keyAlias=upload
   storeFile=/absolute/path/to/upload-keystore.jks
   ```

   Without it, release builds are signed with the debug key — fine for
   testing, not for distribution.

2. Build:

   ```bash
   flutter build apk --release --dart-define=API_BASE_URL=https://vms.utg.uz/api
   ```

   Output: `build/app/outputs/flutter-apk/app-release.apk`.

## Structure

Layered per Flutter's architecture guide (MVVM):

```
lib/
├── data/services/       ApiClient (http + Sanctum token), secure session storage
├── data/repositories/   Auth, Waybill, Dashboard, Reference (pick lists)
├── domain/models/       Plain models with fromJson; WaybillAction = who may do what
├── routing/             go_router: login redirect, tab shell, full-screen routes
└── ui/
    ├── core/            Theme, shared widgets, ViewModel base
    └── features/        auth, tasks, waybills, scanner, profile, shell
```

## Known backend gaps the app works around

- Nothing sets `doctor_check`, so the doctor queue reads `mechanic_ok`.
- Waybill transition endpoints don't check roles; the app hides actions by
  role (`WaybillAction.availableFor`), like the web app does.
