<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Resultados presidenciais de 2026, com dados do TSE, seleção por turno e estado e origem verificável.">
    <meta name="theme-color" content="#0b1220">
    <title>Brasil Apura — Resultados presidenciais 2026</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@include('dashboard.developer-modal')
<a class="skip-link" href="#resultados">Ir para os resultados</a>
<header class="site-header">
    <div class="shell header-inner">
        <a class="brand" href="{{ route('dashboard') }}" aria-label="Brasil Apura, início">
            <span class="brand-symbol" aria-hidden="true"><i></i><i></i><i></i></span>
            Brasil<span>Apura</span>
        </a>
        <nav aria-label="Navegação principal">
            <a class="active" href="#resultados">Resultados</a>
            <a href="#metodologia">Sobre os dados <span aria-hidden="true">↗</span></a>
        </nav>
        <span class="year-label">ELEIÇÕES 2026</span>
    </div>
</header>
<main class="shell">
    <section class="intro" aria-labelledby="page-title">
        <div>
            <p class="eyebrow"><span></span> PAINEL PÚBLICO DE TOTALIZAÇÃO</p>
            <h1 id="page-title">Cada voto, <span>às claras.</span></h1>
            <p class="intro-copy">Acompanhe os resultados para a Presidência da República.<br class="desktop-break"> Dados do TSE, com origem e horários identificados.</p>
        </div>
        <div class="independent-note"><span aria-hidden="true">ⓘ</span><p>Uma iniciativa independente.<br><strong>Este não é um site oficial do TSE.</strong></p></div>
    </section>

    <form class="filters" method="get" action="{{ route('dashboard', [], false) }}#resultados" aria-label="Selecionar resultado">
        @if($selectedCountry)<input type="hidden" name="country" value="{{ $selectedCountry }}">@endif
        <div class="filter-field">
            <label for="round">ELEIÇÃO PRESIDENCIAL</label>
            <select id="round" name="round">
                <option value="1" @selected($round === 1)>1º turno · 04 de outubro de 2026</option>
                <option value="2" @selected($round === 2)>2º turno · 25 de outubro de 2026</option>
            </select>
        </div>
        <div class="filter-field">
            <label for="scope">ABRANGÊNCIA DOS RESULTADOS</label>
            <select id="scope" name="scope">
                @foreach($scopes as $region)
                    <option value="{{ $region->code }}" @selected($scope === $region->code)>{{ $region->name }}{{ $region->type === 'state' ? ' · '.strtoupper($region->code) : '' }}</option>
                @endforeach
            </select>
        </div>
        <button class="button primary" type="submit">Ver resultados <span aria-hidden="true">→</span></button>
        <div class="auto-refresh"><span class="pulse"></span><span id="refresh-status" role="status">Consulta local a cada 60 s</span></div>
    </form>

    <div id="resultados" tabindex="-1" data-api="{{ route('results.api', ['round' => $round, 'scope' => $scope, 'country' => $selectedCountry]) }}">
        @include('dashboard.results')
    </div>

    <section class="methodology" id="metodologia" aria-labelledby="method-title">
        <div><p class="eyebrow">INFORMAÇÃO COM CONTEXTO</p><h2 id="method-title">Transparência em cada atualização.</h2></div>
        <div class="method-grid">
            <article><span class="method-index">01</span><h3>Direto da fonte</h3><p>O servidor consulta os arquivos oficiais do TSE. Todos os visitantes compartilham os mesmos dados armazenados.</p></article>
            <article><span class="method-index">02</span><h3>Horários separados</h3><p>Totalização, geração do arquivo e coleta são momentos distintos. Todos os horários nesta página estão em Brasília.</p></article>
            <article><span class="method-index">03</span><h3>Histórico preservado</h3><p>Novas versões não apagam as observações anteriores. Em falhas de sincronização, os últimos dados válidos permanecem identificados.</p></article>
        </div>
    </section>
</main>
<footer class="site-footer"><div class="shell footer-inner"><span>Brasil Apura <span class="footer-dot">·</span> Eleições presidenciais 2026 <span class="footer-dot">·</span> <button type="button" class="developer-about-button" data-open-developer>Desenvolvido por Erich Fournier</button></span><span>Dados: <a href="https://resultados.tse.jus.br" target="_blank" rel="noopener noreferrer">Tribunal Superior Eleitoral ↗</a><span class="footer-dot">·</span> Mapa: @if($scope === 'zz')<a href="https://www.naturalearthdata.com/" target="_blank" rel="noopener noreferrer">Natural Earth ↗</a> · <a href="https://www.geonames.org/" target="_blank" rel="noopener noreferrer">GeoNames ↗</a>@else<a href="https://servicodados.ibge.gov.br/api/docs/malhas?versao=3" target="_blank" rel="noopener noreferrer">IBGE ↗</a>@endif</span></div></footer>
</body>
</html>
