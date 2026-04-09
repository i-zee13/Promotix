<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DomainManagementController extends Controller
{
    public function index(Request $request): View
    {
        $domains = Domain::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('hostname')
            ->paginate(25);

        return view('domains.index', [
            'domains' => $domains,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hostname' => ['required', 'string', 'max:255'],
        ]);

        $hostname = strtolower(trim($validated['hostname']));
        $hostname = preg_replace('#^https?://#', '', $hostname);
        $hostname = rtrim($hostname, '/');

        Domain::updateOrCreate(
            ['user_id' => $request->user()->id, 'hostname' => $hostname],
            [
                'domain_key' => Str::uuid()->toString(),
                'secret_key' => Str::uuid()->toString(),
                'authentication_key' => Str::uuid()->toString(),
            ]
        );

        return back()->with('status', 'Domain saved.');
    }

    public function setup(Request $request, Domain $domain): View
    {
        abort_unless($domain->user_id === $request->user()->id, 403);

        return view('domains.setup', [
            'domain' => $domain,
        ]);
    }

    public function downloadWpPlugin(Request $request, Domain $domain): BinaryFileResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);

        // WordPress.org / wp-admin installer expects the zip to contain a single top-level folder
        // that matches the plugin slug, e.g. promotix-tag/promotix-tag.php
        $slug = 'promotix-tag';
        $baseDir = base_path('resources/wp-plugin/' . $slug);
        $mainFile = $baseDir . DIRECTORY_SEPARATOR . $slug . '.php';

        if (! file_exists($mainFile)) {
            abort(404, 'Plugin source not found.');
        }

        $zipPath = storage_path('app/' . $slug . '.zip');
        if (file_exists($zipPath)) {
            @unlink($zipPath);
        }

        $zip = new \ZipArchive();
        $ok = $zip->open($zipPath, \ZipArchive::CREATE);
        if ($ok !== true) {
            abort(500, 'Unable to create plugin zip.');
        }

        // Add files under a top-level "{slug}/" folder (WordPress expects this).
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                continue;
            }
            $relative = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $zip->addFile($file->getPathname(), $slug . '/' . str_replace('\\', '/', $relative));
        }

        $zip->close();

        return response()->download($zipPath, $slug . '.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }
}

