@extends('layouts.app')

@section('styles')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8 col-md-10 col-sm-12">
        <div class="card mfa-card">
            <div class="card-header d-flex align-items-center">
                <i class="bi bi-key me-2"></i>
                <span>Recovery Codes</span>
            </div>
            
            <div class="card-body">
                <div class="alert alert-warning d-flex">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <div>
                        <strong>Important:</strong> Store these recovery codes in a secure location. If you lose your two-factor authentication device, you can use one of these codes to regain access to your account. Each code can only be used once.
                    </div>
                </div>
                
                <div class="recovery-codes-container mt-4 mb-4">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped text-center align-middle">
                            <thead class="table-primary">
                                <tr>
                                    <th scope="col">Recovery Code</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recoveryCodes as $code)
                                    <tr>
                                        <td><code>{{ $code }}</code></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary btn-copy" data-code="{{ $code }}" title="Copy to clipboard">
                                                <i class="fa fa-clipboard text-primary"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="action-buttons mt-4">
                    <div class="d-flex flex-column flex-md-row justify-content-center align-items-center gap-3 flex-wrap text-center">
                        <a href="{{ route('mfa.index') }}" class="btn btn-primary">
                            <i class="fa fa-check-circle me-1"></i> I've saved these codes
                        </a>
                
                        <button id="btn-copy-all" class="btn btn-info">
                            <i class="fa fa-clipboard me-1"></i> Copy All
                        </button>
                
                        <button class="btn btn-secondary" onclick="printRecoveryCodes()">
                            <i class="fa fa-print me-1"></i> Print
                        </button>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function printRecoveryCodes() {
    const content = document.querySelector('.recovery-codes-container').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Garrison - Recovery Codes</title>
            <style>
                body { font-family: sans-serif; padding: 20px; }
                h1 { font-size: 18px; margin-bottom: 20px; }
                .recovery-code-item { 
                    font-family: monospace; 
                    margin-bottom: 10px; 
                    display: block; 
                    font-size: 16px;
                    padding: 8px;
                    background: #f8f9fa;
                }
                .btn-copy { display: none; }
            </style>
        </head>
        <body>
            <h1>Recovery Codes - Keep these private and secure</h1>
            <div class="recovery-codes-container">
                ${content}
            </div>
            <p style="margin-top: 30px">These codes can be used once each to log in if you lose access to your authenticator app.</p>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    
    // Show success notification
    Swal.fire({
        title: 'Print Dialog Opened',
        text: 'Recovery codes sent to printer',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Individual copy buttons
    document.querySelectorAll('.btn-copy').forEach(btn => {
        btn.addEventListener('click', function() {
            const code = this.parentElement.getAttribute('data-code');
            navigator.clipboard.writeText(code).then(() => {
                Swal.fire({
                    title: 'Copied!',
                    text: 'Recovery code copied to clipboard',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            }).catch(err => {
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to copy code: ' + err,
                    icon: 'error'
                });
            });
        });
    });
    
    // Copy all button
    document.getElementById('btn-copy-all').addEventListener('click', function() {
        const codes = Array.from(document.querySelectorAll('.recovery-code-item'))
            .map(item => item.getAttribute('data-code'))
            .join('\n');
            
        navigator.clipboard.writeText(codes).then(() => {
            Swal.fire({
                title: 'All Copied!',
                text: 'All recovery codes copied to clipboard',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        }).catch(err => {
            Swal.fire({
                title: 'Error',
                text: 'Failed to copy codes: ' + err,
                icon: 'error'
            });
        });
    });
});
</script>
@endsection