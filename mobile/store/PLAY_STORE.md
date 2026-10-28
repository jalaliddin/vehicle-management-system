# Publishing UTG Transport on Google Play

Everything needed for the first release: what to upload, what to paste, and how
to answer each Play Console form.

## Files

| What | Where | Play Console field |
|---|---|---|
| App bundle | `build/app/outputs/bundle/release/app-release.aab` | Release → Create release |
| App icon 512×512 | `store/icon-512.png` | Store listing → App icon |
| Feature graphic 1024×500 | `store/feature-graphic-1024x500.png` | Store listing → Feature graphic |
| Phone screenshots 1080×1920 | `store/screenshots/01…08_*.png` | Store listing → Phone screenshots |
| Privacy policy | `https://vms.utg.uz/privacy.html` (from `frontend/public/privacy.html`) | App content → Privacy policy |

Rebuild after any change:

```bash
flutter build appbundle --release --dart-define=API_BASE_URL=https://vms.utg.uz/api
flutter test tool/store_screenshots_test.dart   # regenerates the screenshots
```

Before each new upload, raise `version:` in `pubspec.yaml` (`1.0.0+1` → `1.0.1+2`);
the number after `+` must always increase.

## Signing

- Upload key: `~/keystores/utg-transport-upload.jks` (alias `upload`), passwords
  in `android/key.properties`. Neither is in git. **Back both up somewhere safe.**
- On the first release, accept **Play App Signing**. Google then holds the key
  that signs installs; this upload key only proves uploads come from you, and
  Google support can reset it if it is ever lost.
- Upload certificate SHA-256:
  `6E:25:E4:97:8F:EB:58:9E:EA:43:AC:FE:46:E6:BE:45:F1:F7:29:2B:3E:69:8A:6E:AE:73:24:2B:97:80:26:82`

## Store listing

Default language: **Uzbek (uz)**. Add **English (en-US)** as a translation so
reviewers can read it.

**App name** (30 max): `UTG Transport`

### O'zbekcha

**Qisqa tavsif** (80 max):

```
Urganchtransgaz xodimlari uchun yo'l varaqalari: ko'rik, tasdiq va reyslar
```

**To'liq tavsif:**

```
UTG Transport — Urganchtransgaz avtotransport boshqaruv tizimining mobil ilovasi. Yo'l varaqalari bilan ishlash endi telefoningizda: navbatni ko'ring, ko'rikdan o'tkazing, tasdiqlang va reyslarni yakunlang.

Ilova faqat tizimda hisob qaydnomasi bor xodimlar uchun. Hisoblarni tashkilot administratori beradi.

Mexanik uchun
• Texnik ko'rikni kutayotgan yo'l varaqalari navbati
• Bir bosishda «Yaroqli» yoki sababi bilan «Rad etish»

Shifokor uchun
• Tibbiy ko'rik navbati va xulosa kiritish

Dispetcher uchun
• Yangi yo'l varaqasi: avtomobil, haydovchi, yo'nalish, muddat
• Me'yor bo'yicha yoqilg'i sarfini avtomatik hisoblash
• Mexanikka yuborish, «Yo'lga chiqdi» va reysni yakunlash: bosib o'tilgan masofa, sarflangan va qaytarilgan yoqilg'i

Markaziy dispetcher uchun
• Tasdiqlashni kutayotgan yo'l varaqalari
• Avtomobillar, haydovchilar va varaqalar bo'yicha ko'rsatkichlar
• Yoqilg'isi kam qolgan shoxobchalar haqida ogohlantirish

Barcha uchun
• Yo'l varaqasidagi QR kodni skanerlab, uni darhol ochish
• Har bir bosqich: kim va qachon tasdiqlagani, mexanik va shifokor izohlari
• Raqam va holat bo'yicha qidiruv

Ma'lumotlar korxona serverida saqlanadi va faqat shifrlangan ulanish orqali uzatiladi.
```

**Versiya izohi (1.0.0):**

```
Birinchi versiya: ko'rik navbatlari, yo'l varaqasini yaratish va tasdiqlash, QR skaner.
```

