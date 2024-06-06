<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CurrencyController extends Controller
{
    // Function to fetch the latest exchange rates
    private function fetchExchangeRates()
    {
        $response = Http::get('https://v6.exchangerate-api.com/v6/88b52f32b1c3bc7a4150ae61/latest/USD');

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Unable to fetch exchange rates');
    }

    // Function to convert from one currency to another
    public function convertCurrency(Request $request)
    {
        $amount = $request->input('amount');
        $fromCurrency = $request->input('from_currency');
        $toCurrency = $request->input('to_currency');

        try {
            $exchangeRates = $this->fetchExchangeRates();
            $rates = $exchangeRates['conversion_rates'];

            if (!isset($rates[$fromCurrency]) || !isset($rates[$toCurrency])) {
                return response()->json(['error' => 'Invalid currency code'], 400);
            }

            // Convert the amount to USD first
            $amountInUsd = $amount / $rates[$fromCurrency];

            // Then convert the amount from USD to the target currency
            $convertedAmount = $amountInUsd * $rates[$toCurrency];

            // Format the converted amount to 2 decimal places
            $formattedAmount = number_format($convertedAmount, 2, '.', '');

            return response()->json([
                'amount' => $amount,
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'converted_amount' => $formattedAmount
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
