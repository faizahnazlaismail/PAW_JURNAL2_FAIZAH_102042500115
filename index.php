<?php
require 'koneksi.php';

$jenis = $_GET['jenis'] ?? 'mata_kuliah';
$aksi  = $_GET['aksi'] ?? 'daftar';
$id    = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);

$daftar = [
    'mata_kuliah' => [
        'tabel'=>'mata_kuliah',
        'nama'=>'Mata Kuliah',
        'kolom'=>['kode'=>'Kode','nama'=>'Nama','dosen'=>'Dosen','semester'=>'Semester'],
        'wajib'=>['kode','nama','dosen','semester']
    ],
    'materi' => [
        'tabel'=>'materi',
        'nama'=>'Materi',
        'kolom'=>['mata_kuliah_id'=>'Mata Kuliah','judul'=>'Judul','isi'=>'Isi','tautan'=>'Tautan'],
        'wajib'=>['mata_kuliah_id','judul','isi','tautan']
    ],
    'tugas' => [
        'tabel'=>'tugas',
        'nama'=>'Tugas',
        'kolom'=>['mata_kuliah_id'=>'Mata Kuliah','judul'=>'Judul','instruksi'=>'Instruksi','tenggat'=>'Tenggat','status'=>'Status'],
        'wajib'=>['mata_kuliah_id','judul','instruksi','tenggat','status']
    ]
];

if (!isset($daftar[$jenis])) $jenis = 'mata_kuliah';

$cfg = $daftar[$jenis];
$tabel = $cfg['tabel'];
$pesan = '';

function ambil($sql, $params = []) {
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($params) $stmt->execute($params);
    else $stmt->execute();
    $hasil = $stmt->get_result();
    $data = $hasil ? $hasil->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $data;
}

function simpan($sql, $params) {
    global $conn;
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $jumlah = $stmt->affected_rows;
    $stmt->close();
    return $jumlah;
}

function pindah($url) {
    header("Location: $url");
    exit;
}

function validasi($jenis, $d) {
    if ($jenis == 'mata_kuliah') {
        if (!preg_match('/^[A-Z0-9-]{3,20}$/', $d['kode']))
            return 'Kode harus 3-20 karakter kapital, angka, atau tanda -.';
        if (strlen($d['nama']) < 3 || strlen($d['nama']) > 100 ||
            strlen($d['dosen']) < 3 || strlen($d['dosen']) > 100)
            return 'Nama dan dosen harus 3-100 karakter.';
        if (!filter_var($d['semester'], FILTER_VALIDATE_INT) ||
            $d['semester'] < 1 || $d['semester'] > 14)
            return 'Semester harus angka 1-14.';
    } else {
        if (strlen($d['judul']) < 3 || strlen($d['judul']) > 150)
            return 'Judul harus 3-150 karakter.';
        if (!filter_var($d['mata_kuliah_id'], FILTER_VALIDATE_INT) ||
            !ambil("SELECT id FROM mata_kuliah WHERE id=?", [(int)$d['mata_kuliah_id']]))
            return 'Pilih mata kuliah yang tersedia.';

        $isi = $jenis == 'materi' ? $d['isi'] : $d['instruksi'];
        if (strlen($isi) < 3 || strlen($isi) > 2000)
            return 'Isi atau instruksi harus 3-2000 karakter.';

        if ($jenis == 'materi') {
            if (strlen($d['tautan']) > 255 ||
                !preg_match('~^https?://~i', $d['tautan']) ||
                !filter_var($d['tautan'], FILTER_VALIDATE_URL))
                return 'Tautan harus URL valid dengan http:// atau https://.';
        } else {
            $tanggal = DateTime::createFromFormat('!Y-m-d', $d['tenggat']);
            if (!$tanggal || $tanggal->format('Y-m-d') != $d['tenggat'])
                return 'Tanggal tenggat tidak valid.';
            if (!in_array($d['status'], ['Draf','Aktif','Selesai']))
                return 'Status tidak valid.';
        }
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!hash_equals($_SESSION['token'], $_POST['token'] ?? '')) {
        die('Token tidak valid. Refresh halaman lalu coba lagi.');
    }

    if (isset($_POST['hapus'])) {
        $hapusId = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        if (!$hapusId || $hapusId < 1) $pesan = 'ID tidak valid.';
        else {
            try {
                $jumlah = simpan("DELETE FROM `$tabel` WHERE id=?", [$hapusId]);
                $pesan = $jumlah ? 'Data berhasil dihapus.' : 'Data tidak ditemukan.';
            } catch (mysqli_sql_exception $ex) {
                $pesan = 'Data tidak bisa dihapus karena masih digunakan.';
            }
        }
    } else {
        $d = [];
        foreach ($cfg['wajib'] as $kolom) {
            $d[$kolom] = trim($_POST[$kolom] ?? '');
        }

        $error = validasi($jenis, $d);
        $editId = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);

        if ($aksi == 'edit' && (!$editId || $editId < 1))
            $error = 'ID tidak valid.';

        if ($error) {
            $pesan = $error;
        } else {
            try {
                if ($aksi == 'edit') {
                    $lama = ambil("SELECT * FROM `$tabel` WHERE id=?", [$editId]);
                    if (!$lama) {
                        $pesan = 'Data tidak ditemukan.';
                    } else {
                        $kolom = array_keys($d);
                        $set = implode(',', array_map(fn($k) => "`$k`=?", $kolom));
                        $nilai = array_values($d);
                        $nilai[] = $editId;
                        $jumlah = simpan("UPDATE `$tabel` SET $set WHERE id=?", $nilai);
                        $pesan = $jumlah ? 'Data berhasil diubah.' : 'Tidak ada perubahan data.';
                    }
                } else {
                    $kolom = array_keys($d);
                    $fields = implode(',', array_map(fn($k) => "`$k`", $kolom));
                    $tanda = implode(',', array_fill(0, count($kolom), '?'));
                    simpan("INSERT INTO `$tabel` ($fields) VALUES ($tanda)", array_values($d));
                    $pesan = 'Data berhasil ditambahkan.';
                }
            } catch (mysqli_sql_exception $ex) {
                $pesan = $ex->getCode() == 1062
                    ? 'Kode mata kuliah sudah digunakan.'
                    : 'Gagal menyimpan. Periksa kembali data.';
            }
        }
    }
}

