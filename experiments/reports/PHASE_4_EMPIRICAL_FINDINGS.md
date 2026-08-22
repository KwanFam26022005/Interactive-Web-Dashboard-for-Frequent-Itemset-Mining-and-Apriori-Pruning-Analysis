# Phase 4 Empirical Findings & Research Evidence Report

**Project:** Interactive Web Dashboard for Frequent Itemset Mining and Apriori Pruning Analysis  
**Repository:** `D:\Projects\fim-dashboard`  
**Phase:** Phase 4E — Final Evidence Freeze & Empirical Findings  
**Date:** August 2026  

---

## 1. Evidence Lineage & Freeze Summary

All empirical benchmarks in this project are frozen across deterministic revisions with complete cryptographic provenance:

- **Formal RQ1/RQ2 Mining Run Revision:** `fd318b3ca0d3829c0849ee2a5ef783caaae72fdb`
- **Phase 4C Evidence Commit:** `b40022fda83e754078c1a5d4fbd028eb58315917`
- **Formal RQ3 Visualization Run Revision:** `dea90c0962f03872e24c6959cea1959782d446a6`
- **Phase 4D RQ3 Evidence Capture Commit:** `cb34745bfdc4838c878aecbc904ad8ade530a62b`
- **Phase 4D RQ3 Acceptance Revision:** `614658dc59d41eff3e72bca40a4d2d24c8d8d4b2`
- **Phase 4E Derivative Generator Revision:** `dadd18ab7cad5989176836cd6d19b8db76293b9a`

---

## 2. Dataset & Experimental Context

- **Primary Dataset:** UCI Mushroom dataset (`agaricus-lepiota.data`)
- **Dataset Checksum (SHA-256):** `e65d082030501a3ebcbcd7c9f7c71aa9d28fdfff463bf4cf4716a3fe13ac360e`
- **Transactions ($N$):** 8,124
- **Physical Columns:** 23
- **Unique Canonical Items:** 119
- **Formal Support Thresholds:** $[0.60, 0.50, 0.45, 0.40, 0.35]$ (corresponding to required transaction counts of 4,875, 4,062, 3,656, 3,250, and 2,844 transactions)
- **Minimum Confidence:** $0.75$ ($75\%$)
- **Repetitions:** 10 formal runs per support/workload after 2 warmup iterations
- **Dispersion Metric:** Median and Interquartile Range (IQR) calculated via standard Tukey-hinges

---

## 3. Research Question 1 (RQ1): Effect of Minimum Support on Candidate Generation, Output, and Runtime

### Evidence Table (Table T1)

| $\text{min\_support}$ | Required Count | Candidates Generated | Candidates Pruned | Candidates Evaluated | Frequent Itemsets | Rules Count ($\text{conf} \ge 0.75$) | Max $k$ | Median Apriori Runtime (ms) | IQR Runtime (ms) | Pruning Ratio |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **0.60** | 4,875 | 185 | 11 | 174 | 51 | 223 | 5 | **523.072** | 317.164 | 0.059459 |
| **0.50** | 4,062 | 336 | 29 | 307 | 153 | 664 | 5 | **1,424.078** | 238.000 | 0.086310 |
| **0.45** | 3,656 | 641 | 115 | 526 | 329 | 1,859 | 6 | **3,322.764** | 551.320 | 0.179407 |
| **0.40** | 3,250 | 1,104 | 280 | 824 | 565 | 3,576 | 7 | **5,737.617** | 1,270.777 | 0.253623 |
| **0.35** | 2,844 | 2,131 | 624 | 1,507 | 1,189 | 11,055 | 7 | **14,047.443** | 5,549.082 | 0.292820 |

### Concise Answer
On the frozen Mushroom benchmark dataset and Apriori implementation, lowering the minimum support threshold from $0.60$ to $0.35$ was associated with a substantial expansion of candidate generation ($185 \to 2,131$), frequent itemset discovery ($51 \to 1,189$), association rule volume ($223 \to 11,055$), and median execution time ($523.072\text{ ms} \to 14,047.443\text{ ms}$).

