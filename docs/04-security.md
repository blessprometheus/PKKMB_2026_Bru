# 04 — Keamanan & Privasi

**Versi dokumen:** 1.0.0 · 22 September 2026

> **Dokumen ini wajib dibaca sebelum menyentuh apa pun yang berkaitan dengan autentikasi, upload,
> data pribadi, barcode, atau unduhan.** Tanpa kecuali. Lihat [`../AGENTS.md`](../AGENTS.md) §5.

---

## 0. Yang Dilindungi

Sistem ini menyimpan **data pribadi ± 550 mahasiswa baru**: NIM, nama lengkap, fakultas, program studi,
dan kemungkinan tanggal lahir, nomor HP, email, serta pas foto. Ditambah **catatan kehadiran**, yang
adalah dokumen resmi universitas.

Dua ancaman terbesar proyek ini **bukan** peretas dari luar, melainkan:

1. **Penyapuan data** — seseorang mencoba ribuan NIM berurutan di endpoint publik untuk mengumpulkan
   daftar seluruh mahasiswa baru.
2. **Titipan absen** — mahasiswa mencetak QR temannya agar temannya tercatat hadir tanpa datang.

Keduanya dibahas di §2 dan §3. Sisanya adalah kebersihan dasar yang tetap wajib.

---

## 1. Autentikasi & Otorisasi Admin

| Aturan | Rinci |
|---|---|
| Hash kata sandi | `bcrypt`/`argon2id` bawaan Laravel. **Tidak pernah** ada kata sandi polos di source code, seeder yang di-commit, atau dokumen |
| Panjang minimum | 12 karakter untuk akun `admin`, 10 untuk `operator` |
| Sesi | Cookie `HttpOnly`, `Secure`, `SameSite=Lax`; kedaluwarsa 8 jam; diperbarui saat aktif |
| Percobaan login | Maks 5/menit per IP; akun terkunci 15 menit setelah 8 kegagalan berturut-turut |
| Pesan gagal | Selalu `"Email atau kata sandi salah."` — jangan bocorkan email mana yang terdaftar |
| Peran | `admin` = penuh. `operator` = **hanya** `/scan` dan endpoint di [`03-api-spec.md`](03-api-spec.md) §5 |
| Otorisasi | Diperiksa di **middleware + Policy Laravel**, bukan hanya disembunyikan di UI |

**Kenapa operator dibatasi:** akun petugas dipakai di laptop terbuka, di aula, kadang bergantian orang.
Kalau akun itu bisa membuka daftar lengkap 550 mahasiswa beserta nomor HP-nya, satu laptop yang
ditinggal terbuka sudah cukup untuk kebocoran. Petugas hanya perlu melihat nama orang yang baru dipindai.

Akun pertama dibuat lewat **perintah artisan interaktif di server**, bukan seeder berisi kata sandi
yang di-commit.

---

## 2. Endpoint Lookup Publik — Data Pribadi Tanpa Login

Maba mencari dirinya **hanya dengan NIM**, tanpa login. Ini keputusan user (22 September 2026), diambil
demi kemudahan. Konsekuensinya harus dipahami:

**NIM biasanya berurutan.** Siapa pun yang tahu satu NIM bisa menebak ribuan lainnya. Tanpa pembatasan,
endpoint ini adalah daftar seluruh mahasiswa baru yang bisa diunduh siapa saja.

Pembatasan yang **wajib** ada:

1. **Rate limit berlapis:** 10 permintaan/menit **dan** 40/jam per IP. Melebihi → 429 dengan
   `Retry-After`.
2. **Minimisasi field.** Response hanya memuat: `nim`, `name`, `faculty`, `study_program`,
   `group_name`, status kehadiran, dan URL unduhan.
   **Dilarang** mengembalikan `birth_date`, `phone`, `email`, `attendance_token`, `id`.
   Ini pertahanan terkuat di sini: kalaupun data disapu, yang didapat hanya informasi yang toh tercetak
   di nametag dan terlihat semua orang di lokasi acara.
