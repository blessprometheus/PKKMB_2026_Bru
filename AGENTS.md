# AGENTS.md — Aturan Kerja Agen untuk PKKMB UNINUS 2026 (v2)

Dokumen ini mengikat untuk **semua agen AI** (Claude Code, OpenCode, Cursor, dsb.) yang bekerja di
repositori ini. Aturan di sini **menimpa** perilaku default agen.

**Konteks penting sebelum apa pun:** proyek ini adalah **penerus** `Desktop/pkkmb` (PKKMB v1, situs
statis, Fase 1 selesai 17 September 2026). User memutuskan pada **22 September 2026** untuk
**mengganti total** v1 — v1 tidak dikembangkan lagi. Lihat [`docs/01-prd.md`](docs/01-prd.md) §2.
`Desktop/pkkmb/docs/` tetap boleh dijadikan **rujukan** (design system, spesifikasi konten), tapi
bukan sumber kebenaran proyek ini.

Proyek ini juga **bukan** bagian dari migrasi CMS PHP di `Desktop/CLAUDE.md`. Yang diwarisi dari sana
hanya identitas brand.

---

## 1. Bahasa

- **Komunikasi dengan user: Bahasa Indonesia.** Selalu — penjelasan, pertanyaan klarifikasi, ringkasan
  pekerjaan, laporan error, dan pesan commit.
- **Seluruh string yang tampil ke pengguna: Bahasa Indonesia** (`lang="id-ID"`) — label, tombol, pesan
  validasi, pesan error API, `aria-label`, `alt`, judul email.
- **Nama variabel, fungsi, kelas, file, kolom database, dan endpoint: Bahasa Inggris**, konsisten:
  `camelCase` (TS/JS), `PascalCase` (komponen React & kelas PHP), `snake_case` (kolom DB & JSON API),
  `kebab-case` (nama file frontend & rute URL).
- Komentar kode: Bahasa Indonesia dipersilakan, dan lebih disukai untuk menjelaskan **kenapa**, bukan **apa**.

## 2. Dokumen adalah sumber kebenaran

Folder [`docs/`](docs/README.md) berisi seluruh keputusan proyek. **Baca yang relevan sebelum menulis kode.**

| Jenis task | Dokumen wajib |
|---|---|
| Menambah/mengubah fitur atau scope | [`01-prd.md`](docs/01-prd.md) |
| Tabel, kolom, migrasi, relasi | [`02-data-model.md`](docs/02-data-model.md) |
| Endpoint, request/response, validasi | [`03-api-spec.md`](docs/03-api-spec.md) |
| **Apa pun** yang menyentuh auth, upload, data pribadi, barcode, unduhan | [`04-security.md`](docs/04-security.md) — **wajib, tanpa kecuali** |
| Halaman, komponen, warna, layout nametag | [`05-frontend-spec.md`](docs/05-frontend-spec.md) |
| Server, deploy, domain, backup, rollback | [`06-deployment.md`](docs/06-deployment.md) |
| Urutan kerja & prioritas | [`07-timeline.md`](docs/07-timeline.md) |

- Jangan berimprovisasi arsitektur, tabel, library, atau endpoint baru di luar dokumen. Kalau memang perlu
  menyimpang → **diskusikan dengan user dulu**, lalu **perbarui dokumennya di commit yang sama**.
  Dokumen tidak boleh jadi usang diam-diam.
- Cek [`docs/README.md`](docs/README.md) bagian "Status Proyek" untuk tahu fase saat ini. Jangan kerjakan
  task fase lanjutan sebelum exit criteria fase sebelumnya terpenuhi, kecuali user minta eksplisit.

## 3. Stack dikunci — jangan ganti di tengah jalan

| Lapis | Yang dipakai | Catatan |
|---|---|---|
| Frontend | **Next.js (App Router) + TypeScript** | SSR/SSG untuk landing (SEO), client component untuk dashboard & scan |
| Styling | **Tailwind CSS** + token warna UNINUS | token ada di [`05-frontend-spec.md`](docs/05-frontend-spec.md) §2 |
| Animasi | **GSAP** (+ ScrollTrigger) dan **Lenis** | disetujui user 22 Sep 2026. **Hanya untuk landing page publik.** Aturan lengkap di [`05-frontend-spec.md`](docs/05-frontend-spec.md) §9 |
| Backend | **Laravel (PHP)** | API JSON saja, tidak me-render Blade untuk publik |
| Database | **PostgreSQL** | bukan MySQL — perhatikan perbedaan tipe data dan `ILIKE` |
| Auth admin | **Laravel Sanctum** | cookie session same-origin, lihat [`04-security.md`](docs/04-security.md) §1 |
| Server | **VPS sendiri, akses root SSH** | Nginx reverse proxy, lihat [`06-deployment.md`](docs/06-deployment.md) |

- **Dilarang** menambah library/framework baru (state manager, UI kit lain, ORM lain, queue eksternal)
  tanpa persetujuan user. Tanya dulu.
- **Dilarang** mengganti PostgreSQL ke MySQL/SQLite di produksi. SQLite hanya boleh untuk test lokal.
- Versi persis tiap dependensi **diisi setelah instalasi nyata**, bukan ditebak — lihat §4.

## 4. Jangan menebak — verifikasi

Tiga hal yang **paling sering ditebak dan paling mahal kalau salah** di proyek ini:

1. **Nama kolom file Excel dari PMB.** Skema di [`02-data-model.md`](docs/02-data-model.md) §2 adalah
   **rancangan**, bukan final. Sebelum menulis importer, **buka file Excel aslinya** dan cocokkan nama
   header sebenarnya. Kalau meleset → perbarui dokumen skema, jangan diam-diam menyimpang.
   Gunakan skill `anthropic-skills:xlsx` untuk membaca file itu.
