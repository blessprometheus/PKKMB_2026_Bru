"use client";

import { useCallback, useEffect, useState } from "react";
import { api, ApiError, type ApiMeta } from "@/lib/api";
import type { AttendanceSessionAdmin, DashboardStats } from "@/lib/types";

type BarisHadir = {
  id: number;
  nim: string;
  name: string;
  faculty: string;
  study_program: string;
  group_name: string | null;
  scanned_at: string | null;
  method: "qr" | "manual";
  device_label: string | null;
};

export default function HalamanPresensi() {
  const [sesi, setSesi] = useState<AttendanceSessionAdmin[]>([]);
  const [sesiId, setSesiId] = useState<number | null>(null);
  const [statistik, setStatistik] = useState<DashboardStats | null>(null);
  const [baris, setBaris] = useState<BarisHadir[]>([]);
  const [meta, setMeta] = useState<ApiMeta | null>(null);
  const [halaman, setHalaman] = useState(1);
  const [galat, setGalat] = useState<string | null>(null);

  useEffect(() => {
    api
      .get<AttendanceSessionAdmin[]>("/api/v1/admin/attendance-sessions")
      .then((data) => {
        setSesi(data);
        setSesiId(data.find((s) => s.is_active)?.id ?? data[0]?.id ?? null);
      })
      .catch(() => setGalat("Gagal memuat daftar sesi."));
  }, []);

  const muat = useCallback(async (id: number, page: number) => {
    try {
      const [s, daftar] = await Promise.all([
        api.get<DashboardStats>(`/api/v1/admin/dashboard/stats?session_id=${id}`),
        api.paginated<BarisHadir>(`/api/v1/admin/attendances?session_id=${id}&page=${page}&per_page=25`),
      ]);

      setStatistik(s);
      setBaris(daftar.data);
      setMeta(daftar.meta);
      setGalat(null);
    } catch (error) {
      setGalat(error instanceof ApiError ? error.message : "Gagal memuat rekap kehadiran.");
    }
  }, []);

  useEffect(() => {
    if (sesiId !== null) void muat(sesiId, halaman);
  }, [sesiId, halaman, muat]);

  const persen =
    statistik && statistik.total_students > 0
      ? Math.round((statistik.present_count / statistik.total_students) * 100)
      : 0;

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-lg font-bold">Rekap Kehadiran</h1>
          <p className="text-sm text-pk-muted">Kehadiran tercatat per sesi presensi.</p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <label className="flex items-center gap-2 text-sm">
            <span className="font-semibold">Sesi:</span>
            <select
              value={sesiId ?? ""}
              onChange={(e) => {
                setSesiId(Number(e.target.value));
                setHalaman(1);
              }}
              className="rounded-lg border border-pk-border bg-pk-surface px-3 py-2 text-sm"
            >
              {sesi.map((s) => (
                <option key={s.id} value={s.id}>{s.name}</option>
              ))}
            </select>
          </label>

          {sesiId !== null && (
            /* Unduhan memakai navigasi biasa, bukan fetch — browser yang
               menangani penyimpanan berkasnya. */
            <a
              href={`/api/v1/admin/attendances/export?session_id=${sesiId}`}
              className="rounded-lg bg-pk-primary px-4 py-2 text-sm font-semibold text-white hover:bg-pk-primary-dark"
            >
              Unduh Excel
            </a>
          )}
        </div>
      </div>

      {galat && (
        <p role="alert" className="rounded-lg border border-pk-danger/30 bg-pk-danger/10 px-4 py-3 text-sm text-pk-danger">
          {galat}
        </p>
      )}

      {statistik && (
        <>
          <div className="grid gap-4 sm:grid-cols-3">
            {[
              { label: "Total mahasiswa", nilai: statistik.total_students, warna: "text-pk-text" },
              { label: "Sudah hadir", nilai: statistik.present_count, warna: "text-pk-success" },
              { label: "Belum hadir", nilai: statistik.absent_count, warna: "text-pk-warning" },
            ].map((k) => (
              <div key={k.label} className="rounded-xl border border-pk-border bg-pk-surface p-5">
                <p className="text-xs font-semibold uppercase tracking-wide text-pk-muted">{k.label}</p>
                <p className={`mt-1 text-3xl font-bold tabular-nums ${k.warna}`}>{k.nilai}</p>
              </div>
            ))}
          </div>

          <div className="rounded-xl border border-pk-border bg-pk-surface p-5">
            <div className="flex items-baseline justify-between">
              <h2 className="text-sm font-semibold">Kehadiran keseluruhan</h2>
              <span className="text-sm font-bold tabular-nums">{persen}%</span>
            </div>
            <div
              className="mt-2 h-3 overflow-hidden rounded-full bg-pk-surface-2"
              role="progressbar"
              aria-valuenow={persen}
              aria-valuemin={0}
              aria-valuemax={100}
              aria-label="Persentase kehadiran"
            >
              <div className="h-full rounded-full bg-pk-primary" style={{ width: `${persen}%` }} />
            </div>

            {statistik.by_faculty.length > 0 && (
              <ul className="mt-4 space-y-2 text-sm">
                {statistik.by_faculty.map((f) => (
                  <li key={f.faculty} className="flex items-center gap-3">
                    <span className="flex-1 truncate">{f.faculty}</span>
                    <span className="tabular-nums text-pk-muted">
                      {f.present} / {f.total}
                    </span>
                    <span className="h-2 w-24 overflow-hidden rounded-full bg-pk-surface-2">
                      <span
                        className="block h-full rounded-full bg-pk-primary"
                        style={{ width: `${f.total ? (f.present / f.total) * 100 : 0}%` }}
                      />
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </>
      )}

      <div className="overflow-x-auto rounded-xl border border-pk-border bg-pk-surface">
        <table className="w-full min-w-[46rem] text-left text-sm">
          <thead className="border-b border-pk-border bg-pk-surface-2 text-xs uppercase tracking-wide text-pk-muted">
            <tr>
              <th scope="col" className="px-4 py-3 font-semibold">Waktu</th>
              <th scope="col" className="px-4 py-3 font-semibold">NIM</th>
              <th scope="col" className="px-4 py-3 font-semibold">Nama</th>
              <th scope="col" className="px-4 py-3 font-semibold">Program Studi</th>
              <th scope="col" className="px-4 py-3 font-semibold">Cara</th>
              <th scope="col" className="px-4 py-3 font-semibold">Titik</th>
            </tr>
          </thead>
          <tbody>
            {baris.length === 0 && (
              <tr>
                <td colSpan={6} className="px-4 py-10 text-center text-pk-muted">
                  Belum ada kehadiran tercatat pada sesi ini.
                </td>
              </tr>
            )}

            {baris.map((b) => (
              <tr key={b.id} className="border-b border-pk-border last:border-0">
                <td className="px-4 py-3 tabular-nums">
                  {b.scanned_at
                    ? new Date(b.scanned_at).toLocaleTimeString("id-ID", {
                        hour: "2-digit",
                        minute: "2-digit",
                        second: "2-digit",
                      })
                    : "—"}
                </td>
                <td className="px-4 py-3 font-mono text-xs tabular-nums">{b.nim}</td>
                <td className="px-4 py-3 font-medium">{b.name}</td>
                <td className="px-4 py-3 text-pk-muted">{b.study_program}</td>
                <td className="px-4 py-3">
                  {b.method === "manual" ? (
                    <span className="text-xs font-semibold text-pk-warning">Input manual</span>
                  ) : (
                    <span className="text-xs text-pk-muted">QR</span>
                  )}
                </td>
                <td className="px-4 py-3 text-pk-muted">{b.device_label ?? "—"}</td>
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

      <p className="text-xs text-pk-muted">
        Unduhan Excel menyertakan mahasiswa yang <strong>belum hadir</strong> juga — itu yang paling
        sering dibutuhkan panitia setelah acara.
      </p>
    </div>
  );
}