3. **Metode POST**, bukan GET — NIM tidak boleh masuk log akses Nginx, riwayat browser, atau `Referer`.
4. **Pesan 404 seragam** — tidak membedakan sebab.
5. **Pencatatan di `lookup_attempts`** (NIM di-hash) untuk mendeteksi penyapuan. Kalau satu IP mencari
   > 50 NIM berbeda dalam sejam, itu bukan mahasiswa yang lupa NIM-nya.
6. **Tanpa autocomplete/saran NIM.** Jangan pernah membuat endpoint yang menerima NIM sebagian.

**Yang dilakukan kalau ternyata ada penyapuan saat acara:** admin bisa menurunkan batas atau
mengaktifkan sementara verifikasi tambahan (tanggal lahir) lewat konfigurasi, tanpa deploy ulang.
Sediakan flag `LOOKUP_REQUIRE_BIRTHDATE=false` di `.env` sejak awal supaya opsi itu ada saat dibutuhkan.

---

## 3. Barcode Presensi & Titipan Absen

### 3.1 Kenapa QR berisi token acak, bukan NIM

Isi QR adalah `attendance_token` — 32 karakter acak kriptografis
([`02-data-model.md`](02-data-model.md) §4).

Kalau QR berisi NIM, siapa pun bisa membuat QR palsu seluruh angkatan di generator QR online, tanpa
pernah menyentuh sistem kita — tidak terpantau, tidak terbatasi, tidak bisa dicabut.
Dengan token acak, QR yang sah **hanya bisa diperoleh lewat endpoint kita**, sehingga setiap
pengambilan bisa dibatasi, dicatat, dan dibatalkan.

### 3.2 Yang TIDAK diselesaikan token — katakan terus terang

Token **tidak mencegah titipan absen.** Karena lookup hanya butuh NIM, seorang mahasiswa bisa
memasukkan NIM temannya, mengunduh QR-nya, mencetaknya, dan memindaikannya di pintu masuk. Temannya
tercatat hadir tanpa datang.

Ini **melekat pada alur tanpa login** yang dipilih user. Tidak ada tambalan teknis yang
menghilangkannya sepenuhnya. Yang bisa dilakukan adalah membuatnya **mahal, terlihat, dan berisiko
ketahuan**:

| # | Mitigasi | Status |
|---|---|---|
| L1 | **QR dicetak menyatu di nametag, bukan terpisah.** Yang ditunjukkan ke petugas adalah nametag utuh berisi nama besar | ✅ wajib, Fase 3 |
| L2 | **Pas foto di nametag** (kalau PMB menyediakan). Petugas mencocokkan wajah — ini mitigasi paling efektif | ⬜ tergantung D5/ketersediaan foto |
| L3 | **Satu orang tidak bisa menunjukkan dua nametag** tanpa terlihat. Petugas diberi tahu untuk menolak orang yang memindai lebih dari satu | ✅ masuk briefing petugas, H-1 |
| L4 | **Duplikat ditolak database.** Kalau yang dititipi sudah dipindai lalu orangnya datang sendiri, layar kuning muncul dan ketahuan | ✅ wajib, batasan UNIQUE |
| L5 | **Semua pengambilan QR dicatat** (`lookup_attempts`). Satu IP yang mengambil 30 QR berbeda bisa ditelusuri | ✅ wajib |
| L6 | **Scan di luar jendela waktu sesi ditolak** — tidak bisa "mengabsenkan" setelah acara selesai | ✅ wajib |
| L7 | **Token bisa dicabut** per mahasiswa kalau terbukti disalahgunakan | ✅ wajib, `rotate-token` |

> **Keputusan yang masih terbuka untuk user:** kalau panitia ingin titipan absen benar-benar ditekan,
> caranya bukan menambah lapisan teknis di sistem ini, melainkan **nametag berfoto + petugas
> mencocokkan wajah**. Itu keputusan operasional, bukan keputusan kode. Sampaikan ini ke panitia
> sebelum hari-H.

### 3.3 Aturan teknis QR

- QR dibuat **server-side**, tidak pernah di browser — supaya token tidak pernah ada di JavaScript klien.
- Ukuran PNG minimal 600×600 px, error correction level **M** atau lebih tinggi, dengan *quiet zone*
  minimal 4 modul. QR yang dicetak kecil atau terpotong tidak terbaca scanner, dan itu akan terjadi
  di pintu masuk dengan antrean di belakangnya.
