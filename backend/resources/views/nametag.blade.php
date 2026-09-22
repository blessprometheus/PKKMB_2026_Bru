{{--
    Nametag peserta PKKMB UNINUS 2026 — A6 potret (105 × 148 mm).
    Lihat docs/05-frontend-spec.md §6.

    TODO (D3/D5): ketentuan desain resmi dan logo UNINUS resolusi tinggi belum
    diterima. Tata letak di bawah adalah rancangan; JANGAN memasang logo atau
    lambang karangan di dokumen resmi universitas.

    Dibuat ramah tinta: latar putih, tanpa gradien sehalaman penuh — sebagian
    besar mahasiswa akan mencetaknya sendiri di printer seadanya.
--}}
<!DOCTYPE html>
<html lang="id-ID">
<head>
    <meta charset="utf-8">
    <title>Nametag {{ $student->nim }}</title>
    <style>
        @page { size: 105mm 148mm; margin: 0; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "DejaVu Sans", sans-serif;
            color: #212529;
            width: 105mm;
            height: 148mm;
        }

        .pita {
            background: #008F4F;
            color: #ffffff;
            padding: 6mm 6mm 5mm;
            text-align: center;
        }

        .pita .kegiatan { font-size: 15pt; font-weight: bold; letter-spacing: .5pt; }
        .pita .lembaga { font-size: 7.5pt; margin-top: 1.5mm; }

        /* Ruang untuk lubang tali. Tanpa ini, lubang akan menembus nama. */
        .lubang-tali { height: 9mm; }

        .isi { padding: 0 7mm; text-align: center; }

        .nama {
            font-size: 17pt;
            font-weight: bold;
            line-height: 1.15;
            margin: 0;
            /* Nama panjang dikecilkan lewat PHP, bukan dipotong — nama orang
               tidak boleh terpotong di tanda pengenal resmi. */
        }

        .nim {
            font-family: "DejaVu Sans Mono", monospace;
            font-size: 11pt;
            margin-top: 2mm;
            letter-spacing: .5pt;
        }

        .prodi { font-size: 8.5pt; color: #495057; margin-top: 2.5mm; line-height: 1.35; }

        .kelompok {
            display: inline-block;
            margin-top: 3mm;
            padding: 1.2mm 4mm;
            background: #FBC02D;
            /* Teks GELAP di atas emas — emas dilarang untuk teks terang,
               kontrasnya terlalu rendah (docs/05-frontend-spec.md §2). */
            color: #212529;
            font-size: 8pt;
            font-weight: bold;
            border-radius: 2mm;
        }

        .area-qr { text-align: center; margin-top: 4mm; }

        /* Minimal 3 × 3 cm saat dicetak (docs/04-security.md §3.3). */
        .area-qr img { width: 32mm; height: 32mm; }

        .petunjuk { font-size: 6.5pt; color: #6C757D; margin-top: 1.5mm; }

        .kaki {
            position: absolute;
            bottom: 4mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 6pt;
            color: #ADB5BD;
        }
    </style>
</head>
<body>
    <div class="pita">
        <div class="kegiatan">PKKMB 2026</div>
        <div class="lembaga">UNIVERSITAS ISLAM NUSANTARA</div>
    </div>

    <div class="lubang-tali"></div>

    <div class="isi">
        <p class="nama" style="font-size: {{ $ukuranNama }}pt;">{{ $student->name }}</p>

        <div class="nim">{{ $student->nim }}</div>

        <div class="prodi">
            {{ $student->study_program }}<br>
            {{ $student->faculty }}
        </div>

        @if ($student->group_name)
            <div class="kelompok">{{ $student->group_name }}</div>
        @endif

        <div class="area-qr">
            <img src="{{ $qrDataUri }}" alt="Kode QR presensi {{ $student->nim }}">
            <div class="petunjuk">Tunjukkan nametag ini kepada petugas untuk dipindai</div>
        </div>
    </div>

    <div class="kaki">Nametag resmi PKKMB UNINUS 2026 — jangan dipinjamkan kepada orang lain</div>
</body>
</html>
