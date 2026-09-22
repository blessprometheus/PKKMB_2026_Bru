import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

/**
 * Penjaga rute untuk halaman panitia.
 *
 * ⚠️ Di Next.js 16 berkas ini bernama `proxy.ts` (bukan `middleware.ts`) dan
 * fungsinya bernama `proxy`. Diverifikasi dari dokumen bawaan paket:
 * node_modules/next/dist/docs/01-app/02-guides/upgrading/version-16.md
 *
 * ⚠️ INI BUKAN PENGAMANAN. Yang diperiksa di sini hanya ADA/TIDAKNYA cookie
 * sesi — keabsahannya tidak bisa diverifikasi di sini karena sesi disimpan
 * Laravel. Siapa pun bisa memalsukan keberadaan cookie ini dan sampai ke
 * halaman admin; yang akan terjadi, setiap permintaan datanya ditolak 401/403
 * oleh backend dan halamannya kosong.
 *
 * Pengamanan yang sebenarnya ada di middleware `role` Laravel
 * (docs/04-security.md §1). Berkas ini semata-mata supaya pengunjung yang
 * belum masuk langsung diarahkan ke halaman login, bukan melihat layar kosong.
 */
const SESSION_COOKIE = process.env.NEXT_PUBLIC_SESSION_COOKIE ?? "pkkmb_session";

export function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl;

  // Halaman login sendiri harus tetap bisa dibuka tanpa sesi.
  if (pathname.startsWith("/admin/login")) {
    return NextResponse.next();
  }

  const hasSession = request.cookies.has(SESSION_COOKIE);

  if (!hasSession) {
    const loginUrl = new URL("/admin/login", request.url);

    // Simpan tujuan semula supaya setelah masuk, pengguna kembali ke halaman
    // yang tadi dituju — bukan selalu dilempar ke dashboard.
    if (pathname !== "/admin") {
      loginUrl.searchParams.set("lanjut", pathname);
    }

    return NextResponse.redirect(loginUrl);
  }

  return NextResponse.next();
}

export const config = {
  // Tanpa matcher, proxy berjalan di SETIAP permintaan termasuk aset statis —
  // dan logika pengalihan ini akan ikut memblokir CSS, JS, serta gambar.
  matcher: ["/admin/:path*", "/scan/:path*"],
};
