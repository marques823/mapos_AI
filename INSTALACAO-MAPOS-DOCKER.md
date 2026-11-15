# Instalação do MapOS via Docker — guia para máquina nova

Este documento descreve como colocar o **MapOS** (sistema de OS da Técnico Litoral) para
rodar em **Docker** numa máquina nova, com hardware diferente. Foi validado em produção
(`tiagoserver`, 10.10.10.2) e numa VM de teste.

> Contato/contexto: mantido em `/opt/mapos-docker`. Ajuste caminhos/portas conforme a máquina.

---

## 1. Arquitetura da stack

| Serviço        | Imagem / build                         | Função |
|----------------|----------------------------------------|--------|
| `nginx`        | `nginx:alpine`                         | Web (publica a porta) |
| `php-fpm`      | **build** `./docker/etc/php/Dockerfile` → `php:8.4-fpm-bookworm` | PHP 8.4 + extensões + supervisor (`php-fpm`+`cron`) |
| `mysql`        | **mariadb:11** (via override)          | Banco |
| `phpmyadmin`   | `phpmyadmin/phpmyadmin`                | Administração do banco |
| `composer`     | **build** `./docker/etc/composer`      | Instala `application/vendor` (roda uma vez) |

Código-fonte montado em `/var/www/html` (bind `./`). O banco fica em `./docker/data/db/mysql`
(bind) — em produção troque por disco com espaço.

---

## 2. Pré-requisitos

- Ubuntu 22.04/24.04 (ou Debian). Recomendado 24.04.
- **Docker Engine + plugin `compose`**:
  ```bash
  curl -fsSL https://get.docker.com | sh
  sudo usermod -aG docker "$USER"   # relogar depois
  docker compose version            # deve responder v2.x
  ```
- `git` (se for clonar o código), ~5 GB livres em disco, 2 vCPU / 2 GB RAM (mínimo).

---

## 3. Obter o código-fonte

**Opção A — a partir do backup do servidor** (`config/mapos-lampp.tar.gz` dentro do snapshot):
```bash
# o snapshot já vem baixado pelo restore.sh em $WORKDIR/<versão>/
mkdir -p /opt/mapos-docker
tar xzf /caminho/do/snapshot/config/mapos-lampp.tar.gz -C /tmp
cp -a /tmp/mapos /opt/mapos-docker
```

**Opção B — do repositório Git** (se a máquina tiver acesso):
```bash
git clone <URL_DO_REPO_MAPOS> /opt/mapos-docker
```

O diretório já contém: `docker-compose.yml`, `docker/` (nginx/php/composer), `.env`,
`banco.sql` (schema base), `application/`, `index.php`.

---

## 4. Configuração

### 4.1 Trocar MySQL por **MariaDB** (paridade com o banco atual)
Crie `docker-compose.override.yml`:
```yaml
services:
  mysql:
    image: mariadb:11
```
> Motivo: o banco de origem é MariaDB; mantendo `mariadb:11` o dump importa **sem** ajustes
> de `DEFINER`/collation.

### 4.2 `.env` (raiz) — portas e credenciais do banco
Ajuste conforme a máquina (evite portas já em uso):
```ini
NGINX_PORT=9980            # porta pública do MapOS
PHP_MY_ADMIN_PORT=8081     # phpMyAdmin
MYSQL_MAPOS_PORT=3307      # porta do banco exposta no host
MYSQL_MAPOS_HOST=mysql     # nome do serviço (NÃO mudar)
MYSQL_MAPOS_DATABASE=mapos
MYSQL_MAPOS_ROOT_PASSWORD=<senha_root>
MYSQL_MAPOS_USER=mapos
MYSQL_MAPOS_PASSWORD=<senha_mapos>
```

### 4.3 ⚠️ `application/.env` — **URL base** (pegadinha do MapOS)
O MapOS carrega **`application/.env`** (não o `.env` da raiz). É lá que fica o `APP_BASEURL`:
```ini
APP_BASEURL="https://SEU-DOMINIO.com"     # ex.: https://mapos.tecnicolitoral.com
APP_ENCRYPTION_KEY="<sua-chave>"          # deve ser a MESMA da origem
```
Se `APP_BASEURL` ficar com `localhost` (ou ausente), o CodeIgniter **autodetecta host:porta**
e gera redirects errados (ex.: `https://dominio:9980/...`). Defina a URL pública correta.

---

## 5. Subir a stack
```bash
cd /opt/mapos-docker
docker compose -p mapos build       # builda php-fpm e composer (1ª vez; alguns minutos)
docker compose -p mapos up -d
docker compose -p mapos ps          # todos "running"
```
O serviço `composer` instala `application/vendor` automaticamente. Se precisar rodar manualmente:
```bash
docker compose -p mapos run --rm composer install --ignore-platform-reqs --no-scripts
```

> Dica: use um **nome de projeto** (`-p mapos`) e, se houver outro MapOS na mesma máquina,
> prefixe os `container_name` (via override) para evitar conflitos.

---

## 6. Banco de dados

**Instalação nova (banco vazio):** o container cria `mapos` e o usuário a partir do `.env`.
Para criar só o schema: importe `banco.sql` dentro do container.

