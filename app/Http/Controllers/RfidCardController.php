<?php

namespace App\Http\Controllers;

use App\Models\RfidCard;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RfidCardController extends Controller
{
    public function index()
    {
        return Inertia::render('RfidCards/Index', [
            'cards' => RfidCard::with('user:id,name')->latest('last_seen_at')->get(),
            'users' => User::select('id', 'name')->whereDoesntHave('rfidCard')->get(),
        ]);
    }

    public function update(Request $request, RfidCard $rfidCard)
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'exists:users,id', 'unique:rfid_cards,user_id,'.$rfidCard->id],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        // If assigning a user, ensure they don't have another card assigned
        if ($request->filled('user_id')) {
            RfidCard::where('user_id', $validated['user_id'])
                ->where('id', '!=', $rfidCard->id)
                ->update(['user_id' => null]);
        }

        $rfidCard->update($validated);

        return back()->with('success', 'Kartu berhasil diperbarui');
    }

    public function destroy(RfidCard $rfidCard)
    {
        $rfidCard->delete();

        return back()->with('success', 'Kartu berhasil dihapus');
    }
}
