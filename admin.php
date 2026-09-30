<?php
session_start();
include 'koneksi.php';

// ===== GUARD: hanya role admin =====
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

// ===== CSRF & HELPER =====
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrf_ok(): bool {
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}
function e($teks): string {
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

// ===== CLASS BARANG =====
class BarangManager {
    private $db;
    public function __construct($koneksi) { $this->db = $koneksi; }

    public function getAllBarang(): array {
        $data = [];
        $result = $this->db->query("SELECT * FROM barang ORDER BY id_barang ASC");
        if ($result) {
            while ($row = $result->fetch_assoc()) $data[] = $row;
        }
        return $data;
    }

    public function deleteBarang($id): bool {
        $stmt = $this->db->prepare("DELETE FROM barang WHERE id_barang = ?");
        $stmt->bind_param("s", $id);
        try {
            return $stmt->execute();
        } catch (mysqli_sql_exception $ex) {
            return false; // masih terikat data transaksi
        }
    }
}

// ===== CLASS USER =====
class UserManager {
    private $db;
    public const ROLE_VALID = ['petugas', 'wakasek', 'admin'];
    public function __construct($koneksi) { $this->db = $koneksi; }

    public function getAllUser(): array {
        $data = [];
        $result = $this->db->query("SELECT Username, Nama_lengkap, Role FROM user ORDER BY Role, Username");
        if ($result) {
            while ($row = $result->fetch_assoc()) $data[] = $row;
        }
        return $data;
    }

    public function usernameAda($username): bool {
        $stmt = $this->db->prepare("SELECT 1 FROM user WHERE Username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    // return pesan error, atau null jika berhasil
    public function tambahUser($username, $nama, $password, $role): ?string {
        $username = trim($username);
        $nama = trim($nama);
        if ($username === '' || $nama === '' || strlen($password) < 6) {
            return "Semua kolom wajib diisi dan password minimal 6 karakter.";
        }
        if (!in_array($role, self::ROLE_VALID, true)) return "Role tidak valid.";
        if ($this->usernameAda($username)) return "Username sudah dipakai.";

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO user (Username, Password, Nama_lengkap, Role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $hash, $nama, $role);
        return $stmt->execute() ? null : "Gagal menyimpan user.";
    }
}

$barangManager = new BarangManager($koneksi);
$userManager   = new UserManager($koneksi);

// ===== ROUTING SEDERHANA: admin.php?page=barang | user =====
$page = ($_GET['page'] ?? 'barang') === 'user' ? 'user' : 'barang';
$notif = '';

// ===== AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) die("Token tidak valid.");

    if (isset($_POST['hapus'])) {
        $ok = $barangManager->deleteBarang($_POST['hapus']);
        $pesan = $ok ? "Data barang berhasil dihapus!" : "Gagal menghapus! Pastikan barang tidak terikat data transaksi.";
        echo "<script>alert(" . json_encode($pesan) . "); window.location.href='admin.php?page=barang';</script>";
        exit();
    }

    if (isset($_POST['tambah_user'])) {
        $page = 'user';
        $error = $userManager->tambahUser($_POST['username'], $_POST['nama_lengkap'], $_POST['password'], $_POST['role']);
        $notif = $error
            ? "<div class='alert alert-err'>" . e($error) . "</div>"
            : "<div class='alert alert-ok'>User berhasil ditambahkan!</div>";
    }
}

// ===== DATA UNTUK TAMPILAN =====
$daftar_barang = ($page === 'barang') ? $barangManager->getAllBarang() : [];
$daftar_user   = ($page === 'user')   ? $userManager->getAllUser()     : [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Inventaris</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
        body{background:#dce4ed;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px}
        .app-wrapper{width:100%;max-width:1350px;height:92vh;background:#191b20;border-radius:35px;display:flex;box-shadow:0 15px 40px rgba(0,0,0,.15)}
        .sidebar{width:100px;display:flex;flex-direction:column;align-items:center;padding:30px 0;color:#fff}
        .logo-box{width:50px;height:50px;background:#faeed4;color:#191b20;border-radius:15px;display:flex;justify-content:center;align-items:center;font-size:24px;margin-bottom:50px}
        .nav-links{display:flex;flex-direction:column;gap:35px;width:100%;align-items:center}
        .nav-links a,.logout{display:flex;flex-direction:column;align-items:center;gap:8px;color:#6b707c;text-decoration:none;transition:.3s}
        .nav-links a i{font-size:24px}.nav-links a span{font-size:12px;font-weight:500}
        .nav-links a:hover,.nav-links a.active{color:#fff}
        .logout{margin-top:auto;font-size:24px}.logout:hover{color:#ff4d4d}
        .logout span{display:none;font-size:12px}
        .main-content{flex:1;background:#fff;border-radius:30px;margin:15px 15px 15px 0;padding:40px 50px;overflow-y:auto;animation:fade .4s ease both}
        @keyframes fade{from{opacity:0}to{opacity:1}}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px}
        .header h1{font-size:28px;color:#111}
        .table-container{background:#fff;border-radius:15px;border:1px solid #eee;overflow-x:auto}
        table{width:100%;border-collapse:collapse;text-align:left}
        th{background:#f8f9fa;color:#555;padding:15px;font-size:14px;border-bottom:2px solid #eee}
        td{padding:15px;font-size:14px;color:#333;border-bottom:1px solid #eee}
        tr:hover{background:#fdfdfd}
        .badge{padding:5px 10px;border-radius:8px;font-size:12px;font-weight:600}
        .badge-green{background:#dcf2e3;color:#2ecc71}.badge-red{background:#fde8e8;color:#e74c3c}
        .btn-hapus{color:#e74c3c;background:#fdedec;padding:6px 10px;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:4px}
        .btn-hapus:hover{background:#fadbd8}
        .btn-tambah{background:#191b20;color:#fff;padding:10px 20px;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:5px}
        .btn-tambah:hover{background:#333}
        .form-card{border:1px solid #eee;border-radius:15px;padding:25px;margin-bottom:30px;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;align-items:end}
        .form-card label{font-size:13px;font-weight:600;color:#555;display:block;margin-bottom:6px}
        .form-card input,.form-card select{width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:10px;font-size:14px}
        .alert{padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:14px}
        .alert-ok{background:#dcf2e3;color:#1e8449}.alert-err{background:#fde8e8;color:#c0392b}
        @media(max-width:768px){
            body{padding:0;display:block}
            .app-wrapper{flex-direction:column-reverse;height:auto;min-height:100vh;border-radius:0;background:transparent;box-shadow:none}
            .sidebar{position:fixed;bottom:0;left:0;right:0;z-index:10;width:100%;height:68px;flex-direction:row;justify-content:space-around;padding:0 12px;background:#191b20}
            .logo-box{display:none}.nav-links{flex-direction:row;justify-content:space-around;gap:0;flex:1}
            .nav-links a{gap:2px}.nav-links a span{font-size:10px}.logout{margin-top:0;margin-left:8px}
            .main-content{margin:0;border-radius:0;padding:20px 16px 90px;min-height:100vh;overflow:visible}
            .header h1{font-size:22px}
        }
        @media(min-width:769px){
            .sidebar{position:relative;z-index:20}
            .sidebar::before{content:'';position:absolute;inset:0 auto 0 0;width:100%;background:#191b20;border-radius:35px 30px 30px 35px;z-index:-1;transition:width .4s ease,box-shadow .4s ease}
            .sidebar:hover::before{width:240px;box-shadow:6px 0 25px rgba(0,0,0,.25)}
            .nav-links a,.logout{flex-direction:row;gap:18px;width:100%;padding-left:38px;white-space:nowrap}
            .logout span{display:inline}
            .nav-links a span,.logout span{opacity:0;transition:opacity .25s ease}
            .sidebar:hover .nav-links a span,.sidebar:hover .logout span{opacity:1;transition-delay:.1s}
        }
    </style>
</head>
<body>
<div class="app-wrapper">

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="logo-box"><i class='bx bx-layer'></i></div>
        <div class="nav-links">
            <a href="admin.php?page=barang" class="<?= $page === 'barang' ? 'active' : '' ?>" title="Master Barang">
                <i class='bx bx-bar-chart-alt-2'></i><span>Master Barang</span></a>
            <a href="admin.php?page=user" class="<?= $page === 'user' ? 'active' : '' ?>" title="Kelola User">
                <i class='bx bx-user-plus'></i><span>Kelola User</span></a>
        </div>
        <a href="../logout.php" class="logout" title="Logout" onclick="return confirm('Yakin ingin keluar?')">
            <i class='bx bx-log-out'></i><span>Logout</span>
        </a>
    </div>

    <!-- KONTEN -->
    <div class="main-content">

    <?php if ($page === 'barang'): ?>
        <div class="header"><h1>Data Master Barang</h1></div>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>No</th><th>ID Barang</th><th>Nama Barang</th><th>Jenis Barang</th><th>Sumber Dana</th><th>Stok</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                <?php if (empty($daftar_barang)): ?>
                    <tr><td colspan="7" style="text-align:center;padding:20px;">Belum ada data barang.</td></tr>
                <?php else: $no = 1; foreach ($daftar_barang as $row):
                    $kelas = ($row['Stok'] < 5) ? 'badge-red' : 'badge-green'; ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><strong><?= e($row['id_barang']) ?></strong></td>
                        <td><?= e($row['Nama_barang']) ?></td>
                        <td><?= e($row['Jenis_barang']) ?></td>
                        <td><?= e($row['Sumber_dana']) ?></td>
                        <td><span class="badge <?= $kelas ?>"><?= e($row['Stok']) ?> Unit</span></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Hapus barang ini?')">
                                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                                <input type="hidden" name="hapus" value="<?= e($row['id_barang']) ?>">
                                <button type="submit" class="btn-hapus"><i class='bx bx-trash'></i> Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>
        <div class="header"><h1>Kelola User</h1></div>
        <?= $notif ?>
        <form method="POST" action="admin.php?page=user" class="form-card">
            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
            <div><label>Username</label><input type="text" name="username" required></div>
            <div><label>Nama Lengkap</label><input type="text" name="nama_lengkap" required></div>
            <div><label>Password</label><input type="password" name="password" minlength="6" required></div>
            <div><label>Role</label>
                <select name="role">
                    <?php foreach (UserManager::ROLE_VALID as $r): ?>
                        <option value="<?= $r ?>"><?= ucfirst($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="tambah_user" class="btn-tambah"><i class='bx bx-plus'></i> Tambah User</button>
        </form>

        <div class="table-container">
            <table>
                <thead><tr><th>No</th><th>Username</th><th>Nama Lengkap</th><th>Role</th></tr></thead>
                <tbody>
                <?php $no = 1; foreach ($daftar_user as $u): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><strong><?= e($u['Username']) ?></strong></td>
                        <td><?= e($u['Nama_lengkap']) ?></td>
                        <td><span class="badge <?= $u['Role'] === 'admin' ? 'badge-red' : 'badge-green' ?>"><?= e($u['Role']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    </div>
</div>
</body>
</html>