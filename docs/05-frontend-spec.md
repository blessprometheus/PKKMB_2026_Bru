# 05 — Frontend (Next.js)

**Versi dokumen:** 1.0.0 · 22 September 2026

---

## 1. Struktur Rute (App Router)

| Rute | Render | Akses | Isi |
|---|---|---|---|
| `/` | SSG/ISR | publik | Landing: hero + countdown, **kotak cari NIM**, informasi, jadwal, tata tertib, video, FAQ, kontak |
| `/cari` | Client | publik | Halaman hasil pencarian NIM + tombol unduh (juga bisa tampil sebagai bagian `/`) |
| `/admin/login` | Client | publik | Form login panitia |
| `/admin` | Client | admin | Dashboard statistik |
| `/admin/mahasiswa` | Client | admin | Tabel, cari, filter, tambah/edit/hapus |
| `/admin/mahasiswa/impor` | Client | admin | Unggah Excel + laporan hasil |
| `/admin/sesi` | Client | admin | Kelola sesi presensi |
| `/admin/presensi` | Client | admin | Rekap kehadiran per sesi + ekspor |
| `/admin/akun` | Client | admin | Kelola akun panitia & petugas |
| `/scan` | Client | admin/operator | **Halaman pemindaian** — lihat §4 |
| `/404` | Static | publik | Halaman tidak ditemukan, bergaya sama |

Landing (`/`) di-render di server agar SEO dan waktu muat di jaringan seluler baik.
Dashboard dan `/scan` adalah client component — tidak perlu SEO, butuh interaktivitas.

Rute `/admin/*` dan `/scan` dilindungi **`proxy.ts` Next.js** (cek sesi) **dan** otorisasi di Laravel.

> ⚠️ **Next.js 16: `middleware.ts` sudah diganti `proxy.ts`.** Nama berkas **dan** nama fungsi yang
> diekspor keduanya `proxy`, dan runtime-nya hanya `nodejs` (tidak bisa `edge`). Diverifikasi dari
> `node_modules/next/dist/docs/01-app/02-guides/upgrading/version-16.md`. Jangan menulis
> `middleware.ts` dari kebiasaan lama.
>
> Di Next.js 16 `cookies()`, `headers()`, `params`, dan `searchParams` **wajib di-`await`** — akses
> sinkron sudah dihapus sepenuhnya, bukan sekadar deprecated.
Perlindungan di frontend hanya untuk pengalaman pengguna; yang mengamankan adalah backend
([`04-security.md`](04-security.md) §1).

---

## 2. Design Token

Diwarisi dari `Desktop/pkkmb/docs/03-design-system.md` (palet diverifikasi dari CSS variable
uninus.ac.id, 2 Juli 2026). Disalin ke sini agar proyek ini mandiri.

```css
:root {
  --pk-primary:        #008F4F;
  --pk-primary-dark:   #006F3C;
  --pk-primary-light:  #00B060;
  --pk-secondary:      #FBC02D;
  --pk-secondary-dark: #E0A800;

  --pk-bg:        #F8F9FA;
  --pk-surface:   #FFFFFF;
  --pk-surface-2: #F1F3F5;
  --pk-text:      #212529;
  --pk-muted:     #6C757D;
  --pk-border:    #E9ECEF;

  --pk-success: #008F4F;   /* TERCATAT */
  --pk-warning: #DD6B20;   /* SUDAH ABSEN */
  --pk-danger:  #E53E3E;   /* QR TIDAK DIKENAL */
  --pk-info:    #3182CE;

  --pk-gradient: linear-gradient(135deg, #008F4F, #00B060);
}

[data-theme="dark"] {
  --pk-bg:        #121212;
  --pk-surface:   #1E1E1E;
  --pk-surface-2: #252525;
  --pk-text:      #E9ECEF;
  --pk-muted:     #ADB5BD;
  --pk-border:    #343A40;

  --pk-primary:       #12B866;   /* sengaja BUKAN #008F4F — gagal kontras di atas #121212 */
  --pk-primary-dark:  #0E9A55;
  --pk-primary-light: #34D27F;
  --pk-secondary:     #FFD24D;
  --pk-danger:        #FF6B6B;
  --pk-warning:       #F6A15C;
}
```

