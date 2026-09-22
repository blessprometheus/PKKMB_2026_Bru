"use client";

import { createContext, useContext, useEffect, useRef, useState } from "react";
import type Lenis from "lenis";

/**
 * Satu-satunya tempat Lenis + GSAP ScrollTrigger disambungkan.
 * Lihat docs/05-frontend-spec.md §8.
 *
 * ⚠️ HANYA dipakai membungkus halaman PUBLIK. Dilarang dipasang di layout
 * `/admin` atau `/scan` — halaman itu dipakai di bawah tekanan dan *smooth
 * scroll* justru bisa merebut fokus dari kolom input (§8.1).
 *
 * Seluruh kode animasi hidup di folder `components/animation/` ini. Mencabut
 * animasi = menghapus folder ini + `npm uninstall gsap lenis`, tanpa
 * menyentuh markup section mana pun (§8.6).
 */

const AnimationContext = createContext<{ lenis: Lenis | null; berkurang: boolean }>({
  lenis: null,
  berkurang: false,
});

export function useAnimasi() {
  return useContext(AnimationContext);
}

export default function LenisProvider({ children }: { children: React.ReactNode }) {
  const [lenis, setLenis] = useState<Lenis | null>(null);
  const [berkurang, setBerkurang] = useState(false);
  const lenisRef = useRef<Lenis | null>(null);

  useEffect(() => {
    const mq = window.matchMedia("(prefers-reduced-motion: reduce)");
    const kurangiGerak = mq.matches;
    setBerkurang(kurangiGerak);

    // Kalau pengguna meminta gerak dikurangi, Lenis TIDAK diinisialisasi
    // sama sekali — bukan sekadar durasi disetel 0. Gulir kembali ke
    // perilaku asli browser. Alasannya medis, bukan selera: gulir yang
    // dibajak memicu pusing pada gangguan vestibular (§8.2).
    //
    // Diverifikasi dari sumber Lenis 1.3.26: opsi `respectReducedMotion`
    // bawaan hanya memengaruhi scrollTo() terprogram, TIDAK mematikan
    // pembajakan wheel/touch — jadi pengecekan manual ini tetap wajib.
    if (kurangiGerak) return;

    let batal = false;
    let bersihkanTicker: (() => void) | null = null;

    (async () => {
      const [{ default: LenisCtor }, { default: gsap }, { default: ScrollTrigger }] =
        await Promise.all([
          import("lenis"),
          import("gsap"),
          import("gsap/ScrollTrigger"),
        ]);

      if (batal) return;

      gsap.registerPlugin(ScrollTrigger);

      const instance = new LenisCtor({ autoRaf: false });
      lenisRef.current = instance;
      setLenis(instance);

      // Wajib disambungkan ke ScrollTrigger, kalau tidak ScrollTrigger salah
      // menghitung posisi (§8.3).
      instance.on("scroll", ScrollTrigger.update);

      const tick = (time: number) => instance.raf(time * 1000);
      gsap.ticker.add(tick);
      gsap.ticker.lagSmoothing(0);

      bersihkanTicker = () => gsap.ticker.remove(tick);
    })();

    return () => {
      batal = true;
      bersihkanTicker?.();
      lenisRef.current?.destroy();
      lenisRef.current = null;
    };
    // Sengaja hanya berjalan sekali per mount halaman publik.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <AnimationContext.Provider value={{ lenis, berkurang }}>{children}</AnimationContext.Provider>
  );
}
