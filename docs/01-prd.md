# 01 — PRD & Ruang Lingkup

**Proyek:** Website PKKMB UNINUS 2026 (v2 — dengan backend, dashboard admin, dan presensi QR)
**Versi dokumen:** 1.0.0 · 22 September 2026

---

## 1. Latar Belakang

PKKMB (Pengenalan Kehidupan Kampus bagi Mahasiswa Baru) adalah kegiatan wajib seluruh mahasiswa baru
Universitas Islam Nusantara. Dua masalah yang diselesaikan proyek ini:

1. **Informasi tersebar** di grup WhatsApp, Instagram, dan PDF — peserta kesulitan menemukan informasi
   yang benar, panitia menerima pertanyaan berulang.
2. **Presensi manual** — pencatatan kehadiran ± 550 mahasiswa dengan daftar kertas lambat, rawan salah,
   dan rekapnya memakan waktu berhari-hari setelah acara.

Referensi tampilan/alur dari user: `https://pkkmb.unw.ac.id/pkkmb`.

## 2. Keputusan Penting: v2 Mengganti v1

`Desktop/pkkmb` adalah PKKMB UNINUS 2026 versi statis, Fase 1 selesai 17 September 2026 (hero, countdown,
design system, dark mode, navbar, footer, responsif — semua sudah diverifikasi di browser).

Pada **22 September 2026** user memutuskan **mengganti total** v1 dengan proyek ini.

> **Catatan risiko yang sudah disampaikan dan diterima user:** membangun ulang landing page di Next.js
> memakan waktu yang tidak menghasilkan fitur baru, di tengah jadwal tujuh hari. Konsekuensinya ada
> kemungkinan nyata salah satu modul tidak siap di hari-H. Mitigasi: urutan pengerjaan di
> [`07-timeline.md`](07-timeline.md) menempatkan landing page **paling akhir**, dan menyediakan
> rencana cadangan manual untuk presensi.

Implikasi terhadap v1 `01-prd.md` §5: butir "TIDAK MASUK" berupa login, database, backend/API, absensi
digital/QR, dan dashboard panitia **dicabut** — kelimanya sekarang masuk scope. Perubahan ini disengaja
dan disetujui user, bukan improvisasi agen.

## 3. Aktor

| Aktor | Login? | Yang dilakukan |
|---|---|---|
| **Mahasiswa baru (maba)** | ❌ Tidak | Membuka landing page, membaca informasi, memasukkan NIM, mengunduh nametag + QR presensi |
| **Admin panitia** | ✅ Ya | Impor data maba dari Excel, koreksi data, kelola sesi presensi, lihat & ekspor rekap kehadiran, kelola akun petugas |
| **Petugas presensi** | ✅ Ya (peran terbatas) | Membuka halaman scan, memilih sesi, memindai QR peserta |

Asumsi perangkat maba: **mayoritas HP Android kelas menengah-bawah, jaringan seluler.** Halaman lookup
harus ringan dan cepat.

## 4. Alur Inti

### 4.1 Alur data maba (sebelum hari-H)

```
Bagian PMB  →  file Excel/CSV
                    ↓  (admin unggah di dashboard)
            Validasi baris per baris
                    ↓
            Tabel students di PostgreSQL
                    ↓  (otomatis saat impor)
            attendance_token acak per mahasiswa
```

### 4.2 Alur maba mengambil nametag

```
Maba buka landing page
      ↓  masukkan NIM
POST /api/v1/lookup  (rate limit)
      ↓  cocok
Tampil: nama, fakultas, prodi, kelompok, status kehadiran
      ↓
Unduh Nametag (PDF)  &  Unduh QR (PNG)   ← signed URL, berumur pendek
      ↓
Maba mencetak nametag (QR menyatu di nametag)
```

### 4.3 Alur presensi hari-H

```
Petugas login → buka /scan → pilih sesi aktif
      ↓
Scanner gun menembak QR di nametag peserta
(scanner berperilaku seperti keyboard: mengetik token + Enter)
      ↓
POST /api/v1/scan { token, attendance_session_id }
      ↓
✅ "TERCATAT — Nama, NIM, Prodi"        (layar hijau + bunyi)
⚠️ "SUDAH ABSEN 08:14"                  (layar kuning)
❌ "QR TIDAK DIKENAL"                    (layar merah)
      ↓
Rekap real-time di dashboard admin, bisa diekspor ke Excel
```

