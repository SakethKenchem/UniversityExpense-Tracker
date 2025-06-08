<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExpenseExporter
{
    private $conn;
    private $spreadsheet;
    private $sheet;
    private $monthFilter;
    private $whereClause = "";
    private $params = [];
    private $types = "";

    public function __construct($dbConfig, $monthFilter = "")
    {
        $this->connectDb($dbConfig);
        $this->spreadsheet = new Spreadsheet();
        $this->sheet = $this->spreadsheet->getActiveSheet();
        $this->monthFilter = $monthFilter;
        $this->prepareFilter();
    }

    private function connectDb($dbConfig)
    {
        $this->conn = new mysqli(
            $dbConfig['host'],
            $dbConfig['user'],
            $dbConfig['pass'],
            $dbConfig['db']
        );
        if ($this->conn->connect_error) {
            http_response_code(500);
            die("DB connection failed");
        }
    }

    private function prepareFilter()
    {
        if ($this->monthFilter && preg_match('/^\d{4}-\d{2}$/', $this->monthFilter)) {
            [$y, $m] = explode('-', $this->monthFilter);
            $this->whereClause = " WHERE year = ? AND month = ?";
            $this->params = [$y, (int)$m];
            $this->types = "ii";
        }
    }

    private function fetchCSVData($table, $columns)
    {
        $sql = "SELECT " . implode(", ", $columns) . " FROM $table {$this->whereClause} ORDER BY year DESC, month DESC, day DESC";
        $stmt = $this->conn->prepare($sql);
        if ($this->whereClause) $stmt->bind_param($this->types, ...$this->params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    private function groupByMonth($rows, $dateFields = ['year', 'month'])
    {
        $grouped = [];
        foreach ($rows as $row) {
            $key = $row[$dateFields[0]] . '-' . str_pad($row[$dateFields[1]], 2, '0', STR_PAD_LEFT);
            $grouped[$key][] = $row;
        }
        krsort($grouped);
        return $grouped;
    }

    private function classifyByCategory($rows, $categories = ['uber', 'food', 'airtime'])
    {
        $result = array_fill_keys($categories, 0);
        foreach ($rows as $row) {
            $cat = strtolower($row['category'] ?? '');
            if (isset($result[$cat])) {
                $result[$cat] += $row['amount'];
            }
        }
        return $result;
    }

    private function boldRow($rowNum, $colCount, $startCol = 'A')
    {
        $startIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($startCol);
        $endCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startIndex + $colCount - 1);
        $this->sheet->getStyle("{$startCol}{$rowNum}:{$endCol}{$rowNum}")->getFont()->setBold(true);
    }

    public function export()
    {
        $expenses = $this->fetchCSVData("expenses", ["year", "month", "day", "category", "description", "amount"]);
        $incomes = $this->fetchCSVData("income", ["year", "month", "day", "source", "amount"]);

        $totalExpenses = array_sum(array_column($expenses, 'amount'));
        $totalIncome = array_sum(array_column($incomes, 'amount'));
        $remainingBalance = $totalIncome - $totalExpenses;

        // Expenses Section
        $expensesCol = 'A';
        $row = 1;
        $this->sheet->setCellValue("{$expensesCol}{$row}", '==== EXPENSES ====');
        $this->boldRow($row, 4, $expensesCol);
        $row++;
        $this->sheet->fromArray(['Date', 'Category', 'Description', 'Amount'], NULL, "{$expensesCol}{$row}");
        $this->boldRow($row, 4, $expensesCol);
        $row++;
        foreach ($expenses as $e) {
            $date = sprintf('%04d-%02d-%02d', $e['year'], $e['month'], $e['day']);
            $this->sheet->fromArray([$date, $e['category'], $e['description'], number_format($e['amount'], 2)], NULL, "{$expensesCol}{$row}");
            $row++;
        }
        $this->sheet->fromArray(['Total Expenses', '', '', number_format($totalExpenses, 2)], NULL, "{$expensesCol}{$row}");

        // Income Section
        $incomeCol = 'F';
        $incomeRow = 1;
        $this->sheet->setCellValue("{$incomeCol}{$incomeRow}", '==== INCOME ====');
        $this->boldRow($incomeRow, 3, $incomeCol);
        $incomeRow++;
        $this->sheet->fromArray(['Date', 'Source', 'Amount'], NULL, "{$incomeCol}{$incomeRow}");
        $this->boldRow($incomeRow, 3, $incomeCol);
        $incomeRow++;
        foreach ($incomes as $i) {
            $date = sprintf('%04d-%02d-%02d', $i['year'], $i['month'], $i['day']);
            $this->sheet->fromArray([$date, $i['source'], number_format($i['amount'], 2)], NULL, "{$incomeCol}{$incomeRow}");
            $incomeRow++;
        }
        $this->sheet->fromArray(['Total Income', '', number_format($totalIncome, 2)], NULL, "{$incomeCol}{$incomeRow}");

        // Remaining Balance Section
        $balanceCol = 'K';
        $balanceRow = 1;
        $this->sheet->setCellValue("{$balanceCol}{$balanceRow}", '==== REMAINING BALANCE ====');
        $this->boldRow($balanceRow, 2, $balanceCol);
        $balanceRow++;
        $this->sheet->fromArray(['Balance', number_format($remainingBalance, 2)], NULL, "{$balanceCol}{$balanceRow}");
        $this->boldRow($balanceRow, 2, $balanceCol);

        // Find max rows used in main 3 sections for spacing later
        $maxRow = max($row, $incomeRow, $balanceRow);
        $startRow = $maxRow + 3;

        // Expense Classification by Month
        $this->sheet->setCellValue("A{$startRow}", '==== EXPENSE CLASSIFICATION BY MONTH (Uber, Food, Airtime) ====');
        $this->boldRow($startRow, 4, 'A');
        $startRow++;
        $this->sheet->fromArray(['Month', 'Uber', 'Food', 'Airtime'], NULL, "A{$startRow}");
        $this->boldRow($startRow, 4, 'A');
        $startRow++;

        $expensesByMonth = $this->groupByMonth($expenses);
        $categories = ['uber', 'food', 'airtime'];
        foreach ($expensesByMonth as $month => $rows) {
            $classified = $this->classifyByCategory($rows, $categories);
            $this->sheet->fromArray([
                $month,
                number_format($classified['uber'], 2),
                number_format($classified['food'], 2),
                number_format($classified['airtime'], 2),
            ], NULL, "A{$startRow}");
            $startRow++;
        }

        // Autofit columns
        foreach (range('A', 'L') as $col) {
            $this->sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Output Excel file
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="university_expenses.xlsx"');
        $writer = new Xlsx($this->spreadsheet);
        $writer->save('php://output');
        $this->conn->close();
        exit;
    }
}

// Usage
$dbConfig = [
    'host' => 'localhost',
    'db'   => 'expense_tracker',
    'user' => 'root',
    'pass' => 'S00per-d00per'
];
$monthFilter = $_GET['month'] ?? "";

$exporter = new ExpenseExporter($dbConfig, $monthFilter);
$exporter->export();
