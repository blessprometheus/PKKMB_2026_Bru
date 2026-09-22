import Link from "next/link";
import CariNim from "@/components/CariNim";

/**
 * Beranda sementara.
 *
 * Bagian "Cari Data & Unduh" di bawah sudah final — itu fitur WAJIB (M4–M6 di
 * docs/01-prd.md §5) dan tidak boleh dipotong. Sisanya (hero, countdown,
 * jadwal, tata tertib, video, FAQ, kontak) menyusul di Fase 5, dan justru
 * bagian itulah yang pertama dikorbankan bila jadwal memaksa
 * (docs/07-timeline.md §5).
 */
export default function Beranda() {
  return (
    <main className="mx-auto w-full max-w-2xl px-4 py-10">
      <header className="text-center">
        <p className="text-xs font-bold uppercase tracking-widest text-pk-primary">
          Universitas Islam Nusantara
        </p>
        <h1 className="mt-2 text-3xl font-bold">PKKMB UNINUS 2026</h1>
        <p className="mt-3 text-pk-muted">
          Pengenalan Kehidupan Kampus bagi Mahasiswa Baru. Informasi lengkap kegiatan akan
          ditampilkan di halaman ini.
        </p>
      </header>

      <div className="mt-8">
        <CariNim />
      </div>

      <footer className="mt-10 text-center text-xs text-pk-muted">
        <Link href="/admin/login" className="underline hover:text-pk-text">
          Masuk Panitia
        </Link>
      </footer>
    </main>
  );
}
