# 売上集計プラグイン

[![Build Status](https://travis-ci.org/EC-CUBE/sales-report-plugin.svg?branch=sale-report-renew)](https://travis-ci.org/EC-CUBE/sales-report-plugin)
[![Build status](https://ci.appveyor.com/api/projects/status/7ywi4kw3q5pru4j3/branch/master?svg=true)](https://ci.appveyor.com/project/ECCUBE/sales-report-plugin/branch/master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/c044fde6-ee82-42ed-bc3e-7a91652656bf/mini.png)](https://insight.sensiolabs.com/projects/c044fde6-ee82-42ed-bc3e-7a91652656bf)
[![Coverage Status](https://coveralls.io/repos/github/EC-CUBE/sales-report-plugin/badge.svg?branch=master)](https://coveralls.io/github/EC-CUBE/sales-report-plugin?branch=master)

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
| 2016/12 | 2        | 2    | 0    | 0    | 2           | 0             | 0           | 0             | 19,834   | 9,917    |

### 商品別集計
指定した期間で、商品ごとの売上の集計結果を見ることができる。

- 期間指定
	- 単月
	- 期間(from/to)
- 集計方法
	- 商品別
- 集計結果

| 商品コード | 商品名        | 購入件数(件) | 数量(個) | 金額(円) |
|------------|---------------|--------------|----------|----------|----------|
| cafe-01    | 耐熱グラス(S) | 2            | 6        | 18,144   |

### 年代別集計
指定した期間で、会員の年代ごとの売上の集計結果を見ることができる。

- 期間指定
	- 単月
	- 期間(from/to)
- 集計方法
	- 会員年代別
- 集計結果

| 年代 | 購入件数(件) | 購入合計(円) | 購入平均(円) |
|------|--------------|--------------|--------------|
| 30代 | 2            | 19,834       | 9,917        |

### 集計結果のCSV保存
集計結果ページに表示される「CSVダウンロード」ボタンを押すことで、集計結果をCSVに保存することができる。
