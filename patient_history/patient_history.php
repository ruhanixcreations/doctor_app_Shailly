<?php
/**
 * patient_history.php
 * Fresh backend for the revamped patient history workspace.
 *
 * Supported actions:
 *   - action=list_patients  [&q=search&limit=200]
 *   - action=detail         &patient_id=XYZ
 */

header('Content-Type: application/json; charset=utf-8');

$DB_HOST = 'localhost';
$DB_USER = 'ruhanixl_doctorApp';
$DB_PASS = '@aashi12345678@';
$DB_NAME = 'ruhanixl_doctorApp';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$action = $_GET['action'] ?? 'list_patients';

switch ($action) {
    case 'list_patients':
        handle_list_patients($mysqli);
        break;
    case 'detail':
        handle_patient_detail($mysqli);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action supplied']);
        $mysqli->close();
        exit;
}

/**
 * Fetch paginated / searchable patient cards with quick stats.
 */
function handle_list_patients(mysqli $mysqli): void {
    $limit = isset($_GET['limit']) ? max(10, min(500, (int)$_GET['limit'])) : 200;
    $search = trim($_GET['q'] ?? '');

    $baseSql = "
        SELECT
            p.id,
            p.client_id,
            p.patient_id,
            p.patient_name,
            p.mobile,
            p.age,
            p.weight,
            COALESCE(ap.total_appointments, 0)            AS total_appointments,
            COALESCE(ap.upcoming_appointments, 0)        AS upcoming_appointments,
            COALESCE(ap.completed_appointments, 0)       AS completed_appointments,
            ap.last_appointment_date,
            ap.next_appointment_date,
            COALESCE(pr.total_prescriptions, 0)          AS total_prescriptions,
            pr.last_prescription_date
        FROM patient_list p
        LEFT JOIN (
            SELECT
                patient_id,
                COUNT(*) AS total_appointments,
                SUM(
                    CASE
                        WHEN date > CURDATE() THEN 1
                        WHEN date = CURDATE() AND (time IS NULL OR time >= CURTIME()) THEN 1
                        ELSE 0
                    END
                ) AS upcoming_appointments,
                SUM(
                    CASE
                        WHEN date < CURDATE() THEN 1
                        WHEN date = CURDATE() AND time < CURTIME() THEN 1
                        ELSE 0
                    END
                ) AS completed_appointments,
                MAX(date) AS last_appointment_date,
                MIN(
                    CASE
                        WHEN date > CURDATE() THEN date
                        WHEN date = CURDATE() AND (time IS NULL OR time >= CURTIME()) THEN date
                        ELSE NULL
                    END
                ) AS next_appointment_date
            FROM appointments
            GROUP BY patient_id
        ) ap ON ap.patient_id = p.patient_id
        LEFT JOIN (
            SELECT
                patient_id,
                COUNT(*) AS total_prescriptions,
                MAX(created_at) AS last_prescription_date
            FROM prescriptions
            GROUP BY patient_id
        ) pr ON pr.patient_id = p.patient_id
    ";

    $orderClause = " ORDER BY COALESCE(ap.next_appointment_date, ap.last_appointment_date, pr.last_prescription_date) DESC, p.id DESC LIMIT ?";

    if ($search !== '') {
        $sql = $baseSql . " WHERE (p.patient_name LIKE ? OR p.patient_id LIKE ? OR p.mobile LIKE ?)" . $orderClause;
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $mysqli->error]);
            exit;
        }
        $like = '%' . $search . '%';
        $stmt->bind_param('sssi', $like, $like, $like, $limit);
    } else {
        $sql = $baseSql . $orderClause;
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $mysqli->error]);
            exit;
        }
        $stmt->bind_param('i', $limit);
    }

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Query failed: ' . $stmt->error]);
        $stmt->close();
        exit;
    }

    $result = $stmt->get_result();
    $patients = [];
    while ($row = $result->fetch_assoc()) {
        $patients[] = [
            'patient_id'             => $row['patient_id'],
            'client_id'              => $row['client_id'],
            'patient_name'           => $row['patient_name'],
            'mobile'                 => $row['mobile'],
            'age'                    => $row['age'],
            'weight'                 => $row['weight'],
            'total_appointments'     => (int)$row['total_appointments'],
            'upcoming_appointments'  => (int)$row['upcoming_appointments'],
            'completed_appointments' => (int)$row['completed_appointments'],
            'total_prescriptions'    => (int)$row['total_prescriptions'],
            'last_appointment_date'  => $row['last_appointment_date'],
            'next_appointment_date'  => $row['next_appointment_date'],
            'last_prescription_date' => $row['last_prescription_date']
        ];
    }
    $stmt->close();

    echo json_encode(['success' => true, 'patients' => $patients], JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Fetch a single patient's full history snapshot.
 */
function handle_patient_detail(mysqli $mysqli): void {
    $patientId = trim($_GET['patient_id'] ?? '');
    if ($patientId === '') {
        echo json_encode(['success' => false, 'message' => 'patient_id is required']);
        exit;
    }

    $stmt = $mysqli->prepare("SELECT patient_id, patient_name, mobile, age, weight, client_id, doctor, report_file FROM patient_list WHERE patient_id = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $mysqli->error]);
        exit;
    }
    $stmt->bind_param('s', $patientId);
    $stmt->execute();
    $patientRes = $stmt->get_result();
    $patient = $patientRes->fetch_assoc();
    $stmt->close();

    if (!$patient) {
        echo json_encode(['success' => false, 'message' => 'Patient not found']);
        exit;
    }

    $appointments = fetch_patient_appointments($mysqli, $patientId);
    $prescriptions = fetch_patient_prescriptions($mysqli, $patientId);
    $reports = fetch_patient_reports($mysqli, $patientId);

    $stats = calculate_stats($appointments, $prescriptions, $reports);

    echo json_encode([
        'success'       => true,
        'patient'       => $patient,
        'stats'         => $stats,
        'appointments'  => $appointments,
        'prescriptions' => $prescriptions,
        'reports'       => $reports
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Build appointment list enriched with status + readable labels.
 */
function fetch_patient_appointments(mysqli $mysqli, string $patientId): array {
    $stmt = $mysqli->prepare("SELECT id, client_id, doctor, date, time, notes, created_at FROM appointments WHERE patient_id = ? ORDER BY date DESC, time DESC, id DESC");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $patientId);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $res = $stmt->get_result();
    $appointments = [];
    $today = date('Y-m-d');
    $currentMinutes = (int)date('H') * 60 + (int)date('i');

    while ($row = $res->fetch_assoc()) {
        $dateStr = $row['date'] ?? '';
        $timeStr = $row['time'] ?? '';
        $status = 'upcoming';

        if ($dateStr !== '') {
            if ($dateStr < $today) {
                $status = 'completed';
            } elseif ($dateStr === $today) {
                $minutes = parse_time_minutes($timeStr);
                if ($minutes !== null && $minutes < $currentMinutes) {
                    $status = 'completed';
                }
            }
        }

        $timestamp = null;
        if (!empty($dateStr)) {
            $combined = $dateStr . ' ' . ($timeStr ?: '00:00:00');
            $timestamp = strtotime($combined) ?: null;
        }

        $appointments[] = [
            'id'           => (int)$row['id'],
            'client_id'    => $row['client_id'],
            'doctor'       => $row['doctor'],
            'date'         => $dateStr,
            'time'         => $timeStr,
            'display_date' => friendly_date($dateStr),
            'display_time' => friendly_time($timeStr),
            'notes'        => $row['notes'],
            'created_at'   => $row['created_at'],
            'status'       => $status,
            'timestamp'    => $timestamp
        ];
    }
    $stmt->close();
    return $appointments;
}

/**
 * Summarise prescription rows for quick browsing.
 */
function fetch_patient_prescriptions(mysqli $mysqli, string $patientId): array {
    $sql = "
        SELECT
            p.id,
            p.created_at,
            COALESCE(bt.name, '') AS blood_test_name,
            COUNT(pi.id) AS medicine_count,
            MAX(pi.symptoms) AS symptoms,
            MIN(pi.follow_up_date) AS follow_up_date,
            GROUP_CONCAT(DISTINCT pi.medicine_name ORDER BY pi.id SEPARATOR ', ') AS medicines
        FROM prescriptions p
        LEFT JOIN prescription_items pi ON pi.prescription_id = p.id
        LEFT JOIN blood_tests bt ON bt.id = p.blood_test_id
        WHERE p.patient_id = ?
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT 200
    ";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $patientId);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'id'                => (int)$row['id'],
            'created_at'        => $row['created_at'],
            'display_date'      => friendly_datetime($row['created_at']),
            'blood_test_name'   => $row['blood_test_name'],
            'medicine_count'    => (int)$row['medicine_count'],
            'medicines'         => truncate_csv($row['medicines']),
            'symptoms'          => $row['symptoms'],
            'follow_up_date'    => $row['follow_up_date'],
            'follow_up_display' => friendly_date($row['follow_up_date']),
            'print_url'         => 'add_prescription/print.html?prescription_id=' . urlencode($row['id']) . '&patient_id=' . urlencode($patientId)
        ];
    }
    $stmt->close();
    return $items;
}

/**
 * Retrieve uploaded reports for the patient.
 */
function fetch_patient_reports(mysqli $mysqli, string $patientId): array {
    $stmt = $mysqli->prepare("SELECT id, file_name, created_at FROM patient_reports WHERE patient_id = ? ORDER BY id DESC");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $patientId);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }
    $res = $stmt->get_result();
    $basePath = "add_new_patient/uploads/";
    $reports = [];
    while ($row = $res->fetch_assoc()) {
        $reports[] = [
            'id'          => (int)$row['id'],
            'file_name'   => $row['file_name'],
            'file_path'   => $basePath . $row['file_name'],
            'uploaded_at' => $row['created_at'],
            'uploaded_on' => friendly_datetime($row['created_at'])
        ];
    }
    $stmt->close();
    return $reports;
}

