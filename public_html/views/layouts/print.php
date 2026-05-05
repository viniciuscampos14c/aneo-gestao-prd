<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($title ?? 'Impressao') . ' | ' . config('app.name')); ?></title>
    <style>
        @page {
            size: A4 landscape;
            margin: 12mm;
        }

        :root {
            --ink: #0f172a;
            --ink-soft: #334155;
            --line: #0f172a;
            --header: #17325c;
            --month: #dfe8f6;
            --zebra: #f8fafc;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #fff;
            color: var(--ink);
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .print-shell { width: 100%; }

        .print-hero {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 18px;
            align-items: stretch;
            margin-bottom: 16px;
            border: 2px solid var(--line);
        }

        .print-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f766e, #17325c);
            color: #fff;
            font-weight: 800;
            font-size: 28px;
            letter-spacing: 0.12em;
        }

        .print-heading {
            padding: 14px 18px;
            background: linear-gradient(180deg, #f8fbff, #edf4ff);
        }

        .print-kicker {
            margin: 0 0 4px;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .print-heading h1 {
            margin: 0 0 10px;
            font-size: 34px;
            line-height: 1.08;
        }

        .print-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 18px;
            font-size: 12px;
            color: var(--ink-soft);
        }

        .print-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 13px;
        }

        .print-table th,
        .print-table td {
            border: 2px solid var(--line);
            padding: 8px 10px;
            vertical-align: middle;
        }

        .print-table thead th {
            background: var(--header);
            color: #fff;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            text-align: center;
        }

        .print-table tbody tr:nth-child(even) td:not(.month-cell) {
            background: var(--zebra);
        }

        .print-table td {
            font-weight: 600;
        }

        .month-cell {
            width: 120px;
            background: var(--month);
            color: #24406f;
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            text-align: center;
        }

        .date-cell {
            width: 120px;
            text-align: center;
        }

        .date-range {
            font-size: 18px;
            font-weight: 800;
        }

        .date-sub {
            margin-top: 3px;
            font-size: 11px;
            font-weight: 500;
            color: var(--ink-soft);
        }

        @media print {
            .print-hero,
            .print-table tr,
            .print-table td,
            .print-table th {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <?= $content; ?>
</body>
</html>
