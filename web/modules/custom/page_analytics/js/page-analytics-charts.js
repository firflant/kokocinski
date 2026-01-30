/**
 * @file
 * Initializes Chart.js line charts for page analytics report.
 */

(function (Drupal, drupalSettings, once) {
  "use strict";

  function initCharts(context) {
    const canvases = once(
      "page-analytics-chart",
      ".page-analytics-chart",
      context,
    );
    if (typeof Chart === "undefined" || !canvases.length) {
      return;
    }

    canvases.forEach((canvas) => {
      const labels = JSON.parse(canvas.getAttribute("data-labels") || "[]");
      const values = JSON.parse(canvas.getAttribute("data-values") || "[]");

      if (labels.length === 0 || values.length === 0) {
        return;
      }

      new Chart(canvas, {
        type: "line",
        data: {
          labels,
          datasets: [
            {
              label: Drupal.t("Views"),
              data: values,
              borderColor: "rgb(0, 102, 204)",
              backgroundColor: "rgba(0, 102, 204, 0.1)",
              fill: true,
              tension: 0.2,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          plugins: {
            legend: {
              display: false,
            },
          },
          scales: {
            x: {
              display: false,
            },
            y: {
              display: true,
              beginAtZero: true,
              ticks: {
                stepSize: 1,
              },
            },
          },
        },
      });
    });
  }

  Drupal.behaviors.pageAnalyticsCharts = {
    attach(context) {
      initCharts(context);
    },
  };
})(Drupal, drupalSettings, once);