/**
 * Build stats summary for the UI header.
 */
function calculate_stats(array $appointments, array $prescriptions, array $reports): array {
    $upcoming = 0;
    $completed = 0;
    $nextUpcoming = null;
    $nextUpcomingTs = null;
    $latestCompleted = null;
    $latestCompletedTs = null;

    foreach ($appointments as $appt) {
        $ts = $appt['timestamp'] ?? null;
        if ($appt['status'] === 'upcoming') {
            $upcoming++;
            if ($ts !== null && ($nextUpcomingTs === null || $ts < $nextUpcomingTs)) {
                $nextUpcomingTs = $ts;
                $nextUpcoming = $appt;
            }
        } else {
            $completed++;
            if ($ts !== null && ($latestCompletedTs === null || $ts > $latestCompletedTs)) {
                $latestCompletedTs = $ts;
                $latestCompleted = $appt;
            }
        }
    }

    $lastPrescription = $prescriptions[0]['display_date'] ?? null;

    return [
        'total_appointments'     => $upcoming + $completed,
        'upcoming_appointments'  => $upcoming,
        'completed_appointments' => $completed,
        'next_appointment'       => $nextUpcoming ? trim(($nextUpcoming['display_date'] ?? '') . ' ' . ($nextUpcoming['display_time'] ?? '')) : null,
        'last_appointment'       => $latestCompleted ? trim(($latestCompleted['display_date'] ?? '') . ' ' . ($latestCompleted['display_time'] ?? '')) : null,
        'total_prescriptions'    => count($prescriptions),
        'last_prescription'      => $lastPrescription,
        'reports_count'          => count($reports)
    ];
}

