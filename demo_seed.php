<?php
require_once __DIR__ . '/includes/db.php';

$pdo = db();

// Clear existing demo data
$pdo->exec("DELETE FROM qrepo_files");
$pdo->exec("DELETE FROM qrepo_folders");
$pdo->exec("ALTER TABLE qrepo_folders AUTO_INCREMENT = 1");
$pdo->exec("ALTER TABLE qrepo_files AUTO_INCREMENT = 1");

// ── Folder tree ──────────────────────────────────────────────────────────────
$folders = [
    // [name, parent_name, order]
    ['CSE Department', null, 1],
    ['EEE Department', null, 2],
    ['ME Department',  null, 3],

    // CSE
    ['Data Structures & Algorithms', 'CSE Department', 1],
    ['Operating Systems',            'CSE Department', 2],
    ['Database Systems',             'CSE Department', 3],
    ['Computer Networks',            'CSE Department', 4],
    ['Software Engineering',         'CSE Department', 5],

    // EEE
    ['Circuit Theory',               'EEE Department', 1],
    ['Digital Electronics',          'EEE Department', 2],
    ['Power Systems',                'EEE Department', 3],

    // ME
    ['Thermodynamics',               'ME Department', 1],
    ['Fluid Mechanics',              'ME Department', 2],

    // DSA years
    ['2022',  'Data Structures & Algorithms', 1],
    ['2023',  'Data Structures & Algorithms', 2],
    ['2024',  'Data Structures & Algorithms', 3],

    // OS years
    ['2023',  'Operating Systems', 1],
    ['2024',  'Operating Systems', 2],

    // DB Systems years
    ['2023',  'Database Systems', 1],
    ['2024',  'Database Systems', 2],

    // Networks
    ['2023',  'Computer Networks', 1],
    ['2024',  'Computer Networks', 2],

    // Software Eng
    ['2024',  'Software Engineering', 1],

    // Circuit Theory
    ['2023',  'Circuit Theory', 1],
    ['2024',  'Circuit Theory', 2],

    // Digital Electronics
    ['2024',  'Digital Electronics', 1],

    // Power Systems
    ['2023',  'Power Systems', 1],

    // Thermodynamics
    ['2024',  'Thermodynamics', 1],

    // Fluid Mechanics
    ['2024',  'Fluid Mechanics', 1],
];

$idMap = []; // name+parent → id

$insertFolder = $pdo->prepare("INSERT INTO qrepo_folders (name, parent_id, sort_order) VALUES (?, ?, ?)");

foreach ($folders as [$name, $parentName, $order]) {
    $parentId = $parentName ? ($idMap[$parentName] ?? null) : null;
    $insertFolder->execute([$name, $parentId, $order]);
    $id = (int)$pdo->lastInsertId();
    $idMap[$name] = $id;
    // For year-named folders, key by parent+name to avoid collision
    $idMap[$parentName . '::' . $name] = $id;
}

// Re-map year folders properly (they share year names across subjects)
// Reset and do it properly
$pdo->exec("DELETE FROM qrepo_files");
$pdo->exec("DELETE FROM qrepo_folders");

$idByKey = [];

function insertFolder(PDO $pdo, string $name, ?int $parentId, int $order): int {
    $stmt = $pdo->prepare("INSERT INTO qrepo_folders (name, parent_id, sort_order) VALUES (?, ?, ?)");
    $stmt->execute([$name, $parentId, $order]);
    return (int)$pdo->lastInsertId();
}

// Top-level departments
$cse = insertFolder($pdo, 'CSE Department', null, 1);
$eee = insertFolder($pdo, 'EEE Department', null, 2);
$me  = insertFolder($pdo, 'ME Department',  null, 3);

// CSE courses
$dsa  = insertFolder($pdo, 'Data Structures & Algorithms', $cse, 1);
$os   = insertFolder($pdo, 'Operating Systems',            $cse, 2);
$db   = insertFolder($pdo, 'Database Systems',             $cse, 3);
$net  = insertFolder($pdo, 'Computer Networks',            $cse, 4);
$se   = insertFolder($pdo, 'Software Engineering',         $cse, 5);

// EEE courses
$ckt  = insertFolder($pdo, 'Circuit Theory',    $eee, 1);
$dig  = insertFolder($pdo, 'Digital Electronics', $eee, 2);
$pwr  = insertFolder($pdo, 'Power Systems',     $eee, 3);

// ME courses
$thm  = insertFolder($pdo, 'Thermodynamics',  $me, 1);
$flu  = insertFolder($pdo, 'Fluid Mechanics', $me, 2);

