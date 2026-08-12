# Codex–Antigravity Development Workflow

## 1. Operating principle

Use the strongest reasoning where design correctness matters, and use the execution-focused agent for most implementation and command-running work.

```text
Project owner
    |
    v
Codex: reason/design/review
    |
    | implementation contract
    v
Antigravity: implement/test/run
    |
    | evidence + report
    v
Codex: audit
    |
    +--> PASS -> commit/freeze -> next task
    |
    +--> REMEDIATE -> Antigravity fixes
```

## 2. When to use Codex

Use Codex when the task involves one or more of:

- new architecture or boundary decisions
- algorithm semantics
- pruning/candidate-generation correctness
- database/API breaking changes
- benchmark design
- statistical/experimental interpretation
- difficult root-cause reasoning
- deciding whether evidence satisfies a phase gate
- reconciling conflicting requirements

Codex should produce a precise contract rather than large amounts of implementation code whenever practical.

## 3. When to use Antigravity

Use Antigravity for:

- creating/scaffolding files
- implementing approved PHP classes/endpoints
- implementing SQL schema/migrations
- implementing HTML/CSS/Bootstrap
- implementing JavaScript/jQuery/AJAX
- chart implementation
- unit/integration tests from an approved oracle/contract
- running tests, linters, setup commands
- importing datasets
- executing frozen experiment matrices
- generating raw outputs and figures
- mechanical fixes/refactors

## 4. Standard Codex-to-Antigravity task template

```text
TASK: <short task name>

1. Objective
   <single outcome>

2. Existing state
   <relevant files/contracts/current gate>

3. Allowed changes
   <exact directories/files or boundaries>

4. Requirements
   - ...
   - ...

5. Non-goals
   - ...
   - ...

6. Validation
   - exact tests/commands
   - required invariants

7. Required final report
   A. Files created
   B. Files modified
   C. Dependencies changed
   D. Implementation summary
   E. Tests added/updated
   F. Commands executed
   G. Test/validation results
   H. Known limitations
   I. Git status/commit
   J. Final token
```

## 5. Standard Antigravity completion behavior

Antigravity should not merely state that implementation is complete. It must provide evidence.

Acceptable completion tokens should be task-specific, for example:

```text
TASK_IMPLEMENTATION_PASS
```

Phase-level tokens are reserved for reviewed phase gates and are defined in `TIMELINE.md`.

## 6. Blocker protocol

When blocked by a reasoning/design issue, Antigravity must stop before making speculative architecture changes and return:

```text
BLOCKED_FOR_REASONING
```

Required blocker structure:

```text
Problem:
Evidence:
Current behavior:
Why the existing contract is insufficient:
Options:
Affected files/components:
Recommended question for reasoning review:
```

## 7. Review protocol

After an implementation task, Codex should review in this order:

1. Contract compliance
2. Correctness
3. Tests and evidence
4. Data/API compatibility
5. Scope compliance
6. Maintainability proportional to midterm scope
7. Only then style/polish

Possible outcomes:

```text
REVIEW_PASS
REMEDIATION_REQUIRED
BLOCKED_BY_REQUIREMENT
```

## 8. Git discipline

- Keep commits scoped to a coherent task.
- Do not combine unrelated feature work during a remediation pass.
- Record meaningful commit messages.
- Phase freeze should reference a known commit SHA.
- Do not rewrite experimental raw data in place after it has been used for analysis; produce a corrected run/file with provenance if a rerun is required.

## 9. Documentation discipline

Contracts in `docs/` are authoritative until deliberately revised.

If implementation and documentation disagree:

1. stop
2. determine whether implementation is wrong or the contract must change
3. review the decision
4. update contract and code consistently

Do not silently let documentation drift.

## 10. Experiment discipline

Codex freezes methodology; Antigravity executes it.

Antigravity may report anomalies but must not:

- remove inconvenient observations without a documented rule
- change thresholds after seeing results just to improve plots
- infer unsupported causal explanations
- fabricate missing runs

Codex interprets the final evidence and explicitly distinguishes observation from inference.

## 11. Scope escalation

A feature request is considered scope-expanding if it introduces a new major algorithm, runtime, service, or user-management subsystem.

Examples requiring explicit approval:

- FP-Growth implementation
- clustering
- Python service
- Node.js backend
- Redis/queues
- authentication
- cloud deployment architecture

## 12. Daily practical workflow

A productive work session should usually be:

```text
1. Read current gate + relevant docs
2. Pick one bounded task
3. Codex reasons only if ambiguity/design exists
4. Hand contract to Antigravity
5. Antigravity implements and validates
6. Review evidence
7. Commit/freeze
8. Update docs only when the contract actually changed
```

Avoid having Codex and Antigravity modify the same architectural area concurrently.
