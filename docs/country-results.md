# Votação por país e território

A aplicação importa os arquivos EA20 de cada localidade do exterior e soma suas
contagens por país/território. Esse recorte é uma agregação da aplicação sobre
dados oficiais, não um arquivo nacional do TSE republicado como se fosse um
resultado oficial por país.

## Fontes e geografia

- Cadastro de localidades EA12 de cada turno, ambiente oficial do TSE.
- Votos presidenciais EA20: dados/zz/zz{codigo5}-c0001-e{eleicao6}-u.json.
- Correspondência geográfica: [GeoNames cities1000](https://download.geonames.org/export/dump/cities1000.zip)
  e [countryInfo](https://download.geonames.org/export/dump/countryInfo.txt),
  sob [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/).
- Países e territórios são mantidos separadamente, incluindo Guiana Francesa e Hong Kong.
- Contornos: Natural Earth. Localidades de territórios sem contorno nessa escala
  são representadas por pontos geográficos.

resources/data/abroad-cities.json contém 186 localidades, código TSE, nome,
país/território, coordenadas, identificador e URL GeoNames, método de
correspondência e hashes dos arquivos geográficos consultados. Homônimos e
variantes de nomes foram revisados pela aplicação; o agrupamento geográfico
não é atribuído a um campo de país do EA12. Nomes em português usam códigos
regionais para apresentação. Dados eleitorais vêm exclusivamente de 2026.

Foram examinados também cadastros eleitorais anteriores e planilhas do TRE-DF
durante a investigação. Eles não são usados como resultados eleitorais nem
como fonte da correspondência final. O cadastro de 2026 e o GeoNames são as
referências do manifesto versionado.

## Uso

```powershell
php artisan migrate
php artisan tse:sync-abroad
php artisan tse:sync-abroad --round=2 --country=PRT
```

TSE_SYNC_ENABLED deve estar true. Primeiro configure eleições/turnos com migrate
--seed em uma instalação nova. O comando confere o catálogo do turno e recusa
cidades desconhecidas ou com nomes divergentes do manifesto antes da coleta.

O Scheduler executa esse comando a cada cinco minutos. Arquivos finalizados
respeitam a espera mínima de uma hora; arquivos sem alteração e respostas 304
não duplicam snapshots. Cache e limites são compartilhados com o coletor geral.

Exemplo de página:
`/?round=2&scope=zz&country=PRT#resultados`.

A API interna aceita os mesmos filtros:
`/api/results?round=2&scope=zz&country=PRT`.
Ela consulta apenas banco/cache.

## Cálculos e rastreabilidade

Votos, seções e eleitorado são somados; percentuais são recalculados sobre os
totais agregados, sem média aritmética dos percentuais das localidades.
Os nomes e votos por candidato são mantidos com a identificação das fontes.

Cobertura informa localidades disponíveis/esperadas. Uma cidade ausente não é
convertida em zero: o país aparece como parcial ou indisponível. A listagem
de localidades mantém o valor ausente como NULL. Zero só é exibido se observado.

As fontes têm horários próprios. O país exibe os extremos de totalização e os
horários mais recentes de geração/coleta, sem afirmar simultaneidade. A última
confirmação corresponde à mais antiga entre as localidades incluídas.
O hash apresentado para o país é o hash da relação de fontes, não de um
suposto arquivo oficial de país. Os arquivos brutos individuais ficam preservados
e seus links oficiais aparecem na tabela de rastreabilidade.

## Verificação em 26/10/2026

- 186 localidades importadas em cada turno: 372 respostas/snapshots adicionais.
- 134 países e territórios no agrupamento geográfico.
- Nos dois turnos, a soma das localidades coincide com o exterior em votos
  totais, válidos, brancos, nulos, comparecimento, abstenções, seções e votos de
  cada candidato, sem diferenças.
- Portugal, segundo turno: 59.040 votos contabilizados e 57.752 válidos,
  provenientes de Faro, Lisboa e Porto.
- Observações históricas entre 17h e 19h não foram recuperadas por essa importação.

Dados oficiais podem ser revisados; a aplicação repete a conferência e informa
quando a cobertura está incompleta ou os arquivos refletem versões divergentes.