## 5. Ruang Lingkup — MASUK

Diurutkan berdasarkan prioritas. **MUST** = tanpa ini acara terganggu.

### MUST (wajib jalan di hari-H)

| # | Fitur | Modul |
|---|---|---|
| M1 | Login admin (hash password, sesi aman, dua peran: admin & petugas) | Backend + Admin |
| M2 | Impor data maba dari Excel/CSV, dengan laporan baris gagal | Admin |
| M3 | Daftar & pencarian mahasiswa, tambah/edit/hapus manual (untuk koreksi & pendaftar susulan) | Admin |
| M4 | Pencarian NIM oleh maba di landing page (tanpa login, dibatasi rate limit) | Publik |
| M5 | Unduh nametag (PDF siap cetak, QR menyatu di dalamnya) | Publik |
| M6 | Unduh QR presensi (PNG terpisah, untuk yang hanya menyimpan di HP) | Publik |
| M7 | Kelola sesi presensi (nama, tanggal, jam mulai/selesai, aktif/tidak) | Admin |
| M8 | Halaman scan petugas: pilih sesi, input auto-focus, umpan balik besar, tolak duplikat | Presensi |
| M9 | Rekap kehadiran per sesi + ekspor Excel | Admin |
| M10 | Input NIM manual di halaman scan sebagai cadangan kalau QR rusak/tidak terbaca | Presensi |

### SHOULD (dikerjakan kalau waktu cukup)

| # | Fitur |
|---|---|
| S1 | Landing page informasi lengkap (hero, countdown, jadwal, tata tertib, video, FAQ, kontak) |
| S2 | Dashboard statistik: total maba, sudah/belum absen, sebaran per fakultas |
| S3 | Cetak nametag massal oleh panitia (PDF gabungan per fakultas/kelompok) |
| S4 | Antrean offline di halaman scan (simpan sementara di browser kalau koneksi putus, kirim ulang saat tersambung) |
| S5 | Dark mode |
| S6 | Log audit aksi admin |

### COULD (kalau semua di atas beres)

| # | Fitur |
|---|---|
| C1 | Animasi bergaya v1 (partikel hero, parallax) |
| C2 | Halaman publik "statistik kehadiran" agregat tanpa data pribadi |
| C3 | Notifikasi WhatsApp/email ke maba yang belum mengunduh nametag |

## 6. Ruang Lingkup — TIDAK MASUK

Menambahkan salah satunya = perubahan sifat proyek, **harus disetujui user lebih dulu**
(lihat [`../AGENTS.md`](../AGENTS.md) §6).

- ❌ Login/akun untuk mahasiswa baru — maba **tanpa login**, cukup NIM
- ❌ Pendaftaran ulang, unggah berkas, atau pembayaran
- ❌ Sertifikat otomatis, e-voting, kuesioner evaluasi
- ❌ Integrasi SSO / SIAKAD / PMB lewat API — data masuk lewat impor Excel
- ❌ Aplikasi mobile native
- ❌ Multi-bahasa (Bahasa Indonesia saja)
- ❌ Upload foto oleh mahasiswa

## 7. Kriteria Sukses

| Kriteria | Target |
|---|---|
| Waktu per pemindaian (scan → layar berubah) | < 1,5 detik |
| Kapasitas presensi | 3 titik paralel, ± 550 peserta tanpa antrean menumpuk |
| Impor Excel 550 baris | < 30 detik, dengan laporan baris gagal yang bisa dibaca |
| Lookup NIM (maba) | < 2 detik di jaringan seluler |
| Ketepatan rekap kehadiran | 100% — tidak ada kehadiran ganda, tidak ada yang hilang |
| Rate limit lookup publik | Aktif dan teruji, lihat [`04-security.md`](04-security.md) §2 |
| Keamanan | `security-review` dan `ship-gate` lolos tanpa temuan kritis |
| Kontras teks & navigasi keyboard | WCAG 2.1 AA di halaman scan dan lookup (dipakai di bawah tekanan waktu) |
| Uji perangkat | Android Chrome (maba), laptop panitia + scanner gun (petugas) |

## 8. Data yang Masih Ditunggu dari User

**Fase 6 (go-live) tidak boleh dijalankan sebelum D1–D4 terisi.**

