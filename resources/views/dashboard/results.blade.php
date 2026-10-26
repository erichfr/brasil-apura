@php
    $data = $result['data'];
    $totals = $data['totals'] ?? [];
    $candidates = $data['candidates'] ?? [];
    $progress = $totals['counted_sections_percentage'] ?? null;
    $isFinal = ($data['official_status'] ?? null) === 'f';
    $statusLabels = ['available' => 'Dados disponíveis', 'stale' => 'Dados possivelmente desatualizados', 'unavailable' => 'Dados indisponíveis', 'not_disclosed' => 'Votação ainda não divulgada', 'partial' => 'Dados parciais do país'];
@endphp
<div class="result-heading">
    <div><p class="eyebrow">{{ $round }}º TURNO <span class="text-separator">/</span> PRESIDENTE</p><h2>{{ $result['scope_name'] }} <span class="scope-code">{{ $selectedCountry ?: strtoupper($scope) }}</span></h2></div>
    <div class="source-time"><span class="status-badge {{ $result['status'] === 'stale' ? 'warning' : '' }}"><i></i>{{ $statusLabels[$result['status']] }}</span><p>{{ $selectedCountry ? 'Última totalização das localidades' : 'Totalização na fonte' }} <strong>{{ $date($data['source_reported_at'] ?? null) }}</strong></p></div>
</div>

@if($scope === 'zz')
    <div class="notice exterior-notice"><strong>{{ $selectedCountry ? 'Votos em '.$result['scope_name'] : 'Votos no exterior' }}</strong><span>{{ $selectedCountry ? 'Soma da aplicação dos resultados oficiais das localidades deste país ou território.' : 'Resultado presidencial dos eleitores que votaram fora do Brasil, consolidado pelo TSE.' }} {{ $round }}º turno · {{ $number($totals['total_votes'] ?? null) }} votos contabilizados. @if($selectedCountry){{ $result['coverage']['available'] }} de {{ $result['coverage']['expected'] }} localidades importadas.@endif</span></div>
@endif

@if($result['status'] === 'partial')
    <div class="notice warning" role="status">Importação incompleta: os totais apresentados incluem apenas as localidades disponíveis, sem estimar as restantes.</div>
@elseif($result['status'] === 'stale')
    <div class="notice warning" role="status">@if(($result['freshness_reason'] ?? null) === 'confirmation_expired')
        A confirmação automática destes dados está pendente. Última consulta bem-sucedida: {{ $date($result['last_success_at']) }}. Os números exibidos são os últimos resultados válidos armazenados.
    @elseif(($result['freshness_reason'] ?? null) === 'older_generation')
        A fonte retornou uma versão anterior à já armazenada. Mantivemos a versão mais recente disponível no painel.
    @else
        A última consulta não conseguiu confirmar a versão atual na fonte. Mantivemos os últimos resultados válidos. Última consulta bem-sucedida: {{ $date($result['last_success_at']) }}.
    @endif</div>
@elseif($result['status'] === 'unavailable')
    <div class="notice" role="status">Ainda não há resultados armazenados para esta abrangência. A ausência de informação não significa zero votos.</div>
@elseif(!($data['votes_disclosed'] ?? false))
    <div class="notice" role="status">A fonte ainda não autoriza divulgar a votação. Totais e votos individuais não são apresentados como zero.</div>
@endif

<div class="metrics">
    @foreach([['Votos contabilizados', 'total_votes', 'Votos computados na abrangência'], ['Votos válidos', 'valid_votes', 'Base dos percentuais de votos válidos'], ['Votos em branco', 'blank_votes', 'Conforme totalização oficial'], ['Votos nulos', 'null_votes', 'Inclui votos nulos técnicos']] as [$label, $key, $hint])
    <article class="metric">
        <p>{{ $label }} <span aria-hidden="true">{{ $loop->first ? '↗' : '·' }}</span></p>
        <strong>{{ $number($totals[$key] ?? null) }}</strong>
        <small>{{ $hint }}</small>
    </article>
    @endforeach
</div>

