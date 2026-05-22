<?php
session_start();
require_once __DIR__ . '/config.php';

$upload_dir = __DIR__ . '/uploads/';
$max_size = 5 * 1024 * 1024;
$allowed_ext = ['zip'];

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resource_file'])) {
    $file = $_FILES['resource_file'];
    $filename = $file['name'];
    $tmp_name = $file['tmp_name'];
    $size = $file['size'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_ext)) {
        $_SESSION['error'] = '只允许上传 ZIP 文件。';
    } elseif ($size > $max_size) {
        $_SESSION['error'] = '文件大小不能超过 5MB。';
    } elseif (!is_uploaded_file($tmp_name)) {
        $_SESSION['error'] = '上传失败，请重试。';
    } else {
        $stored_name = uniqid() . '_' . $filename;
        $dest = $upload_dir . $stored_name;

        if (move_uploaded_file($tmp_name, $dest)) {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO resources (original_name, stored_name, upload_time) VALUES (?, ?, ?)");
            $stmt->execute([$filename, $stored_name, time()]);
            $_SESSION['message'] = '上传成功。';
        } else {
            $_SESSION['error'] = '文件保存失败。';
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_GET['download'], $_GET['file'])) {
    $file_basename = basename($_GET['file']);
    $filepath = $upload_dir . $file_basename;

    if (!file_exists($filepath)) {
        die('文件不存在。');
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT original_name FROM resources WHERE stored_name = ? LIMIT 1");
    $stmt->execute([$file_basename]);
    $row = $stmt->fetch();
    $download_name = $row ? $row['original_name'] : $file_basename;

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . addslashes($download_name) . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}

$db = getDB();
$resources = $db->query("SELECT * FROM resources ORDER BY upload_time DESC")->fetchAll();

function format_size($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>甜瓜资源站</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 0; background: #f0f2f5; color: #333; line-height: 1.6; }
        .container { max-width: 1100px; margin: 0 auto; padding: 20px 16px 40px; }
        .header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 2px solid #4CAF50; }
        .header h1 { font-size: 1.8rem; margin: 0; color: #2e3b32; }
        .btn { display: inline-block; padding: 10px 22px; background: #4CAF50; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.95rem; text-decoration: none; transition: background 0.2s; }
        .btn:hover { background: #388E3C; }
        .message { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; }
        .msg-ok { background: #e8f5e9; color: #2e7d32; }
        .msg-err { background: #ffebee; color: #c62828; }
        .upload-card { background: #fff; border-radius: 12px; padding: 24px; margin-bottom: 30px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .upload-card h2 { margin-top: 0; color: #444; }
        .form-file { display: flex; flex-direction: column; gap: 12px; }
        .form-file input[type="file"] { padding: 8px; border: 1px solid #ccc; border-radius: 6px; background: #fafafa; width: 100%; }
        .table-wrapper { background: #fff; border-radius: 12px; padding: 0 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); overflow-x: auto; }
        .res-table { width: 100%; border-collapse: collapse; min-width: 550px; }
        .res-table th { text-align: left; padding: 14px 12px; background: #f5f5f5; font-weight: 600; color: #555; border-bottom: 2px solid #ddd; }
        .res-table td { padding: 12px 12px; border-bottom: 1px solid #eee; }
        .res-table tr:last-child td { border-bottom: none; }
        .res-table tr:hover td { background: #fafafa; }
        .file-name-col { min-width: 200px; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .download-link { color: #1565C0; text-decoration: none; font-weight: 500; }
        .download-link:hover { text-decoration: underline; }
        .empty { text-align: center; padding: 40px; color: #888; }
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: flex-start; }
            .res-table { font-size: 0.9rem; }
            .file-name-col { min-width: 140px; max-width: 200px; }
        }
        @media (max-width: 480px) {
            .form-file input[type="file"] { font-size: 0.9rem; }
            .btn { padding: 8px 16px; font-size: 0.9rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>甜瓜资源站</h1>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="message msg-ok"><?= htmlspecialchars($_SESSION['message']) ?></div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message msg-err"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="upload-card">
            <h2>上传资源</h2>
            <form method="post" enctype="multipart/form-data" class="form-file">
                <input type="file" name="resource_file" accept=".zip" required>
                <button type="submit" class="btn">上传 (最大 5MB)</button>
            </form>
        </div>

        <h2>资源列表</h2>
        <div class="table-wrapper">
            <?php if (empty($resources)): ?>
                <div class="empty">暂无资源，快上传第一个吧。</div>
            <?php else: ?>
                <table class="res-table">
                    <thead>
                        <tr>
                            <th class="file-name-col">文件名</th>
                            <th>大小</th>
                            <th>上传时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resources as $res): ?>
                        <tr>
                            <td class="file-name-col" title="<?= htmlspecialchars($res['original_name']) ?>">
                                <?= htmlspecialchars($res['original_name']) ?>
                            </td>
                            <td><?= format_size(filesize($upload_dir . $res['stored_name'])) ?></td>
                            <td><?= date('Y-m-d H:i', $res['upload_time']) ?></td>
                            <td>
                                <a class="download-link" href="?download=1&file=<?= urlencode($res['stored_name']) ?>">下载</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>