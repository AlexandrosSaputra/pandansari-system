# Pandansari System - Contributor Guide

## Repository map

- `backend/` contains the Laravel 13 REST API, domain services, migrations, seeders, and PHPUnit tests.
- `frontend/` contains the React, TypeScript, and Vite web application.
- `ai-service/` is reserved for the future forecasting service. It is currently empty.
- `docs/` contains the project-level architecture, scope, roadmap, and verification documents.

`backend/AGENTS.md` applies to every change below `backend/` and takes precedence over this file for that subtree.

## Working conventions

- Treat the backend as the source of truth for authorization, prices, stock balances, and domain rules. The frontend must not enforce a rule that the backend does not also enforce.
- Preserve role and operational-unit isolation. A user assigned to an outlet may only access data and create transactions for that outlet.
- Make stock changes through domain services and record a corresponding `stock_movements` row in the same database transaction.
- Keep completed transactions immutable. A barcode scan may add an item to a cart, but stock is reduced only on a successful checkout.
- Do not add dependencies, change infrastructure configuration, or create migrations without an explicit request.
- Before changing code, inspect the applicable local guidance and existing tests. Use the smallest relevant test suite to verify a code change.
- Run backend tests against dedicated MySQL databases only; never use SQLite. Keep test database names clearly separated from development and production databases (for example, with a `_testing` suffix), and create the dedicated test databases when they do not yet exist.

## Documentation conventions

- Keep project documentation in `docs/` and update `docs/README.md` when adding a new document.
- Describe current implementation separately from planned capstone functionality. Do not present an unimplemented feature as available.
- Update `docs/implementation-status.md` after a material feature is completed or its verification status changes.
- Use Indonesian for user-facing project documentation unless a technical standard requires English.

## Commit-message convention

- When asked for a suitable commit comment/message (for example, `Bagaimana commit comment yang cocok?`), return one ready-to-paste Conventional Commit message in a single fenced `text` block.
- The message must use a single fenced `text` block with one concise Conventional Commit header followed directly by imperative bullet points. Do not add `Head:` or `Body:` labels or separate sections.
- Commit comments must be written entirely in English, including the head, body, scope, and bullet points.
- Choose the type by the actual change: `feat` for functionality, `fix` for a reproduced defect, `refactor` for behavior-preserving code changes, `perf` for performance improvements, `style` for formatting only, `docs` for documentation only, `test` for test-only coverage changes, `ci` for continuous-integration changes, `build` for build/dependency changes, `chore` for other non-source/test/docs work, and `revert` for reversals.
- Tests and documentation that directly support a feature or bug fix belong in the same `feat` or `fix` message.

## Current architecture decisions

- The web application is split into a Laravel API and a React client.
- Current inventory uses `inventory_balances` as a materialized balance and `stock_movements` as the auditable movement history.
- Warehouse-to-outlet transfers reserve source stock while pending and write paired transfer movements only when accepted.
- Forecasting and ESP32 device integration are planned capstone extensions; they are not yet implemented.