if (isset($_GET['pesan'])) $pesan = $_GET['pesan'];

$edit = [];
if ($aksi == 'edit' && $id && $id > 0) {
    $hasilEdit = ambil("SELECT * FROM `$tabel` WHERE id=?", [$id]);
    if (!$hasilEdit) {
        $pesan = 'Data tidak ditemukan.';
        $aksi = 'daftar';
    } else $edit = $hasilEdit[0];
}

$cari = trim($_GET['cari'] ?? '');
$filter = (int)($_GET['filter'] ?? 0);
$kolomCari = $jenis == 'mata_kuliah' ? 'CONCAT(kode," ",nama)' : 'judul';

if ($jenis == 'mata_kuliah') {
    $data = ambil("SELECT * FROM mata_kuliah WHERE kode LIKE ? OR nama LIKE ? ORDER BY id DESC",
        ["%$cari%", "%$cari%"]);
} else {
    $sql = "SELECT x.*, m.nama AS nama_mk FROM `$tabel` x
            JOIN mata_kuliah m ON x.mata_kuliah_id=m.id WHERE x.`$kolomCari` LIKE ?";
    $params = ["%$cari%"];
    if ($filter > 0) {
        $sql .= " AND x.mata_kuliah_id=?";
        $params[] = $filter;
    }
    $data = ambil($sql . " ORDER BY x.id DESC", $params);
}

