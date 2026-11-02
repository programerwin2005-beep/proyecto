<?php

namespace App\Http\Controllers;

use App\Http\Requests\MinisterioRequest;
use App\Models\LiderIglesia;
use App\Models\Miembro;
use App\Models\Ministerio;
use App\Support\VinculoPivote;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class MinisterioController extends Controller
{
    public function index()
    {
        $ministerios = Ministerio::with('lider.cargo')->paginate(10);

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

            $this->sincronizarMiembros($ministerio, $miembros);
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
        $miembros = $this->miembrosDisponibles();
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
                $this->sincronizarMiembros($ministerio, $miembros);
            }
        });

        return redirect()->route('ministerios.index')->with('success', 'Ministerio actualizado con éxito.');
    }

    public function destroy($id)
    {
        $ministerio = Ministerio::findOrFail($id);
        $ministerio->delete();

        return redirect()->route('ministerios.index')->with('success', 'Ministerio eliminado lógicamente.');
    }

    /**
     * Miembros que se pueden asignar al ministerio.
     */
    private function miembrosDisponibles()
    {
        return Miembro::whereNull('deleted_at')->orderBy('nombre')->get();
    }

    /**
     * La composición vive solo en el pivote. `fecha_inicio` se conserva al
     * restaurar un vínculo que había sido desactivado.
     *
     * @param  array<int, int|string>  $miembros
     */
    private function sincronizarMiembros(Ministerio $ministerio, array $miembros): void
    {
        VinculoPivote::sincronizar(
            $ministerio->miembros(),
            $miembros,
            [
                'fecha_inicio' => now()->toDateString(),
                'fecha_fin' => null,
            ],
            ['fecha_inicio']
        );
    }
}
