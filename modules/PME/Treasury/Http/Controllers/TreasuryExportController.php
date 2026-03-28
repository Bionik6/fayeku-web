<?php

namespace Modules\PME\Treasury\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PME\Treasury\Services\TreasuryService;

class TreasuryExportController extends Controller
{
    public function __invoke(Request $request, TreasuryService $treasuryService)
    {
        $company = $request->user()?->companies()->where('type', 'sme')->first();

        abort_unless($company, 403);

        $period = $request->string('period')->toString();
        $dashboard = $treasuryService->dashboard($company, $period);
        $filename = sprintf(
            'tresorerie-encaissements-%s.csv',
            now()->format('Ymd-His')
        );

        return response()->streamDownload(function () use ($dashboard): void {
            $stream = fopen('php://output', 'w');

            fputcsv($stream, [
                'document',
                'client',
                'montant_ttc',
                'reste_a_encaisser',
                'echeance',
                'retard_actuel',
                'niveau_de_confiance',
                'confiance_pct',
                'entree_estimee',
                'date_entree_estimee',
                'statut',
            ]);

            foreach ($dashboard['rows'] as $row) {
                fputcsv($stream, [
                    $row['document'],
                    $row['client_name'],
                    $row['total'],
                    $row['remaining'],
                    $row['due_at_label'],
                    $row['delay_label'],
                    $row['confidence_label'],
                    $row['confidence_score'],
                    $row['estimated_amount'],
                    $row['estimated_date_label'],
                    $row['status_label'],
                ]);
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
