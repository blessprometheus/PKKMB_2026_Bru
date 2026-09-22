"use client";

import { useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { StudentLookup } from "@/lib/types";

/**
 * Pencarian data diri oleh mahasiswa baru. Lihat docs/05-frontend-spec.md §3.
 *
 * Dipakai di HP kelas menengah-bawah dengan jaringan seluler, sering sambil
 * berdiri. Jadi: ringan, satu kolom, pesan error yang menolong, dan tombol
 * unduh yang tidak bisa salah tekan.
 */
export default function CariNim() {
  const [nim, setNim] = useState("");
  const [hasil, setHasil] = useState<StudentLookup | null>(null);
  const [galat, setGalat] = useState<string | null>(null);
  const [mencari, setMencari] = useState(false);

  async function cari(event: React.FormEvent) {
    event.preventDefault();

    setMencari(true);
    setGalat(null);
    setHasil(null);

    try {
      setHasil(await api.post<StudentLookup>("/api/v1/lookup", { nim: nim.trim() }));
    } catch (error) {
      if (error instanceof ApiError) {
        setGalat(
          error.status === 429
            ? "Terlalu banyak percobaan. Coba lagi dalam beberapa menit."
            : (error.fieldError("nim") ?? error.message),
        );
      } else {
        setGalat("Gagal terhubung. Periksa koneksi Anda lalu coba lagi.");
      }
    } finally {
      setMencari(false);
    }
  }

  return (
    <section
      id="cari-data"
      aria-labelledby="judul-cari"
      className="rounded-2xl border border-pk-border bg-pk-surface p-6 text-left shadow-sm"
    >
      <h2 id="judul-cari" className="text-lg font-bold">
        Cari Data & Unduh Nametag
      </h2>
      <p className="mt-1 text-sm text-pk-muted">
        Masukkan NIM Anda untuk mengunduh nametag dan QR presensi.
      </p>

      <form onSubmit={cari} className="mt-4 flex flex-col gap-2 sm:flex-row">
        <div className="flex-1">
          <label htmlFor="nim" className="sr-only">
            NIM
          </label>
          <input
            id="nim"
            name="nim"
            /*
             * type="text", BUKAN type="number": sebagian NIM berawalan nol dan
             * input numerik akan membuangnya. inputMode="numeric" tetap
             * memunculkan papan ketik angka di HP.
             */
            type="text"
            inputMode="numeric"
            autoComplete="off"
            required
            value={nim}
            onChange={(e) => setNim(e.target.value)}
            placeholder="Contoh: 20260012345"
            className="w-full rounded-lg border border-pk-border bg-pk-surface px-4 py-3 text-base outline-none focus:border-pk-primary"
          />
        </div>

        <button
          type="submit"
          disabled={mencari || nim.trim() === ""}
          className="rounded-lg bg-pk-primary px-6 py-3 text-base font-semibold text-white transition hover:bg-pk-primary-dark disabled:opacity-60"
        >
          {mencari ? "Mencari…" : "Cari"}
        </button>
      </form>

      {galat && (
        <p
          role="alert"
          className="mt-4 rounded-lg border border-pk-danger/30 bg-pk-danger/10 px-4 py-3 text-sm text-pk-danger"
        >
          {galat}
        </p>
      )}

      {hasil && (
        <div className="mt-5 rounded-xl border border-pk-border bg-pk-surface-2 p-5">
          <p className="text-xl font-bold">{hasil.name}</p>
          <p className="mt-0.5 font-mono text-sm tabular-nums text-pk-muted">{hasil.nim}</p>
          <p className="mt-2 text-sm">
            {hasil.study_program} · {hasil.faculty}
          </p>
          {hasil.group_name && (
            <p className="mt-2 inline-block rounded-md bg-pk-secondary px-3 py-1 text-xs font-bold text-pk-text">
              {hasil.group_name}
            </p>
          )}

          {hasil.attendance.length > 0 && (
            <div className="mt-4">
              <h3 className="text-sm font-semibold">Kehadiran Anda</h3>
              <ul className="mt-1.5 space-y-1 text-sm">
                {hasil.attendance.map((sesi) => (
                  <li key={sesi.session_name} className="flex items-center gap-2">
                    {/* Ikon + teks, bukan warna saja — agar tetap terbaca oleh
                        pengguna buta warna (docs/05-frontend-spec.md §2). */}
                    <span aria-hidden="true">{sesi.status === "hadir" ? "✅" : "⬜"}</span>
                    <span className="flex-1">{sesi.session_name}</span>
                    <span
                      className={
                        sesi.status === "hadir"
                          ? "font-semibold text-pk-success"
                          : "text-pk-muted"
                      }
                    >
                      {sesi.status === "hadir"
                        ? `Hadir ${new Date(sesi.scanned_at!).toLocaleTimeString("id-ID", {
                            hour: "2-digit",
                            minute: "2-digit",
                          })}`
                        : "Belum"}
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          )}

          <div className="mt-5 flex flex-col gap-2 sm:flex-row">
            <a
              href={hasil.downloads.nametag_url}
              className="flex-1 rounded-lg bg-pk-primary px-4 py-3 text-center text-sm font-semibold text-white transition hover:bg-pk-primary-dark"
            >
              Unduh Nametag (PDF)
            </a>
            <a
              href={hasil.downloads.qr_url}
              className="flex-1 rounded-lg border border-pk-primary px-4 py-3 text-center text-sm font-semibold text-pk-primary transition hover:bg-pk-primary/10"
            >
              Unduh QR Presensi (PNG)
            </a>
          </div>

          <p className="mt-3 text-xs text-pk-muted">
            Cetak nametag ini dan bawa saat PKKMB. QR di nametag dipindai panitia untuk mencatat
            kehadiran Anda.{" "}
            <strong>Tautan unduhan berlaku 15 menit</strong> — bila kedaluwarsa, cari NIM Anda lagi.
          </p>
        </div>
      )}

      <p className="mt-4 text-xs text-pk-muted">
        Tidak menemukan data Anda? Hubungi panitia PKKMB melalui narahubung resmi.
      </p>
    </section>
  );
}