**Aturan pakai:**
- `#008F4F` di atas putih = kontras 4.6:1 → aman untuk teks.
- `#FBC02D` (emas) **dilarang untuk teks biasa** — kontrasnya rendah. Hanya latar badge (dengan teks
  gelap), garis aksen, dan sorotan.
- Teks di atas gradient hijau selalu putih.
- **Jangan menyeragamkan** hijau terang & gelap. Perbedaannya disengaja.
- Warna **tidak boleh** jadi satu-satunya pembeda makna — selalu sertai ikon atau teks.

Token dipasang sebagai CSS variable dan dipetakan ke Tailwind lewat `tailwind.config`, supaya bisa
ditulis `bg-pk-primary` tanpa kehilangan kemampuan berganti tema.

**Tipografi:** Plus Jakarta Sans (Google Fonts), dimuat lewat `next/font` agar tidak ada
pergeseran layout.

---

## 3. Halaman Publik — Cari NIM

Ini pintu masuk maba. Harus **ringan dan jelas**, dipakai di HP kelas bawah dengan jaringan seluler.

**Alur di layar:**

```
[ Kolom: Masukkan NIM Anda ]  [ Cari ]
        ↓ ditemukan
┌─────────────────────────────────────┐
│ Nama Mahasiswa                      │
│ 20260012345                         │
│ Fakultas Teknik · Teknik Informatika│
│ Gugus 3                             │
│                                     │
│ Kehadiran:                          │
│  Hari 1 — Pembukaan   ✅ Hadir 08:14│
│  Hari 2 — Materi      ⬜ Belum      │
│                                     │
│ [ Unduh Nametag (PDF) ]             │
│ [ Unduh QR Presensi (PNG) ]         │
└─────────────────────────────────────┘
```

Ketentuan:

- `inputMode="numeric"` agar papan ketik angka muncul di HP, tapi field tetap **teks**
  (NIM bisa berawalan nol).
- Tombol "Cari" dinonaktifkan saat permintaan berjalan; tampilkan indikator muat.
- **Pesan error wajib Bahasa Indonesia yang membantu**, bukan kode:
  - tidak ditemukan → *"NIM tidak ditemukan. Pastikan NIM sesuai yang diberikan bagian akademik."*
  - kena rate limit (429) → *"Terlalu banyak percobaan. Coba lagi dalam beberapa menit."*
  - gagal jaringan → *"Gagal terhubung. Periksa koneksi Anda lalu coba lagi."*
- **Jangan menyimpan hasil pencarian di `localStorage`.** HP sering dipinjam-pakai; data pribadi tidak
  perlu tertinggal di browser.
- Di bawah hasil, tampilkan petunjuk singkat: *"Cetak nametag ini dan bawa saat PKKMB. QR di nametag
  dipindai panitia untuk mencatat kehadiran Anda."*
- Ada tautan "Tidak menemukan data Anda?" → menuju kontak panitia.

---

## 4. Halaman `/scan` — Yang Paling Kritis

Halaman ini dipakai **di bawah tekanan**: antrean panjang, aula ramai, petugas yang baru diajari
lima menit sebelumnya. Kalau satu halaman harus benar-benar mulus, ini halamannya.

### 4.1 Cara kerja scanner gun

Scanner gun 2D/imager berperilaku seperti **papan ketik**: ia mengetikkan isi QR ke elemen yang sedang
fokus, lalu biasanya menekan Enter. Jadi halaman ini **tidak memakai kamera** — cukup satu `<input>`.

Konsekuensi yang wajib ditangani:

1. **Fokus harus selalu di kolom input.** Kalau petugas mengklik di tempat lain, fokus hilang dan
   tembakan berikutnya menghilang entah ke mana. Pasang pengembali fokus otomatis (`onBlur` → fokus
   ulang, dan interval pemeriksaan ringan).
2. **Jangan mengandalkan Enter saja.** Sebagian scanner tidak dikonfigurasi mengirim Enter.
   Kirim juga setelah jeda ± 80 ms tanpa ketikan baru.
