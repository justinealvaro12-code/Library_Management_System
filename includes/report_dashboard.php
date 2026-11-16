<?php
if (!defined('SITE_URL')) { http_response_code(403); exit('Forbidden'); }

$k = $report['kpis'];
$borrowSeries = $report['borrows_over_time'];
$hasBorrowSeries = array_sum($borrowSeries['values']) > 0;
$hasTopBooks = !empty($report['top_books']);
$hasTopBorrowers = !empty($report['top_borrowers']);
$hasFines = ((float)$k['fines_paid'] > 0 || (float)$k['fines_unpaid'] > 0);
$hasAnyChart = $hasBorrowSeries || $hasTopBooks || $hasTopBorrowers || $hasFines;
$rangeLabel = $report['from'] . ' to ' . $report['to'];
$exportQuery = http_build_query(array_intersect_key($_GET, array_flip(['from', 'to'])));
$exportUrl = $exportBase . ($exportQuery !== '' ? '?' . $exportQuery : '');
?>

<div class="page-header">
  <h1>Reports</h1>
  <p>Circulation and fine analytics for the selected date range.</p>
</div>

<?php if ($range['message'] !== ''): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($range['message']) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px">
  <form method="GET" class="report-filter">
    <div class="form-group">
      <label for="from">From</label>
      <input type="date" id="from" name="from" class="form-control" value="<?= htmlspecialchars($report['from']) ?>">
    </div>
    <div class="form-group">
      <label for="to">To</label>
      <input type="date" id="to" name="to" class="form-control" value="<?= htmlspecialchars($report['to']) ?>">
    </div>
    <div class="report-filter-actions">
      <button type="submit" class="btn btn-primary">Apply</button>
      <a href="reports.php" class="btn btn-outline">Reset</a>
      <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-outline">Export CSV</a>
    </div>
  </form>
  <div class="report-range">Showing <strong><?= htmlspecialchars($rangeLabel) ?></strong></div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
  <div class="stat-card" style="--accent:#c9a84c">
    <div class="stat-icon">📚</div>
    <div class="stat-value"><?= (int)$k['borrows'] ?></div>
    <div class="stat-label">Borrows</div>
  </div>
  <div class="stat-card" style="--accent:#27ae60">
    <div class="stat-icon">↩️</div>
    <div class="stat-value"><?= (int)$k['returns'] ?></div>
    <div class="stat-label">Returns</div>
  </div>
  <div class="stat-card" style="--accent:#e74c3c">
    <div class="stat-icon">⏰</div>
    <div class="stat-value"><?= (int)$k['overdue'] ?></div>
    <div class="stat-label">Overdue at range end</div>
  </div>
  <div class="stat-card" style="--accent:#16a085">
    <div class="stat-icon">₱</div>
    <div class="stat-value">₱<?= number_format((float)$k['fines_paid'], 2) ?></div>
    <div class="stat-label">Fines paid (returns in range)</div>
  </div>
  <div class="stat-card" style="--accent:#e67e22">
    <div class="stat-icon">₱</div>
    <div class="stat-value">₱<?= number_format((float)$k['fines_unpaid'], 2) ?></div>
    <div class="stat-label">Fines unpaid (returns in range)</div>
  </div>
  <div class="stat-card" style="--accent:#2980b9">
    <div class="stat-icon">👥</div>
    <div class="stat-value"><?= (int)$k['active_borrowers'] ?></div>
    <div class="stat-label">Active borrowers</div>
  </div>
</div>

