# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## このリポジトリについて

EC-CUBE 4 系の**売上集計プラグイン**。管理画面に売上集計メニューを追加し、期間別・商品別・年代別の
売上をグラフと一覧で表示し、集計結果を CSV でダウンロードできる。

- 管理画面: `SalesReportNav`（`EccubeNav`）が管理画面ナビに「売上集計」メニュー（期間別/商品別/年代別）を追加し、
  `Controller/SalesReportController.php` が各画面と CSV 出力を担当。集計ロジックは `Service/SalesReportService.php`。
- フロント: 機能なし。

プラグインコードは `SalesReport44`、Composer パッケージ名は `ec-cube/salesreport44`。Twig 名前空間（`@SalesReport44`）・
クラス名前空間（`Plugin\SalesReport44\...`）はすべて `SalesReport44` 接頭辞を使う。

### ブランチ運用

ブランチ名が対応する EC-CUBE 本体バージョンを表す（`4.0` / `4.2` / `4.4` など）。`4.2` がデフォルトブランチ。
`4.2` ブランチは EC-CUBE 4.2/4.3（`SalesReport42`）に対応し、`4.4` ブランチは EC-CUBE 4.4
（Symfony 7.4 / Doctrine ORM 3.0 / PHP 8.2+、`SalesReport44`）に対応する。**4.3 と 4.4 はアノテーション必須/属性必須の
違いで非互換**のため、別ブランチで保守する。

## 開発・テストコマンド

このプラグイン単体では動作せず、**EC-CUBE 本体に組み込んだ状態**で開発・テストする。本体の取得・インストール・
プラグイン有効化は `docker-compose.dev.yml` の entrypoint が自動実行する。

```bash
# 開発環境 (SQLite) の起動 — 本体インストール・プラグイン有効化まで自動
export COMPOSE_FILE=docker-compose.yml:docker-compose.dev.yml
docker compose up -d --wait

# MySQL / PostgreSQL で起動する場合
export COMPOSE_FILE=docker-compose.yml:docker-compose.mysql.yml:docker-compose.dev.yml
export COMPOSE_FILE=docker-compose.yml:docker-compose.pgsql.yml:docker-compose.dev.yml

# PHP バージョン切り替え（8.2-apache-4.4 / 8.3-apache-4.4 / 8.4-apache-4.4 / 8.5-apache-4.4）
TAG=8.3-apache-4.4 docker compose up -d --wait
```

起動後は管理画面 `http://localhost:8080/admin`（`admin` / `password`）、メールは MailCatcher `http://localhost:1080`。

### PHPUnit

テストは `Tests/` 配下の PHPUnit（`Tests/Web/`）。`phpunit.xml.dist` により `APP_ENV=test` で実行される。

```bash
docker compose exec ec-cube bash -lc \
  "APP_ENV=test bin/console cache:clear --no-warmup && ./vendor/bin/phpunit -c app/Plugin/SalesReport44/phpunit.xml.dist app/Plugin/SalesReport44/Tests"
```

**注意（コンパイル済みキャッシュ）**: 有効化したプラグインのルーティングは、コンテナのコンパイル時に確定する。
有効化直後の test キャッシュには反映されていないことがあるため、**PHPUnit 実行前に `APP_ENV=test` でキャッシュをクリアする**。
これを怠るとコントローラのルートが `RouteNotFoundException` になる。

**注意（データプロバイダ）**: PHPUnit 11 では `@dataProvider` が指すメソッドは **`static`** でなければならない。

### 静的解析・整形（任意）

EC-CUBE 本体（コンテナ内）の vendor を使って実行する。

```bash
# php-cs-fixer
docker compose exec ec-cube bash -lc \
  "cd app/Plugin/SalesReport44 && /var/www/html/vendor/bin/php-cs-fixer fix --config=Resource/.php-cs-fixer.dist.php --dry-run --diff"

# rector（再移行・検証用）
docker compose exec ec-cube bash -lc \
  "cd app/Plugin/SalesReport44 && /var/www/html/vendor/bin/rector process --config=Resource/rector.php --dry-run"

# phpstan
docker compose exec ec-cube bash -lc \
  "cd app/Plugin/SalesReport44 && /var/www/html/vendor/bin/phpstan analyse -c phpstan.neon.dist"
```

phpstan は level 6。新規コードもこの水準を維持すること。

## アーキテクチャ

- **Controller** (`Controller/SalesReportController.php`): `#[Route]` 属性。期間別/商品別/年代別の各画面と CSV 出力（`StreamedResponse`）を担当。
- **Service** (`Service/SalesReportService.php`): 受注データを集計してグラフ/一覧データを生成し、CSV を出力する。DQL は読み取り専用。
- **Form** (`Form/Type/SalesReportType.php`): 集計条件（期間種別/年月/期間 from-to/集計単位）の入力フォーム。ブロックプレフィックスは `sales_report`。
- **Nav** (`SalesReportNav.php`): 管理画面ナビに売上集計メニューを追加する（`EccubeNav`）。
- **PluginManager** (`PluginManager.php`): デフォルト実装のまま。
- Entity / Repository / EntityExtension / FormExtension は持たない。

## 規約・移行メモ

### 開発ツール設定ファイルは `Resource/` 配下に置く（rector.php / .php-cs-fixer.dist.php）

`rector.php` や `.php-cs-fixer.dist.php` を**プラグインのルート直下に置いてはならない**。`Resource/` 配下に置く。

**理由**: EC-CUBE 本体の `config/eccube/services.yaml` がプラグインを丸ごと PSR-4 サービス検出対象として読み込む:

```yaml
Plugin\:
    resource: '../../../app/Plugin/*'
    exclude: '../../../app/Plugin/*/{Entity,Resource,ServiceProvider,Tests,Codeception,DoctrineMigrations}'
```

ルート直下の `*.php` は「サービスクラス」として読み込まれるため、`rector.php` を置くと Symfony が
`Plugin\SalesReport44\rector` クラスを期待し、見つからず **EC-CUBE 全体が 500 エラー**になる。`exclude` に
`Resource` が含まれるため `Resource/` 配下なら衝突しない。`phpstan.neon.dist` は `.php` ではないためルートに置ける。

**将来「本体に合わせてルートへ戻す」とリグレッションするため、この配置を変更しないこと。**

### docker 環境は `APP_ENV=dev` で起動する

ブラウザログインには実セッション（`session.storage.factory.native`）が必要。`APP_ENV=test` ではモックストレージ
（`mock_file`）になりログインできない。また EC-CUBE 4.4（Symfony 7）は既定 `cookie_samesite: none` のため、HTTP 環境では
`dockerbuild/dev-framework.yaml`（`cookie_secure:false` / `cookie_samesite:lax`）を
`app/config/eccube/packages/dev/framework.yaml` に重ねて回避している。

### プラグインの導入方法（tar + plugin:install）

`docker-compose.dev.yml` はマウントしたプラグインを `./*` で tar 化し `eccube:plugin:install --path` で導入する。
`eccube:composer:require` はパッケージ API（`extra.id`）を要求するため path プラグインでは使えない。また
**`PharData` は先頭の `./` エントリで展開に失敗する**ため、プラグインディレクトリ内で `./*` を対象に tar 化する（`-C dir .` は不可）。