3. **Cegah kirim ganda.** Kosongkan input segera setelah dikirim, dan abaikan kiriman identik
   dalam 1,5 detik.
4. **Token tidak boleh terbaca di layar.** Kolom input bergaya `-webkit-text-security` atau langsung
   dikosongkan — layar ini menghadap antrean.
5. Konfigurasi scanner (sufiks Enter, mode keyboard) **diuji dengan alat asli** di Fase 4 (D4).

### 4.2 Tampilan

```
┌──────────────────────────────────────────────┐
│ Sesi: Hari 1 — Pembukaan     Pintu: [Pintu A]│
│ Petugas: Budi          Terhubung ●           │
├──────────────────────────────────────────────┤
│                                              │
│            ✅  T E R C A T A T               │
│                                              │
│            Nama Mahasiswa                    │
│            20260012345                       │
│            Teknik Informatika · Gugus 3      │
│                                              │
├──────────────────────────────────────────────┤
│ 10 pemindaian terakhir                       │
│ 08:14  Nama A   ✅                           │
│ 08:14  Nama B   ⚠️ sudah absen 08:02         │
└──────────────────────────────────────────────┘
```

- Hasil ditampilkan **sebesar mungkin** — terbaca sambil berdiri, dari jarak satu meter.
- Warna + ikon + teks bersamaan (petugas buta warna tetap bisa bekerja).
- **Bunyi berbeda** untuk `recorded` (nada naik), `duplicate` (nada datar), `unknown_token` (nada turun).
  Di aula ramai, telinga lebih cepat daripada mata. Sediakan tombol bisukan.
- Indikator **"Terhubung"** selalu terlihat. Kalau permintaan gagal, statusnya berubah merah seketika —
  petugas harus tahu detik itu juga, bukan setelah 50 orang lewat.
- Tombol **"Input NIM Manual"** selalu ada untuk QR rusak / maba tanpa nametag
  ([`03-api-spec.md`](03-api-spec.md) §5.3).
- Daftar 10 pemindaian terakhir memberi bukti visual bahwa sistem masih hidup.

### 4.3 Ketahanan (SHOULD — S4)

Kalau waktu cukup: simpan pemindaian yang gagal terkirim ke antrean di memori/`sessionStorage`, kirim
ulang otomatis saat koneksi pulih, dan tampilkan jumlah antrean. Halaman **tidak boleh** menampilkan
"TERCATAT" untuk sesuatu yang masih mengantre — pakai keadaan ketiga: "MENGANTRE".

Kalau tidak sempat: halaman harus **gagal dengan jelas** (layar merah "GAGAL TERKIRIM — catat manual"),
bukan gagal diam-diam. Rencana cadangan kertas ada di [`07-timeline.md`](07-timeline.md) §6.

---

## 5. Dashboard Admin

- **Tabel mahasiswa:** paginasi server-side (maks 100/halaman), pencarian nama/NIM dengan *debounce*
  400 ms, filter fakultas & status kehadiran. Jangan memuat 550 baris sekaligus ke satu tabel.
- **Impor Excel:** area seret-dan-lepas, tampilkan nama file & ukuran sebelum kirim, indikator proses,
  lalu **ringkasan hasil** (berhasil/diperbarui/gagal) dengan tabel baris gagal yang menyebut nomor
  baris dan alasannya, plus tombol unduh daftar error.
- **Dashboard statistik:** kartu angka (total, hadir, belum hadir) + grafik. Untuk grafik apa pun,
  **muat skill `dataviz` sebelum menulis kode grafiknya.**
- **Rekap kehadiran:** pilih sesi → tabel → tombol ekspor Excel. Ekspor menyertakan yang **belum hadir**
  juga; itu justru yang paling dicari panitia.
- Setiap aksi merusak (hapus mahasiswa, hapus kehadiran) butuh **dialog konfirmasi** yang menyebut
  nama yang akan terpengaruh.

---

## 6. Layout Nametag (PDF)

> `TODO:` menunggu D3 (ketentuan desain resmi) dan D5 (logo). Yang di bawah adalah rancangan awal.

