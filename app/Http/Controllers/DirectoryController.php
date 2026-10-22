<?php

namespace App\Http\Controllers;

use App\Services\DirectoryService;
use Illuminate\Http\Request;
use RuntimeException;

class DirectoryController extends Controller
{
    public function __invoke(Request $request, DirectoryService $directory)
    {
        $data = $request->validate(['query' => 'required|string|min:2|max:100']);
        try {
            return response()->json($directory->searchUsers($data['query']));
        } catch (RuntimeException $e) {
            report($e);

            return response()->json(['error' => 'Directory search is unavailable. You can enter the full name manually.'], 503);
        }
    }
}
