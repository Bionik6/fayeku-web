@include('pdf.document', [
    'doc' => $document,
    'type' => $document->isProforma() ? 'proforma' : 'quote',
    'logoBase64' => $logoBase64,
])