- Ukuran: **A6 potret (105 × 148 mm)**, satu nametag per halaman; tersedia juga tata letak
  **4-up di A4** untuk cetak massal panitia (S3).
- Susunan dari atas: logo UNINUS + logo PKKMB → judul "PKKMB UNINUS 2026" → **nama besar** →
  NIM → fakultas · program studi → kelompok → **QR minimal 3 × 3 cm** → ruang kosong untuk lubang tali.
- Pas foto (kalau tersedia) di kanan atas — ini mitigasi titipan absen L2
  ([`04-security.md`](04-security.md) §3.2).
- Nama panjang **dikecilkan otomatis**, tidak dipotong. Nama orang tidak boleh terpotong di tanda
  pengenal resmi.
- Cetak ramah tinta: latar putih, tanpa gradient penuh halaman.
- QR dibuat **server-side**; PDF juga dibuat server-side (Laravel), bukan di browser.

---

## 7. Aksesibilitas & Performa

- Target **WCAG 2.1 AA**: kontras teks ≥ 4.5:1 di kedua tema, navigasi keyboard penuh,
  `:focus-visible` terlihat, heading berurutan, landmark semantik.
- `prefers-reduced-motion: reduce` **wajib dihormati** di setiap animasi.
- Mobile-first, dirancang dari **360 px**. Tidak boleh ada gulir horizontal.
- Landing page: target LCP < 2,5 detik di 4G simulasi; gambar lewat `next/image`;
  video YouTube pakai *facade* klik-untuk-putar (`youtube-nocookie.com`).
- Halaman `/scan`: seringan mungkin, tanpa animasi berat — latensi terasa langsung di antrean.
- Setiap pesan status (`recorded`/`duplicate`/`unknown_token`) diumumkan lewat `aria-live="assertive"`.

---

## 8. Animasi — GSAP + Lenis

**Keputusan user, 22 September 2026.** GSAP (dengan ScrollTrigger) untuk animasi, Lenis untuk
*smooth scroll*. Ini menambah dua dependensi di luar rencana awal; aturan di bawah mengikat.

### 8.1 Batas pemakaian — keras

| Halaman | Animasi? |
|---|---|
| `/`, `/cari` (publik) | ✅ Ya |
| `/scan` | ❌ **Dilarang, tanpa kecuali** |
| `/admin/*` | ❌ Dilarang |

`/scan` dipakai sambil berdiri menghadapi antrean. *Smooth scroll* membajak gulir dan bisa merebut
fokus dari kolom input — padahal seluruh halaman itu bergantung pada fokus yang tidak pernah lepas
(§4.1). Di tabel admin, Lenis hanya membuat gulir terasa berat. Jangan dipasang di layout global;
pasang di layout grup rute publik saja.

### 8.2 `prefers-reduced-motion` — bukan opsional

- Kalau pengguna meminta gerak dikurangi, **Lenis tidak diinisialisasi sama sekali** — bukan sekadar
  durasi disetel 0. Gulir kembali ke perilaku asli browser.
- GSAP langsung menetapkan keadaan akhir (`gsap.set`), elemen tampil tanpa transisi.
- Alasannya medis, bukan selera: gulir yang dibajak memicu pusing pada pengguna dengan gangguan
  vestibular. Halaman ini wajib tetap terpakai penuh tanpa animasi.

### 8.3 Integrasi teknis yang wajib benar

- Keduanya **client-only**: `'use client'`, dimuat lewat `dynamic(..., { ssr: false })` agar tidak
  ikut render server dan tidak menunda HTML landing.
- Lenis **harus** disambungkan ke ScrollTrigger, kalau tidak ScrollTrigger salah menghitung posisi:
  `lenis.on('scroll', ScrollTrigger.update)` dan `gsap.ticker.add(t => lenis.raf(t * 1000))`,
  dengan `gsap.ticker.lagSmoothing(0)`.
