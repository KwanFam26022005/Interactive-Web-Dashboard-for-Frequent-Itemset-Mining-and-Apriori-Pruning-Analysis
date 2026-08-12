# System Architecture

## 1. Architectural objective

Keep the midterm system simple enough to finish and explain, while preserving clear boundaries between the browser, PHP application logic, mining logic, and persistence.

## 2. High-level architecture

```text
Browser
  |
  | HTML5 / CSS3 / Bootstrap
  | JavaScript / jQuery
  | ECharts
  |
  | AJAX + JSON
  v
PHP Web Layer
  |
  +-- Dataset endpoint/service
  +-- Mining endpoint/service
  +-- Experiment/result endpoint/service
  |
  v
Application / Mining Layer
  |
  +-- Dataset parser/validator
  +-- Apriori engine
  +-- Association-rule generator
  +-- Performance profiler
  |
  v
MySQL
  |
  +-- dataset metadata
  +-- normalized transactions/items when persisted
  +-- experiment/run metadata when persisted
```

## 3. Responsibilities

### Browser

- collect dataset/mining parameters
- send AJAX requests
- show loading/error states
- render returned results
- apply top-N/display limits without changing mining semantics

The browser must not silently recompute authoritative support/confidence/lift values differently from the backend.

### PHP web layer

- validate request shape and parameter ranges
- call application/mining services
- convert domain output to stable JSON responses
- return explicit errors rather than partial ambiguous results

### Mining layer

- own Apriori candidate generation
- own Apriori-property pruning
- own support counting
- own frequent-itemset selection
- own association-rule metrics
- emit instrumentation required by the experiment contract

### Persistence

MySQL should support course-aligned dynamic web behavior and reproducible dataset/run handling. Persistence must remain an implementation detail of the application; the mining algorithm must be testable against in-memory fixtures.

## 4. Planned frontend views

### Dataset/control area

- dataset selector and/or upload
- dataset summary
- minimum support
- minimum confidence
- optional top-N visualization control
- Run Mining action

### Overview

- transaction count
- unique item count
- frequent itemset count
- rule count
- runtime
- pruning summary

### Pattern visualization

- bar chart for top frequent itemsets
- co-occurrence heatmap for selected/top items

### Association-rule visualization

Scatter plot mapping:

- x = support
- y = confidence
- bubble size or another visual channel = lift

### Performance visualization

- generated candidates
- pruned candidates
- evaluated candidates
- frequent candidates/itemsets
- runtime versus parameter values for stored/experimental runs where available

## 5. Component boundaries

The implementation may choose concrete PHP classes/files later, but should preserve these conceptual boundaries:

```text
DatasetParser / DatasetValidator
          |
          v
     AprioriEngine
          |
          +--> LevelMetrics
          |
          v
AssociationRuleGenerator
          |
          v
      MiningResult
```

The web controller/endpoint should orchestrate these components rather than embed the Apriori algorithm directly in request-handling code.

## 6. Error model

At minimum distinguish:

- invalid request parameter
- unsupported or malformed dataset
- empty dataset
- mining constraint violation
- internal processing failure

User-facing responses should be clear while internal errors should remain diagnosable during development.

## 7. Performance guardrails

Because Apriori can exhibit candidate explosion:

- parameter validation may enforce documented safe ranges for interactive demo use
- visualization may cap rendered items/rules without truncating raw mining evidence used for experiments
- expensive stress tests should be run through the controlled experiment workflow, not casually from repeated UI slider events
- UI controls should not automatically trigger uncontrolled mining on every slider movement

## 8. Architecture non-goals

Do not introduce the following without explicit scope approval:

- distributed workers
- queue systems
- Redis
- WebSockets
- microservices
- React/Node.js application layer
- Python mining service

## 9. Architecture review questions

Before the architecture freeze, verify:

1. Can the Apriori engine be tested independently of HTTP/MySQL?
2. Are candidate/pruning metrics first-class outputs rather than debug logs?
3. Is the AJAX contract sufficient for every required visualization?
4. Can raw experimental evidence be exported without scraping chart pixels?
5. Are visualization limits separated from mining-result correctness?