- QR di nametag minimal **3 × 3 cm** saat dicetak.
- Token **tidak pernah** muncul sebagai teks di halaman, di URL, di nama file, atau di response JSON.

---

## 4. Unduhan Nametag & QR

- Hanya lewat **signed URL berumur 15 menit** (`URL::temporarySignedRoute`).
- **Tidak boleh** ada file nametag/QR tersimpan di webroot dengan nama yang bisa ditebak
  (`/uploads/nametag/20260012345.pdf` = seluruh angkatan bisa diunduh dengan skrip sederhana).
  Berkas dibuat saat diminta, atau disimpan di `storage/app/private/` dan dialirkan lewat controller.
- Nama file unduhan boleh memuat NIM (`nametag-20260012345.pdf`) — itu di perangkat maba sendiri.
- Tanda tangan kedaluwarsa → 403 dengan pesan Bahasa Indonesia yang jelas, bukan error mentah.

---

## 5. Upload File Excel

### 5.1 Validasi berkas

- Ekstensi diizinkan: `.xlsx`, `.xls`, `.csv`. Ukuran maks **10 MB**.
- Validasi **MIME asli** (`finfo`), bukan hanya ekstensi atau `Content-Type` dari klien.
- Disimpan di `storage/app/imports/` — **di luar webroot**, tidak bisa diakses lewat URL.
- Nama file disimpan ulang dengan nama acak; nama asli hanya dicatat di database.
- Direktori penyimpanan **tidak boleh** mengeksekusi apa pun. Nginx tidak melayani `storage/` sama sekali.
- Hanya `role = admin` yang boleh mengunggah.

### 5.2 Pemrosesan

- Pemetaan kolom berdasarkan **nama header**, bukan urutan ([`02-data-model.md`](02-data-model.md) §5).
- Setiap nilai dibersihkan: `trim`, buang karakter kontrol, batasi panjang sesuai kolom.
- Berkas besar diproses lewat **chunk**, bukan dimuat seluruhnya ke memori.
- Kegagalan parsing ditangani dan dilaporkan, tidak menampilkan jejak error ke layar admin.

### 5.3 CSV/Excel injection pada **ekspor** — jangan dilewatkan

Rekap kehadiran diekspor ke Excel lalu dibuka panitia. Kalau ada nilai yang diawali `=`, `+`, `-`, atau
`@`, Excel akan menganggapnya **formula** dan menjalankannya di komputer panitia.

Aturan: setiap nilai teks yang akan diekspor dan diawali salah satu karakter itu **wajib diberi
awalan petik tunggal** (`'`) atau dipaksa bertipe teks. Ini berlaku untuk **semua** ekspor —
mahasiswa, kehadiran, laporan error impor.

---

## 6. Input, Query, dan Validasi

- Semua query lewat **Eloquent / query builder**. Tidak ada string SQL yang dirangkai dari input.
  `whereRaw` hanya boleh dengan parameter binding, dan harus punya alasan yang ditulis di komentar.
- Setiap endpoint punya **FormRequest** dengan aturan validasi eksplisit dan pesan Bahasa Indonesia.
- `nim` divalidasi sebagai string beranggota `[0-9A-Za-z.\-]` dengan panjang maks 30.
  Jangan pernah menganggapnya integer — Excel membuang nol di depan.
- Paginasi `per_page` dibatasi maks 100, supaya tidak ada yang meminta 100.000 baris sekaligus.
- Output di frontend di-escape oleh React secara default. **Jangan** pakai `dangerouslySetInnerHTML`
  untuk data apa pun yang berasal dari database.

---

## 7. CSRF, CORS, dan Header

- Frontend dan API **same-origin** (Nginx meneruskan `/api/*` ke Laravel). Dengan begitu CORS tidak
  perlu dilonggarkan sama sekali. Jangan setel `Access-Control-Allow-Origin: *`.
- Semua permintaan yang mengubah state wajib membawa CSRF token Sanctum.
- Header keamanan di Nginx:

