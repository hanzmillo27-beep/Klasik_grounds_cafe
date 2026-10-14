<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">Weekly Net Profit</h3>
                    <div class="card-tools text-muted">
                        <?= date('M d', strtotime('monday this week')) ?>
                        – <?= date('M d, Y') ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="weekly-profit-chart-wrap">
                        <canvas id="weeklyNetProfitChart" aria-label="Weekly net profit line graph" role="img"></canvas>
                    </div>
                    <script>
                        window.weeklyNetProfitData = <?= json_encode(
                            $weeklyNetProfit,
                            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
                        ) ?>;
                    </script>
                </div>
            </div>
        </div>
    </div>

    <div class="row">

        <div class="col-lg-4 col-md-6">

            <div class="small-box bg-primary">

                <div class="inner">

                    <h3>
                        ₱<?= number_format(
                            $expenseSummary['today'],
                            2
                        ) ?>
                    </h3>

                    <p>
                        Today's Expenses
                    </p>

                </div>

                <div class="icon">
                    <i class="fas fa-calendar-day"></i>
                </div>

            </div>

        </div>


        <div class="col-lg-4 col-md-6">

            <div class="small-box bg-info">

                <div class="inner">

                    <h3>
                        ₱<?= number_format(
                            $expenseSummary['week'],
                            2
                        ) ?>
                    </h3>

                    <p>
                        This Week's Expenses
                    </p>

                </div>

                <div class="icon">
                    <i class="fas fa-calendar-week"></i>
                </div>

            </div>

        </div>


        <div class="col-lg-4 col-md-6">

            <div class="small-box bg-success">

                <div class="inner">

                    <h3>
                        ₱<?= number_format(
                            $expenseSummary['month'],
                            2
                        ) ?>
                    </h3>

                    <p>
                        This Month's Expenses
                    </p>

                </div>

                <div class="icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>

            </div>

        </div>

    </div>


    <div class="row">

        <div class="col-lg-6 col-md-6">

            <div class="small-box bg-secondary">

                <div class="inner">

                    <h3>
                        <?= (int) $inventorySummary->total_items ?>
                    </h3>

                    <p>
                        Inventory Items
                    </p>

                </div>

                <div class="icon">
                    <i class="fas fa-boxes"></i>
                </div>

            </div>

        </div>


        <div class="col-lg-6 col-md-6">

            <div class="small-box bg-warning">

                <div class="inner">

                    <h3>
                        <?= (int) $inventorySummary->low_stock ?>
                    </h3>

                    <p>
                        Low Stock Items
                    </p>

                </div>

                <div class="icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>

            </div>

        </div>

    </div>


    <div class="row">

        <div class="col-lg-5">

            <div class="card card-primary card-outline">

                <div class="card-header">

                    <h3 class="card-title">
                        Recent Daily Expenses
                    </h3>

                    <div class="card-tools">

                        <a
                            href="<?= BASE_URL ?>/DailyExpenses"
                            class="btn btn-sm btn-primary"
                        >
                            View All
                        </a>

                    </div>

                </div>


                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table class="table table-hover mb-0">

                            <thead>

                                <tr>

                                    <th>
                                        Expense
                                    </th>

                                    <th>
                                        Amount
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php if (empty($recentExpenses)): ?>

                                    <tr>

                                        <td
                                            colspan="3"
                                            class="text-center text-muted py-4"
                                        >
                                            No expenses recorded.
                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($recentExpenses as $expense): ?>

                                        <tr>

                                            <td>
                                                <?= htmlspecialchars(
                                                    $expense->expense_name
                                                ) ?>
                                            </td>

                                            <td>
                                                ₱<?= number_format(
                                                    $expense->amount,
                                                    2
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= date(
                                                    'M d, Y',
                                                    strtotime(
                                                        $expense->expense_date
                                                    )
                                                ) ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>


        <div class="col-lg-7">

            <div class="card card-primary card-outline">

                <div class="card-header">

                    <h3 class="card-title">
                        Inventory Status
                    </h3>

                    <div class="card-tools">

                        <a
                            href="<?= BASE_URL ?>/Inventory"
                            class="btn btn-sm btn-primary"
                        >
                            View All
                        </a>

                    </div>

                </div>


                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table class="table table-hover mb-0">

                            <thead>

                                <tr>

                                    <th>
                                        Item
                                    </th>

                                    <th>
                                        Amount
                                    </th>

                                    <th>
                                        Unit
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php if (empty($inventory)): ?>

                                    <tr>

                                        <td
                                            colspan="4"
                                            class="text-center text-muted py-4"
                                        >
                                            No inventory items.
                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($inventory as $item): ?>

                                        <tr>

                                            <td>
                                                <?= htmlspecialchars(
                                                    $item->inv_name
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= number_format(
                                                    $item->amount,
                                                    2
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars(
                                                    $item->unit
                                                ) ?>
                                            </td>

                                            <td>

                                                <?php if (
                                                    $item->stock_status ===
                                                    'Low Stock'
                                                ): ?>

                                                    <span class="badge badge-warning">
                                                        Low Stock
                                                    </span>

                                                <?php else: ?>

                                                    <span class="badge badge-success">
                                                        Available
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>