- `html { scroll-behavior: smooth }` **wajib dimatikan** saat Lenis aktif — keduanya berebut kendali
  gulir dan hasilnya tersendat. Di Next.js 16 framework **tidak lagi** menimpa `scroll-behavior` saat
  navigasi, jadi konfliknya murni antara CSS dan Lenis. Jangan pula menambahkan
  `data-scroll-behavior="smooth"` pada `<html>` — atribut itu justru **menghidupkan kembali** penimpaan
  oleh Next.js dan akan bertabrakan dengan Lenis. (Diverifikasi dari `version-16.md` §Scroll Behavior
  Override.)
- Navigasi anchor (`#jadwal`, `#tata-tertib`) memakai `lenis.scrollTo()`, bukan lompatan bawaan browser.
  Navbar yang berubah saat gulir dihitung dari event Lenis, bukan event `scroll` bawaan.
- Bersihkan saat unmount: `lenis.destroy()` dan matikan seluruh ScrollTrigger. App Router me-mount
  ulang komponen saat berpindah rute; tanpa pembersihan, instansinya menumpuk.

### 8.4 Anggaran performa

Landing menargetkan LCP < 2,5 detik di 4G pada HP Android kelas bawah ([`01-prd.md`](01-prd.md) §7).

- Impor **hanya modul yang dipakai** (`gsap`, `gsap/ScrollTrigger`), bukan seluruh paket plugin.
- Animasi **tidak boleh menunda LCP**. Hero tampil lebih dulu dalam keadaan terbaca; animasi masuk
  menyusul setelah hidrasi.
- Hanya menganimasikan `transform` dan `opacity`. **Dilarang** menganimasikan `width`, `height`,
  `top`, `left`, atau `margin` — itu memicu perhitungan ulang tata letak di tiap frame.
- Kalau berat halaman awal melewati anggaran, **yang dipotong adalah animasinya**, bukan anggarannya.

### 8.5 Lisensi

Diverifikasi dari paket yang benar-benar terpasang pada 22 September 2026:

| Paket | Versi | Lisensi (dari `package.json`/`README.md` paket) |
|---|---|---|
| `gsap` | 3.15.0 | GreenSock standard "no charge" license — `https://gsap.com/standard-license` |
| `lenis` | 1.3.26 | MIT |

Paket `gsap` publik **sudah menyertakan** `ScrollTrigger.js`, `ScrollSmoother.js`, dan `SplitText.js`
(diperiksa langsung di `node_modules/gsap/`), jadi tidak perlu registry berbayar terpisah.

> `TODO:` isi teks lisensi GreenSock **belum dibaca** — yang diverifikasi baru rujukan URL-nya di paket.
> Sebelum go-live, buka `https://gsap.com/standard-license` dan pastikan pemakaian di situs resmi
> universitas (tidak menjual akses ke fitur animasinya) tercakup. Catat hasilnya di sini.
>
> **Keputusan yang sudah diambil:** kita memakai `ScrollTrigger` saja. `ScrollSmoother` **tidak dipakai**
> karena tumpang tindih dengan Lenis — dua mesin *smooth scroll* sekaligus akan saling berebut.

### 8.6 Harus bisa dicabut dalam hitungan menit

[`07-timeline.md`](07-timeline.md) §5 menempatkan animasi sebagai **hal pertama yang dipotong** kalau
presensi belum siap. Karena itu seluruh kode animasi hidup di **satu provider + satu berkas util**,
tidak disebar ke banyak komponen. Mencabutnya = melepas provider dan menghapus dua dependensi,
tanpa menyentuh markup section mana pun.

---

## 9. Yang Sengaja Tidak Dipakai

- ❌ Pemindaian via kamera browser (scanner gun sudah tersedia; kamera jadi cadangan **hanya** bila
  user memintanya kemudian).
- ❌ State manager eksternal (Redux/Zustand) — cakupan state tidak membutuhkannya.
- ❌ UI kit selain Tailwind + komponen sendiri.
- ❌ Library animasi lain selain GSAP + Lenis (anime.js, AOS, Framer Motion). Mencampur dua mesin
  animasi membuat perilaku `prefers-reduced-motion` dan anggaran performa mustahil dijaga.
- ❌ `dangerouslySetInnerHTML` untuk data dari database.
