# Task Plan — Ray.FakeQuery 実装

## Goal
`FakeQueryModule` をインストールすると、`#[DbQuery]` アノテーション付きインターフェースのメソッドが
SQLではなくJSONファイルからデータを返すようになるパッケージを実装する。

## ステータス凡例
- [ ] pending
- [~] in_progress
- [x] complete
- [!] blocked / 要確認

---

## Phase 0: 設計判断（確定済み）[x]

1. **Constructor エンティティの引数マッピング** → **名前ベース**
   - Reflection でパラメータ名を取得し、JSON キー（snake_case→camelCase変換）でマッピング

2. **nullable + JSONファイル不在の挙動** → **FakeJsonNotFoundException をスロー**
   - null を返したければ明示的に JSON ファイルに `null` と書く

3. **PHP バージョン要件** → **`^8.2` に変更**

4. **`DbQuery::$type` の扱い** → **尊重する（MediaQuery と同じ挙動）**
   - `type === 'row'` → single fetch 強制

---

## Phase 1: 依存関係セットアップ [ ]

### 1-1. composer.json 更新
```json
"require": {
    "php": "^8.2",
    "ray/di": "^2.18",
    "ray/media-query": "^1.0"
}
```

### 1-2. composer update
```bash
composer update
```

### 検証
- `vendor/ray/media-query/src/` が存在する
- `vendor/ray/di/` が存在する

---

## Phase 2: コア実装 [ ]

### 2-1. `src/FakeQueryConfig.php`
```php
final class FakeQueryConfig {
    public function __construct(
        public readonly string $fakeDir,
    ) {}
}
```

### 2-2. `src/Exception/FakeJsonNotFoundException.php`
`src/Exception/RuntimeException.php` を継承。
```php
final class FakeJsonNotFoundException extends RuntimeException {
    public function __construct(string $queryId, string $fakeDir) {
        parent::__construct("Fake JSON file not found: {$queryId}.json in {$fakeDir}");
    }
}
```

### 2-3. `src/Hydrator/FakeJsonHydrator.php`
責務: JSON データ → エンティティオブジェクト or 配列

入力:
- `array|null $data` （JSONデコード済みデータ）
- `string|null $entityClass` （エンティティFQCN、nullならraw array）
- `bool $isRow` （single か list か）

ロジック:
```
$isRow = true:
  $entityClass = null → return $data as-is (raw assoc)
  $entityClass あり:
    $data = null → return null
    → hydrateOne($data, $entityClass)

$isRow = false:
  $entityClass = null → return $data as-is (raw assoc array)
  $entityClass あり:
    → array_map(fn($row) => hydrateOne($row, $entityClass), $data)

hydrateOne($row, $entity):
  method_exists($entity, '__construct') なし
    → new $entity(), public property を camelCase 変換後に set
  あり
    → Reflection でコンストラクタパラメータを取得
    → param名（またはsnake_case）で $row からマッピング
    → new $entity(...$args)
```

### 2-4. `src/Interceptor/FakeQueryInterceptor.php`
```php
final class FakeQueryInterceptor implements MethodInterceptor {
    public function __construct(
        private readonly FakeQueryConfig $config,
        private readonly FakeJsonHydrator $hydrator,
        private readonly ReturnEntityInterface $returnEntity,
    ) {}

    public function invoke(MethodInvocation $invocation): mixed {
        $method = $invocation->getMethod();
        $dbQuery = $method->getAnnotation(DbQuery::class); // Ray.Aop API

        // void → no-op
        $returnType = $method->getReturnType();
        if ($returnType instanceof ReflectionNamedType && $returnType->getName() === 'void') {
            return null;
        }

        // row か row_list か判定（MediaQueryと同ロジック）
        $isRow = $dbQuery->type === 'row'
            || $returnType instanceof ReflectionUnionType
            || ($returnType instanceof ReflectionNamedType && $returnType->getName() !== 'array');

        // JSON ファイル読込
        $jsonFile = $this->config->fakeDir . '/' . $dbQuery->id . '.json';
        if (! file_exists($jsonFile)) {
            throw new FakeJsonNotFoundException($dbQuery->id, $this->config->fakeDir);
        }
        $data = json_decode((string) file_get_contents($jsonFile), true);

        // エンティティクラス取得（PHPDoc含む）
        $entityClass = ($this->returnEntity)($method);

        return $this->hydrator->hydrate($data, $entityClass, $isRow);
    }
}
```