// Year folders under each course
$dsa22 = insertFolder($pdo, '2022', $dsa, 1);
$dsa23 = insertFolder($pdo, '2023', $dsa, 2);
$dsa24 = insertFolder($pdo, '2024', $dsa, 3);
$os23  = insertFolder($pdo, '2023', $os,  1);
$os24  = insertFolder($pdo, '2024', $os,  2);
$db23  = insertFolder($pdo, '2023', $db,  1);
$db24  = insertFolder($pdo, '2024', $db,  2);
$net23 = insertFolder($pdo, '2023', $net, 1);
$net24 = insertFolder($pdo, '2024', $net, 2);
$se24  = insertFolder($pdo, '2024', $se,  1);
$ckt23 = insertFolder($pdo, '2023', $ckt, 1);
$ckt24 = insertFolder($pdo, '2024', $ckt, 2);
$dig24 = insertFolder($pdo, '2024', $dig, 1);
$pwr23 = insertFolder($pdo, '2023', $pwr, 1);
$thm24 = insertFolder($pdo, '2024', $thm, 1);
$flu24 = insertFolder($pdo, '2024', $flu, 1);

echo "Folders created.\n";

// ── Fake files ───────────────────────────────────────────────────────────────
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

function makeDummyPdf(string $uploadDir, string $label): array {
    $filename = uniqid('demo_', true) . '.pdf';
    $path = $uploadDir . $filename;
    // Minimal valid-looking PDF placeholder
    $content = "%PDF-1.4\n% Demo file: $label\n%%EOF\n";
    file_put_contents($path, $content);
    return [$filename, strlen($content)];
}

$fileData = [
    // [folder_id, title, label]
    [$dsa22, 'Midterm Exam 2022',             'DSA Midterm 2022'],
    [$dsa22, 'Final Exam 2022',               'DSA Final 2022'],
    [$dsa23, 'Midterm Exam 2023',             'DSA Midterm 2023'],
    [$dsa23, 'Final Exam 2023',               'DSA Final 2023'],
    [$dsa23, 'Class Test 1 — 2023',           'DSA CT1 2023'],
    [$dsa24, 'Midterm Exam 2024',             'DSA Midterm 2024'],
    [$dsa24, 'Final Exam 2024',               'DSA Final 2024'],

    [$os23,  'Midterm Exam 2023',             'OS Midterm 2023'],
    [$os23,  'Final Exam 2023',               'OS Final 2023'],
    [$os24,  'Midterm Exam 2024',             'OS Midterm 2024'],
    [$os24,  'Final Exam 2024',               'OS Final 2024'],

    [$db23,  'Midterm Exam 2023',             'DB Midterm 2023'],
    [$db23,  'Final Exam 2023',               'DB Final 2023'],
    [$db24,  'Midterm Exam 2024',             'DB Midterm 2024'],
    [$db24,  'Final Exam 2024',               'DB Final 2024'],
    [$db24,  'Lab Report Format 2024',        'DB Lab Report 2024'],

    [$net23, 'Midterm Exam 2023',             'NET Midterm 2023'],
    [$net23, 'Final Exam 2023',               'NET Final 2023'],
    [$net24, 'Midterm Exam 2024',             'NET Midterm 2024'],

    [$se24,  'Midterm Exam 2024',             'SE Midterm 2024'],
    [$se24,  'Final Exam 2024',               'SE Final 2024'],

    [$ckt23, 'Midterm Exam 2023',             'CKT Midterm 2023'],
    [$ckt23, 'Final Exam 2023',               'CKT Final 2023'],
    [$ckt24, 'Midterm Exam 2024',             'CKT Midterm 2024'],
    [$ckt24, 'Final Exam 2024',               'CKT Final 2024'],

    [$dig24, 'Midterm Exam 2024',             'DIG Midterm 2024'],
    [$dig24, 'Final Exam 2024',               'DIG Final 2024'],

    [$pwr23, 'Final Exam 2023',               'PWR Final 2023'],

    [$thm24, 'Midterm Exam 2024',             'THM Midterm 2024'],
    [$thm24, 'Final Exam 2024',               'THM Final 2024'],

    [$flu24, 'Midterm Exam 2024',             'FLU Midterm 2024'],
];

$insertFile = $pdo->prepare(
    "INSERT INTO qrepo_files (folder_id, title, filename, original_name, file_size) VALUES (?,?,?,?,?)"
);

foreach ($fileData as [$folderId, $title, $label]) {
    [$filename, $size] = makeDummyPdf(UPLOAD_DIR, $label);
    $origName = str_replace(' ', '_', $label) . '.pdf';
    $insertFile->execute([$folderId, $title, $filename, $origName, $size]);
}

echo "Files inserted.\n";
echo "Done! Visit http://localhost/qrepo/ to see the demo.\n";