| # | Data | Dibutuhkan untuk | Prioritas | Status |
|---|---|---|---|---|
| D1 | **Contoh file Excel asli dari PMB** (boleh data disamarkan) | Memverifikasi nama kolom sebelum menulis importer | 🔴 Blocker Fase 2 | ⬜ Belum |
| D2 | Tanggal & jam resmi PKKMB 2026, serta **daftar sesi presensi** (berapa hari, berapa sesi per hari) | Tabel `attendance_sessions`, countdown, rekap | 🔴 Blocker Fase 4 | ⬜ Belum |
| D3 | Desain/ketentuan nametag: ukuran, logo, apakah memuat foto, siapa yang mencetak | Layout PDF nametag | 🔴 Blocker Fase 3 | ⬜ Belum |
| D4 | Merek & model scanner gun yang dipakai | Memastikan QR terbaca & menentukan sufiks Enter | 🔴 Blocker Fase 4 | ⬜ Belum |
| D5 | Logo UNINUS resolusi tinggi (SVG/PNG transparan) + logo PKKMB 2026 | Nametag, navbar, favicon, OG image | 🟠 Fase 3 | ⬜ Belum |
| D6 | Apakah maba dikelompokkan (gugus/kelompok + nama pendamping) | Kolom `kelompok` di nametag & rekap | 🟠 Fase 2 | ⬜ Belum |
| D7 | Nama & jumlah admin/petugas yang butuh akun | Seeder akun | 🟠 Fase 2 | ⬜ Belum |
| D8 | Tema & tagline resmi PKKMB 2026 | Hero landing | 🟡 Fase 5 | ⬜ Belum |
| D9 | Naskah informasi, rundown, tata tertib (+ PDF) | Section landing | 🟡 Fase 5 | ⬜ Belum |
| D10 | Link YouTube video profil & sambutan LLDIKTI Wilayah IV | Section video | 🟡 Fase 5 | ⬜ Belum |
| D11 | Daftar narahubung panitia (nama, divisi, nomor WA) | Section kontak | 🟡 Fase 5 | ⬜ Belum |
| D12 | Domain/subdomain final + akses DNS | Deployment | 🟠 Fase 6 | ⬜ Belum |

> **Aturan keras:** jangan mengarang isi D2, D8–D11. Nama pejabat, nomor telepon, dan tanggal yang
> dikarang di situs resmi universitas adalah kesalahan serius, bukan detail kecil.

## 9. Risiko

| Risiko | Dampak | Mitigasi |
|---|---|---|
| **Jadwal 7 hari, 1 orang, 3 modul, stack baru** | Sistem tidak siap di hari-H | Urutan MUST-first di [`07-timeline.md`](07-timeline.md); batas potong scope; **rencana cadangan presensi kertas dicetak H-1** |
| **Titipan absen** — siapa pun yang tahu NIM orang lain bisa mencetak QR-nya | Data kehadiran tidak dapat dipercaya | Mitigasi berlapis di [`04-security.md`](04-security.md) §3. Tidak bisa dihilangkan sepenuhnya di alur tanpa login — user sudah diberi tahu |
| **Endpoint lookup publik bisa disapu** untuk mengumpulkan data maba | Kebocoran data pribadi | Rate limit + minimisasi field yang dikembalikan ([`04-security.md`](04-security.md) §2) |
| Nama kolom Excel PMB berbeda dari dugaan | Importer gagal saat dibutuhkan | D1 jadi blocker Fase 2; importer memetakan kolom secara eksplisit, bukan berdasarkan urutan |
| Koneksi internet aula putus saat presensi | Presensi berhenti total | Antrean offline (S4) + rencana cadangan kertas + petugas mencatat NIM manual |
| Scanner gun tidak mengirim Enter / mengirim karakter tambahan | Scan tidak terkirim | Uji dengan alat asli di Fase 4 (D4); halaman scan menangani submit tanpa Enter |
| Maba tidak mencetak nametag sebelum datang | Antrean di pintu masuk | Input NIM manual di halaman scan (M10) + panitia mencetak cadangan (S3) |
| Data pribadi 550 mahasiswa bocor dari repo/server | Masalah serius bagi universitas | Aturan di [`../AGENTS.md`](../AGENTS.md) §5 + checklist [`04-security.md`](04-security.md) §11 |
