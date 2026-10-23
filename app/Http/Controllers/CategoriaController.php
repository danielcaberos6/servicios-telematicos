<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoriaRequest;
use App\Models\Categoria;
use App\Services\CategoriaService;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    public function index(Request $request)
    {
        $filtros = $request->validate(['estado' => ['nullable', 'in:habilitadas,deshabilitadas']]);
        return view('admin.categorias.index', ['categorias' => Categoria::query()
            ->when($filtros['estado'] ?? null, fn ($query, $estado) => $query->where('habilitada', $estado === 'habilitadas'))
            ->orderBy('nombre_categoria')->paginate(15)->withQueryString()]);
    }

    public function create()
    {
        return view('admin.categorias.formulario', ['categoria' => new Categoria]);
    }

    public function edit(Categoria $categoria)
    {
        return view('admin.categorias.formulario', compact('categoria'));
    }

    public function store(CategoriaRequest $request, CategoriaService $servicio)
    {
        $servicio->guardar($request->user()->id_usuario, null, $request->validated());

        return redirect()->route('admin.categorias.index')->with('status', 'Categoría creada.');
    }

    public function update(CategoriaRequest $request, Categoria $categoria, CategoriaService $servicio)
    {
        $servicio->guardar($request->user()->id_usuario, $categoria->id_categoria, $request->validated());

        return redirect()->route('admin.categorias.index')->with('status', 'Categoría actualizada.');
    }

    public function estado(Request $request, Categoria $categoria, CategoriaService $servicio)
    {
        $request->validate(['habilitada' => ['required', 'boolean']]);
        $servicio->habilitar($request->user()->id_usuario, $categoria->id_categoria, $request->boolean('habilitada'));

        return back()->with('status', 'Disponibilidad de la categoría actualizada.');
    }
}
