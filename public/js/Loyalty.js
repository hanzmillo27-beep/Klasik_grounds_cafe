$(document).ready(function () {
    const points = $('#loyalty-points');
    const rewards = $('#loyalty-rewards');
    const nextReward = $('#loyalty-next-reward');
    const progress = $('#loyalty-progress');
    const activity = $('#loyalty-activity');
    const redeemButton = $('#loyalty-redeem');
    const rewardCode = $('#loyalty-reward-code');
    let balance = 0;
    let redeemPending = false;

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function updateRedeemButton() {
        redeemButton.prop('disabled', redeemPending || balance < 10);
    }

    function showReadyCodes(codes) {
        rewardCode.toggleClass('d-none', !codes.length).html(codes.length
            ? 'Reward issued. Show a ready code to the cashier to choose your free coffee: '
                + codes.map(code => `<strong>${escapeHtml(code)}</strong>`).join(', ')
            : '');
    }

    function loadSummary() {
        return $.ajax({
            url: `${base_url}/api/Loyalty/summary`,
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            const data = response.data || {};
            balance = Number(data.points || 0);
            const canRedeem = balance >= 10;

            points.text(balance);
            rewards.text(Number(data.rewards_available || 0));
            nextReward.text(canRedeem ? 0 : 10 - balance);
            progress.css('width', `${canRedeem ? 100 : balance * 10}%`);
            updateRedeemButton();

            const rows = data.activity || [];
            showReadyCodes(rows.filter(item => item.status === 'available' && item.reward_code)
                .map(item => item.reward_code));
            if (!rows.length) {
                activity.html('<tr><td colspan="5" class="text-center text-muted">No loyalty activity yet. Place a kiosk order while signed in to start earning points.</td></tr>');
                return;
            }

            activity.html(rows.map(function (item) {
                const pointValue = Number(item.points || 0);
                const pointLabel = pointValue > 0 ? `+${pointValue}` : String(pointValue);
                const status = item.status === 'available' ? 'Ready for cashier' : item.status === 'used' ? 'Used' : '\u2014';
                return `<tr>
                    <td>${escapeHtml(item.description)}</td>
                    <td>${escapeHtml(item.activity_date)}</td>
                    <td>${escapeHtml(pointLabel)}</td>
                    <td>${escapeHtml(item.reward_code || '\u2014')}</td>
                    <td>${escapeHtml(status)}</td>
                </tr>`;
            }).join(''));
        }).fail(handleAjaxError);
    }

    redeemButton.on('click', function () {
        if (redeemPending || balance < 10) return;
        redeemPending = true;
        updateRedeemButton();

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
            balance = Number(response.data.points);
            showReadyCodes([code]);
            msgBox('success', 'Reward issued. Show the code to the cashier to choose your free coffee.');
            loadSummary();
        }).fail(function (xhr) {
            handleAjaxError(xhr);
        }).always(function () {
            redeemPending = false;
            updateRedeemButton();
        });
    });

    loadSummary();
});
