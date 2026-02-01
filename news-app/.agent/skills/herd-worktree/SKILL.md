---
name: herd-worktree
description: Automate Laravel Herd worktree management for branch-based development. Creates isolated development environments with proper Herd linking, environment configuration, and dependency management.
---

# Laravel Herd Worktree Skill

Automates Git worktrees with Laravel Herd integration for isolated branch development.

## Commands

### Create Worktree
```bash
# Create worktree for a branch
git worktree add .worktrees/<branch-name> <branch-name>
```

### Link to Herd
```bash
# Link worktree to Herd (creates .test domain)
herd link .worktrees/<branch-name>
```

### Configure Environment
After creating worktree, update `.env`:
```env
APP_URL=http://<branch-name>.test
SESSION_DOMAIN=.<branch-name>.test
SANCTUM_STATEFUL_DOMAINS=<branch-name>.test
```

### Install Dependencies
```bash
cd .worktrees/<branch-name>
composer install
npm install
```

### Start Vite with CORS
```bash
npm run dev -- --host
```

## Workflow: Create Feature Branch Environment

1. **Create branch and worktree**
   ```bash
   git checkout -b feature/my-feature
   git worktree add .worktrees/my-feature feature/my-feature
   ```

2. **Link to Herd**
   ```bash
   cd .worktrees/my-feature
   herd link
   ```

3. **Configure .env**
   ```bash
   cp ../.env .env
   # Update APP_URL, SESSION_DOMAIN, SANCTUM_STATEFUL_DOMAINS
   ```

4. **Install dependencies**
   ```bash
   composer install && npm install
   php artisan migrate
   ```

5. **Access at** `http://my-feature.test`

## Workflow: Cleanup Worktree

1. **Remove Herd link**
   ```bash
   herd unlink my-feature
   ```

2. **Remove worktree**
   ```bash
   git worktree remove .worktrees/my-feature
   ```

## Key Herd Commands

| Command | Description |
|---------|-------------|
| `herd link` | Link current directory to Herd |
| `herd unlink <name>` | Remove Herd link |
| `herd list` | List all linked sites |
| `herd php <version>` | Switch PHP version |
| `herd isolate <version>` | Isolate PHP version for current site |

## Best Practices

1. **Naming**: Use branch name for worktree directory
2. **Database**: Use separate SQLite or create branch-specific DB
3. **Gitignore**: Add `.worktrees/` to `.gitignore`
4. **Cleanup**: Always unlink from Herd before removing worktree
