# Progress — Ray.FakeQuery 実装

## セッション: 2026-03-02

### 完了
- [x] Ray.MediaQuery ソース調査
  - DbQuery アトリビュート構造（id, type, factory）
  - Interceptor バインディングパターン（any() × annotatedWith(DbQuery)）
  - Interface スキャン（Queries::fromDir → ClassesInDirectories）
  - ReturnEntity（phpdocumentor による PHPDoc 解析）
  - StringCase::camel（snake → camel 変換）
  - エンティティ種別判定（constructor あり / なし）
  - row/row_list 判定ロジック

### 確定（Phase 0 完了）
- [x] Phase 0: 設計判断の確定
  - Constructor 引数: 名前ベースマッピング（snake_case→camelCase）
  - nullable + ファイル不在: FakeJsonNotFoundException スロー
  - PHP バージョン: ^8.2 に変更
  - DbQuery::type: 尊重する（MediaQuery 互換）

### 未着手
- [ ] Phase 1: 依存関係セットアップ（composer.json 更新）
- [ ] Phase 2: コア実装（5ファイル）
- [ ] Phase 3: テスト実装
- [ ] Phase 4: 品質チェック

## エラーログ
（なし）
