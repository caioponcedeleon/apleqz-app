<?php

namespace App\Http\Middleware;

use App\Services\TranslationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(
        protected TranslationService $translations
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->translations->localeForRequest(
            $request->session()->get('locale'),
            $request->user()?->locale
        );

        app()->setLocale($locale);

        return $next($request);
    }
}
