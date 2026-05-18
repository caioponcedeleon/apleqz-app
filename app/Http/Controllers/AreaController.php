<?php

namespace App\Http\Controllers;

use App\Http\Requests\AreaRequest;
use App\Models\Area;
use Illuminate\Http\RedirectResponse;

class AreaController extends Controller
{
    public function store(AreaRequest $request): RedirectResponse
    {
        $this->authorize('create', Area::class);

        $request->user()->areas()->create($request->validated());

        return back()->with('success', __('app.flash.area_created'));
    }

    public function update(AreaRequest $request, Area $area): RedirectResponse
    {
        $this->authorize('update', $area);

        $area->update($request->validated());

        return back()->with('success', __('app.flash.area_updated'));
    }

    public function destroy(Area $area): RedirectResponse
    {
        $this->authorize('delete', $area);

        if ($area->applications()->exists()) {
            return back()->with('error', __('app.flash.area_in_use'));
        }

        $area->delete();

        return back()->with('success', __('app.flash.area_deleted'));
    }
}
