<?php
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

class Helpers {
    
    
    public static function formatDate($date, $format = 'F j, Y') {
        if (!$date) return '';
        return date($format, strtotime($date));
    }
    
    
    public static function redirect($url, $message = null, $type = 'success') {
        if ($message) {
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $type;
        }
        header("Location: $url");
        exit();
    }
    
    
    public static function getFlash() {
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            $type = $_SESSION['flash_type'];
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_type']);
            return ['message' => $message, 'type' => $type];
        }
        return null;
    }
    
    
    public static function sanitize($input) {
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $input[$key] = self::sanitize($value);
            }
            return $input;
        }
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    
    public static function getCountries() {
        $db = new Database();
        return $db->fetchAll("SELECT iso2, name FROM countries ORDER BY name");
    }
    
    
    public static function getDegreeLevels() {
        $db = new Database();
        return $db->fetchAll("SELECT * FROM degree_levels ORDER BY name");
    }
    
    
    public static function getTestTypes() {
        $db = new Database();
        return $db->fetchAll("SELECT * FROM test_types ORDER BY name");
    }
    
    
    public static function getUniversities() {
        $db = new Database();
        return $db->fetchAll("SELECT * FROM universities WHERE deleted_at IS NULL ORDER BY name");
    }
    
    
    public static function getPrograms() {
        $db = new Database();
        return $db->fetchAll("
            SELECT p.*, u.name as university_name 
            FROM programs p 
            JOIN universities u ON p.university_id = u.university_id 
            WHERE p.deleted_at IS NULL 
            ORDER BY p.program_name
        ");
    }
    
    
    public static function getUserApplications($student_id) {
        $db = new Database();
        return $db->fetchAll("
            SELECT a.*, p.program_name, u.name as university_name, s.name as status_name
            FROM applications a
            JOIN programs p ON a.program_id = p.program_id
            JOIN universities u ON a.university_id = u.university_id
            JOIN application_statuses s ON a.application_status_id = s.application_status_id
            WHERE a.student_id = ? AND a.deleted_at IS NULL
            ORDER BY a.applied_at DESC
        ", [$student_id]);
    }
    public static function getApplicationFee($program_id) {
    $db = new Database();
    return $db->fetchOne(
        "SELECT * FROM application_fees WHERE program_id = ?",
        [$program_id]
    );
}

public static function isFeePaid($application_id) {
    $db = new Database();
    $payment = $db->fetchOne(
        "SELECT * FROM fee_payments WHERE application_id = ? AND status = 'completed'",
        [$application_id]
    );
    return !empty($payment);
}

public static function getApplicationPayment($application_id) {
    $db = new Database();
    return $db->fetchOne(
        "SELECT * FROM fee_payments WHERE application_id = ? ORDER BY paid_at DESC LIMIT 1",
        [$application_id]
    );
}

public static function generatePDF($html, $filename, $paperSize = 'A4', $orientation = 'portrait') {
    $options = new Options();
    $options->set('isRemoteEnabled', true); // enable remote images
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper($paperSize, $orientation);
    $dompdf->render();
    $dompdf->stream($filename, ['Attachment' => 1]); // download
    exit;
}

public static function generateICS($session) {
    $dtstart = date('Ymd\THis\Z', strtotime($session['scheduled_at']));
    $dtend = date('Ymd\THis\Z', strtotime($session['scheduled_at'] . ' + ' . ($session['duration_minutes'] ?? 60) . ' minutes'));

    $ics = "BEGIN:VCALENDAR\r\n";
    $ics .= "VERSION:2.0\r\n";
    $ics .= "PRODID:-//StudySphere//EN\r\n";
    $ics .= "CALSCALE:GREGORIAN\r\n";
    $ics .= "METHOD:PUBLISH\r\n";
    $ics .= "BEGIN:VEVENT\r\n";
    $ics .= "UID:" . uniqid() . "@studysphere.local\r\n";
    $ics .= "DTSTART:$dtstart\r\n";
    $ics .= "DTEND:$dtend\r\n";
    $ics .= "SUMMARY:" . addcslashes($session['title'], ",\n\r") . "\r\n";
    if (!empty($session['description'])) {
        $ics .= "DESCRIPTION:" . addcslashes($session['description'], ",\n\r") . "\r\n";
    }
    if (!empty($session['location'])) {
        $ics .= "LOCATION:" . addcslashes($session['location'], ",\n\r") . "\r\n";
    }
    $ics .= "END:VEVENT\r\n";
    $ics .= "END:VCALENDAR\r\n";
    return $ics;
}

public static function awardMilestone($student_id, $milestone_code) {
    $db = new Database();
    // Find milestone definition
    $def = $db->fetchOne("SELECT milestone_id FROM milestone_definitions WHERE code = ?", [$milestone_code]);
    if (!$def) return false;
    
    // Check if already earned
    $earned = $db->fetchOne("
        SELECT * FROM student_milestones 
        WHERE student_id = ? AND milestone_def_id = ?
    ", [$student_id, $def['milestone_id']]);
    
    if (!$earned) {
        $db->insert('student_milestones', [
            'student_id' => $student_id,
            'milestone_def_id' => $def['milestone_id'],
            'milestone_type' => $milestone_code,
            'achieved_at' => date('Y-m-d H:i:s')
        ]);
        return true;
    }
    return false;
}
}
?>