<?php
require 'koneksi.php';

$jenis = $_GET['jenis'] ?? 'mata_kuliah';
$id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);

$tabel = [
    'mata_kuliah' => 'mata_kuliah',
    'materi' => 'materi',
    'tugas' => 'tugas'
];

if (!isset($tabel[$jenis]) || !$id || $id < 1) {
    die('Jenis data atau ID tidak valid.');
}

$sql = "SELECT x.*";
if ($jenis != 'mata_kuliah') {
    $sql .= ", m.nama AS nama_mk";
}
$sql .= " FROM `{$tabel[$jenis]}` x";

if ($jenis != 'mata_kuliah') {
    $sql .= " JOIN mata_kuliah m ON x.mata_kuliah_id=m.id";
}
$sql .= " WHERE x.id=?";

$stmt = $conn->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->get_result()->fetch_assoc();

if (!$data) die('Data tidak ditemukan.');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Detail Data LMS</title>
<style>
body{font-family:Arial;background:#f3f5fa;padding:25px}
main{background:white;padding:22px;border-radius:10px;max-width:700px;margin:auto}
a{background:#234c83;color:white;padding:10px 15px;text-decoration:none;border-radius:5px}
</style>
</head>
<body>
<main>
<h1>Detail <?=e(ucwords(str_replace('_',' ',$jenis)))?></h1>
<?php foreach ($data as $kolom=>$nilai): ?>
<p><b><?=e(ucwords(str_replace('_',' ',$kolom)))?>:</b>
<?=e($nilai)?></p>
<?php endforeach; ?>
<p><a href="index.php?jenis=<?=e($jenis)?>">Kembali</a></p>
</main>
</body>
</html>