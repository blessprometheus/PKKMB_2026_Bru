import type { NextConfig } from "next";

/**
 * Di produksi, Nginx yang meneruskan /api/* ke Laravel sehingga frontend dan API
 * benar-benar satu origin (docs/06-deployment.md §3).
 *
 * Di lokal, Next berjalan di :3000 dan Laravel di :8000. Tanpa penanganan khusus
 * keduanya beda origin, dan kita harus melonggarkan CORS — yang justru dilarang
 * docs/04-security.md §7. Rewrite di bawah membuat browser HANYA berbicara ke
 * :3000, lalu Next yang meneruskan ke Laravel. Hasilnya: lingkungan lokal
 * berperilaku sama persis dengan produksi, dan CORS tidak perlu disentuh.
 */
const backendOrigin = process.env.BACKEND_ORIGIN ?? "http://127.0.0.1:8000";

const nextConfig: NextConfig = {
  async rewrites() {
    return [
      { source: "/api/:path*", destination: `${backendOrigin}/api/:path*` },
      { source: "/sanctum/:path*", destination: `${backendOrigin}/sanctum/:path*` },
    ];
  },
};

export default nextConfig;
