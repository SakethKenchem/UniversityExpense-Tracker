<?php
header('Content-Type: application/json');
$host = "localhost";
$db = "expense_tracker";
$user = "root";
$pass = "S00per-d00per";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

$monthFilter = $_GET['month'] ?? ""; // format YYYY-MM or empty

$whereClause = "";
$params = [];
$types = "";

if ($monthFilter && preg_match('/^\d{4}-\d{2}$/', $monthFilter)) {
    [$y, $m] = explode('-', $monthFilter);
    $whereClause = " WHERE year = ? AND month = ?";
    $params = [$y, (int)$m];
    $types = "ii";
}

function fetchTable($conn, $table, $columns, $whereClause, $params, $types) {
    $sql = "SELECT id, " . implode(", ", $columns) . " FROM $table $whereClause ORDER BY year DESC, month DESC, day DESC, id DESC";
    $stmt = $conn->prepare($sql);
    if ($whereClause) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        foreach ($columns as $col) {
            if (isset($row[$col]) && is_numeric($row[$col])) {
                // Cast amounts to float
                if (in_array($col, ['amount'])) {
                    $row[$col] = (float)$row[$col];
                } else {
                    $row[$col] = (int)$row[$col];
                }
            }
        }
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

$expenses = fetchTable($conn, "expenses", ["amount", "category", "description", "day", "month", "year"], $whereClause, $params, $types);
$incomes = fetchTable($conn, "income", ["amount", "source", "day", "month", "year"], $whereClause, $params, $params ? $types : "");

echo json_encode([
    "expenses" => $expenses,
    "incomes" => $incomes
]);
$conn->close();
