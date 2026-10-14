$(document).ready(function () {
    const points = $('#loyalty-points');
    const rewards = $('#loyalty-rewards');
    const nextReward = $('#loyalty-next-reward');
    const progress = $('#loyalty-progress');
    const activity = $('#loyalty-activity');
    const redeemButton = $('#loyalty-redeem');
    const rewardCode = $('#loyalty-reward-code');

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function loadSummary() {
        $.ajax({
            url: `${base_url}/api/Loyalty/summary`,
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            const data = response.data || {};
            const balance = Number(data.points || 0);
            const canRedeem = balance >= 10;

            points.text(balance);
            rewards.text(Number(data.rewards_available || 0));
            nextReward.text(canRedeem ? 0 : 10 - balance);
            progress.css('width', `${canRedeem ? 100 : balance * 10}%`);
            redeemButton.prop('disabled', !canRedeem);

            const rows = data.activity || [];
            if (!rows.length) {
                activity.html('<tr><td colspan="4" class="text-center text-muted">No loyalty activity yet. Place a kiosk order while signed in to start earning points.</td></tr>');
                return;
            }

            activity.html(rows.map(function (item) {
                const pointValue = Number(item.points || 0);
                const pointLabel = pointValue > 0 ? `+${pointValue}` : String(pointValue);
                return `<tr>
                    <td>${escapeHtml(item.description)}</td>
                    <td>${escapeHtml(item.activity_date)}</td>
                    <td>${escapeHtml(pointLabel)}</td>
                    <td>${escapeHtml(item.reward_code || '—')}</td>
                </tr>`;
            }).join(''));
        }).fail(handleAjaxError);
    }

    redeemButton.on('click', function () {
        redeemButton.prop('disabled', true);

        $.ajax({
            url: `${base_url}/api/Loyalty/redeem`,
            type: 'POST',
            dataType: 'json'
        }).done(function (response) {
            if (response.status !== 'success') {
                msgBox('error', response.message || 'Unable to redeem reward.');
                return;
            }

            const code = response.data.reward_code;
            rewardCode.removeClass('d-none').html(
                `Free coffee redeemed! Show this code to the cashier: <strong>${escapeHtml(code)}</strong>`
            );
            msgBox('success', response.message);
            loadSummary();
        }).fail(function (xhr) {
            handleAjaxError(xhr);
            redeemButton.prop('disabled', false);
        });
    });

    loadSummary();
});
