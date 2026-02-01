---
name: dandy-code
description: Complete PHP/Laravel clean code ruleset from "Dandy Code" book. Formatting, naming, early returns, error handling, comments, and code hygiene.
allowed-tools: Read, Write, Edit, Grep
version: 1.0
priority: HIGH
---

# Dandy Code - PHP Clean Code Standards

> **Source:** "Dandy Code" by Alexandr Chernyaev - Practical rules for elegant PHP code.

---

## Core Philosophy

| Principle | Description |
|-----------|-------------|
| **Visual Elegance** | Code should be pleasant to look at |
| **Respect for Colleagues** | Write code others can understand quickly |
| **Communication** | Names and structure reveal intent |
| **Simplicity** | Remove unnecessary complexity |

---

## Chapter 1: Project Structure

### README Requirements

Every project MUST have README.md with:

| Section | Content |
|---------|---------|
| **Title** | Clear project name |
| **Description** | What this project does |
| **Installation** | How to set up locally |
| **Usage** | Basic usage examples |
| **Owner** | Responsible person contact |

### Responsible Person

```markdown
| Owner | Contact | Status |
|-------|---------|--------|
| @developer | email@corp | active |
```

> **Rule:** A project with a name attached gets more care.

### Module Boundaries

- Monolith is fine, but organize into clear modules
- Each module should be independent
- Break 20,000+ file projects into understandable chunks

---

## Chapter 2: Formatting

### Auto-Formatting (MANDATORY)

| Tool | Purpose |
|------|---------|
| **PHP CS Fixer** | Auto-format on save |
| **Laravel Pint** | Laravel-specific formatting |

> **Rule:** Never return code for manual formatting issues. The CI should auto-fix.

### File Encoding

- UTF-8 without BOM
- LF line endings
- No trailing whitespace

---

## Chapter 3: Code Breathing

### Blank Line Usage

Separate logical blocks with blank lines:

```php
// ❌ No breathing - hard to read
$user = $request->user();
$zone = ClimateZone::find($id);
$zone->assign($user);
$zone->save();
return $zone;

// ✅ Breathing - logical groups visible
$user = $request->user();

$zone = ClimateZone::find($id);
$zone->assign($user);
$zone->save();

return $zone;
```

### When NOT to Add Blank Lines

Sequential operations that form one logical flow:

```php
// ✅ Correct - no breaks in continuous flow
$logger->debug('start');
$service->prepare();
$service->run();
$logger->debug('done');
```

---

## Chapter 4: Naming Conventions

### Language

| Rule | Example |
|------|---------|
| **English only** | `$user` not `$polzovatel` |
| **No transliteration** | `$weather` not `$pogoda` |
| **No abbreviations** | `$configuration` not `$cfg` |

### Variable Naming

| Type | Convention | Example |
|------|------------|---------|
| General | Descriptive noun | `$activeUsers`, `$orderTotal` |
| Boolean | Question form | `$isActive`, `$hasPermission`, `$canEdit` |
| Collection | Plural | `$users`, `$orders` |
| Single item | Singular | `$user`, `$order` |

### Method Naming

| Type | Convention | Example |
|------|------------|---------|
| Getter | `get` + noun | `getUser()`, `getOrderTotal()` |
| Setter | `set` + noun | `setUser()`, `setStatus()` |
| Boolean | `is`/`has`/`can` | `isValid()`, `hasPermission()` |
| Action | Verb + noun | `calculateTotal()`, `sendEmail()` |
| Transform | `to` + target | `toArray()`, `toJson()` |

### Don't Change Variable Type

```php
// ❌ Wrong - type changes
function process(array $user) {
    $user = new User($user);  // Was array, now object
}

// ✅ Correct - clear names
function process(array $userData) {
    $user = new User($userData);
}
```

---

## Chapter 5: Magic Values

### Use Named Constants

```php
// ❌ Magic numbers
if ($retries > 3) { ... }
sleep(86400);

// ✅ Named constants
const MAX_RETRY_COUNT = 3;
const SECONDS_PER_DAY = 86400;

if ($retries > self::MAX_RETRY_COUNT) { ... }
sleep(self::SECONDS_PER_DAY);
```

### Use Enums for Fixed Sets

```php
// ❌ Magic strings
$status = 'pending';

// ✅ Enum
enum OrderStatus: string {
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}

$status = OrderStatus::Pending;
```

---

## Chapter 6: Method Size

### Size Limits

| Metric | Limit |
|--------|-------|
| **Lines** | 10-20 max |
| **Arguments** | 2-3 max |
| **Nesting** | 2 levels max |

### If It's Too Long, Split

```php
// ❌ Too long
public function processOrder($order) {
    // 50 lines of validation
    // 30 lines of calculation
    // 20 lines of notification
}

// ✅ Split by responsibility
public function processOrder($order) {
    $this->validateOrder($order);
    $total = $this->calculateTotal($order);
    $this->notifyCustomer($order, $total);
}
```

