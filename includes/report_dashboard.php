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

<div class="report-kpi-grid">
  <div class="kpi-card" style="--accent:#c9a84c">
    <span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 5.5v16M8 7h8M8 11h8"/></svg></span>
    <div class="stat-value"><?= (int)$k['borrows'] ?></div>
    <div class="stat-label">Borrows</div>
  </div>
  <div class="kpi-card" style="--accent:#27ae60">
    <span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 7v5h-5M4.5 10a7.5 7.5 0 0 1 13-3L20 12M4 17v-5h5M19.5 14a7.5 7.5 0 0 1-13 3L4 12"/></svg></span>
    <div class="stat-value"><?= (int)$k['returns'] ?></div>
    <div class="stat-label">Returns</div>
  </div>
  <div class="kpi-card" style="--accent:#e74c3c">
    <span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 1.5M9 2h6M12 2v3"/></svg></span>
    <div class="stat-value"><?= (int)$k['overdue'] ?></div>
    <div class="stat-label">Overdue at range end</div>
  </div>
  <div class="kpi-card" style="--accent:#16a085">
    <span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6zM15 3v5h4M9 12h7M9 16h7M4 7v14"/></svg></span>
    <div class="stat-value">₱<?= number_format((float)$k['fines_paid'], 2) ?></div>
    <div class="stat-label">Fines paid (returns in range)</div>
  </div>
  <div class="kpi-card" style="--accent:#e67e22">
    <span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg></span>
    <div class="stat-value">₱<?= number_format((float)$k['fines_unpaid'], 2) ?></div>
    <div class="stat-label">Fines unpaid (returns in range)</div>
  </div>
  <div class="kpi-card" style="--accent:#2980b9">
    <span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1zM16 5a3 3 0 0 1 0 6M18 14a5 5 0 0 1 3 4.5V20h-4"/></svg></span>
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
