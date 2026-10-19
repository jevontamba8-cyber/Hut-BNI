<?php
/**
 * BNI Anniversary Celebration
 * Download Excel / CSV Template for Importing Participants
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin(['superadmin', 'admin']);

$format = strtolower($_GET['format'] ?? 'xlsx');

// Sample demonstration rows
$headers = ['nik', 'nomor_undian', 'nama_lengkap', 'divisi', 'cabang'];
$sampleData = [
    ['3171012304900001', 'BNI-0001', 'Jevon Hot Asi Alexander Tamba', 'Divisi Teknologi Informasi', 'Kantor Pusat Grha BNI'],
    ['3201024508920002', 'BNI-0002', 'Siti Rahmawati', 'Divisi Human Capital', 'Kantor Wilayah Jakarta Senayan'],
    ['3578031201880003', 'BNI-0003', 'Ahmad Fauzi', 'Divisi Consumer Banking', 'Kantor Cabang Surabaya'],
    ['5171046709950004', 'BNI-0004', 'Ni Putu Ayu Saraswati', 'Divisi Risk Management', 'Kantor Wilayah Denpasar'],
    ['1271052011910005', 'BNI-0005', 'Budi Santoso', 'Divisi Treasury & Global Markets', 'Kantor Cabang Medan']
];

// Check if PhpSpreadsheet is available
$hasPhpSpreadsheet = class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class);

if ($format === 'xlsx' && $hasPhpSpreadsheet) {
    try {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Peserta BNI');

        // Header Styling
        $sheet->fromArray($headers, null, 'A1');
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F37021'] // BNI Orange
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
        ];
        $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Fill Sample Data with NIK explicitly formatted as TEXT (DataType::TYPE_STRING)
        $rowIdx = 2;
        foreach ($sampleData as $row) {
            $sheet->setCellValueExplicit('A' . $rowIdx, $row[0], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B' . $rowIdx, $row[1], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('C' . $rowIdx, $row[2]);
            $sheet->setCellValue('D' . $rowIdx, $row[3]);
            $sheet->setCellValue('E' . $rowIdx, $row[4]);
            $rowIdx++;
        }

        // Auto size columns
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="Template_Import_Peserta_BNI.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } catch (Exception $e) {
        error_log('Error generating XLSX template: ' . $e->getMessage());
        // Fall back to CSV
    }
}

// Fallback or Direct CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="Template_Import_Peserta_BNI.csv"');
$out = fopen('php://output', 'w');
// UTF-8 BOM for Excel compatibility
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
fputcsv($out, $headers);
foreach ($sampleData as $row) {
    fputcsv($out, $row);
}
fclose($out);
exit;
