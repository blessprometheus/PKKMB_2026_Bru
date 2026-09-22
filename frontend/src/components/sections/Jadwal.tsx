import Reveal from "@/components/animation/Reveal";
import { acara, jadwalHari1, jadwalMendatang } from "@/lib/content";

export default function Jadwal() {
  return (
    <section id="jadwal" className="mx-auto max-w-3xl px-4 py-14">
      <Reveal>
        <h2 className="text-center text-2xl font-bold">Jadwal Kegiatan</h2>
        <p className="mt-2 text-center text-sm text-pk-muted">
          Hari pertama, {acara.hariPertama.label} — {acara.lokasi}
        </p>
      </Reveal>

      <ol className="mt-8 space-y-4">
        {jadwalHari1.map((item, i) => (
          <Reveal key={item.waktu} delay={i * 0.08}>
            <li className="flex gap-4 rounded-xl border border-pk-border bg-pk-surface p-4">
              <div className="w-24 shrink-0 text-sm font-bold tabular-nums text-pk-primary sm:w-28">
                {item.waktu}
              </div>
              <div>
                <p className="font-semibold">{item.judul}</p>
                {item.catatan && <p className="mt-0.5 text-sm text-pk-muted">{item.catatan}</p>}
              </div>
            </li>
          </Reveal>
        ))}
      </ol>

      {jadwalMendatang && (
        <Reveal delay={0.24}>
          <p className="mt-6 rounded-xl border border-dashed border-pk-border p-4 text-center text-sm text-pk-muted">
            Jadwal hari berikutnya akan diumumkan menyusul oleh panitia.
          </p>
        </Reveal>
      )}
    </section>
  );
}
