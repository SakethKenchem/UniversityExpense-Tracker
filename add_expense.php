<?php
class ExpenseTracker {
    private $conn;

    public function __construct($host, $user, $pass, $db) {
        $this->conn = new mysqli($host, $user, $pass, $db);
        if ($this->conn->connect_error) {
            die("DB connection failed: " . $this->conn->connect_error);
        }
    }

    public function addExpense($data) {
        $stmt = $this->conn->prepare(
            "INSERT INTO expenses (description, amount, category, day, month, year) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "sdssii",
            $data["description"],
            $data["amount"],
            $data["category"],
            $data["day"],
            $data["month"],
            $data["year"]
        );

        if ($stmt->execute()) {
            $result = ["success" => true];
        } else {
            $result = ["success" => false, "error" => $stmt->error];
        }

        $stmt->close();
        return $result;
    }

    public function close() {
        $this->conn->close();
    }
}

$host = "localhost";
$db = "expense_tracker";
$user = "root";
$pass = "S00per-d00per";

$data = json_decode(file_get_contents("php://input"), true);

$tracker = new ExpenseTracker($host, $user, $pass, $db);
$result = $tracker->addExpense($data);
$tracker->close();

echo json_encode($result);
?>
