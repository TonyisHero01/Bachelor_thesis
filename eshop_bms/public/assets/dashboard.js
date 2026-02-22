const { sales, topProducts, topCustomers } = window.dashboardData;

/**
 * Creates the sales line chart.
 */
function renderSalesChart() {
    const el = document.getElementById('salesChart');
    if (!el) return;

    new Chart(el, {
        type: 'line',
        data: {
            labels: sales.map((d) => d.date),
            datasets: [
                {
                    label: 'Sales (CZK)',
                    data: sales.map((d) => parseFloat(d.total)),
                    fill: true,
                    tension: 0.1,
                },
            ],
        },
    });
}

/**
 * Creates the top products bar chart.
 */
function renderTopProductsChart() {
    const el = document.getElementById('topProductsChart');
    if (!el) return;

    new Chart(el, {
        type: 'bar',
        data: {
            labels: topProducts.map((d) => d.product_name),
            datasets: [
                {
                    label: 'Quantity Sold',
                    data: topProducts.map((d) => parseFloat(d.total_quantity)),
                },
            ],
        },
    });
}

/**
 * Creates the top customers bar chart.
 */
function renderTopCustomersChart() {
    const el = document.getElementById('topCustomersChart');
    if (!el) return;

    new Chart(el, {
        type: 'bar',
        data: {
            labels: topCustomers.map((d) => d.email),
            datasets: [
                {
                    label: 'Total Spent (CZK)',
                    data: topCustomers.map((d) => parseFloat(d.total_spent)),
                },
            ],
        },
    });
}

renderSalesChart();
renderTopProductsChart();
renderTopCustomersChart();