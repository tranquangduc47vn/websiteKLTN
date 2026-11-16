/**
 * Admin dashboard — filter khoảng ngày + Chart.js (doanh thu) + KPI loading skeleton
 */
(function () {
	'use strict';

	var customWrap = document.getElementById('admDashCustomRange');
	var periodInputs = document.querySelectorAll('.adm-dash-filter__pills input[name="period"]');
	var kpiRows = document.getElementById('admDashKpiRows');
	var filterForm = document.querySelector('.adm-dash-filter');

	function syncCustomRange() {
		if (!customWrap || !periodInputs.length) {
			return;
		}

		var showCustom = false;
		periodInputs.forEach(function (radio) {
			if (radio.checked && radio.value === 'custom') {
				showCustom = true;
			}
		});

		customWrap.hidden = !showCustom;

		if (showCustom) {
			var fromInput = customWrap.querySelector('input[name="from"]');
			if (fromInput) {
				fromInput.focus();
			}
		}
	}

	function showKpiLoading() {
		if (kpiRows) {
			kpiRows.classList.add('is-loading');
		}
	}

	if (customWrap && periodInputs.length) {
		periodInputs.forEach(function (radio) {
			radio.addEventListener('change', function () {
				syncCustomRange();
				if (radio.value !== 'custom') {
					showKpiLoading();
				}
			});
		});
		syncCustomRange();
	}

	if (filterForm) {
		filterForm.addEventListener('submit', showKpiLoading);
		filterForm.querySelectorAll('input[name="from"], input[name="to"]').forEach(function (input) {
			input.addEventListener('change', showKpiLoading);
		});
	}

	var cfg = window.admDashboardChart;
	if (!cfg || typeof Chart === 'undefined') {
		return;
	}

	var revCtx = document.getElementById('admChartRevenue');
	if (revCtx && revCtx.getContext) {
		new Chart(revCtx.getContext('2d')).Line({
			labels: cfg.labels || [],
			datasets: [{
				label: 'Doanh thu',
				fillColor: 'rgba(37, 99, 235, 0.12)',
				strokeColor: '#2563eb',
				pointColor: '#2563eb',
				pointStrokeColor: '#ffffff',
				pointHighlightFill: '#ffffff',
				pointHighlightStroke: '#2563eb',
				data: cfg.revenue || [],
			}],
		}, {
			responsive: true,
			maintainAspectRatio: false,
			animation: false,
			scaleBeginAtZero: true,
			bezierCurve: true,
			datasetFill: true,
			scaleShowGridLines: true,
			tooltipTemplate: '<%if (label){%><%=label%>: <%}%><%= value %> ₫',
		});
	}
})();
