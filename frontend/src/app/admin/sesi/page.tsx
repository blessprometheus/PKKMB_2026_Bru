"use client";

import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { AttendanceSessionAdmin } from "@/lib/types";

/** Nilai untuk <input type="datetime-local">, dalam waktu lokal perangkat. */
function keInputLokal(iso: string): string {
  const d = new Date(iso);
  const pad = (n: number) => String(n).padStart(2, "0");

  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(
    d.getMinutes(),
  )}`;
}

export default function HalamanSesi() {
  const [daftar, setDaftar] = useState<AttendanceSessionAdmin[]>([]);
  const [galat, setGalat] = useState<string | null>(null);
  const [memuat, setMemuat] = useState(true);

  // Prasetel dari dokumen panitia: registrasi hari pertama 06:30–07:15,
  // Selasa 29 September 2026 (bahan/README.md §4).
  const [form, setForm] = useState({
    name: "Registrasi Daftar Hadir — Hari 1",
    event_date: "2026-09-29",
    starts_at: "2026-09-29T06:30",
    ends_at: "2026-09-29T07:15",
  });

  const muat = useCallback(async () => {
    setMemuat(true);
    try {
      setDaftar(await api.get<AttendanceSessionAdmin[]>("/api/v1/admin/attendance-sessions"));
      setGalat(null);
    } catch (error) {
      setGalat(error instanceof ApiError ? error.message : "Gagal memuat sesi presensi.");
    } finally {
      setMemuat(false);
    }
  }, []);

  useEffect(() => {
    void muat();
  }, [muat]);

  async function tambah(event: React.FormEvent) {
    event.preventDefault();
    setGalat(null);

    try {
      await api.post("/api/v1/admin/attendance-sessions", form);
      await muat();
    } catch (error) {
      setGalat(
        error instanceof ApiError
          ? (error.fieldError("ends_at") ?? error.fieldError("name") ?? error.message)
          : "Gagal menyimpan sesi.",
      );
    }
  }

  async function aktifkan(id: number) {
    try {
      await api.post(`/api/v1/admin/attendance-sessions/${id}/activate`);
      await muat();
    } catch (error) {
      setGalat(error instanceof ApiError ? error.message : "Gagal mengaktifkan sesi.");
    }
  }

  async function hapus(sesi: AttendanceSessionAdmin) {
    if (!window.confirm(`Hapus sesi "${sesi.name}"?`)) return;

    try {
      await api.delete(`/api/v1/admin/attendance-sessions/${sesi.id}`);
      await muat();
    } catch (error) {
      setGalat(error instanceof ApiError ? error.message : "Gagal menghapus sesi.");
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-lg font-bold">Sesi Presensi</h1>
        <p className="text-sm text-pk-muted">
          Pemindaian hanya diterima pada sesi yang <strong>aktif</strong> dan berada dalam jendela
          waktunya. Di luar itu, petugas akan melihat layar merah.
        </p>
      </div>

      {galat && (
        <p role="alert" className="rounded-lg border border-pk-danger/30 bg-pk-danger/10 px-4 py-3 text-sm text-pk-danger">
          {galat}
        </p>
      )}

      <form onSubmit={tambah} className="grid gap-3 rounded-xl border border-pk-border bg-pk-surface p-5 sm:grid-cols-2">
        <label className="sm:col-span-2">
          <span className="mb-1 block text-sm font-semibold">Nama sesi</span>
          <input
            required
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            className="w-full rounded-lg border border-pk-border bg-pk-surface px-3 py-2 text-sm"
          />
        </label>

        <label>
          <span className="mb-1 block text-sm font-semibold">Tanggal kegiatan</span>
          <input
            type="date"
            required
            value={form.event_date}
            onChange={(e) => setForm({ ...form, event_date: e.target.value })}
            className="w-full rounded-lg border border-pk-border bg-pk-surface px-3 py-2 text-sm"
          />
        </label>

        <div className="grid grid-cols-2 gap-3">
          <label>
            <span className="mb-1 block text-sm font-semibold">Mulai</span>
            <input
              type="datetime-local"
              required
              value={form.starts_at}
              onChange={(e) => setForm({ ...form, starts_at: e.target.value })}
              className="w-full rounded-lg border border-pk-border bg-pk-surface px-3 py-2 text-sm"
            />
          </label>
          <label>
            <span className="mb-1 block text-sm font-semibold">Selesai</span>
            <input
              type="datetime-local"
              required
              value={form.ends_at}
              onChange={(e) => setForm({ ...form, ends_at: e.target.value })}
              className="w-full rounded-lg border border-pk-border bg-pk-surface px-3 py-2 text-sm"
            />
          </label>
        </div>

        <div className="sm:col-span-2">
          <button className="rounded-lg bg-pk-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-pk-primary-dark">
            Tambah sesi
          </button>
        </div>
      </form>

      <div className="overflow-x-auto rounded-xl border border-pk-border bg-pk-surface">
        <table className="w-full min-w-[42rem] text-left text-sm">
          <thead className="border-b border-pk-border bg-pk-surface-2 text-xs uppercase tracking-wide text-pk-muted">
            <tr>
              <th scope="col" className="px-4 py-3 font-semibold">Nama</th>
              <th scope="col" className="px-4 py-3 font-semibold">Jendela waktu</th>
              <th scope="col" className="px-4 py-3 font-semibold">Hadir</th>
              <th scope="col" className="px-4 py-3 font-semibold">Status</th>
              <th scope="col" className="px-4 py-3 font-semibold"><span className="sr-only">Tindakan</span></th>
            </tr>
          </thead>
          <tbody>
            {memuat && (
              <tr><td colSpan={5} className="px-4 py-8 text-center text-pk-muted">Memuat…</td></tr>
            )}

            {!memuat && daftar.length === 0 && (
              <tr><td colSpan={5} className="px-4 py-8 text-center text-pk-muted">Belum ada sesi presensi.</td></tr>
            )}

            {daftar.map((s) => (
              <tr key={s.id} className="border-b border-pk-border last:border-0">
                <td className="px-4 py-3 font-medium">{s.name}</td>
                <td className="px-4 py-3 text-pk-muted">
                  {s.starts_at && s.ends_at
                    ? `${keInputLokal(s.starts_at).replace("T", " ")} – ${keInputLokal(s.ends_at).slice(11)}`
                    : "—"}
                </td>
                <td className="px-4 py-3 tabular-nums">{s.attendances_count}</td>
                <td className="px-4 py-3">
                  {/* Warna selalu disertai teks — jangan sampai maknanya hilang
                      bagi petugas yang buta warna. */}
                  {s.is_open_now ? (
                    <span className="rounded-md bg-pk-success/15 px-2 py-1 text-xs font-bold text-pk-success">
                      ● Dibuka
                    </span>
                  ) : s.is_active ? (
                    <span className="rounded-md bg-pk-warning/15 px-2 py-1 text-xs font-bold text-pk-warning">
                      ● Aktif, di luar jam
                    </span>
                  ) : (
                    <span className="rounded-md bg-pk-border px-2 py-1 text-xs font-bold text-pk-muted">
                      ○ Nonaktif
                    </span>
                  )}
                </td>
                <td className="px-4 py-3 text-right whitespace-nowrap">
                  {!s.is_active && (
                    <button
                      onClick={() => aktifkan(s.id)}
                      className="rounded-md px-2 py-1 text-xs font-semibold text-pk-primary hover:bg-pk-primary/10"
                    >
                      Aktifkan
                    </button>
                  )}
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

      <p className="text-xs text-pk-muted">
        Hanya satu sesi yang boleh aktif. Mengaktifkan satu sesi otomatis menonaktifkan yang lain —
        supaya kehadiran tidak pernah tercatat di sesi yang salah.
      </p>
    </div>
  );
}
