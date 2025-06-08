<?php
// --- PHP delete handler at the top of the file ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $type = $_POST['type'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    $allowed = ['expense' => 'expenses', 'income' => 'income'];
    $table = $allowed[$type] ?? null;
    $success = false;
    if ($table && $id > 0) {
        $mysqli = new mysqli("localhost", "root", "S00per-d00per", "expense_tracker");
        if (!$mysqli->connect_error) {
            $stmt = $mysqli->prepare("DELETE FROM `$table` WHERE id=?");
            $stmt->bind_param("i", $id);
            $success = $stmt->execute();
            $stmt->close();
            $mysqli->close();
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => $success]);
    exit;
}

// --- PHP search handler for AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search_expenses'])) {
    $search = $_GET['search_expenses'];
    $mysqli = new mysqli("localhost", "root", "S00per-d00per", "expense_tracker");
    $results = [];
    if (!$mysqli->connect_error) {
        $like = '%' . $mysqli->real_escape_string($search) . '%';
        $sql = "SELECT id, category, description, amount, day, month, year FROM expenses 
                WHERE category LIKE ? OR description LIKE ? OR amount LIKE ? OR day LIKE ? OR month LIKE ? OR year LIKE ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ssssss", $like, $like, $like, $like, $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['amount'] = floatval($row['amount']);
            $results[] = $row;
        }
        $stmt->close();
        $mysqli->close();
    }
    header('Content-Type: application/json');
    echo json_encode(['expenses' => $results]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>University Expense Tracker</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { background-color: #f4f6f8; padding: 20px; font-family: 'Segoe UI', sans-serif; }
    .card { border: none; border-radius: 16px; box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05); }
    table { font-size: 0.9rem; }
    .expense-table-wrapper { max-height: 350px; overflow-y: auto; width: 100%; }
    .expense-table-wrapper  { max-height: 350px; overflow-y: auto; width: 100%; }
    .table thead th { position: sticky; top: 0; background: #fff; z-index: 2; }
    .small-chart { max-width: 320px; max-height: 320px; margin: 0 auto; display: block; }
    .search-bar { max-width: 350px; margin-bottom: 10px; }
  </style>
</head>
<body>
  <div class="container">
    <h2 class="mb-4 text-center">University Expense Tracker</h2>
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="card p-3">
          <h5>Add Expense</h5>
          <input type="number" id="amount" placeholder="Amount" class="form-control mb-2" />
          <input type="text" id="description" placeholder="Description" class="form-control mb-2" />
          <select id="type" class="form-control mb-2">
            <option value="uber">Uber</option>
            <option value="food">Food</option>
            <option value="airtime">Airtime</option>
            <option value="other">Other</option>
          </select>
          <input type="date" id="date" class="form-control mb-2" />
          <button class="btn btn-primary w-100" onclick="app.addExpense()">Add Expense</button>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card p-3">
          <h5>Add Income</h5>
          <input type="number" id="incomeAmount" placeholder="Amount" class="form-control mb-2" />
          <input type="text" id="incomeDesc" placeholder="Description" class="form-control mb-2" />
          <input type="date" id="incomeDate" class="form-control mb-2" />
          <button class="btn btn-success w-100" onclick="app.addIncome()">Add Income</button>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card p-3">
          <h5>Summary</h5>
          <p>Total Income: <span id="totalIncome">0</span></p>
          <p>Total Expenses: <span id="totalExpenses">0</span></p>
            <p><strong>Remaining Balance: <span id="balance">0</span></strong> <span style="font-size:0.95em;color:#888;">as of <span id="balanceDate"><?php echo date('Y-m-d'); ?></span></span></p>
          <button class="btn btn-secondary w-100" onclick="app.exportCSV()">Export To Excel File Format</button>
        </div>
      </div>
    </div>
    <div class="row mb-4">
      <div class="col-md-4">
        <select id="monthFilter" class="form-control" onchange="app.fetchAndRender()">
          <option value="">All Months</option>
        </select>
      </div>
      <div class="col-md-4">
        <!-- Search bar for expenses -->
        <input type="text" id="expenseSearch" class="form-control search-bar" placeholder="Search expenses (all fields)" oninput="app.searchExpenses()" />
      </div>
    </div>
    <div class="row">
      <div class="col-md-6">
        <div class="card p-3 mb-4">
          <h5 class="mb-3">Expense Records</h5>
          <div class="expense-table-wrapper">
            <table class="table table-striped mb-0">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Category</th>
                  <th>Description</th>
                  <th>Amount</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="expenseTable"></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card p-3 mb-4">
          <h5 class="mb-3">Income Records</h5>
          <div class="expense-table-wrapper">
          <table class="table table-striped">
            <thead>
              <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Amount</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="incomeTable"></tbody>
          </table>
          </div>
        </div>
      </div>
    </div>
    <div class="row mt-5">
      <div class="col-md-6 offset-md-3">
        <canvas id="incomeChart" class="small-chart"></canvas>
        <div class="mt-4"></div>
        <canvas id="expenseChart" class="small-chart"></canvas>
      </div>
    </div>
  </div>
  <script>
    class ExpenseTrackerApp {
      constructor() {
        this.myPie = null;
        this.myExpensePie = null;
        this.expensesCache = [];
        this.incomesCache = [];
        this.lastSearch = '';
        this.fetchAndRender();
      }

      async fetchAndRender() {
        const month = document.getElementById('monthFilter').value;
        const res = await fetch('fetch_records.php?month=' + encodeURIComponent(month) + '&_=' + Date.now());
        const data = await res.json();
        this.expensesCache = data.expenses;
        this.incomesCache = data.incomes;
        this.renderTables(data.expenses, data.incomes);
        this.renderSummary(data.expenses, data.incomes);
        this.populateMonths(data.expenses, data.incomes);
        this.drawChart(data.incomes);
        this.drawExpenseChart(data.expenses);
        document.getElementById('expenseSearch').value = '';
        this.lastSearch = '';
      }

      async addExpense() {
        const amount = +document.getElementById('amount').value;
        const description = document.getElementById('description').value.trim();
        const category = document.getElementById('type').value;
        const date = document.getElementById('date').value || new Date().toISOString().slice(0, 10);
        if (!amount || !category) return alert("Please enter valid amount and category.");
        const d = new Date(date);
        const payload = {
          amount,
          description,
          category,
          day: d.getDate(),
          month: d.getMonth() + 1,
          year: d.getFullYear()
        };
        const res = await fetch('add_expense.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const result = await res.json();
        if (result.success) {
          this.clearInputs(['amount', 'description', 'date']);
          this.fetchAndRender();
        } else {
          alert('Failed to add expense: ' + (result.error || 'Unknown error'));
        }
      }

      async addIncome() {
        const amount = +document.getElementById('incomeAmount').value;
        const description = document.getElementById('incomeDesc').value.trim();
        const date = document.getElementById('incomeDate').value || new Date().toISOString().slice(0, 10);
        if (!amount) return alert("Please enter valid amount.");
        const d = new Date(date);
        const payload = {
          amount,
          source: description,
          day: d.getDate(),
          month: d.getMonth() + 1,
          year: d.getFullYear()
        };
        const res = await fetch('add_income.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const result = await res.json();
        if (result.success) {
          this.clearInputs(['incomeAmount', 'incomeDesc', 'incomeDate']);
          this.fetchAndRender();
        } else {
          alert('Failed to add income: ' + (result.error || 'Unknown error'));
        }
      }

      clearInputs(ids) {
        ids.forEach(id => {
          const el = document.getElementById(id);
          if (el) el.value = '';
        });
      }

      renderTables(expenses, incomes) {
        const expenseTable = document.getElementById('expenseTable');
        const incomeTable = document.getElementById('incomeTable');
        expenseTable.innerHTML = '';
        expenses.forEach(e => {
          expenseTable.innerHTML += `<tr>
            <td>${e.year}-${String(e.month).padStart(2, '0')}-${String(e.day).padStart(2, '0')}</td>
            <td>${e.category}</td>
            <td>${e.description}</td>
            <td>${e.amount.toFixed(2)}</td>
            <td><button class="btn btn-sm btn-danger" onclick="app.deleteTransaction('expense',${e.id})">Delete</button></td>
          </tr>`;
        });
        incomeTable.innerHTML = '';
        incomes.forEach(i => {
          incomeTable.innerHTML += `<tr>
            <td>${i.year}-${String(i.month).padStart(2, '0')}-${String(i.day).padStart(2, '0')}</td>
            <td>${i.source}</td>
            <td>${i.amount.toFixed(2)}</td>
            <td><button class="btn btn-sm btn-danger" onclick="app.deleteTransaction('income',${i.id})">Delete</button></td>
          </tr>`;
        });
      }

      renderSummary(expenses, incomes) {
        const totalExpenses = expenses.reduce((sum, e) => sum + e.amount, 0);
        const totalIncome = incomes.reduce((sum, i) => sum + i.amount, 0);
        document.getElementById('totalExpenses').textContent = totalExpenses.toFixed(2);
        document.getElementById('totalIncome').textContent = totalIncome.toFixed(2);
        document.getElementById('balance').textContent = (totalIncome - totalExpenses).toFixed(2);
      }

      populateMonths(expenses, incomes) {
        const select = document.getElementById('monthFilter');
        const monthsSet = new Set([
          ...expenses.map(e => `${e.year}-${String(e.month).padStart(2, '0')}`),
          ...incomes.map(i => `${i.year}-${String(i.month).padStart(2, '0')}`)
        ]);
        const uniqueMonths = Array.from(monthsSet).sort((a, b) => b.localeCompare(a));
        const currentVal = select.value;
        select.innerHTML = '<option value="">All Months</option>';
        uniqueMonths.forEach(m => {
          const opt = document.createElement('option');
          opt.value = m;
          opt.textContent = m;
          select.appendChild(opt);
        });
        if (currentVal) select.value = currentVal;
      }

      drawChart(incomes) {
        const ctx = document.getElementById('incomeChart').getContext('2d');
        const grouped = incomes.reduce((acc, item) => {
          acc[item.source] = (acc[item.source] || 0) + item.amount;
          return acc;
        }, {});
        if (this.myPie) this.myPie.destroy();
        this.myPie = new Chart(ctx, {
          type: 'pie',
          data: {
            labels: Object.keys(grouped),
            datasets: [{
              data: Object.values(grouped),
              backgroundColor: ['#4caf50', '#ff9800', '#03a9f4', '#e91e63', '#9c27b0', '#3f51b5', '#00bcd4'],
            }]
          },
          options: {
            plugins: {
              legend: { position: 'bottom' }
            }
          }
        });
      }

      drawExpenseChart(expenses) {
        const ctx = document.getElementById('expenseChart').getContext('2d');
        const month = document.getElementById('monthFilter').value;
        let filtered = expenses;
        if (month) {
          const [year, m] = month.split('-');
          filtered = expenses.filter(e => e.year == year && String(e.month).padStart(2, '0') == m);
        }
        const grouped = filtered.reduce((acc, item) => {
          acc[item.category] = (acc[item.category] || 0) + item.amount;
          return acc;
        }, {});
        if (this.myExpensePie) this.myExpensePie.destroy();
        this.myExpensePie = new Chart(ctx, {
          type: 'pie',
          data: {
            labels: Object.keys(grouped),
            datasets: [{
              data: Object.values(grouped),
              backgroundColor: ['#ff6384', '#36a2eb', '#ffcd56', '#4bc0c0', '#9966ff', '#ff9f40'],
            }]
          },
          options: {
            plugins: {
              legend: { position: 'bottom' }
            }
          }
        });
      }

      async deleteTransaction(type, id) {
        if (!confirm('Delete this ' + type + '?')) return;
        const form = new FormData();
        form.append('action', 'delete');
        form.append('type', type);
        form.append('id', id);
        const res = await fetch('index.php', {
          method: 'POST',
          body: form
        });
        const result = await res.json();
        if (result.success) this.fetchAndRender();
        else alert('Failed to delete ' + type + '.');
      }

      exportCSV() {
        const month = document.getElementById('monthFilter').value;
        window.open('export_csv.php?month=' + encodeURIComponent(month), '_blank');
      }

      async searchExpenses() {
        const query = document.getElementById('expenseSearch').value.trim();
        this.lastSearch = query;
        if (!query) {
          // If search is empty, show all (filtered by month)
          this.renderTables(this.expensesCache, this.incomesCache);
          this.renderSummary(this.expensesCache, this.incomesCache);
          this.drawExpenseChart(this.expensesCache);
          return;
        }
        // AJAX search
        const res = await fetch('index.php?search_expenses=' + encodeURIComponent(query));
        const data = await res.json();
        // Show only filtered expenses, but keep incomes as is
        this.renderTables(data.expenses, this.incomesCache);
        // Update summary and chart for filtered expenses
        this.renderSummary(data.expenses, this.incomesCache);
        this.drawExpenseChart(data.expenses);
      }
    }

    const app = new ExpenseTrackerApp();
  </script>
</body>

<!--add footer with my name and year and emoji--> 
<footer class="text-center mt-5">
  <p>&copy; Made By Saketh Kenchem 2025 <span role="img" aria-label="smile">🚀💻🛜</span></p
  <!-- Add a link to the GitHub repository -->
  <p>
    <a style="text-decoration: none;" href="https://github.com/SakethKenchem/UniversityExpense-Tracker" target="_blank">View on GitHub</a>
  </p>
</footer>

</html>