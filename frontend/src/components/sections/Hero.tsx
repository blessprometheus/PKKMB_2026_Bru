import AnchorLink from "@/components/animation/AnchorLink";
import Countdown from "@/components/Countdown";
import { acara } from "@/lib/content";

/**
 * SENGAJA tidak dibungkus <Reveal> — LCP (judul & CTA) tidak boleh menunggu
 * animasi (docs/05-frontend-spec.md §8.4). Hero harus tampil terbaca
 * seketika, animasi hanya untuk section di bawahnya.
 */
export default function Hero() {
  return (
    <section
      id="beranda"
      className="bg-pk-gradient relative overflow-hidden px-4 py-16 text-white sm:py-20"
      style={{ background: "linear-gradient(135deg, #008F4F, #00B060)" }}
    >
      <div className="relative mx-auto max-w-3xl text-center">
        <span className="inline-block rounded-full bg-white/15 px-4 py-1.5 text-xs font-bold uppercase tracking-widest backdrop-blur-sm">
          Tahun Akademik {acara.tahunAkademik}
        </span>

        <h1 className="mt-5 text-3xl font-black leading-tight sm:text-5xl">{acara.nama}</h1>

        <p className="mx-auto mt-4 max-w-xl text-base font-medium text-white/95 sm:text-lg">
          &ldquo;{acara.tema}&rdquo;
        </p>

        <p className="mt-5 text-sm font-semibold sm:text-base">
          {acara.hariPertama.label} &middot; {acara.lokasiSingkat}
        </p>

        <div className="mt-6 flex justify-center">
          <Countdown targetIso={acara.registrasiMulaiIso} />
        </div>

        <div className="mt-8 flex flex-wrap justify-center gap-3">
          <AnchorLink
            href="#cari-data"
            className="rounded-lg bg-white px-6 py-3 text-sm font-bold text-pk-primary shadow-lg transition hover:bg-white/90"
          >
            Cari Data &amp; Unduh Nametag
          </AnchorLink>
          <AnchorLink
            href="#jadwal"
            className="rounded-lg border-2 border-white/70 px-6 py-3 text-sm font-bold text-white transition hover:bg-white/10"
          >
            Lihat Jadwal
          </AnchorLink>
        </div>
      </div>
    </section>
  );
}
