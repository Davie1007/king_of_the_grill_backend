<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Notifications\UnreachablePosApiNotification;

class HealthController extends Controller
{
    public function check($system)
    {
        $urls = [
            'butchery' => env('BUTCHERY_API_URL', 'https://kingofthegrill.co.ke/#/butchery'),
            'gas' => env('GAS_API_URL', 'https://kingofthegrill.co.ke/#/gas'),
            'drinks' => env('DRINKS_API_URL', 'https://kingofthegrill.co.ke/#/drinks'),
        ];

        if (!isset($urls[$system])) {
            return response()->json(['error' => 'Invalid system'], 400);
        }

        try {
            $response = Http::timeout(5)->get($urls[$system] . '/health');
            if ($response->successful()) {
                return response()->json(['status' => 'healthy']);
            }
            throw new \Exception('API unreachable');
        } catch (\Exception $e) {
            $owner = User::where('role', 'owner')->first();
            $owner->notify(new UnreachablePosApiNotification(ucfirst($system)));
            return response()->json(['status' => 'unreachable'], 503);
        }
    }
}