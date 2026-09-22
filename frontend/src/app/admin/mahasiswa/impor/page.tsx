"use client";

import { useState } from "react";
import { ApiError, type FieldErrors } from "@/lib/api";
import type { ImportBatch } from "@/lib/types";

/**
 * Unggah berkas Excel/CSV dari bagian PMB.
 *
 * Unggahan memakai FormData, sehingga tidak bisa lewat helper JSON di lib/api.
 * Alur CSRF-nya tetap sama: ambil cookie XSRF dulu, kirim balik di header.
 */
async function unggah(file: File): Promise<ImportBatch> {
  await fetch("/sanctum/csrf-cookie", { credentials: "include" });

  const xsrf = document.cookie
    .split("; ")
    .find((row) => row.startsWith("XSRF-TOKEN="))
    ?.split("=")
    .slice(1)
    .join("=");

  const formData = new FormData();
  formData.append("file", file);

  const response = await fetch("/api/v1/admin/students/import", {
    method: "POST",
    credentials: "include",
    headers: {
      Accept: "application/json",
      // Content-Type sengaja TIDAK diset — browser harus menambahkan
      // boundary multipart-nya sendiri.
      ...(xsrf ? { "X-XSRF-TOKEN": decodeURIComponent(xsrf) } : {}),
    },
    body: formData,
  });

  const payload = await response.json().catch(() => null);

  if (!response.ok || payload?.success === false) {
    throw new ApiError(
      payload?.message ?? "Berkas gagal diproses.",
      response.status,
      payload?.errors as FieldErrors | undefined,
    );
  }

  return payload.data as ImportBatch;
}

export default function HalamanImpor() {
  const [file, setFile] = useState<File | null>(null);
  const [sedangProses, setSedangProses] = useState(false);
  const [hasil, setHasil] = useState<ImportBatch | null>(null);
  const [galat, setGalat] = useState<string | null>(null);

  async function kirim(event: React.FormEvent) {
    event.preventDefault();

    if (!file) return;

    setSedangProses(true);
    setGalat(null);
    setHasil(null);

    try {
      setHasil(await unggah(file));
    } catch (error) {
      setGalat(
        error instanceof ApiError
          ? error.message
          : "Gagal terhubung ke server. Periksa koneksi Anda lalu coba lagi.",
      );
    } finally {
      setSedangProses(false);
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-lg font-bold">Impor Data Mahasiswa</h1>
        <p className="text-sm text-pk-muted">
          Unggah berkas Excel (.xlsx, .xls) atau CSV dari bagian PMB.
        </p>
      </div>

      <section className="rounded-xl border border-pk-border bg-pk-surface-2 p-5 text-sm">
        <h2 className="font-semibold">Yang perlu diperhatikan</h2>
        <ul className="mt-2 space-y-1.5 text-pk-muted">
          <li>
            • Berkas <strong>wajib memuat kolom</strong> NIM, Nama, Fakultas, dan Program Studi.
            Bila salah satu tidak ditemukan, seluruh impor dibatalkan — tidak ada data yang masuk
            setengah-setengah.
          </li>
          <li>
            • Baris yang bermasalah dilaporkan beserta nomor barisnya; baris lain tetap masuk.
          </li>
          <li>
            • Mengunggah ulang berkas yang sama <strong>memperbarui</strong> data, bukan
            menggandakannya. QR presensi mahasiswa yang sudah ada <strong>tidak berubah</strong>,
            sehingga nametag yang sudah dicetak tetap berlaku.
          </li>
          <li>• Ukuran berkas maksimal 10 MB.</li>
        </ul>
      </section>

      <form onSubmit={kirim} className="space-y-4">
        <div>
          <label htmlFor="berkas" className="mb-1.5 block text-sm font-semibold">
            Pilih berkas
          </label>
          <input
            id="berkas"
            type="file"
            accept=".xlsx,.xls,.csv"
            required
            onChange={(e) => {
              setFile(e.target.files?.[0] ?? null);
              setHasil(null);
              setGalat(null);
            }}
            className="block w-full max-w-md text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-pk-primary file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-white hover:file:bg-pk-primary-dark"
          />
          {file && (
            <p className="mt-2 text-xs text-pk-muted">
              {file.name} — {(file.size / 1024).toFixed(0)} KB
            </p>
          )}
        </div>

        <button
          type="submit"
          disabled={!file || sedangProses}
          className="rounded-lg bg-pk-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-pk-primary-dark disabled:opacity-60"
        >
          {sedangProses ? "Memproses berkas…" : "Unggah dan impor"}
        </button>
      </form>

      {galat && (
        <div
          role="alert"
          className="rounded-xl border border-pk-danger/30 bg-pk-danger/10 p-5 text-sm text-pk-danger"
        >
          <p className="font-semibold">Impor gagal</p>
          <p className="mt-1">{galat}</p>
        </div>
      )}

      {hasil && (
        <section className="space-y-4 rounded-xl border border-pk-border bg-pk-surface p-5">
          <h2 className="font-semibold">Hasil impor — {hasil.original_filename}</h2>

          <div className="grid gap-3 sm:grid-cols-4">
            {[
              { label: "Total baris", nilai: hasil.total_rows, warna: "text-pk-text" },
              { label: "Data baru", nilai: hasil.inserted_count, warna: "text-pk-success" },
              { label: "Diperbarui", nilai: hasil.updated_count, warna: "text-pk-info" },
              { label: "Gagal", nilai: hasil.failed_count, warna: "text-pk-danger" },
            ].map((kotak) => (
              <div key={kotak.label} className="rounded-lg border border-pk-border p-3">
                <p className="text-xs text-pk-muted">{kotak.label}</p>
                <p className={`mt-1 text-2xl font-bold tabular-nums ${kotak.warna}`}>
                  {kotak.nilai}
                </p>
              </div>
            ))}
          </div>

          {hasil.errors && hasil.errors.length > 0 && (
            <div>
              <h3 className="text-sm font-semibold">
                Baris yang gagal ({hasil.errors.length})
              </h3>
              <p className="mb-2 text-xs text-pk-muted">
                Perbaiki baris berikut di berkas aslinya, lalu unggah ulang. Nomor baris di bawah
                sesuai dengan nomor baris di Excel.
              </p>

              <div className="max-h-80 overflow-auto rounded-lg border border-pk-border">
                <table className="w-full text-left text-sm">
                  <thead className="sticky top-0 bg-pk-surface-2 text-xs uppercase tracking-wide text-pk-muted">
                    <tr>
                      <th scope="col" className="px-3 py-2 font-semibold">Baris</th>
                      <th scope="col" className="px-3 py-2 font-semibold">Kolom</th>
                      <th scope="col" className="px-3 py-2 font-semibold">Masalah</th>
                    </tr>
                  </thead>
                  <tbody>
                    {hasil.errors.map((galatBaris, i) => (
                      <tr key={i} className="border-b border-pk-border last:border-0">
                        <td className="px-3 py-2 tabular-nums">{galatBaris.row}</td>
                        <td className="px-3 py-2 font-mono text-xs">{galatBaris.column}</td>
                        <td className="px-3 py-2">{galatBaris.message}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {hasil.failed_count === 0 && (
            <p className="text-sm text-pk-success">
              Seluruh baris berhasil diproses tanpa kesalahan.
            </p>
          )}
        </section>
      )}
    </div>
  );
}
