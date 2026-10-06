<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubastaRequest;
use App\Models\Categoria;
use App\Models\Subasta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubastaController extends Controller
{
    public function home()
    {
        return view('home', [
            'subastas' => Subasta::activas()->with(['imagenes', 'categoria'])->withCount('pujas')->withMax('pujas', 'monto')->orderBy('fecha_fin')->limit(4)->get(),
            'totalActivas' => Subasta::activas()->count(),
            'categorias' => Categoria::orderBy('id_categoria')->get(),
        ]);
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'categoria' => ['nullable', 'integer', 'exists:categoria,id_categoria'],
            'estado' => ['nullable', Rule::in(Subasta::CONDITIONS)],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', ...($request->filled('desde') ? ['after_or_equal:desde'] : [])],
            'min' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'max' => ['nullable', 'numeric', 'max:9999999999.99', 'min:0', ...($request->filled('min') ? ['gte:min'] : [])],
            'ubicacion' => ['nullable', 'string', 'max:250'],
            'orden' => ['nullable', Rule::in(['recientes', 'finalizan', 'precio_asc', 'precio_desc'])],
        ]);
        $catalogo = app(\App\Services\CatalogoService::class);

        return view('auctions.index', ['subastas' => $catalogo->paginar($filters), 'categorias' => $catalogo->categorias()]);
    }

    public function create()
    {
        return view('auctions.form', ['subasta' => new Subasta, 'categorias' => Categoria::orderBy('nombre_categoria')->get()]);
    }

    public function store(SubastaRequest $request)
    {
        $paths = [];
        try {
            $subasta = DB::transaction(function () use ($request, &$paths) {
                $subasta = $request->user()->subastas()->create([...$request->safe()->except(['imagenes', 'eliminar_imagenes']), 'fecha_inicio' => now()]);
                $this->saveImages($subasta, $request, $paths);

                return $subasta;
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($paths);
            throw $exception;
        }

        return redirect()->route('auctions.show', $subasta)->with('status', 'Tu subasta se publicó correctamente y ya aparece en Buscar.');
    }

    public function show(Subasta $subasta)
    {
        $subasta->load(['imagenes', 'categoria', 'usuario'])->loadCount('pujas')->loadMax('pujas', 'monto');
        $ganadora = $subasta->pujas()->with('usuario')->orderByDesc('monto')->first();

        return view('auctions.show', compact('subasta', 'ganadora'));
    }

    public function mine(Request $request)
    {
        $subastas = $request->user()->subastas()->with(['imagenes', 'categoria'])->withCount('pujas')->withMax('pujas', 'monto')->orderByDesc('fecha_inicio')->paginate(12);

        return view('auctions.mine', compact('subastas'));
    }

    public function edit(Request $request, Subasta $subasta)
    {
        $this->checkOwner($request, $subasta);
        $this->checkEditable($subasta);

        return view('auctions.form', ['subasta' => $subasta->load('imagenes'), 'categorias' => Categoria::orderBy('nombre_categoria')->get()]);
    }

    public function update(SubastaRequest $request, Subasta $subasta)
    {
        $this->checkOwner($request, $subasta);
        $paths = [];
        $removed = [];
        try {
            DB::transaction(function () use ($request, $subasta, &$paths, &$removed) {
                $locked = Subasta::whereKey($subasta->id_subasta)->lockForUpdate()->firstOrFail();
                $this->checkEditable($locked);
                $ids = $request->validated('eliminar_imagenes', []);
                $images = $locked->imagenes()->whereIn('id_imagen', $ids)->get();
                if ($images->count() !== count($ids)) {
                    abort(403);
                }
                if ($locked->imagenes()->count() - $images->count() + count($request->file('imagenes', [])) > 5) {
                    throw ValidationException::withMessages(['imagenes' => 'La subasta puede tener como máximo 5 imágenes.']);
                }
                $removed = $images->pluck('ruta')->all();
                $locked->imagenes()->whereIn('id_imagen', $ids)->delete();
                $locked->update($request->safe()->except(['imagenes', 'eliminar_imagenes']));
                $this->saveImages($locked, $request, $paths);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($paths);
            throw $exception;
        }
        Storage::disk('public')->delete($removed);

        return redirect()->route('auctions.show', $subasta)->with('status', 'Subasta actualizada.');
    }

    public function destroy(Request $request, Subasta $subasta)
    {
        $this->checkOwner($request, $subasta);
        $paths = DB::transaction(function () use ($subasta) {
            $locked = Subasta::whereKey($subasta->id_subasta)->lockForUpdate()->firstOrFail();
            if ($locked->pujas()->exists()) {
                throw ValidationException::withMessages(['subasta' => 'No puedes eliminar una subasta que recibió pujas.']);
            }
            $paths = $locked->imagenes()->pluck('ruta')->all();
            $locked->delete();

            return $paths;
        });
        Storage::disk('public')->delete($paths);

        return redirect()->route('auctions.mine')->with('status', 'Subasta eliminada.');
    }

    private function saveImages(Subasta $subasta, Request $request, array &$paths): void
    {
        foreach ($request->file('imagenes', []) as $image) {
            $path = $image->store('subastas/'.$subasta->id_subasta, 'public');
            $paths[] = $path;
            $subasta->imagenes()->create(['ruta' => $path, 'tamano' => $image->getSize()]);
        }
    }

    private function checkOwner(Request $request, Subasta $subasta): void
    {
        abort_unless($subasta->id_usuario === $request->user()->id_usuario, 403);
    }

    private function checkEditable(Subasta $subasta): void
    {
        if ($subasta->pujas()->exists() || $subasta->estado !== 'Activa') {
            throw ValidationException::withMessages(['subasta' => 'Solo puedes editar subastas activas que todavía no recibieron pujas.']);
        }
    }
}
