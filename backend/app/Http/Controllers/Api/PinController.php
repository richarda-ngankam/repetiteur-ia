<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ParentApiHelpers;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PinController extends Controller
{
    use ParentApiHelpers;

    // Le parent définit (ou change) son PIN à 4 chiffres.
    public function definirParent(Request $request): JsonResponse
    {
        $parent = $this->parentConnecte($request);

        $data = $request->validate([
            'pin' => ['required', 'digits:4'],
        ]);

        $parent->forceFill([
            'pin_hash' => Hash::make($data['pin']),
            'pin_failed_attempts' => 0,
            'pin_locked_until' => null,
        ])->save();

        return response()->json(['message' => 'PIN enregistré.']);
    }
}