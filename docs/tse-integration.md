# Integração TSE — verificação de 26/10/2026

## Referências oficiais consultadas

- [Informações técnicas 2026 e FAQ](https://www.tse.jus.br/eleicoes/informacoes-tecnicas-sobre-a-divulgacao-de-resultados)
- [EA20 — resultado unificado, versão identificada como 13/10/2026](https://www.tse.jus.br/eleicoes/eleicoes-2026-content/arquivos/divulgacao-de-resultados/tse-ea20-arquivo-de-resultado-unificado)
- [EA11 — configuração](https://www.tse.jus.br/eleicoes/eleicoes-2026-content/arquivos/divulgacao-de-resultados/tse-ea11-arquivo-de-configuracao-de-eleicoes)
- [Instruções de download 2026, versão 1.1 de 19/10/2026](https://www.tse.jus.br/eleicoes/eleicoes-2026-content/arquivos/divulgacao-de-resultados/tse-instrucoes-para-download-dos-arquivos-da-divulgacao-2026)
- [Configuração oficial EA11](https://resultados.tse.jus.br/oficial/comum/config/ele-c.json)

Os códigos federais 6257/6258, os pleitos 3220/3221, o ciclo ele2026, os turnos,
as datas e o cargo 1 foram conferidos contra o EA11 recebido por HTTPS.
O catálogo bruto é preservado e seu SHA-256 acompanha cada snapshot.

## URLs de resultados

```text
https://resultados.tse.jus.br/oficial/ele2026/6257/dados/br/br-c0001-e006257-u.json
https://resultados.tse.jus.br/oficial/ele2026/6258/dados/br/br-c0001-e006258-u.json
```

Para as UFs e exterior, substituir os dois componentes br por sigla em
minúsculas ou zz. Apenas os turnos e as abrangências da lista interna são aceitos.
Nenhuma rota recebe uma URL arbitrária de visitantes.

## Mapeamento e distinções

| Dado normalizado | Campo EA20 |
|---|---|
| Identidade da eleição/turno | ele / t |
| Fase oficial | f=o; simulado é rejeitado |
| Abrangência | tpabr / cdabr |
| Identificador da geração | idg; não é usado como contador monotônico |
| Geração do arquivo | dg + hg |
| Referência da totalização | dt + ht |
| Divulgação de votação | dv |
| Andamento | and |
| Totalização final | tf, preservado separadamente de and |
| Total de votos | v.tv |
| Votos válidos | v.vv |
| Brancos | v.vb |
| Total nulos, incluindo técnicos | v.tvn |
| Comparecimento / abstenção | e.c / e.a |
| Seções totais / totalizadas | s.ts / s.st |
| Percentual de seções | s.pstn, com fallback para s.pst |
| Candidaturas | carg[cd=1].agr[].par[].cand[] |
| Identificador / nome de urna / número | sqcand / nmu / n |
| Votos do candidato | vap |
| Situação e destinação | st / dvt |
| Federação | fed[], relacionada pelo par.nfed |

pvap/pvapn usa v.vvc como denominador. O valor original é preservado em
official_metadata, com identificação da base. valid_votes_percentage é calculado
com BCMath sobre v.vv apenas para destinação Válido e denominador maior que zero.
Esse percentual calculado é truncado em quatro casas decimais; a interface pode
formatá-lo sem substituir o percentual oficial por um rótulo incorreto.
Votos com destinações diferentes continuam registrados, sem percentual de válidos.

Horários da fonte são interpretados no fuso America/Sao_Paulo, configurado
explicitamente para apresentação e conversão. A resposta original permanece
intacta para auditoria dessa interpretação. As três datas — totalização,
geração e coleta — são armazenadas separadamente em UTC.

dv=n gera snapshot com indicação de divulgação suspensa, totais de votos nulos
no banco (NULL, não zero) e nenhuma votação individual publicada.
Campos opcionais ausentes permanecem NULL. Situação oficial não é inferida a
partir do percentual de seções nem da posição no ranking.
Fotos ainda não são baixadas; esse recurso será tratado com o dashboard.

## Fluxo operacional

1. O comando confirma o catálogo oficial (cache por uma hora).
2. Cada abrangência tem bloqueio compartilhado e intervalo mínimo de consulta.
3. O transporte usa HTTPS, timeout, tamanho máximo e bloqueia redirecionamentos.
4. Arquivos JSON recebidos são preservados por hash antes da validação semântica.
5. Normalização e gravação do snapshot ocorrem em transação.
6. A referência ao último snapshot é promovida somente para uma geração mais nova
   ou para o mesmo snapshot já confirmado.
7. Versões antigas ou conflitantes com o mesmo horário são arquivadas sem
   substituir a versão atual. Falhas preservam o último resultado válido.
8. A leitura usa somente banco/cache e não consulta o TSE.

A transação não modifica observações anteriores. Nomes, partido, federação,
situação e percentuais da observação também são preservados nos metadados de
cada resultado individual. Payloads idênticos não criam snapshots duplicados.
Uma resposta 304 atualiza a última confirmação bem-sucedida sem mudar a coleta
original do snapshot. Todos os acessos tentados após os controles locais são
registrados em sync_runs, inclusive erros e respostas sem alteração.

## Frequência e disponibilidade

A FAQ publica um teto de 100 requisições por segundo por IP e informa bloqueio
temporário de 10 minutos, potencialmente renovável, para excesso de consultas.
Também alerta para bloqueios por 404 repetidos. Não estabelece polling numérico
definitivo. A aplicação adota política conservadora própria:

- Até 10 acessos por segundo no limitador compartilhado e transporte serializado.
- No mínimo 60 segundos por arquivo durante totalização.
- No mínimo uma hora por arquivo com andamento finalizado.
- HTTP 403/429: pausa global de pelo menos 10 minutos, respeitando Retry-After.
- HTTP 404: pausa de 10 minutos para o arquivo.
- Erros de conexão/HTTP: espera crescente entre tentativas futuras, até uma hora.
- ETag e Last-Modified quando informados; 304 não gera um novo snapshot.

O Scheduler verifica os trabalhos a cada minuto, mas cada arquivo só é consultado
quando seu intervalo vence. Não há nova chamada externa por visitante.
Não é necessário manter uma fila para este coletor sequencial; o lock impede
execuções simultâneas do mesmo trabalho em instâncias com cache compartilhado.

## Histórico temporal

Nos documentos e endpoints oficiais examinados não foi identificado um endpoint
de versões históricas de EA20 em intervalos de cinco minutos. Isso não prova que
outros arquivos históricos oficiais não existam. O feed examinado fornece o
estado corrente e não permite reconstruir retroativamente a janela 17h–19h.

As coletas de 26/10/2026 registram o que foi recebido nessa data, com os horários
anteriores que constam na fonte. Não são observações coletadas em 04/10 ou 25/10.
Nenhuma série temporal retroativa foi criada. Importação histórica verificada
permanece para a etapa 6.
