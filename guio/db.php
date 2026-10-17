<?php
/**
 * Akses "database" JSON online (JSONBin.io).
 *
 * Seluruh data disimpan dalam satu bin dengan bentuk:
 * {
 *   "users": [ { "username": "...", "password": "<hash>", "nama": "..." } ],
 *   "siswa": [ { "id": "...", "nama": "...", "nis": "...", "kelas": "...",
 *               "gender": "...", "hobi": [...], "alamat": "..." } ]
 * }
 */

$GLOBALS["db_config"] = require __DIR__ . "/config.php";


// Kirim permintaan ke JSONBin. Melempar RuntimeException jika gagal.
function dbRequest(string $method, string $path, ?array $body = null): array
{
    $cfg = $GLOBALS["db_config"];

    if (strpos($cfg["bin_id"], "ISI_") === 0 || strpos($cfg["api_key"], "ISI_") === 0) {
        throw new RuntimeException("config.php belum diisi (bin_id / api_key).");
    }

    $headers = [
        "X-Master-Key: " . $cfg["api_key"],
        "Accept: application/json",
    ];

    $ch = curl_init(rtrim($cfg["base_url"], "/") . $path);

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 20,
    ]);

    if ($body !== null) {
        $headers[] = "Content-Type: application/json";
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($raw === false) {
        throw new RuntimeException("Koneksi ke JSON online gagal: " . $err);
    }

    $json = json_decode($raw, true);

    if ($code >= 400) {
        $pesan = is_array($json) ? ($json["message"] ?? "tanpa pesan") : "tanpa pesan";
        throw new RuntimeException("JSON online menolak permintaan (HTTP $code): $pesan");
    }

    if (!is_array($json)) {
        throw new RuntimeException("Respons JSON online tidak valid.");
    }

    return $json;
}


// Baca seluruh data terbaru dari bin
function dbRead(): array
{
    $cfg  = $GLOBALS["db_config"];
    $json = dbRequest("GET", "/b/" . rawurlencode($cfg["bin_id"]) . "/latest?meta=false");

    // Jaga-jaga jika respons masih dibungkus {record, metadata}
    if (isset($json["record"], $json["metadata"]) && is_array($json["record"])) {
        $json = $json["record"];
    }

    return [
        "users" => is_array($json["users"] ?? null) ? array_values($json["users"]) : [],
        "siswa" => is_array($json["siswa"] ?? null) ? array_values($json["siswa"]) : [],
    ];
}


// Simpan seluruh data ke bin (menimpa isi sebelumnya)
function dbWrite(array $data): void
{
    $cfg = $GLOBALS["db_config"];

    $data["users"] = array_values($data["users"] ?? []);
    $data["siswa"] = array_values($data["siswa"] ?? []);

    dbRequest("PUT", "/b/" . rawurlencode($cfg["bin_id"]), $data);
}


// Baca -> ubah -> simpan. Callback menerima $data (by reference) dan
// boleh melempar exception untuk membatalkan penyimpanan.
function dbUpdate(callable $fn)
{
    $data  = dbRead();
    $hasil = $fn($data);

    dbWrite($data);

    return $hasil;
}


// Cari user berdasarkan username (tidak peka huruf besar/kecil)
function dbFindUser(string $username): ?array
{
    foreach (dbRead()["users"] as $user) {
        if (isset($user["username"]) && strcasecmp($user["username"], $username) === 0) {
            return $user;
        }
    }

    return null;
}