```
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'; img-src 'self' data:; frame-src https://www.youtube-nocookie.com; object-src 'none'; base-uri 'self'
```

> ⚠️ **CSP di atas belum bisa ditegakkan — sementara dipasang `Content-Security-Policy-Report-Only`.**
> Diverifikasi 24 September 2026 dari hasil `next build`: HTML Next.js App Router memuat `<script>`
> inline (`self.__next_f.push(...)`) untuk hidrasi. `script-src 'self'` yang ditegakkan memblokirnya,
> dan form cari NIM, `/admin`, serta `/scan` mati total. Jalan keluar yang benar: **nonce per
> permintaan lewat `proxy.ts`** (belum dikerjakan). Checklist butir 15 belum boleh dianggap lolos
> penuh sampai CSP ditegakkan.

CSP disesuaikan setelah tahu skrip apa saja yang benar-benar dipakai — **jangan** melonggarkannya
dengan `unsafe-inline` hanya supaya cepat jalan; catat kalau terpaksa dan perbaiki sebelum go-live.

> ⚠️ **`Referrer-Policy` tidak boleh disetel `no-referrer`.** Ditemukan saat uji rantai penuh
> (22 September 2026): Sanctum hanya menyalakan sesi bila host di header `Origin` **atau** `Referer`
> cocok dengan `SANCTUM_STATEFUL_DOMAINS`. Browser tidak mengirim `Origin` pada permintaan `GET`
> same-origin — yang dikirim hanya `Referer`. Dengan `no-referrer`, keduanya hilang, seluruh
> permintaan `GET` dianggap tidak bersesi, dan **panitia yang sudah masuk tetap mendapat 401**.
>
> `strict-origin-when-cross-origin` (nilai yang dipakai di atas) aman: untuk permintaan same-origin
> ia tetap mengirim `Referer` lengkap.

---

## 8. Secret & Konfigurasi

- `.env` **tidak pernah** di-commit. `.env.example` berisi nama variabel saja, tanpa nilai nyata.
- `APP_DEBUG=false` dan `APP_ENV=production` di server. Ini satu baris yang, kalau salah, menampilkan
  kredensial database di halaman error.
- Kredensial PostgreSQL: user khusus aplikasi, **bukan** superuser `postgres`, hanya punya hak pada
  database proyek ini.
- PostgreSQL hanya mendengarkan `localhost` — tidak diekspos ke internet.
- Kalau ada kunci yang pernah ter-commit tidak sengaja: **ganti kuncinya**, jangan cukup menghapus commit.

---

## 9. Log & Audit

- **Jangan menulis data pribadi ke log aplikasi.** Catat `student_id`, bukan nama dan NIM.
- `scan_logs` menyimpan **hash** input mentah, bukan tokennya — supaya log tidak menjadi daftar QR
  yang bisa dipakai ulang.
- `audit_logs` mencatat aksi admin yang mengubah/menghapus data, termasuk penghapusan kehadiran.
- Log akses Nginx tidak boleh memuat NIM — inilah salah satu alasan lookup memakai POST.
- Rotasi log aktif; `scan_logs` dan `lookup_attempts` dibersihkan setelah 30 hari.

---

## 10. Data Pribadi di Repositori & Perangkat

- `.gitignore` **wajib** memuat minimal:

```
.env
*.xlsx
*.xls
*.csv
*.sql
*.dump
storage/app/imports/
storage/app/private/
public/uploads/
```

- Jangan menaruh file Excel PMB di repo, di folder proyek yang ter-commit, atau di chat/issue.
- Dump database untuk keperluan uji **wajib disamarkan** (nama dan NIM diganti) sebelum dipakai di lokal.
- Laptop petugas: akun `operator`, sesi kedaluwarsa 8 jam, dan diberi tahu untuk logout setelah acara.

---

## 11. Checklist Sebelum Go-Live

Semua harus ✅ sebelum sistem dipakai untuk data asli. Jalankan `security-review` lalu
`anthropic-skills:ship-gate`.

