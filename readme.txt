=== Kashiwazaki SEO All Content Lister ===
Contributors: tsuyoshikashiwazaki
Tags: content, seo, list, admin, management
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.2
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress管理画面で全ての投稿・固定ページ・カスタム投稿タイプを一覧表示し、ソート・フィルター・CSV出力機能を提供する管理ツール。

== Description ==

Kashiwazaki SEO All Content Lister は、WordPress管理画面で全てのコンテンツを一元管理するためのプラグインです。

= 主な機能 =

* **コンテンツ一覧表示**: 全ての投稿タイプを統合して一覧表示
* **ソート機能**: ID、タイトル、投稿日、更新日などでソート可能
* **フィルター機能**: 投稿タイプ、ステータス、カテゴリーでフィルタリング
* **カラム表示切替**: 表示するカラムをカスタマイズ可能（設定はブラウザに保存）
* **CSV出力**: 一覧データをCSV形式でエクスポート（UTF-8/Shift_JIS対応）
* **被リンク調査**: サイト内の内部リンク構造を解析し、各記事への被リンク元を表示
* **リンクマップ**: D3.jsによるフォースダイレクトグラフで内部リンク構造を視覚化

== Installation ==

1. プラグインフォルダを `/wp-content/plugins/` ディレクトリにアップロード
2. WordPress管理画面の「プラグイン」からプラグインを有効化
3. 左メニューに「Kashiwazaki SEO All Content Lister」が追加されます

== Frequently Asked Questions ==

= 対応している投稿タイプは？ =

投稿（post）、固定ページ（page）、および全てのカスタム投稿タイプに対応しています。

= 被リンク調査にはどのくらい時間がかかりますか？ =

記事数によりますが、バックグラウンドでバッチ処理されるため、処理中も他の作業を続けられます。

= リンクマップが表示されない =

D3.jsライブラリが正しく読み込まれているか確認してください。ブラウザのコンソールでエラーを確認できます。

== Changelog ==

= 1.0.1 =
* 被リンク調査機能を追加（バックグラウンド処理）
* リンクマップ視覚化機能を追加（D3.js）
* ノード選択時のズーム・センタリング機能を追加
* リンクマップのノード選択が動作しない問題を修正
* 非関連ノードの視覚的な強調を調整

= 1.0.0 =
* 初回リリース
* コンテンツ一覧表示機能
* ソート・フィルター機能
* CSV出力機能（UTF-8/Shift_JIS対応）

== Upgrade Notice ==

= 1.0.1 =
被リンク調査機能とリンクマップ視覚化機能を追加

= 1.0.0 =
初回リリース
