<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $users = User::where('id', '!=', $request->user()->id)
            ->where('is_bot', false)
            ->where(function ($query) use ($request): void {
                $query->whereRaw('name ILIKE ?', ['%'.$request->search.'%'])
                    ->orWhereRaw('email ILIKE ?', ['%'.$request->search.'%']);
            })
            ->select('id', 'name')
            ->limit(20)
            ->get();

        return response()->json($users);
    }
}
