<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FIM Dashboard — Frequent Itemset Mining &amp; Apriori Pruning Analysis</title>
  <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/app.css">
  <link rel="stylesheet" href="assets/css/demo-visualizations.css">
</head>
<body>
  <!-- Header / Project Identity -->
  <header id="app-header" class="sticky-top">
    <nav class="navbar navbar-expand-lg navbar-light py-2">
      <div class="app-container d-flex justify-content-between align-items-center">
        <div>
          <span class="brand-title">FIM Dashboard</span>
          <span class="brand-subtitle ms-2 d-none d-sm-inline">| Frequent Itemset Mining &amp; Apriori Pruning Analysis</span>
        </div>
        <div class="d-flex align-items-center">
          <span class="badge bg-light text-dark border font-monospace">Apriori Core v1.0</span>
        </div>
      </div>
    </nav>
  </header>

  <main class="app-container py-3">

    <!-- Dataset Panel -->
    <section id="dataset-panel" class="mb-3" aria-label="Dataset Management">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
          <div class="d-flex align-items-center gap-2">
            <h2>Active Dataset</h2>
            <span class="card-subtitle">Repository Store</span>
          </div>
          <button type="button" class="btn btn-outline-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#importDatasetModal">
            + Import Dataset
          </button>
        </div>
        <div class="card-body py-2">
          <div class="row g-3 align-items-center">
            <!-- Dataset Selector -->
            <div class="col-lg-5">
              <label for="dataset-select" class="form-label mb-1">Select Dataset</label>
              <select id="dataset-select" class="form-select">
                <option value="">Loading datasets…</option>
              </select>
            </div>
            <!-- Metadata Container -->
            <div class="col-lg-7">
              <div id="dataset-meta" class="border rounded p-2 bg-light">
                <span class="text-muted small">Select a dataset to view its profile metadata.</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Import Dataset Modal -->
    <div class="modal fade" id="importDatasetModal" tabindex="-1" aria-labelledby="importDatasetModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header py-2">
            <h3 class="modal-title h6 mb-0" id="importDatasetModalLabel">Import New Dataset</h3>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="upload-form" enctype="multipart/form-data">
              <div class="mb-2">
                <label for="upload-format" class="form-label">Format Profile</label>
                <select id="upload-format" name="format" class="form-select" required>
                  <option value="basket_csv">basket_csv (.csv)</option>
                  <option value="basket_txt">basket_txt (.txt, .dat)</option>
                  <option value="mushroom">mushroom (.csv, .data)</option>
                </select>
              </div>
              <div class="mb-2">
                <label for="upload-name" class="form-label">
                  Dataset Name <span class="text-muted fw-normal">(optional)</span>
                </label>
                <input type="text" id="upload-name" name="name" class="form-control"
                       maxlength="120" placeholder="Defaults to source filename">
              </div>
              <div class="mb-3">
                <label for="upload-file" class="form-label">Source File (max 10 MiB)</label>
                <input type="file" id="upload-file" name="file" class="form-control"
                       accept=".csv,.txt,.dat,.data" required>
              </div>
              <div class="d-flex justify-content-between align-items-center">
                <button type="submit" id="upload-submit" class="btn btn-primary btn-sm px-3">
                  Upload &amp; Import
                </button>
                <span class="text-muted small">Synchronous atomic persistence</span>
              </div>
            </form>
            <div id="upload-warnings" class="mt-2" role="region" aria-label="Upload Warnings"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Mining Controls -->
    <section id="mining-panel" class="mb-3" aria-label="Mining Parameters">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
          <h2>Mining Parameters &amp; Execution</h2>
          <span class="card-subtitle">Server-Side Apriori Engine</span>
        </div>
        <div class="card-body py-2 px-3">
          <div class="row g-2 align-items-end">
            <div class="col-6 col-sm-6 col-lg-3">
              <label for="support-input" class="form-label mb-1">
                Min Support <span class="text-muted">(0, 1]</span>
              </label>
              <input type="number" id="support-input" class="form-control font-monospace"
                     min="0.000001" max="1" step="0.01" value="0.5">
            </div>
            <div class="col-6 col-sm-6 col-lg-3">
              <label for="confidence-input" class="form-label mb-1">
                Min Confidence <span class="text-muted">[0, 1]</span>
              </label>
              <input type="number" id="confidence-input" class="form-control font-monospace"
                     min="0" max="1" step="0.01" value="0.75">
            </div>
            <div class="col-6 col-sm-6 col-lg-3">
              <label for="topn-input" class="form-label mb-1">
                Top N Views <span class="text-muted">[1, 100]</span>
              </label>
              <input type="number" id="topn-input" class="form-control font-monospace"
                     min="1" max="100" step="1" value="20">
            </div>
            <div class="col-6 col-sm-6 col-lg-3">
              <button type="button" id="run-mining-btn" class="btn btn-primary w-100 fw-semibold" disabled>
                Run Mining
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Status / Notification Region -->
    <div id="status-region" class="mb-3" role="status" aria-live="polite"></div>

    <!-- KPI Summary Cards -->
    <section id="kpi-panel" class="mb-3 d-none" aria-label="Summary Performance Indicators">
      <div class="row g-2">
        <div class="col-6 col-sm-4 col-md-3 col-xl">
          <div class="card text-center h-100">
            <div class="card-body py-2 px-1">
              <div id="kpi-frequent-itemsets" class="kpi-value font-monospace">—</div>
              <div class="kpi-label">Frequent Itemsets</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-sm-4 col-md-3 col-xl">
          <div class="card text-center h-100">
            <div class="card-body py-2 px-1">
              <div id="kpi-rules-count" class="kpi-value font-monospace">—</div>
              <div class="kpi-label">Association Rules</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-sm-4 col-md-3 col-xl">
          <div class="card text-center h-100">
            <div class="card-body py-2 px-1">
              <div id="kpi-runtime" class="kpi-value font-monospace">—</div>
              <div class="kpi-label">Apriori Runtime</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-sm-4 col-md-3 col-xl">
          <div class="card text-center h-100">
            <div class="card-body py-2 px-1">
              <div id="kpi-rule-runtime" class="kpi-value font-monospace">—</div>
              <div class="kpi-label">Rule Gen Runtime</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-sm-4 col-md-3 col-xl">
          <div class="card text-center h-100">
            <div class="card-body py-2 px-1">
              <div id="kpi-candidates-generated" class="kpi-value font-monospace">—</div>
              <div class="kpi-label">Candidates Gen</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-sm-4 col-md-3 col-xl">
          <div class="card text-center h-100">
            <div class="card-body py-2 px-1">
              <div id="kpi-pruning-ratio" class="kpi-value font-monospace">—</div>
              <div class="kpi-label">Pruning Ratio</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-sm-4 col-md-3 col-xl">
          <div class="card text-center h-100">
            <div class="card-body py-2 px-1">
              <div id="kpi-max-k" class="kpi-value font-monospace">—</div>
              <div class="kpi-label">Max Level (k)</div>
            </div>
          </div>
        </div>
      </div>
      <!-- Result limits info -->
      <div id="result-limits-info" class="mt-2 d-none" role="region" aria-label="Result Limit Notice"></div>
    </section>

    <!-- Visualizations Panel -->
    <section id="viz-panel" class="mb-3 d-none" aria-label="Mining Visualizations">
      <div class="row g-3">
        <!-- Frequent Itemset Horizontal Bar (~58% desktop width) -->
        <div class="col-lg-7">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
              <h3>Top Frequent Itemsets</h3>
              <span class="card-subtitle">Support Count Ranking</span>
            </div>
            <div class="card-body p-2">
              <div id="itemset-chart" class="chart-container"></div>
            </div>
          </div>
        </div>

        <!-- Association Rules Scatter (~42% desktop width) -->
        <div class="col-lg-5">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
              <h3>Association Rules</h3>
              <span class="card-subtitle">Support × Confidence (Bubble Size &amp; Color = Lift)</span>
            </div>
            <div class="card-body p-2">
              <div id="rule-chart" class="chart-container"></div>
            </div>
          </div>
        </div>

        <!-- Apriori Candidate Flow & Pruning (~58% desktop width) -->
        <div class="col-lg-7">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
              <h3>Apriori Candidate Flow &amp; Pruning</h3>
              <span class="card-subtitle">Generated = Pruned + Evaluated, Frequent &le; Evaluated</span>
            </div>
            <div class="card-body p-2">
              <div id="levels-chart" class="chart-container"></div>
            </div>
          </div>
        </div>

        <!-- Co-occurrence Heatmap (~42% desktop width) -->
        <div class="col-lg-5">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
              <h3>Singleton Co-occurrence Heatmap</h3>
              <span class="card-subtitle">Top Singletons &amp; Pairwise Counts</span>
            </div>
            <div class="card-body p-2">
              <div id="heatmap-chart" class="chart-container"></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Interactive Research Demo Panel -->
    <section id="demo-panel" class="mb-3 d-none" aria-label="Interactive Research Demo">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
          <div class="d-flex align-items-center flex-wrap gap-2">
            <h2 class="d-inline-block mb-0">Interactive Research Demo</h2>
            <span class="demo-disclaimer badge bg-light text-dark border">
              Presentation enhancement — does not alter mining results or formal experiment evidence.
            </span>
          </div>
          <div class="btn-group" role="group" aria-label="Demo view navigation">
            <button type="button" class="btn btn-outline-primary btn-sm demo-nav-btn active"
                    data-demo-view="overview" aria-selected="true">Overview</button>
            <button type="button" class="btn btn-outline-primary btn-sm demo-nav-btn"
                    data-demo-view="apriori" aria-selected="false">Explain Apriori</button>
            <button type="button" class="btn btn-outline-primary btn-sm demo-nav-btn"
                    data-demo-view="rules" aria-selected="false">Explore Rules</button>
          </div>
        </div>
        <div class="card-body p-3">
          <!-- View 1: Overview -->
          <div id="demo-view-overview" class="demo-view-content">
            <div id="demo-overview-prompt" class="demo-empty-state">
              Run mining to enable interactive research visualizations.
            </div>
            <div id="demo-overview-content" class="d-none">
              <div class="mb-3 text-muted small">
                Interactive presentation layers synthesized from authoritative mining response.
                Navigate using the presentation cards below or the tabs above.
              </div>
              <div id="demo-overview-stats"></div>
            </div>
          </div>

          <!-- View 2: Explain Apriori -->
          <div id="demo-view-apriori" class="demo-view-content d-none">
            <!-- Toolbar & Controls -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2 pb-2 border-bottom">
              <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="btn-group btn-group-sm" role="group" aria-label="Apriori Level Navigation">
                  <button type="button" id="demo-apriori-prev" class="btn btn-outline-secondary" aria-label="Previous Level">
                    &larr; Previous
                  </button>
                  <button type="button" id="demo-apriori-next" class="btn btn-outline-secondary" aria-label="Next Level">
                    Next &rarr;
                  </button>
                </div>
                <div class="btn-group btn-group-sm" role="group" aria-label="Apriori Playback Controls">
                  <button type="button" id="demo-apriori-play" class="btn btn-outline-success" aria-label="Play Levels">
                    &#9654; Play Levels
                  </button>
                  <button type="button" id="demo-apriori-pause" class="btn btn-outline-secondary" aria-label="Pause Autoplay">
                    &#10074;&#10074; Pause
                  </button>
                  <button type="button" id="demo-apriori-restart" class="btn btn-outline-secondary" aria-label="Restart from Level 1">
                    &#8634; Restart
                  </button>
                </div>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span id="demo-apriori-level-label" class="badge bg-primary" aria-live="polite">Level k=1</span>
              </div>
            </div>

            <!-- Clickable Level Navigation Strip -->
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="text-muted small fw-semibold text-nowrap">Level Strip:</span>
              <div id="demo-apriori-levels-strip" class="d-flex flex-wrap align-items-center gap-1"></div>
            </div>

            <!-- Level metrics strip -->
            <div id="demo-apriori-metrics" class="demo-metrics-strip mb-2" aria-live="polite"></div>

            <!-- Apriori Sankey container -->
            <div id="demo-sankey-chart" class="demo-chart-container" style="min-height: 450px; height: 450px;"></div>
          </div>

          <!-- View 3: Explore Rules -->
          <div id="demo-view-rules" class="demo-view-content d-none">
            <!-- Secondary Navigation Switch inside Explore Rules -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 pb-2 border-bottom">
              <div class="btn-group" role="group" aria-label="Explore Rules View Switch">
                <button type="button" class="btn btn-outline-primary btn-sm demo-rules-subnav active"
                        data-rules-subview="network" aria-selected="true">
                  Network (Force-Directed)
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm demo-rules-subnav"
                        data-rules-subview="rulespace" aria-selected="false">
                  Rule Space (2D / 3D)
                </button>
              </div>
              <span class="text-muted small">Client-side analytical views of extracted association rules</span>
            </div>

            <!-- Subview 1: Association Rule Network -->
            <div id="demo-subview-network">
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <div class="d-flex flex-wrap align-items-center gap-2">
                  <span class="fw-semibold small text-muted">Rule Selection:</span>
                  <div class="btn-group btn-group-sm" role="group" aria-label="Rule Count Filter">
                    <button type="button" class="btn btn-outline-secondary demo-network-filter active" data-limit="10">Top 10</button>
                    <button type="button" class="btn btn-outline-secondary demo-network-filter" data-limit="20">Top 20</button>
                    <button type="button" class="btn btn-outline-secondary demo-network-filter" data-limit="all">All Returned</button>
                  </div>
                  <button type="button" id="demo-network-reset" class="btn btn-outline-secondary btn-sm" aria-label="Reset Network Layout">
                    Reset Layout
                  </button>
                </div>
              </div>

              <div id="demo-network-empty" class="demo-empty-state d-none">
                No association rules are available for this mining result.
                Try lowering min_confidence or selecting a richer dataset.
              </div>

              <div class="row g-3" id="demo-network-content-row">
                <!-- 9 cols: Network Visualization -->
                <div class="col-lg-9 col-xl-9">
                  <div id="demo-network-chart" class="demo-chart-container" style="min-height: 520px; height: 520px;"></div>
                </div>
                <!-- 3 cols: Side Detail Panel & Legend -->
                <div class="col-lg-3 col-xl-3">
                  <div class="card h-100 border bg-light">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                      <span class="fw-semibold small">Rule &amp; Node Details</span>
                      <span class="badge bg-secondary-subtle text-secondary small">Network Info</span>
                    </div>
                    <div class="card-body p-2" id="demo-network-side-panel">
                      <!-- Populated dynamically: rule detail or guidance -->
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Subview 2: Rule Space Explorer (2D / 3D) -->
            <div id="demo-subview-rulespace" class="d-none">
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <div class="d-flex flex-wrap align-items-center gap-2">
                  <span class="fw-semibold small text-muted">Dimension Mode:</span>
                  <div class="btn-group btn-group-sm" role="group" aria-label="Dimension Mode">
                    <button type="button" class="btn btn-outline-secondary demo-rulespace-mode active" data-mode="2d">2D Scatter</button>
                    <button type="button" class="btn btn-outline-secondary demo-rulespace-mode" data-mode="3d">3D Explore</button>
                  </div>
                  <div id="demo-3d-controls-group" class="d-none">
                    <div class="d-flex align-items-center gap-2">
                      <button type="button" id="demo-3d-reset-view" class="btn btn-outline-secondary btn-sm" aria-label="Reset 3D Camera View">
                        Reset View
                      </button>
                      <button type="button" id="demo-3d-auto-rotate" class="btn btn-outline-secondary btn-sm" aria-label="Toggle Camera Auto-Rotation">
                        Auto Rotate: OFF
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 3D Academic Notice -->
              <div id="demo-3d-disclaimer-row" class="mb-2 d-none">
                <div id="demo-3d-disclaimer" class="demo-scientific-notice">
                  <span class="badge bg-secondary me-1">Academic Notice</span>
                  3D/WebGL demo enhancement — not part of the formal RQ3 D3/Chart.js/ECharts Canvas benchmark.
                </div>
              </div>

              <div id="demo-rulespace-empty" class="demo-empty-state d-none">
                No association rules are available for this mining result.
                Try lowering min_confidence or selecting a richer dataset.
              </div>

              <div class="row g-3" id="demo-rulespace-content-row">
                <!-- 9 cols: Chart visualization (shared by 2D & 3D) -->
                <div class="col-lg-9 col-xl-9">
                  <div id="demo-rulespace-2d-chart" class="demo-chart-container" style="min-height: 520px; height: 520px;"></div>
                  <div id="demo-rulespace-3d-chart" class="demo-chart-container d-none" style="min-height: 520px; height: 520px;"></div>
                  <div id="demo-3d-fallback" class="demo-empty-state d-none text-danger">
                    3D visualization is unavailable in this environment.
                  </div>
                </div>
                <!-- 3 cols: Side Detail Panel -->
                <div class="col-lg-3 col-xl-3">
                  <div class="card h-100 border bg-light">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                      <span class="fw-semibold small">Selected Rule Detail</span>
                      <span class="badge bg-primary-subtle text-primary small">Inspection</span>
                    </div>
                    <div class="card-body p-2" id="demo-rulespace-side-panel">
                      <div id="demo-3d-rule-detail" class="demo-rule-detail-container" aria-live="polite"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </section>

    <!-- Run Metadata Panel (compact) -->
    <section id="run-meta-panel" class="mb-3 d-none" aria-label="Run Metadata">
      <div class="card">
        <div class="card-body py-2">
          <small id="run-meta-text" class="text-muted font-monospace"></small>
        </div>
      </div>
    </section>

  </main>

  <!-- Vendored Local Offline Assets -->
  <script src="assets/vendor/jquery/jquery.min.js"></script>
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/echarts/echarts.min.js"></script>
  <script src="assets/vendor/echarts-gl/echarts-gl.min.js"></script>
  <script src="assets/js/demo-visualizations.js"></script>
  <script src="assets/js/app.js"></script>
</body>
</html>
