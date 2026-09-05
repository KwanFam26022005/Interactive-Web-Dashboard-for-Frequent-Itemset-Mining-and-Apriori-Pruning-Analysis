# Interactive Research Demo — User Guide & Technical Documentation

This document describes the **Interactive Research Demo** presentation layer for the Frequent Itemset Mining (FIM) Dashboard.

> [!IMPORTANT]
> **Academic & Scientific Boundary**:
> The interactive demo visualizations (Apriori Flow Sankey, Association Rule Network, 2D/3D Rule Space Explorer) are **presentation enhancements only**.
> They do not alter mining semantics, candidate generation, pruning invariants, API response contracts, or canonical research evidence.
> The 3D WebGL explorer is **NOT** part of the formal Research Question 3 (RQ3) visualization benchmark (which remains strictly evaluated across D3 SVG, Chart.js Canvas, and ECharts Canvas). Formal benchmark scripts (RQ1, RQ2, RQ3) must never be rerun for demo purposes.

---

## 1. Prerequisites & Environment Setup

- **PHP**: PHP 8.2+ with `pdo_mysql`, `mbstring`, `json` extensions enabled.
- **MySQL / MariaDB**: MySQL 8.0+ or MariaDB 10.4+ (e.g., via Laragon or standard local service).
- **Web Browser**: Modern Chromium-based browser (Google Chrome, Microsoft Edge) or Mozilla Firefox with WebGL enabled for 3D exploration.
- **Local Server**: PHP built-in web server or Apache/Nginx.

---

## 2. Quick Start Commands

From the repository root (`D:\Projects\fim-dashboard`):

### A. Database Migration
Ensure your MySQL server is running and the database exists (defined in `.env`), then run:

```bash
php database/migrate.php development
```

### B. Launch Local Application Server
Start the PHP built-in web server pointing to the `public` directory:

```bash
php -S 127.0.0.1:8000 -t public
```

### C. Access Dashboard
Open your browser and navigate to:

```text
http://127.0.0.1:8000/
```

---

## 3. Recommended Demo Scenarios

### Scenario A: Tiny Synthetic Fixture (Correctness & Invariant Proof)

This scenario demonstrates exact Apriori candidate pruning mechanics and safe zero/few-rule handling.

1. **Select Dataset**: Choose `tiny` (or upload `tests/fixtures/tiny.csv` with format `basket_csv`).
   - Profile: 4 transactions, 3 unique items (`A`, `B`, `C`).
2. **Configure Mining Parameters**:
   - **Min Support**: `0.50` (50% threshold, requires $\ge 2$ transactions)
   - **Min Confidence**: `0.75` (75% confidence)
   - **Top N Views**: `20`
3. **Click "Run Mining"**:
   - **KPI Cards**: Count up smoothly to final values (Frequent Itemsets: `5`, Rules: `2`, Max k: `2`).
   - **Standard Visualizations**: Render the 4 analytical charts with animated transitions.
4. **Open "Interactive Research Demo"**:
   - Scroll down to the **Interactive Research Demo** panel.
   - Click the **[ Explain Apriori ]** tab:
     - **Level k=1** (`singleton_scan`): 3 generated, 0 pruned, 3 evaluated, 3 frequent. Shows full flow from Generated to Evaluated to Frequent.
     - Click **Next**:
     - **Level k=2** (`join_prune`): 3 generated ($\{A,B\}, \{A,C\}, \{B,C\}$), 0 pruned, 3 evaluated, 2 frequent ($\{A,B\}, \{A,C\}$).
     - Click **Next**:
     - **Level k=3** (`join_prune`): 1 candidate generated ($\{A,B,C\}$). Subset $\{B,C\}$ is infrequent in $L_2$. **Pruned by Apriori property: 1 (100.00% pruning ratio!)**. Evaluated: 0. Flow demonstrates candidate pruning visually: `Generated (1) → Pruned (1)`.
   - Click **[ Explore Rules ]**:
     - **Rule Network**: Displays directed links between complete itemset nodes: `{B} → {A}` and `{C} → {A}`. Notice `{A}` is reused as the single consequent node.
     - **Rule Space Explorer**: 2D scatter plot displays the 2 rules in support $\times$ confidence space with lift coloring.

---

### Scenario B: UCI Mushroom Benchmark (Rich Interactive Geometry)

This scenario demonstrates dense candidate flow, extensive pruning, and 2D/3D rule space exploration.

1. **Select Dataset**: Choose `UCI Mushroom` (8,124 transactions, 119 items).
2. **Configure Mining Parameters**:
   - **Min Support**: `0.50` (50% threshold, executes in ~1.4 seconds)
   - **Min Confidence**: `0.75` (75% confidence)
   - **Top N Views**: `20`
3. **Click "Run Mining"**:
   - Server computes Apriori across 6 levels (153 frequent itemsets, 664 association rules).
   - KPI cards animate and report summary counts, runtimes, and a ~19% pruning ratio.
