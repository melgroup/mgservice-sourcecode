<?php
session_start();
require_once __DIR__ . '/config.php';

$upload_dir = __DIR__ . '/uploads/';

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

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin']);
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login' && isset($_POST['password'])) {
        if ($_POST['password'] === ADMIN_PASSWORD) {
            $_SESSION['admin'] = true;
            header('Location: admin.php');
            exit;
        } else {
            $_SESSION['login_error'] = '密码错误';
        }
    }

    if ($_POST['action'] === 'delete' && isset($_POST['file']) && isset($_SESSION['admin'])) {
        $file = basename($_POST['file']);
        $filepath = $upload_dir . $file;

        $db = getDB();
        $stmt = $db->prepare("DELETE FROM resources WHERE stored_name = ?");
        $stmt->execute([$file]);

        if (file_exists($filepath)) {
            unlink($filepath);
        }

        $_SESSION['admin_message'] = '删除成功';
        header('Location: admin.php');
        exit;
    }
}

$is_admin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

$resources = [];
if ($is_admin) {
    $db = getDB();
    $resources = $db->query("SELECT * FROM resources ORDER BY upload_time DESC")->fetchAll();
}

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
    <title>管理后台 - 甜瓜资源站</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 0; background: #f0f2f5; color: #333; line-height: 1.6; }
        .container { max-width: 1100px; margin: 0 auto; padding: 20px 16px 40px; }
        .header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 2px solid #e74c3c; }
        .header h1 { font-size: 1.8rem; margin: 0; color: #2e3b32; }
        .btn { display: inline-block; padding: 10px 22px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.95rem; text-decoration: none; transition: background 0.2s; }
        .btn-outline { background: #fff; color: #333; border: 1px solid #ccc; }
        .btn-outline:hover { background: #f5f5f5; }
        .btn-danger { background: #e74c3c; color: #fff; }
        .btn-danger:hover { background: #c0392b; }
        .message { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; }
        .msg-ok { background: #e8f5e9; color: #2e7d32; }
        .msg-err { background: #ffebee; color: #c62828; }
        .login-card { background: #fff; border-radius: 12px; padding: 40px; max-width: 420px; margin: 60px auto; box-shadow: 0 4px 20px rgba(0,0,0,0.08); text-align: center; }
        .login-card h2 { margin-top: 0; }
        .form-group { margin-bottom: 18px; text-align: left; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; }
        .form-group input { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; }
        .form-group input:focus { border-color: #4CAF50; outline: none; }
        .table-wrapper { background: #fff; border-radius: 12px; padding: 0 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); overflow-x: auto; }
        .res-table { width: 100%; border-collapse: collapse; min-width: 550px; }
        .res-table th { text-align: left; padding: 14px 12px; background: #f5f5f5; font-weight: 600; color: #555; border-bottom: 2px solid #ddd; }
        .res-table td { padding: 12px 12px; border-bottom: 1px solid #eee; }
        .res-table tr:last-child td { border-bottom: none; }
        .res-table tr:hover td { background: #fafafa; }
        .file-name-col { min-width: 200px; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .delete-btn { background: none; border: none; color: #e74c3c; cursor: pointer; font-weight: 500; font-size: 0.9rem; }
        .delete-btn:hover { text-decoration: underline; }
        .empty { text-align: center; padding: 40px; color: #888; }
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: flex-start; }
            .login-card { margin: 30px auto; padding: 24px; }
            .res-table { font-size: 0.9rem; }
            .file-name-col { min-width: 140px; max-width: 200px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$is_admin): ?>
            <div class="login-card">
                <h2>管理员登录</h2>
                <?php if (isset($_SESSION['login_error'])): ?>
                    <div class="message msg-err"><?= htmlspecialchars($_SESSION['login_error']) ?></div>
                    <?php unset($_SESSION['login_error']); ?>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label for="password">管理员密码</label>
                        <input type="password" name="password" id="password" required>
                    </div>
                    <button type="submit" class="btn btn-danger" style="width:100%;">登录</button>
                </form>
                <p style="margin-top:20px;"><a href="index.php" style="color:#666;">返回首页</a></p>
            </div>
        <?php else: ?>
            <div class="header">
                <h1>管理后台</h1>
                <div style="display:flex; gap:10px;">
                    <a href="index.php" class="btn btn-outline">返回首页</a>
                    <a href="?action=logout" class="btn btn-danger">退出登录</a>
                </div>
            </div>

            <?php if (isset($_SESSION['admin_message'])): ?>
                <div class="message msg-ok"><?= htmlspecialchars($_SESSION['admin_message']) ?></div>
                <?php unset($_SESSION['admin_message']); ?>
            <?php endif; ?>

            <h2>资源管理</h2>
            <div class="table-wrapper">
                <?php if (empty($resources)): ?>
                    <div class="empty">暂无资源。</div>
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
                                    <form method="post" style="display:inline;" onsubmit="return confirm('确认删除？')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="file" value="<?= htmlspecialchars($res['stored_name']) ?>">
                                        <button type="submit" class="delete-btn">删除</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>