<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;


class ImageController extends Controller
{
    /**
     * Serve an image from the local uploads directory
     *
     * @param string $filename
     * @return \Illuminate\Http\Response
     */
public function serve($filename)
{
    try {
        $filename = basename(urldecode($filename));

        Log::info("??? Looking for image: {$filename}");

        $paths = [
            $filename,
            'IMG_' . $filename,
            str_replace('IMG_', '', $filename),
        ];

        foreach ($paths as $path) {
            if (Storage::disk('uploads')->exists($path)) {
                Log::info("? Found image at: uploads/{$path}");

                $contents = Storage::disk('uploads')->get($path);

                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mimeTypes = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'bmp' => 'image/bmp',
                ];

                $contentType = $mimeTypes[$extension] ?? 'image/jpeg';

                return response($contents, 200, [
                    'Content-Type' => $contentType,
                    'Cache-Control' => 'public, max-age=86400',
                ]);
            }
        }

        Log::warning("? Image not found in uploads/: {$filename}");
        return $this->placeholder();

    } catch (\Exception $e) {
        Log::error('?? Error loading image: ' . $e->getMessage(), [
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
        $svg = '<svg width="200" height="200" xmlns="http://www.w3.org/2000/svg">';
        $svg .= '<rect width="200" height="200" fill="#f5f5f5" />';
        $svg .= '<text x="50%" y="50%" font-family="Arial" font-size="24" text-anchor="middle" fill="#aaa">No Image</text>';
        $svg .= '</svg>';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
        ]);
    }

    /**
     * List available images from local storage
     *
     * @return \Illuminate\Http\Response
     */

    public function serveProfileImage($userId)
	{
   		$record = DB::table('user_profile_photo')->where('user_id', $userId)->first();

    		if (!$record || !file_exists($record->file_name_link)) {
        	return response()->file(public_path('images/default-portrait.png'));
    		}

    	return response()->file($record->file_name_link, [
        'Content-Type' => mime_content_type($record->file_name_link),
        'Content-Disposition' => 'inline; filename="' . basename($record->file_name_link) . '"'
    	]);
    }

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
}
