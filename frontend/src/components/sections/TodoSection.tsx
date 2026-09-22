import Reveal from "@/components/animation/Reveal";

/**
 * Kerangka untuk section yang datanya BELUM diterima dari panitia.
 * Lihat AGENTS.md §4 dan docs/01-prd.md §8 — dilarang mengarang isinya.
 *
 * Ditampilkan apa adanya sebagai "menyusul", bukan disembunyikan, supaya
 * pengunjung tahu section itu memang ada tapi belum siap — bukan bug.
 */
export default function TodoSection({
  id,
  judul,
  keterangan,
}: {
  id: string;
  judul: string;
  keterangan: string;
}) {
  return (
    <section id={id} className="mx-auto max-w-2xl px-4 py-14">
      <Reveal>
        <h2 className="text-center text-2xl font-bold">{judul}</h2>
        <div className="mt-6 rounded-xl border border-dashed border-pk-border p-8 text-center">
          <p className="text-sm text-pk-muted">{keterangan}</p>
        </div>
      </Reveal>
    </section>
  );
}
