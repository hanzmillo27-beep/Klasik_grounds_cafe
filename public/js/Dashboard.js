$(document).ready(function () {
    var canvas = document.getElementById('weeklyNetProfitChart');
    var chartData = window.weeklyNetProfitData;

    if (!canvas || !chartData || typeof Chart === 'undefined') {
        return;
    }

    function peso(value, decimals) {
        return '₱' + Number(value || 0).toLocaleString('en-PH', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Net Profit',
                data: chartData.values,
                borderColor: '#243e32',
                backgroundColor: 'rgba(36, 62, 50, 0.12)',
                pointBackgroundColor: '#243e32',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                borderWidth: 3,
                lineTension: 0.25,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                display: false
            },
            tooltips: {
                callbacks: {
                    label: function (tooltipItem) {
                        return 'Net Profit: ' + peso(tooltipItem.yLabel, 2);
                    }
                }
            },
            scales: {
                xAxes: [{
                    gridLines: {
                        display: false
                    }
                }],
                yAxes: [{
                    ticks: {
                        callback: function (value) {
                            return peso(value, 0);
                        }
                    }
                }]
            }
        }
    });
});
