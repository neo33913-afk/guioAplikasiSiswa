<?php
session_start();

// Hapus seluruh data session lama
$_SESSION = [];
session_destroy();

// Mulai session baru hanya untuk membawa notifikasi ke halaman login
session_start();
session_regenerate_id(true);

$_SESSION["flash"] = [
    "type"  => "success",
    "pesan" => "Anda berhasil logout. Sampai jumpa!",
];

header("Location: ../login.php");
exit;