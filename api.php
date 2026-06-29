<?php
/**
* MangroveSense — api.php
* Updated for InfinityFree Hosting
* Place this file in your htdocs folder on InfinityFree
*
* Endpoints
*   POST  action=save_tree   — insert or update a tree record
*   GET   action=get_trees   — return recent records as JSON
*   POST  action=delete_tree — delete one record by tree_code (for demo clear)
*   GET   action=get_analytics — return aggregated data for charts
*/
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

// ── Database connection ──────────────────────────────────────
// InfinityFree Database Credentials
$db_host = 'sql203.infinityfree.com';
$db_name = 'if0_42252134_mangrove';
$db_user = 'if0_42252134';
$db_pass = 'DkrBvvDgBn';  // ⚠️ REPLACE with your actual password from cPanel

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user, $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]);
    exit;
}

// ── Route ───────────────────────────────────────────────────
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
switch ($action) {

    // ── SAVE TREE ────────────────────────────────────────────
    case 'save_tree':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { bad('POST required'); }
        $code    = trim($_POST['tree_code']   ?? '');
        $species = trim($_POST['species_id']  ?? '');
        $circ    = num($_POST['trunk_circumference_cm'] ?? null);
        $height  = num($_POST['tree_height_m']          ?? null);
        $dbh     = num($_POST['dbh_cm']                 ?? null);
        $lat     = num($_POST['lat']                    ?? null);
        $lng     = num($_POST['lng']                    ?? null);
        $zone    = trim($_POST['zone_label']            ?? '');
        $health  = in_array($_POST['health_status'] ?? '', ['Healthy','Monitor','Critical'])
                   ? $_POST['health_status'] : 'Healthy';
        $notes   = trim($_POST['notes'] ?? '');

        if (!$code || !$species) { bad('tree_code and species_id are required'); }

        // Auto-add new species if not already in species table
        $pdo->prepare('INSERT IGNORE INTO species (species_id) VALUES (?)')
            ->execute([strtoupper($species)]);

        // Handle base64 photo
        $photoFilename = null;
        $photoData = $_POST['photo_data'] ?? '';
        if ($photoData) {
            // InfinityFree: Use absolute path to htdocs/uploads
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
            // Strip the data: URI prefix
            $imgData = preg_replace('/^data:image\/\w+;base64,/', '', $photoData);
            $imgBytes = base64_decode($imgData);
            if ($imgBytes !== false) {
                // Detect extension from MIME
                $mime = substr($photoData, 5, strpos($photoData, ';') - 5);
                $ext  = ($mime === 'image/png') ? 'png' : 'jpg';
                $safeCode = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $code);
                $photoFilename = $safeCode . '_' . date('Ymd_His') . '.' . $ext;
                file_put_contents($uploadDir . $photoFilename, $imgBytes);
            }
        }

        $sql = '
            INSERT INTO trees
            (tree_code, species_id, trunk_circumference_cm, tree_height_m, dbh_cm,
             lat, lng, zone_label, health_status, notes, photo_filename, data_source)
            VALUES
            (:code, :species, :circ, :height, :dbh,
             :lat, :lng, :zone, :health, :notes, :photo, \'manual\')
            ON DUPLICATE KEY UPDATE
                species_id             = VALUES(species_id),
                trunk_circumference_cm = VALUES(trunk_circumference_cm),
                tree_height_m          = VALUES(tree_height_m),
                dbh_cm                 = VALUES(dbh_cm),
                lat                    = VALUES(lat),
                lng                    = VALUES(lng),
                zone_label             = VALUES(zone_label),
                health_status          = VALUES(health_status),
                notes                  = VALUES(notes),
                photo_filename         = COALESCE(VALUES(photo_filename), photo_filename),
                data_source            = \'manual\',
                updated_at             = CURRENT_TIMESTAMP
        ';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':code'    => $code,
                ':species' => strtoupper($species),
                ':circ'    => $circ,
                ':height'  => $height,
                ':dbh'     => $dbh,
                ':lat'     => $lat,
                ':lng'     => $lng,
                ':zone'    => $zone ?: null,
                ':health'  => $health,
                ':notes'   => $notes ?: null,
                ':photo'   => $photoFilename,
            ]);
            $photoUrl = $photoFilename ? 'uploads/' . $photoFilename : null;
            echo json_encode([
                'success'       => true,
                'tree_code'     => $code,
                'photo_url'     => $photoUrl,
                'rows_affected' => $stmt->rowCount(),
            ]);
        } catch (PDOException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    // ── GET TREES (recent list / map markers) ────────────────
    case 'get_trees':
        $limit  = max(1, min(1000, intval($_GET['limit'] ?? 20)));
        $source = $_GET['source'] ?? '';   // '' = all, 'manual' = form only
        $where  = $source === 'manual' ? "WHERE data_source = 'manual'" : '';
        $stmt   = $pdo->query(
            "SELECT tree_code, species_id, dbh_cm, tree_height_m, trunk_circumference_cm,
                    health_status, lat, lng, zone_label, notes, photo_filename,
                    data_source, updated_at
             FROM trees $where
             ORDER BY updated_at DESC
             LIMIT $limit"
        );
        echo json_encode($stmt->fetchAll());
        break;

    // ── LIST TREES (paginated + searchable, for the Tree Inventory page) ──
    case 'list_trees':
        $page     = max(1, intval($_GET['page'] ?? 1));
        $pageSize = max(1, min(5000, intval($_GET['page_size'] ?? 25)));
        $offset   = ($page - 1) * $pageSize;
        $search   = trim($_GET['search'] ?? '');
        $species  = trim($_GET['species'] ?? '');
        $health   = trim($_GET['health'] ?? '');
        $source   = trim($_GET['source'] ?? '');

        $conditions = [];
        $params     = [];
        if ($search !== '') {
            $conditions[] = '(tree_code LIKE :search OR zone_label LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        if ($species !== '') {
            $conditions[] = 'species_id = :species';
            $params[':species'] = $species;
        }
        if ($health !== '') {
            $conditions[] = 'health_status = :health';
            $params[':health'] = $health;
        }
        if ($source !== '') {
            $conditions[] = 'data_source = :source';
            $params[':source'] = $source;
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Total count for pagination
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM trees $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Page of results
        $sql = "SELECT id, tree_code, species_id, trunk_circumference_cm, tree_height_m, dbh_cm,
                       pos_x, pos_y, lat, lng, zone_label, health_status, notes, photo_filename,
                       data_source, updated_at
                FROM trees
                $where
                ORDER BY tree_code ASC
                LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode([
            'records'     => $stmt->fetchAll(),
            'total'       => $total,
            'page'        => $page,
            'page_size'   => $pageSize,
            'total_pages' => (int)ceil($total / $pageSize),
        ]);
        break;

    // ── DELETE TREE (used by the "clear manual entries" button) ──
    case 'delete_tree':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { bad('POST required'); }
        $code = trim($_POST['tree_code'] ?? '');
        if (!$code) { bad('tree_code required'); }
        $stmt = $pdo->prepare('DELETE FROM trees WHERE tree_code = ? AND data_source = ?');
        $stmt->execute([$code, 'manual']);
        echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
        break;

    // ── CHECK CODE EXISTS (for duplicate warning in form) ────
    case 'check_code':
        $code = trim($_GET['code'] ?? '');
        if (!$code) { bad('code required'); }
        $stmt = $pdo->prepare('SELECT tree_code, data_source FROM trees WHERE tree_code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        echo json_encode(['exists' => (bool)$row, 'row' => $row ?: null]);
        break;

    // ── GET ANALYTICS (NEW: for the Analytics Carbon page) ────
    case 'get_analytics':
        $stmt = $pdo->query("SELECT species_id, dbh_cm, health_status, updated_at FROM trees");
        $trees = $stmt->fetchAll();
        
        $totalTrees = count($trees);
        $totalBiomass = 0;
        $totalCarbon = 0;
        $speciesCount = [];
        $speciesCarbon = [];
        $healthCount = ['Healthy' => 0, 'Monitor' => 0, 'Critical' => 0];
        $dbhBins = [0, 0, 0, 0, 0, 0]; // <10, 10-20, 20-30, 30-40, 40-50, >50
        $monthlyTrees = [];
        
        foreach ($trees as $tree) {
            $dbh = $tree['dbh_cm'] ? (float)$tree['dbh_cm'] : 0;
            
            // Allometric equation for Mangrove AGB (kg): AGB = 0.119 * (DBH^2.62)
            $agb = 0.119 * pow($dbh, 2.62);
            $carbon_kg = $agb * 0.47; // Carbon is ~47% of biomass
            $carbon_t = $carbon_kg / 1000;
            
            $totalBiomass += $agb;
            $totalCarbon += $carbon_t;
            
            // Species composition
            $sp = $tree['species_id'] ?: 'Unknown';
            $speciesCount[$sp] = ($speciesCount[$sp] ?? 0) + 1;
            $speciesCarbon[$sp] = ($speciesCarbon[$sp] ?? 0) + $carbon_t;
            
            // Health status
            $h = $tree['health_status'] ?: 'Healthy';
            if (isset($healthCount[$h])) $healthCount[$h]++;
            
            // DBH Distribution bins
            if ($dbh < 10) $dbhBins[0]++;
            elseif ($dbh < 20) $dbhBins[1]++;
            elseif ($dbh < 30) $dbhBins[2]++;
            elseif ($dbh < 40) $dbhBins[3]++;
            elseif ($dbh < 50) $dbhBins[4]++;
            else $dbhBins[5]++;
            
            // Cumulative over time (YYYY-MM)
            $month = substr($tree['updated_at'], 0, 7);
            $monthlyTrees[$month] = ($monthlyTrees[$month] ?? 0) + 1;
        }
        
        // Build cumulative data
        ksort($monthlyTrees);
        $cumulative = 0;
        $cumulativeData = [];
        $labels = [];
        foreach ($monthlyTrees as $m => $count) {
            $cumulative += $count;
            $labels[] = $m;
            $cumulativeData[] = $cumulative;
        }
        
        echo json_encode([
            'total_trees' => $totalTrees,
            'total_biomass_kg' => round($totalBiomass, 2),
            'total_carbon_t' => round($totalCarbon, 4),
            'carbon_value_usd' => round($totalCarbon * 18, 2),
            'species_count' => $speciesCount,
            'species_carbon' => $speciesCarbon,
            'health_count' => $healthCount,
            'dbh_bins' => $dbhBins,
            'cumulative_labels' => $labels,
            'cumulative_data' => $cumulativeData
        ]);
        break;

    // ── GET SPECIES (dynamic dropdown for entry form) ────────
    case 'get_species':
        $stmt = $pdo->query("SELECT species_id FROM species ORDER BY species_id ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => "Unknown action '$action'"]);
}

// ── Helpers ──────────────────────────────────────────────────
function num($v) {
    if ($v === '' || $v === null) return null;
    return is_numeric($v) ? (float)$v : null;
}
function bad($msg) {
    http_response_code(400);
    echo json_encode(['error' => $msg]);
    exit;
}