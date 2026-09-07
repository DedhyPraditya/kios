<?php

namespace App\Http\Controllers;

use App\Support\Changelog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppLogController extends Controller
{
    /**
     * Tampilkan halaman Log Pembaruan Aplikasi.
     */
    public function index(Request $request): Response
    {
        $search = strtolower(trim((string) $request->input('search', '')));
        $entries = Changelog::all();

        if ($search !== '') {
            $entries = array_values(array_filter($entries, function ($entry) use ($search) {
                if (
                    str_contains(strtolower($entry['version']), $search) ||
                    str_contains(strtolower($entry['title']), $search) ||
                    str_contains(strtolower($entry['description']), $search)
                ) {
                    return true;
                }

                foreach ($entry['changes'] as $change) {
                    if (
                        str_contains(strtolower($change['title']), $search) ||
                        str_contains(strtolower($change['description']), $search) ||
                        str_contains(strtolower($change['category']), $search)
                    ) {
                        return true;
                    }
                }

                return false;
            }));
        }

        return Inertia::render('AppLog/Index', [
            'logs' => $entries,
            'filters' => [
                'search' => $request->input('search', ''),
            ],
            'system' => [
                'app_name' => config('app.name'),
                'app_env' => config('app.env'),
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
                'db_driver' => config('database.default'),
                'latest_version' => $entries[0]['version'] ?? '1.0.0',
                'total_releases' => count(Changelog::all()),
            ],
        ]);
    }
}
