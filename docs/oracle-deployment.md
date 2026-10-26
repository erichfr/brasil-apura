# Publicação na Oracle Cloud

Estado: arquivos preparados e verificações locais concluídas. Nenhuma conta,
máquina, recurso pago ou publicação foi criada. A execução dos containers e a
validação de MariaDB em produção dependem do servidor; Docker não está instalado
neste Windows.

## 1. Criar a conta

Acesse https://www.oracle.com/cloud/free/ e conclua o cadastro pessoal.
A Oracle pode solicitar cartão para verificação de identidade. Guarde suas
credenciais e habilite autenticação em dois fatores na conta.

A oferta Always Free é diferente do crédito promocional temporário.
Confira sempre as condições e os recursos elegíveis no console:
https://docs.oracle.com/en-us/iaas/Content/FreeTier/freetier_topic-Always_Free_Resources.htm

Não é necessário escolher um serviço gerenciado de banco: MariaDB será
executado na mesma VM, dentro dos limites gratuitos da máquina.

## 2. Criar a VM

Configuração proposta, sujeita à disponibilidade gratuita na região da conta:

- Imagem: Ubuntu 24.04 LTS, arquitetura ARM64.
- Shape: VM.Standard.A1.Flex.
- Tamanho inicial: 1 OCPU e 6 GB de RAM, dentro da cota atualmente documentada.
- Disco inicial: 50 GB, dentro da franquia Always Free de volumes.
- Sub-rede pública, com IP público para a VM.
- Salve a chave SSH privada no seu computador; envie à Oracle apenas a pública.

Confira a elegibilidade gratuita antes de criar a VM. A disponibilidade de
capacidade varia, e instâncias ociosas podem ser recolhidas conforme as regras
do provedor. O backup deve ter uma cópia privada fora da VM.

Na rede Oracle, permita TCP 80 e 443 para o site. Restrinja SSH (TCP 22) ao
seu endereço IP. Banco de dados não precisa de porta pública. Verifique também
o firewall do Ubuntu; não desative regras gerais de segurança para liberar o site.
Docker publica somente as portas do gateway.

## 3. GitHub e endereço público

Crie um repositório GitHub para o código e envie o projeto.
.env, .env.production, bancos locais e respostas privadas não são versionados.
.env.production.example é somente um modelo sem credenciais.

Para HTTPS, use um domínio/subdomínio que aponte ao IP público da VM.
Uma opção de subdomínio gratuito é https://www.duckdns.org/.
O nome desejado depende de disponibilidade. Aponte seu registro ao IP da VM.
O Caddy obtém e renova o certificado automaticamente depois que DNS e portas
80/443 estiverem funcionando.

## 4. Acessar e preparar o servidor

No PowerShell do seu computador:

```powershell
ssh -i C:\caminho\chave-privada.key ubuntu@IP_PUBLICO
```

No Ubuntu, instale Git, Docker Engine e o plugin Compose seguindo a documentação:
https://docs.docker.com/engine/install/ubuntu/

Confira:

```bash
sudo docker version
sudo docker compose version
```

Os comandos seguintes usam sudo. Não é necessário conceder acesso ao Docker
a outros usuários.

Clone o repositório público, substituindo o endereço:

```bash
sudo git clone https://github.com/SEU_USUARIO/brasil-apura.git /opt/brasil-apura
cd /opt/brasil-apura
```

## 5. Gerar configuração e publicar

Substitua o domínio pelo seu endereço real:

```bash
sudo bash deploy/init.sh seu-painel.duckdns.org
sudo bash deploy/deploy.sh --sync
```

init.sh cria .env.production com permissões restritas, APP_KEY exclusiva e
senhas aleatórias. Não exibe os segredos nem sobrescreve configuração existente.

deploy.sh compila PHP e assets, espera o banco ficar saudável, aplica migrations,
cadastra eleições/turnos, coleta os dados oficiais quando --sync é informado
e inicia os serviços. A coleta de todas as localidades pode demorar alguns minutos.

Os serviços são:

| Serviço | Responsabilidade |
|---|---|
| gateway | Caddy, endereço público e HTTPS |
| app | Laravel, PHP 8.3 e Apache com raiz em public/ |
| db | MariaDB 11.4 com volume persistente |
| scheduler | Coleta automática, independente de visitantes |

Os volumes preservam banco, arquivos brutos e certificados entre reinícios e
atualizações dos containers. Os serviços reiniciam com o Docker após reboot da VM.
O banco e a aplicação não têm portas publicadas diretamente no host.
O Laravel confia nos cabeçalhos HTTPS somente do endereço interno do gateway.

## 6. Conferir o funcionamento

```bash
sudo docker compose --env-file .env.production -f compose.production.yaml ps
sudo docker compose --env-file .env.production -f compose.production.yaml logs --tail=80 gateway app scheduler
sudo docker compose --env-file .env.production -f compose.production.yaml exec -u www-data app php artisan tse:results --round=2 --scope=br
```

Acesse o domínio HTTPS, confira o endpoint /up e os filtros dos dois turnos,
UFs, exterior e países. Teste novamente após reiniciar os serviços para confirmar
persistência. Confirme que a última consulta bem-sucedida continua sendo renovada.

A publicação só deve ser considerada verificada depois desses testes no servidor.
O workflow .github/workflows/checks.yml está preparado para testar PHP com SQLite
e MariaDB e compilar assets no GitHub; ainda precisa executar no repositório remoto.

## 7. Backup e atualizações

Antes de atualizar:

```bash
cd /opt/brasil-apura
sudo bash deploy/backup.sh
sudo git pull --ff-only
sudo bash deploy/deploy.sh
```

O backup inclui dump consistente do banco, respostas brutas, configuração privada
e identificação do commit. A coleta é pausada durante o backup e retomada se
estava ativa. O diretório backups/ tem permissões restritas e fica fora do Git.
Copie o backup para armazenamento privado fora da VM, por exemplo seu computador.

A chave e as senhas de produção permanecem no .env.production existente.
Os comandos de atualização não substituem esse arquivo nem apagam volumes.
Guarde a configuração para restauração; APP_KEY não deve ser regenerada em
atualizações normais.

Se houver falha de migration, examine os logs antes de prosseguir.
Para recuperar, use o backup em um banco de restauração separado e confira
dados/arquivos antes de trocar o serviço em uso.

## Verificações já feitas localmente

- Sintaxe dos scripts shell.
- Geração de configuração em diretório isolado e recusa de sobrescrita.
- Compose contra o schema oficial; somente o gateway expõe portas.
- YAML do workflow.
- Testes PHP, incluindo cabeçalhos HTTPS de proxy confiável e rejeição dos demais.
- Execução real de containers e emissão do certificado: pendentes da VM.