2. **Versi dan API library.** Jangan tulis kode Laravel/Next dari ingatan. Setelah `composer create-project`
   dan `create-next-app` dijalankan, **catat versi persisnya** di [`06-deployment.md`](docs/06-deployment.md) §2
   dan ikuti API versi itu.
3. **Data resmi:** tanggal dan jam PKKMB, nama pejabat, nomor WA panitia, lokasi, tema. **Jangan dikarang.**
   Pakai placeholder bertanda `TODO:` dan daftarkan di [`01-prd.md`](docs/01-prd.md) §8.
   Ini situs resmi universitas — informasi palsu yang lolos ke produksi adalah kegagalan, bukan detail kecil.

## 5. Keamanan dan data pribadi — aturan keras

Proyek ini menyimpan **data pribadi sekitar 550 mahasiswa baru** (NIM, nama, prodi, kemungkinan tanggal
lahir dan kontak). Perlakukan seperti itu.

- **Jangan pernah commit data mahasiswa asli** — tidak file Excel PMB, tidak dump database, tidak
  screenshot berisi NIM dan nama. `.gitignore` wajib menutup `*.xlsx`, `*.csv`, `storage/app/imports/`, `*.sql`.
- Password admin **wajib di-hash** (bcrypt/argon2 bawaan Laravel). Tidak ada password polos di seeder
  yang di-commit, tidak ada di source code, tidak ada di dokumen.
- **Jangan kirim kredensial atau data pribadi lewat query string GET.** NIM dicari lewat `POST`, bukan `?nim=`.
- Semua query lewat **Eloquent/query builder** (prepared statement). Tidak ada string SQL yang dirangkai
  dari input.
- Semua endpoint yang mengubah state wajib terlindungi **CSRF** (same-origin) dan otorisasi per peran.
- Endpoint publik wajib **rate limit**. Lihat [`04-security.md`](docs/04-security.md) §2.
- Unduhan nametag/QR hanya lewat **signed URL berumur pendek**, bukan path file yang bisa ditebak.
- `.env` **tidak pernah** di-commit. `APP_KEY`, kredensial DB, dan secret lain hanya ada di server.
- **Setiap task yang menyentuh auth, upload, barcode, atau unduhan wajib membaca**
  [`04-security.md`](docs/04-security.md) lebih dulu, dan dijalankan lewat skill `security-review`
  sebelum dianggap selesai.

## 6. Scope guard — jangan menambah sendiri

Fitur berikut **sengaja tidak masuk** ([`01-prd.md`](docs/01-prd.md) §6). Kalau ada task yang memintanya,
**berhenti dan tanya user dulu** — itu mengubah sifat proyek dan jadwalnya:

- ❌ Login/akun untuk mahasiswa baru (maba **tanpa login**, hanya cari NIM)
- ❌ Pembayaran, sertifikat otomatis, e-voting, kuesioner
- ❌ Integrasi SSO / SIAKAD / PMB via API (data masuk lewat impor Excel)
- ❌ Aplikasi mobile native
- ❌ Multi-bahasa (Bahasa Indonesia saja)
- ❌ Upload foto oleh mahasiswa

Deadline proyek ini **sangat ketat** (lihat [`07-timeline.md`](docs/07-timeline.md)). Menambah fitur di
luar daftar MUST berarti menambah risiko gagal di hari-H. Katakan itu terus terang ke user, jangan
diam-diam mengerjakannya.

## 7. Penggunaan skill (khusus Claude Code)

Gunakan skill yang **benar-benar tersedia di sesi** — jangan mengarang nama skill. Kalau user menyebut
skill yang tidak ada, **katakan terus terang** dan tawarkan padanan terdekat.

| Kapan | Skill |
|---|---|
| Keputusan arsitektur lintas stack, menggali requirement | `anthropic-skills:cs-fullstack-engineer` |
| Laravel API, skema DB, auth, query | `anthropic-skills:senior-backend` |
| Next.js, React, Tailwind, komponen, aksesibilitas | `anthropic-skills:senior-frontend` |
| Membaca/memverifikasi file Excel PMB | `anthropic-skills:xlsx` |
| Threat model dan keputusan keamanan (barcode, data pribadi publik) | `anthropic-skills:senior-security` |
| Audit kerentanan sebelum go-live | `anthropic-skills:security-pen-testing` |
| VPS, Nginx, PM2, SSL, deploy, backup | `anthropic-skills:senior-devops` |
| Menulis test (unit/E2E) | `anthropic-skills:senior-qa` |
| Grafik/statistik di dashboard admin | `dataviz` |
| Review setelah satu fase selesai | `code-review` |
| **Wajib** sebelum go-live | `security-review` lalu `anthropic-skills:ship-gate` |
| Menjalankan dan melihat aplikasi berjalan sungguhan | `run` |
| Catatan keputusan arsitektur (ADR) | `anthropic-skills:senior-architect` |

## 8. Gaya kerja

- Selesaikan task sampai tuntas. Kalau ada bagian yang terblokir, kerjakan sisanya dan **sebutkan
  eksplisit** apa yang tidak dikerjakan dan kenapa.
- **Jangan menghapus atau menimpa file yang belum dibaca.**
- Jangan klaim sesuatu "sudah jalan" tanpa menjalankannya. Kalau test gagal, katakan gagal beserta
  outputnya.
- Commit kecil dan sering, pesan commit Bahasa Indonesia, format: `tipe: ringkasan`
  (`feat:`, `fix:`, `docs:`, `refactor:`, `chore:`). Jangan commit perubahan besar langsung ke `main`.
- Kalau menemukan masalah nyata pada permintaan user, **sampaikan sekali dengan jelas**, lalu kerjakan
  keputusan user. Jangan mengulang keberatan yang sama.