> **Status per 22 September 2026:** ✅ = diuji/diverifikasi (otomatis atau manual). 🟡 = siap
> tapi belum diterapkan/diuji di server sungguhan. ⬜ = butuh akses VPS untuk bisa dikerjakan
> sama sekali. Rincian lengkap ada di [`../deploy/README.md`](../deploy/README.md).

| # | Butir | Status | Keterangan |
|---|---|---|---|
| 1 | `APP_DEBUG=false`, `APP_ENV=production` | 🟡 | `.env.example` sudah production-safe; belum diterapkan di server sungguhan — butuh akses VPS |
| 2 | `.env` tidak ada di git history; tidak ada kata sandi polos di repo | ✅ | Diperiksa `git log --all -p` — nihil |
| 3 | Akun admin pertama dibuat lewat artisan di server, kata sandi kuat, bukan dari seeder | 🟡 | Perintah `pkkmb:create-admin` ada & teruji (Fase 1); belum dijalankan "di server" sungguhan |
| 4 | HTTPS aktif, HTTP dialihkan 301, HSTS terpasang | ⬜ | `deploy/nginx/pkkmb26.conf` sudah menyiapkan ini; butuh domain + VPS untuk certbot |
| 5 | Rate limit lookup **diuji** (kirim 15 permintaan → dapat 429) | ✅ | Diuji otomatis (10/menit) **dan** independensi batas 40/jam (test baru Fase 6, simulasi 4 jendela lewat `travel()`) |
| 6 | Response lookup **diperiksa manual** — tidak ada `phone`, `email`, `birth_date`, `attendance_token` | 🟡 | Diuji otomatis terhadap data uji (Fase 3); pemeriksaan manual dengan **NIM mahasiswa asli** tetap wajib sekali di server sebelum go-live |
| 7 | URL unduhan diuji: tanda tangan dibuang/diubah → 403; ditunggu 16 menit → 403 | ✅ | Termasuk uji menukar id mahasiswa di URL → 403 (Fase 3) |
| 8 | Endpoint `/scan` menolak permintaan tanpa login (uji dengan `curl` polos) | ✅ | **Butir ini yang menemukan bug nyata di Fase 6** — lihat `deploy/README.md`. `curl` tanpa header `Accept` sempat mendapat 500 mentah, sekarang 401 bersih & dikunci test regresi |
| 9 | Akun `operator` diuji **tidak bisa** membuka daftar mahasiswa & impor (403, bukan sekadar tersembunyi) | ✅ | Fase 2 |
| 10 | Duplikat scan diuji: pindai orang yang sama dua kali → `duplicate`, bukan dua baris | ✅ | Termasuk 10 tembakan beruntun (Fase 4) |
| 11 | Scan di luar jendela waktu sesi → ditolak | ✅ | Fase 4, termasuk regresi bug zona waktu |
| 12 | Ekspor Excel diuji dengan nama berisi `=cmd` → tidak menjadi formula | ✅ | `=cmd\|calc`, `+1+1`, `-SUM()` diperiksa langsung di berkas `.xlsx` hasil ekspor (Fase 4) |
| 13 | Upload diuji: file `.php` disamarkan `.xlsx` → ditolak; file 20 MB → ditolak | ✅ | Disamarkan `.xlsx` sejak Fase 2; ukuran berlebih + batas tepat 10 MB ditambahkan Fase 6 |
| 14 | PostgreSQL tidak bisa diakses dari luar server (`nmap`/`psql` dari luar gagal) | ⬜ | Butuh VPS + `nmap` dari komputer lain — lihat `deploy/scripts/verify-security.sh` |
| 15 | Header keamanan terpasang (cek di browser devtools) | 🟡 | Konfigurasi siap di `deploy/nginx/pkkmb26.conf` (sintaks tervalidasi `crossplane`), belum aktif di server sungguhan |
| 16 | Backup database otomatis jalan, dan **hasilnya sudah pernah dipulihkan sekali** untuk dibuktikan | ⬜ | Skrip siap (`deploy/backup/`), belum pernah dijalankan+dipulihkan sungguhan |
| 17 | Log aplikasi diperiksa — tidak ada NIM/nama di dalamnya | ✅ | Diperiksa manual terhadap `storage/logs/laravel.log` (Fase 6) |
