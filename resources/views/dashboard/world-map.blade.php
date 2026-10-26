<div class="panel-heading"><div><p class="eyebrow">VOTOS NO EXTERIOR</p><h3 id="map-title">Votação por país</h3></div><span aria-hidden="true">◎</span></div>
<p class="world-map-caption">Selecione um país ou território para ver sua votação presidencial.</p>
<form class="world-country-control" method="get" action="{{ route('dashboard', [], false) }}#resultados">
    <input type="hidden" name="round" value="{{ $round }}"><input type="hidden" name="scope" value="zz">
    <label for="world-country">Selecionar país ou território</label>
    <select id="world-country" name="country">
        <option value="">Todo o exterior</option>
        @foreach($countryResults as $code => $countryResult)
            <option value="{{ $code }}" @selected($selectedCountry === $code)>{{ $countryResult['scope_name'] }} · {{ $number($countryResult['data']['totals']['total_votes'] ?? null) }} votos</option>
        @endforeach
    </select>
    <button type="submit" class="button primary country-submit">Ver votação do país →</button>
</form>
<div class="world-map-frame">
    <svg id="world-map" viewBox="0 0 720 300" class="world-map" role="group" aria-labelledby="world-map-title world-map-desc" data-selected="{{ $selectedCountry }}">
        <title id="world-map-title">Mapa mundial com países e territórios</title>
        <desc id="world-map-desc">Países com dados importados podem ser selecionados. Cores indicam disponibilidade e seleção, sem representar vencedores.</desc>
        <g class="world-grid" aria-hidden="true">
            @foreach([60,120,180,240,300,360,420,480,540,600,660] as $x)<line x1="{{ $x }}" x2="{{ $x }}" y1="0" y2="300"/>@endforeach
            @foreach([50,110,170,230,290] as $y)<line x1="0" x2="720" y1="{{ $y }}" y2="{{ $y }}"/>@endforeach
        </g>
        @foreach($worldMap as $country)
            @php $availableCountry = isset($countryResults[$country['code']]); @endphp
            @if($availableCountry)<a href="{{ route('dashboard', ['round' => $round, 'scope' => 'zz', 'country' => $country['code']], false) }}#resultados" aria-label="Votação em {{ $countryResults[$country['code']]['scope_name'] }}" @if($selectedCountry === $country['code']) aria-current="true" @endif>@endif
            @if(isset($country['path']))
            <path d="{{ $country['path'] }}" data-country="{{ $country['code'] }}" data-name="{{ $country['name'] }}" class="world-country {{ $availableCountry ? 'has-results' : '' }} {{ $selectedCountry === $country['code'] ? 'selected' : '' }}"><title>{{ $country['name'] }}</title></path>
            @else
            <circle cx="{{ $country['x'] }}" cy="{{ $country['y'] }}" r="2.8" data-country="{{ $country['code'] }}" data-name="{{ $country['name'] }}" class="world-country country-marker has-results {{ $selectedCountry === $country['code'] ? 'selected' : '' }}"><title>{{ $country['name'] }}</title></circle>
            @endif
            @if($availableCountry)</a>@endif
        @endforeach
    </svg>
    <a class="world-reset" href="{{ route('dashboard', ['round' => $round, 'scope' => 'zz'], false) }}#resultados">Todo o exterior ↺</a>
</div>
<p class="world-country-name" role="status" aria-live="polite">{{ $selectedCountry ? $result['scope_name'] : 'Clique em um país destacado ou use a lista.' }}</p>
<div class="map-caption"><span>Malha: Natural Earth · localização: GeoNames</span><span>Países e territórios</span></div>
<p class="world-data-note"><strong>{{ $selectedCountry ? 'Votos das localidades de '.$result['scope_name'].'.' : 'Exibindo o total oficial do exterior.' }}</strong> Os recortes por país são somas da aplicação sobre arquivos oficiais do TSE por localidade. Países e territórios são apresentados separadamente.</p>
@if($reconciliation && $reconciliation['matches'])
    <p class="country-reconciliation">✓ Soma conferida com o exterior · {{ $reconciliation['cities_available'] }} localidades</p>
@elseif($reconciliation && $reconciliation['cities_expected'] > 0)
    <p class="country-reconciliation warning">Conferência pendente: {{ $reconciliation['cities_available'] }} de {{ $reconciliation['cities_expected'] }} localidades importadas; totais podem refletir versões diferentes.</p>
@endif
<div class="map-shortcuts"><a href="{{ route('dashboard', ['round' => $round, 'scope' => 'br'], false) }}#resultados">← Voltar ao Brasil</a><span>{{ $round }}º turno · Exterior</span></div>
