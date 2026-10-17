<?php
session_start();

// Jika sudah login, langsung ke aplikasi
if (isset($_SESSION["user_id"])) {
    header("Location: admin/dashboard.php");
    exit;
}

require "db.php";

// Token CSRF
if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

// Notifikasi sekali tampil (mis. dari logout atau akses tanpa login)
$flash = $_SESSION["flash"] ?? null;
unset($_SESSION["flash"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token    = $_POST["csrf"] ?? "";
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if (!hash_equals($_SESSION["csrf"], $token)) {
        $flash = ["type" => "error", "pesan" => "Permintaan tidak valid. Muat ulang halaman."];
    } elseif ($username === "" || $password === "") {
        $flash = ["type" => "warning", "pesan" => "Username dan password wajib diisi."];
    } else {
        $koneksiGagal = false;
        $user = null;

        try {
            $user = dbFindUser($username);
        } catch (RuntimeException $e) {
            error_log("Login gagal konek database JSON online: " . $e->getMessage());
            $koneksiGagal = true;
        }

        if ($koneksiGagal) {
            $flash = ["type" => "error", "pesan" => "Tidak dapat terhubung ke database online. Coba lagi sebentar lagi."];
        } elseif ($user && password_verify($password, $user["password"] ?? "")) {
            session_regenerate_id(true);

            $_SESSION["user_id"]  = $user["username"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["nama"]     = $user["nama"] ?? $user["username"];

            // Notifikasi berhasil masuk, ditampilkan di index.php
            $_SESSION["flash"] = [
                "type"  => "success",
                "pesan" => "Berhasil masuk. Selamat datang, " . $_SESSION["nama"] . "!",
            ];

            header("Location: admin/dashboard.php");
            exit;
        } else {
            $flash = ["type" => "error", "pesan" => "Username atau password salah."];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Aplikasi Data Siswa</title>
    <link rel="stylesheet" href="asset/css/style.css">
</head>

<body class="login-page"<?php if ($flash): ?>
      data-flash-type="<?= htmlspecialchars($flash["type"]) ?>"
      data-flash-msg="<?= htmlspecialchars($flash["pesan"]) ?>"<?php endif; ?>>

<div class="login-card">

    <div class="login-logo">🎓</div>

    <h1>Aplikasi Data Siswa</h1>
    <p class="subtitle">Silakan login untuk melanjutkan.</p>

    <form method="POST" action="login.php">

        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"]) ?>">

        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username"
                   placeholder="Masukkan username"
                   value="<?= htmlspecialchars($_POST["username"] ?? "") ?>"
                   required autofocus>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrap">
                <input type="password" id="password" name="password"
                       placeholder="Masukkan password" required>
                <button type="button" class="toggle-password" onclick="togglePassword()">👁</button>
            </div>
        </div>

        <button type="submit" class="btn-login">Masuk</button>

    </form>

</div>

<script src="asset/js/script.js"></script>

</body>
</html>