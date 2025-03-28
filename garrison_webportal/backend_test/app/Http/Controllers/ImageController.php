<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ImageController extends Controller
{
    /**
     * Serve an image from the SFTP server
     *
     * @param string $filename
     * @return \Illuminate\Http\Response
     */
    public function serve($filename)
    {
        try {
            // Fix for when a complete URL is passed
            if (strpos($filename, 'http://') !== false || strpos($filename, 'https://') !== false) {
                // Extract just the filename from the URL
                $parsedUrl = parse_url($filename);
                $pathParts = explode('/', trim($parsedUrl['path'] ?? '', '/'));
                $filename = end($pathParts);
            }

            // Remove any remaining path elements and clean up
            $filename = basename($filename);
            $filename = urldecode($filename);
            
            // Log what file we're looking for
            Log::info("Attempting to serve image: {$filename}");
            
            // If we still have "images/serve" or similar in the filename, extract just the actual filename
            if (strpos($filename, 'images/serve/') !== false) {
                $parts = explode('images/serve/', $filename);
                $filename = end($parts);
            }
            
            // Prepend 'IMG_' if it's not already there
            if (strpos($filename, 'IMG_') !== 0) {
                $filenameWithPrefix = 'IMG_' . $filename;
            } else {
                $filenameWithPrefix = $filename;
            }
            
            // Check if the file exists in the uploads directory on the SFTP server
            $paths = [
                'uploads/' . $filenameWithPrefix,  // Try with uploads/ prefix and IMG_ prefix
                'uploads/' . $filename,            // Try with uploads/ prefix
                $filenameWithPrefix,               // Try with just IMG_ prefix
                $filename,                         // Try the raw filename
            ];
            
            $fileFound = false;
            $fileContents = null;
            
            foreach ($paths as $path) {
                Log::info("Checking path: {$path}");
                
                if (Storage::disk('sftp')->exists($path)) {
                    Log::info("Found image at path: {$path}");
                    $fileContents = Storage::disk('sftp')->get($path);
                    $fileFound = true;
                    break;
                }
            }
            
            if (!$fileFound) {
                Log::warning("Image not found for any tried paths. Filename: {$filename}");
                return $this->placeholder();
            }
            
            // Determine MIME type based on file extension
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $mimeTypes = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'bmp' => 'image/bmp',
            ];
            
            $contentType = $mimeTypes[$extension] ?? 'image/jpeg';
            
            // Return the image with appropriate headers
            return response($fileContents, 200, [
                'Content-Type' => $contentType,
                'Cache-Control' => 'public, max-age=86400', // Cache for 24 hours
            ]);
        } catch (\Exception $e) {
            Log::error('Error serving image: ' . $e->getMessage(), [
                'filename' => $filename,
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->placeholder();
        }
    }
    
    /**
     * Serve a placeholder image when the actual image is not found
     *
     * @return \Illuminate\Http\Response
     */
    public function placeholder()
    {
        // Create a simple SVG placeholder
        $svg = '<svg width="200" height="200" xmlns="http://www.w3.org/2000/svg">';
        $svg .= '<rect width="200" height="200" fill="#f5f5f5" />';
        $svg .= '<text x="50%" y="50%" font-family="Arial" font-size="24" text-anchor="middle" fill="#aaa">No Image</text>';
        $svg .= '</svg>';
        
        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
        ]);
    }
    
    /**
     * Display a list of available images
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $files = Storage::disk('sftp')->files();
            
            return view('images.index', [
                'files' => $files
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to list SFTP images: ' . $e->getMessage());
            
            return view('images.index', [
                'files' => [],
                'error' => 'Failed to retrieve images from server: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get image by filename - alias for serve method
     * 
     * @param string $filename
     * @return \Illuminate\Http\Response
     */
    public function getImage($filename)
    {
        return $this->serve($filename);
    }
    
    /**
     * Test the SFTP connection
     *
     * @return \Illuminate\Http\Response
     */
    public function testConnection()
    {
        try {
            $files = Storage::disk('sftp')->files();
            return response()->json([
                'status' => 'connected',
                'message' => 'Successfully connected to SFTP server',
                'files_found' => count($files),
                'files' => $files
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Detailed connection test with better error reporting
     *
     * @return \Illuminate\Http\Response
     */
    public function detailedTest()
    {
        try {
            // Get configuration
            $config = config('filesystems.disks.sftp');
            
            // Test connection
            $files = Storage::disk('sftp')->files();
            
            // Log success with files list
            Log::info('SFTP connection successful', [
                'files_count' => count($files),
                'first_few_files' => array_slice($files, 0, 5)
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'SFTP connection successful',
                'configuration' => [
                    'host' => $config['host'],
                    'port' => $config['port'],
                    'username' => $config['username'],
                    'root' => $config['root'],
                ],
                'files_found' => count($files),
                'sample_files' => array_slice($files, 0, 10),
            ]);
        } catch (\Exception $e) {
            Log::error('SFTP connection test failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'configuration' => config('filesystems.disks.sftp'),
                'suggestion' => 'Check your SFTP credentials and server connectivity'
            ], 500);
        }
    }

    /**
     * Serve an image from the local directory
     *
     * @param string $filename
     * @return \Illuminate\Http\Response
     */
    public function serveLocal($filename)
    {
        try {
            // Clean up filename
            $filename = basename(urldecode($filename));
            
            // Remove URL if present in filename
            if (strpos($filename, 'http://') !== false || strpos($filename, 'https://') !== false) {
                $parsedUrl = parse_url($filename);
                $pathParts = explode('/', trim($parsedUrl['path'] ?? '', '/'));
                $filename = end($pathParts);
            }
            
            // Log what we're looking for
            Log::info("Looking for local image: {$filename}");
            
            // Try different path variations
            $paths = [
                $filename,
                'IMG_' . $filename,
                str_replace('IMG_', '', $filename),
            ];
            
            foreach ($paths as $path) {
                if (Storage::disk('local_uploads')->exists($path)) {
                    Log::info("Found local image at: {$path}");
                    $contents = Storage::disk('local_uploads')->get($path);
                    
                    // Determine content type
                    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    $mimeTypes = [
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'bmp' => 'image/bmp',
                    ];
                    
                    $contentType = $mimeTypes[$extension] ?? 'image/jpeg';
                    
                    // Return the image
                    return response($contents, 200, [
                        'Content-Type' => $contentType,
                        'Cache-Control' => 'public, max-age=86400',
                    ]);
                }
            }
            
            // If image isn't found in any of the paths
            Log::warning("Local image not found: {$filename}");
            return $this->placeholder();
            
        } catch (\Exception $e) {
            Log::error('Error loading local image: ' . $e->getMessage(), [
                'filename' => $filename,
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->placeholder();
        }
    }

    /**
     * Get a list of available images in the local uploads directory
     *
     * @return \Illuminate\Http\Response
     */
    public function listLocalImages()
    {
        try {
            $files = Storage::disk('local_uploads')->files();
            $images = [];
            
            foreach ($files as $file) {
                $extension = pathinfo($file, PATHINFO_EXTENSION);
                if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'bmp'])) {
                    $images[] = [
                        'name' => $file,
                        'url' => route('images.local', ['filename' => basename($file)]),
                        'size' => Storage::disk('local_uploads')->size($file),
                        'last_modified' => Storage::disk('local_uploads')->lastModified($file),
                    ];
                }
            }
            
            return response()->json([
                'count' => count($images),
                'images' => $images,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Serve an image from the SFTP server with path fix
     *
     * @param string $filename
     * @return \Illuminate\Http\Response
     */
    public function serveFromSftp($filename)
    {
        try {
            // Clean up filename
            $filename = basename(urldecode($filename));
            
            // Try different paths on SFTP
            $paths = [
                $filename,
                'IMG_' . $filename,
                // Use the correct path if trying to access a specific subdirectory
                'uploads/' . $filename,
                'uploads/IMG_' . $filename,
            ];
            
            foreach ($paths as $path) {
                if (Storage::disk('sftp')->exists($path)) {
                    // Rest of your code...
                }
            }
            
            // ...
        } catch (\Exception $e) {
            // ...
        }
    }
}