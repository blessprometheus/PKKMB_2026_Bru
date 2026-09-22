# Bahan dari Panitia

Folder ini menyimpan **berkas asli dari panitia PKKMB** — logo, dokumen acara, dan materi lain.
Isinya **tidak diubah**; yang dipakai aplikasi adalah turunannya (lihat §3).

Diserahkan user pada **22 September 2026**.

---

## 1. Isi Folder

| Berkas | Asli | Keterangan |
|---|---|---|
| [`logo/logo-uninus.png`](logo/logo-uninus.png) | 2934 × 4032, PNG **transparan**, 811 KB | Logo resmi Universitas Islam Nusantara |
| [`logo/logo-pkkmb-2026.jpeg`](logo/logo-pkkmb-2026.jpeg) | 1600 × 1600, JPEG, 141 KB | Logo PKKMB 2026 |
| [`logo/maskot-pkkmb-2026.jpeg`](logo/maskot-pkkmb-2026.jpeg) | 1600 × 1600, JPEG, 344 KB | Maskot PKKMB 2026 |
| [`logo/filosofi-logo-pkkmb-2026.jpeg`](logo/filosofi-logo-pkkmb-2026.jpeg) | 1447 × 1087, JPEG, 313 KB | Penjelasan filosofi logo — bahan rujukan, bukan aset yang dipasang |
| `dokumen/2026-09-29-susunan-acara-hari-1-sidang-terbuka.pdf` | 216 KB | Susunan acara teknis hari pertama |

Nama berkas diseragamkan ke `kebab-case` tanpa spasi. Berkas PDF diberi awalan **tanggal acara**
(29 September 2026), bukan tanggal dokumen dibuat (19 Agustus 2026), supaya urutannya masuk akal
ketika nanti ada susunan acara hari kedua dan seterusnya.

## 2. ⚠️ Dokumen susunan acara bertanda CONFIDENTIAL

Halaman PDF susunan acara memuat tulisan **CONFIDENTIAL** di setiap halamannya, dan isinya memuat
nama orang (MC, pembaca ayat suci, pembaca doa) serta koreografi internal acara.

**Karena itu berkas PDF-nya sengaja TIDAK di-commit ke git** (lihat `.gitignore`). Ia tetap ada di
folder ini pada mesin lokal, tetapi tidak ikut terbawa bila repositori ini kelak dipasang di
GitHub/GitLab atau dibagikan.

Yang di-commit hanyalah **fakta yang memang dibutuhkan proyek ini**, dirangkum di §4 — tanggal, jam,
lokasi, tema, dan jendela waktu registrasi. Koreografi lighting, audio, dan penugasan divisi tidak
ada hubungannya dengan website dan tidak disalin ke mana pun.

> Kalau Anda ingin PDF-nya ikut di-commit (misalnya karena repositori dipastikan privat selamanya),
> hapus barisnya dari `.gitignore`. Keputusan ini sengaja dibuat konservatif dan mudah dibalik.

## 3. Turunan yang Dipakai Aplikasi

Berkas asli terlalu besar untuk dipasang langsung — logo UNINUS 811 KB akan menghabiskan seluruh
anggaran berat halaman ([`../docs/01-prd.md`](../docs/01-prd.md) §7) sendirian, padahal mayoritas
mahasiswa membukanya lewat jaringan seluler.

| Turunan | Ukuran | Dari | Dipakai di |
|---|---|---|---|
| `frontend/public/logo-uninus.png` | 116 × 160, 11 KB | logo-uninus.png | Navbar & hero halaman publik |
| `frontend/public/logo-pkkmb.png` | 200 × 200, 23 KB | logo-pkkmb-2026.jpeg | Hero halaman publik |
| `backend/resources/images/logo-uninus.png` | 160 × 220, 17 KB | logo-uninus.png | Nametag PDF |
| `backend/resources/images/logo-pkkmb.png` | 220 × 220, 27 KB | logo-pkkmb-2026.jpeg | Nametag PDF |

Transparansi logo UNINUS dipertahankan pada seluruh turunannya. Kalau berkas asli diperbarui,
buat ulang turunannya — **jangan mengedit turunan secara manual**, karena perubahannya akan hilang
pada pembuatan ulang berikutnya.

## 4. Fakta yang Diambil untuk Proyek Ini

Dirangkum dari dokumen susunan acara hari pertama. Ini **menjawab sebagian data yang ditunggu**
di [`../docs/01-prd.md`](../docs/01-prd.md) §8.

| Hal | Nilai |
|---|---|
| Tahun akademik | 2026/2027 |
| **Tema resmi** | *Berakar pada Nilai, Bertumbuh dalam Ilmu dan Bergerak Membawa Dampak* |
| **Hari pertama** | **Selasa, 29 September 2026** |
| **Lokasi** | Aula UNINUS, Gedung Pascasarjana Lt. 3 |
| Persiapan panitia | 06:00 – 06:30 |
| **Registrasi daftar hadir mahasiswa baru** | **06:30 – 07:15 WIB (45 menit)** |
| Pengkondisian & masuk ruang sidang | 07:15 – 07:58 |
| Sidang Terbuka Senat | 08:00 – 09:37 WIB (1 jam 37 menit) |

### Cara registrasi menurut dokumen

> "Seluruh mahasiswa baru melakukan registrasi dengan cara menunjukan **barcode daftar hadir**
> kepada petugas registrasi."

Properti yang disebut dokumen untuk registrasi: **4 meja, 4 komputer, 4 scanner barcode.**
Penanggung jawab: divisi **Penerima Tamu** (memindai), dibantu **Komisi Disiplin** (menertibkan antrean).

### Yang berubah bagi rancangan kita

1. **Jumlah titik scan: 4, bukan 3.** User sebelumnya menyebut 3 alat; dokumen menyebut 4 meja,
   4 komputer, dan 4 scanner. Perlu dikonfirmasi mana yang berlaku — halaman scan dirancang untuk
   jumlah berapa pun, tetapi jumlah titik memengaruhi perhitungan antrean di bawah.
2. **Jendela registrasi hanya 45 menit.** Dengan ±550 mahasiswa dan 4 titik, rata-rata
   **satu pemindaian setiap ~20 detik per titik**. Itu longgar bila sistem cepat — tetapi kedatangan
   tidak merata, mayoritas menumpuk menjelang 07:15. Konsekuensinya ada di
   [`../docs/05-frontend-spec.md`](../docs/05-frontend-spec.md) §4: fokus input tidak boleh lepas,
   umpan balik harus instan, dan kegagalan harus terlihat seketika.
3. **Istilah panitia adalah "registrasi daftar hadir"**, bukan "presensi". Label di antarmuka petugas
   sebaiknya memakai istilah yang sama agar tidak membingungkan saat hari-H.
