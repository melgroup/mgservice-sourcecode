<?php
session_start();

if (file_exists(__DIR__ . '/config.php')) {
    die('网站已安装。如需重新安装，请删除 config.php 后再访问本页面。');
}

$step = $_GET['step'] ?? 1;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';
    $db_name = $_POST['db_name'] ?? '';
    $admin_password = $_POST['admin_password'] ?? '';

    if (empty($db_user) || empty($db_name) || empty($admin_password)) {
        $error = '请填写所有必填项。';
    } else {
        try {
            $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $pdo->exec("CREATE TABLE IF NOT EXISTS resources (
                id INT AUTO_INCREMENT PRIMARY KEY,
                original_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(255) NOT NULL,
                upload_time INT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $config_content = "<?php\n\n";
            $config_content .= "define('DB_HOST', " . var_export($db_host, true) . ");\n";
            $config_content .= "define('DB_USER', " . var_export($db_user, true) . ");\n";
            $config_content .= "define('DB_PASS', " . var_export($db_pass, true) . ");\n";
            $config_content .= "define('DB_NAME', " . var_export($db_name, true) . ");\n";
            $config_content .= "define('ADMIN_PASSWORD', " . var_export($admin_password, true) . ");\n";

            file_put_contents(__DIR__ . '/config.php', $config_content);

            if (!file_exists(__DIR__ . '/uploads')) {
                mkdir(__DIR__ . '/uploads', 0755, true);
            }

            $_SESSION['install_success'] = true;
            header('Location: install.php?step=3');
            exit;
        } catch (PDOException $e) {
            $error = '数据库连接失败：' . $e->getMessage();
        }
    }
}

if (($step == 3) && isset($_SESSION['install_success'])) {
    unset($_SESSION['install_success']);
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>安装成功 - 甜瓜资源站</title>
        <style>
            body { font-family: 'Segoe UI', Roboto, sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
            .box { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 90%; }
            h2 { color: #2e7d32; margin-top: 0; }
            p { color: #555; }
            .btn { display: inline-block; padding: 12px 28px; background: #4CAF50; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 20px; }
            .btn:hover { background: #388E3C; }
            .warning { color: #d32f2f; font-weight: bold; margin-top: 25px; }
        </style>
    </head>
    <body>
        <div class="box">
            <h2>安装成功！</h2>
            <p>数据库已配置，管理员账户已创建。</p>
            <p class="warning">请立即删除服务器上的 <code>install.php</code> 文件，以免被他人利用。</p>
            <a href="index.php" class="btn">进入网站</a>
            <a href="admin.php" class="btn" style="background:#1565C0; margin-left:10px;">管理后台</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装向导 - 甜瓜资源站</title>
    <style>
        body { font-family: 'Segoe UI', Roboto, sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .install-box { background: #fff; padding: 35px 40px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); width: 100%; max-width: 480px; }
        h2 { color: #333; margin-top: 0; text-align: center; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: #444; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; transition: border-color 0.2s; }
        input:focus { border-color: #4CAF50; outline: none; }
        .error { background: #ffebee; color: #c62828; padding: 10px; border-radius: 6px; margin-bottom: 18px; font-size: 14px; }
        .submit-btn { width: 100%; padding: 12px; background: #4CAF50; color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 8px; }
        .submit-btn:hover { background: #388E3C; }
        .hint { font-size: 13px; color: #777; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="install-box">
        <h2>甜瓜资源站 · 安装</h2>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label>数据库主机</label>
                <input type="text" name="db_host" value="localhost" required>
            </div>
            <div class="form-group">
                <label>数据库用户名</label>
                <input type="text" name="db_user" required>
            </div>
            <div class="form-group">
                <label>数据库密码</label>
                <input type="password" name="db_pass">
            </div>
            <div class="form-group">
                <label>数据库名称</label>
                <input type="text" name="db_name" required>
                <div class="hint">请确保数据库已存在，程序将自动创建所需数据表。</div>
            </div>
            <div class="form-group">
                <label>管理员密码</label>
                <input type="password" name="admin_password" required>
            </div>
            <button type="submit" class="submit-btn">开始安装</button>
        </form>
    </div>
</body>
</html>