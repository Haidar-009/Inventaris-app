<?php
    session_start();
    include 'koneksi.php';

    // Cek apakah user sudah login. Jika belum, kembali ke login.php
    if(!isset($_SESSION['username'])) {
        header("Location: login.php");
        exit();
    }

    // Mengambil nama lengkap dari session 
    $nama_user = isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : $_SESSION['username'];

    // Helper: escape output HTML (mencegah XSS)
    function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

    // CLASS OOP
    /**
     * TransaksiFilter
     * Menyimpan & membungkus (enkapsulasi) parameter pencarian, filter, dan limit.
     */
    class TransaksiFilter {
        private $q;
        private $jenis;
        private $kategori;
        private $limit;

        public function __construct(array $input) {
            $this->q        = trim($input['q'] ?? '');
            $this->jenis    = $input['jenis'] ?? '';
            $this->kategori = $input['kategori'] ?? '';
            $this->limit    = max(10, min(200, (int)($input['limit'] ?? 10)));
        }

        public function getQ()        { return $this->q; }
        public function getJenis()    { return $this->jenis; }
        public function getKategori() { return $this->kategori; }
        public function getLimit()    { return $this->limit; }

        // Apakah ada pencarian/filter yang sedang dipakai?
        public function isAktif() {
            return $this->q !== '' || $this->jenis !== '' || $this->kategori !== '';
        }

        // Parameter URL (hanya yang terisi)
        public function toArray() {
            return array_filter([
                'q' => $this->q, 'jenis' => $this->jenis, 'kategori' => $this->kategori
            ], 'strlen');
        }
    }


    /**
     * DashboardManager
     * Semua query database untuk dashboard.
     */
    class DashboardManager {
        const MASUK  = ['Barang Masuk', 'Pemasukan'];
        const KELUAR = ['Barang Keluar', 'Peminjaman'];

        private $db;

        public function __construct($koneksi) {
            $this->db = $koneksi;
        }

        // Ambil 1 angka dari query (SUM / COUNT), default 0
        private function scalar($sql) {
            $r   = $this->db->query($sql);
            $row = $r ? $r->fetch_row() : null;
            return ($row && $row[0]) ? $row[0] : 0;
        }

        // Total kuantitas berdasarkan kelompok jenis transaksi
        private function totalJenis($list) {
            $in = "'" . implode("','", $list) . "'";
            return $this->scalar("SELECT SUM(d.Kuantitas)
                FROM detail_pendataan d
                JOIN pendataan_barang p ON d.Id_Transaksi = p.Id_Transaksi
                WHERE p.Jenis_transaksi IN ($in)");
        }

        public function getTotalStok()   { return $this->scalar("SELECT SUM(Stok) FROM barang"); }
        public function getTotalMasuk()  { return $this->totalJenis(self::MASUK); }
        public function getTotalKeluar() { return $this->totalJenis(self::KELUAR); }
        public function getTotalRestok() { return $this->scalar("SELECT COUNT(*) FROM barang WHERE Stok < 5"); }

        // Daftar kategori untuk dropdown filter
        public function getKategori() {
            $out = [];
            $r = $this->db->query("SELECT DISTINCT Jenis_barang FROM barang ORDER BY Jenis_barang");
            while ($r && $row = $r->fetch_row()) $out[] = $row[0];
            return $out;
        }

        // Susun WHERE + parameter (prepared statement)
        private function buatWhere(TransaksiFilter $f, &$types, &$params) {
            $where = [];
            if ($f->getQ() !== '') {
                $where[]  = "b.Nama_barang LIKE ?";
                $types   .= 's';
                $params[] = '%' . addcslashes($f->getQ(), '%_\\') . '%';
            }
            if ($f->getJenis() === 'masuk' || $f->getJenis() === 'keluar') {
                $list    = ($f->getJenis() === 'masuk') ? self::MASUK : self::KELUAR;
                $where[] = "p.Jenis_transaksi IN (" . implode(',', array_fill(0, count($list), '?')) . ")";
                foreach ($list as $j) { $types .= 's'; $params[] = $j; }
            }
            if ($f->getKategori() !== '') {
                $where[]  = "b.Jenis_barang = ?";
                $types   .= 's';
                $params[] = $f->getKategori();
            }
            return $where ? 'WHERE ' . implode(' AND ', $where) : '';
        }

        private function jalankan($sql, $types, $params) {
            $stmt = $this->db->prepare($sql);
            if (!$stmt) return false;
            if ($params) $stmt->bind_param($types, ...$params);
            $stmt->execute();
            return $stmt->get_result();
        }

        // Riwayat transaksi. $semua = true -> tanpa LIMIT (dipakai ekspor)
        public function getTransaksi(TransaksiFilter $f, $semua = false) {
            $types = ''; $params = [];
            $where = $this->buatWhere($f, $types, $params);
            $sql = "SELECT b.Nama_barang, b.Jenis_barang, b.Stok, p.Tanggal, d.Kuantitas, p.Jenis_transaksi
                    FROM detail_pendataan d
                    JOIN pendataan_barang p ON d.Id_Transaksi = p.Id_Transaksi
                    JOIN barang b ON d.id_barang = b.Id_barang
                    $where
                    ORDER BY p.Tanggal DESC, p.Id_Transaksi DESC";
            if (!$semua) $sql .= " LIMIT " . (int)$f->getLimit();

            $data = [];
            $r = $this->jalankan($sql, $types, $params);
            while ($r && $row = $r->fetch_assoc()) $data[] = $row;
            return $data;
        }

        public function countTransaksi(TransaksiFilter $f) {
            $types = ''; $params = [];
            $where = $this->buatWhere($f, $types, $params);
            $r = $this->jalankan("SELECT COUNT(*) FROM detail_pendataan d
                    JOIN pendataan_barang p ON d.Id_Transaksi = p.Id_Transaksi
                    JOIN barang b ON d.id_barang = b.Id_barang $where", $types, $params);
            $row = $r ? $r->fetch_row() : null;
            return $row ? (int)$row[0] : 0;
        }
    }


    /**
     * LaporanExporter (abstract class)
     * Kerangka laporan; tiap format (PDF/Word/Excel) mengisi kirimHeader() sendiri.
     */
    abstract class LaporanExporter {
        protected $manager;
        protected $filter;
        protected $namaUser;

        public function __construct(DashboardManager $manager, TransaksiFilter $filter, $namaUser) {
            $this->manager  = $manager;
            $this->filter   = $filter;
            $this->namaUser = $namaUser;
        }

        // Wajib dibuat oleh class turunan
        abstract protected function kirimHeader($namaFile);

        // Bisa di-override (dipakai PDF untuk auto print)
        protected function scriptTambahan() { return ''; }

        public function kirim() {
            $this->kirimHeader('laporan-inventaris-' . date('Ymd'));
            echo $this->renderHtml();
        }

        protected function renderHtml() {
            $data = $this->manager->getTransaksi($this->filter, true);
            ob_start(); 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Histori Transaksi</title>
    <style>
        @page { size: landscape; margin: 12mm; }
        body { font-family: Arial, sans-serif; font-size: 13px; color: #222; }
        h2 { margin: 0; padding-bottom: 10px; font-size: 24px; border-bottom: 2px solid #111; }
        .filter { margin: 8px 0 0; font-size: 12px; color: #666; }
        table { border-collapse: collapse; width: 100%; margin-top: 18px; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #ddd; padding: 12px 10px; text-align: left; }
        th { font-weight: 600; color: #555; }
        td b { display: block; }
        td small { display: block; margin-top: 2px; font-size: 11px; color: #777; }
        .masuk { color: #2ecc71; }
        .pinjam { color: #f39c12; }
        .keluar { color: #e74c3c; }
    </style>
</head>
<body>
    <h2>Laporan Histori Transaksi</h2>
    <?php if ($this->filter->isAktif()) echo '<p class="filter">Filter: ' . e(http_build_query($this->filter->toArray(), '', ', ')) . '</p>'; ?>
    <table>
        <thead>
            <tr><th>Tanggal</th><th>Nama Barang</th><th>Jenis Transaksi</th><th>Jml</th></tr>
        </thead>
        <tbody>
        <?php if (empty($data)): ?>
            <tr><td colspan="4">Tidak ada data.</td></tr>
        <?php else: foreach ($data as $r):
            if (in_array($r['Jenis_transaksi'], DashboardManager::MASUK))  $warna = 'masuk';
            elseif ($r['Jenis_transaksi'] === 'Peminjaman')                $warna = 'pinjam';
            else                                                           $warna = 'keluar';
        ?>
            <tr>
                <td><?php echo date('d M Y', strtotime($r['Tanggal'])); ?></td>
                <td><b><?php echo e($r['Nama_barang']); ?></b><small><?php echo e($r['Jenis_barang']); ?></small></td>
                <td class="<?php echo $warna; ?>"><?php echo e($r['Jenis_transaksi']); ?></td>
                <td><?php echo e($r['Kuantitas']); ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <?php echo $this->scriptTambahan(); ?>
</body>
</html>
<?php
            return ob_get_clean();
        }
    }

    // Turunan (inheritance + polymorphism): tiap format punya header sendiri
    class ExcelExporter extends LaporanExporter {
        protected function kirimHeader($namaFile) {
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header("Content-Disposition: attachment; filename=$namaFile.xls");
        }
    }

    class WordExporter extends LaporanExporter {
        protected function kirimHeader($namaFile) {
            header('Content-Type: application/msword; charset=utf-8');
            header("Content-Disposition: attachment; filename=$namaFile.doc");
        }
    }

    class PdfExporter extends LaporanExporter {
        protected function kirimHeader($namaFile) {
            header('Content-Type: text/html; charset=utf-8');   // dibuka di tab baru, lalu print -> Save as PDF
        }
        protected function scriptTambahan() {
            return '<script>window.onload = function () { window.print(); };</script>';
        }
    }

    // Factory: memilih class exporter sesuai format
    class ExporterFactory {
        public static function buat($format, DashboardManager $m, TransaksiFilter $f, $namaUser) {
            switch ($format) {
                case 'excel': return new ExcelExporter($m, $f, $namaUser);
                case 'word':  return new WordExporter($m, $f, $namaUser);
                default:      return new PdfExporter($m, $f, $namaUser);
            }
        }
    }


    // EKSEKUSI
    $dashboard = new DashboardManager($koneksi);
    $filter    = new TransaksiFilter($_GET);

    // Ekspor laporan: index.php?export=pdf|word|excel
    if (isset($_GET['export'])) {
        ExporterFactory::buat($_GET['export'], $dashboard, $filter, $nama_user)->kirim();
        exit();
    }

    // Variabel untuk tampilan (template di bawah)
    $q                  = $filter->getQ();
    $jenis              = $filter->getJenis();
    $kategori           = $filter->getKategori();
    $limit              = $filter->getLimit();
    $filter_aktif       = $filter->isAktif();
    $param              = $filter->toArray();
    $qs                 = http_build_query($param);

    $total_masuk        = $dashboard->getTotalMasuk();
    $total_keluar       = $dashboard->getTotalKeluar();
    $daftar_kategori    = $dashboard->getKategori();
    $transaksi_terakhir = $dashboard->getTransaksi($filter);
    $total_transaksi    = $dashboard->countTransaksi($filter);

?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard - Inventaris Barang</title>
    <!-- Import icon dari Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <style>
        /* Reset & Dasar */
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        }

        body { 
            background-color: #dce4ed; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            padding: 20px; 
        }

        .app-wrapper { 
            width: 100%; 
            max-width: 1350px; 
            height: 92vh; 
            background-color: #191b20; 
            border-radius: 35px; 
            display: flex; 
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15); 
        }

        /* Sidebar */
        .sidebar { 
            width: 100px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            padding: 30px 0; 
            color: #fff; 
        }

        .sidebar .logo-box { 
            width: 50px; 
            height: 50px; 
            background-color: #faeed4; 
            color: #191b20; 
            border-radius: 15px; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            font-size: 24px; 
            font-weight: bold; 
            margin-bottom: 50px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.2); 
        }

        .nav-links { 
            display: flex; 
            flex-direction: column; 
            gap: 35px; 
            width: 100%; 
            align-items: center; 
        }

        .nav-links a { 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            gap: 8px; 
            color: #6b707c; 
            text-decoration: none; 
            transition: 0.3s; 
        }

        .nav-links a i {
            font-size: 24px; /* Ukuran khusus ikon */
        }

        .nav-links a span {
            font-size: 12px; /* Ukuran khusus teks menu */
            font-weight: 500;
        }

        .nav-links a:hover, 
        .nav-links a.active { 
            color: #ffffff; 
        }

        .logout { 
            margin-top: auto; 
            color: #6b707c; 
            font-size: 24px; 
            text-decoration: none; 
            transition: 0.3s; 
        }

        .logout:hover { 
            color: #ff4d4d; 
        }

        /* Konten Utama */
        .main-content { 
            flex: 1; 
            background-color: #ffffff; 
            border-radius: 30px; 
            margin: 15px 15px 15px 0; 
            padding: 40px 50px; 
            overflow-y: auto;
            scrollbar-width: none;        /* Firefox */
            -ms-overflow-style: none      /* Edge/IE lama */
        }

        .main-content::-webkit-scrollbar {
            display: none;   /* Chrome, Edge, Safari */
        }

        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
        }

        .header h1 { 
            font-size: 28px; 
            color: #111; 
        }

        .header-right { 
            display: flex; 
            align-items: center; 
            gap: 20px; 
        }

        .header-right i { 
            font-size: 22px; 
            color: #555; 
            cursor: pointer; 
        }

        /* User Profile Badge */
        .user-profile { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            background-color: #f5f6f8; 
            padding: 8px 15px; 
            border-radius: 30px; 
            font-weight: 600; 
            font-size: 14px; 
            color: #333; 
        }

        .user-profile .avatar { 
            width: 30px; 
            height: 30px; 
            background-color: #191b20; 
            color: #fff; 
            border-radius: 50%; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            font-size: 16px; 
        }

        /* Grid Bagian Atas (Statistik) */
        .top-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 30px; 
            margin-bottom: 40px; 
        }

        .section-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 15px; 
        }

        .section-header h3 { 
            font-size: 18px; 
            color: #111; 
        }

        /* Kartu Chart Utama */
        .chart-card { 
            background-color: #e6f0fa; 
            border-radius: 20px; 
            padding: 25px; 
            position: relative; 
        }

        .chart-card h2 { 
            font-size: 32px; 
            color: #111; 
            margin-bottom: 5px; 
        }

        .chart-card p { 
            color: #666; 
            font-size: 13px; 
        }

        .badge-dark { 
            position: absolute; 
            top: 25px; 
            right: 25px; 
            background-color: #191b20; 
            color: #fff; 
            padding: 5px 12px; 
            border-radius: 15px; 
            font-size: 12px; 
            font-weight: 600; 
        }

        .chart-placeholder { 
            margin-top: 20px; 
            height: 80px; 
            border-bottom: 2px dashed #b8cde0; 
            display: flex; 
            align-items: flex-end; 
            gap: 5px; 
        }

        /* Kartu Aset Kecil */
        .assets-grid { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 15px; 
        }

        .asset-card { 
            border-radius: 20px; 
            padding: 20px; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
        }

        .asset-card.purple { background-color: #e5dff2; }
        .asset-card.green  { background-color: #dcf2e3; }
        .asset-card.yellow { background-color: #faefdb; }

        .asset-card h4 { 
            font-size: 18px; 
            color: #111; 
            margin-bottom: 5px; 
        }

        .asset-card p { 
            font-size: 12px; 
            color: #666; 
        }

        .asset-icon-row { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-top: 30px; 
        }

        .asset-icon { 
            width: 35px; 
            height: 35px; 
            background-color: #fff; 
            border-radius: 10px; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            font-size: 18px; 
            color: #111; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); 
        }

        .asset-trend { 
            font-size: 12px; 
            color: #888; 
        }

        /* List Transaksi */
        .list-header { 
            display: grid; 
            grid-template-columns: 2fr 1fr 1fr 1fr; 
            font-size: 12px; 
            color: #999; 
            margin-bottom: 15px; 
            padding: 0 10px; 
        }

        .list-item { 
            display: grid; 
            grid-template-columns: 2fr 1fr 1fr 1fr; 
            align-items: center; 
            padding: 12px 10px; 
            border-bottom: 1px solid #f0f0f0; 
            transition: 0.2s; 
        }

        .list-item:hover { 
            background-color: #f9fafc; 
            border-radius: 10px; 
        }

        .item-name { 
            display: flex; 
            align-items: center; 
            gap: 15px; 
        }

        .item-icon-dark { 
            width: 40px; 
            height: 40px; 
            background-color: #191b20; 
            color: #fff; 
            border-radius: 12px; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            font-size: 20px; 
        }

        .item-name-text strong { 
            display: block; 
            font-size: 14px; 
            color: #111; 
        }

        .item-name-text span { 
            font-size: 12px; 
            color: #888; 
        }

        .item-val { 
            font-size: 14px; 
            color: #111; 
            font-weight: 500; 
        }

        /* Status Warna */
        .item-status.plus  { color: #2ecc71; }
        .item-status.minus { color: #e74c3c; }
        .item-status.blue  { color: #3498db; }

        /* Panel pencarian */
        .search-panel { 
            display: none; 
            gap: 10px; 
            flex-wrap: wrap; 
            margin-bottom: 25px; 
            padding: 15px; 
            background: #f5f6f8; 
            border-radius: 18px; 
        }

        .search-panel.open { display: flex; }
        .search-panel input, .search-panel select { 
            padding: 10px 14px; 
            border: 1px solid #dde1e7; 
            border-radius: 12px; 
            font-size: 14px; 
            background: #fff; 
        }

        .search-panel input { flex: 1 1 220px; }
        .search-panel button { 
            background: #191b20; 
            color: #fff; 
            border: 0; 
            padding: 10px 20px; 
            border-radius: 12px; 
            cursor: pointer; 
            font-weight: 600; 
        }

        .search-panel .reset { 
            align-self: center; 
            font-size: 13px; 
            color: #e74c3c; 
            text-decoration: none; 
        }

        /* Kartu ekspor */
        .export-card h2 { 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            font-size: 24px; 
        }

        .export-btns { 
            display: flex; 
            gap: 10px; 
            flex-wrap: wrap; 
            margin-top: 25px; 
        }

        .export-btns a { 
            flex: 1 1 80px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            gap: 4px; 
            padding: 14px 8px; 
            background: #fff; 
            border-radius: 14px; 
            color: #191b20; 
            text-decoration: none; 
            font-size: 13px; 
            font-weight: 600; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); 
            transition: 0.2s; 
        }
        .export-btns a i { font-size: 26px; }
        .export-btns a:hover { transform: translateY(-3px); }

        /* Transaksi */
        .count { 
            font-size: 12px; 
            color: #999; 
        }

        .load-more { 
            display: block; 
            width: max-content; 
            margin: 20px auto 0; 
            padding: 10px 22px; 
            border-radius: 15px; 
            background: #e4f0fa; 
            color: #191b20; 
            font-weight: 600; 
            font-size: 13px; 
            text-decoration: none; 
        }

        /* RESPONSIVE (HP & tablet kecil) */
        @media (max-width: 768px) {
            body { 
                padding: 0; 
                display: block; 
            }

            .app-wrapper { 
                flex-direction: column-reverse; 
                height: auto; 
                min-height: 100vh; 
                border-radius: 0; 
                background: transparent; 
                box-shadow: none; 
            }

            .sidebar { 
                position: fixed; 
                bottom: 0; 
                left: 0; 
                right: 0; 
                z-index: 10; 
                width: 100%; 
                height: 68px; 
                flex-direction: row; 
                justify-content: space-around; 
                padding: 0 12px; 
                background: #191b20; 
            }

            .sidebar .logo-box { display: none; }
            .nav-links { 
                flex-direction: row; 
                justify-content: space-around; 
                gap: 0; 
                flex: 1; 
            }

            .nav-links a { gap: 2px; }
            .nav-links a i { font-size: 22px; }
            .nav-links a span { font-size: 10px; }

            .logout { 
                margin-top: 0; 
                margin-left: 8px; 
            }

            .main-content { 
                margin: 0; 
                border-radius: 0; 
                padding: 20px 16px 90px; 
                min-height: 100vh; 
                overflow: visible; 
            }

            .header h1 { font-size: 22px; }
            .header-right { gap: 12px; }
            .user-profile { padding: 6px 10px; }

            .user-profile span { 
                max-width: 110px; 
                overflow: hidden; 
                text-overflow: ellipsis; 
                white-space: nowrap; 
            }

            .top-grid { 
                grid-template-columns: 1fr; 
                gap: 25px; 
            }

            .list-header { display: none; }

            .list-item { 
                grid-template-columns: 1fr 1fr; 
                gap: 8px 10px; 
            }

            .item-name { grid-column: 1 / -1; }

            .item-val::before { 
                content: attr(data-label); 
                display: block; 
                font-size: 11px; 
                color: #999; 
                font-weight: 400; 
            }

        }


        /* Transisi fade in / fade out antar halaman (seamless) */
        .main-content > * { 
            animation: pageFadeIn 0.35s ease backwards; 
        }

        .main-content.fade-out > * { 
            animation: pageFadeOut 0.25s ease forwards; 
        }

        @keyframes pageFadeIn  { from { opacity: 0; } to { opacity: 1; } }
        @keyframes pageFadeOut { from { opacity: 1; } to { opacity: 0; } }
        
        /* Sidebar: hanya ikon, meluncur (sliding) terbuka saat di-hover */
        .logout span { 
            display: none; 
            font-size: 12px; 
            font-weight: 500; 
        }

        @media (min-width: 769px) {
            .sidebar { 
                position: relative; 
                z-index: 20; 
            }

            .sidebar::before { 
                content: ''; 
                position: absolute; 
                top: 0; bottom: 0; 
                left: 0; 
                width: 100%; 
                background: #191b20; 
                border-radius: 0 30px 30px 0; 
                z-index: -1; 
                transition: width 0.4s ease, box-shadow 0.4s ease; 
            }

            .sidebar:hover::before { 
                width: 240px; 
                box-shadow: 6px 0 25px rgba(0,0,0,0.25); 
            }

            .nav-links a, .logout { 
                display: flex; 
                align-items: center; 
                flex-direction: row; 
                gap: 18px; 
                width: 100%; 
                padding-left: 38px; 
                white-space: nowrap; 
            }

            .logout span { display: inline; }

            .nav-links a span, .logout span {
                opacity: 0; 
                transition: opacity 0.25s ease; 
            }

            .sidebar:hover .nav-links a span, .sidebar:hover .logout span { 
                opacity: 1; 
                transition-delay: 0.1s; 
            }
        }
    </style>
</head>
<body>

    <div class="app-wrapper">

        <!-- SIDEBAR -->
        <div class="sidebar">
            <div class="logo-box"><i class='bx bx-layer'></i></div>
            <div class="nav-links">
                <a href="index.php" class="active" title="Beranda">
                    <i class='bx bxs-grid-alt'></i>
                    <span>Beranda</span>
                </a>
                <a href="CRUD/pendataan.php" title="Pendataan Barang">
                    <i class='bx bx-folder'></i>
                    <span>Pendataan</span>
                </a>
                <a href="CRUD/read.php" title="Statistik & Data">
                    <i class='bx bx-bar-chart-alt-2'></i>
                    <span>Master Barang</span>
                </a>
                <a href="CRUD/update.php" title="Update Data">
                    <i class='bx bx-cube'></i>
                    <span>Update</span>
                </a>
                <?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
                    <a href="CRUD/tambah_user.php" title="Tambah User">
                    <i class='bx bx-user-plus'></i>
                    <span>Tambah User</span>
                    </a>
                <?php endif; ?>
            </div>
            <a href="logout.php" class="logout" title="Logout" data-logout data-no-fade>
                <i class='bx bx-log-out'></i>
                 <span>Logout</span>
            </a>
        </div>


        <!-- KONTEN UTAMA -->
        <div class="main-content">
            
            <div class="header">
                <h1>Overview</h1>
                <div class="header-right">
                    <i class='bx bx-search' id="btnCari" title="Cari barang"></i>
                    <div class="user-profile">
                        <div class="avatar"><i class='bx bx-user'></i></div>
                        <span><?php echo $nama_user; ?> <i class='bx bx-chevron-down' style="font-size: 16px;"></i></span>
                    </div>
                </div>
            </div>


            <!-- Panel pencarian + filter -->
            <form class="search-panel <?php echo $filter_aktif ? 'open' : ''; ?>" id="panelCari" method="get" action="index.php#transaksi">
                <input type="text" name="q" value="<?php echo e($q); ?>" placeholder="Cari nama barang..." autocomplete="off">
                <select name="jenis">
                    <option value="">Semua transaksi</option>
                    <option value="masuk"  <?php echo $jenis === 'masuk'  ? 'selected' : ''; ?>>Barang masuk</option>
                    <option value="keluar" <?php echo $jenis === 'keluar' ? 'selected' : ''; ?>>Barang keluar</option>
                </select>
                <select name="kategori">
                    <option value="">Semua kategori</option>
                    <?php foreach ($daftar_kategori as $k): ?>
                        <option value="<?php echo e($k); ?>" <?php echo $kategori === $k ? 'selected' : ''; ?>><?php echo e($k); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Cari</button>
                <?php if ($filter_aktif): ?><a class="reset" href="index.php#transaksi">Reset</a><?php endif; ?>
            </form>


            <!-- Bagian Atas, Chart & Kartu Warna dinamis -->
            <div class="top-grid">
                
                <div>
                    <div class="section-header">
                        <h3>Ekspor Laporan</h3>
                    </div>
                    <div class="chart-card export-card">
                        <h2><i class='bx bxs-file-export'></i> Unduh Laporan</h2>
                        <p>Stok &amp; transaksi barang<?php echo $filter_aktif ? ' (sesuai filter aktif)' : ''; ?></p>
                        <div class="badge-dark">Gudang Utama</div>
                        <div class="export-btns">
                            <a href="?export=pdf&<?php echo $qs; ?>" target="_blank"><i class='bx bx-printer'></i>Cetak Laporan</a>
                        </div>
                    </div>
                </div>


                <div>
                    <div class="section-header">
                        <h3>Status Aset (Riwayat)</h3>
                        <i class='bx bx-slider-alt' style="color: #666; font-size: 20px;"></i>
                    </div>

                    <!-- Grid 3 Kartu Pastel Dinamis -->
                    <div class="assets-grid">
                        <div class="asset-card purple">
                            <div>
                                <h4><?php echo $total_masuk; ?> Unit</h4>
                                <p>Barang Masuk</p>
                            </div>
                            <div class="asset-icon-row">
                                <div class="asset-icon"><i class='bx bx-download'></i></div>
                                <span class="asset-trend">+</span>
                            </div>
                        </div>

                        <div class="asset-card green">
                            <div>
                                <h4><?php echo $total_keluar; ?> Unit</h4>
                                <p>Barang Keluar</p>
                            </div>
                            <div class="asset-icon-row">
                                <div class="asset-icon"><i class='bx bx-upload'></i></div>
                                <span class="asset-trend">-</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>


            <!-- Transaksi (full width) -->
            <div id="transaksi">
                <div class="section-header">
                    <h3>Transaksi Terakhir</h3>
                    <span class="count"><?php echo count($transaksi_terakhir); ?> dari <?php echo $total_transaksi; ?> transaksi</span>
                </div>

                <div class="list-header">
                    <span>Nama Barang</span>
                    <span>Jenis transaksi</span>
                    <span>Kuantitas</span>
                    <span>Tgl Transaksi</span>
                </div>

                <?php if (empty($transaksi_terakhir)): ?>
                    <div style="text-align:center; color:#999; margin-top:20px;">
                        <?php echo $filter_aktif ? 'Tidak ada transaksi yang cocok dengan pencarian.' : 'Belum ada riwayat transaksi.'; ?>
                    </div>
                <?php else: foreach ($transaksi_terakhir as $row):
                    $tgl_format  = date('d M Y', strtotime($row['Tanggal']));
                    $icon        = in_array($row['Jenis_transaksi'], DashboardManager::MASUK) ? 'bx-down-arrow-alt' : 'bx-up-arrow-alt';
                ?>

                <div class="list-item">
                    <div class="item-name">
                        <div class="item-icon-dark"><i class='bx <?php echo $icon; ?>'></i></div>
                        <div class="item-name-text">
                            <strong><?php echo e($row['Nama_barang']); ?></strong>
                            <span><?php echo e($row['Jenis_barang']); ?></span>
                        </div>
                    </div>

                    <div class="item-val item-status blue" data-label="Transaksi">
                        <?php echo e($row['Jenis_transaksi']); ?> (<strong><?php echo e($row['Kuantitas']); ?></strong>)
                    </div>
                    <div class="item-val item-status" data-label="Kuantitas">
                        <?php echo e($row['Kuantitas']); ?>
                    </div>
                    <div class="item-val" style="color:#888;" data-label="Tanggal"><?php echo $tgl_format; ?></div>
                </div>

                <?php endforeach; endif; ?>

                <?php if ($total_transaksi > count($transaksi_terakhir)): ?>
                    <a class="load-more" href="?<?php echo http_build_query($param + ['limit' => $limit + 10]); ?>#transaksi">Muat lebih banyak</a>
                <?php endif; ?>

            </div>

        </div>
    </div>

    <script>
        // Ikon search membuka/menutup panel pencarian
        var panel = document.getElementById('panelCari');
        document.getElementById('btnCari').addEventListener('click', function () {
            panel.classList.toggle('open');
            if (panel.classList.contains('open')) panel.querySelector('input').focus();
        });

        // Fade out sebelum pindah halaman (navbar)
        document.addEventListener('click', function (e) {
            var a = e.target.closest('a');
            if (!a || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey) return;
            var href = a.getAttribute('href');
            if (!href || href.charAt(0) === '#') return;
            if (a.target === '_blank' || a.hasAttribute('download') ||
                a.hasAttribute('onclick') || a.hasAttribute('data-no-fade')) return;
            if (a.origin !== location.origin) return;
            e.preventDefault();
            document.querySelector('.main-content').classList.add('fade-out');
            setTimeout(function () { location.href = a.href; }, 250);
        });

        window.addEventListener('pageshow', function (e) {
            if (e.persisted) document.querySelector('.main-content').classList.remove('fade-out');
        });

    </script>
    <?php include 'logout.php'; ?>
</body>
</html>