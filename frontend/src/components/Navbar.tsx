import Image from "next/image";
import Link from "next/link";
import AnchorLink from "@/components/animation/AnchorLink";

const TAUTAN = [
  { href: "#informasi", label: "Informasi" },
  { href: "#jadwal", label: "Jadwal" },
  { href: "#cari-data", label: "Cari Data" },
  { href: "#tata-tertib", label: "Tata Tertib" },
  { href: "#faq", label: "FAQ" },
  { href: "#kontak", label: "Kontak" },
] as const;

export default function Navbar() {
  return (
    <header className="sticky top-0 z-40 border-b border-pk-border bg-pk-surface/90 backdrop-blur-sm">
      <nav
        className="mx-auto flex max-w-5xl items-center gap-3 px-4 py-2.5"
        aria-label="Navigasi utama"
      >
        <a href="#beranda" className="flex items-center gap-2">
          <Image src="/logo-uninus.png" alt="" width={116} height={160} className="h-9 w-auto" priority />
          <Image src="/logo-pkkmb.png" alt="" width={200} height={200} className="h-8 w-8" priority />
          <span className="sr-only">PKKMB UNINUS 2026 — beranda</span>
        </a>

        <ul className="ml-2 hidden flex-1 items-center gap-1 overflow-x-auto md:flex">
          {TAUTAN.map((t) => (
            <li key={t.href}>
              <AnchorLink
                href={t.href}
                className="inline-block whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium text-pk-muted transition hover:bg-pk-surface-2 hover:text-pk-text"
              >
                {t.label}
              </AnchorLink>
            </li>
          ))}
        </ul>

        <Link
          href="/admin/login"
          className="ml-auto shrink-0 rounded-lg border border-pk-border px-3 py-1.5 text-xs font-semibold text-pk-muted transition hover:border-pk-primary hover:text-pk-primary md:ml-0"
        >
          Masuk Panitia
        </Link>
      </nav>

      {/* Menu ringkas untuk layar sempit — sisanya dapat digulir. */}
      <div className="border-t border-pk-border px-4 py-1.5 md:hidden">
        <ul className="flex gap-1 overflow-x-auto">
          {TAUTAN.map((t) => (
            <li key={t.href}>
              <AnchorLink
                href={t.href}
                className="inline-block whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-medium text-pk-muted hover:bg-pk-surface-2"
              >
                {t.label}
              </AnchorLink>
            </li>
          ))}
        </ul>
      </div>
    </header>
  );
}