### Interpretation
As the support threshold decreases, the number of singletons and low-order frequent itemsets that satisfy the threshold increases. Because candidate generation at level $k+1$ depends combinatorially on the size of the frequent itemset pool $L_k$, the candidate search space grows rapidly. This expansion drives higher support evaluation workloads over the transaction set, increasing overall execution runtime from approximately $0.5\text{ seconds}$ at $\text{min\_support}=0.60$ to over $14.0\text{ seconds}$ at $\text{min\_support}=0.35$.

---

## 4. Research Question 2 (RQ2): Apriori-Property Pruning Dynamics and Efficiency

### Overall Pruning Evidence (Table T2)

| $\text{min\_support}$ | Candidates Generated | Candidates Pruned | Candidates Evaluated | Overall Pruning Ratio |
| :--- | :--- | :--- | :--- | :--- |
| **0.60** | 185 | 11 | 174 | **5.95%** (0.059459) |
| **0.50** | 336 | 29 | 307 | **8.63%** (0.086310) |
| **0.45** | 641 | 115 | 526 | **17.94%** (0.179407) |
| **0.40** | 1,104 | 280 | 824 | **25.36%** (0.253623) |
| **0.35** | 2,131 | 624 | 1,507 | **29.28%** (0.292820) |

### Per-Level Pruning Dynamics (Table T2b Summary)

- **Level $k=1$ (Singleton Scan):** Across all supports, 119 singletons were scanned with 0 pruned by definition, as singletons have no proper non-empty subsets.
- **Level $k=2$ (Join-Prune):** 0 pairs pruned because all items joined from $L_1$ are frequent by construction.
- **Levels $k \ge 3$ (Join-Prune):** The join-prune stage applies from $k \ge 2$. In the formal observations, non-zero Apriori subset-pruning elimination first appeared at $k = 3$; $k = 2$ generated no pruned candidates. At higher levels, pruning activates strongly:
  - At $\text{min\_support}=0.60$: Pruning eliminated $17.4\%$ of candidates at $k=3$, $46.2\%$ at $k=4$, and $50.0\%$ at $k=5$.
  - At $\text{min\_support}=0.35$: Pruning eliminated $208$ of $563$ candidates ($36.9\%$) at $k=3$, $243$ of $649$ candidates ($37.4\%$) at $k=4$, $134$ of $390$ candidates ($34.4\%$) at $k=5$, and $34$ of $118$ candidates ($28.8\%$) at $k=6$.

### Concise Answer
Apriori-property pruning removed an increasing fraction of candidate itemsets within the evaluated support range, scaling from $5.95\%$ of total generated candidates at $\text{min\_support}=0.60$ to $29.28\%$ at $\text{min\_support}=0.35$. Non-zero subset elimination first appeared at itemset length $k = 3$, preventing hundreds of infrequent candidate itemsets from undergoing full transaction support evaluation.

---

## 5. Research Question 3 (RQ3): Comparative Front-End Visualization Performance

### Formal Protocol Context
- **Formal Run Revision:** `dea90c0962f03872e24c6959cea1959782d446a6`
- **Stage Container:** $800 \times 500\text{ px}$ with 5 fixed linear gridlines per axis
- **Runtime Environment:** Microsoft Edge `151.0.0.0` at inner viewport $1440 \times 900\text{ px}$, Device Pixel Ratio (DPR) `1.0`, display scaling `1.0`
- **Workload Data:** Single canonical `workload_data.json` bundle generated via Mulberry32 PRNG (seed `0xDEADBEEF`)
- **In-Place Update Invariant:** Exact $50\%$ point displacement ($y_i \leftarrow (y_i + 0.1) \pmod{1.0}$) preserving $50\%$ identical base points
- **Timing & Settle Protocol:** Render-to-two-frame-observation latency (`performance.now()` across `double-requestAnimationFrame` boundary) with $100\text{ ms}$ inter-trial settle delay and natural garbage collection
- **Design Matrix:** 3 libraries $\times$ 4 workload sizes ($100, 1000, 5000, 10000$) $\times$ 10 formal repetitions = 120 recorded observations (following 24 unrecorded warmup trials)

### Formal Evidence Table (Table T3)

