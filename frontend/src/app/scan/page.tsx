"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { api, ApiError } from "@/lib/api";
import type { ScanContext, ScanResult, RecentScan } from "@/lib/types";

/**
 * Halaman registrasi daftar hadir. Lihat docs/05-frontend-spec.md §4.
 *
 * Dipakai sambil berdiri menghadapi antrean, oleh petugas yang baru diajari
 * lima menit sebelumnya. Dokumen panitia memberi jendela 45 menit untuk ±550
 * mahasiswa di 4 titik (bahan/README.md §4) — jadi yang diutamakan di sini
 * adalah kecepatan, fokus yang tidak pernah lepas, dan kegagalan yang terlihat
 * seketika.
 *
 * Halaman ini SENGAJA tanpa animasi, tanpa Lenis, tanpa GSAP.
 */

/** Jeda tanpa ketikan baru yang dianggap "tembakan scanner selesai". */
const JEDA_SELESAI_MS = 80;

/** Abaikan kiriman identik dalam jendela ini — mencegah tembakan ganda. */
const JENDELA_DUPLIKAT_MS = 1500;

type Nada = "sukses" | "ulang" | "gagal";

/**
 * Bunyi dibuat lewat Web Audio API, bukan berkas suara.
 *
 * Di aula yang ramai, telinga petugas lebih cepat daripada matanya. Tiga nada
 * yang jelas berbeda membuat mereka tahu hasilnya tanpa menatap layar.
 */
function bunyikan(nada: Nada) {
  try {
    const AudioCtx =
      window.AudioContext ?? (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext;
    const ctx = new AudioCtx();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();

    const pola: Record<Nada, [number, number, number]> = {
      sukses: [880, 1320, 0.12], // nada naik
      ulang: [660, 660, 0.18], // datar
      gagal: [440, 220, 0.3], // turun
    };

    const [awal, akhir, durasi] = pola[nada];

    osc.frequency.setValueAtTime(awal, ctx.currentTime);
    osc.frequency.linearRampToValueAtTime(akhir, ctx.currentTime + durasi);
    gain.gain.setValueAtTime(0.18, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + durasi);

    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + durasi);
    osc.onended = () => ctx.close();
  } catch {
    // Bunyi adalah pelengkap, bukan syarat. Kegagalannya tidak boleh
    // menghentikan pencatatan kehadiran.
  }
}

const TAMPILAN: Record<string, { warna: string; ikon: string }> = {
  recorded: { warna: "bg-pk-success", ikon: "✓" },
  duplicate: { warna: "bg-pk-warning", ikon: "!" },
  unknown_token: { warna: "bg-pk-danger", ikon: "✕" },
  session_closed: { warna: "bg-pk-danger", ikon: "✕" },
  gagal_kirim: { warna: "bg-pk-danger", ikon: "✕" },
};

