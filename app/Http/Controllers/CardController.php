<?php

// app/Http/Controllers/CardController.php
namespace App\Http\Controllers;

use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CardController extends Controller
{
    /**
     * Register a new card
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'phone'         => 'nullable|string|max:50',
        ]);

        $cardNumber = strtoupper(Str::random(10)); // generate random code

        $card = Card::create([
            'card_number'   => $cardNumber,
            'customer_name' => $validated['customer_name'],
            'phone'         => $validated['phone'] ?? null,
            'balance'       => 0, // start at zero or some default
        ]);

        return response()->json([
            'message'      => 'Card registered successfully',
            'card_number'  => $card->card_number,
            'balance'      => $card->balance,
        ]);
    }

    /**
     * Redeem amount from a card
     */
    public function redeem(Request $request)
    {
        $validated = $request->validate([
            'card_number' => 'required|exists:cards,card_number',
            'amount'      => 'required|numeric|min:0.01',
        ]);

        $card = Card::where('card_number', $validated['card_number'])->firstOrFail();

        if ($card->balance < $validated['amount']) {
            return response()->json(['message' => 'Insufficient balance'], 422);
        }

        $card->balance -= $validated['amount'];
        $card->save();

        return response()->json([
            'message'     => 'Redeemed successfully',
            'new_balance' => $card->balance,
        ]);
    }

    /**
     * Optionally: add funds to card
     */
    public function topup(Request $request)
    {
        $validated = $request->validate([
            'card_number' => 'required|exists:cards,card_number',
            'amount'      => 'required|numeric|min:0.01',
        ]);

        $card = Card::where('card_number', $validated['card_number'])->firstOrFail();
        $card->balance += $validated['amount'];
        $card->save();

        return response()->json([
            'message'     => 'Card topped up',
            'new_balance' => $card->balance,
        ]);
    }
}
