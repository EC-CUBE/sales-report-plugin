# 売上集計プラグイン

[![CI for SalesReport44](https://github.com/EC-CUBE/sales-report-plugin/actions/workflows/main.yml/badge.svg)](https://github.com/EC-CUBE/sales-report-plugin/actions/workflows/main.yml)

## 概要
売上を集計し、結果をグラフと一覧で確認できます。
結果をCSVで保存することもできます。

## フロント
機能なし

## 管理画面
### 期間別集計
指定した期間で、日/月/曜日/時間ごとの売上の集計結果を見ることができる。

- 期間指定
	- 単月
	- 期間(from/to)
- 集計方法
	- 日別
	- 月別
	- 曜日別
	- 時間別
- 集計結果

| 期間    | 購入件数 | 男性 | 女性 | 不明 | 男性 (会員) | 男性 (非会員) | 女性 (会員) | 女性 (非会員) | 購入合計 | 購入平均 |
|---------|----------|------|------|------|-------------|---------------|-------------|---------------|----------|----------|
| 2018/10 |32        | 15    | 15    | 2    | 10           | 5             | 10           | 5             | ¥119,834   | ¥3,744    |

### 商品別集計
指定した期間で、商品ごとの売上の集計結果を見ることができる。

- 期間指定
	- 単月
	- 期間(from/to)
- 集計方法
	- 商品別
- 集計結果


| 商品コード | 商品名        | 購入件数(件) | 数量(個) | 金額 |
|------------|---------------|--------------|----------|----------|
| sand-01    | チェリーアイスサンド | 12            | 6        | ¥18,144   |

### 年代別集計
指定した期間で、会員の年代ごとの売上の集計結果を見ることができる。

- 期間指定
	- 単月
	- 期間(from/to)
- 集計方法
	- 会員年代別
- 集計結果

| 年代 | 購入件数(件) | 購入合計 | 購入平均 |
|------|--------------|--------------|--------------|
| 30代 | 12            | 59,834       | ¥4,986        |

### 集計結果のCSV保存
集計結果ページに表示される「CSVダウンロード」ボタンを押すことで、集計結果をCSVに保存することができる。

----------------------------------------------------------------------
## Docker Compose でのテスト

Docker Compose で EC-CUBE 4.4 + 本プラグインの環境を起動し、PHPUnit を実行できます。
EC-CUBE 本体は初回起動時に自動インストールされ（デモ商品データも投入）、マウントした
プラグインが自動でインストール・有効化されます。

### 構成ファイル

| ファイル | 役割 |
|---|---|
| `docker-compose.yml` | ベース（EC-CUBE 4.4 + mailcatcher、SQLite） |
| `docker-compose.dev.yml` | プラグインのマウント・インストール・有効化 |
| `docker-compose.mysql.yml` | DB を MySQL 8 に切り替え |
| `docker-compose.pgsql.yml` | DB を PostgreSQL 18 に切り替え |

### 環境の起動

```bash
# SQLite で起動
export COMPOSE_FILE=docker-compose.yml:docker-compose.dev.yml
docker compose up -d --wait

# MySQL で起動する場合
export COMPOSE_FILE=docker-compose.yml:docker-compose.mysql.yml:docker-compose.dev.yml
docker compose up -d --wait

# PostgreSQL で起動する場合
export COMPOSE_FILE=docker-compose.yml:docker-compose.pgsql.yml:docker-compose.dev.yml
docker compose up -d --wait
```

PHP バージョンは環境変数 `TAG` で変更できます（`8.2-apache-4.4` / `8.3-apache-4.4` / `8.4-apache-4.4` / `8.5-apache-4.4`）。

```bash
TAG=8.3-apache-4.4 docker compose up -d --wait
```

### PHPUnit の実行

PHPUnit は `phpunit.xml.dist` により `APP_ENV=test` で実行されます。有効化直後は test 環境の
コンパイル済みキャッシュにプラグインのルーティングが反映されていない場合があるため、
実行前に test 環境のキャッシュをクリアします。

```bash
docker compose exec ec-cube bash -lc \
  "APP_ENV=test bin/console cache:clear --no-warmup && ./vendor/bin/phpunit -c app/Plugin/SalesReport44/phpunit.xml.dist app/Plugin/SalesReport44/Tests"
```

管理画面は http://localhost:8080/admin 、送信メールは http://localhost:1080 (mailcatcher) で確認できます。

### 環境の破棄

```bash
docker compose down -v
```
