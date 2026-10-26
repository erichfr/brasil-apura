# Brasil Apura

Painel independente da totalização presidencial de 2026. Não é um sistema oficial do TSE.

## Estado atual

Etapa 4 concluída: ingestão real dos arquivos oficiais EA11/EA20, armazenamento
bruto, normalização, snapshots e leitura local implementados. A coleta inicial
verificou 58 recortes: Brasil, 27 UFs e exterior nos dois turnos.
O dashboard está disponível na página inicial, com filtros por turno e UF, mapa IBGE, gráficos e API interna. A análise histórica aguarda observações verificadas.
Nenhum resultado eleitoral é incluído nos seeders.

## Requisitos

Windows, PHP 8.3+ com BCMath, PDO MySQL, cURL, OpenSSL, mbstring, XML e fileinfo;
Composer 2, Node.js 22+, npm e MySQL 8 ou MariaDB compatível.
Redis é opcional: cache, sessões e filas usam banco inicialmente.

## Instalação no Windows

No PowerShell:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm.cmd ci
```

Crie um banco vazio chamado brasil_apura e um usuário restrito a esse banco.
Preencha DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME e DB_PASSWORD no .env.

```powershell
php artisan migrate --seed
npm.cmd run build
php artisan serve --port=8080
```

Acesse http://127.0.0.1:8080 para visualizar os resultados presidenciais. Para desenvolver
assets, use npm.cmd run dev em outro terminal.

Na cópia local criada pelo instalador, .env usa SQLite para execução e
verificação isolada. Os resultados coletados estão nesse banco local ignorado
pelo Git. MySQL está configurado no .env.example; ajuste seu .env e execute as
migrations no banco desejado. Não houve migração automática do banco local
para MySQL nem teste com credenciais MySQL de produção.

## Coleta e consulta dos resultados

No .env, habilite TSE_SYNC_ENABLED=true. O exemplo deixa a coleta desativada
para evitar acessos externos antes da configuração do banco.

```powershell
php artisan tse:status
php artisan tse:sync
php artisan tse:sync --round=2 --scope=sp
php artisan tse:sync --all-scopes
php artisan tse:sync-abroad
php artisan tse:results --round=2 --scope=br
php artisan tse:results --round=1 --scope=sp --json
```

tse:sync sem opções consulta Brasil nos dois turnos. --round e --scope aceitam
múltiplas ocorrências. --all-scopes inclui Brasil, UFs e exterior. Opções
inválidas são recusadas antes do início do lote. Não há opção para URLs externas.

tse:results lê apenas banco/cache, sem acessar o TSE. Exibe horário da fonte,
geração, coleta e última confirmação separadamente. A saída JSON poderá alimentar
a API interna na próxima etapa. Ausência de dados não é apresentada como zero.

## Atualização automática

No desenvolvimento, execute em terminal separado:

```powershell
php artisan schedule:work
```

O Scheduler agenda tse:sync --all-scopes a cada minuto quando a coleta está
habilitada. Cada arquivo respeita seu intervalo: no mínimo 60 segundos durante
totalização e uma hora após andamento finalizado. Não é necessário deixar uma
fila aberta para esse coletor sequencial. Os locks exigem cache compartilhado
quando houver mais de uma instância.

Parâmetros no .env:

| Variável | Padrão |
|---|---|
| TSE_SYNC_ENABLED | false |
| TSE_POLL_SECONDS | 60 |
| TSE_FINAL_POLL_SECONDS | 3600 |
| TSE_TIMEOUT_SECONDS | 15 |
| TSE_CONNECT_TIMEOUT_SECONDS | 5 |
| TSE_STALE_AFTER_SECONDS | 180 |
| TSE_MAX_BODY_BYTES | 10485760 |

A leitura marca dados como possivelmente desatualizados após falha ou ao vencer
a tolerância. Para arquivos finalizados, a tolerância considera o intervalo de
uma hora mais TSE_STALE_AFTER_SECONDS. Falhas não apagam dados válidos.
Tentativas e erros são registrados em sync_runs e storage/logs.

## Fonte e rastreabilidade

- [Documentação oficial TSE](https://www.tse.jus.br/eleicoes/informacoes-tecnicas-sobre-a-divulgacao-de-resultados)
- [Mapeamento e decisões de integração](docs/tse-integration.md)
- [Verificação da coleta inicial](docs/stage-4-verification.md)

Cada versão guarda SHA-256, horários e proveniência. Bytes recebidos ficam em
storage/app/private/tse. O catálogo EA11 é preservado e validado antes dos
resultados. Snapshots não são sobrescritos durante novas sincronizações.
Respostas repetidas não duplicam o histórico. ETag, Last-Modified e 304 são
tratados sem alterar a coleta original da observação.

Horários são armazenados em UTC e interpretados/apresentados em Brasília.
O resultado nacional vem do arquivo Brasil, que inclui exterior; não é
recalculado pela soma de UFs.

## Histórico das 17h às 19h

Não foi identificado endpoint de versões temporais nas fontes oficiais
examinadas. Isso não comprova inexistência de outros arquivos oficiais.
Coletas atuais não recuperam observações passadas. Nenhuma série retroativa
foi criada. Futuras importações exigirão origem, evidências e hash.
Variação entre acumulados não indica horário de votação.

## Verificações

```powershell
php artisan test
php vendor/bin/pint --test
php artisan schedule:list
```

Testes usam SQLite em memória e HTTP simulado, sem alterar os resultados locais
ou consultar o TSE. As fixtures são sintéticas e exclusivas dos testes.
A coleta real é verificada separadamente contra os arquivos oficiais.
O endpoint /up verifica o início do Laravel.

## GitHub e produção

O repositório pode ser público. Nunca publique .env, senhas, chaves, vendor,
node_modules, bancos locais ou arquivos privados. Versione composer.lock e
package-lock.json. Novas instalações obtêm dados executando a sincronização.

GitHub Pages não executa Laravel. O painel exige hospedagem PHP com banco,
HTTPS, armazenamento persistente e execução do Scheduler.

1. Configure o document root em public/ e HTTPS.
2. Use APP_ENV=production, APP_DEBUG=false e APP_URL com o domínio.
3. Gere uma APP_KEY exclusiva e configure credenciais fora do repositório.
4. Execute composer install --no-dev --optimize-autoloader.
5. Compile assets com npm ci e npm run build.
6. Execute php artisan migrate --force e php artisan db:seed --force para a configuração inicial.
7. Habilite a coleta, execute tse:sync --all-scopes e php artisan optimize.
8. Programe php artisan schedule:run a cada minuto usando o agendador da hospedagem.
9. Configure backups do banco e dos arquivos brutos e Redis compartilhado se houver múltiplas instâncias.

Não existem endpoints administrativos públicos. GitHub remoto e hospedagem
ainda não foram publicados.

## Resultados por país

O filtro Exterior permite selecionar países e territórios pelo mapa ou pela lista.
Foram importadas as 186 localidades oficiais dos dois turnos (372 snapshots),
agrupadas em 134 países e territórios, com conferência contra o exterior.
Os percentuais são recalculados sobre as somas dos votos válidos.
[Metodologia, fontes geográficas e operação](docs/country-results.md).

## Publicação na Oracle

A configuração de produção usa Docker Compose com aplicação, MariaDB persistente,
Caddy para HTTPS e coletor automático. Siga o [guia de publicação](docs/oracle-deployment.md).
Os arquivos foram preparados; a execução real depende de criar a conta e a VM.
