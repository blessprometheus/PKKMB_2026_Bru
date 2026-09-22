"use client";

import { useAnimasi } from "./LenisProvider";

/**
 * Tautan ke section di halaman yang sama, lewat Lenis bila tersedia.
 * Lihat docs/05-frontend-spec.md §8.3 — navigasi anchor WAJIB memakai
 * `lenis.scrollTo()`, bukan lompatan bawaan browser, kalau tidak keduanya
 * berebut kendali gulir.
 *
 * Kalau Lenis belum siap (masih dimuat) atau gerak dikurangi, jatuh kembali
 * ke lompatan anchor biasa `<a href>` — tautan tetap berfungsi tanpa JS.
 */
export default function AnchorLink({
  href,
  children,
  className,
}: {
  href: `#${string}`;
  children: React.ReactNode;
  className?: string;
}) {
  const { lenis } = useAnimasi();

  function klik(e: React.MouseEvent<HTMLAnchorElement>) {
    if (!lenis) return; // biarkan default anchor jump menangani

    const target = document.querySelector(href);
    if (!target) return;

    e.preventDefault();
    lenis.scrollTo(target as HTMLElement, { offset: -72 }); // kompensasi navbar melayang
  }

  return (
    <a href={href} onClick={klik} className={className}>
      {children}
    </a>
  );
}
