<div class="row justify-content-center">
    <div class="col-12 col-lg-9">
        <div class="card card-primary card-outline">
            <div class="card-header d-flex flex-wrap align-items-center">
                <h3 class="card-title">Your Cafe Rewards</h3>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted">Points balance</div>
                            <div id="loyalty-points" class="display-4 font-weight-bold">—</div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted">Free coffees available</div>
                            <div id="loyalty-rewards" class="display-4 font-weight-bold">—</div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted">Points to next coffee</div>
                            <div id="loyalty-next-reward" class="display-4 font-weight-bold">—</div>
                        </div>
                    </div>
                </div>

                <div class="progress mb-3" style="height: 12px;">
                    <div id="loyalty-progress" class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
                </div>
                <p class="text-muted text-center">Earn 1 point for a completed purchase made through your account. Only one point can be earned per day. Redeem 10 points for a free coffee.</p>

                <div class="text-center mb-3">
                    <button id="loyalty-redeem" class="btn btn-success btn-lg" type="button" disabled>
                        Redeem a Free Coffee
                    </button>
                </div>
                <div id="loyalty-reward-code" class="alert alert-success text-center d-none" role="status"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center"><h3 class="card-title">Recent Activity</h3></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr><th>Activity</th><th>Date</th><th>Points</th><th>Reward code</th></tr>
                        </thead>
                        <tbody id="loyalty-activity">
                            <tr><td colspan="4" class="text-center text-muted">Loading activity…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
