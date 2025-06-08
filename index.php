<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Expense Tracker App</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body {
      background-color: #f4f6f8;
      padding: 20px;
      font-family: 'Segoe UI', sans-serif;
    }
    .card {
      border: none;
      border-radius: 16px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
    }
    table {
      font-size: 0.9rem;
    }
    .expense-table-wrapper {
      max-height: 220px;
      overflow-y: auto;
      width: 100%;
    }
    .table thead th {
      position: sticky;
      top: 0;
      background: #fff;
      z-index: 2;
    }
    .small-chart {
      max-width: 320px;
      max-height: 320px;
      margin: 0 auto;
      display: block;
    }
  </style>
</head>

<body>
  <div class="container">
    <h2 class="mb-4 text-center">Expense & Income Tracker</h2>
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
          <p><strong>Remaining Balance: <span id="balance">0</span></strong></p>
          <button class="btn btn-secondary w-100" onclick="app.exportCSV()">Export CSV (DB)</button>
        </div>
      </div>
    </div>
    <div class="row mb-4">
      <div class="col-md-4">
        <select id="monthFilter" class="form-control" onchange="app.fetchAndRender()">
          <option value="">All Months</option>
        </select>
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
        this.fetchAndRender();
      }

      async fetchAndRender() {
        const month = document.getElementById('monthFilter').value;
        const res = await fetch('fetch_records.php?month=' + encodeURIComponent(month));
        const data = await res.json();
        this.renderTables(data.expenses, data.incomes);
        this.renderSummary(data.expenses, data.incomes);
        this.populateMonths(data.expenses, data.incomes);
        this.drawChart(data.incomes);
        this.drawExpenseChart(data.expenses);
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
            <td><button class="btn btn-sm btn-danger" onclick="app.deleteExpense(${e.id})">Delete</button></td>
          </tr>`;
        });
        incomeTable.innerHTML = '';
        incomes.forEach(i => {
          incomeTable.innerHTML += `<tr>
            <td>${i.year}-${String(i.month).padStart(2, '0')}-${String(i.day).padStart(2, '0')}</td>
            <td>${i.source}</td>
            <td>${i.amount.toFixed(2)}</td>
            <td><button class="btn btn-sm btn-danger" onclick="app.deleteIncome(${i.id})">Delete</button></td>
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

      async deleteExpense(id) {
        if (!confirm('Delete this expense?')) return;
        const res = await fetch('delete_expense.php?id=' + id, { method: 'DELETE' });
        const result = await res.json();
        if (result.success) this.fetchAndRender();
        else alert('Failed to delete expense.');
      }

      async deleteIncome(id) {
        if (!confirm('Delete this income?')) return;
        const res = await fetch('delete_income.php?id=' + id, { method: 'DELETE' });
        const result = await res.json();
        if (result.success) this.fetchAndRender();
        else alert('Failed to delete income.');
      }

      exportCSV() {
        const month = document.getElementById('monthFilter').value;
        window.open('export_csv.php?month=' + encodeURIComponent(month), '_blank');
      }
    }

    // Global instance for event handlers
    const app = new ExpenseTrackerApp();
  </script>
</body>
</html>