# Upgrading from 2.x to 3.0

3.0 implements the property inspection contracts of kariricode/contract 2.12.0 and drops the package's own interfaces (ADR-009). A processor ends up depending on the contract only.

```
2.x  processor ──require──▶ kariricode/property-inspector (Contract\*, Utility\*)
3.0  processor ──require──▶ kariricode/contract ^2.12 (Inspection\*)
                                   ▲
     kariricode/property-inspector 3.0 implements it (bound once in the container)
```

## 1. Dependencies

```diff
 "require": {
-    "kariricode/property-inspector": "^2.0"
+    "kariricode/contract": "^2.12"
 }
```

The application, not the processor, requires `kariricode/property-inspector:^3.0` and binds the two implementations:

```php
$container->instance(PropertyInspector::class, new ReflectionPropertyInspector());
$container->instance(PropertyChangeApplier::class, new ReflectionChangeApplier());
```

## 2. Renamed and removed types

| 2.x | 3.0 |
|---|---|
| `Contract\PropertyInspector` | `KaririCode\Contract\Inspection\PropertyInspector` |
| `Contract\PropertyAttributeHandler` | `KaririCode\Contract\Inspection\PropertyAttributeHandler` |
| `Contract\PropertyChangeApplier` | `KaririCode\Contract\Inspection\PropertyChangeApplier` |
| `Contract\AttributeAnalyzer` | removed; the analyzer is internal |
| `AttributeAnalyzer` | `Core\AttributeAnalyzer` (internal) |
| `Utility\PropertyInspector` | `Core\ReflectionPropertyInspector` |
| `Utility\PropertyAccessor` | removed; write through `PropertyChangeApplier` |
| `Exception\PropertyInspectionException` (class) | interface; `InspectionFailedException` and `ChangeRejectedException` are the classes |

## 3. Inspecting

The attribute class moves from the constructor to `inspect()`, so one instance serves every processor. `inspect()` returns `void`.

```diff
-$inspector = new PropertyInspector(new AttributeAnalyzer(Sanitize::class));
-$handler = $inspector->inspect($dto, $handler);
+$inspector->inspect($dto, Sanitize::class, $handler);   // $inspector: Contract\Inspection\PropertyInspector
```

## 4. Handlers

A handler implements one method that returns `void`. What it accumulates is read through the processor's own API; the three 2.x getters are gone.

```diff
-final class SanitizeHandler implements PropertyAttributeHandler, PropertyChangeApplier
+final class SanitizeRules implements \KaririCode\Contract\Inspection\PropertyAttributeHandler
 {
-    public function handleAttribute(string $propertyName, object $attribute, mixed $value): mixed { … return $value; }
-    public function getProcessedPropertyValues(): array { … }
-    public function getProcessingResultMessages(): array { return []; }
-    public function getProcessingResultErrors(): array { return []; }
-    public function applyChanges(object $object): void { … PropertyAccessor … }
+    public private(set) array $values = [];
+    public private(set) array $rules = [];
+
+    public function handleAttribute(string $propertyName, object $attribute, mixed $value): void
+    {
+        $this->values[$propertyName] = $value;
+        $this->rules[$propertyName] = [...$this->rules[$propertyName] ?? [], ...$attribute->rules];
+    }
 }
```

## 5. Writing back

Changes are written to a copy; the original is never touched. This works for readonly DPOs.

```diff
-$handler->applyChanges($dto);            // in place, through Utility\PropertyAccessor
-return $dto;
+$clean = $applier->applyChanges($dto, $sanitizedValues);   // a new instance
+return $clean;                                              // validate the copy, not $dto
```

The copy's constructor does not run, so validate after sanitizing. Set hooks run for the changed properties only (ADR-006).

## 6. Exceptions

| 2.x | 3.0 |
|---|---|
| `catch (PropertyInspectionException $e)` | unchanged; it is now an interface, also catchable as `Contract\Exception\Runtime` |
| handler exceptions wrapped as 2502, 2504 or 2505 | the handler's exception propagates unchanged (ADR-010); catch it as your own type |
| 2501 (reflection error in an attribute) | 2506 |
| 2503 for an uninitialized property | 2507 (2503 now means an attribute that cannot be instantiated) |
| — | 2520–2523, from `applyChanges()` |

Messages start with `[PI_<code>]`. They no longer contain the cause's message, so read it through `getPrevious()` (ADR-011).

## 7. Behaviour that changed

- **Static properties.** 2.x inspected an annotated static property and passed its class-level value to the handler. 3.0 ignores static properties, and the applier rejects them (2521).
- **Get hooks.** In 2.x, an exception thrown by a get hook while values were read was wrapped (2503 or 2506). In 3.0 it propagates unchanged (ADR-010).
- **Unchanged.** Some behaviour stays as it was, and the contract now states it. A parent's private properties are not inspected. Every annotated property is read, and every attribute built, before the handler sees any of them.

The 2.x line stays available for consumers that cannot migrate yet.
