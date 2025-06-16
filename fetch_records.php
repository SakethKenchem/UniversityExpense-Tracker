<?php
header('Content-Type: application/json');

class Database {
    private $conn;

    public function __construct($host, $user, $pass, $db) {
        $this->conn = new mysqli($host, $user, $pass, $db);
        if ($this->conn->connect_error) {
            http_response_code(500);
            echo json_encode(["error" => "DB connection failed"]);
            exit;
        }
    }

    public function fetchTable($table, $columns, $whereClause = "", $params = [], $types = "") {
        $sql = "SELECT id, " . implode(", ", $columns) . " FROM $table $whereClause ORDER BY year DESC, month DESC, day DESC, id DESC";
        $stmt = $this->conn->prepare($sql);
        if ($whereClause && $params && $types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            foreach ($columns as $col) {
                if (isset($row[$col]) && is_numeric($row[$col])) {
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

    public function close() {
        $this->conn->close();
    }
}

class ExpenseTracker {
    private $db;
    private $monthFilter;
    private $whereClause = "";
    private $params = [];
    private $types = "";

    public function __construct($db, $monthFilter = "") {
        $this->db = $db;
        $this->monthFilter = $monthFilter;
        $this->buildWhereClause();
    }

    private function buildWhereClause() {
        if ($this->monthFilter && preg_match('/^\d{4}-\d{2}$/', $this->monthFilter)) {
            [$y, $m] = explode('-', $this->monthFilter);
            $this->whereClause = " WHERE year = ? AND month = ?";
            $this->params = [$y, (int)$m];
            $this->types = "ii";
        }
    }

    public function getExpenses() {
        return $this->db->fetchTable(
            "expenses",
            ["amount", "category", "description", "day", "month", "year"],
            $this->whereClause,
            $this->params,
            $this->types
        );
    }

    public function getIncomes() {
        return $this->db->fetchTable(
            "income",
            ["amount", "source", "day", "month", "year"],
            $this->whereClause,
            $this->params,
            $this->types
        );
    }
}

// --- Main execution ---
$host = "localhost";
$db = "expense_tracker";
$user = "root";
$pass = "S00per-d00per";
$monthFilter = $_GET['month'] ?? "";

$db = new Database($host, $user, $pass, $db);
$tracker = new ExpenseTracker($db, $monthFilter);

echo json_encode([
    "expenses" => $tracker->getExpenses(),
    "incomes" => $tracker->getIncomes()
]);

$db->close();
