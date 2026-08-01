<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$report = isset($report) ? $report : null;
$preview = !empty($preview);
if (!$report instanceof ExportReportDefinition) {
	return;
}
$this->load->helper('export');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo htmlspecialchars($report->title, ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($report->shop_name, ENT_QUOTES, 'UTF-8'); ?></title>
	<style>
		* { box-sizing: border-box; }
		body {
			margin: 0;
			font-family: DejaVu Sans, Arial, sans-serif;
			font-size: 11px;
			color: #111827;
			background: #ffffff;
			line-height: 1.45;
		}
		.report-wrap { padding: 0; }
		.report-header {
			border-bottom: 2px solid #1f2937;
			padding-bottom: 12px;
			margin-bottom: 16px;
		}
		.report-brand {
			font-size: 18px;
			font-weight: 700;
			color: #1f2937;
			letter-spacing: 0.04em;
			text-transform: uppercase;
		}
		.report-title {
			font-size: 16px;
			font-weight: 700;
			margin: 6px 0 2px;
		}
		.report-subtitle {
			color: #6b7280;
			margin: 0 0 8px;
		}
		.report-meta {
			color: #6b7280;
			font-size: 10px;
		}
		.report-filters {
			background: #f9fafb;
			border: 1px solid #e5e7eb;
			padding: 10px 12px;
			margin-bottom: 14px;
		}
		.report-filters__title {
			font-weight: 700;
			margin-bottom: 6px;
			font-size: 10px;
			text-transform: uppercase;
			letter-spacing: 0.06em;
			color: #6b7280;
		}
		.report-filters dl {
			margin: 0;
		}
		.report-filters dt {
			margin: 0;
			font-weight: 600;
			color: #374151;
			display: inline-block;
			width: 120px;
			vertical-align: top;
		}
		.report-filters dd {
			margin: 0 0 4px;
			display: inline-block;
			width: calc(100% - 130px);
			vertical-align: top;
		}
		.report-cards {
			margin-bottom: 14px;
			width: 100%;
			border-collapse: separate;
			border-spacing: 10px 0;
		}
		.report-card {
			border: 1px solid #e5e7eb;
			padding: 10px 12px;
			background: #fff;
			vertical-align: top;
		}
		.report-card__label {
			font-size: 10px;
			color: #6b7280;
			text-transform: uppercase;
			letter-spacing: 0.04em;
		}
		.report-card__value {
			font-size: 15px;
			font-weight: 700;
			margin-top: 4px;
			color: #1f2937;
		}
		table.report-table {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 12px;
		}
		table.report-table th,
		table.report-table td {
			border: 1px solid #e5e7eb;
			padding: 6px 8px;
			vertical-align: top;
		}
		table.report-table th {
			background: #1f2937;
			color: #fff;
			font-weight: 700;
			font-size: 10px;
		}
		table.report-table tbody tr:nth-child(even) td {
			background: #fcfcfd;
		}
		.text-left { text-align: left; }
		.text-center { text-align: center; }
		.text-right { text-align: right; }
		.report-totals {
			margin-top: 8px;
			padding-top: 8px;
			border-top: 1px solid #e5e7eb;
		}
		.report-totals__row {
			padding: 3px 0;
			font-weight: 600;
			width: 100%;
		}
		.report-totals__row span:first-child {
			float: left;
		}
		.report-totals__row span:last-child {
			float: right;
		}
		.report-totals__row::after {
			content: '';
			display: block;
			clear: both;
		}
		.report-notes {
			margin-top: 12px;
			font-size: 10px;
			color: #6b7280;
		}
		.report-footer {
			margin-top: 18px;
			padding-top: 10px;
			border-top: 1px solid #e5e7eb;
			font-size: 10px;
			color: #6b7280;
			width: 100%;
		}
		.report-footer span:first-child {
			float: left;
		}
		.report-footer span:last-child {
			float: right;
		}
		.report-footer::after {
			content: '';
			display: block;
			clear: both;
		}
		<?php if ($preview) { ?>
		body { background: #eef2f7; padding: 24px; }
		.report-wrap {
			max-width: 1100px;
			margin: 0 auto;
			background: #fff;
			padding: 28px 32px;
			box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
			border-radius: 12px;
		}
		.report-filters {
			border-radius: 8px;
		}
		.report-card {
			border-radius: 8px;
		}
		.report-preview-actions {
			max-width: 1100px;
			margin: 0 auto 12px;
			text-align: right;
		}
		.report-preview-actions button {
			border: none;
			background: #1f2937;
			color: #fff;
			padding: 8px 14px;
			border-radius: 8px;
			cursor: pointer;
			font-size: 13px;
		}
		@media print {
			body { background: #fff; padding: 0; }
			.report-wrap { box-shadow: none; border-radius: 0; padding: 0; }
			.report-preview-actions { display: none !important; }
		}
		<?php } ?>
	</style>
</head>
<body>
<?php if ($preview) { ?>
<div class="report-preview-actions">
	<button type="button" onclick="window.print()"><span>&#128424;</span> In báo cáo</button>
</div>
<?php } ?>
<div class="report-wrap">
	<header class="report-header">
		<div class="report-brand"><?php echo htmlspecialchars($report->shop_name, ENT_QUOTES, 'UTF-8'); ?></div>
		<h1 class="report-title"><?php echo htmlspecialchars($report->title, ENT_QUOTES, 'UTF-8'); ?></h1>
		<?php if ($report->subtitle !== '') { ?>
			<p class="report-subtitle"><?php echo htmlspecialchars($report->subtitle, ENT_QUOTES, 'UTF-8'); ?></p>
		<?php } ?>
		<div class="report-meta">
			Xuất lúc <?php echo htmlspecialchars($report->generated_at, ENT_QUOTES, 'UTF-8'); ?>
			· Phạm vi: <?php echo htmlspecialchars($report->scope_label, ENT_QUOTES, 'UTF-8'); ?>
		</div>
	</header>

	<?php if (!empty($report->filter_lines)) { ?>
	<section class="report-filters">
		<div class="report-filters__title">Bộ lọc áp dụng</div>
		<dl>
			<?php foreach ($report->filter_lines as $line) { ?>
				<dt><?php echo htmlspecialchars($line['label'], ENT_QUOTES, 'UTF-8'); ?></dt>
				<dd><?php echo htmlspecialchars($line['value'], ENT_QUOTES, 'UTF-8'); ?></dd>
			<?php } ?>
		</dl>
	</section>
	<?php } ?>

	<?php if (!empty($report->summary_cards)) { ?>
	<table class="report-cards" role="presentation">
		<tr>
		<?php foreach ($report->summary_cards as $card) { ?>
			<td class="report-card">
				<div class="report-card__label"><?php echo htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8'); ?></div>
				<div class="report-card__value"><?php echo admin_export_format_cell($card['value'], $card['type']); ?></div>
			</td>
		<?php } ?>
		</tr>
	</table>
	<?php } ?>

	<?php if (!empty($report->columns)) { ?>
	<table class="report-table">
		<thead>
			<tr>
				<?php foreach ($report->columns as $col) {
					$align = isset($col['align']) ? $col['align'] : 'left';
				?>
					<th class="text-<?php echo htmlspecialchars($align, ENT_QUOTES, 'UTF-8'); ?>">
						<?php echo htmlspecialchars($col['label'], ENT_QUOTES, 'UTF-8'); ?>
					</th>
				<?php } ?>
			</tr>
		</thead>
		<tbody>
			<?php if (!empty($report->rows)) { ?>
				<?php foreach ($report->rows as $row) { ?>
					<tr>
						<?php foreach ($report->columns as $col) {
							$key = $col['key'];
							$align = isset($col['align']) ? $col['align'] : 'left';
							$type = isset($col['type']) ? $col['type'] : 'text';
							$value = isset($row[$key]) ? $row[$key] : '';
						?>
							<td class="text-<?php echo htmlspecialchars($align, ENT_QUOTES, 'UTF-8'); ?>">
								<?php echo admin_export_format_cell($value, $type); ?>
							</td>
						<?php } ?>
					</tr>
				<?php } ?>
			<?php } else { ?>
				<tr>
					<td colspan="<?php echo count($report->columns); ?>" class="text-center">Không có dữ liệu phù hợp.</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
	<?php } ?>

	<?php if (!empty($report->totals)) { ?>
	<section class="report-totals">
		<?php foreach ($report->totals as $total) { ?>
			<div class="report-totals__row">
				<span><?php echo htmlspecialchars($total['label'], ENT_QUOTES, 'UTF-8'); ?></span>
				<span><?php echo admin_export_format_cell($total['value'], $total['type']); ?></span>
			</div>
		<?php } ?>
	</section>
	<?php } ?>

	<?php if (!empty($report->notes)) { ?>
	<div class="report-notes">
		<?php foreach ($report->notes as $note) { ?>
			<div>• <?php echo htmlspecialchars($note, ENT_QUOTES, 'UTF-8'); ?></div>
		<?php } ?>
	</div>
	<?php } ?>

	<footer class="report-footer">
		<span><?php echo htmlspecialchars($report->shop_name, ENT_QUOTES, 'UTF-8'); ?> · Báo cáo quản trị</span>
		<span>Trang {PAGENO} / {nbpg}</span>
	</footer>
</div>
</body>
</html>