<div class="dashboard-grid">
    <section class="panel candidates-panel" aria-labelledby="candidates-title">
        <div class="panel-heading"><div><p class="eyebrow">RESULTADO PRESIDENCIAL</p><h3 id="candidates-title">Votação por candidato</h3></div><span class="muted small">{{ count($candidates) }} candidaturas</span></div>
        <p class="panel-caption">Ordenado por votos computados. Situação conforme a fonte oficial.</p>
        <div class="candidate-list">
            @forelse($candidates as $candidate)
            <article class="candidate" style="--candidate-color: {{ $color($candidate) }}">
                <div class="candidate-main">
                    <span class="candidate-number" aria-label="Número {{ $candidate['number'] }}">{{ $candidate['number'] }}</span>
                    <div class="candidate-identity"><h4>{{ $candidate['name'] }}</h4><p>{{ $candidate['party'] }} @if($candidate['official_status'])<span class="candidate-status">{{ $candidate['official_status'] }}</span>@endif</p></div>
                    <div class="candidate-share"><strong>{{ $number($candidate['valid_votes_percentage'], 2) }}{{ $candidate['valid_votes_percentage'] !== null ? '%' : '' }}</strong><span>dos votos válidos</span></div>
                </div>
                <div class="candidate-bar" aria-hidden="true"><span style="width: {{ max(0, min(100, (float) ($candidate['valid_votes_percentage'] ?? 0))) }}%"></span></div>
                <div class="candidate-detail"><span><strong>{{ $number($candidate['votes']) }}</strong> votos computados</span><span>{{ $candidate['vote_destination'] }}</span></div>
                @if($candidate['federation'])<p class="federation">{{ $candidate['federation'] }}</p>@endif
            </article>
            @empty
            <div class="empty-state"><span aria-hidden="true">—</span><h4>Votação não disponível</h4><p>Os resultados aparecerão aqui quando houver dados oficiais armazenados e divulgáveis.</p></div>
            @endforelse
        </div>
        <div class="panel-footnote">Percentuais calculados sobre os votos válidos. Destinações diferentes de “Válido” não recebem esse percentual.</div>
    </section>

    <aside class="side-panels">
        <section class="panel progress-panel" aria-labelledby="progress-title">
            <div class="panel-heading"><h3 id="progress-title">Andamento da totalização</h3><span class="tiny-label">SEÇÕES</span></div>
            <div class="progress-value"><strong>{{ $number($progress, 2) }}{{ $progress !== null ? '%' : '' }}</strong><span>{{ $data ? ($isFinal ? 'Totalização finalizada' : (($data['official_status'] ?? '') === 'n' ? 'Não iniciada' : 'Em andamento')) : 'Sem informação' }}</span></div>
            <div class="section-bar" aria-hidden="true"><span style="width: {{ max(0, min(100, (float) ($progress ?? 0))) }}%"></span></div>
            <p class="section-count"><strong>{{ $number($totals['counted_sections'] ?? null) }}</strong> de {{ $number($totals['total_sections'] ?? null) }} seções totalizadas</p>
            <div class="attendance"><div><span>Comparecimento</span><strong>{{ $number($totals['turnout'] ?? null) }}</strong></div><div><span>Abstenções</span><strong>{{ $number($totals['abstentions'] ?? null) }}</strong></div></div>
        </section>

        <section class="panel vote-composition" aria-labelledby="composition-title">
            <div class="panel-heading"><h3 id="composition-title">Composição dos votos</h3></div>
            @php
                $otherVotes = isset($totals['total_votes'], $totals['valid_votes'], $totals['blank_votes'], $totals['null_votes']) ? max(0, $totals['total_votes'] - $totals['valid_votes'] - $totals['blank_votes'] - $totals['null_votes']) : null;
                $composition = [$totals['valid_votes'] ?? null, $totals['blank_votes'] ?? null, $totals['null_votes'] ?? null, $otherVotes];
            @endphp
            <div class="composition-content">
                @if(($totals['total_votes'] ?? 0) > 0)<div class="chart-wrap"><canvas class="composition-chart" data-values="{{ json_encode($composition) }}" role="img" aria-label="Distribuição de votos. Valores na tabela ao lado."></canvas></div>@endif
                <table class="composition-table"><caption class="sr-only">Distribuição dos votos contabilizados</caption><tbody>
                    @foreach([['Válidos','#67d9cb'],['Brancos','#92a5ff'],['Nulos','#b4bfd0'],['Outras destinações','#f1c57a']] as $index => [$label,$swatch])
                    <tr><th scope="row"><i style="background: {{ $swatch }}" aria-hidden="true"></i>{{ $label }}</th><td>{{ $number($composition[$index]) }}</td></tr>
                    @endforeach
                </tbody></table>
            </div>
        </section>

        <section class="panel map-panel" aria-labelledby="map-title">
            @if($scope === 'zz')
                @include('dashboard.world-map')
            @else
            <div class="panel-heading"><div><p class="eyebrow">EXPLORE OS RESULTADOS</p><h3 id="map-title">Escolha um estado</h3></div><span aria-hidden="true">↗</span></div>
            <svg viewBox="0 0 470 450" class="brazil-map" role="group" aria-label="Mapa do Brasil. Selecione uma unidade da Federação.">
                @foreach($map as $state)
                <a href="{{ route('dashboard', ['round' => $round, 'scope' => $state['uf']], false) }}#resultados" aria-label="Resultados presidenciais de {{ strtoupper($state['uf']) }}" @if($scope === $state['uf']) aria-current="true" @endif>
                    <path d="{{ $state['path'] }}" class="{{ $scope === $state['uf'] ? 'selected' : '' }}"><title>{{ strtoupper($state['uf']) }}</title></path>
                </a>
                @endforeach
            </svg>
            <div class="map-caption"><span>Geometria oficial do IBGE</span><span>Seleção por UF</span></div>
            <div class="map-shortcuts"><a href="{{ route('dashboard', ['round' => $round, 'scope' => 'br'], false) }}#resultados">Ver Brasil</a><a href="{{ route('dashboard', ['round' => $round, 'scope' => 'zz'], false) }}#resultados"@if($scope === 'zz') aria-current="page" @endif>Ver exterior →</a></div>
            @endif
        </section>
    </aside>
