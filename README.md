# 🚗 UTG Transport — Avtotransport Boshqaruv Tizimi

**Urganchtransgaz** uchun avtotransport va maxsus texnika boshqaruv tizimi.

---

## 📋 Tizim haqida

| Xususiyat | Tafsilot |
|-----------|----------|
| **Backend** | Laravel 13 (PHP 8.3) + Sanctum API |
| **Frontend** | Vue 3 + Vuetify 3 (Material Design 3) |
| **Ma'lumotlar bazasi** | MySQL 8.4 |
| **Arxitektura** | Docker (PHP-FPM + Nginx) |

### Asosiy imkoniyatlar

- 🏢 **14 ta quyi tashkilot** boshqaruvi
- 🚌 Avtomobil va maxsus texnikalar ro'yxati
- 👤 Haydovchilar registri (PINFL, guvohnoma, staj)
- 📄 Yo'l varaqalari — to'liq tasdiqlash zanjiri:
  - `Dispetcher → Mexanik → Shifokor → HQ → Yo'lga chiqdi → Yakunlandi`
- ⛽ Yoqilg'i monitoring (real vaqtda qoldiq + ogohlantirishlar)
- 📊 Hisobotlar (yo'l varaqalari, yoqilg'i sarfi, avtomobillar)
- 🔐 Role asosidagi kirish huquqi (6 ta rol)

---

## 🐳 Docker bilan ishga tushirish (VPS)

### Talablar

```
Docker >= 24.0
Docker Compose >= 2.20
Git
```

### 1. Kodni yuklab olish

```bash
git clone <REPO_URL> vms.utg.uz
cd vms.utg.uz
```

### 2. `.env` faylini sozlash

```bash
cp .env.example .env
nano .env
```

```env
DB_ROOT_PASSWORD=kuchliparol123
DB_DATABASE=utg_transport
DB_USERNAME=utg_user
DB_PASSWORD=boshqaparol456

BACKEND_PORT=8000
FRONTEND_PORT=80
```

### 3. Backend `.env.docker` ni sozlash

```bash
nano backend/.env.docker
```

Majburiy o'zgartiriladigan qatorlar:
```env
APP_URL=http://YOUR_SERVER_IP:8000   # yoki domen

DB_USERNAME=utg_user                 # .env dagi DB_USERNAME bilan bir xil
DB_PASSWORD=boshqaparol456           # .env dagi DB_PASSWORD bilan bir xil
```

> **APP_KEY** `deploy.sh fresh` buyrug'i avtomatik generatsiya qiladi.

### 4. Deploy qilish

```bash
bash deploy.sh fresh
```

Bu buyruq:
1. Docker image'larni build qiladi
2. MySQL, Backend, Frontend, Queue worker'larni ishga tushiradi
3. Migratsiyalarni bajaradi
4. Boshlang'ich ma'lumotlarni yuklaydi (14 tashkilot + foydalanuvchilar)
5. Laravel cache'ni optimallashtiradi

### 5. Tekshirish

```
http://YOUR_SERVER_IP       → Frontend (Vue 3)
http://YOUR_SERVER_IP:8000  → Backend API
```

---

## 🔑 Kirish ma'lumotlari

| Rol | Email | Parol |
|-----|-------|-------|
| Super Admin | `admin@utg.uz` | `password` |
| HQ Dispetcher | `hq@utg.uz` | `password` |
| Dispetcher | `dispatcher@utg.uz` | `password` |
| Mexanik | `mechanic@utg.uz` | `password` |
| Shifokor | `doctor@utg.uz` | `password` |

> ⚠️ **Ishga tushirgandan so'ng parollarni o'zgartiring!**

---

## 📁 Loyiha tuzilmasi

```
vms.utg.uz/
├── backend/                 # Laravel 13 API
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   │   ├── AuthController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── VehicleController.php
│   │   │   ├── DriverController.php
│   │   │   ├── WaybillController.php
│   │   │   ├── FuelController.php
│   │   │   ├── OrganizationController.php
│   │   │   ├── FuelNormController.php
│   │   │   └── ReportController.php
│   │   └── Models/
│   │       ├── User.php
│   │       ├── Organization.php
│   │       ├── Vehicle.php
│   │       ├── Driver.php
│   │       ├── Waybill.php
│   │       ├── FuelStation.php
│   │       └── FuelTransaction.php
│   ├── routes/api.php
│   ├── Dockerfile
│   ├── .env.docker          # Docker uchun .env
│   └── docker/              # PHP-FPM konfiguratsiya
│
├── frontend/                # Vue 3 + Vuetify 3
│   ├── src/
│   │   ├── views/           # Sahifalar
│   │   ├── components/      # Komponentlar
│   │   ├── stores/          # Pinia stores
│   │   ├── services/api.js  # Axios API client
│   │   └── router/          # Vue Router
│   ├── Dockerfile
│   └── nginx.conf           # Production nginx
│
├── docker/
│   ├── nginx/
│   │   └── backend.conf     # Laravel API nginx
│   └── mysql/
│       └── my.cnf           # MySQL tuning
│
├── docker-compose.yml
├── deploy.sh                # Deploy skripti
├── .env.example
└── README.md
```

---

## 🔌 API Endpointlar

### Autentifikatsiya
```
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me
```

### Dashboard
```
GET    /api/dashboard/stats
```

