"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { api, ApiError, type ApiMeta } from "@/lib/api";
import type { Student } from "@/lib/types";

export default function HalamanMahasiswa() {
  const [kataKunci, setKataKunci] = useState("");
  const [halaman, setHalaman] = useState(1);
  const [daftar, setDaftar] = useState<Student[]>([]);
  const [meta, setMeta] = useState<ApiMeta | null>(null);
  const [memuat, setMemuat] = useState(true);
  const [galat, setGalat] = useState<string | null>(null);

  // Menghindari kondisi balapan: respons permintaan lama bisa tiba setelah
  // yang baru dan menimpa hasil yang benar.
  const permintaanKe = useRef(0);

  const muat = useCallback(async (q: string, page: number) => {
    const nomor = ++permintaanKe.current;

    setMemuat(true);
    setGalat(null);

    try {
      const params = new URLSearchParams({ page: String(page), per_page: "25" });
      if (q.trim() !== "") params.set("q", q.trim());

      const hasil = await api.paginated<Student>(`/api/v1/admin/students?${params}`);

      if (nomor !== permintaanKe.current) return;

      setDaftar(hasil.data);
      setMeta(hasil.meta);
    } catch (error) {
      if (nomor !== permintaanKe.current) return;

      setGalat(
        error instanceof ApiError
          ? error.message
          : "Gagal terhubung ke server. Periksa koneksi Anda lalu coba lagi.",
      );
    } finally {
      if (nomor === permintaanKe.current) setMemuat(false);
    }
  }, []);

  // Debounce 400 ms: tanpa ini setiap ketikan memicu satu query ke database.
  useEffect(() => {
    const timer = setTimeout(() => muat(kataKunci, halaman), 400);

    return () => clearTimeout(timer);
  }, [kataKunci, halaman, muat]);

  async function hapus(student: Student) {
    const yakin = window.confirm(
      `Hapus data ${student.name} (${student.nim})?\n\n` +
        "Tindakan ini tidak bisa dibatalkan, dan QR presensi mahasiswa ini ikut hilang.",
    );

    if (!yakin) return;

    try {
      await api.delete(`/api/v1/admin/students/${student.id}`);
      await muat(kataKunci, halaman);
    } catch (error) {
      setGalat(error instanceof ApiError ? error.message : "Gagal menghapus data.");
    }
  }

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-lg font-bold">Mahasiswa</h1>
          <p className="text-sm text-pk-muted">
            {meta ? `${meta.total} mahasiswa terdaftar` : "Memuat…"}
          </p>
        </div>
      </div>

      <div>
        <label htmlFor="cari" className="mb-1.5 block text-sm font-semibold">
          Cari nama atau NIM
        </label>
        <input
          id="cari"
          type="search"
          value={kataKunci}
          onChange={(e) => {
            setKataKunci(e.target.value);
            setHalaman(1);
          }}
          placeholder="Misalnya: Ahmad, atau 2026001"
          className="w-full max-w-md rounded-lg border border-pk-border bg-pk-surface px-3.5 py-2.5 text-sm outline-none focus:border-pk-primary"
        />
      </div>

      {galat && (
        <p role="alert" className="rounded-lg border border-pk-danger/30 bg-pk-danger/10 px-4 py-3 text-sm text-pk-danger">
          {galat}
        </p>
      )}

      <div className="overflow-x-auto rounded-xl border border-pk-border bg-pk-surface">
        <table className="w-full min-w-[46rem] text-left text-sm">
          <caption className="sr-only">Daftar mahasiswa baru peserta PKKMB 2026</caption>
          <thead className="border-b border-pk-border bg-pk-surface-2 text-xs uppercase tracking-wide text-pk-muted">
            <tr>
              <th scope="col" className="px-4 py-3 font-semibold">NIM</th>
              <th scope="col" className="px-4 py-3 font-semibold">Nama</th>
              <th scope="col" className="px-4 py-3 font-semibold">Fakultas</th>
              <th scope="col" className="px-4 py-3 font-semibold">Program Studi</th>
              <th scope="col" className="px-4 py-3 font-semibold">Kelompok</th>
              <th scope="col" className="px-4 py-3 font-semibold">
                <span className="sr-only">Tindakan</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {memuat && daftar.length === 0 && (
              <tr>
                <td colSpan={6} className="px-4 py-10 text-center text-pk-muted">
                  Memuat data…
                </td>
              </tr>
            )}

            {!memuat && daftar.length === 0 && (
              <tr>
                <td colSpan={6} className="px-4 py-10 text-center text-pk-muted">
                  {kataKunci.trim() === ""
                    ? "Belum ada data mahasiswa. Impor dari berkas Excel bagian PMB terlebih dahulu."
                    : `Tidak ada mahasiswa yang cocok dengan "${kataKunci}".`}
                </td>
              </tr>
            )}

            {daftar.map((s) => (
              <tr key={s.id} className="border-b border-pk-border last:border-0">
                <td className="px-4 py-3 font-mono text-xs tabular-nums">{s.nim}</td>
                <td className="px-4 py-3 font-medium">{s.name}</td>
                <td className="px-4 py-3 text-pk-muted">{s.faculty}</td>
                <td className="px-4 py-3 text-pk-muted">{s.study_program}</td>
                <td className="px-4 py-3 text-pk-muted">{s.group_name ?? "—"}</td>
                <td className="px-4 py-3 text-right">
                  <button
                    onClick={() => hapus(s)}
                    className="rounded-md px-2 py-1 text-xs font-semibold text-pk-danger hover:bg-pk-danger/10"
                  >
                    Hapus
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {meta && meta.last_page > 1 && (
        <nav className="flex items-center justify-between gap-3" aria-label="Navigasi halaman">
          <button
            onClick={() => setHalaman((h) => Math.max(1, h - 1))}
            disabled={meta.current_page <= 1}
            className="rounded-lg border border-pk-border px-3 py-2 text-sm font-semibold disabled:opacity-40"
          >
            ← Sebelumnya
          </button>

          <p className="text-sm text-pk-muted">
            Halaman {meta.current_page} dari {meta.last_page}
          </p>

          <button
            onClick={() => setHalaman((h) => Math.min(meta.last_page, h + 1))}
            disabled={meta.current_page >= meta.last_page}
            className="rounded-lg border border-pk-border px-3 py-2 text-sm font-semibold disabled:opacity-40"
          >
            Berikutnya →
          </button>
        </nav>
      )}
    </div>
  );
}
