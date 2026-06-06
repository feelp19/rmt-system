<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\DepositRequest;
use App\Http\Resources\Wallet\WalletResource;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(private readonly WalletService $wallets) {}

    public function show(Request $request): JsonResponse
    {
        $wallet = $this->wallets->walletFor($request->user());

        return response()->json([
            'data' => WalletResource::make($wallet)->resolve($request),
        ]);
    }

    public function deposit(DepositRequest $request): JsonResponse
    {
        $wallet = $this->wallets->deposit(
            $request->user(),
            (int) $request->validated('amount_cents'),
        );

        return response()->json([
            'data' => WalletResource::make($wallet)->resolve($request),
        ]);
    }
}
