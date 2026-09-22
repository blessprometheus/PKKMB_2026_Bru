"use client";

import { useEffect, useState } from "react";

type Sisa = { hari: number; jam: number; menit: number; detik: number };

function hitungSisa(targetIso: string): Sisa | null {
  const bedaMs = new Date(targetIso).getTime() - Date.now();
  if (bedaMs <= 0) return null;

  const detikTotal = Math.floor(bedaMs / 1000);

  return {
    hari: Math.floor(detikTotal / 86400),
    jam: Math.floor((detikTotal % 86400) / 3600),
    menit: Math.floor((detikTotal % 3600) / 60),
    detik: detikTotal % 60,
  };
}

/**
 * Hitung mundur ke pembukaan registrasi. Lihat docs/01-prd.md §5 (S1).
 *
 * Render awal SELALU "..." di server (menghindari mismatch hidrasi, karena
 * server dan klien punya `Date.now()` yang berbeda), lalu terisi begitu
 * komponen aktif di browser.
 */
export default function Countdown({ targetIso }: { targetIso: string }) {
  const [sisa, setSisa] = useState<Sisa | null | undefined>(undefined);

  useEffect(() => {
    setSisa(hitungSisa(targetIso));
    const interval = setInterval(() => setSisa(hitungSisa(targetIso)), 1000);
    return () => clearInterval(interval);
  }, [targetIso]);

  const kotak = [
    { label: "Hari", nilai: sisa?.hari },
    { label: "Jam", nilai: sisa?.jam },
    { label: "Menit", nilai: sisa?.menit },
    { label: "Detik", nilai: sisa?.detik },
  ];

  if (sisa === null) {
    return (
      <p className="rounded-full bg-pk-secondary px-5 py-2 text-sm font-bold text-pk-text">
        Registrasi sedang berlangsung / telah selesai
      </p>
    );
  }

  return (
    <div
      className="flex gap-3 sm:gap-4"
      role="timer"
      aria-live="off"
      aria-label={
        sisa
          ? `${sisa.hari} hari ${sisa.jam} jam ${sisa.menit} menit menuju registrasi`
          : "Memuat hitung mundur"
      }
    >
      {kotak.map((k) => (
        <div
          key={k.label}
          className="flex w-14 flex-col items-center rounded-xl bg-white/15 py-2.5 backdrop-blur-sm sm:w-16"
        >
          <span className="text-xl font-black tabular-nums sm:text-2xl">
            {k.nilai !== undefined ? String(k.nilai).padStart(2, "0") : "--"}
          </span>
          <span className="text-[10px] font-semibold uppercase tracking-wide opacity-90">
            {k.label}
          </span>
        </div>
      ))}
    </div>
  );
}