4. **Explore the Demo Layer**:
   - **Explain Apriori**:
     - Click **[ ▶ Play Levels ]** to watch autoplay progress through levels $k=1, 2, 3, \dots$ at 1.2-second intervals.
     - Observe how candidate pruning volume escalates in higher levels, drastically reducing the search space before support evaluation.
     - Click **Pause** or **Restart** at any time. Autoplay stops automatically upon reaching the final level.
   - **Explore Association Rules**:
     - **Association Rule Network**:
       - View the force-directed graph.
       - Toggle filters between **Top 10**, **Top 20**, and **All Returned**.
       - Drag nodes to explore cluster connectivity; zoom in/out with the mouse wheel.
       - Hover over any directed edge to view exact support, confidence, and lift in a rich-text tooltip.
       - Click **Reset Layout** to restore the physics simulation equilibrium.
     - **2D Rule Space**:
       - Displays rules mapped on X: Support $[0, 1]$ and Y: Confidence $[0, 1]$ with bubble size and color proportional to Lift.
     - **3D WebGL Explorer**:
       - Click **[ 3D Explore ]**.
       - Notice the mandatory disclaimer badge:
         > `3D/WebGL demo enhancement — not part of the formal RQ3 D3/Chart.js/ECharts Canvas benchmark.`
       - **Interactive Camera**: Drag to rotate the 3D scatter cloud in full 3D space; wheel to zoom.
       - **Reset View**: Returns the camera to its canonical perspective ($25^\circ$ elevation, $40^\circ$ azimuth).
       - **Auto Rotate**: Click to enable smooth automated camera rotation around the Z (Lift) axis. Click again to stop immediately.
       - **Rule Detail Panel**: Click on any 3D data point to persist rule details in the adjacent inspection table (Antecedent, Consequent, Support, Support Count, Confidence, Lift).

---

## 4. Visualizations & Theoretical Semantics

| Component | Technology | Semantics & Formulas |
|---|---|---|
| **Apriori Flow Explainer** | ECharts Sankey | Single-level flow: $\text{Generated} = \text{Pruned} + \text{Evaluated}$, $\text{Evaluated} = \text{Frequent} + \text{Infrequent}$. **Never represents cross-level mass flow** ($F_k \to C_{k+1}$) because candidate generation is combinatorial, not mass-conserving. |
| **Association Rule Network** | ECharts Force Graph | Node = Complete itemset side (e.g. $\{A, B\}$), **not individual items**. Directed edges represent rules $A \to B$. Edge width $\propto \text{Confidence}$, Edge opacity $\propto \text{Support}$. Shared itemset sides reuse the exact same node. |
| **2D Rule Space** | ECharts Scatter | X = Support, Y = Confidence, Symbol Size & Color $\propto$ Lift. Dedicated exploration instance separate from standard dashboard. |
| **3D WebGL Rule Space** | ECharts-GL `scatter3D` | X = Support $[0, 1]$, Y = Confidence $[0, 1]$, Z = Lift $[0, \text{max}]$. Direct WebGL analytical scatter geometry. |

---

## 5. Accessibility & Motion Policy

- **`prefers-reduced-motion`**:
  - Automatically detected via CSS media queries and JavaScript `window.matchMedia`.
  - When active: KPI count-up transitions are disabled (final values display immediately), ECharts animation durations are set to 0, Apriori autoplay is prevented, and 3D Auto Rotate remains strictly disabled. Manual stepping controls (Previous, Next, Restart) remain fully operable.
- **Keyboard Navigation**:
  - All interactive controls are standard `<button>` elements with clear `:focus-visible` outline rings and ARIA labels.
  - Selected rule details are accessible via keyboard focus and click events.
- **Color Contrast**:
  - Text and metrics adhere to WCAG AA contrast standards. Red/Green/Blue encodings are paired with explicit text labels and percentages.

---

## 6. Troubleshooting & Fallbacks

| Symptom | Cause | Resolution |
|---|---|---|
| **"No association rules are available for this mining result"** | `min_confidence` is set too high or dataset is sparse. | Explain Apriori remains fully functional. Lower `min_confidence` (e.g., from 0.8 to 0.5) or select a denser dataset. |
| **"3D visualization is unavailable in this environment"** | Browser WebGL is disabled or running in headless mode. | The application catches WebGL initialization failures gracefully without throwing errors. Standard dashboard, Apriori flow, rule network, and 2D rule space remain 100% operational. |
| **Database Connection Error (HTTP 500)** | MySQL server is stopped or `.env` credentials mismatch. | Ensure MySQL service is running in Laragon / locally. Verify `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_NAME` in `.env`. |
| **Charts appear narrow after view switch** | Container was hidden when initialized. | ECharts resize hook (`FIMDemoVisualizations.resize()`) automatically fires upon tab switching and window resizing. |

---

## 7. Canonical File Integrity Guarantee

The demo branch (`demo/interactive-visualization`) adheres to strict canonical isolation:
- No changes exist under `experiments/**`, `docs/report/**`, `database/**`, `src/**`, or `config/**`.
- The formal RQ1, RQ2, and RQ3 benchmarks remain identical to the canonical midterm release at revision `48f8960b82779370b2e1aae19074e1f50e01475d`.