**Migrando de outra máquina (recomendado):** na origem, gere o dump e importe no container:
```bash
# ORIGEM (no servidor antigo). Se o root usar unix_socket, rode com sudo:
sudo mariadb-dump --single-transaction --quick --routines --triggers mapos > /tmp/mapos.sql

# DESTINO (na máquina nova):
docker exec -i mysql mariadb -uroot -p"$MYSQL_MAPOS_ROOT_PASSWORD" \
  -e 'DROP DATABASE IF EXISTS mapos; CREATE DATABASE mapos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
docker exec -i mysql mariadb -uroot -p"$MYSQL_MAPOS_ROOT_PASSWORD" mapos < /tmp/mapos.sql

# conferir
docker exec mysql mariadb -uroot -p"$MYSQL_MAPOS_ROOT_PASSWORD" -N -e \
  'SELECT COUNT(*) FROM mapos.clientes;'
```

---

## 7. Publicar (Cloudflare Tunnel ou proxy reverso)

- **Cloudflare Tunnel**: aponte a rota do domínio para **`http://127.0.0.1:${NGINX_PORT}`**
  (ex.: `http://127.0.0.1:9980`). O TLS é terminado na Cloudflare.
- **Proxy reverso (nginx/traefik)**: faça proxy para `http://127.0.0.1:${NGINX_PORT}`.

Se o app for acessado por HTTPS atrás de proxy, confirme que `APP_BASEURL` (4.3) usa `https://`.

---

## 8. Verificação
```bash
# página de login
curl -sL -o /dev/null -w '%{http_code}\n' http://127.0.0.1:${NGINX_PORT}/
# redirect deve apontar para a URL correta (sem :porta estranha)
curl -sI http://127.0.0.1:${NGINX_PORT}/ | grep -i location

# o PHP conecta no banco?
docker exec php-fpm php -r '$m=@mysqli_connect(getenv("DB_HOSTNAME"),getenv("DB_USERNAME"),getenv("DB_PASSWORD"),getenv("DB_DATABASE")); echo $m?"DB OK\n":"DB FAIL: ".mysqli_connect_error()."\n";'

# API (quando público)
curl -s https://SEU-DOMINIO.com/index.php/api/v1/status
```
Esperado: login **200**, `DB OK`, API `{"status":true,"message":"Map-OS online",...}`.

---

## 9. Persistência e Backup

- **Persistência**: o banco é gravado no host via bind `./docker/data/db/mysql` → **sobrevive** a
  `docker compose down/up` e restart. Só é perdido se a pasta for apagada (o `-v` do `down` não
  afeta bind mounts; afetaria volumes nomeados).
- **Backup (Google Drive)** — `backup-drive.sh` do servidor captura:
  - o **código** do projeto como `config/projects/mapos-docker.tar.gz`
    (a pasta de dados do banco `*/docker/data/db` é **excluída** do tar);
  - o **banco do container** como dump SQL: **`db/maposreal-mysql-all.sql.gz`**
    (loop de containers MySQL/MariaDB do script).
- **Restaurar**: extraia o projeto, suba a stack (seção 5) e importe o dump no container
  **depois** de ele subir:
  ```bash
  gunzip < maposreal-mysql-all.sql.gz | \
    docker exec -i maposreal-mysql mariadb -uroot -p"$MYSQL_MAPOS_ROOT_PASSWORD"
  ```
- **Guarde fora do servidor**: `MYSQL_MAPOS_ROOT_PASSWORD`, `MYSQL_MAPOS_PASSWORD` e
  `APP_ENCRYPTION_KEY`.

---

## 10. Solução de problemas

| Sintoma | Causa provável | Correção |
|---|---|---|
| **HTTP 500** com página do Whoops | erro de app/PHP | veja `application/logs/log-*.php` |
| Redirect para `dominio:PORTA` | `application/.env` com `localhost`/sem URL | definir `APP_BASEURL` (4.3) |
| `Bind for 0.0.0.0:PORTA failed` | porta ocupada | trocar `NGINX_PORT`/`MYSQL_MAPOS_PORT` |
| `container name already in use` | outro MapOS na máquina | usar `-p` + `container_name` prefixados |
| `CREATE USER ... ERROR 1396` | dumpar schema `mysql` | importar só bancos de usuário + `--system=users` |
| erro de `DEFINER` no import | MySQL×MariaDB | usar imagem **mariadb:11** |
| `Undefined array key APP_ENCRYPTION_KEY` | chave ausente em `application/.env` | definir `APP_ENCRYPTION_KEY` |

Logs: `docker compose -p mapos logs -f <serviço>` (nginx, php-fpm, mysql).

---

## 11. Migrar uma instalação LAMPP existente → Docker

1. Copie o código atual (`/opt/lampp/htdocs/mapos`) para `/opt/mapos-docker`.
2. Aplique os itens da seção 4 (override MariaDB, `.env`, **`application/.env`/`APP_BASEURL`**).
3. Suba a stack (seção 5) **em portas de teste** (ex.: 8000) e importe o dump do banco (seção 6).
4. Valide login/API. Depois faça o **corte**: pare o LAMPP, libere a porta de produção e
   republique o Docker nela (ou ajuste o túnel/proxy para o Docker).
5. Mantenha o LAMPP parado (não remova) até confirmar; só então desative.

---

### Valores usados no servidor atual (referência)
- Diretório: `/opt/mapos-docker` · projeto compose: `maposreal`
- Portas: nginx **9980** (domínio), mariadb **3307**, phpMyAdmin **8081**
- Domínio: **https://mapos.tecnicolitoral.com**
- LAMPP antigo (comparação): **:8000**
