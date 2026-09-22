"use client";

import { Suspense, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { api, ApiError } from "@/lib/api";
import type { AdminProfile } from "@/lib/types";

function FormLogin() {
  const router = useRouter();
  const searchParams = useSearchParams();

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [pesanUmum, setPesanUmum] = useState<string | null>(null);
  const [sedangKirim, setSedangKirim] = useState(false);

  async function kirim(event: React.FormEvent) {
    event.preventDefault();

    setErrors({});
    setPesanUmum(null);
    setSedangKirim(true);

    try {
      const profil = await api.post<AdminProfile>("/api/v1/auth/login", { email, password });

      // Operator hanya berkepentingan dengan halaman scan; melemparnya ke
      // dashboard hanya akan berakhir 403.
      const tujuan =
        searchParams.get("lanjut") ?? (profil.role === "operator" ? "/scan" : "/admin");

      router.replace(tujuan);
    } catch (error) {
      if (error instanceof ApiError) {
        const perField: Record<string, string> = {};

        for (const [field, pesan] of Object.entries(error.errors ?? {})) {
          perField[field] = pesan[0];
        }

        setErrors(perField);

        // 422 sudah tampil di bawah field; sisanya (403 akun nonaktif,
        // 429 terkunci, 400 salah konfigurasi domain) perlu pesan tersendiri.
        if (error.status !== 422) setPesanUmum(error.message);
      } else {
        setPesanUmum("Gagal terhubung ke server. Periksa koneksi Anda lalu coba lagi.");
      }
    } finally {
      setSedangKirim(false);
    }
  }

  return (
    <form onSubmit={kirim} className="space-y-5" noValidate>
      {pesanUmum && (
        <p
          role="alert"
          className="rounded-lg border border-pk-danger/30 bg-pk-danger/10 px-4 py-3 text-sm text-pk-danger"
        >
          {pesanUmum}
        </p>
      )}

      <div className="space-y-1.5">
        <label htmlFor="email" className="block text-sm font-semibold">
          Email
        </label>
        <input
          id="email"
          type="email"
          autoComplete="username"
          required
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          aria-invalid={Boolean(errors.email)}
          aria-describedby={errors.email ? "galat-email" : undefined}
          className="w-full rounded-lg border border-pk-border bg-pk-surface px-3.5 py-2.5 text-sm outline-none focus:border-pk-primary"
        />
        {errors.email && (
          <p id="galat-email" className="text-sm text-pk-danger">
            {errors.email}
          </p>
        )}
      </div>

      <div className="space-y-1.5">
        <label htmlFor="password" className="block text-sm font-semibold">
          Kata sandi
        </label>
        <input
          id="password"
          type="password"
          autoComplete="current-password"
          required
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          aria-invalid={Boolean(errors.password)}
          aria-describedby={errors.password ? "galat-password" : undefined}
          className="w-full rounded-lg border border-pk-border bg-pk-surface px-3.5 py-2.5 text-sm outline-none focus:border-pk-primary"
        />
        {errors.password && (
          <p id="galat-password" className="text-sm text-pk-danger">
            {errors.password}
          </p>
        )}
      </div>

      <button
        type="submit"
        disabled={sedangKirim}
        className="w-full rounded-lg bg-pk-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-pk-primary-dark disabled:opacity-60"
      >
        {sedangKirim ? "Memproses…" : "Masuk"}
      </button>

      <p className="text-center text-xs text-pk-muted">
        Akun panitia dibuat oleh admin. Hubungi admin bila Anda belum memilikinya.
      </p>
    </form>
  );
}

export default function HalamanLogin() {
  return (
    <main className="flex min-h-dvh items-center justify-center bg-pk-bg px-4 py-10">
      <div className="w-full max-w-sm">
        <div className="mb-6 text-center">
          <p className="text-xs font-bold uppercase tracking-widest text-pk-primary">
            PKKMB UNINUS 2026
          </p>
          <h1 className="mt-1 text-xl font-bold">Masuk Panitia</h1>
          <p className="mt-1 text-sm text-pk-muted">
            Halaman ini khusus panitia. Mahasiswa baru tidak perlu masuk.
          </p>
        </div>

        <div className="rounded-xl border border-pk-border bg-pk-surface p-6 shadow-sm">
          {/* useSearchParams wajib berada di dalam Suspense pada App Router. */}
          <Suspense fallback={<p className="text-sm text-pk-muted">Memuat…</p>}>
            <FormLogin />
          </Suspense>
        </div>
      </div>
    </main>
  );
}
