<?php

namespace App\Http\Controllers;

use App\Models\Koperasi;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KoperasiController extends Controller
{
    public function index()
    {
        return Inertia::render('Koperasi', [
            'koperasi' => Koperasi::all(),
        ]);
    }
}
