"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { api } from "@/lib/api";
import type { Student } from "@/lib/types";

export default function HalamanDasbor() {
  const [totalMahasiswa, setTotalMahasiswa] = useState<number | null>(null);
  const [gagal, setGagal] = useState(false);

  useEffect(() => {
    // Endpoint statistik khusus belum ada (docs/03-api-spec.md §4.4 — Fase 4).
    // Sementara jumlah diambil dari meta paginasi daftar mahasiswa, dengan
    // per_page=1 supaya tidak ada baris yang benar-benar diambil.
    api
      .paginated<Student>("/api/v1/admin/students?per_page=1")
      .then(({ meta }) => setTotalMahasiswa(meta.total))
      .catch(() => setGagal(true));
  }, []);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-lg font-bold">Dasbor</h1>
        <p className="text-sm text-pk-muted">Ringkasan data peserta PKKMB 2026.</p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div className="rounded-xl border border-pk-border bg-pk-surface p-5">
          <p className="text-xs font-semibold uppercase tracking-wide text-pk-muted">
            Total mahasiswa terdaftar
          </p>
          <p className="mt-2 text-3xl font-bold tabular-nums">
            {gagal ? "—" : (totalMahasiswa ?? "…")}
          </p>
          {gagal && <p className="mt-1 text-xs text-pk-danger">Gagal memuat data.</p>}
        </div>

        <Link
          href="/admin/mahasiswa"
          className="rounded-xl border border-pk-border bg-pk-surface p-5 transition hover:border-pk-primary"
        >
          <p className="text-sm font-semibold">Kelola mahasiswa →</p>
          <p className="mt-1 text-sm text-pk-muted">
            Cari, tambah, perbaiki, atau hapus data peserta.
          </p>
        </Link>

        <Link
          href="/admin/mahasiswa/impor"
          className="rounded-xl border border-pk-border bg-pk-surface p-5 transition hover:border-pk-primary"
        >
          <p className="text-sm font-semibold">Impor data dari Excel →</p>
          <p className="mt-1 text-sm text-pk-muted">
            Unggah berkas dari bagian PMB dan lihat laporan baris yang gagal.
          </p>
        </Link>
      </div>

      {/*
        Menyebut terus terang apa yang belum ada lebih baik daripada menampilkan
        kartu kosong yang membuat panitia mengira fiturnya rusak.
      */}
      <section className="rounded-xl border border-dashed border-pk-border p-5">
        <h2 className="text-sm font-semibold">Belum tersedia</h2>
        <ul className="mt-2 space-y-1 text-sm text-pk-muted">
          <li>• Statistik kehadiran dan grafik laju kedatangan — menyusul di Fase 4.</li>
          <li>• Pengelolaan sesi presensi — menyusul di Fase 4.</li>
          <li>• Rekap &amp; ekspor kehadiran — menyusul di Fase 4.</li>
        </ul>
      </section>
    </div>
  );
}
