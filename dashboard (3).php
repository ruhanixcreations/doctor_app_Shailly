<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.ruhanixlegal.in',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

$sessionDir = "/home4/ruhanixlegal/public_html/environment_project/sessions";
if (!file_exists($sessionDir)) {
    mkdir($sessionDir, 0777, true);
}
session_save_path($sessionDir);
session_start();

date_default_timezone_set('Asia/Kolkata');

require_once $_SERVER['DOCUMENT_ROOT'] . "/environment_project/connections.php";

// base URL where videos are served from (web path, relative to site root)
$baseUrl = "/environment_project/dashboard/uploads/videos/";

/**
 * Build video URL by using child_name folder first (clientId_childName),
 * falling back to clientId folder if needed. Optionally logs debug info.
 *
 * @param string $baseUrl   web path base (starts with '/')
 * @param string $clientId
 * @param string $videoFile
 * @param string|null $childName
 * @param bool $debug
 * @return string           URL (relative to site root) suitable for <video src="...">
 */
function buildVideoUrl($baseUrl, $clientId, $videoFile, $childName = null, $debug = false) {
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
    $fsBase = $docRoot . rtrim($baseUrl, '/');

    $clientIdEnc = rawurlencode($clientId);
    $videoFileEnc = rawurlencode($videoFile);

    $attempted = [];

    // Prefer child folder: clientId_childName
    if (!empty($childName)) {
        // sanitize childName for filesystem usage (we don't urlencode here, we use raw value for file_exists)
        $folderChildRaw = $clientId . "_" . $childName;
        $fsChild = $fsBase . '/' . $folderChildRaw . '/' . $videoFile;
        $attempted[] = $fsChild;
        if (file_exists($fsChild) && is_readable($fsChild)) {
            if ($debug) error_log("[gallery-debug] using child path: $fsChild");
            return rtrim($baseUrl, '/') . '/' . rawurlencode($folderChildRaw) . '/' . $videoFileEnc;
        } else {
            if ($debug) error_log("[gallery-debug] child path not found or unreadable: $fsChild");
        }
    }

    // Fall back to main client folder: clientId
    $fsMain = $fsBase . '/' . $clientId . '/' . $videoFile;
    $attempted[] = $fsMain;
    if (file_exists($fsMain) && is_readable($fsMain)) {
        if ($debug) error_log("[gallery-debug] using main path: $fsMain");
        return rtrim($baseUrl, '/') . '/' . $clientIdEnc . '/' . $videoFileEnc;
    } else {
        if ($debug) error_log("[gallery-debug] main path not found or unreadable: $fsMain");
    }

    // If neither exists, return child URL if childName present (so UI will attempt it) else main URL
    if (!empty($childName)) {
        $folderChildRaw = $clientId . "_" . $childName;
        $candidate = rtrim($baseUrl, '/') . '/' . rawurlencode($folderChildRaw) . '/' . $videoFileEnc;
        if ($debug) error_log("[gallery-debug] falling back to child candidate URL (file missing): $candidate ; attempts: " . implode(" | ", $attempted));
        return $candidate;
    }

    $candidate = rtrim($baseUrl, '/') . '/' . $clientIdEnc . '/' . $videoFileEnc;
    if ($debug) error_log("[gallery-debug] falling back to main candidate URL (file missing): $candidate ; attempts: " . implode(" | ", $attempted));
    return $candidate;
}

// debug flag (use &debug=1 in the request to enable)
$debugMode = isset($_GET['debug']) && $_GET['debug'] === '1';

