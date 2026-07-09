<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplicationMomentRequest;
use App\Models\Application;
use App\Models\ApplicationMoment;
use Illuminate\Http\RedirectResponse;

class ApplicationMomentController extends Controller
{
    public function store(ApplicationMomentRequest $request, Application $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $sortOrder = (int) $application->moments()->max('sort_order') + 1;

        $application->moments()->create([
            ...$request->momentAttributes(),
            'sort_order' => $sortOrder,
            'is_system' => false,
        ]);

        return back()->with('success', __('app.flash.moment_created'));
    }

    public function update(
        ApplicationMomentRequest $request,
        Application $application,
        ApplicationMoment $moment,
    ): RedirectResponse {
        $this->authorize('update', $application);
        abort_unless($moment->application_id === $application->id && ! $moment->is_system, 404);

        $moment->update($request->momentAttributes());

        return back()->with('success', __('app.flash.moment_updated'));
    }

    public function destroy(Application $application, ApplicationMoment $moment): RedirectResponse
    {
        $this->authorize('update', $application);
        abort_unless($moment->application_id === $application->id && ! $moment->is_system, 404);

        $moment->delete();

        return back()->with('success', __('app.flash.moment_deleted'));
    }
}
