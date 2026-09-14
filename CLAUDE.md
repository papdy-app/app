# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Papdy is a **Laravel Zero** (PHP 8.3) CLI application that builds and deploys a PHP application to multiple non-containerized servers. It orchestrates build/deploy steps by driving local shell commands, SSH-remote shell commands, or SCP file transfer, based on a per-project `env.ini` config file. It is distributed as a self-contained Phar binary (`box.json`) invoked as `./papdy`.

## Commands

```bash
composer install              # install dependencies

php papdy list                # list all available commands
php papdy build --name=<ref>  # build a branch/tag/PR on the configured build server(s)
php papdy deploy --name=<ref> # build, download, upload, and deploy to the configured deploy server(s)

php papdy env:init:system                          # scaffold a new env.ini
php papdy env:init:server --name=... --type=...     # register a server (local/remote/ssh)
php papdy env:init:build --serverName=... ...        # configure a build component on a server
php papdy env:init:deploy --serverName=... ...       # configure a deploy component on a server
php papdy env:init:build:link / :env / :pre          # append link/env/pre-command entries to a build
php papdy env:init:deploy:link / :pre                # append link/pre-command entries to a deploy

php papdy app:build            # package the app into a single-file executable (box.json)

composer cs                    # php-cs-fixer dry-run diff (app/)
composer fix                   # php-cs-fixer apply (app/)
composer ps                    # phpstan analyse (level 9, app/ only)

vendor/bin/pest                # run tests (Pest, wraps PHPUnit)
vendor/bin/pest tests/Feature/SomeTest.php   # single test file
vendor/bin/pest --filter=testName            # single test by name
```

There are currently no test files under `tests/Feature` or `tests/Unit` (only `tests/Pest.php` and `tests/TestCase.php` exist) — `phpunit.xml.dist` expects those two suites.

`env.ini` (gitignored) holds the actual per-project server/build/deploy configuration and is read from the current working directory (`Config::getFileName()` = `getcwd()/env.ini`), not from the repo. The `env.ini` present in the repo root is local scratch/test data, not committed config.

## Architecture

Three parallel layers exist for every operation, all following the same naming/nesting pattern — when changing behavior, all three usually need touching:

1. **`app/Commands/**`** — Symfony/Laravel Zero console command classes. All extend `App\Commands\Base`, which builds the command signature from `getCommandName()`/`getCommandParameters()`, and centralizes option parsing/validation (`getOption`, `getRequiredOption`, `getAllowedOption`, `getFlag`). Commands only parse input and delegate to a matching `Process` class resolved via the container.
2. **`app/Models/Process/**`** — the actual orchestration logic, mirroring the `Commands` namespace 1:1 (e.g. `Commands\Env\Init\Build` → `Models\Process\Env\Init\Build`). `Process\Base` implements the shared machinery: resolving which server(s) a "component" (`build`/`deploy` id) lives on, running scripts on them, and uploading/downloading files. `Process\Build` and `Process\Deploy` are the two entry points used by the `build`/`deploy` commands.
3. **`app/Models/Process/Parameter/**`** — maps `env.ini` config keys onto the flat CLI-style parameter names the shell scripts expect (e.g. `path` → `buildPath`, `link` list → `buildLink`). Registered in `AppServiceProvider` as container bindings `process.parameters.build` / `process.parameters.deploy`, and looked up dynamically by component name from `Process\Base::prepareServerParameters()`.

**Config (`App\Models\Config`)** wraps an INI file (`env.ini`) with sections keyed by server/component name (e.g. `[app]`, `[app_build]`, `[app_deploy]`). `system.server[]` lists the known server names; each server section has `type` (`local`/`remote`/`ssh`) plus a `build`/`deploy` key pointing at another section name (the "component"). Values support scalars and INI arrays (`key[] = ...`); `Config::checkConfig()` enforces the shape. `env:init:*` commands are the intended way to mutate `env.ini` rather than editing it by hand.

**Component resolution (`Process\Base::runScript`/`upload`/`download`)**: a "components" array like `['build:all']` or `['deploy']` is walked recursively. Each entry is `name` or `name:mode` where mode is `single` (first server with that component wins) or `all` (fan out to every server that has it). This is how `deploy` targets exactly one build server but every deploy server (`['deploy:all']`).

**Command execution (`App\Models\Command\**`)** — three interchangeable backends selected by a server's `type` in `env.ini`:
- `Local` — runs shell commands with `popen`/`pclose` on the current machine, or does plain `copy()` for local up/download.
- `Remote` — extends `Local` but only flags the command as `remote` (a `--remote` CLI flag passed through); upload/download are unimplemented (throws `ScriptException`). Intended for a build/deploy script invoked on a machine that is itself remote-orchestrating, not for arbitrary SSH.
- `SSH` — uses `phpseclib3` SCP for exec/upload/download, supports `agent`/`password`/`key`/`file` auth (`Config` keys `auth`, `password`, `privateKey`, `privateKeyFile`).

All three share `completeCommand()` (turns an associative parameter array into `--key "value"` CLI args) and `processResult()` (parses the trailing `Exit status: N` marker appended to script output to recover the real exit code, since SSH exec doesn't expose one directly).

**Shell scripts (`scripts/bash/<shell>/...`)** are the actual build/deploy implementation and are shipped alongside the Phar (`Path::getBasePath()` extracts scripts out of the phar to a temp dir at runtime, since scripts must be plain files, not phar-packed, to be copied/executed on remote hosts). `Process\Base::executeRun()` resolves which script to run by walking `scriptPaths` params (most-specific first) under `scripts/bash/<shell>/`. Every script sources `prepare-parameters.sh` first, which turns `--key value` CLI args into bash variables (including repeated `--key` becoming a bash array) — this is the bridge between the PHP-side parameter arrays and bash variable names like `buildPath`, `deployLink`, etc. Build supports two source types: `git` (clone/checkout branch, tag, or PR ref, strip `.git`, tar the result) or `composer` (create-project against a Composer repository, optionally with basic-auth project credentials). Deploy extracts the build tarball into a timestamped release dir and re-links the web path to it (classic symlink-swap deploy).

**Pre/post commands**: each build/deploy component can carry `pre[]`/`post[]` INI list entries (mapped to `buildPre`/`buildPost`/`deployPre`/`deployPost` parameters by `Process\Parameter\Build`/`Deploy`). `Process\Base::executeCommand()` runs pre-commands (with `{placeholder}` substitution against the current parameter set via `Strings::replacePlaceHolders()`) through the resolved server environment (`Local`/`Remote`/`SSH`) before the main script, and collects post-commands to run the same way afterward. `env:init:build:pre` / `env:init:deploy:pre` append to the `pre[]` list; there is no equivalent `:post` command yet, so `post[]` entries must be added directly to `env.ini`. The `env:init:build:*`/`env:init:deploy:*` subcommands (`Link`/`Env`/`Pre`) share option parsing via `Commands\Env\Init\Build\Base`/`Deploy\Base` (`getBaseCommandParameters()`/`getBaseOptions()` for `serverName`/`host`/`id`).

## Code style

- `declare(strict_types=1)` and a standard file header docblock (`@author`/`@copyright`/`@license`) are used on essentially every class — match this on new files.
- PHPStan runs at level 9 against `app/` only (`phpstan.neon`); keep array shapes documented via `@param`/`@return` PHPDoc as done throughout, since level 9 requires precise generics for arrays.
- `.php-cs-fixer.dist.php` targets `app` and `tests` with `@PHP7x1Migration`, `@PSR12`, `@PhpCsFixer` rule sets plus `operator_linebreak` at `end`.
