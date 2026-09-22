/**
 * Konfigurasi PM2 untuk menjaga proses Next.js tetap hidup.
 * Lihat docs/06-deployment.md §5.
 *
 * CARA PAKAI di server:
 *   cd /var/www/pkkmb26/frontend && npm ci && npm run build
 *   pm2 start /var/www/pkkmb26/deploy/pm2/ecosystem.config.js
 *   pm2 save && pm2 startup   (agar otomatis jalan lagi setelah reboot server)
 *
 * Setelah deploy berikutnya:
 *   pm2 reload pkkmb-web      (zero-downtime reload, BUKAN restart)
 */
module.exports = {
  apps: [
    {
      name: "pkkmb-web",
      cwd: "/var/www/pkkmb26/frontend",
      script: "npm",
      args: "start",
      // TODO: sesuaikan jumlah instance dengan jumlah core VPS setelah
      // instalasi nyata. 1 instance cukup untuk beban kecil hari-H
      // (±550 mahasiswa dalam jendela 45 menit) — jangan menaikkan tanpa
      // alasan, Next.js "start" sudah menangani banyak koneksi bersamaan
      // dalam satu proses selama tidak CPU-bound.
      instances: 1,
      exec_mode: "fork",
      env: {
        NODE_ENV: "production",
        PORT: 3000,
      },
      max_memory_restart: "512M",
      autorestart: true,
      // Kalau proses crash berulang dalam waktu singkat, JANGAN coba lagi
      // tanpa henti — itu tanda ada bug yang butuh perhatian manusia, bukan
      // restart-loop yang menyembunyikan gejalanya.
      max_restarts: 10,
      min_uptime: "30s",
      out_file: "/var/log/pkkmb26/pm2-out.log",
      error_file: "/var/log/pkkmb26/pm2-error.log",
      time: true,
    },
  ],
};