### 2-5. `src/FakeQueryModule.php`
```php
final class FakeQueryModule extends AbstractModule {
    public function __construct(
        private readonly string $fakeDir,
        private readonly string $interfaceDir,
        AbstractModule|null $module = null,
    ) {
        parent::__construct($module);
    }

    protected function configure(): void {
        // 1. FakeQueryConfig をバインド
        $this->bind(FakeQueryConfig::class)->toInstance(new FakeQueryConfig($this->fakeDir));

        // 2. ReturnEntityInterface をバインド（ray/media-queryから再利用）
        $this->bind(DocBlockFactoryInterface::class)->toInstance(DocBlockFactory::createInstance());
        $this->bind(ReturnEntityInterface::class)->to(ReturnEntity::class);

        // 3. interfaceDir からインターフェースをスキャン、toNull() でバインド
        $queries = Queries::fromDir($this->interfaceDir);
        foreach ($queries->classes as $class) {
            $this->bind($class)->toNull();
        }

        // 4. #[DbQuery] メソッドへインターセプトをバインド
        $this->bindInterceptor(
            $this->matcher->any(),
            $this->matcher->annotatedWith(DbQuery::class),
            [FakeQueryInterceptor::class],
        );
    }
}
```

---

## Phase 3: テスト実装 [ ]

### 3-1. テスト用 Fake データ作成

**`tests/Fake/Interface/TodoQueryInterface.php`**
```php
interface TodoQueryInterface {
    #[DbQuery('todo_item')]
    public function item(string $todoId): ?TodoEntity;

    #[DbQuery('todo_list')]
    /** @return array<TodoEntity> */
    public function list(): array;
}
```

**`tests/Fake/Entity/TodoEntity.php`**
```php
final class TodoEntity {
    public string $todoId;
    public string $todoTitle;
    public bool $isCompleted;
}
```

**`tests/Fake/todo_item.json`**
```json
{
    "todo_id": "01HVXXXXXX0008",
    "todo_title": "Beフレームワーク",
    "is_completed": false
}
```

**`tests/Fake/todo_list.json`**
```json
[
    {"todo_id": "01HVXXXXXX0008", "todo_title": "Beフレームワーク", "is_completed": false},
    {"todo_id": "01HVXXXXXX0007", "todo_title": "ALPS設計", "is_completed": true}
]
```

**Command 用**:
```php
interface TodoCommandInterface {
    #[DbQuery('todo_add')]
    public function add(string $todoId, string $title): void;
}
```

### 3-2. `tests/FakeQueryModuleTest.php`

テストケース:
1. `testItemQuery` — `?Entity` 戻り値、single JSON → エンティティ取得
2. `testListQuery` — `array<Entity>` PHPDoc、JSON配列 → エンティティ配列
3. `testCommandIsNoOp` — `void` 戻り値 → 例外なし、null返却
4. `testMissingJsonThrows` — JSON ファイルなし → `FakeJsonNotFoundException`
5. `testRawArrayQuery` — PHPDoc なし `array` 戻り値 → raw array

---

## Phase 4: 品質チェック [ ]

```bash
composer cs-fix          # コードスタイル修正
composer phpstan         # PHPStan level max
composer psalm           # Psalm
composer test            # PHPUnit
composer tests           # 全チェック
```

---

## リスクと注意点

| リスク | 対策 |
|--------|------|
| `ReturnEntity` が internal 変更される | `ReturnEntityInterface` (public) を通してのみ使う |
| Constructor エンティティのパラメータ名マッピング漏れ | Reflection で getName() を使い、camelCase/snake_case 両方試みる |
| PHPStan level max で型エラー | `@param`, `@return` を丁寧に書く |
| `ray/media-query ^1.0` が `^8.2` 要求 | PHP 要件を `^8.2` に更新 |

---

## 依存グラフ（実装順）

```
Phase 1: composer.json
    ↓
FakeQueryConfig (単純 VO)
    ↓
FakeJsonNotFoundException (単純例外)
    ↓
FakeJsonHydrator (hydration ロジック)
    ↓
FakeQueryInterceptor (FakeQueryConfig + Hydrator + ReturnEntity)
    ↓
FakeQueryModule (全体を束ねる)
    ↓
Tests
    ↓
Quality checks
```
