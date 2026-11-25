<?php

namespace App\Http\Controllers;

use App\Http\Requests\MinisterioRequest;
use App\Models\LiderIglesia;
use App\Models\Miembro;
use App\Models\Ministerio;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class MinisterioController extends Controller
{
    public function index(Request $request)
    {
        $ministerios = Ministerio::with('lider.cargo')
            ->when($request->filled('q'), function ($query) use ($request) {
                $termino = trim($request->input('q'));

                $query->where(function ($sub) use ($termino) {
                    $sub->where('nombre', 'like', '%'.$termino.'%')
                        ->orWhere('descripcion', 'like', '%'.$termino.'%')
                        ->orWhereHas('lider', fn ($l) => $l->where('nombre', 'like', '%'.$termino.'%'));
                });
            })
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view('ministerios.index', compact('ministerios'));
    }

    public function create()
    {
        $lideres = LiderIglesia::with('cargo')->orderBy('nombre')->get();
        $miembros = $this->miembrosDisponibles();
        $seleccionados = old('miembros', []);

        return view('ministerios.create', compact('lideres', 'miembros', 'seleccionados'));
    }

    public function store(MinisterioRequest $request)
    {
        DB::transaction(function () use ($request) {
            $datos = $request->validated();
            $miembros = Arr::pull($datos, 'miembros', []);

            $ministerio = Ministerio::create($datos);

            $this->asignarMiembros($ministerio, $miembros);
        });

        return redirect()->route('ministerios.index')->with('success', 'Ministerio creado con éxito.');
    }

    public function show($id)
    {
        $ministerio = Ministerio::with(['lider.cargo', 'miembros'])->withTrashed()->findOrFail($id);

        return view('ministerios.show', compact('ministerio'));
    }

    public function edit($id)
    {
        $ministerio = Ministerio::with('miembros')->withTrashed()->findOrFail($id);
        $lideres = LiderIglesia::with('cargo')->orderBy('nombre')->get();
        $miembros = $this->miembrosDisponibles($ministerio);
        $seleccionados = old('miembros', $ministerio->miembros->pluck('id')->all());

        return view('ministerios.edit', compact('ministerio', 'lideres', 'miembros', 'seleccionados'));
    }

    public function update(MinisterioRequest $request, $id)
    {
        $ministerio = Ministerio::withTrashed()->findOrFail($id);

        DB::transaction(function () use ($request, $ministerio) {
            $datos = $request->validated();
            $miembros = Arr::pull($datos, 'miembros', []);
            $sincronizar = $request->has('miembros') || $request->boolean('miembros_presente');

            $ministerio->update($datos);

            if ($sincronizar) {
                $this->asignarMiembros($ministerio, $miembros);
            }
        });

        return redirect()->route('ministerios.index')->with('success', 'Ministerio actualizado con éxito.');
    }

    public function destroy($id)
    {
        $ministerio = Ministerio::findOrFail($id);

        DB::transaction(function () use ($ministerio) {
            // Al eliminar el ministerio sus miembros quedan disponibles para
            // asignarlos a otro; la FK no actúa porque el borrado es lógico.
            Miembro::where('id_ministerio', $ministerio->id)
                ->update(['id_ministerio' => null]);

            $ministerio->delete();
        });

        return redirect()->route('ministerios.index')->with('success', 'Ministerio eliminado exitosamente.');
    }

    /**
     * Miembros que se pueden asignar: los que aún no tienen ministerio y, al
     * editar, los que ya pertenecen a este ministerio.
     */
    private function miembrosDisponibles(?Ministerio $ministerio = null)
    {
        return Miembro::whereNull('deleted_at')
            ->where(function ($query) use ($ministerio) {
                $query->whereNull('id_ministerio');

                if ($ministerio) {
                    $query->orWhere('id_ministerio', $ministerio->id);
                }
            })
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Cada miembro tiene un solo ministerio: los que se desmarcan quedan sin
     * ministerio y los seleccionados pasan a este.
     *
     * @param  array<int, int|string>  $miembros
     */
    private function asignarMiembros(Ministerio $ministerio, array $miembros): void
    {
        $ids = array_values(array_unique(array_map('intval', $miembros)));

        Miembro::where('id_ministerio', $ministerio->id)
            ->when($ids !== [], fn ($query) => $query->whereNotIn('id', $ids))
            ->update(['id_ministerio' => null]);

        if ($ids === []) {
            return;
        }

        Miembro::whereIn('id', $ids)
            ->where(fn ($query) => $query->whereNull('id_ministerio')
                ->orWhere('id_ministerio', $ministerio->id))
            ->update(['id_ministerio' => $ministerio->id]);
    }
}
