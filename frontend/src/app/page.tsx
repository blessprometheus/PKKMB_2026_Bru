import Link from "next/link";

/**
 * Penanda sementara.
 *
 * Landing page sesungguhnya (hero, countdown, jadwal, tata tertib, video, FAQ,
 * kontak, kotak cari NIM) dikerjakan pada Fase 5 — lihat docs/07-timeline.md.
 * Ditempatkan paling akhir dengan sengaja: bagian ini yang paling aman dipotong
 * bila modul presensi belum siap.
 */
export default function Beranda() {
  return (
    <main className="mx-auto flex min-h-dvh max-w-xl flex-col justify-center px-4 py-12 text-center">
      <p className="text-xs font-bold uppercase tracking-widest text-pk-primary">
        Universitas Islam Nusantara
      </p>

      <h1 className="mt-2 text-3xl font-bold">PKKMB UNINUS 2026</h1>

      <p className="mt-3 text-pk-muted">
        Situs resmi Pengenalan Kehidupan Kampus bagi Mahasiswa Baru sedang disiapkan.
        Informasi lengkap dan pencarian data peserta akan tersedia di halaman ini.
      </p>

      <div className="mt-8">
        <Link
          href="/admin/login"
          className="inline-block rounded-lg border border-pk-border px-4 py-2.5 text-sm font-semibold transition hover:border-pk-primary"
        >
          Masuk Panitia
        </Link>
      </div>
    </main>
  );
}