export default function HalamanScan() {
  const router = useRouter();

  const [konteks, setKonteks] = useState<ScanContext | null>(null);
  const [sesiId, setSesiId] = useState<number | null>(null);
  const [titik, setTitik] = useState("");
  const [hasil, setHasil] = useState<(ScanResult & { result: string }) | null>(null);
  const [terakhir, setTerakhir] = useState<RecentScan[]>([]);
  const [terhubung, setTerhubung] = useState(true);
  const [bisukan, setBisukan] = useState(false);
  const [modeManual, setModeManual] = useState(false);
  const [nilaiInput, setNilaiInput] = useState("");

  const inputRef = useRef<HTMLInputElement>(null);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const terakhirDikirim = useRef<{ nilai: string; waktu: number }>({ nilai: "", waktu: 0 });

  // ── Muat konteks ────────────────────────────────────────────────────
  useEffect(() => {
    api
      .get<ScanContext>("/api/v1/scan/context")
      .then((data) => {
        setKonteks(data);
        const aktif = data.sessions.find((s) => s.is_open_now) ?? data.sessions.find((s) => s.is_active);
        setSesiId(aktif?.id ?? data.sessions[0]?.id ?? null);
      })
      .catch((error) => {
        if (error instanceof ApiError && (error.status === 401 || error.status === 403)) {
          router.replace("/admin/login?lanjut=/scan");
        } else {
          setTerhubung(false);
        }
      });
  }, [router]);

  const muatTerakhir = useCallback(async (id: number) => {
    try {
      setTerakhir(await api.get<RecentScan[]>(`/api/v1/scan/recent?session_id=${id}&limit=10`));
    } catch {
      /* daftar riwayat bukan hal kritis; kegagalannya tidak perlu menakuti petugas */
    }
  }, []);

  useEffect(() => {
    if (sesiId !== null) void muatTerakhir(sesiId);
  }, [sesiId, muatTerakhir]);

  // ── Fokus tidak boleh lepas ─────────────────────────────────────────
  /*
   * Scanner gun mengetik ke elemen yang sedang fokus. Kalau petugas tidak
   * sengaja mengklik di tempat lain, tembakan berikutnya menghilang entah ke
   * mana — dan tidak ada yang menyadarinya sampai ada mahasiswa protes.
   */
  useEffect(() => {
    const fokuskan = () => {
      if (!modeManual) inputRef.current?.focus();
    };

    fokuskan();
    const interval = setInterval(fokuskan, 700);
    window.addEventListener("click", fokuskan);

    return () => {
      clearInterval(interval);
      window.removeEventListener("click", fokuskan);
    };
  }, [modeManual]);

  // ── Pengiriman ──────────────────────────────────────────────────────
  const kirim = useCallback(
    async (nilai: string) => {
      const bersih = nilai.trim();
      if (bersih === "" || sesiId === null) return;

      const sekarang = Date.now();
      if (
        bersih === terakhirDikirim.current.nilai &&
        sekarang - terakhirDikirim.current.waktu < JENDELA_DUPLIKAT_MS
      ) {
        setNilaiInput("");
        return;
      }
      terakhirDikirim.current = { nilai: bersih, waktu: sekarang };

      setNilaiInput("");

      const manual = modeManual || !/^[0-9a-f]{32}$/.test(bersih);

      try {
        const data = await api.post<ScanResult>(manual ? "/api/v1/scan/manual" : "/api/v1/scan", {
          ...(manual ? { nim: bersih } : { token: bersih }),
          attendance_session_id: sesiId,
          device_label: titik || null,
        });

        setTerhubung(true);
        setHasil(data);

        if (!bisukan) {
          bunyikan(
            data.result === "recorded" ? "sukses" : data.result === "duplicate" ? "ulang" : "gagal",
          );
        }

        if (data.result === "recorded") void muatTerakhir(sesiId);
      } catch (error) {
        /*
         * Kegagalan HARUS terlihat seketika. Halaman ini tidak boleh gagal
         * diam-diam: kalau petugas mengira tercatat padahal tidak, kesalahannya
         * baru ketahuan setelah acara bubar (docs/05-frontend-spec.md §4.3).
         */
        setTerhubung(false);
        setHasil({
          result: "gagal_kirim",
          message: "GAGAL TERKIRIM — CATAT MANUAL",
          student: null,
          scanned_at: null,
        });

        if (!bisukan) bunyikan("gagal");

        if (error instanceof ApiError && error.status === 401) {
          router.replace("/admin/login?lanjut=/scan");
        }
      }
    },
    [sesiId, titik, modeManual, bisukan, muatTerakhir, router],
  );

  /*
   * Sebagian scanner tidak dikonfigurasi mengirim Enter. Karena itu pengiriman
   * juga dipicu oleh jeda singkat tanpa ketikan baru — jangan mengandalkan
   * Enter saja (docs/05-frontend-spec.md §4.1 butir 2).
   */
  function saatKetik(nilai: string) {
    setNilaiInput(nilai);

    if (modeManual) return; // mode manual menunggu petugas menekan tombol

    if (timerRef.current) clearTimeout(timerRef.current);
    timerRef.current = setTimeout(() => void kirim(nilai), JEDA_SELESAI_MS);
  }

  function saatSubmit(event: React.FormEvent) {
    event.preventDefault();
    if (timerRef.current) clearTimeout(timerRef.current);
    void kirim(nilaiInput);
  }

  const sesiAktif = konteks?.sessions.find((s) => s.id === sesiId);
  const tampilan = hasil ? (TAMPILAN[hasil.result] ?? TAMPILAN.unknown_token) : null;

  if (!konteks) {
    return (
      <main className="flex min-h-dvh items-center justify-center">
        <p className="text-sm text-pk-muted">Memuat…</p>
      </main>
    );
  }

  return (
    <main className="flex min-h-dvh flex-col bg-pk-bg">
      {/* ── Bilah atas ── */}
      <header className="border-b border-pk-border bg-pk-surface px-4 py-3">
        <div className="mx-auto flex max-w-5xl flex-wrap items-center gap-x-4 gap-y-2 text-sm">
          <label className="flex items-center gap-2">
            <span className="font-semibold">Sesi:</span>
            <select
              value={sesiId ?? ""}
              onChange={(e) => setSesiId(Number(e.target.value))}
              className="rounded-lg border border-pk-border bg-pk-surface px-2 py-1.5"
            >
              {konteks.sessions.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                  {s.is_open_now ? "" : " (tutup)"}
                </option>
              ))}
            </select>
          </label>

          <label className="flex items-center gap-2">
            <span className="font-semibold">Titik:</span>
            <input
              value={titik}
              onChange={(e) => setTitik(e.target.value)}
              placeholder="Pintu A"
              className="w-28 rounded-lg border border-pk-border bg-pk-surface px-2 py-1.5"
            />
          </label>

          <span className="text-pk-muted">Petugas: {konteks.operator.name}</span>

          {/* Indikator ini harus selalu terlihat. Petugas perlu tahu detik itu
              juga kalau koneksi putus, bukan setelah 50 orang lewat. */}
          <span
            className={`ml-auto flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold ${
              terhubung ? "bg-pk-success/15 text-pk-success" : "bg-pk-danger/15 text-pk-danger"
            }`}
          >
            <span aria-hidden="true">●</span>
            {terhubung ? "Terhubung" : "TERPUTUS"}
          </span>

          <button
            onClick={() => setBisukan((b) => !b)}
            className="rounded-lg border border-pk-border px-2.5 py-1.5 text-xs font-semibold"
          >
            {bisukan ? "🔇 Bisu" : "🔊 Bunyi"}
          </button>
        </div>

        {sesiAktif && !sesiAktif.is_open_now && (
          <p
            role="alert"
            className="mx-auto mt-2 max-w-5xl rounded-lg bg-pk-danger/10 px-3 py-2 text-sm font-semibold text-pk-danger"
          >
            Sesi ini sedang tidak dibuka — pemindaian akan ditolak. Periksa jadwal sesi di dasbor admin.
          </p>
        )}
      </header>

      {/* ── Layar hasil ── */}
      <section className="flex flex-1 items-center justify-center p-4">
        <div className="w-full max-w-3xl">
          <form onSubmit={saatSubmit}>
            <label htmlFor="pindai" className="sr-only">
              {modeManual ? "Masukkan NIM" : "Pindai QR"}
            </label>
            <input
              id="pindai"
              ref={inputRef}
              value={nilaiInput}
              onChange={(e) => saatKetik(e.target.value)}
              autoComplete="off"
              inputMode={modeManual ? "numeric" : "none"}
              placeholder={modeManual ? "Ketik NIM lalu tekan Enter" : "Siap memindai…"}
              /* Layar ini menghadap antrean — token tidak boleh terbaca orang lain. */
              style={modeManual ? undefined : { WebkitTextSecurity: "disc" } as React.CSSProperties}
              className="w-full rounded-xl border-2 border-pk-primary bg-pk-surface px-4 py-3 text-center text-lg tracking-widest outline-none"
            />
          </form>

          <div
            aria-live="assertive"
            className={`mt-4 rounded-2xl p-8 text-center text-white transition-colors ${
              tampilan ? tampilan.warna : "bg-pk-surface-2"
            }`}
          >
            {hasil && tampilan ? (
              <>
                <p className="text-6xl leading-none" aria-hidden="true">
                  {tampilan.ikon}
                </p>
                <p className="mt-3 text-3xl font-black tracking-wide sm:text-4xl">{hasil.message}</p>

                {hasil.student && (
                  <div className="mt-4">
                    <p className="text-2xl font-bold sm:text-3xl">{hasil.student.name}</p>
                    <p className="mt-1 font-mono text-lg tabular-nums opacity-90">{hasil.student.nim}</p>
                    <p className="mt-1 text-sm opacity-90">
                      {hasil.student.study_program}
                      {hasil.student.group_name ? ` · ${hasil.student.group_name}` : ""}
                    </p>
                  </div>
                )}
              </>
            ) : (
              <p className="text-lg text-pk-muted">Menunggu pemindaian pertama…</p>
            )}
          </div>

          <div className="mt-4 flex flex-wrap gap-2">
            <button
              onClick={() => {
                setModeManual((m) => !m);
                setNilaiInput("");
                setTimeout(() => inputRef.current?.focus(), 0);
              }}
              className="rounded-lg border-2 border-pk-primary px-4 py-2.5 text-sm font-bold text-pk-primary"
            >
              {modeManual ? "← Kembali ke mode pindai QR" : "Input NIM manual"}
            </button>

            {modeManual && (
              <button
                onClick={() => void kirim(nilaiInput)}
                className="rounded-lg bg-pk-primary px-5 py-2.5 text-sm font-bold text-white"
              >
                Catat kehadiran
              </button>
            )}
          </div>
        </div>
      </section>

      {/* ── Riwayat: bukti visual bahwa sistem masih hidup ── */}
      <footer className="border-t border-pk-border bg-pk-surface px-4 py-3">
        <div className="mx-auto max-w-5xl">
          <h2 className="text-xs font-bold uppercase tracking-wide text-pk-muted">
            10 pemindaian terakhir
          </h2>
          {terakhir.length === 0 ? (
            <p className="mt-1 text-sm text-pk-muted">Belum ada.</p>
          ) : (
            <ul className="mt-1 max-h-32 space-y-0.5 overflow-auto text-sm">
              {terakhir.map((t, i) => (
                <li key={`${t.nim}-${i}`} className="flex gap-3">
                  <span className="tabular-nums text-pk-muted">
                    {t.scanned_at
                      ? new Date(t.scanned_at).toLocaleTimeString("id-ID", {
                          hour: "2-digit",
                          minute: "2-digit",
                          second: "2-digit",
                        })
                      : "—"}
                  </span>
                  <span className="flex-1 truncate">{t.name}</span>
                  <span className="font-mono text-xs tabular-nums text-pk-muted">{t.nim}</span>
                  {t.method === "manual" && (
                    <span className="text-xs font-semibold text-pk-warning">manual</span>
                  )}
                </li>
              ))}
            </ul>
          )}
        </div>
      </footer>
    </main>
  );
}
