<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class CommandController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            "command" => ['required', 'string']
        ]);

        Artisan::call($data["command"]);

        return response()->json(
            ["message" => "Command executed successfully!"]
        );
    }
}
