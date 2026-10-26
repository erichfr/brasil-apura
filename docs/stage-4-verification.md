# Verificação da etapa 4

Executada em 26/10/2026, após a validação das especificações e da configuração oficial.

- 58 abrangências atuais: 29 em cada turno (Brasil, 27 UFs, exterior).
- 58 respostas EA20 preservadas, com SHA-256 recalculado e conferido.
- 58 snapshots normalizados; nenhum erro registrado na coleta inicial.
- Primeiro turno: total nacional de 125.275.835 votos; soma UFs + exterior coincidente.
- Segundo turno: total nacional de 128.236.074 votos; soma UFs + exterior coincidente.
- A soma é somente uma verificação de integridade; o painel usa a fonte nacional diretamente.
- 33 testes automatizados aprovados, com 91 asserções.
- Migrations aplicadas no SQLite local; MySQL/MariaDB não foram testados nesta execução.

Fontes nacionais:
[Primeiro turno](https://resultados.tse.jus.br/oficial/ele2026/6257/dados/br/br-c0001-e006257-u.json)
e [segundo turno](https://resultados.tse.jus.br/oficial/ele2026/6258/dados/br/br-c0001-e006258-u.json).
Os dados podem ser revisados pela fonte após esta verificação.

O histórico de totalização entre 17h e 19h não foi reconstruído. Snapshots atuais
identificam separadamente a coleta de 26/10 e os horários anteriores da fonte.
Dados brutos e banco ficam fora do Git; este documento registra a verificação,
sem substituir a proveniência armazenada em source_payloads/sync_runs.
