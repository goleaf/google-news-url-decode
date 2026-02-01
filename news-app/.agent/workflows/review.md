---
description: Code review workflow using Dandy Code principles for PHP/Laravel projects
---

# /review - Dandy Code Review Workflow

Run a code quality review using Dandy Code principles.

## Usage

```
/review                    # Review current file
/review path/to/file.php   # Review specific file
/review app/Models/        # Review directory
```

## Steps

### 1. Formatting Check
// turbo
```bash
# Check if PHP CS Fixer is available
php vendor/bin/pint --test || php vendor/bin/php-cs-fixer fix --dry-run --diff
```

### 2. Apply Dandy Code Checklist

Review the code against these criteria:

| Category | Check |
|----------|-------|
| **Naming** | English only, no abbreviations, booleans start with is/has/can |
| **Methods** | 10-20 lines max, 2-3 arguments max |
| **Nesting** | Max 2 levels, use early returns |
| **Errors** | No silent catches, all errors logged with context |
| **Comments** | Explain WHY not WHAT, config has examples |
| **Hygiene** | No commented code, no dead code |
| **Magic** | No magic numbers/strings, use constants/enums |

### 3. Report Findings

Format output as:

```markdown
## Dandy Code Review: [filename]

### ❌ Issues Found
- [Line X] Issue description
- [Line Y] Issue description

### ⚠️ Suggestions
- Consider splitting method X (too long)
- Variable name could be more descriptive

### ✅ Good Practices
- Early returns used correctly
- Good naming conventions
```

### 4. Offer Fixes

Ask user: "Should I fix these issues?"

If yes, apply fixes following Dandy Code principles.

---

## Quick Checks

| Issue | Fix |
|-------|-----|
| `$u`, `$cfg` | Use full names: `$user`, `$config` |
| `if (if (if()))` | Use guard clauses |
| `catch {}` empty | Add logging |
| `// old code` | Delete it |
| `3600`, `86400` | Use `SECONDS_PER_HOUR`, `SECONDS_PER_DAY` |
