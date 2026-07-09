<?php
require_once '../app/includes/init.php';

if (!isset($_GET['country'])) {
    die('Country code required');
}

$country_code = $_GET['country'];
$db = new Database();


$visa_types = $db->fetchAll(
    "SELECT * FROM visa_types WHERE country_code = ? ORDER BY name",
    [$country_code]
);


$country = $db->fetchOne(
    "SELECT * FROM countries WHERE iso2 = ?",
    [$country_code]
);

if ($country) {
    echo "<h5>Student Visas for " . htmlspecialchars($country['name']) . "</h5>";
    
    if (!empty($visa_types)) {
        echo "<ul>";
        foreach($visa_types as $visa) {
            echo "<li><strong>" . htmlspecialchars($visa['name']) . ":</strong> ";
            if ($visa['processing_time_days']) {
                echo "Processing: " . $visa['processing_time_days'] . " days";
            }
            echo "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No specific visa information available. Check the embassy website.</p>";
    }
    
    
    $embassies = $db->fetchAll(
        "SELECT * FROM embassy_consulate_records WHERE country_code = ? ORDER BY city",
        [$country_code]
    );
    
    if (!empty($embassies)) {
        echo "<h6>Embassies/Consulates:</h6>";
        echo "<ul>";
        foreach($embassies as $embassy) {
            echo "<li>";
            echo "<strong>" . htmlspecialchars($embassy['name']) . "</strong><br>";
            echo htmlspecialchars($embassy['city']) . "<br>";
            if ($embassy['website']) {
                echo "<a href='" . htmlspecialchars($embassy['website']) . "' target='_blank'>Website</a>";
            }
            echo "</li>";
        }
        echo "</ul>";
    }
} else {
    echo "<p>Country information not found.</p>";
}
?>