<div class="report-grid">
  <div class="card chart-card">
    <div class="card-title">Borrows over time</div>
    <div class="report-range"><?= htmlspecialchars($rangeLabel) ?> (<?= $borrowSeries['granularity'] === 'month' ? 'monthly' : 'daily' ?>)</div>
    <?php if ($hasBorrowSeries): ?>
      <div class="chart-wrap"><canvas id="borrowsChart"></canvas></div>
    <?php else: ?>
      <p class="report-empty">No data for the selected range.</p>
    <?php endif; ?>
  </div>

  <div class="card chart-card">
    <div class="card-title">Top 5 books borrowed</div>
    <div class="report-range"><?= htmlspecialchars($rangeLabel) ?></div>
    <?php if ($hasTopBooks): ?>
      <div class="chart-wrap"><canvas id="topBooksChart"></canvas></div>
    <?php else: ?>
      <p class="report-empty">No data for the selected range.</p>
    <?php endif; ?>
  </div>

  <div class="card chart-card">
    <div class="card-title">Top 5 borrowers</div>
    <div class="report-range"><?= htmlspecialchars($rangeLabel) ?></div>
    <?php if ($hasTopBorrowers): ?>
      <div class="chart-wrap"><canvas id="topBorrowersChart"></canvas></div>
    <?php else: ?>
      <p class="report-empty">No data for the selected range.</p>
    <?php endif; ?>
  </div>

  <div class="card chart-card">
    <div class="card-title">Fines paid vs unpaid</div>
    <div class="report-range"><?= htmlspecialchars($rangeLabel) ?> · returns in range</div>
    <?php if ($hasFines): ?>
      <div class="chart-wrap"><canvas id="finesChart"></canvas></div>
    <?php else: ?>
      <p class="report-empty">No data for the selected range.</p>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-top:24px">
  <div class="card-title">Overdue at end of range</div>
  <div class="report-range"><?= htmlspecialchars($rangeLabel) ?></div>
  <?php if (empty($report['overdue_rows'])): ?>
    <p class="report-empty">No overdue books at the end of this range.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Student</th><th>ID</th><th>Book</th><th>Due Date</th><th>Days Overdue</th><th>Fine</th></tr></thead>
      <tbody>
      <?php foreach ($report['overdue_rows'] as $o): ?>
        <tr>
          <td><?= htmlspecialchars($o['full_name']) ?></td>
          <td style="font-size:12px;color:#aaa"><?= htmlspecialchars($o['student_id'] ?? '—') ?></td>
          <td><?= htmlspecialchars($o['title']) ?></td>
          <td style="color:#e74c3c"><?= htmlspecialchars($o['due_date']) ?></td>
          <td><span class="badge badge-danger"><?= (int)$o['days'] ?> days</span></td>
          <td style="color:#e74c3c;font-weight:600">₱<?= number_format((float)$o['fine'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php if ($hasAnyChart): ?>
<script src="<?= SITE_URL ?>/assets/js/chart.umd.min.js"></script>
<script>
(function () {
  const data = <?= json_encode([
      'borrowLabels' => $borrowSeries['labels'],
      'borrowValues' => $borrowSeries['values'],
      'topBookLabels' => array_map(function ($b) { return mb_strimwidth($b['title'], 0, 32, '...'); }, $report['top_books']),
      'topBookValues' => array_map(function ($b) { return $b['count']; }, $report['top_books']),
      'topBorrowerLabels' => array_map(function ($b) { return $b['full_name']; }, $report['top_borrowers']),
      'topBorrowerValues' => array_map(function ($b) { return $b['count']; }, $report['top_borrowers']),
      'fineValues' => [(float)$k['fines_paid'], (float)$k['fines_unpaid']],
  ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

  if (data.borrowLabels.length > 0) {
    new Chart(document.getElementById('borrowsChart'), {
      type: 'line',
      data: {
        labels: data.borrowLabels,
        datasets: [{
          label: 'Borrows',
          data: data.borrowValues,
          borderColor: '#c9a84c',
          backgroundColor: 'rgba(201,168,76,0.16)',
          fill: true,
          tension: 0.25,
          pointRadius: 2,
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  if (data.topBookLabels.length > 0) {
    new Chart(document.getElementById('topBooksChart'), {
      type: 'bar',
      data: {
        labels: data.topBookLabels,
        datasets: [{ label: 'Borrows', data: data.topBookValues, backgroundColor: '#2980b9' }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  if (data.topBorrowerLabels.length > 0) {
    new Chart(document.getElementById('topBorrowersChart'), {
      type: 'bar',
      data: {
        labels: data.topBorrowerLabels,
        datasets: [{ label: 'Borrows', data: data.topBorrowerValues, backgroundColor: '#27ae60' }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  if (data.fineValues[0] > 0 || data.fineValues[1] > 0) {
    new Chart(document.getElementById('finesChart'), {
      type: 'doughnut',
      data: {
        labels: ['Fines paid', 'Fines unpaid'],
        datasets: [{
          data: data.fineValues,
          backgroundColor: ['#16a085', '#e67e22'],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
      }
    });
  }
})();
</script>
<?php endif; ?>
