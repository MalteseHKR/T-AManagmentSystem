@extends('layouts.app')

@section('title', 'SFTP Connection Test')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">SFTP Connection Test</div>
                
                <div class="card-body">
                    <div id="loading">
                        <p class="text-center">
                            <i class="fas fa-spinner fa-spin fa-2x"></i><br>
                            Testing connection...
                        </p>
                    </div>
                    
                    <div id="result" style="display: none;">
                        <div id="success-panel" style="display: none;">
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i> SFTP Connection Successful!
                            </div>
                            
                            <h5>Connection Details:</h5>
                            <table class="table table-bordered">
                                <tr>
                                    <th>Host</th>
                                    <td id="host"></td>
                                </tr>
                                <tr>
                                    <th>Port</th>
                                    <td id="port"></td>
                                </tr>
                                <tr>
                                    <th>Username</th>
                                    <td id="username"></td>
                                </tr>
                                <tr>
                                    <th>Root Directory</th>
                                    <td id="root"></td>
                                </tr>
                            </table>
                            
                            <h5>Files Found: <span id="file-count"></span></h5>
                            <ul id="file-list" class="list-group"></ul>
                        </div>
                        
                        <div id="error-panel" style="display: none;">
                            <div class="alert alert-danger">
                                <i class="fas fa-times-circle me-2"></i> SFTP Connection Failed!
                            </div>
                            <div id="error-message" class="mb-3"></div>
                            
                            <h5>Connection Attempts:</h5>
                            <table class="table table-bordered">
                                <tr>
                                    <th>Host</th>
                                    <td id="error-host"></td>
                                </tr>
                                <tr>
                                    <th>Port</th>
                                    <td id="error-port"></td>
                                </tr>
                                <tr>
                                    <th>Root Directory</th>
                                    <td id="error-root"></td>
                                </tr>
                            </table>
                            
                            <h5>Troubleshooting Tips:</h5>
                            <ul>
                                <li>Verify SFTP server is running and accessible</li>
                                <li>Check username and password in .env file</li>
                                <li>Ensure PHP has the SSH2 extension installed</li>
                                <li>Check firewall settings on both servers</li>
                                <li>Verify the root directory exists and is accessible</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    fetch('{{ route("images.test-connection") }}')
        .then(response => response.json())
        .then(data => {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('result').style.display = 'block';
            
            if (data.success) {
                // Show success panel
                document.getElementById('success-panel').style.display = 'block';
                
                // Fill connection details
                document.getElementById('host').textContent = data.connection.host;
                document.getElementById('port').textContent = data.connection.port;
                document.getElementById('username').textContent = data.connection.username;
                document.getElementById('root').textContent = data.connection.root;
                
                // Show file count
                document.getElementById('file-count').textContent = data.fileCount;
                
                // Add files to list
                const fileList = document.getElementById('file-list');
                data.sampleFiles.forEach(file => {
                    const li = document.createElement('li');
                    li.className = 'list-group-item';
                    li.textContent = file;
                    fileList.appendChild(li);
                });
            } else {
                // Show error panel
                document.getElementById('error-panel').style.display = 'block';
                document.getElementById('error-message').textContent = data.message;
                
                // Fill error details
                document.getElementById('error-host').textContent = data.connection.host;
                document.getElementById('error-port').textContent = data.connection.port;
                document.getElementById('error-root').textContent = data.connection.root;
            }
        })
        .catch(error => {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('result').style.display = 'block';
            document.getElementById('error-panel').style.display = 'block';
            document.getElementById('error-message').textContent = 'Failed to connect: ' + error;
        });
});
</script>
@endpush