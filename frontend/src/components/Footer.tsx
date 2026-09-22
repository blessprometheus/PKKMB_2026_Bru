import Image from "next/image";
import Link from "next/link";

export default function Footer() {
  return (
    <footer className="border-t border-pk-border bg-pk-surface-2">
      <div className="mx-auto max-w-5xl px-4 py-8 text-center">
        <div className="flex items-center justify-center gap-3">
          <Image src="/logo-uninus.png" alt="Logo Universitas Islam Nusantara" width={116} height={160} className="h-10 w-auto" />
          <Image src="/logo-pkkmb.png" alt="Logo PKKMB 2026" width={200} height={200} className="h-9 w-9" />
        </div>

        <p className="mt-3 text-sm font-semibold">Universitas Islam Nusantara</p>
        <p className="mt-1 text-xs text-pk-muted">
          Pengenalan Kehidupan Kampus bagi Mahasiswa Baru — Tahun Akademik 2026/2027
        </p>

        <p className="mt-4 text-xs text-pk-muted">
          © 2026 Universitas Islam Nusantara.{" "}
          <Link href="/admin/login" className="underline hover:text-pk-text">
            Masuk Panitia
          </Link>
        </p>
      </div>
    </footer>
  );
}
