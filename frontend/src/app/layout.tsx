import type { Metadata } from "next";
import { Plus_Jakarta_Sans } from "next/font/google";
import "./globals.css";

/**
 * Plus Jakarta Sans adalah font identitas UNINUS (docs/05-frontend-spec.md §2).
 * Dimuat lewat next/font agar tidak ada pergeseran tata letak saat font tiba.
 */
const jakarta = Plus_Jakarta_Sans({
  variable: "--font-jakarta",
  subsets: ["latin"],
  display: "swap",
});

export const metadata: Metadata = {
  // TODO: [D12] domain produksi belum final — docs/06-deployment.md merancang
  // pkkmb.uninus.ac.id. Setel NEXT_PUBLIC_SITE_URL di .env begitu domain pasti.
  metadataBase: new URL(process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000"),
  title: {
    default: "PKKMB UNINUS 2026",
    template: "%s — PKKMB UNINUS 2026",
  },
  description:
    "Informasi resmi Pengenalan Kehidupan Kampus bagi Mahasiswa Baru Universitas Islam Nusantara 2026.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    // lang="id-ID": seluruh konten berbahasa Indonesia (AGENTS.md §1).
    // Sengaja TANPA data-scroll-behavior="smooth" — atribut itu menghidupkan
    // penimpaan scroll oleh Next.js dan akan bertabrakan dengan Lenis nanti
    // (docs/05-frontend-spec.md §8.3).
    <html lang="id-ID" className={`${jakarta.variable} h-full antialiased`}>
      <body className="min-h-full flex flex-col font-sans">{children}</body>
    </html>
  );
}
