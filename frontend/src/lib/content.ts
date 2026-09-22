/**
 * Konten landing page yang BUKAN kode — teks, tanggal, dan tautan.
 * Dipisah dari markup supaya panitia (lewat developer) bisa memperbarui tanpa
 * menyentuh komponen React.
 *
 * ATURAN KERAS (AGENTS.md §4, docs/01-prd.md §8): data yang ditandai `TODO:`
 * di bawah BELUM diterima dari panitia dan TIDAK BOLEH dikarang — termasuk
 * nama pejabat, nomor kontak, dan tautan video. Jangan hapus penanda TODO
 * sampai isinya benar-benar diganti dengan data resmi.
 *
 * Fakta yang SUDAH diverifikasi diambil dari bahan/README.md §4 (dirangkum
 * dari dokumen panitia "Susunan Acara Teknis Sidang Terbuka PKKMB 2026",
 * diterima 22 September 2026).
 */

export const acara = {
  nama: "PKKMB UNINUS 2026",
  tema: "Berakar pada Nilai, Bertumbuh dalam Ilmu dan Bergerak Membawa Dampak",
  tahunAkademik: "2026/2027",
  lokasi: "Aula UNINUS, Gedung Pascasarjana Lantai 3",
  lokasiSingkat: "Aula UNINUS Gd. Pascasarjana Lt. 3",

  // Hari pertama terverifikasi. Hari-hari berikutnya BELUM ada dokumennya —
  // jangan menambah hari ke-2/3 tanpa sumber.
  hariPertama: {
    tanggalIso: "2026-09-29",
    label: "Selasa, 29 September 2026",
  },

  // Batas bawah jendela registrasi — dipakai countdown di hero.
  registrasiMulaiIso: "2026-09-29T06:30:00+07:00",
  registrasiSelesaiIso: "2026-09-29T07:15:00+07:00",
  sidangMulaiIso: "2026-09-29T08:00:00+07:00",
  sidangSelesaiIso: "2026-09-29T09:37:00+07:00",
} as const;

/**
 * Jadwal publik. Hanya memuat waktu & nama segmen besar — BUKAN koreksi
 * lighting/audio/nama petugas dari dokumen internal panitia yang bertanda
 * CONFIDENTIAL (lihat bahan/README.md §2). Itu bukan konsumsi publik.
 */
export const jadwalHari1 = [
  { waktu: "06:30 – 07:15", judul: "Registrasi Daftar Hadir Mahasiswa Baru", catatan: "Tunjukkan QR pada nametag Anda kepada petugas." },
  { waktu: "07:15 – 07:58", judul: "Memasuki Ruang Sidang", catatan: null },
  { waktu: "08:00 – 09:37", judul: "Sidang Terbuka Senat Universitas", catatan: "Pembukaan resmi PKKMB Tahun Akademik 2026/2027." },
] as const;

// TODO: [D9] Jadwal hari ke-2 dan seterusnya — menunggu dokumen dari panitia.
export const jadwalMendatang = true;

/*
 * TODO: [D9] Naskah "Informasi PKKMB", dokumen tata tertib + PDF.
 * TODO: [D10] Tautan YouTube video profil UNINUS & sambutan LLDIKTI Wilayah IV.
 * TODO: [D11] Daftar narahubung panitia (nama, divisi, nomor WA).
 *
 * Section untuk keempatnya di src/app/page.tsx masih memakai <TodoSection>.
 * Begitu datanya diterima, GANTI komponennya dengan section sungguhan —
 * jangan menambah flag boolean di sini untuk menyalakan/mematikannya.
 */

export const faq = [
  {
    pertanyaan: "Bagaimana cara mengambil nametag dan QR presensi?",
    jawaban:
      'Masukkan NIM Anda di kotak "Cari Data & Unduh Nametag" pada halaman ini, lalu unduh berkas PDF nametag dan gambar QR-nya. Cetak nametag dan bawa saat PKKMB.',
  },
  {
    pertanyaan: "NIM saya tidak ditemukan, apa yang harus dilakukan?",
    jawaban:
      "Pastikan NIM yang dimasukkan sesuai dengan yang diberikan bagian akademik, tanpa spasi. Bila tetap tidak ditemukan, data Anda mungkin belum masuk sistem — hubungi panitia.",
  },
  {
    pertanyaan: "Apakah QR presensi boleh dibagikan ke orang lain?",
    jawaban:
      "Tidak. QR ini adalah bukti kehadiran Anda pribadi. Nametag akan dicocokkan petugas di lokasi, dan setiap QR hanya bisa dipakai sekali per sesi.",
  },
  {
    pertanyaan: "Saya sudah memindai QR, tapi status masih “Belum”. Kenapa?",
    jawaban:
      "Status diperbarui otomatis begitu petugas memindai QR Anda di lokasi acara. Cari NIM Anda kembali beberapa saat setelah proses registrasi untuk melihat status terbaru.",
  },
] as const;
