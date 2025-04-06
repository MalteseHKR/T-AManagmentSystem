@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Recovery Codes</div>
                
                <div class="card-body">
                    <div class="alert alert-warning">
                        <strong>Important:</strong> Store these recovery codes in a secure location. If you lose your two-factor authentication device, you can use one of these codes to regain access to your account.
                    </div>
                    
                    <div class="bg-light p-3 mt-4 mb-4">
                        @foreach($recoveryCodes as $code)
                            <code class="d-block mb-2">{{ $code }}</code>
                        @endforeach
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('mfa.index') }}" class="btn btn-primary">
                            I've saved these codes
                        </a>
                        
                        <button class="btn btn-secondary" onclick="printRecoveryCodes()">
                            <i class="bi bi-printer"></i> Print
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function printRecoveryCodes() {
    const content = document.querySelector('.bg-light').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Garrison - Recovery Codes</title>
            <style>
                body { font-family: sans-serif; padding: 20px; }
                h1 { font-size: 18px; margin-bottom: 20px; }
                .code { font-family: monospace; margin-bottom: 10px; display: block; }
            </style>
        </head>
        <body>
            <h1>Recovery Codes - Keep these private and secure</h1>
            ${content}
            <p style="margin-top: 30px">These codes can be used once each to log in if you lose access to your authenticator app.</p>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
}
</script>
@endsection