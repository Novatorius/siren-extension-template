# siren-extension-template — Agent Instructions

Read this file at the start of every session. It is deliberately thin: it tells
you where the real context lives, not what the context is. Standards, patterns,
and anti-patterns live in the Navigator knowledgebase, not here.

## What This Repo Is

The template for building Siren Affiliates extensions — standalone WordPress
plugins (PHP 8.1+) that extend the Siren host plugin, either as third-party
integrations or as capability extensions. It ships scaffolding for both types
(Integration class, Handlers, Transformers, Adapters) and registers with Siren
via the `siren_ready` hook. Clone it, rename the namespace, and delete what you
don't need.

- **Initiative:** `siren` (declared in `navigator.yaml`)
- **Repository:** `Novatorius/siren-extension-template`

How extensions work — the initializer pipeline, event bindings vs listeners,
the no-facades rule, transformers/adapters, the file structure — lives in the
KB: `siren-extension-development-guide` (loaded via kb context below).

## Before Coding: Load the KB Context

Repo standards live in the Navigator KB, referenced from `knowledgeDependencies`
in `navigator.yaml`. They are not in your training data.

1. If `.claude/kb-context.md` exists, read it.
2. If it doesn't, generate it, then read it:

```bash
navigator kb context
```

Do not skip this step. For targeted lookups afterwards:

```bash
navigator kb search "<topic>" --initiative=siren --json
navigator kb show <id>
```

## Verification

The `ci` block in `navigator.yaml` is the contract. Run its commands before and
after every change and leave them green:

```bash
composer install   # ci.setup
```

The template itself has no test suite; extensions built from it should add one.
What "done" means (PR audit, testing tiers, UAT proof) is defined in the KB:
`global-definition-of-done`. Test-modification rules:
`global-unit-testing-standards-modifying-existing-tests`. Both load via kb context.

## Session End

Journal early and often, not just at the end — journals are the update feed;
discovery docs (Navigator sources) hold current state. Conventions live in the
KB: `journaling-at-novatorius` and `discovery-process`. Quick path: the `/log`
skill, or:

```bash
navigator journal submit "<summary>" "<narrative content>" \
  --initiative=siren --tags=siren-extension-template --json
```

If this session produced decisions or durable understanding, that is discovery —
capture it per `discovery-process` (journal + charter drafts), don't let it
evaporate into chat history.
