import type { Metadata } from "next";
import LenisProvider from "@/components/animation/LenisProvider";
import Reveal from "@/components/animation/Reveal";
import Navbar from "@/components/Navbar";
import Footer from "@/components/Footer";
import CariNim from "@/components/CariNim";
import Hero from "@/components/sections/Hero";
import Jadwal from "@/components/sections/Jadwal";
import Faq from "@/components/sections/Faq";
import TodoSection from "@/components/sections/TodoSection";
import { acara } from "@/lib/content";

const deskripsi = `${acara.nama} — "${acara.tema}". ${acara.hariPertama.label} di ${acara.lokasi}. Cari NIM Anda untuk mengunduh nametag dan QR presensi.`;

export const metadata: Metadata = {
  // Tanpa `title` di sini: root layout sudah menetapkan "PKKMB UNINUS 2026"
  // sebagai judul default. Menuliskannya lagi akan memicu template layout
  // dan menghasilkan "PKKMB UNINUS 2026 — PKKMB UNINUS 2026".
  description: deskripsi,
  alternates: { canonical: "/" },
  openGraph: {
    type: "website",
    locale: "id_ID",
    title: acara.nama,
    description: deskripsi,
    // TODO: ganti dengan banner OG resmi (1200x630) begitu panitia menyediakan.
    images: ["/logo-pkkmb.png"],
  },
  twitter: {
    card: "summary",
    title: acara.nama,
    description: deskripsi,
    images: ["/logo-pkkmb.png"],
  },
};

/**
 * JSON-LD schema.org Event. Hanya memuat fakta yang sudah terverifikasi dari
 * bahan/README.md §4 — tidak ada tanggal/nama yang dikarang.
 */
function jsonLd() {
  return {
    "@context": "https://schema.org",
    "@type": "Event",
    name: acara.nama,
    description: deskripsi,
    startDate: acara.registrasiMulaiIso,
    endDate: acara.sidangSelesaiIso,
    eventAttendanceMode: "https://schema.org/OfflineEventAttendanceMode",
    eventStatus: "https://schema.org/EventScheduled",
    location: {
      "@type": "Place",
      name: acara.lokasi,
      address: "Universitas Islam Nusantara",
    },
    organizer: {
      "@type": "Organization",
      name: "Universitas Islam Nusantara",
      url: "https://uninus.ac.id",
    },
  };
}

export default function Beranda() {
  return (
    <LenisProvider>
      <Navbar />

      <main className="flex-1">
        <Hero />

        {/*
          Empat section di bawah (informasi, tata tertib, video, kontak) memakai
          TodoSection selama D9–D11 belum diterima (docs/01-prd.md §8). Begitu
          datanya ada, GANTI seluruh <TodoSection> itu dengan komponen section
          sungguhan — jangan hanya mengisi propertinya, boolean *Tersedia di
          content.ts hanya penanda dokumentasi, bukan saklar tampilan.
        */}
        <TodoSection
          id="informasi"
          judul="Informasi PKKMB"
          keterangan="Naskah informasi PKKMB (tujuan, ketentuan peserta) akan ditampilkan di sini setelah diterima dari panitia."
        />

        <Jadwal />

        {/* CariNim membawa <section id="cari-data"> sendiri — jangan dibungkus
            section lain, id-nya akan duplikat. */}
        <div className="mx-auto max-w-2xl px-4 py-14">
          <Reveal>
            <CariNim />
          </Reveal>
        </div>

        <TodoSection
          id="tata-tertib"
          judul="Tata Tertib"
          keterangan="Dokumen tata tertib resmi PKKMB (beserta berkas PDF yang dapat diunduh) akan ditampilkan di sini setelah diterima dari panitia."
        />

        <TodoSection
          id="video"
          judul="Video"
          keterangan="Video profil UNINUS dan sambutan LLDIKTI Wilayah IV akan ditampilkan di sini setelah tautannya diterima dari panitia."
        />

        <Faq />

        <TodoSection
          id="kontak"
          judul="Kontak Panitia"
          keterangan="Daftar narahubung panitia per divisi akan ditampilkan di sini setelah diterima dari panitia."
        />
      </main>

      <Footer />

      {/* eslint-disable-next-line react/no-danger -- data terkontrol, bukan input pengguna */}
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd()) }}
      />
    </LenisProvider>
  );
}
