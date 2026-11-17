<?php
if (!defined('SITE_URL')) {
    http_response_code(403);
    exit('Forbidden');
}
?>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Student</th><th>Book</th><th>Due Date</th><th>Days Overdue</th><th>Computed Fine</th><th>Action</th></tr></thead>
      <tbody>
      <?php if ($totalRecords === 0): ?>
        <tr><td colspan="6" style="text-align:center;color:#888;padding:24px">No overdue loans.</td></tr>
      <?php else: while ($row = $records->fetch_assoc()): $overdue = overdueDetails($row); ?>
        <tr>
          <td><?= htmlspecialchars($row['full_name']) ?><br><span style="font-size:11px;color:#888"><?= htmlspecialchars($row['student_id'] ?? '') ?></span></td>
          <td><?= htmlspecialchars($row['title']) ?></td>
          <td><?= htmlspecialchars($row['due_date']) ?></td>
          <td><?= $overdue['days'] ?></td>
          <td>PHP <?= number_format($overdue['fine'], 2) ?></td>
          <td><a class="btn btn-sm btn-gold" href="<?= SITE_URL ?>/librarian/return_book.php?borrow_id=<?= (int)$row['id'] ?>">Go to return</a></td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalRecords > 0): ?><?= renderPagination($pager) ?><?php endif; ?>
</div>