if (isset($_GET['loadGallery'])) {

    if (!isset($conn) || !$conn) {
        http_response_code(500);
        die("Database connection not found.");
    }

    // If client_id is passed, use it as an extra WHERE clause
    $clientFilter = !empty($_GET['client_id']) ? $_GET['client_id'] : null;

    // If JSON mode requested, handle hierarchical endpoints
    $isJson = isset($_GET['json']) && $_GET['json'] === '1';
    $level = isset($_GET['level']) ? $_GET['level'] : '';

    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');

        // Helper: add client filter to where clauses and binding arrays
        $addClientFilter = function(&$whereParts, &$types, &$values) use ($clientFilter) {
            if ($clientFilter !== null) {
                $whereParts[] = "client_id = ?";
                $types .= "s";
                $values[] = $clientFilter;
            }
        };

        // LEVEL: states => return one row per state that has Pending videos
        if ($level === 'states') {
            $whereParts = ["status = 'Pending'"];
            $types = "";
            $values = [];
            $addClientFilter($whereParts, $types, $values);
            $whereSQL = implode(" AND ", $whereParts);

            $sql = "SELECT state, COUNT(*) AS total_videos, COALESCE(SUM(view_count),0) AS total_views
                    FROM upload_video
                    WHERE $whereSQL
                    GROUP BY state
                    ORDER BY total_videos DESC";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                echo json_encode(['error' => 'prepare_error', 'details' => $conn->error]);
                $conn->close();
                exit;
            }
            if (strlen($types) > 0) {
                $bindParams = []; $bindParams[] = & $types;
                for ($i = 0; $i < count($values); $i++) $bindParams[] = & $values[$i];
                call_user_func_array([$stmt, 'bind_param'], $bindParams);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            $states = [];
            while ($row = $res->fetch_assoc()) {
                $stateName = $row['state'] ?? 'Unknown';
                $count = (int)$row['total_videos'];
                $views = (int)$row['total_views'];

                // fetch representative (earliest) video for this state, include child_name
                $rep = ['id'=>0,'url'=>'','thumb'=>'','title'=>'','upload_time'=>''];
                $repStmt = $conn->prepare("SELECT id, client_id, child_name, video_file, view_count, upload_date, city, district, state FROM upload_video WHERE state = ? AND status = 'Pending' ORDER BY upload_date ASC LIMIT 1");
                if ($repStmt !== false) {
                    $repStmt->bind_param("s", $stateName);
                    $repStmt->execute();
                    $repRes = $repStmt->get_result();
                    if ($r = $repRes->fetch_assoc()) {
                        $rep['id'] = (int)$r['id'];
                        $rep['url'] = buildVideoUrl($baseUrl, $r['client_id'], $r['video_file'], $r['child_name'] ?? null, $debugMode);
                        $rep['thumb'] = ''; // replace with thumbnail logic if available
                        $rep['title'] = $r['city'] ?: ($stateName . " video");
                        $rep['upload_time'] = $r['upload_date'] ?? '';
                        $rep['views'] = (int)($r['view_count'] ?? 0);
                        $rep['city'] = $r['city'] ?? '';
                        $rep['district'] = $r['district'] ?? '';
                        $rep['state'] = $r['state'] ?? '';
                        $rep['child_name'] = $r['child_name'] ?? '';
                    }
                    $repStmt->close();
                }

                $states[] = [
                    'state' => $stateName,
                    'total_videos' => $count,
                    'total_views' => $views,
                    'rep' => $rep
                ];
            }
            echo json_encode($states, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $stmt->close();
            $conn->close();
            exit;
        }

        // LEVEL: districts?state=... => return one row per district inside state
        if ($level === 'districts' && !empty($_GET['state'])) {
            $stateParam = $_GET['state'];
            $whereParts = ["status = 'Pending'", "state = ?"];
            $types = "s";
            $values = [$stateParam];
            $addClientFilter($whereParts, $types, $values);
            $whereSQL = implode(" AND ", $whereParts);

            $sql = "SELECT district, COUNT(*) AS total_videos, COALESCE(SUM(view_count),0) AS total_views
                    FROM upload_video
                    WHERE $whereSQL
                    GROUP BY district
                    ORDER BY total_videos DESC";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                echo json_encode(['error' => 'prepare_error', 'details' => $conn->error]);
                $conn->close();
                exit;
            }
            $bindParams = []; $bindParams[] = & $types;
            for ($i = 0; $i < count($values); $i++) $bindParams[] = & $values[$i];
            call_user_func_array([$stmt, 'bind_param'], $bindParams);
            $stmt->execute();
            $res = $stmt->get_result();
            $districts = [];
            while ($row = $res->fetch_assoc()) {
                $districtName = $row['district'] ?? 'Unknown';
                $count = (int)$row['total_videos'];
                $views = (int)$row['total_views'];
                // rep video for district (include child_name)
                $rep = ['id'=>0,'url'=>'','thumb'=>'','title'=>'','upload_time'=>''];
                $repStmt = $conn->prepare("SELECT id, client_id, child_name, video_file, view_count, upload_date, city, district, state FROM upload_video WHERE district = ? AND status = 'Pending' ORDER BY upload_date ASC LIMIT 1");
                if ($repStmt !== false) {
                    $repStmt->bind_param("s", $districtName);
                    $repStmt->execute();
                    $repRes = $repStmt->get_result();
                    if ($r = $repRes->fetch_assoc()) {
                        $rep['id'] = (int)$r['id'];
                        $rep['url'] = buildVideoUrl($baseUrl, $r['client_id'], $r['video_file'], $r['child_name'] ?? null, $debugMode);
                        $rep['thumb'] = '';
                        $rep['title'] = $r['city'] ?: ($districtName . " video");
                        $rep['upload_time'] = $r['upload_date'] ?? '';
                        $rep['views'] = (int)($r['view_count'] ?? 0);
                        $rep['city'] = $r['city'] ?? '';
                        $rep['district'] = $r['district'] ?? '';
                        $rep['state'] = $r['state'] ?? '';
                        $rep['child_name'] = $r['child_name'] ?? '';
                    }
                    $repStmt->close();
                }

                $districts[] = [
                    'district' => $districtName,
                    'total_videos' => $count,
                    'total_views' => $views,
                    'rep' => $rep
                ];
            }
            echo json_encode($districts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $stmt->close();
            $conn->close();
            exit;
        }

        // LEVEL: cities?state=...&district=... => return one row per city inside district
        if ($level === 'cities' && !empty($_GET['state']) && !empty($_GET['district'])) {
            $stateParam = $_GET['state'];
            $districtParam = $_GET['district'];
            $whereParts = ["status = 'Pending'", "state = ?", "district = ?"];
            $types = "ss";
            $values = [$stateParam, $districtParam];
            $addClientFilter($whereParts, $types, $values);
            $whereSQL = implode(" AND ", $whereParts);

            $sql = "SELECT city, COUNT(*) AS total_videos, COALESCE(SUM(view_count),0) AS total_views
                    FROM upload_video
                    WHERE $whereSQL
                    GROUP BY city
                    ORDER BY total_videos DESC";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                echo json_encode(['error' => 'prepare_error', 'details' => $conn->error]);
                $conn->close();
                exit;
            }
            $bindParams = []; $bindParams[] = & $types;
            for ($i = 0; $i < count($values); $i++) $bindParams[] = & $values[$i];
            call_user_func_array([$stmt, 'bind_param'], $bindParams);
            $stmt->execute();
            $res = $stmt->get_result();
            $cities = [];
            while ($row = $res->fetch_assoc()) {
                $cityName = $row['city'] ?? 'Unknown';
                $count = (int)$row['total_videos'];
                $views = (int)$row['total_views'];

                // rep video for city (include child_name)
                $rep = ['id'=>0,'url'=>'','thumb'=>'','title'=>'','upload_time'=>''];
                $repStmt = $conn->prepare("SELECT id, client_id, child_name, video_file, view_count, upload_date, city, district, state FROM upload_video WHERE city = ? AND status = 'Pending' ORDER BY upload_date ASC LIMIT 1");
                if ($repStmt !== false) {
                    $repStmt->bind_param("s", $cityName);
                    $repStmt->execute();
                    $repRes = $repStmt->get_result();
                    if ($r = $repRes->fetch_assoc()) {
                        $rep['id'] = (int)$r['id'];
                        $rep['url'] = buildVideoUrl($baseUrl, $r['client_id'], $r['video_file'], $r['child_name'] ?? null, $debugMode);
                        $rep['thumb'] = '';
                        $rep['title'] = $r['city'] ?: ($cityName . " video");
                        $rep['upload_time'] = $r['upload_date'] ?? '';
                        $rep['views'] = (int)($r['view_count'] ?? 0);
                        $rep['city'] = $r['city'] ?? '';
                        $rep['district'] = $r['district'] ?? '';
                        $rep['state'] = $r['state'] ?? '';
                        $rep['child_name'] = $r['child_name'] ?? '';
                    }
                    $repStmt->close();
                }

                $cities[] = [
                    'city' => $cityName,
                    'total_videos' => $count,
                    'total_views' => $views,
                    'rep' => $rep
                ];
            }
            echo json_encode($cities, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $stmt->close();
            $conn->close();
            exit;
        }

        // LEVEL: videos?state=...&district=...&city=... => return flat video list matching filters
        if ($level === 'videos') {
            // build where clauses based on available filters
            $whereParts = ["status = 'Pending'"];
            $types = "";
            $values = [];
            if (!empty($_GET['state'])) { $whereParts[] = "state = ?"; $types .= "s"; $values[] = $_GET['state']; }
            if (!empty($_GET['district'])) { $whereParts[] = "district = ?"; $types .= "s"; $values[] = $_GET['district']; }
            if (!empty($_GET['city'])) { $whereParts[] = "city = ?"; $types .= "s"; $values[] = $_GET['city']; }
            $addClientFilter($whereParts, $types, $values);
            $whereSQL = implode(" AND ", $whereParts);

            $limit = 1000;
            $sql = "SELECT id, client_id, child_name, video_file, city, district, state, view_count, upload_date
                    FROM upload_video
                    WHERE $whereSQL
                    ORDER BY upload_date DESC
                    LIMIT {$limit}";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                echo json_encode(['error' => 'prepare_error', 'details' => $conn->error]);
                $conn->close();
                exit;
            }
            if (strlen($types) > 0) {
                $bindParams = []; $bindParams[] = & $types;
                for ($i = 0; $i < count($values); $i++) $bindParams[] = & $values[$i];
                call_user_func_array([$stmt, 'bind_param'], $bindParams);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            $videos = [];
            while ($r = $res->fetch_assoc()) {
                $videos[] = [
                    'id' => (int)$r['id'],
                    'url' => buildVideoUrl($baseUrl, $r['client_id'], $r['video_file'], $r['child_name'] ?? null, $debugMode),
                    'thumb' => '',
                    'title' => $r['city'] ?: ($r['district'] ?: $r['state'] ?: 'Video'),
                    'views' => (int)($r['view_count'] ?? 0),
                    'state' => $r['state'] ?? '',
                    'district' => $r['district'] ?? '',
                    'city' => $r['city'] ?? '',
                    'upload_time' => $r['upload_date'] ?? '',
                    'child_name' => $r['child_name'] ?? ''
                ];
            }
            echo json_encode($videos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $stmt->close();
            $conn->close();
            exit;
        }

        // unknown level
        echo json_encode(['error' => 'invalid_level', 'message' => 'level must be one of states|districts|cities|videos']);
        $conn->close();
        exit;
    } // end isJson

    // If not JSON: return HTML grouped view (backwards-compatible)
    header("Content-Type: text/html; charset=UTF-8");

    $showAll = isset($_GET['all']) && $_GET['all'] === 'true';
    $hasStateFilter = !empty($_GET['state']);
    $hasDistrictFilter = !empty($_GET['district']);
    $hasCityFilter = !empty($_GET['city']);

    if ($showAll && !$hasStateFilter && !$hasDistrictFilter && !$hasCityFilter) {
        $sql = "SELECT id, client_id, child_name, video_file, city, view_count
                FROM upload_video
                WHERE status = 'Pending'
                ORDER BY upload_date DESC";
        $res = $conn->query($sql);
        if (!$res) {
            die('Query Error: ' . $conn->error);
        }
        if ($res->num_rows === 0) {
            echo "<p class='no-photos'>No videos uploaded yet.</p>";
            $conn->close();
            exit;
        }

        while ($row = $res->fetch_assoc()) {
            $videoId = (int)$row['id'];
            $clientId = $row['client_id'];
            $childName = $row['child_name'] ?? null;
            $videoFile = $row['video_file'];
            $city = htmlspecialchars($row['city'] ?? '', ENT_QUOTES, 'UTF-8');
            $views = (int)($row['view_count'] ?? 0);

            $videoUrl = buildVideoUrl($baseUrl, $clientId, $videoFile, $childName, $debugMode);
            $onclick = $city ? "onclick=\"openCityGallery('".str_replace("'", "\\'", $city)."')\"" : "";

            echo "
<div class='video-card' {$onclick}>
  <video 
    src='{$videoUrl}' 
    muted 
    preload='metadata' 
    data-video-id='{$videoId}'>
    <source src='{$videoUrl}' type='video/mp4'>
  </video>
  <div class='video-info'>
    <div class='video-info-left'>
      <h3>" . ($city ?: "Unknown location") . "</h3>
      <p>1 video</p>
    </div>
    <div class='video-info-right'>
      <p>{$views} " . ($views == 1 ? "view" : "views") . "</p>
    </div>
  </div>
</div>";
        }

        $conn->close();
        exit;
    }

    // grouped-by-city HTML (default for compatibility)
    $whereParts = ["status = 'Pending'"];
    if ($hasStateFilter) $whereParts[] = "state = '" . $conn->real_escape_string($_GET['state']) . "'";
    if ($hasDistrictFilter) $whereParts[] = "district = '" . $conn->real_escape_string($_GET['district']) . "'";
    if ($hasCityFilter) $whereParts[] = "city = '" . $conn->real_escape_string($_GET['city']) . "'";
    if (!empty($_GET['client_id'])) $whereParts[] = "client_id = '" . $conn->real_escape_string($_GET['client_id']) . "'";
    $whereSQL = implode(" AND ", $whereParts);

    $limitSQL = "LIMIT 6";
    $sql = "SELECT city, COUNT(*) AS total_videos, COALESCE(SUM(view_count), 0) AS total_views, MIN(upload_date) AS first_upload_time 
            FROM upload_video 
            WHERE $whereSQL
            GROUP BY city 
            ORDER BY total_videos DESC
            $limitSQL";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        // fallback: per-video listing
        $fallbackSql = "SELECT id, client_id, child_name, video_file, city, view_count
                        FROM upload_video
                        WHERE status = 'Pending'
                        ORDER BY upload_date DESC
                        LIMIT 6";
        $res = $conn->query($fallbackSql);
        if (!$res) { die('Query Error (fallback): ' . $conn->error); }
        if ($res->num_rows === 0) {
            echo "<p class='no-photos'>No videos uploaded yet.</p>";
            $conn->close();
            exit;
        }
        while ($row = $res->fetch_assoc()) {
            $videoId = (int)$row['id'];
            $clientId = $row['client_id'];
            $childName = $row['child_name'] ?? null;
            $videoFile = $row['video_file'];
            $city = htmlspecialchars($row['city'] ?? '', ENT_QUOTES, 'UTF-8');
            $views = (int)($row['view_count'] ?? 0);

            $videoUrl = buildVideoUrl($baseUrl, $clientId, $videoFile, $childName, $debugMode);
            $onclick = $city ? "onclick=\"openCityGallery('".str_replace("'", "\\'", $city)."')\"" : "";

            echo "
<div class='video-card' {$onclick}>
  <video 
    src='{$videoUrl}' 
    muted 
    preload='metadata' 
    data-video-id='{$videoId}'>
    <source src='{$videoUrl}' type='video/mp4'>
  </video>
  <div class='video-info'>
    <div class='video-info-left'>
      <h3>" . ($city ?: "Unknown location") . "</h3>
      <p>1 video</p>
    </div>
    <div class='video-info-right'>
      <p>{$views} " . ($views == 1 ? "view" : "views") . "</p>
    </div>
  </div>
</div>";
        }
        $conn->close();
        exit;
    }

    if (!$stmt->execute()) {
        die('Execute Error: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result === false) {
        die('Query Error: ' . $conn->error);
    }

    if ($result->num_rows === 0) {
        echo "<p class='no-photos'>No videos uploaded yet.</p>";
        $stmt->close();
        $conn->close();
        exit;
    }

    while ($row = $result->fetch_assoc()) {
        $city_raw = $row['city'];
        $city = htmlspecialchars($city_raw, ENT_QUOTES, 'UTF-8');
        $count = (int)$row['total_videos'];
        $views = (int)$row['total_views'];

        // Get earliest video for the city (include child_name)
        $thumbQuery = $conn->prepare("
            SELECT id, client_id, child_name, video_file 
            FROM upload_video 
            WHERE city = ? AND status = 'Pending'
            ORDER BY upload_date ASC 
            LIMIT 1
        ");
        if ($thumbQuery !== false) {
            $thumbQuery->bind_param("s", $city_raw);
            $thumbQuery->execute();
            $thumbResult = $thumbQuery->get_result();
            $thumbRow = $thumbResult->fetch_assoc() ?? [];

            $videoId = (int)($thumbRow['id'] ?? 0);
            $clientId = $thumbRow['client_id'] ?? "";
            $childName = $thumbRow['child_name'] ?? null;
            $videoFile = $thumbRow['video_file'] ?? "";
            $thumbQuery->close();
        } else {
            $videoId = 0; $clientId = ""; $childName = null; $videoFile = "";
        }

        $videoUrl = buildVideoUrl($baseUrl, $clientId, $videoFile, $childName, $debugMode);
        $onclickCity = str_replace("'", "\'", $city);

        echo "
<div class='video-card' onclick=\"openCityGallery('{$onclickCity}')\">
  <video 
    src='{$videoUrl}' 
    muted 
    preload='metadata' 
    data-video-id='{$videoId}'>
    <source src='{$videoUrl}' type='video/mp4'>
  </video>
  <div class='video-info'>
    <div class='video-info-left'>
      <h3>{$city}</h3>
      <p>{$count} videos</p>
    </div>
    <div class='video-info-right'>
      <p>{$views} " . ($views == 1 ? "view" : "views") . "</p>
    </div>
  </div>
</div>";
    }

    $stmt->close();
    $conn->close();
    exit;
}
?>
