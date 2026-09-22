"use client";

import { useState } from "react";
import Reveal from "@/components/animation/Reveal";
import { faq } from "@/lib/content";

export default function Faq() {
  const [terbuka, setTerbuka] = useState<number | null>(0);

  return (
    <section id="faq" className="mx-auto max-w-2xl px-4 py-14">
      <Reveal>
        <h2 className="text-center text-2xl font-bold">Pertanyaan Umum</h2>
      </Reveal>

      <div className="mt-8 space-y-3">
        {faq.map((item, i) => {
          const buka = terbuka === i;

          return (
            <Reveal key={item.pertanyaan} delay={i * 0.06}>
              <div className="rounded-xl border border-pk-border bg-pk-surface">
                <button
                  onClick={() => setTerbuka(buka ? null : i)}
                  aria-expanded={buka}
                  aria-controls={`faq-${i}`}
                  className="flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left text-sm font-semibold"
                >
                  {item.pertanyaan}
                  <span aria-hidden="true" className="shrink-0 text-pk-primary">
                    {buka ? "−" : "+"}
                  </span>
                </button>
                {buka && (
                  <p id={`faq-${i}`} className="border-t border-pk-border px-4 py-3.5 text-sm text-pk-muted">
                    {item.jawaban}
                  </p>
                )}
              </div>
            </Reveal>
          );
        })}
      </div>
    </section>
  );
}
