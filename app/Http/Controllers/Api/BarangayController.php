<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BarangayController extends Controller
{
    private function norm(string $s): string
    {
        $s = strtolower(preg_replace('/\(.*?\)/', '', $s));
        $s = str_replace(['city of', 'city'], '', $s);
        return preg_replace('/[^a-z]/', '', $s);
    }

    public function index(Request $request)
    {
        $municipality = (string) $request->query('municipality', '');
        if ($municipality === '') {
            return response()->json([]);
        }

        $all = Cache::rememberForever('leyte_barangays', function () {
            return json_decode(file_get_contents(database_path('data/leyte_barangays.json')), true) ?: [];
        });

        $target = $this->norm($municipality);
        foreach ($all as $name => $barangays) {
            if ($this->norm($name) === $target) {
                return response()->json($barangays);
            }
        }

        return response()->json([]);
    }
}