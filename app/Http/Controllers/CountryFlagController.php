<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CountryFlagController extends Controller
{
    public function show(string $code): BinaryFileResponse|Response
    {
        $code = strtolower($code);
        abort_unless((bool) preg_match('/^[a-z]{2}$/', $code), 404);

        $dir = storage_path('app/flags');
        $png = $dir.'/'.$code.'.png';

        if (! is_file($png)) {
            File::ensureDirectoryExists($dir);
            try {
                $response = Http::timeout(4)
                    ->withHeaders(['User-Agent' => 'Clickronix/1.0'])
                    ->get('https://flagcdn.com/w40/'.$code.'.png');

                $body = $response->body();
                $type = strtolower((string) $response->header('Content-Type'));
                if ($response->successful() && str_starts_with($type, 'image/') && strlen($body) > 40) {
                    File::put($png, $body);
                }
            } catch (\Throwable) {
                // Serve the local SVG badge if the CDN is unreachable.
            }
        }

        if (is_file($png)) {
            return response()->file($png, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=2592000, immutable',
            ]);
        }

        $iso = strtoupper($code);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="15" viewBox="0 0 20 15">'
            .'<rect width="20" height="15" rx="2" fill="#3d3550"/>'
            .'<text x="10" y="10.5" text-anchor="middle" font-family="ui-sans-serif,system-ui,sans-serif" font-size="7" font-weight="700" fill="#fff">'
            .$iso
            .'</text></svg>';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
