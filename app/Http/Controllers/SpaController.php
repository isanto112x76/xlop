<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SpaController extends Controller
{
    /**
     * Zwraca główny widok aplikacji, który jest punktem wejścia dla Vue.js.
     * Upewnij się, że w resources/views masz plik, który ładuje Twoją skompilowaną aplikację Vue.
     * Vuexy prawdopodobnie generuje plik index.html, który można opakować w ten widok.
     */
    public function index()
    {
        return view('application'); // Zakładamy, że główny plik to 'application.blade.php'
    }
}
