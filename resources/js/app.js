import Chart from "chart.js/auto";

window.Chart = Chart;

function initializeCatalogValueCharts() {
    document
        .querySelectorAll("[data-catalog-value-chart]")
        .forEach((canvas) => {
            // Voorkom dat dezelfde grafiek meerdere keren wordt aangemaakt.
            if (Chart.getChart(canvas)) {
                return;
            }

            const labels = JSON.parse(canvas.dataset.chartLabels || "[]");
            const datasets = JSON.parse(canvas.dataset.chartDatasets || "[]");
            const currency = canvas.dataset.chartCurrency || "";

            const colors = ["#795548", "#b08968", "#5d4037"];

            const chartDatasets = datasets.map((dataset, index) => ({
                label: dataset.label,
                data: dataset.data,
                borderColor: colors[index % colors.length],
                backgroundColor: colors[index % colors.length],
                borderWidth: 2,
                tension: 0.2,
                pointRadius: 4,
                pointHoverRadius: 6,
                spanGaps: false,
            }));

            new Chart(canvas, {
                type: "line",

                data: {
                    labels,
                    datasets: chartDatasets,
                },

                options: {
                    responsive: true,
                    maintainAspectRatio: false,

                    interaction: {
                        mode: "index",
                        intersect: false,
                    },

                    plugins: {
                        legend: {
                            position: "bottom",
                            labels: {
                                color: "#27272a",
                                usePointStyle: true,
                                padding: 20,
                            },
                        },

                        tooltip: {
                            callbacks: {
                                label(context) {
                                    const value = context.parsed.y;

                                    if (value === null) {
                                        return context.dataset.label;
                                    }

                                    const formattedValue =
                                        new Intl.NumberFormat("nl-NL", {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2,
                                        }).format(value);

                                    return `${context.dataset.label}: ${currency} ${formattedValue}`;
                                },
                            },
                        },
                    },

                    scales: {
                        x: {
                            ticks: {
                                color: "#52525b",
                            },
                            grid: {
                                display: false,
                            },
                        },

                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: "#52525b",
                                callback(value) {
                                    return `${currency} ${value}`;
                                },
                            },
                        },
                    },
                },
            });
        });
}

document.addEventListener("DOMContentLoaded", initializeCatalogValueCharts);
document.addEventListener("livewire:navigated", initializeCatalogValueCharts);
