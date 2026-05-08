<?php
// 启用错误显示（调试用）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 获取用户参数（用于表单回显）
$k = isset($_POST['k']) ? floatval($_POST['k']) : 0.75;
$val = isset($_POST['val']) ? floatval($_POST['val']) : 600.0;
$di_dt_max = isset($_POST['di_dt_max']) ? floatval($_POST['di_dt_max']) : 200000.0;
$dref = isset($_POST['dref']) ? floatval($_POST['dref']) : 44400.0;

// 参数范围限制
$k = max(0.1, min(2.0, $k));
$val = max(10, min(2000, $val));
$di_dt_max = max(10000, min(500000, $di_dt_max));
$dref = max(1000, min(200000, $dref));

// 初始化结果变量（默认空）
$image_url = '';
$table_content = '';
$table_file_to_show = '';
$output = '';

// 仅在 POST 请求时执行仿真（点击按钮后）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 生成唯一表格文件名
    $timestamp = time() . '_' . bin2hex(random_bytes(4));
    $output_table = "result_{$timestamp}.txt";

    // 格式化浮点数（保留一位小数，匹配 Python 输出）
    $formatted_val = number_format($val, 1, '.', '');
    $formatted_di_dt_max = number_format($di_dt_max, 1, '.', '');
    $formatted_dref = number_format($dref, 1, '.', '');

    // Python 脚本路径
    $python_script = __DIR__ . '/okapi.py';

    // 构建命令
    $cmd = escapeshellcmd("python3 " . $python_script)
         . " --k " . escapeshellarg($k)
         . " --val " . escapeshellarg($val)
         . " --di_dt_max " . escapeshellarg($di_dt_max)
         . " --dref " . escapeshellarg($dref)
         . " --output_table " . escapeshellarg($output_table);

    // 执行命令
    $output = shell_exec($cmd . " 2>&1");

    // 1. 优先从输出中解析带时间戳的图片文件名
    if (preg_match('/图片已保存为:\s*([^\s]+\.png)/', $output, $matches)) {
        $candidate = trim($matches[1]);
        if (file_exists($candidate)) {
            $image_url = $candidate;
        }
    }

    // 2. 若解析失败，使用通配符查找（兼容旧版本或无时间戳的文件）
    if (empty($image_url)) {
        $pattern = "Figure4_k{$k}_Imax{$formatted_val}_didt{$formatted_di_dt_max}_Dref{$formatted_dref}_dynamic*.png";
        $files = glob($pattern);
        if (!empty($files)) {
            $image_url = $files[0];
        }
    }

    // 读取表格内容
    if (file_exists($output_table)) {
        $table_content = file_get_contents($output_table);
        $table_file_to_show = $output_table;
        $lines = explode("\n", $table_content);
        if (count($lines) > 1000) {
            $table_content = implode("\n", array_slice($lines, 0, 1000)) . "\n... (表格过长，仅显示前1000行，完整内容请下载)";
        }
    }

    // 清理旧文件（保留最近1小时内的表格和最多100张图片）
    $old_txt = glob("result_*.txt");
    foreach ($old_txt as $f) {
        if (filemtime($f) < time() - 3600) @unlink($f);
    }
    $old_png = glob("Figure4_*.png");
    if (count($old_png) > 100) {
        usort($old_png, function($a, $b) { return filemtime($a) - filemtime($b); });
        $to_delete = array_slice($old_png, 0, count($old_png) - 100);
        foreach ($to_delete as $f) @unlink($f);
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>OpenOkapi · Web仿真 · 储能动态响应工具</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* 官网完整样式 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0a0a0a;
            color: #e0e0e0;
            line-height: 1.6;
            scroll-behavior: smooth;
            padding-top: 80px;
        }
        :root {
            --zebra-light: rgba(255, 255, 255, 0.03);
            --zebra-dark: rgba(255, 255, 255, 0.08);
            --primary: #d4a5ff;
            --primary-glow: #b77cf2;
            --accent: #ffb86b;
        }
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(10, 10, 10, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(212, 165, 255, 0.15);
            z-index: 1000;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.5rem;
            box-shadow: 0 4px 30px rgba(0,0,0,0.8);
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .logo a {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            text-decoration: none;
            color: inherit;
            background: linear-gradient(135deg, #d4a5ff, #ffb86b);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .logo i {
            font-size: 2.2rem;
            color: #d4a5ff;
            filter: drop-shadow(0 0 8px #b77cf2);
        }
        .nav-links {
            display: flex;
            gap: 2.2rem;
            font-weight: 500;
            flex-wrap: wrap;
        }
        .nav-links a {
            color: #ccc;
            text-decoration: none;
            font-size: 1.1rem;
            transition: 0.2s;
            border-bottom: 2px solid transparent;
            padding-bottom: 4px;
        }
        .nav-links a:hover {
            color: #d4a5ff;
            border-bottom-color: #d4a5ff;
        }
        .container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        section {
            margin-bottom: 7rem;
            scroll-margin-top: 90px;
        }
        .card {
            background: #111;
            border: 1px solid #2a2a2a;
            border-radius: 32px;
            padding: 2rem 1.8rem;
            transition: all 0.3s ease;
            box-shadow: 0 20px 30px -15px rgba(0,0,0,0.8);
            position: relative;
            overflow: hidden;
        }
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(45deg, transparent, transparent 20px, rgba(212,165,255,0.02) 20px, rgba(212,165,255,0.02) 40px);
            pointer-events: none;
        }
        .card:hover {
            border-color: #d4a5ff;
            transform: translateY(-8px);
            box-shadow: 0 25px 35px -12px #b77cf270;
        }
        .card i {
            font-size: 2.6rem;
            color: #d4a5ff;
            margin-bottom: 1.2rem;
            filter: drop-shadow(0 0 10px #b77cf2);
        }
        .card h3 {
            font-size: 1.7rem;
            font-weight: 600;
            margin-bottom: 0.8rem;
            color: #f0f0f0;
        }
        .card p {
            color: #999;
            font-size: 1rem;
        }
        .param-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 1.5rem 0;
        }
        .param-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #d4a5ff;
            font-weight: 600;
        }
        .param-group input {
            width: 100%;
            padding: 0.8rem;
            background: #1e1e1e;
            border: 1px solid #333;
            border-radius: 24px;
            color: #fff;
            font-family: monospace;
        }
        button {
            background: #d4a5ff;
            color: #0a0a0a;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 40px;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.2s;
        }
        button:hover {
            background: #e2b9ff;
            transform: scale(1.02);
        }
        .result-image {
            text-align: center;
            margin: 2rem 0;
        }
        .result-image img {
            max-width: 100%;
            border-radius: 24px;
            border: 1px solid #2a2a2a;
            cursor: pointer;
        }
        pre {
            background: #0e0e0e;
            padding: 1rem;
            border-radius: 16px;
            overflow-x: auto;
            font-size: 0.8rem;
            font-family: monospace;
            border-left: 4px solid #d4a5ff;
        }
        .download-link {
            display: inline-block;
            margin-top: 1rem;
            color: #d4a5ff;
        }
        .error-msg {
            color: #ff8888;
            background: #2a0a0a;
            padding: 1rem;
            border-radius: 16px;
            margin: 1rem 0;
        }
        footer {
            text-align: center;
            padding: 3rem 0 2rem;
            border-top: 1px solid #222;
            color: #666;
        }
        @media (max-width: 700px) {
            body { padding-top: 120px; }
            .navbar { padding: 1rem; flex-direction: column; align-items: stretch; }
            .nav-links { justify-content: center; gap: 1rem; }
        }
    </style>
</head>
<body>

<!-- 固定导航栏（与官网一致） -->
<nav class="navbar">
    <div class="logo">
        <a href="https://openokapi.com">
            <span style="font-size: 3rem;">🦒</span>
            <span>OpenOkapi</span>
        </a>
    </div>
    <div class="nav-links">
        <a href="https://openokapi.com/">首页</a>
        <a href="https://openokapi.com/#tool">仿真工具</a>
        <a href="https://openokapi.com/#features">技术特性</a>
        <a href="https://openokapi.com/#gallery">仿真图表</a>
        <a href="https://openokapi.com/#roadmap">路线图</a>
        <a href="https://php.openokapi.com">Web仿真</a>
        <a href="https://openokapi.com/#contact">联系我们</a>
    </div>
</nav>
</br>
</br>
<main class="container">
    <section>
        <div class="card" style="margin-top: 1rem;">
            <h2 style="display: flex; align-items: center; gap: 10px;"><i class="fas fa-microchip"></i> 在线储能仿真</h2>
            <p>基于电网电流di/dt的跟随/保持控制策略仿真工具。<br>修改参数后点击「开始仿真」。</p>
            <form method="post">
                <div class="param-grid">
                    <div class="param-group">
                        <label>比例系数 k</label>
                        <input type="number" step="0.01" name="k" value="<?= htmlspecialchars($k) ?>" required>
                    </div>
                    <div class="param-group">
                        <label>最大补偿电流 I_max (A)</label>
                        <input type="number" step="10" name="val" value="<?= htmlspecialchars($val) ?>" required>
                    </div>
                    <div class="param-group">
                        <label>储能自身电流变化率 (A/s)</label>
                        <input type="number" step="10000" name="di_dt_max" value="<?= htmlspecialchars($di_dt_max) ?>" required>
                    </div>
                    <div class="param-group">
                        <label>电网电流di/dt阈值 Dref (A/s)</label>
                        <input type="number" step="1000" name="dref" value="<?= htmlspecialchars($dref) ?>" required>
                    </div>
                </div>
                <button type="submit"><i class="fas fa-play"></i> 开始仿真</button>
            </form>
        </div>
</br>
</br>
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div class="card">
            <h2>仿真结果</h2>
            <?php if ($image_url && file_exists($image_url)): ?>
            <div class="result-image">
                <a href="<?= htmlspecialchars($image_url) ?>" target="_blank">
                    <img src="<?= htmlspecialchars($image_url) ?>" alt="仿真曲线">
                </a>
                <p><small>点击图片查看原图</small></p>
            </div>
            <?php else: ?>
            <div class="error-msg">
                <strong>⚠️ 未能生成图片。</strong><br>
                Python 脚本输出：<br>
                <pre><?= htmlspecialchars($output ?: '(无输出)') ?></pre>
            </div>
            <?php endif; ?>

            <?php if ($table_content): ?>
            <h3>数据表格</h3>
            <pre><?= htmlspecialchars($table_content) ?></pre>
            <a href="<?= htmlspecialchars($table_file_to_show) ?>" class="download-link" download>📥 下载完整表格 (TXT)</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>
</main>

<!-- 页脚 -->
<footer>
    <p>© 2026 OpenOkapi · 开源储能仿真工具 · 基于电流di/dt的动态响应算法</p>
    <p style="margin-top: 0.8rem; font-size: 0.9rem;">MIT License · 灵感源自 Okapi 的优雅条纹与电力暂态波形</p>
    <!-- Matomo 统计保留 -->
    <script>
      var _paq = window._paq = window._paq || [];
      _paq.push(['trackPageView']);
      _paq.push(['enableLinkTracking']);
      (function() {
        var u="//geoffhinton.com/Ana/";
        _paq.push(['setTrackerUrl', u+'matomo.php']);
        _paq.push(['setSiteId', '1']);
        var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
        g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
      })();
    </script>
</footer>
</body>
</html>