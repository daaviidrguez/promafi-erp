<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class CatalogosSatController extends Controller
{
    /**
     * Índice del módulo Catálogos SAT (hub con enlaces a cada catálogo).
     */
    public function index()
    {
        return view('catalogos-sat.index');
    }
}
