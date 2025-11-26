<?php
// Test file to verify session configuration
// Access this file directly in browser to check session status

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.ruhanixlegal.in',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

$sessionDir = __DIR__ . "/sessions";
if (!file_exists($sessionDir)) {
    mkdir($sessionDir, 0777, true);
}
session_save_path($sessionDir);
session_start();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Debug Tool</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f7fa;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-top: 0;
        }
        h2 {
            color: #00A9A5;
            border-bottom: 2px solid #e0e6ed;
            padding-bottom: 8px;
        }
        .status {
            padding: 12px;
            border-radius: 6px;
            margin: 10px 0;
            font-weight: 600;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e0e6ed;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 13px;
        }
        .cookie-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 12px;
            border-radius: 6px;
            margin: 10px 0;
        }
        .btn {
            background: #00A9A5;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 10px;
        }
        .btn:hover {
            background: #008f8c;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔍 Session Debug Tool</h1>
        <p>This page helps diagnose session configuration issues.</p>
    </div>

    <div class="card">
        <h2>Session Status</h2>
        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
            <div class="status success">
                ✅ Session Active - User is logged in
            </div>
        <?php else: ?>
            <div class="status error">
                ❌ No Active Session - User is NOT logged in
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Session Configuration</h2>
        <table>
            <tr>
                <th>Parameter</th>
                <th>Value</th>
                <th>Status</th>
            </tr>
            <tr>
                <td>Session ID</td>
                <td><code><?php echo session_id() ?: 'Not set'; ?></code></td>
                <td><?php echo session_id() ? '✅' : '❌'; ?></td>
            </tr>
            <tr>
                <td>Session Save Path</td>
                <td><code><?php echo session_save_path(); ?></code></td>
                <td><?php echo is_writable(session_save_path()) ? '✅ Writable' : '❌ Not writable'; ?></td>
            </tr>
            <tr>
                <td>Cookie Domain</td>
                <td><code><?php 
                    $params = session_get_cookie_params();
                    echo $params['domain'] ?: '(empty)';
                ?></code></td>
                <td><?php echo ($params['domain'] === '.ruhanixlegal.in') ? '✅ Correct' : '⚠️ Check config'; ?></td>
            </tr>
            <tr>
                <td>Cookie Path</td>
                <td><code><?php echo $params['path']; ?></code></td>
                <td><?php echo ($params['path'] === '/') ? '✅' : '⚠️'; ?></td>
            </tr>
            <tr>
                <td>Cookie Lifetime</td>
                <td><code><?php echo $params['lifetime']; ?></code> seconds</td>
                <td><?php echo ($params['lifetime'] === 0) ? '✅ Session cookie' : 'ℹ️ Persistent'; ?></td>
            </tr>
            <tr>
                <td>HttpOnly</td>
                <td><code><?php echo $params['httponly'] ? 'true' : 'false'; ?></code></td>
                <td><?php echo $params['httponly'] ? '✅ Secure' : '⚠️ Insecure'; ?></td>
            </tr>
            <tr>
                <td>SameSite</td>
                <td><code><?php echo $params['samesite']; ?></code></td>
                <td><?php echo ($params['samesite'] === 'Lax') ? '✅' : 'ℹ️'; ?></td>
            </tr>
        </table>
    </div>

    <div class="card">
        <h2>Session Data</h2>
        <?php if (!empty($_SESSION)): ?>
            <table>
                <tr>
                    <th>Key</th>
                    <th>Value</th>
                </tr>
                <?php foreach ($_SESSION as $key => $value): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($key); ?></code></td>
                        <td><code><?php echo htmlspecialchars(is_array($value) ? json_encode($value) : $value); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <div class="status info">
                ℹ️ No session data available. Please sign in first.
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Session Files</h2>
        <?php
        $sessionDir = __DIR__ . "/sessions";
        if (is_dir($sessionDir)) {
            $files = glob($sessionDir . '/sess_*');
            if (count($files) > 0) {
                echo '<div class="status success">✅ Found ' . count($files) . ' session file(s)</div>';
                echo '<table>';
                echo '<tr><th>File</th><th>Modified</th><th>Size</th></tr>';
                foreach (array_slice($files, 0, 10) as $file) {
                    $basename = basename($file);
                    $mtime = date('Y-m-d H:i:s', filemtime($file));
                    $size = filesize($file);
                    echo "<tr><td><code>$basename</code></td><td>$mtime</td><td>$size bytes</td></tr>";
                }
                echo '</table>';
                if (count($files) > 10) {
                    echo '<p><em>Showing 10 of ' . count($files) . ' files</em></p>';
                }
            } else {
                echo '<div class="status info">ℹ️ No session files found. This is normal if no one is logged in.</div>';
            }
        } else {
            echo '<div class="status error">❌ Session directory not found: ' . htmlspecialchars($sessionDir) . '</div>';
        }
        ?>
    </div>

    <div class="card">
        <h2>Cookie Information</h2>
        <div class="cookie-info">
            <strong>Expected Cookie:</strong><br>
            Name: <code>PHPSESSID</code><br>
            Domain: <code>.ruhanixlegal.in</code><br>
            Path: <code>/</code><br>
            HttpOnly: <code>✓</code><br>
            SameSite: <code>Lax</code>
        </div>
        <p><strong>To check actual cookies:</strong></p>
        <ol>
            <li>Press F12 to open DevTools</li>
            <li>Go to "Application" tab (or "Storage" in Firefox)</li>
            <li>Click "Cookies" → Select your domain</li>
            <li>Find <code>PHPSESSID</code> cookie</li>
            <li>Verify it matches the expected values above</li>
        </ol>
    </div>

    <div class="card">
        <h2>Quick Actions</h2>
        <button class="btn" onclick="location.reload()">🔄 Refresh Page</button>
        <button class="btn" onclick="window.location.href='check_session.php'" style="background:#1a75bb">
            🔐 Test check_session.php
        </button>
        <button class="btn" onclick="window.location.href='signin.html'" style="background:#6c757d">
            👤 Go to Sign In
        </button>
    </div>

    <div class="card">
        <h2>Troubleshooting</h2>
        <div class="status info">
            <strong>If session is not working:</strong>
            <ol style="margin: 10px 0; padding-left: 20px;">
                <li>Verify cookie domain is <code>.ruhanixlegal.in</code> (with leading dot)</li>
                <li>Check session directory is writable: <code><?php echo session_save_path(); ?></code></li>
                <li>Ensure all PHP files use identical session configuration</li>
                <li>Clear browser cookies and localStorage</li>
                <li>Check PHP error logs for session-related errors</li>
                <li>Sign in again and check if session data appears above</li>
            </ol>
        </div>
    </div>

    <div style="text-align: center; color: #6c757d; padding: 20px; font-size: 12px;">
        Session Debug Tool v1.0 | Generated: <?php echo date('Y-m-d H:i:s'); ?>
    </div>
</body>
</html>
