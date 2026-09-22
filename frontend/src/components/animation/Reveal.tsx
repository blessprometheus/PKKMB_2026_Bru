"use client";

import { useEffect, useRef } from "react";
import { useAnimasi } from "./LenisProvider";

type Props = {
  children: React.ReactNode;
  className?: string;
  /** Jeda sebelum animasi mulai (detik) — dipakai untuk efek beruntun ringan. */
  delay?: number;
};

/**
 * Pembungkus "muncul saat digulir" berbasis GSAP + ScrollTrigger.
 * Lihat docs/05-frontend-spec.md §8.
 *
 * SENGAJA tidak dipakai untuk Hero — LCP tidak boleh menunggu animasi
 * (§8.4). Hanya untuk section di bawah lipatan pertama.
 *
 * Hanya menganimasikan `transform` dan `opacity` — keduanya tidak memicu
 * perhitungan ulang tata letak (§8.4).
 */
export default function Reveal({ children, className, delay = 0 }: Props) {
  const ref = useRef<HTMLDivElement>(null);
  const { berkurang } = useAnimasi();

  useEffect(() => {
    // prefers-reduced-motion: langsung tetapkan keadaan akhir, tanpa animasi.
    if (berkurang || !ref.current) return;

    let batal = false;
    // Bentuk minimal yang dipakai saja — menghindari impor tipe statis dari
    // "gsap" di sini supaya modulnya tetap hanya dimuat secara dinamis.
    let tween: { kill: () => void; scrollTrigger?: { kill: () => void } | null } | undefined;

    (async () => {
      const [{ default: gsap }, { default: ScrollTrigger }] = await Promise.all([
        import("gsap"),
        import("gsap/ScrollTrigger"),
      ]);

      if (batal || !ref.current) return;

      gsap.registerPlugin(ScrollTrigger);
      gsap.set(ref.current, { opacity: 0, y: 24 });

      tween = gsap.to(ref.current, {
        opacity: 1,
        y: 0,
        duration: 0.7,
        delay,
        ease: "power2.out",
        scrollTrigger: { trigger: ref.current, start: "top 85%", once: true },
      });
    })();

    return () => {
      batal = true;
      tween?.scrollTrigger?.kill();
      tween?.kill();
    };
  }, [berkurang, delay]);

  return (
    <div ref={ref} className={className}>
      {children}
    </div>
  );
}
