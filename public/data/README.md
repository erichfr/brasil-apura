# Malhas do mapa\n\nFonte: Instituto Brasileiro de Geografia e Estatística (IBGE), API de malhas v3.\nColeta: 26/10/2026.\n\nEndpoints por UF: https://servicodados.ibge.gov.br/api/v3/malhas/estados/{codigo}?formato=application/vnd.geo+json&qualidade=minima\n\nDocumentação: https://servicodados.ibge.gov.br/api/docs/malhas?versao=3\n\nbrazil-states.geojson reúne as 27 respostas estaduais. brazil-map.json deriva os contornos em projeção de Mercator para uso local no SVG. O mapa é neutro: mostra abrangência selecionada, sem inferir vitória por UF. O filtro textual oferece seleção equivalente.\n
## Mapa mundial

Fonte: Natural Earth, Admin 0 Countries 1:110m (domínio público).
GeoJSON de origem: https://raw.githubusercontent.com/nvkelso/natural-earth-vector/master/geojson/ne_110m_admin_0_countries.geojson
Documentação: https://www.naturalearthdata.com/downloads/110m-cultural-vectors/110m-admin-0-countries/
Coleta: 26/10/2026. world-map.json deriva contornos em projeção equirretangular, sem Antártida, preservando nomes e códigos da fonte.
A seleção de países com localidades importadas filtra os votos presidenciais. GeoNames fornece coordenadas e correspondência geográfica (CC BY 4.0): https://www.geonames.org/ . Pontos representam territórios com localidades que não têm contorno nesta escala. As cores indicam disponibilidade e seleção, sem indicar vencedor.