### Avtomobillar
```
GET    /api/vehicles
POST   /api/vehicles
GET    /api/vehicles/{id}
PUT    /api/vehicles/{id}
DELETE /api/vehicles/{id}
PUT    /api/vehicles/{id}/status
```

### Haydovchilar
```
GET    /api/drivers
POST   /api/drivers
GET    /api/drivers/{id}
PUT    /api/drivers/{id}
DELETE /api/drivers/{id}
POST   /api/drivers/{id}/assign
```

### Yo'l varaqalari
```
GET    /api/waybills
POST   /api/waybills
GET    /api/waybills/{id}
PUT    /api/waybills/{id}
DELETE /api/waybills/{id}

POST   /api/waybills/{id}/submit          # → Mexanikga yuborish
POST   /api/waybills/{id}/mechanic-check  # Mexanik xulosasi
POST   /api/waybills/{id}/doctor-check    # Shifokor xulosasi
POST   /api/waybills/{id}/approve         # HQ tasdiqlash
POST   /api/waybills/{id}/depart          # Yo'lga chiqdi
POST   /api/waybills/{id}/complete        # Yakunlash
POST   /api/waybills/{id}/route           # Leaflet route saqlash
```

### Yoqilg'i
```
GET    /api/fuel/monitoring
GET    /api/fuel/stations
POST   /api/fuel/stations
PUT    /api/fuel/stations/{id}
POST   /api/fuel/stations/{id}/transaction
GET    /api/fuel/transactions
```

### Hisobotlar
```
GET    /api/reports/waybills
GET    /api/reports/fuel
GET    /api/reports/vehicles
GET    /api/reports/drivers
GET    /api/reports/fuel-consumption
```

---

## 🔒 Rollar va huquqlar

| Rol | Huquqlar |
|-----|----------|
| `superadmin` | To'liq huquq, foydalanuvchilar boshqaruvi |
| `hq_dispatcher` | Barcha tashkilotlar, tasdiqlash |
| `dispatcher` | Faqat o'z tashkiloti, varaq yaratish |
| `mechanic` | Mexanik tekshiruvi |
| `doctor` | Shifokor tekshiruvi |
| `viewer` | Faqat ko'rish |

---

## 🛠️ Boshqaruv buyruqlari

```bash
# Holat ko'rish
bash deploy.sh status

# Loglarni ko'rish
bash deploy.sh logs           # backend logs
bash deploy.sh logs frontend  # frontend logs
bash deploy.sh logs db        # mysql logs

# Yangilash (git pull + rebuild)
bash deploy.sh update

# To'xtatish
bash deploy.sh down
```

### Docker Compose to'g'ridan buyruqlar

```bash
# Barcha konteynerlar holati
docker compose ps

# Backend shell
docker compose exec backend sh

# Artisan buyruqlar
docker compose exec backend php artisan migrate
docker compose exec backend php artisan db:seed --force
docker compose exec backend php artisan cache:clear
docker compose exec backend php artisan config:cache

# MySQL'ga kirish
docker compose exec db mysql -u root -p utg_transport
```

---

## ⚙️ Domen va SSL sozlash (ixtiyoriy)

Agar domen bilan ishlatmoqchi bo'lsangiz, serverda Nginx Reverse Proxy o'rnating:

```bash
sudo apt install nginx certbot python3-certbot-nginx

# /etc/nginx/sites-available/vms.utg.uz
server {
    server_name vms.utg.uz;

    location /api/ {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-Proto https;
    }

    location / {
        proxy_pass http://127.0.0.1:80;
        proxy_set_header Host $host;
    }
}

# SSL
certbot --nginx -d vms.utg.uz
```

`backend/.env.docker` ni yangilang:
```env
APP_URL=https://vms.utg.uz
APP_ENV=production
```

---

## 🗄️ Ma'lumotlar bazasi zaxira nusxasi

```bash
# Zaxira olish
docker compose exec db mysqldump \
  -u root -p"${DB_ROOT_PASSWORD}" utg_transport \
  > backup_$(date +%Y%m%d_%H%M).sql

# Tiklash
docker compose exec -T db mysql \
  -u root -p"${DB_ROOT_PASSWORD}" utg_transport \
  < backup_20260501_1200.sql
```

---

## 📊 Tizim talablari (minimum VPS)

| Resurs | Minimum | Tavsiya |
|--------|---------|---------|
| CPU | 2 vCPU | 4 vCPU |
| RAM | 2 GB | 4 GB |
| Disk | 20 GB SSD | 40 GB SSD |
| OS | Ubuntu 22.04 LTS | Ubuntu 24.04 LTS |

---

## 🐛 Muammolar va echimlar

**"Connection refused" xatosi:**
```bash
# MySQL tayyor emasligini tekshiring
docker compose logs db | tail -20
```

**Frontend API'ga ulanmayapti:**
```bash
# backend_nginx ishlayotganini tekshiring
docker compose ps backend_nginx
docker compose logs backend_nginx
```

**Migration xatosi:**
```bash
docker compose exec backend php artisan migrate:status
docker compose exec backend php artisan migrate --force
```

**Cache muammolari:**
```bash
docker compose exec backend php artisan optimize:clear
docker compose exec backend php artisan optimize
```

---

*Urganchtransgaz © 2026 — Avtotransport Boshqaruv Tizimi*
