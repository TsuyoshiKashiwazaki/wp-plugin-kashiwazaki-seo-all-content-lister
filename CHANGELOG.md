# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-01-07

### Added
- 被リンク調査機能（バックグラウンドバッチ処理）
- 被リンク元カラム（被リンク数と被リンク元記事の表示）
- リンクマップ視覚化機能（D3.js フォースダイレクトグラフ）
- ノード選択時のズーム・センタリング機能
- リンク元（緑）・リンク先（青）のハイライト表示

### Fixed
- リンクマップのノード選択が動作しない問題を修正
- 非関連ノードの視覚的な強調を調整

## [1.0.0] - 2026-01-03

### Added
- コンテンツ一覧表示機能（投稿・固定ページ・カスタム投稿タイプ対応）
- ソート機能（ID、タイトル、投稿日、更新日など）
- フィルター機能（投稿タイプ、ステータス、カテゴリー）
- カラム表示切替機能（ブラウザのlocalStorageに保存）
- CSV出力機能（UTF-8/Shift_JIS対応）

[1.0.1]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-all-content-lister/releases/tag/v1.0.1-dev
[1.0.0]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-all-content-lister/releases/tag/v1.0.0
