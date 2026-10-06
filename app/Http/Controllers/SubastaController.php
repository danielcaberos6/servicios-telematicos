<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubastaRequest;
use App\Models\Subasta;
use App\Services\CatalogoService;
use App\Services\OperacionesService;
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
            'subastas' => app(CatalogoService::class)->consultar()->orderBy('fecha_fin')->limit(4)->get(),
            'totalActivas' => app(CatalogoService::class)->consultar()->count(),
            'categorias' => app(CatalogoService::class)->categorias(),
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
        $catalogo = app(CatalogoService::class);

        return view('auctions.index', ['subastas' => $catalogo->paginar($filters), 'categorias' => $catalogo->categorias()]);
    }

    public function create()
    {
        return view('auctions.form', ['subasta' => new Subasta, 'categorias' => app(CatalogoService::class)->categorias()]);
    }

    public function store(SubastaRequest $request)
    {
        $paths = [];
        try {
            $subasta = DB::transaction(function () use ($request, &$paths) {
                $subasta = app(OperacionesService::class)->crearSubasta($request->user()->id_usuario, $request->safe()->except(['imagenes', 'eliminar_imagenes']));
                $this->saveImages($subasta, $request, $paths);

                return $subasta;
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($paths);
            throw $exception;
        }

        return redirect()->route('auctions.show', $subasta)->with('status', 'Tu subasta se publicó correctamente y ya aparece en Buscar.');
    }

    public function show(Request $request, Subasta $subasta)
    {
        $subasta->load(['imagenes', 'categoria', 'usuario'])->loadCount('pujas')->loadMax('pujas', 'monto');
        $ganadora = $subasta->pujas()->with('usuario')->orderByDesc('monto')->first();

        $canViewRanking = $request->user() && ($request->user()->id_usuario === $subasta->id_usuario || $subasta->pujas()->where('id_usuario', $request->user()->id_usuario)->exists());
        $ranking = $canViewRanking ? app(OperacionesService::class)->ranking($subasta->id_subasta, $request->user()->id_usuario) : [];

        return view('auctions.show', compact('subasta', 'ganadora', 'ranking', 'canViewRanking'));
    }

    public function mine(Request $request)
    {
        $subastas = app(OperacionesService::class)->misSubastas($request->user()->id_usuario);

        return view('auctions.mine', compact('subastas'));
    }

    public function edit(Request $request, Subasta $subasta)
    {
        $this->checkOwner($request, $subasta);
        $this->checkEditable($subasta);

        return view('auctions.form', ['subasta' => $subasta->load('imagenes'), 'categorias' => app(CatalogoService::class)->categorias()]);
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
                foreach ($ids as $imageId) {
                    app(OperacionesService::class)->eliminarImagen($request->user()->id_usuario, $imageId);
                }
                app(OperacionesService::class)->actualizarSubasta($request->user()->id_usuario, $locked->id_subasta, $request->safe()->except(['imagenes', 'eliminar_imagenes']));
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
        $paths = app(OperacionesService::class)->eliminarSubasta($request->user()->id_usuario, $subasta->id_subasta);
        Storage::disk('public')->delete($paths);

        return redirect()->route('auctions.mine')->with('status', 'Subasta eliminada.');
    }

    private function saveImages(Subasta $subasta, Request $request, array &$paths): void
    {
        foreach ($request->file('imagenes', []) as $image) {
            $path = $image->store('subastas/'.$subasta->id_subasta, 'public');
            $paths[] = $path;
            app(OperacionesService::class)->guardarImagen($request->user()->id_usuario, $subasta->id_subasta, $path, $image->getSize());
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
