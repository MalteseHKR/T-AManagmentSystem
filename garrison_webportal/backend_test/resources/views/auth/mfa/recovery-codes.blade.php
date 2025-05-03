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
                
                <div class="recovery-codes-container mt-4 mb-4" id="print-section">
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
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-copy" data-code="{{ $code }}" title="Copy to clipboard">
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
                
                        <button type="button" id="btn-copy-all" class="btn btn-info">
                            <i class="fa fa-clipboard me-1"></i> Copy All
                        </button>
                
                        <button type="button" class="btn btn-secondary" onclick="printRecoveryCodes()">
                            <i class="fa fa-print me-1"></i> Print
                        </button>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function printRecoveryCodes() {
    const originalContents = document.body.innerHTML;
    const printContents = document.getElementById('print-section').innerHTML;

    const style = `
        <style>
            body { font-family: sans-serif; padding: 20px; }
            .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            .table th, .table td { border: 1px solid #dee2e6; padding: 8px; }
            .table-primary { background-color: #e9ecef; }
            .btn-copy { display: none !important; }
        </style>
    `;

    document.body.innerHTML = style + '<h1>Recovery Codes - Keep these private and secure</h1>' + printContents + '<p style="margin-top: 30px">These codes can be used once each to log in if you lose access to your authenticator app.</p>';
    window.print();
    document.body.innerHTML = originalContents;
    window.location.reload();
}

document.querySelectorAll('.btn-copy').forEach(btn => {
    btn.addEventListener('click', () => {
        const code = btn.dataset.code;

        if (!code) {
            console.warn("\u26A0\uFE0F No code found in data attribute.");
            return;
        }

        if (navigator.clipboard) {
            navigator.clipboard.writeText(code).then(() => {
                showCopySuccess("Recovery code copied to clipboard.");
            }).catch(err => {
                console.error("Clipboard API failed:", err);
                fallbackCopy(code);
            });
        } else {
            fallbackCopy(code);
        }
    });
});

function fallbackCopy(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        showCopySuccess("Recovery code copied to clipboard.");
    } catch (err) {
        console.error('Fallback copy failed:', err);
        Swal.fire({
            icon: 'error',
            title: 'Oops!',
            text: 'Failed to copy the code.',
            toast: true,
            position: 'top-end',
            timer: 3000,
            showConfirmButton: false
        });
    }
    document.body.removeChild(textarea);
}

function showCopySuccess(message) {
    Swal.fire({
        icon: 'success',
        title: 'Copied!',
        text: message,
        toast: true,
        position: 'top-end',
        timer: 3000,
        showConfirmButton: false
    });
}

document.getElementById('btn-copy-all')?.addEventListener('click', () => {
    const codes = Array.from(document.querySelectorAll('.btn-copy')).map(btn => btn.dataset.code).join('\n');

    if (!codes) {
        Swal.fire({
            icon: 'warning',
            title: 'No Codes Found',
            text: 'Could not find any recovery codes to copy.',
            toast: true,
            position: 'top-end',
            timer: 3000,
            showConfirmButton: false
        });
        return;
    }

    navigator.clipboard.writeText(codes).then(() => {
        showCopySuccess("All recovery codes copied.");
    }).catch(err => {
        console.error("Copy all failed:", err);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to copy recovery codes.',
            toast: true,
            position: 'top-end',
            timer: 3000,
            showConfirmButton: false
        });
    });
});
</script>
@endpush