$mataKuliah = ambil("SELECT id,nama FROM mata_kuliah ORDER BY nama");
$jumlah = [
    'mata_kuliah'=>ambil("SELECT COUNT(*) AS n FROM mata_kuliah")[0]['n'],
    'materi'=>ambil("SELECT COUNT(*) AS n FROM materi")[0]['n'],
    'tugas'=>ambil("SELECT COUNT(*) AS n FROM tugas")[0]['n']
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LMS PAW</title>
<style>
body{font-family:Arial,sans-serif;background:#f3f5fa;margin:0;color:#263247}
header{background:#172b4d;color:white;padding:22px}
main{max-width:1050px;margin:25px auto;padding:0 16px}
nav,.ringkasan,.form-grid{display:flex;gap:12px;flex-wrap:wrap}
nav a,.btn{background:#234c83;color:white;padding:10px 14px;border:0;
border-radius:6px;text-decoration:none;cursor:pointer;display:inline-block}
.card{background:white;padding:18px;border-radius:10px;margin:14px 0;
box-shadow:0 2px 8px #0001}
.ringkasan .card{flex:1;min-width:130px}
input,select,textarea{box-sizing:border-box;width:100%;padding:10px;margin:6px 0 12px;
border:1px solid #ccd2df;border-radius:5px}
label{font-weight:bold}
table{border-collapse:collapse;width:100%;background:white}
th,td{padding:11px;border-bottom:1px solid #ddd;text-align:left}
th{background:#e8eef7}
.pesan{background:#fff0c2;padding:12px;border-radius:6px}
.aksi a{margin-right:8px}
</style>
</head>
<body>
<header><h1>LMS PAW</h1><p>Learning Management System sederhana</p></header>
<main>
<div class="ringkasan">
<?php foreach ($jumlah as $k=>$v): ?>
<div class="card"><b><?=e(ucwords(str_replace('_',' ',$k)))?></b>
<h2><?=e($v)?></h2></div>
<?php endforeach; ?>
</div>

<nav>
<a href="index.php">Beranda</a>
<a href="?jenis=mata_kuliah">Mata Kuliah</a>
<a href="?jenis=materi">Materi</a>
<a href="?jenis=tugas">Tugas</a>
</nav>

<?php if ($pesan): ?><p class="pesan"><?=e($pesan)?></p><?php endif; ?>

<?php if ($aksi == 'hapus'): ?>
<div class="card">
<h2>Konfirmasi Hapus</h2>
<p>Yakin ingin menghapus data ini?</p>
<form method="post" action="index.php?jenis=<?=e($jenis)?>">
<input type="hidden" name="token" value="<?=e($_SESSION['token'])?>">
<input type="hidden" name="id" value="<?=e($id)?>">
<button class="btn" name="hapus" value="1">Ya, Hapus</button>
<a class="btn" href="?jenis=<?=e($jenis)?>">Batal</a>
</form>
</div>

<?php elseif ($aksi == 'tambah' || $aksi == 'edit'): ?>
<div class="card">
<h2><?= $aksi == 'edit' ? 'Edit ' : 'Tambah ' ?><?=e($cfg['nama'])?></h2>
<form method="post" action="index.php?jenis=<?=e($jenis)?>&aksi=<?=e($aksi)?><?= $aksi=='edit' ? '&id='.(int)$id : '' ?>">
<input type="hidden" name="token" value="<?=e($_SESSION['token'])?>">
<?php if ($aksi == 'edit'): ?><input type="hidden" name="id" value="<?=e($id)?>"><?php endif; ?>

<?php foreach ($cfg['kolom'] as $k=>$label): ?>
<label><?=e($label)?></label>
<?php if ($k == 'mata_kuliah_id'): ?>
<select name="<?=$k?>" required>
<option value="">Pilih Mata Kuliah</option>
<?php foreach ($mataKuliah as $mk): ?>
<option value="<?=$mk['id']?>" <?=($edit[$k]??'')==$mk['id']?'selected':''?>>
<?=e($mk['nama'])?></option>
<?php endforeach; ?>
</select>
<?php elseif ($k == 'isi' || $k == 'instruksi'): ?>
<textarea name="<?=$k?>" minlength="3" maxlength="2000" required><?=e($edit[$k]??'')?></textarea>
<?php elseif ($k == 'semester'): ?>
<input type="number" name="<?=$k?>" min="1" max="14" value="<?=e($edit[$k]??'')?>" required>
<?php elseif ($k == 'tenggat'): ?>
<input type="date" name="<?=$k?>" value="<?=e($edit[$k]??'')?>" required>
<?php elseif ($k == 'status'): ?>
<select name="status" required>
<?php foreach (['Draf','Aktif','Selesai'] as $s): ?>
<option value="<?=$s?>" <?=($edit['status']??'Draf')==$s?'selected':''?>><?=$s?></option>
<?php endforeach; ?>
</select>
<?php else: ?>
<input name="<?=$k?>" maxlength="<?= $k=='tautan'?255:(in_array($k,['judul'])?150:100) ?>"
value="<?=e($edit[$k]??'')?>" required>
<?php endif; ?>
<?php endforeach; ?>
<button class="btn" type="submit">Simpan</button>
<a class="btn" href="?jenis=<?=e($jenis)?>">Kembali</a>
</form>
</div>

<?php else: ?>
<div class="card">
<h2>Daftar <?=e($cfg['nama'])?></h2>
<a class="btn" href="?jenis=<?=e($jenis)?>&aksi=tambah">+ Tambah Data</a>
<form method="get">
<input type="hidden" name="jenis" value="<?=e($jenis)?>">
<input name="cari" placeholder="Cari data..." value="<?=e($cari)?>">
<?php if ($jenis != 'mata_kuliah'): ?>
<select name="filter">
<option value="0">Semua Mata Kuliah</option>
<?php foreach ($mataKuliah as $mk): ?>
<option value="<?=$mk['id']?>" <?=$filter==$mk['id']?'selected':''?>><?=e($mk['nama'])?></option>
<?php endforeach; ?>
</select>
<?php endif; ?>
<button class="btn">Cari</button>
<a class="btn" href="?jenis=<?=e($jenis)?>">Reset</a>
</form>
<table>
<tr><th>ID</th><th>Data</th><th>Detail</th><th>Aksi</th></tr>
<?php foreach ($data as $row): ?>
<tr>
<td><?=e($row['id'])?></td>
<td>
<?php if ($jenis=='mata_kuliah'): ?>
<b><?=e($row['kode'])?></b> - <?=e($row['nama'])?><br>
Dosen: <?=e($row['dosen'])?> | Semester: <?=e($row['semester'])?>
<?php else: ?>
<b><?=e($row['judul'])?></b><br>
Mata Kuliah: <?=e($row['nama_mk'])?>
<?php if ($jenis=='materi'): ?><br><?=e($row['tautan'])?>
<?php else: ?><br>Tenggat: <?=e($row['tenggat'])?> | <?=e($row['status'])?><?php endif; ?>
<?php endif; ?>
</td>
<td><a href="detail.php?jenis=<?=e($jenis)?>&id=<?=$row['id']?>">Lihat</a></td>
<td class="aksi">
<a href="?jenis=<?=e($jenis)?>&aksi=edit&id=<?=$row['id']?>">Edit</a>
<a href="?jenis=<?=e($jenis)?>&aksi=hapus&id=<?=$row['id']?>">Hapus</a>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php if (!$data): ?><p>Data tidak ditemukan.</p><?php endif; ?>
</div>
<?php endif; ?>
</main>
</body>
</html>