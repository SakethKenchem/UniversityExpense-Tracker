<?php
$host = "localhost";
$db = "expense_tracker";
$user = "root";
$pass = "S00per-d00per";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    die("DB connection failed");
}

$monthFilter = $_GET['month'] ?? ""; // YYYY-MM or empty

$whereClause = "";
$params = [];
$types = "";

if ($monthFilter && preg_match('/^\d{4}-\d{2}$/', $monthFilter)) {
    [$y, $m] = explode('-', $monthFilter);
    $whereClause = " WHERE year = ? AND month = ?";
    $params = [$y, (int)$m];
    $types = "ii";
}

function fetchCSVData($conn, $table, $columns, $whereClause, $params, $types) {
    $sql = "SELECT " . implode(", ", $columns) . " FROM $table $whereClause ORDER BY year DESC, month DESC, day DESC";
    $stmt = $conn->prepare($sql);
    if ($whereClause) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

$expenses = fetchCSVData($conn, "expenses", ["year", "month", "day", "category", "description", "amount"], $whereClause, $params, $types);
$incomes = fetchCSVData($conn, "income", ["year", "month", "day", "source", "amount"], $whereClause, $params, $types);

// Calculate totals
$totalExpenses = array_sum(array_column($expenses, 'amount'));
$totalIncome = array_sum(array_column($incomes, 'amount'));
$remainingBalance = $totalIncome - $totalExpenses;

// Helper: group by month
function groupByMonth($rows, $dateFields = ['year', 'month']) {
    $grouped = [];
    foreach ($rows as $row) {
        $key = $row[$dateFields[0]] . '-' . str_pad($row[$dateFields[1]], 2, '0', STR_PAD_LEFT);
        $grouped[$key][] = $row;
    }
    krsort($grouped); // latest month first
    return $grouped;
}

// Helper: classify by category
function classifyByCategory($rows, $categories = ['uber', 'food', 'airtime']) {
    $result = [];
    foreach ($categories as $cat) {
        $result[$cat] = 0;
    }
    foreach ($rows as $row) {
        $cat = strtolower($row['category'] ?? '');
        if (isset($result[$cat])) {
            $result[$cat] += $row['amount'];
        }
    }
    return $result;
}

// Output CSV with improved styling
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="expense_income_export.csv"');

$output = fopen('php://output', 'w');

// Section: Expenses (all)
fputcsv($output, ['==== EXPENSES ====']);
fputcsv($output, ['Date', 'Category', 'Description', 'Amount']);
foreach ($expenses as $e) {
    $date = sprintf('%04d-%02d-%02d', $e['year'], $e['month'], $e['day']);
    fputcsv($output, [$date, $e['category'], $e['description'], number_format($e['amount'], 2)]);
}
fputcsv($output, ['Total Expenses', '', '', number_format($totalExpenses, 2)]);
fputcsv($output, []);

// Section: Income (all)
fputcsv($output, ['==== INCOME ====']);
fputcsv($output, ['Date', 'Source', 'Amount']);
foreach ($incomes as $i) {
    $date = sprintf('%04d-%02d-%02d', $i['year'], $i['month'], $i['day']);
    fputcsv($output, [$date, $i['source'], number_format($i['amount'], 2)]);
}
fputcsv($output, ['Total Income', '', number_format($totalIncome, 2)]);
fputcsv($output, []);

// Section: Remaining Balance
fputcsv($output, ['==== REMAINING BALANCE ====']);
fputcsv($output, ['Balance', number_format($remainingBalance, 2)]);
fputcsv($output, []);

// Section: Expenses by Month
fputcsv($output, ['==== EXPENSES BY MONTH ====']);
$expensesByMonth = groupByMonth($expenses);
foreach ($expensesByMonth as $month => $rows) {
    $monthTotal = array_sum(array_column($rows, 'amount'));
    fputcsv($output, ["$month", '', '', number_format($monthTotal, 2)]);
    foreach ($rows as $e) {
        $date = sprintf('%04d-%02d-%02d', $e['year'], $e['month'], $e['day']);
        fputcsv($output, [$date, $e['category'], $e['description'], number_format($e['amount'], 2)]);
    }
    fputcsv($output, []); // space between months
}

// Section: Income by Month
fputcsv($output, ['==== INCOME BY MONTH ====']);
$incomesByMonth = groupByMonth($incomes);
foreach ($incomesByMonth as $month => $rows) {
    $monthTotal = array_sum(array_column($rows, 'amount'));
    fputcsv($output, ["$month", '', number_format($monthTotal, 2)]);
    foreach ($rows as $i) {
        $date = sprintf('%04d-%02d-%02d', $i['year'], $i['month'], $i['day']);
        fputcsv($output, [$date, $i['source'], number_format($i['amount'], 2)]);
    }
    fputcsv($output, []); // space between months
}

// Section: Expense Classification by Month (Uber, Food, Airtime)
fputcsv($output, ['==== EXPENSE CLASSIFICATION BY MONTH (Uber, Food, Airtime) ====']);
fputcsv($output, ['Month', 'Uber', 'Food', 'Airtime']);
foreach ($expensesByMonth as $month => $rows) {
    $classified = classifyByCategory($rows, ['uber', 'food', 'airtime']);
    fputcsv($output, [
        $month,
        number_format($classified['uber'], 2),
        number_format($classified['food'], 2),
        number_format($classified['airtime'], 2)
    ]);
}
fputcsv($output, []);

// Section: Income Classification by Month (Uber, Food, Airtime)
fputcsv($output, ['==== INCOME CLASSIFICATION BY MONTH (Uber, Food, Airtime) ====']);
fputcsv($output, ['Month', 'Uber', 'Food', 'Airtime']);
foreach ($incomesByMonth as $month => $rows) {
    // For income, use 'source' instead of 'category'
    $classified = ['uber' => 0, 'food' => 0, 'airtime' => 0];
    foreach ($rows as $row) {
        $src = strtolower($row['source'] ?? '');
        if (isset($classified[$src])) {
            $classified[$src] += $row['amount'];
        }
    }
    fputcsv($output, [
        $month,
        number_format($classified['uber'], 2),
        number_format($classified['food'], 2),
        number_format($classified['airtime'], 2)
    ]);
}

fclose($output);
$conn->close();
exit;