<?php
require_once dirname(__DIR__).'/config.php';
require_admin();
$conn = db();
$pageTitle = 'Payment History';

$rows = $conn->query("SELECT p.id, p.amount, p.payment_method, p.paid_at,
        CASE WHEN p.reference_number LIKE 'TXN-%' THEN p.reference_number
            ELSE 'TXN-' || TO_CHAR(p.paid_at, 'YYYYMMDD') || '-' || UPPER(SUBSTRING(MD5(p.id::text || '|' || p.paid_at::text), 1, 6))
        END AS automatic_reference,
    COALESCE(au.full_name, su.full_name, 'System') AS recorded_by_name,
    s.student_no, s.first_name, s.last_name, e.event_name, f.reason, f.amount AS fine_amount, f.status AS fine_status
    FROM payments p
    JOIN fines f ON f.id = p.fine_id
    JOIN students s ON s.id = f.student_id
    JOIN events e ON e.id = f.event_id
    LEFT JOIN admin_users au ON au.id = p.recorded_by
    LEFT JOIN ssc_users su ON su.id = p.recorded_by
    ORDER BY p.paid_at DESC, p.id DESC");

$totalAmount = 0.0;
$paymentCount = 0;
$methodTotals = [];
$history = [];
while ($row = $rows->fetch_assoc()) {
    $row['amount'] = (float) $row['amount'];
    $totalAmount += $row['amount'];
    $paymentCount++;
    $method = $row['payment_method'];
    $methodTotals[$method] = ($methodTotals[$method] ?? 0) + $row['amount'];
    $history[] = $row;
}

ob_start();
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="text-xs font-bold uppercase tracking-[0.2em] text-indigo-600">Finance records</div>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Payment history</h1>
            <p class="mt-2 text-sm text-slate-500">Review every recorded payment and its related student event.</p>
        </div>
        <a href="payments.php" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500">Record payment</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Total collected</div>
            <div class="mt-3 text-3xl font-black text-emerald-600">₱<?php echo number_format($totalAmount, 2); ?></div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Transactions</div>
            <div class="mt-3 text-3xl font-black text-slate-900"><?php echo number_format($paymentCount); ?></div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Payment methods</div>
            <div class="mt-3 flex flex-wrap gap-2">
                <?php foreach ($methodTotals as $method => $total): ?>
                    <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700"><?php echo h($method); ?> · ₱<?php echo number_format($total, 2); ?></span>
                <?php endforeach; ?>
                <?php if (!$methodTotals): ?><span class="text-sm text-slate-500">No payments recorded</span><?php endif; ?>
            </div>
        </div>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-5">
            <div class="text-lg font-semibold text-slate-900">Transaction log</div>
            <label class="relative w-full sm:w-80">
                <span class="sr-only">Search payment history</span>
                <input id="paymentHistorySearch" type="search" placeholder="Search student, event, method..." autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100">
            </label>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Student</th>
                        <th class="px-4 py-3">Event / reason</th>
                        <th class="px-4 py-3">Method</th>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Payment status</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3">Recorded by</th>
                    </tr>
                </thead>
                <tbody id="paymentHistoryRows" class="divide-y divide-slate-100">
                    <?php if ($history): foreach ($history as $row): ?>
                        <tr class="payment-history-row align-top hover:bg-slate-50" data-search="<?php echo h(strtolower(implode(' ', [$row['student_no'], $row['last_name'], $row['first_name'], $row['event_name'], $row['reason'], $row['payment_method'], $row['automatic_reference']]))); ?>">
                            <td class="whitespace-nowrap px-4 py-4 text-slate-600"><?php echo h(date('M j, Y g:i A', strtotime($row['paid_at']))); ?></td>
                            <td class="px-4 py-4"><div class="font-semibold text-slate-900"><?php echo h($row['last_name'].', '.$row['first_name']); ?></div><div class="mt-1 text-xs text-slate-500">ID: <?php echo h($row['student_no']); ?></div></td>
                            <td class="px-4 py-4"><div class="font-medium text-slate-800"><?php echo h($row['event_name']); ?></div><div class="mt-1 text-xs text-slate-500"><?php echo h($row['reason']); ?></div></td>
                            <td class="px-4 py-4"><span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700"><?php echo h($row['payment_method']); ?></span></td>
                            <td class="px-4 py-4 font-mono text-xs text-slate-600"><?php echo h($row['automatic_reference']); ?></td>
                            <td class="whitespace-nowrap px-4 py-4"><?php if ($row['fine_status'] === 'PAID'): ?><span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Fully paid</span><?php else: ?><span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Down payment</span><?php endif; ?></td>
                            <td class="whitespace-nowrap px-4 py-4 text-right font-bold text-emerald-700">₱<?php echo number_format($row['amount'], 2); ?></td>
                            <td class="whitespace-nowrap px-4 py-4 text-slate-600"><?php echo h($row['recorded_by_name']); ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="8" class="px-4 py-12 text-center text-slate-500">No payment history yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<script>
(function () {
    var search = document.getElementById('paymentHistorySearch');
    if (!search) return;
    search.addEventListener('input', function () {
        var term = search.value.trim().toLowerCase();
        document.querySelectorAll('.payment-history-row').forEach(function (row) {
            row.hidden = term !== '' && !row.dataset.search.includes(term);
        });
    });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__.'/_layout.php';
