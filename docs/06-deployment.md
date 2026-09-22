# 06 — Deployment

**Versi dokumen:** 1.0.0 · 22 September 2026
**Target:** `TODO:` domain/subdomain final belum dikonfirmasi (D12). Rancangan: `https://pkkmb.uninus.ac.id/`

---

## 1. Prasyarat

| # | Hal | Penanggung jawab | Status |
|---|---|---|---|
| P1 | VPS aktif dengan akses root SSH | User | ✅ Sudah ada (22 Sep 2026) |
| P2 | Domain/subdomain diarahkan ke IP VPS | Tim IT UNINUS | ⬜ |
| P3 | Sertifikat SSL (Let's Encrypt) | Otomatis via certbot | ⬜ |
| P4 | File Excel PMB (D1) | Panitia/PMB | ⬜ |
| P5 | Daftar sesi presensi resmi (D2) | Panitia | ⬜ |
| P6 | Scanner gun diuji dengan halaman `/scan` (D4) | User + panitia | ⬜ |

**Sistem tidak boleh dipakai untuk data asli** sebelum checklist [`04-security.md`](04-security.md) §11 lolos.

---

## 2. Perangkat Lunak di Server

> ⚠️ **Isi versi persisnya setelah instalasi nyata.** Jangan menulis versi dari ingatan —
> lihat [`../AGENTS.md`](../AGENTS.md) §4.

**Versi di mesin pengembangan (diverifikasi 22 September 2026):**

| Komponen | Versi dev | Versi server | Catatan |
|---|---|---|---|
| OS | Windows 10 Pro | `TODO:` | server: Ubuntu LTS disarankan |
| Nginx | — | `TODO:` | reverse proxy + penyaji statis |
| PHP | **8.3.32** | `TODO:` | ekstensi `pdo_pgsql`, `pgsql`, `mbstring`, `gd`, `zip`, `intl`, `fileinfo` — semua aktif di dev |
| Composer | **2.10.3** | `TODO:` | dipasang dengan verifikasi SHA-384 installer |
| Laravel | **13.32.0** | idem | `laravel/framework ^13.17` |
| Laravel Sanctum | terpasang via `artisan install:api` | idem | auth cookie SPA same-origin |
| Node.js | **24.18.0** | `TODO:` | server: pakai versi LTS |
| npm | **11.16.0** | `TODO:` | |
| Next.js | **16.3.5** | idem | ⚠️ lihat catatan breaking change di bawah |
| React | **19.2.8** | idem | |
| Tailwind CSS | **4.x** | idem | |
| TypeScript | **5.x** | idem | |
| GSAP | **3.15.0** | idem | ScrollTrigger sudah termasuk di paket publik |
| Lenis | **1.3.26** | idem | MIT |
| PostgreSQL | **17.10** | `TODO:` | dev: service `postgresql-x64-17` |
| PM2 | — | `TODO:` | menjaga proses Next tetap hidup |

> ⚠️ **Next.js 16 punya breaking change yang relevan langsung:** `middleware.ts` → `proxy.ts`
> (nama berkas dan nama fungsi), dan `cookies()`/`headers()`/`params`/`searchParams` wajib di-`await`.
> Diverifikasi dari `frontend/node_modules/next/dist/docs/01-app/02-guides/upgrading/version-16.md`.
> Dokumen bawaan itu adalah acuan, bukan ingatan — lihat [`../AGENTS.md`](../AGENTS.md) §4.

> ⚠️ **Catatan keamanan mesin dev:** `pg_hba.conf` di mesin ini memakai `trust` untuk koneksi
> `127.0.0.1`, sehingga proses lokal mana pun bisa masuk sebagai superuser PostgreSQL.
> Itu **tidak boleh** ditiru di server — di VPS gunakan `scram-sha-256` dan user aplikasi
> non-superuser (§5 langkah 2).

Paket PHP yang direncanakan (**verifikasi nama & kompatibilitas versi saat instalasi**):
pembuat QR, pembaca/penulis Excel, dan pembuat PDF. Jangan menambah paket di luar kebutuhan itu tanpa
persetujuan user ([`../AGENTS.md`](../AGENTS.md) §3).

---

## 3. Arsitektur di Server

**Keputusan: frontend dan API berbagi satu domain (same-origin).**

```
                    Internet
                       │  HTTPS
                  ┌────▼────┐
                  │  Nginx  │
                  └──┬───┬──┘
        /api/*, /sanctum/*  │  selain itu
                     │      │
             ┌───────▼──┐ ┌─▼──────────┐
             │ PHP-FPM  │ │  Next.js   │
             │ Laravel  │ │  (PM2:3000)│
             └────┬─────┘ └────────────┘
                  │
           ┌──────▼──────┐
           │ PostgreSQL  │  hanya localhost
           └─────────────┘
```

**Kenapa same-origin:** cookie sesi bisa `HttpOnly` + `SameSite=Lax` tanpa perlu melonggarkan CORS,
dan tidak ada token yang disimpan di `localStorage`. Lihat [`04-security.md`](04-security.md) §7.

Kerangka konfigurasi Nginx (dilengkapi saat Fase 1):

```nginx
server {
    listen 443 ssl http2;
    server_name pkkmb.uninus.ac.id;   # TODO: sesuaikan D12

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options DENY always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;

    client_max_body_size 12M;   # impor Excel maks 10 MB

    location ~ ^/(api|sanctum)/ {
        root /var/www/pkkmb26/backend/public;
        try_files $uri /index.php?$query_string;
        # ... fastcgi_pass ke PHP-FPM
    }

    location / {
        proxy_pass http://127.0.0.1:3000;   # Next.js
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # storage/ tidak pernah dilayani langsung
    location /storage { deny all; }
}

server {
    listen 80;
    server_name pkkmb.uninus.ac.id;
    return 301 https://$host$request_uri;
}
```

`X-Forwarded-For` wajib diteruskan — tanpa itu **seluruh rate limit** melihat semua pengunjung sebagai
satu IP (IP Nginx), dan perlindungan di [`04-security.md`](04-security.md) §2 tidak berguna.
Setel `TRUSTED_PROXIES` di Laravel.

---

## 4. Struktur Direktori

```
/var/www/pkkmb26/
├── backend/           Laravel (repo yang sama, subfolder)
│   ├── public/        satu-satunya folder Laravel yang dilayani Nginx
│   └── storage/app/imports/   file Excel — DI LUAR webroot
├── frontend/          Next.js
└── .env               TIDAK di-commit
```

---

## 5. Langkah Deploy (pertama kali)

1. Pasang paket sistem: Nginx, PHP-FPM + ekstensi, Composer, Node.js, PM2, PostgreSQL, certbot.
2. Buat database dan user PostgreSQL **khusus aplikasi** (bukan superuser), batasi ke `localhost`.
3. Klon repo ke `/var/www/pkkmb26`.
4. `backend/`: `composer install --no-dev --optimize-autoloader`, salin `.env`, `php artisan key:generate`.
5. `php artisan migrate --force`.
6. **Buat akun admin pertama lewat perintah artisan interaktif** — bukan seeder berisi kata sandi
   ([`04-security.md`](04-security.md) §1).
7. `php artisan config:cache route:cache view:cache`.
8. `frontend/`: `npm ci && npm run build`, jalankan lewat PM2 (`pm2 start npm --name pkkmb-web -- start`),
   lalu `pm2 save && pm2 startup`.
9. Pasang konfigurasi Nginx, uji `nginx -t`, muat ulang.
10. `certbot --nginx` untuk SSL, pastikan pembaruan otomatis aktif.
11. Setel izin: `storage/` dan `bootstrap/cache` dapat ditulis PHP-FPM, tidak lebih longgar dari itu.
12. Jalankan checklist [`04-security.md`](04-security.md) §11 **seluruhnya**.

### Deploy berikutnya

```
git pull
cd backend  && composer install --no-dev -o && php artisan migrate --force && php artisan config:cache
cd ../frontend && npm ci && npm run build && pm2 reload pkkmb-web
```

Jalankan `php artisan down` sebelum migrasi yang mengubah struktur, dan `php artisan up` sesudahnya —
**kecuali pada hari-H**, di mana tidak boleh ada deploy sama sekali (§7).

---

## 6. Backup & Pemulihan

Data kehadiran adalah dokumen resmi. Kehilangannya tidak bisa diperbaiki setelah acara bubar.

| Kapan | Apa |
|---|---|
| Harian, otomatis (cron) | `pg_dump` ke direktori backup, simpan 14 hari terakhir |
| **Setiap 15 menit pada hari-H** | `pg_dump` tabel `attendances` + `scan_logs` |
| Sebelum setiap migrasi | dump manual |
| H-1 | satu salinan diunduh ke **luar server** (laptop user) |

**Backup yang belum pernah dipulihkan bukan backup.** Sebelum go-live, lakukan sekali:
pulihkan dump ke database uji dan pastikan datanya utuh. Butir 16 di checklist keamanan.

### Rollback

- Kode: `git checkout <tag-sebelumnya>` lalu ulangi langkah deploy. Beri tag setiap rilis
  (`rilis-YYYYMMDD-HHmm`).
- Database: migrasi **maju saja**. Jangan `migrate:rollback` di produksi setelah ada data asli —
  pulihkan dari dump kalau benar-benar terpaksa.
- Kalau `/scan` bermasalah di hari-H: **jangan memperbaiki sambil acara berjalan.** Beralih ke rencana
  cadangan kertas ([`07-timeline.md`](07-timeline.md) §6), perbaiki setelah sesi selesai, lalu masukkan
  data kertas lewat input manual.

---

## 7. Runbook Hari-H

**Aturan utama: tidak ada deploy, tidak ada migrasi, tidak ada perubahan kode pada hari-H.**
Pembekuan kode dimulai H-1 sore.

**H-1:**
- [ ] Deploy final, lalu bekukan kode.
- [ ] Uji end-to-end dengan **3 scanner gun asli**, tiga laptop, bersamaan.
- [ ] Buat sesi presensi sesuai D2, pastikan jendela waktunya benar (zona waktu Asia/Jakarta).
- [ ] Buat akun `operator` untuk tiap titik (Pintu A/B/C), uji login di laptop yang akan dipakai.
- [ ] Cetak **daftar hadir kertas cadangan** per fakultas — wajib, bukan opsional.
- [ ] Cetak beberapa nametag uji, pindai sungguhan, pastikan QR terbaca.
- [ ] Unduh backup database ke luar server.
- [ ] Briefing petugas: cara pakai, arti tiga warna, kapan pakai input manual, siapa yang dihubungi.

**Hari-H, sebelum acara:**
- [ ] Buka `/scan` di tiap laptop, login, pilih sesi & label pintu.
- [ ] Uji satu pemindaian nyata di tiap titik.
- [ ] Pastikan indikator "Terhubung" hijau di ketiganya.
- [ ] Pastikan daya laptop & koneksi tercadangkan (tethering HP sebagai cadangan).

**Saat acara:**
- Pantau `/admin` untuk laju kedatangan dan jumlah hadir.
- Kalau satu titik bermasalah → alihkan antrean ke titik lain, jangan menghentikan semuanya.
- Kalau seluruh sistem bermasalah → **beralih ke kertas**, catat NIM, masukkan setelahnya lewat
  input manual.

**Setelah acara:**
- [ ] Ekspor rekap kehadiran per sesi ke Excel, serahkan ke panitia.
- [ ] Masukkan catatan kertas (kalau ada) lewat input manual, tandai `method = manual`.
- [ ] Backup final, disimpan di luar server.
- [ ] Petugas logout dari semua laptop.

---

## 8. Staging

Kalau waktu memungkinkan, jalankan satu salinan di `pkkmb-staging.<domain>` atau port terpisah dengan
**database berbeda berisi data samaran**. Staging **wajib** memakai `robots.txt` berisi `Disallow: /`,
dan **tidak boleh** memuat data mahasiswa asli.

Dengan jadwal tujuh hari, staging adalah SHOULD, bukan MUST. Yang **tidak boleh** dikorbankan adalah
uji end-to-end dengan scanner asli di H-1.