---

## Chapter 7: Early Returns

### Guard Clauses First

```php
// ❌ Deep nesting
public function process($user) {
    if ($user !== null) {
        if ($user->isActive()) {
            // logic
        }
    }
}

// ✅ Guard clauses
public function process($user) {
    if ($user === null) {
        throw new UserNotFoundException();
    }

    if (!$user->isActive()) {
        throw new UserInactiveException();
    }

    // Clean main logic here
}
```

### Return Early, Return Often

- Check preconditions at top
- Fail fast on invalid state
- Main logic should be unnested

---

## Chapter 8: Arguments

### Limit Count

| Count | Action |
|-------|--------|
| 0-2 | ✅ Ideal |
| 3 | ⚠️ Consider object |
| 4+ | ❌ Use object/DTO |

### Prefer Objects Over Primitives

```php
// ❌ Too many primitives
function add($itemName, $itemId, $itemValue) { ... }

// ✅ Use object
function add(Model $model) {
    $key = $model->getKey();
}
```

### Avoid Boolean Flag Arguments

```php
// ❌ Boolean flag changes behavior
function render($template, $cached = false) { ... }

// ✅ Separate methods
function render($template) { ... }
function renderCached($template) { ... }
```

---

## Chapter 9: Error Handling

### Never Silence Exceptions

```php
// ❌ Silent failure - FORBIDDEN
try {
    $this->process($data);
} catch (\Throwable $e) {
    // nothing
}

// ✅ Log and handle
try {
    $this->process($data);
} catch (ProcessingException $e) {
    Log::error('Processing failed', [
        'data_id' => $data->id,
        'exception' => $e,
    ]);
    throw $e;
}
```

### Include Context in Error Messages

```php
// ❌ Vague error
throw new Exception('User must be inactive');

// ✅ Include ID
throw new RuntimeException(sprintf(
    'Cannot delete active user: ID=%d',
    $user->id,
));
```

### Exception Hierarchy

| Base Class | Use Case |
|------------|----------|
| `Exception` | Checked, expected errors to catch |
| `RuntimeException` | Unchecked, programming errors |

---

## Chapter 10: Comments

### What NOT to Comment

```php
// ❌ Obvious comments
// Get the user
$user = User::find($id);

// Check if active
if ($user->isActive()) { ... }
```

### What TO Comment

```php
// ✅ Explain WHY
// We fetch user before validating session because
// premium users have extended session timeouts
$user = User::find($id);
```

### Config File Examples

```php
// ❌ No example
'icons' => [],

// ✅ With example
/*
 | Example: ['fa' => storage_path('app/fontawesome')]
 */
'icons' => [],
```

### Comment Formatting (Staircase)

```php
// ❌ Jagged lines
/**
 * Short line.
 * This is a much longer line that goes on and on.
 * Medium.
 */

// ✅ Staircase - each line same or shorter
/**
 * Save user immediately to prevent data loss,
 * since subsequent actions may throw exceptions.
 * Previously we used events but it was unreliable.
 */
```

---

## Chapter 11: Delete Code

### Commented Code = Noise

```php
// ❌ Leave for later (NEVER)
// $this->legacyProcess($data);
// $token = base64_encode($payload);

// ✅ Delete it - Git has history
```

### Keep Names Consistent

If `validate()` now does more than validation, rename it:

```php
// Before: only validated login/password
// Now: validates tokens, cookies, captcha
// Action: Rename to validateAuthentication() or similar
```

---

## Chapter 12: Controllers (Laravel)

### Thin Controllers

```php
// ❌ Fat controller
class OrderController {
    public function store(Request $request) {
        // 50 lines of business logic
    }
}

// ✅ Thin controller
class OrderController {
    public function store(StoreOrderRequest $request, OrderService $service) {
        $order = $service->create($request->validated());
        return new OrderResource($order);
    }
}
```

---

## Chapter 13: AI Coding

### Always Verify AI Output

| Check | Question |
|-------|----------|
| **Context** | Did AI see related classes? |
| **Symmetry** | If import exists, does export match? |
| **Duplication** | Did AI create duplicate logic? |
| **Style** | Does it match project conventions? |

### Own the Code

- Understand AI-generated code
- Rewrite to match your style
- Integrate consciously into architecture
- AI helps write, you make decisions

---

## Quick Reference

| Category | Rule |
|----------|------|
| **Files** | README required, owner listed |
| **Format** | PHP CS Fixer, code breathing |
| **Names** | English, descriptive, no magic |
| **Methods** | 10-20 lines, 2-3 args max |
| **Flow** | Early returns, max 2 nesting |
| **Errors** | Never silence, always log with context |
| **Comments** | WHY not WHAT, include examples |
| **Hygiene** | Delete commented code, rename outdated |
| **AI** | Verify context, symmetry, ownership |
