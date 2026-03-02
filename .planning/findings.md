# Findings — Ray.FakeQuery 実装調査

## Ray.MediaQuery ソースから判明した事実

### DbQuery アトリビュート
```php
// Ray\MediaQuery\Annotation\DbQuery
#[Attribute(Attribute::TARGET_METHOD)]
final class DbQuery {
    public function __construct(
        public string $id,
        public string $type = 'row_list', // 'row' | 'row_list'
        public string $factory = '',
    ) {}
}
```
- `$id` でクエリIDを取得
- `$type` で single/list を明示できる（デフォルト row_list）

### Ray.Aop getAnnotation API
`DbQueryInterceptor` では:
```php
$dbQuery = $method->getAnnotation(DbQuery::class); // Ray\Aop\ReflectionMethod API
```
`getAttributes()` ではなく `getAnnotation()` を使う（Ray.Aop固有メソッド）。

### Interface スキャン（Queries::fromDir）
```php
// Queries::fromDir($dir) → ClassesInDirectories::list($dir)
// - PHPファイルをトークン解析してclass/interfaceを取得
// - class_exists() / interface_exists() でオートロード確認
// - Generator で class-string を yield
```
`ray/media-query` の `Queries::fromDir()` をそのまま使える。

### Interface バインディングパターン
```php
// MediaQueryBaseModule::configure()
foreach ($this->queries->classes as $class) {
    $this->bind($class)->toNull(); // Null object にバインド
}
// → InterceptorがNull objectのメソッド呼び出しをインターセプト
```

### Interceptor バインディング
```php
// MediaQueryDbModule::configure()
$this->bindInterceptor(
    $this->matcher->any(),
    $this->matcher->annotatedWith(DbQuery::class),
    [DbQueryInterceptor::class],
);
```
`any()` クラス × `#[DbQuery]` メソッド にインターセプトをバインド。

### ReturnEntity（PHPDoc解析）
```php
// ReturnEntityInterface::__invoke(ReflectionMethod) → ?class-string
// - 戻り値型がクラスなら そのFQCN を返す
// - array<Entity> (PHPDoc) なら Entity のFQCN を返す
// - void / null / raw array → null を返す
// phpdocumentor/reflection-docblock に依存（ray/media-queryが持つ）
```
`ReturnEntityInterface` は public → そのままバインドして再利用できる。

### row/row_list 判定ロジック（DbQueryInterceptor より）
```php
$isRow = $dbQuery->type === 'row'
    || $returnType instanceof ReflectionUnionType
    || ($returnType instanceof ReflectionNamedType && $returnType->getName() !== 'array');
```
FakeQuery でも同じロジックを採用する。

### エンティティ種別と PDO fetch との対応

| 条件 | PDO fetch | FakeQuery hydration |
|------|-----------|---------------------|
| entity = null（raw array） | FETCH_ASSOC | JSON data をそのまま返す |
| entity あり、__construct なし | FETCH_CLASS（プロパティ直設定） | `new $entity()` → public property を set |
| entity あり、__construct あり | FETCH_FUNC（位置引数） | Reflection で param名→JSON値 マッピング → `new $entity(...)` |

### snake_case → camelCase 変換
```php
// Ray\MediaQuery\StringCase::camel('todo_id') → 'todoId'
// ray/media-query に含まれるため再利用可能
```

### void メソッド（Command）の確認
```php
// TodoAddInterface
#[DbQuery('todo_add')]
public function __invoke(string $id, string $title): void;
// → JSON ファイル不要、no-op
```

## 依存関係

### 現在の composer.json（Ray.FakeQuery）
```json
"require": { "php": "^8.1" }
```
**追加が必要：**
- `ray/di: ^2.18`（AbstractModule, bindInterceptor等）
- `ray/media-query: ^1.0`（Queries, ReturnEntity, DbQuery, StringCase等）

transitively 取得できるもの:
- `ray/aop`（MethodInterceptor, MethodInvocation）
- `phpdocumentor/reflection-docblock`（ReturnEntity内部で使用）

### Ray.MediaQuery の PHP 要件
`ray/media-query` は `php: ^8.2` を要求している。
→ Ray.FakeQuery の `require.php` も `^8.2` に上げる必要あり。

## テスト用 Fake データ構造

DESIGN.md のテスト例:
```
tests/
├── FakeQueryModuleTest.php
└── Fake/
    ├── Interface/               ← interfaceDir として渡す
    │   └── TodoQueryInterface.php
    ├── Entity/
    │   └── TodoEntity.php
    ├── todo_item.json           ← fakeDir として渡す
    └── todo_list.json
```

## 未解決事項（設計判断が必要）

### Q1: エンティティ constructor 引数マッピング
`FetchNewInstance` は PDO の列順に `new $entity(...$args)` を呼ぶ。
JSON は名前付きキーなので、**constructor パラメータ名でマッピング**が必要。
- snake_case JSON キー → camelCase → コンストラクタパラメータ名 でマッピング?

### Q2: DbQuery::type の扱い
`$type = 'row'` の場合に single fetch を強制するか？
→ MediaQuery と同じロジックで yes（`$type === 'row'` → row）

### Q3: nullable の JSON ファイルが存在しない場合
`?Entity` 戻り値型で JSON ファイルが存在しない場合：
- DESIGN.md: 「Missing files: throw FakeJsonNotFoundException」
- ただし nullable なら null を返すべきか？ or 明示的に `null` を JSON に書く？

### Q4: `array<Entity>` で要素が Entity かどうかのチェック
`ReturnEntity` が null を返す（PHPDoc なし or raw array）かつ戻り型が `array` なら raw array として扱う。
これは MediaQuery と同じ動作で OK？
