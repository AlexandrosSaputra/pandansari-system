# Dokumentasi Pandansari System

Dokumentasi teknis ini menjelaskan kondisi proyek per 24 Oktober 2026 untuk tim pengembang. Dokumen membedakan fitur yang sudah ada di repositori dengan target yang masih direncanakan pada proposal capstone.

## Isi

- [Arsitektur dan alur data](architecture.md) - batas komponen saat ini, model data utama, dan arsitektur target.
- [Menjalankan proyek](development-guide.md) - prasyarat, setup lokal, server, dan pemeriksaan dasar.
- [Kontrak alur inti](domain-flows.md) - aturan role, POS, stok, transfer, dan batas integrasi scanner.
- [Panduan integrasi scanner ESP32](integrasi-scanner-esp32.md) - kontrak API, autentikasi, `scan_id`, retry, contoh firmware, dan checklist tim IoT.
- [Status implementasi](implementation-status.md) - pemetaan kebutuhan proposal ke kondisi kode saat ini.
- [Palet warna light-mode](palet-warna.md) - peran warna Pandansari, token, kontras, dan batas verifikasi tampilan.
- [Roadmap implementasi](roadmap.md) - urutan kerja yang direkomendasikan untuk menyelesaikan target capstone.
- [Rencana verifikasi](verification.md) - bukti verifikasi saat ini dan skenario pengujian berikutnya.
- [Keputusan dan pertanyaan terbuka](decisions.md) - keputusan yang sudah disepakati dan hal yang masih harus diputuskan tim.

## Artefak penyerahan tim IoT

- [Panduan integrasi scanner ESP32 (Word)](<Panduan Integrasi Scanner ESP32.docx>) - dokumen siap dibagikan kepada pengembang Arduino dan ESP32.
- [Paket panduan integrasi scanner ESP32 (ZIP)](<Panduan Integrasi Scanner ESP32.zip>) - berisi sumber Markdown dan dokumen Word.

## Batas dokumen

Dokumen ini bukan SKPL atau DPPL final dan tidak menggantikan proposal. Stock opname tidak termasuk lingkup website. Keputusan yang masih memerlukan konfirmasi, khususnya sumber data AI, perangkat scanner, safety stock, dan aturan pembayaran, dicatat di [daftar keputusan](decisions.md) sebelum diimplementasikan.
