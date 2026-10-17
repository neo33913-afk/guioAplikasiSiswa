<?php
session_start();

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function kirim(int $kode, array $data)
{
    http_response_code($kode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Hanya user yang sudah login
if (!isset($_SESSION["user_id"])) {
    kirim(401, ["ok" => false, "pesan" => "Sesi berakhir. Silakan login kembali."]);
}

require "db.php";

const DAFTAR_KELAS  = ["X RPL 1", "X RPL 2", "XI RPL 1", "XI RPL 2", "XII RPL 1", "XII RPL 2"];
const DAFTAR_GENDER = ["Laki-laki", "Perempuan"];
const DAFTAR_HOBI   = ["Gaming", "Membaca", "Olahraga", "Musik", "Coding"];


// Validasi & bersihkan data siswa dari browser. Melempar DomainException jika tidak valid.
function bersihkanSiswa($masuk): array
{
    if (!is_array($masuk)) {
        throw new DomainException("Data siswa tidak valid.");
    }

    $nama   = trim((string) ($masuk["nama"] ?? ""));
    $nis    = trim((string) ($masuk["nis"] ?? ""));
    $kelas  = (string) ($masuk["kelas"] ?? "");
    $gender = (string) ($masuk["gender"] ?? "");
    $alamat = trim((string) ($masuk["alamat"] ?? ""));
    $hobi   = is_array($masuk["hobi"] ?? null) ? $masuk["hobi"] : [];

    if ($nama === "" || $nis === "" || $alamat === "") {
        throw new DomainException("Mohon lengkapi semua data siswa.");
    }

    if (mb_strlen($nama) > 100 || mb_strlen($nis) > 20 || mb_strlen($alamat) > 255) {
        throw new DomainException("Ada data yang terlalu panjang.");
    }

    if (!in_array($kelas, DAFTAR_KELAS, true) || !in_array($gender, DAFTAR_GENDER, true)) {
        throw new DomainException("Kelas atau jenis kelamin tidak valid.");
    }

    $hobi = array_values(array_unique(array_filter(
        $hobi,
        fn($h) => is_string($h) && in_array($h, DAFTAR_HOBI, true)
    )));

    return [
        "nama"   => $nama,
        "nis"    => $nis,
        "kelas"  => $kelas,
        "gender" => $gender,
        "hobi"   => $hobi,
        "alamat" => $alamat,
    ];
}


function nisSudahAda(array $daftar, string $nis, ?string $kecualiId = null): bool
{
    foreach ($daftar as $s) {
        if (($s["nis"] ?? "") === $nis && ($s["id"] ?? null) !== $kecualiId) {
            return true;
        }
    }

    return false;
}


try {

    // ---------- BACA ----------
    if ($_SERVER["REQUEST_METHOD"] === "GET") {
        kirim(200, ["ok" => true, "data" => dbRead()["siswa"]]);
    }

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        kirim(405, ["ok" => false, "pesan" => "Metode tidak didukung."]);
    }

    // ---------- UBAH DATA (wajib token CSRF) ----------
    $token = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";

    if (empty($_SESSION["csrf"]) || !hash_equals($_SESSION["csrf"], $token)) {
        kirim(403, ["ok" => false, "pesan" => "Token keamanan tidak valid. Muat ulang halaman."]);
    }

    $input = json_decode(file_get_contents("php://input"), true);

    if (!is_array($input)) {
        kirim(400, ["ok" => false, "pesan" => "Permintaan tidak valid."]);
    }

    $aksi = $input["aksi"] ?? "";
    $id   = isset($input["id"]) ? (string) $input["id"] : null;

    $daftar = dbUpdate(function (array &$data) use ($aksi, $id, $input) {

        if ($aksi === "tambah") {
            $baru = bersihkanSiswa($input["data"] ?? null);

            if (nisSudahAda($data["siswa"], $baru["nis"])) {
                throw new DomainException("NIS " . $baru["nis"] . " sudah terdaftar.");
            }

            $data["siswa"][] = ["id" => bin2hex(random_bytes(6))] + $baru;

        } elseif ($aksi === "ubah") {
            $baru   = bersihkanSiswa($input["data"] ?? null);
            $posisi = null;

            foreach ($data["siswa"] as $i => $s) {
                if (($s["id"] ?? null) === $id) {
                    $posisi = $i;
                    break;
                }
            }

            if ($posisi === null) {
                throw new DomainException("Data siswa tidak ditemukan.");
            }

            if (nisSudahAda($data["siswa"], $baru["nis"], $id)) {
                throw new DomainException("NIS " . $baru["nis"] . " sudah terdaftar.");
            }

            $data["siswa"][$posisi] = ["id" => $id] + $baru;

        } elseif ($aksi === "hapus") {
            $sebelum = count($data["siswa"]);

            $data["siswa"] = array_values(array_filter(
                $data["siswa"],
                fn($s) => ($s["id"] ?? null) !== $id
            ));

            if (count($data["siswa"]) === $sebelum) {
                throw new DomainException("Data siswa tidak ditemukan.");
            }

        } else {
            throw new DomainException("Aksi tidak dikenal.");
        }

        return $data["siswa"];
    });

    kirim(200, ["ok" => true, "data" => $daftar]);

} catch (DomainException $e) {

    kirim(422, ["ok" => false, "pesan" => $e->getMessage()]);

} catch (RuntimeException $e) {

    error_log("Database JSON online: " . $e->getMessage());
    kirim(502, ["ok" => false, "pesan" => "Tidak dapat terhubung ke database online. Coba lagi."]);
}