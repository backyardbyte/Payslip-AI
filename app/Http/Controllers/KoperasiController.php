<?php

namespace App\Http\Controllers;

use App\Models\Koperasi;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KoperasiController extends Controller
{
    public function __construct()
    {
        // Allow all authenticated users to view koperasi data
        $this->middleware('auth')->only(['index', 'show']);
        $this->middleware('permission:koperasi.create')->only(['create', 'store']);
        $this->middleware('permission:koperasi.update')->only(['edit', 'update']);
        $this->middleware('permission:koperasi.delete')->only(['destroy']);
        $this->middleware('permission:koperasi.manage_rules')->only(['updateRules']);
    }

    public function index()
    {
        return Inertia::render('Koperasi', [
            'koperasi' => Koperasi::all(),
            'permissions' => [
                'canCreate' => auth()->user()->hasPermission('koperasi.create'),
                'canUpdate' => auth()->user()->hasPermission('koperasi.update'),
                'canDelete' => auth()->user()->hasPermission('koperasi.delete'),
                'canManageRules' => auth()->user()->hasPermission('koperasi.manage_rules'),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('koperasi/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'rules' => 'required|array|min:1',
            'is_active' => 'boolean',
        ]);

        Koperasi::create([
            'name' => $request->name,
            'rules' => $request->rules,
            'is_active' => $request->is_active ?? true,
        ]);

        return redirect()->route('koperasi')->with('success', 'Koperasi created successfully.');
    }

    public function show(Koperasi $koperasi)
    {
        return Inertia::render('koperasi/Show', [
            'koperasi' => $koperasi,
        ]);
    }

    public function edit(Koperasi $koperasi)
    {
        return Inertia::render('koperasi/Edit', [
            'koperasi' => $koperasi,
        ]);
    }

    public function update(Request $request, Koperasi $koperasi)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'rules' => 'required|array|min:1',
            'is_active' => 'boolean',
        ]);

        $koperasi->update([
            'name' => $request->name,
            'rules' => $request->rules,
            'is_active' => $request->is_active ?? true,
        ]);

        return redirect()->route('koperasi')->with('success', 'Koperasi updated successfully.');
    }

    public function destroy(Koperasi $koperasi)
    {
        $koperasi->delete();

        return redirect()->route('koperasi')->with('success', 'Koperasi deleted successfully.');
    }

    public function updateRules(Request $request, Koperasi $koperasi)
    {
        $request->validate([
            'rules' => 'required|array|min:1',
            'rules.*.key' => 'required|string',
            'rules.*.value' => 'required|numeric|min:0',
        ]);

        $koperasi->update(['rules' => $request->rules]);

        return back()->with('success', 'Koperasi rules updated successfully.');
    }
}