</div>


<details class="source-details"><summary>Origem e rastreabilidade dos dados <span aria-hidden="true">+</span></summary>
    <dl><div><dt>{{ $selectedCountry ? 'Totalização mais recente entre localidades' : 'Totalização informada pelo TSE' }}</dt><dd>{{ $date($data['source_reported_at'] ?? null) }}</dd></div><div><dt>{{ $selectedCountry ? 'Geração mais recente entre arquivos' : 'Geração do arquivo oficial' }}</dt><dd>{{ $date($data['source_generated_at'] ?? null) }}</dd></div><div><dt>{{ $selectedCountry ? 'Coleta mais recente entre localidades' : 'Coleta deste snapshot' }}</dt><dd>{{ $date($data['collected_at'] ?? null) }}</dd></div><div><dt>Última confirmação bem-sucedida</dt><dd>{{ $date($result['last_success_at']) }}</dd></div></dl>
    @if($result['source_url'])<p><a href="{{ $result['source_url'] }}" target="_blank" rel="noopener noreferrer">Abrir arquivo JSON oficial do TSE ↗</a></p>@endif
    @if($data)<p class="hash">{{ $selectedCountry ? 'SHA-256 da relação de fontes da agregação' : 'SHA-256' }} <code>{{ $data['source_sha256'] }}</code></p>@endif
    @if($selectedCountry)
        <p>Totalizações das localidades: {{ $date($data['official_metadata']['source_reported_at_min'] ?? null) }} a {{ $date($data['official_metadata']['source_reported_at_max'] ?? null) }}. A soma não representa um snapshot simultâneo do país.</p>
        <div class="locality-table-wrap"><table class="locality-table"><caption>Localidades e arquivos oficiais incluídos</caption><thead><tr><th>Localidade</th><th>Votos</th><th>Totalização</th><th>Fonte</th></tr></thead><tbody>@foreach($result['localities'] as $locality)<tr><th scope="row">{{ $locality['name'] }}</th><td>{{ $number($locality['total_votes']) }}</td><td>{{ $date($locality['source_reported_at']) }}</td><td>@if($locality['source_url'])<a href="{{ $locality['source_url'] }}" target="_blank" rel="noopener noreferrer">JSON TSE ↗</a>@else Pendente @endif</td></tr>@endforeach</tbody></table></div>
        <p class="small muted">Correspondência cidade–país: GeoNames, com nomes revisados pela aplicação. Os resultados eleitorais são do TSE.</p>
    @endif
    <p class="small muted">Todos os horários exibidos estão no fuso de Brasília (America/Sao_Paulo).</p>
</details>
