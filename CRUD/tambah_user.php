<?php
    session_start();
    include '../koneksi.php';

    // Harus login
    if(!isset($_SESSION['username'])) { header("Location: ../login.php"); exit(); }
    // Hanya admin
    if(($_SESSION['role'] ?? '') !== 'admin') { header("Location: read.php"); exit(); }

    if(empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    function csrf_ok() { return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']); }
    function e($t) { return htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8'); }

    class UserManager {
        private $db;
        public const ROLE_VALID = ['petugas', 'wakasek', 'admin'];

        public function __construct($koneksi) { $this->db = $koneksi; }

        public function usernameAda($username) {
            $stmt = $this->db->prepare("SELECT 1 FROM user WHERE Username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            return $stmt->get_result()->num_rows > 0;
        }

        // Mengembalikan pesan error, atau null jika berhasil
        public function tambahUser($username, $nama, $password, $role) {
            $username = trim($username);
            $nama = trim($nama);
            
            if($username === '' || $nama === '' || strlen($password) < 6) {
                return "Semua kolom wajib diisi dan password minimal 6 karakter.";
            }
            if(!in_array($role, self::ROLE_VALID, true)) { return "Role tidak valid."; }
            if($this->usernameAda($username)) { return "Username sudah dipakai."; }

            // 1. Buat ID acak 9 digit sesuai permintaan
            $id_petugas = rand(100000000, 999999999);

            // 2. Query disiapkan untuk menerima 5 data (termasuk ID)
            $stmt = $this->db->prepare("INSERT INTO user (id_petugas, Username, Password, Nama_lengkap, Role) VALUES (?, ?, ?, ?, ?)");
            
            // 3. Masukkan data: 'i' untuk ID (integer), 's' untuk sisanya (string). Password tetap plain text.
            $stmt->bind_param("issss", $id_petugas, $username, $password, $nama, $role);

            return $stmt->execute() ? null : "Gagal menyimpan user.";
        }
    }

    $userManager = new UserManager($koneksi);
    $notif = '';
    if(isset($_GET['sukses'])) { $notif = "<div class='alert alert-ok'>User berhasil ditambahkan!</div>"; }

    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_user'])) {
        if(!csrf_ok()) { die("Token tidak valid."); }
        $error = $userManager->tambahUser($_POST['username'], $_POST['nama_lengkap'], $_POST['password'], $_POST['role']);
        if(!$error) { header('Location: tambah_user.php?sukses=1'); exit(); } // cegah kirim ulang form saat refresh
        $notif = $error
            ? "<div class='alert alert-err'>" . e($error) . "</div>"
            : "<div class='alert alert-ok'>User berhasil ditambahkan!</div>";
    }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah User - Inventaris</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* CSS Sama Persis dengan Sebelumnya */
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

        .nav-links a i { font-size: 24px; }

        .nav-links a span { 
            font-size: 12px; 
            font-weight: 500; 
        }

        .nav-links a:hover, .nav-links a.active { color: #ffffff; }

        .logout { 
            margin-top: auto; 
            color: #6b707c; 
            font-size: 24px; 
            text-decoration: none; 
            transition: 0.3s; 
        }

        .logout:hover { color: #ff4d4d; }

        .main-content { 
            flex: 1; 
            background-color: #ffffff; 
            border-radius: 30px; 
            margin: 15px 15px 15px 0; 
            padding: 40px 50px; 
            overflow-y: auto; 
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
        
        .btn-tambah { 
            background-color: #191b20; 
            color: #fff; 
            padding: 10px 20px; 
            border-radius: 10px; 
            text-decoration: none; 
            font-size: 14px; 
            font-weight: 600; 
            display: flex; 
            align-items: center; 
            gap: 5px; 
            transition: 0.3s; 
        }

        .btn-tambah:hover { background-color: #333; }

        .table-container { 
            background: #fff; 
            border-radius: 15px; 
            border: 1px solid #eee; 
            overflow-x: auto; 
        }

        table { 
            width: 100%; 
            border-collapse: collapse; 
            text-align: left; 
        }

        th { 
            background-color: #f8f9fa; 
            color: #555; 
            padding: 15px; 
            font-size: 14px; 
            font-weight: 600; 
            border-bottom: 2px solid #eee; 
        }

        td { 
            padding: 15px; 
            font-size: 14px; 
            color: #333; 
            border-bottom: 1px solid #eee; 
        }

        tr:hover { background-color: #fdfdfd; }

        .badge { 
            padding: 5px 10px; 
            border-radius: 8px; 
            font-size: 12px; 
            font-weight: 600; 
        }

        .badge-green { 
            background-color: #dcf2e3; 
            color: #2ecc71; 
        }

        .badge-red { 
            background-color: #fde8e8; 
            color: #e74c3c; 
        }


        .action-btns { 
            display: flex; 
            gap: 10px; 
        }
        
        /* Menggunakan style btn-hapus yang sudah Anda buat */
        .btn-hapus { 
            color: #e74c3c; 
            background: #fdedec;
            padding: 6px 10px; 
            border-radius: 6px; 
            text-decoration: none; 
            font-size: 14px; 
            display: inline-flex; 
            align-items: center; 
            gap: 4px; 
            font-weight: 600;
        }

        .btn-hapus:hover { background: #fadbd8; }

        .btn-hapus{border:none;cursor:pointer;}

        /* RESPONSIVE (HP & tablet kecil) */
        @media (max-width: 768px) {
            body { padding: 0; display: block; }

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
        }

        /* Transisi fade in / fade out antar halaman */
        .main-content { 
            animation: pageFadeIn 0.4s ease both; 
            transition: opacity 0.3s ease; 
        }

        .main-content.fade-out { opacity: 0; }
        @keyframes pageFadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* Sidebar: hanya ikon, meluncur (sliding) terbuka saat di-hover */
        .logout span { display: none; font-size: 12px; font-weight: 500; }
        @media (min-width: 769px) {
            .sidebar { position: relative; z-index: 20; }

            .sidebar::before { 
                content: ''; 
                position: absolute; 
                top: 0; 
                bottom: 0; 
                left: 0; 
                width: 100%; 
                background: #191b20; 
                border-radius: 35px 30px 30px 35px; 
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
        
    .form-card{border:1px solid #eee;border-radius:15px;padding:25px;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:15px;align-items:end}
        .form-card label{font-size:13px;font-weight:600;color:#555;display:block;margin-bottom:6px}
        .form-card input,.form-card select{width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:10px;font-size:14px}
        .btn-tambah{border:none;cursor:pointer;justify-content:center}
        .alert{padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:14px}
        .alert-ok{background:#dcf2e3;color:#1e8449}.alert-err{background:#fde8e8;color:#c0392b}
    </style>
</head>
<body>
    <div class="app-wrapper">


        <div class="sidebar">
            <div class="logo-box"><i class='bx bx-layer'></i></div>
            <div class="nav-links">
                <a href="../index.php" title="Beranda"><i class='bx bxs-grid-alt'></i><span>Beranda</span></a>
                <a href="pendataan.php" title="Pendataan Barang"><i class='bx bx-folder'></i><span>Pendataan</span></a>
                <a href="read.php" title="Statistik & Data"><i class='bx bx-bar-chart-alt-2'></i><span>Master Barang</span></a>
                <a href="update.php" title="Update Data"><i class='bx bx-cube'></i><span>Update</span></a>
                <a href="tambah_user.php" class="active" title="Tambah User"><i class='bx bx-user-plus'></i><span>Tambah User</span></a>
            </div>
            <a href="../logout.php" class="logout" title="Logout" onclick="return confirm('Yakin ingin keluar?')">
                <i class='bx bx-log-out'></i>
            </a>
        </div>

        <div class="main-content">
            <div class="header"><h1>Tambah User</h1></div>
            <?php echo $notif; ?>

            <form method="POST" class="form-card">
                <input type="hidden" name="csrf" value="<?php echo $_SESSION['csrf']; ?>">
                <div><label>Username</label><input type="text" name="username" required></div>
                <div><label>Nama Lengkap</label><input type="text" name="nama_lengkap" required></div>
                <div><label>Password</label><input type="password" name="password" minlength="6" required></div>
                <div><label>Role</label>
                    <select name="role">
                        <?php foreach(UserManager::ROLE_VALID as $r): ?>
                            <option value="<?php echo $r; ?>"><?php echo ucfirst($r); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="tambah_user" class="btn-tambah"><i class='bx bx-plus'></i> Simpan User</button>
            </form>
        </div>
    </div>
</body>
</html>