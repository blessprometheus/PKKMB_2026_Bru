"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { api, ApiError } from "@/lib/api";
import type { AdminProfile } from "@/lib/types";

const MENU = [
  { href: "/admin", label: "Dasbor" },
  { href: "/admin/mahasiswa", label: "Mahasiswa" },
  { href: "/admin/mahasiswa/impor", label: "Impor Data" },
  { href: "/admin/sesi", label: "Sesi Presensi" },
  { href: "/admin/presensi", label: "Rekap Kehadiran" },
];

/**
 * Kerangka dashboard panitia.
 *
 * Halaman login memakai rute /admin/login yang juga melewati layout ini, jadi
 * layout harus melewatkannya tanpa memeriksa sesi — kalau tidak, akan terjadi
 * pengalihan berputar.
 */
export default function AdminLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();

  const [profil, setProfil] = useState<AdminProfile | null>(null);
  const [memuat, setMemuat] = useState(true);

  const halamanLogin = pathname.startsWith("/admin/login");

  useEffect(() => {
    if (halamanLogin) {
      setMemuat(false);
      return;
    }

    let batal = false;

    api
      .get<AdminProfile>("/api/v1/auth/me")
      .then((data) => {
        if (!batal) setProfil(data);
      })
      .catch((error) => {
        if (batal) return;

        // proxy.ts hanya memeriksa keberadaan cookie. Sesi yang sudah
        // kedaluwarsa baru ketahuan di sini, saat backend menolak.
        if (error instanceof ApiError && (error.status === 401 || error.status === 403)) {
          router.replace("/admin/login");
        }
      })
      .finally(() => {
        if (!batal) setMemuat(false);
      });

    return () => {
      batal = true;
    };
  }, [halamanLogin, pathname, router]);

  if (halamanLogin) return <>{children}</>;

  if (memuat) {
    return (
      <main className="flex min-h-dvh items-center justify-center">
        <p className="text-sm text-pk-muted">Memuat…</p>
      </main>
    );
  }

  if (!profil) return null;

  async function keluar() {
    try {
      await api.post("/api/v1/auth/logout");
    } finally {
      router.replace("/admin/login");
    }
  }

  return (
    <div className="flex min-h-dvh flex-col">
      <header className="border-b border-pk-border bg-pk-surface">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-4 px-4 py-3">
          <div className="mr-auto">
            <p className="text-[11px] font-bold uppercase tracking-widest text-pk-primary">
              PKKMB UNINUS 2026
            </p>
            <p className="text-sm font-semibold">Dasbor Panitia</p>
          </div>

          <div className="text-right text-xs">
            <p className="font-semibold">{profil.name}</p>
            <p className="text-pk-muted">
              {profil.role === "admin" ? "Admin" : "Petugas presensi"}
            </p>
          </div>

          <button
            onClick={keluar}
            className="rounded-lg border border-pk-border px-3 py-1.5 text-xs font-semibold hover:bg-pk-surface-2"
          >
            Keluar
          </button>
        </div>

        <nav className="mx-auto max-w-6xl px-4" aria-label="Menu utama">
          <ul className="flex gap-1 overflow-x-auto">
            {MENU.map((item) => {
              const aktif =
                item.href === "/admin"
                  ? pathname === "/admin"
                  : pathname.startsWith(item.href);

              return (
                <li key={item.href}>
                  <Link
                    href={item.href}
                    aria-current={aktif ? "page" : undefined}
                    className={`inline-block whitespace-nowrap border-b-2 px-3 py-2.5 text-sm transition ${
                      aktif
                        ? "border-pk-primary font-semibold text-pk-primary"
                        : "border-transparent text-pk-muted hover:text-pk-text"
                    }`}
                  >
                    {item.label}
                  </Link>
                </li>
              );
            })}
          </ul>
        </nav>
      </header>

      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-6">{children}</main>
    </div>
  );
}