/**
 * Helpers
 */
function friendly_date(?string $dateStr): ?string {
    if (!$dateStr) return null;
    $ts = strtotime($dateStr);
    if (!$ts) return $dateStr;
    return date('d M Y', $ts);
}

function friendly_time(?string $timeStr): ?string {
    if (!$timeStr) return null;
    $ts = strtotime($timeStr);
    if (!$ts) return $timeStr;
    return date('h:i A', $ts);
}

function friendly_datetime(?string $dateTime): ?string {
    if (!$dateTime) return null;
    $ts = strtotime($dateTime);
    if (!$ts) return $dateTime;
    return date('d M Y - h:i A', $ts);
}

function truncate_csv(?string $csv, int $maxParts = 4): ?string {
    if (!$csv) return null;
    $parts = array_filter(array_map('trim', explode(',', $csv)));
    if (!$parts) return null;
    if (count($parts) <= $maxParts) {
        return implode(', ', $parts);
    }
    $visible = array_slice($parts, 0, $maxParts);
    $remaining = count($parts) - $maxParts;
    return implode(', ', $visible) . " +{$remaining} more";
}

function parse_time_minutes(?string $timeStr): ?int {
    if (!$timeStr) return null;
    $trimmed = trim($timeStr);
    if ($trimmed === '') return null;
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s?(AM|PM)$/i', $trimmed, $m)) {
        $hours = (int)$m[1];
        $minutes = (int)$m[2];
        $meridian = strtoupper($m[4]);
        if ($hours === 12) $hours = 0;
        if ($meridian === 'PM') $hours += 12;
        return $hours * 60 + $minutes;
    }
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $trimmed, $m)) {
        $hours = (int)$m[1];
        $minutes = (int)$m[2];
        return $hours * 60 + $minutes;
    }
    return null;
}

?>
