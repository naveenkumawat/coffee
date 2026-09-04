<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Support\Documentation\DocumentationCatalog;
use App\Support\Documentation\DocumentationPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DocumentationController extends Controller
{
    public function __construct(
        protected DocumentationCatalog $catalog,
        protected DocumentationPresenter $presenter,
    ) {}

    public function index(Request $request): View
    {
        $panel = $this->panel($request);
        $query = trim((string) $request->query('q', ''));
        $modules = $query !== ''
            ? $this->catalog->search($panel, $query)
            : $this->catalog->modulesForRole($panel);

        return view('internal.documentation.index', [
            'panel' => $panel,
            'query' => $query,
            'grouped' => $this->presenter->groupModules($modules),
            'roleLabel' => $this->catalog->roleLabels()[$panel] ?? ucfirst($panel),
        ]);
    }

    public function show(Request $request, string $module): View
    {
        $panel = $this->panel($request);
        $doc = $this->catalog->findModule($panel, $module);

        abort_if($doc === null, 404);

        return view('internal.documentation.show', [
            'panel' => $panel,
            'module' => $doc,
            'roleLabel' => $this->catalog->roleLabels()[$panel] ?? ucfirst($panel),
            'siblings' => $this->catalog->modulesForRole($panel),
        ]);
    }

    protected function panel(Request $request): string
    {
        $panel = (string) $request->route('panel', '');

        abort_unless(in_array($panel, $this->catalog->roles(), true), 404);

        return $panel;
    }
}