| Library | Version | Renderer | Workload Size ($N$) | Valid Runs | Median Initial Render (ms) | IQR Initial Render (ms) | Median Data Update (ms) | IQR Data Update (ms) |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Chart.js** | 4.4.8 | Canvas | **100** | 10 / 10 | **18.200** | 4.200 | **33.350** | 0.400 |
| **Chart.js** | 4.4.8 | Canvas | **1,000** | 10 / 10 | **23.000** | 15.500 | **33.300** | 0.100 |
| **Chart.js** | 4.4.8 | Canvas | **5,000** | 10 / 10 | **20.400** | 14.600 | **33.200** | 0.100 |
| **Chart.js** | 4.4.8 | Canvas | **10,000** | 10 / 10 | **25.900** | 12.000 | **33.400** | 0.200 |
| **D3.js** | 7.9.0 | SVG | **100** | 10 / 10 | **18.550** | 3.600 | **33.350** | 0.200 |
| **D3.js** | 7.9.0 | SVG | **1,000** | 10 / 10 | **31.900** | 12.900 | **33.200** | 0.300 |
| **D3.js** | 7.9.0 | SVG | **5,000** | 10 / 10 | **37.300** | 7.500 | **33.200** | 13.000 |
| **D3.js** | 7.9.0 | SVG | **10,000** | 10 / 10 | **72.600** | 13.700 | **64.550** | 16.100 |
| **Apache ECharts** | 5.6.0 | Canvas | **100** | 10 / 10 | **27.800** | 15.200 | **33.100** | 0.300 |
| **Apache ECharts** | 5.6.0 | Canvas | **1,000** | 10 / 10 | **18.300** | 9.600 | **33.200** | 0.200 |
| **Apache ECharts** | 5.6.0 | Canvas | **5,000** | 10 / 10 | **43.200** | 3.300 | **54.550** | 8.600 |
| **Apache ECharts** | 5.6.0 | Canvas | **10,000** | 10 / 10 | **88.500** | 33.600 | **94.050** | 19.800 |

### Initial Render & Update Analysis
- **Small Workloads ($N \le 1,000$):** At the smaller workloads, the measured medians remained near frame-scale observation boundaries and did not support a simple stable ordering across libraries. Initial render medians ranged between $18.200\text{ ms}$ and $31.900\text{ ms}$, while update medians clustered tightly around $33.100\text{ ms} - 33.350\text{ ms}$ (representing two $60\text{-Hz}$ frame observation quantization intervals).
- **Medium Workload ($N = 5,000$):** At $N = 5,000$, Chart.js recorded the lowest median initial-render latency ($20.400\text{ ms}$), followed by D3.js SVG ($37.300\text{ ms}$) and Apache ECharts Canvas ($43.200\text{ ms}$). For data update, Chart.js and D3.js recorded identical stored medians ($33.200\text{ ms}$), while ECharts scaled to $54.550\text{ ms}$.
- **Dense Workload ($N = 10,000$):** Under the frozen Edge 151 scatter workload at $N = 10,000$, Chart.js recorded the lowest median render-to-two-frame-observation latency ($25.900\text{ ms}$ render / $33.400\text{ ms}$ update), followed by D3.js SVG ($72.600\text{ ms}$ render / $64.550\text{ ms}$ update) and Apache ECharts Canvas ($88.500\text{ ms}$ render / $94.050\text{ ms}$ update).
- **Update vs. Render Dynamics:** The relative cost of in-place update versus initial render was library- and workload-dependent in the accepted formal run. For D3 at $N \ge 5,000$, update latency was lower than initial render ($33.200\text{ ms}$ vs $37.300\text{ ms}$ at $N=5,000$; $64.550\text{ ms}$ vs $72.600\text{ ms}$ at $N=10,000$). Conversely, for Chart.js and ECharts, update latency was higher than initial render at several workload points (e.g., Chart.js at $N=10,000$: $33.400\text{ ms}$ update vs $25.900\text{ ms}$ render; ECharts at $N=10,000$: $94.050\text{ ms}$ update vs $88.500\text{ ms}$ render). The accepted evidence does not support a universal claim that in-place updates are uniformly faster than initial creation.
- **Empirical Trajectory Non-Monotonicity:** The medians did not increase monotonically at every adjacent workload size, particularly near frame-scale observations (e.g., Chart.js render $N=1,000 \to 5,000$: $23.000\text{ ms} \to 20.400\text{ ms}$; ECharts render $N=100 \to 1,000$: $27.800\text{ ms} \to 18.300\text{ ms}$). These represent faithful empirical observations under frame-quantized browser scheduling.

