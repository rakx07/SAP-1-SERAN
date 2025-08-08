<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Memory;

class MemoryController extends Controller
{
public function update(Request $request, $id)
{
    $request->validate([
        'instruction' => 'required|regex:/^[01]{8}$/'
    ]);

    $memory = Memory::findOrFail($id);
    $memory->instruction = $request->input('instruction');
    $memory->save();

    return redirect()->back()->with('success', 'Memory instruction updated successfully.');
}
}
