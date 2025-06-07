<?php
$host = "localhost";
$db = "expense_tracker";
$user = "root";
$pass = "S00per-d00per";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

$data = json_decode(file_get_contents("php://input"), true);

$stmt = $conn->prepare("INSERT INTO expenses (description, amount, category, day, month, year) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sdssii", $data["description"], $data["amount"], $data["category"], $data["day"], $data["month"], $data["year"]);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