### Concise Answer
Under the frozen Chromium/Edge 151 environment, $800 \times 500\text{ px}$ stage, and standardized render-to-two-frame-observation latency protocol, Chart.js recorded the lowest median latency at dense workloads ($N=10,000$), followed by D3.js SVG and Apache ECharts Canvas. At smaller workloads ($N \le 1,000$), latencies clustered near discrete frame boundaries without establishing a single universal ranking across libraries or operations.

---

## 6. Threats to Validity & Experimental Limitations

### Mining Experiment Limitations (RQ1 & RQ2)
1. **Single Dataset Context:** Observations were gathered exclusively on the UCI Mushroom dataset (`agaricus-lepiota.data`, $N=8,124$, 119 distinct items). Generalization across dense vs. sparse retail or webclick transaction characteristics requires further multi-dataset studies.
2. **Support Matrix Calibration:** The initial lower-support matrix was revised before formal collection after a non-evidentiary feasibility probe showed that the original thresholds violated the frozen runtime guardrail on the Mushroom dataset and current implementation. In additional probe points, support 0.25 exceeded the Apriori timeout, while support 0.30 completed Apriori but exceeded the rule-generation limit.
3. **No Unpruned Timing Baseline:** Pruning effectiveness is reported as candidate count reduction and pruning ratios. Absolute execution time savings cannot be quantified without an unpruned baseline engine.
4. **Candidate Monotonicity:** Candidate-count monotonicity is an empirical diagnostic outcome on this dataset rather than a universal mathematical invariant.
5. **Fixed Confidence:** Association rule generation was evaluated at a constant threshold ($\text{min\_conf} = 0.75$).

### Visualization Benchmark Limitations (RQ3)
1. **Renderer Architecture Coupling:** D3.js was tested with an SVG DOM renderer while Chart.js and ECharts used HTML5 Canvas rasterization. Renderer architecture represents an intrinsic part of the library framework treatment rather than an isolated algorithmic comparison.
2. **Double-rAF Frame Quantization:** The double `requestAnimationFrame` metric measures render-to-two-frame-observation latency. Timing observations cluster near discrete browser frame synchronization intervals ($16.7\text{ ms}, 33.3\text{ ms}$, etc.). The metric explicitly does not measure GPU, paint, or presentation completion.
3. **Plot Layout Engine:** Outer stages were fixed at $800 \times 500\text{ px}$ with 5 fixed linear gridlines, but inner scale margins followed library-native auto-layout.
4. **Garbage Collection:** Browser runtime garbage collection remains uncontrolled background jitter, mitigated by reporting medians and IQRs across 10 formal repetitions per treatment.
5. **Environment & Workload Scope:** Evaluated under a single frozen Edge/browser environment on 2D numerical scatter plots with unchunked updates and disabled animations.

---

## 7. Canonical Figure Inventory

