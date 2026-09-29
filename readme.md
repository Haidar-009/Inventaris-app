# Inventaris-app (Dalam Pengembangan)

Aplikasi web untuk membantu pendataan inventaris barang di gudang sekolah (sarpras).

## Fitur

- **Login & Logout**: akses aplikasi dibatasi untuk pengguna yang sudah masuk.
- **Dashboard (Beranda)**: ringkasan total barang masuk dan barang keluar.
- **Riwayat Transaksi**: daftar transaksi terakhir beserta stok barang saat ini.
- **Pencarian & Filter**: cari berdasarkan nama barang, filter jenis transaksi (masuk/keluar) dan kategori.
- **Pendataan Barang**: mencatat transaksi barang masuk, keluar, dan peminjaman.
- **Statistik & Data**: melihat data barang.
- **Update Data**: mengubah data barang.
- **Ekspor Laporan**: unduh laporan dalam format PDF, Word, atau Excel.
- **Responsive**: tampilan menyesuaikan layar HP dan desktop.

## Teknologi

PHP (OOP), MySQL, HTML, CSS, JavaScript, Boxicons.

## Cara Clone / Pull

```bash
# Clone repository
git clone https://github.com/Haidar-009/Inventaris-app.git

# Ambil pembaruan terbaru (jika sudah pernah clone)
cd Inventaris-app
git pull
```

## Cara Menjalankan

1. Pindahkan folder proyek ke `htdocs` (XAMPP) atau `www` (Laragon).
2. Nyalakan **Apache** dan **MySQL**.
3. Buat database di phpMyAdmin, lalu impor file `.sql` dari proyek ini.
4. Sesuaikan pengaturan database di `koneksi.php`.
5. Buka `http://localhost/Inventaris-app` di browser.

## Pengembang

- **Haidar-009** – [github.com/Haidar-009](https://github.com/Haidar-009)
