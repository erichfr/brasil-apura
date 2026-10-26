<?php

namespace App\Http\Controllers;

use App\Models\Scope;
use App\Services\Tse\TseCountryResultsService;
use App\Services\Tse\TseStoredResultsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    private function selection(Request $request): array
    {
        $selection = $request->validate([
            'round' => ['sometimes', 'integer', Rule::in([1, 2])],
            'scope' => ['sometimes', 'string', Rule::in(config('tse.scopes'))],
            'country' => ['nullable', 'string', 'size:3', Rule::exists('abroad_cities', 'country_code')],
        ]);

        return [(int) ($selection['round'] ?? 2), $selection['scope'] ?? 'br', ($selection['scope'] ?? 'br') === 'zz' ? ($selection['country'] ?? null) : null];
    }

    private function data(Request $request, TseStoredResultsService $results): array
    {
        [$round, $scope, $selectedCountry] = $this->selection($request);
        $overview = $scope === 'zz' ? app(TseCountryResultsService::class)->overview($round) : ['countries' => [], 'reconciliation' => null];
        $countryResults = $overview['countries'];
        $reconciliation = $overview['reconciliation'];
        abort_if($selectedCountry && ! isset($countryResults[$selectedCountry]), 422, 'País sem cadastro neste turno.');
        $result = $selectedCountry ? $countryResults[$selectedCountry] : $results->get($round, $scope);
        $scopes = Scope::whereIn('code', config('tse.scopes'))->orderByRaw("CASE WHEN code = 'br' THEN 0 WHEN code = 'zz' THEN 2 ELSE 1 END")->orderBy('name')->get();
        $number = fn ($value, $digits = 0) => $value === null ? '—' : number_format((float) $value, $digits, ',', '.');
        $date = fn ($value) => $value ? CarbonImmutable::parse($value)->setTimezone('America/Sao_Paulo')->format('d/m/Y · H:i:s') : 'Não disponível';
        $palette = ['#67d9cb', '#92a5ff', '#f1c57a', '#e7a0c9', '#7cc4f0', '#bbabeb'];
        $color = fn ($candidate) => $palette[((int) $candidate['number']) % count($palette)];
        $worldMap = $scope === 'zz' ? json_decode(file_get_contents(public_path('data/world-map.json')), true, 128, JSON_THROW_ON_ERROR) : [];
        $map = json_decode(file_get_contents(public_path('data/brazil-map.json')), true, 128, JSON_THROW_ON_ERROR);

        return compact('result', 'round', 'scope', 'scopes', 'number', 'date', 'color', 'map', 'worldMap', 'selectedCountry', 'countryResults', 'reconciliation');
    }

    public function index(Request $request, TseStoredResultsService $results)
    {
        return view('dashboard.index', $this->data($request, $results));
    }

    public function api(Request $request, TseStoredResultsService $results)
    {
        $data = $this->data($request, $results);

        return response()->json([
            'result' => $data['result'],
            'html' => view('dashboard.results', $data)->render(),
        ])->header('Cache-Control', 'no-store');
    }
}