| Figure ID | File Path | Concept / Research Question | Canvas Size |
| :--- | :--- | :--- | :--- |
| **Figure F1** | [`experiments/figures/F1_apriori_runtime_vs_support.svg`](file:///D:/Projects/fim-dashboard/experiments/figures/F1_apriori_runtime_vs_support.svg) | Apriori Runtime vs. Minimum Support (RQ1) | $1200 \times 800\text{ px}$ |
| **Figure F2** | [`experiments/figures/F2_candidate_volume_vs_support.svg`](file:///D:/Projects/fim-dashboard/experiments/figures/F2_candidate_volume_vs_support.svg) | Candidate Search Space vs. Minimum Support (RQ1 / RQ2) | $1200 \times 800\text{ px}$ |
| **Figure F3** | [`experiments/figures/F3_pattern_output_vs_support.svg`](file:///D:/Projects/fim-dashboard/experiments/figures/F3_pattern_output_vs_support.svg) | Pattern Output Volume (Itemsets & Rules) vs. Support (RQ1) | $1200 \times 800\text{ px}$ |
| **Figure F4** | [`experiments/figures/F4_pruning_dynamics_per_level.svg`](file:///D:/Projects/fim-dashboard/experiments/figures/F4_pruning_dynamics_per_level.svg) | Pruning Dynamics Across Levels for All 5 Supports (RQ2) | $1200 \times 800\text{ px}$ |
| **Figure F5** | [`experiments/figures/F5_visualization_initial_render.svg`](file:///D:/Projects/fim-dashboard/experiments/figures/F5_visualization_initial_render.svg) | Initial Visualization Render Latency vs. Workload Size (RQ3) | $1200 \times 800\text{ px}$ |
| **Figure F6** | [`experiments/figures/F6_visualization_update.svg`](file:///D:/Projects/fim-dashboard/experiments/figures/F6_visualization_update.svg) | Visualization In-Place Update Latency vs. Workload Size (RQ3) | $1200 \times 800\text{ px}$ |

---

## 8. Canonical Evidence Checksums (SHA-256)

### Source Evidence Files
- `experiments/raw/mushroom_support_runs.csv`: `022a56cbe99344c76a8fd51cbe0329a48e4804815f6b861614fb266cfe5fc641`
- `experiments/raw/mushroom_pruning_levels.csv`: `613632ed7fd961ba155b8ca92ad23a2e30d271d6663ffec0d034bd6176303c11`
- `experiments/processed/mushroom_support_summary.csv`: `1b60921ada3edbb2f4625683338729d3e8f0dc090ae9782b3746bbcb7798f0d2`
- `experiments/processed/mushroom_pruning_summary.csv`: `b89a2fb983113861a7df23ed3832fc5fa983e3b3bdcbc3784851018540c804f2`
- `experiments/raw/visualization_runs.csv`: `9e80833a32f392a2836217287e363f5cb1081afe3ea7a9aba1e0f3c232ed27f4`
- `experiments/processed/visualization_summary.csv`: `8628fb9568d78f21f9b475b3bd4411a0e15ea889ea1a186022da8de2b6591cc0`

### Generated Figure & Table Artifacts
- `experiments/figures/F1_apriori_runtime_vs_support.svg`: `01f26608f18c5d5a51b72ba4a10e81e08b34f2a8f8cfbc7a44dd3bc1afbac15c`
- `experiments/figures/F2_candidate_volume_vs_support.svg`: `d7e63ac8ac310c8950bc26da2231f38aa6befa052ce7fb89d9f8fd6d7e89b739`
- `experiments/figures/F3_pattern_output_vs_support.svg`: `5ded8c2dc9afac383879766af1c874cd262345fc9b2245860c3b158afa09fad3`
- `experiments/figures/F4_pruning_dynamics_per_level.svg`: `513ef1de89e170d4769bf5246afbd8b733d99a346aedd980dd76d06a4a6c84fe`
- `experiments/figures/F5_visualization_initial_render.svg`: `c30f1e5a1151f00844cc83e3cb0221490f0f2312f3a0e11fce1eb9bcaa933df3`
- `experiments/figures/F6_visualization_update.svg`: `fd3d4421c217a79efca3165eedf5bd744b5510ef67fed6725d240c5bf4d7a48c`
- `experiments/tables/T1_rq1_support_effect.csv`: `969432e8ba2a03b33520cecf5d2396d4ea574845c89822aaf7f629072b769466`
- `experiments/tables/T2_rq2_overall_pruning.csv`: `ecddf3a435632753052f760d9409fab15099c4edac5edd47ad0a3f6b5e3e5abe`
- `experiments/tables/T2b_rq2_per_level_pruning.csv`: `103be5a479102576e8e4517c63cc5e3844eb1f5f312ea4e95d2c37ad4bc18acb`
- `experiments/tables/T3_rq3_visualization_performance.csv`: `8628fb9568d78f21f9b475b3bd4411a0e15ea889ea1a186022da8de2b6591cc0`

---

## 9. Report-Writing Guardrails

When drafting the final academic report, adhere strictly to these empirical constraints:
1. Cite medians and IQRs rather than means or arbitrary single observations.
2. State the frozen experimental parameter context whenever citing numbers.
3. Treat renderer architecture (Canvas vs. SVG) as an integrated component of each library framework treatment.
4. Do not state claims of performance superiority where timing disparities fall within single frame quantization boundaries ($< 16.7\text{ ms}$).
5. Do not assert that in-place updates are universally faster than initial render across all libraries or workload scales.