### English

**Short description:**

```
Waybills for Urganchtransgaz staff: inspections, approvals and trips
```

**Full description:**

```
UTG Transport is the mobile app for the Urganchtransgaz vehicle management system. Work with waybills from your phone: check your queue, run inspections, approve, and close trips.

The app is for employees with an account in the system only. Accounts are issued by an organization administrator.

For mechanics
• Queue of waybills awaiting the technical check
• Pass in one tap, or reject with a reason

For doctors
• Medical check queue with conclusions

For dispatchers
• New waybill: vehicle, driver, destination, validity
• Fuel estimate from the vehicle's consumption norm
• Send to the mechanic, mark departure, and complete the trip with distance driven and fuel used and returned

For HQ dispatchers
• Waybills awaiting approval
• Fleet, driver and waybill statistics
• Low-fuel alerts for fuel stations

For everyone
• Scan the QR code on a printed waybill to open it instantly
• Full history of each step: who approved it and when, with mechanic and doctor notes
• Search by number and filter by status

Data is stored on the company's servers and transmitted only over encrypted connections.
```

**Release notes (1.0.0):**

```
First release: inspection queues, creating and approving waybills, QR scanner.
```

**Category:** Business. **Contact email:** the same address as in the privacy
policy. **Website:** `https://vms.utg.uz`

## App content forms

**Privacy policy** — `https://vms.utg.uz/privacy.html`. Fill in the contact
email in `frontend/public/privacy.html` (both languages) and deploy the
frontend first; the URL must be live when you submit.

**App access** — choose *All or some functionality is restricted* and give
reviewers a working account on the production server. Create a dedicated
account for this (e.g. role `dispatcher` in one organization, a strong
password, a few sample waybills); do not hand out a real admin account.
Instructions for reviewers:

```
The app is an internal tool for Urganchtransgaz staff; accounts are created by an administrator.
Sign in with the account above. "Vazifalar" (Tasks) shows the work queue, "Varaqalar" (Waybills) lists all waybills and has the "Yangi varaq" (New waybill) button, "Skaner" scans the QR code printed on a waybill, "Profil" has sign-out.
```

**Ads** — No.

**Content rating** — questionnaire category *All other app types*; answer No
to all content questions. Expected rating: Everyone / 3+.

**Target audience** — 18 and over only. Not designed for children.

**Data safety** — suggested answers (check them against what you actually
store):

| Question | Answer |
|---|---|
| Collects or shares user data? | Collects: Yes. Shares: No |
| Encrypted in transit? | Yes (HTTPS) |
| Users can request deletion? | Yes — via the organization administrator or the policy contact |
| Personal info → Name, Email address | Collected, required, for App functionality and Account management |
| App activity → Other user-generated content | Collected (inspection notes, waybill data), App functionality |
| Health info | Declare it if doctors write health details (e.g. blood pressure) in notes |
| Camera images | Not collected (processed on device, never sent) |
| Location, contacts, files | Not collected |

QR scanning uses Google ML Kit, which sends diagnostics to Google. Add what
Google lists for it: <https://developers.google.com/ml-kit/android-data-disclosure>.

**Account deletion** — not required: the app has no in-app sign-up (accounts
come from the administrator). If asked, answer that users cannot create accounts in
the app.

**Government apps / Financial features / Health apps** — answer for your
organization; the app has no financial features.

## Release path

1. Create the app: name `UTG Transport`, default language Uzbek, App, Free.
2. **Internal testing** first: upload the `.aab`, add your own Google accounts as
   testers, install from the Play link on a real phone, and sign in against
   production. This build has not yet run on a physical device; check login,
   a queue, the QR scanner (camera permission prompt), and one full waybill flow.
3. **Personal developer account** (not organization): Google requires a closed
   test with at least 12 testers opted in for 14 days before production access.
   An **organization** account (needs a D-U-N-S number) skips this.
4. Promote to **Production** and submit for review (usually a few days for a new
   app).

If the app should only ever reach company phones, a **private app** through
Managed Google Play (Android Enterprise) avoids a public listing entirely.
