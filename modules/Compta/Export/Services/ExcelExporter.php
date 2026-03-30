<?php

namespace Modules\Compta\Export\Services;

use Illuminate\Support\Collection;
use Modules\Compta\Export\Interfaces\AccountingExporterInterface;
use Modules\PME\Invoicing\Models\Invoice;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelExporter implements AccountingExporterInterface
{
    private const ACCOUNT_SALES = '710000';

    private const ACCOUNT_CLIENTS = '411000';

    private const ACCOUNT_VAT_COLLECTED = '445710';

    /** @param Collection<int, Invoice> $invoices */
    public function export(Collection $invoices): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Écritures comptables');

        $this->writeHeader($sheet);
        $row = 2;

        foreach ($invoices as $invoice) {
            $row = $this->writeInvoiceEntries($sheet, $invoice, $row);
        }

        $this->autoSizeColumns($sheet);

        $tempFile = tempnam(sys_get_temp_dir(), 'export_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    public function mimeType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function filename(string $period): string
    {
        return sprintf('export-comptable-%s-%s.xlsx', $period, now()->format('Ymd-His'));
    }

    private function writeHeader(Worksheet $sheet): void
    {
        $headers = [
            'Date pièce',
            'Code journal',
            'N° pièce',
            'N° compte',
            'Libellé écriture',
            'Débit',
            'Crédit',
            'Client',
            'Entreprise',
        ];

        foreach ($headers as $col => $header) {
            $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($col + 1).'1');
            $cell->setValue($header);
        }

        $headerRange = 'A1:I1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '024D4D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(28);
    }

    private function writeInvoiceEntries(
        Worksheet $sheet,
        Invoice $invoice,
        int $row,
    ): int {
        $date = $invoice->issued_at?->format('d/m/Y') ?? '';
        $journal = 'VE';
        $reference = $invoice->reference ?? '';
        $clientName = $invoice->client?->name ?? '';
        $companyName = $invoice->company?->name ?? '';
        $subtotal = $invoice->subtotal / 100;
        $taxAmount = $invoice->tax_amount / 100;
        $total = $invoice->total / 100;

        // Ligne 1 : Débit 411000 Clients (TTC)
        $this->writeRow($sheet, $row, [
            $date,
            $journal,
            $reference,
            self::ACCOUNT_CLIENTS,
            'Facture '.$reference.' - '.$clientName,
            $total,
            '',
            $clientName,
            $companyName,
        ]);
        $row++;

        // Ligne 2 : Crédit 710000 Ventes (HT)
        $this->writeRow($sheet, $row, [
            $date,
            $journal,
            $reference,
            self::ACCOUNT_SALES,
            'Facture '.$reference.' - '.$clientName,
            '',
            $subtotal,
            $clientName,
            $companyName,
        ]);
        $row++;

        // Ligne 3 : Crédit 445710 TVA collectée (si TVA > 0)
        if ($taxAmount > 0) {
            $this->writeRow($sheet, $row, [
                $date,
                $journal,
                $reference,
                self::ACCOUNT_VAT_COLLECTED,
                'TVA Facture '.$reference.' - '.$clientName,
                '',
                $taxAmount,
                $clientName,
                $companyName,
            ]);
            $row++;
        }

        return $row;
    }

    /** @param array<int, mixed> $values */
    private function writeRow(
        Worksheet $sheet,
        int $row,
        array $values,
    ): void {
        foreach ($values as $col => $value) {
            $colLetter = Coordinate::stringFromColumnIndex($col + 1);
            $cell = $sheet->getCell($colLetter.$row);

            // Force account codes (column D) as explicit text
            if ($colLetter === 'D') {
                $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
            } else {
                $cell->setValue($value);
            }
        }

        // Format montant columns (Débit = F, Crédit = G)
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

        if ($row % 2 === 0) {
            $sheet->getStyle("A{$row}:I{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFA']],
            ]);
        }
    }

    private function autoSizeColumns(Worksheet $sheet): void
    {
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
