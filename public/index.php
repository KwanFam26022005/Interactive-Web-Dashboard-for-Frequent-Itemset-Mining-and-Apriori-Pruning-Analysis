<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FIM Dashboard — Frequent Itemset Mining &amp; Apriori Pruning Analysis</title>
  <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <!-- Header / Project Identity -->
  <header id="app-header" class="sticky-top">
    <nav class="navbar navbar-expand-lg navbar-light py-2">
      <div class="container-fluid px-4">
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

  <main class="container-fluid px-4 py-3">

    <!-- Dataset Panel -->
    <section id="dataset-panel" class="mb-3" aria-label="Dataset Management">
      <div class="row g-3">
        <!-- Dataset Selector & Metadata -->
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h2>Select Active Dataset</h2>
              <span class="card-subtitle">Repository Store</span>
            </div>
            <div class="card-body">
              <div class="mb-3">
                <label for="dataset-select" class="form-label">Available Datasets</label>
                <select id="dataset-select" class="form-select">
                  <option value="">Loading datasets…</option>
                </select>
              </div>
              <div id="dataset-meta" class="border rounded p-2 bg-light">
                <span class="text-muted small">Select a dataset to view its profile metadata.</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Import Controls -->
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h2>Import New Dataset</h2>
              <span class="card-subtitle">All-or-nothing Ingestion</span>
            </div>
            <div class="card-body">
              <form id="upload-form" enctype="multipart/form-data">
                <div class="row g-2 mb-2">
                  <div class="col-sm-5">
                    <label for="upload-format" class="form-label">Format Profile</label>
                    <select id="upload-format" name="format" class="form-select" required>
                      <option value="basket_csv">basket_csv (.csv)</option>
                      <option value="basket_txt">basket_txt (.txt, .dat)</option>
                      <option value="mushroom">mushroom (.csv, .data)</option>
                    </select>
                  </div>
                  <div class="col-sm-7">
                    <label for="upload-name" class="form-label">
                      Dataset Name <span class="text-muted fw-normal">(optional)</span>
                    </label>
                    <input type="text" id="upload-name" name="name" class="form-control"
                           maxlength="120" placeholder="Defaults to source filename">
                  </div>
                </div>
                <div class="mb-3">
                  <label for="upload-file" class="form-label">Source File (max 10 MiB)</label>
                  <input type="file" id="upload-file" name="file" class="form-control"
                         accept=".csv,.txt,.dat,.data" required>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                  <button type="submit" id="upload-submit" class="btn btn-outline-primary btn-sm px-3">
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
    </section>

    <!-- Mining Controls -->
    <section id="mining-panel" class="mb-3" aria-label="Mining Parameters">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h2>Mining Parameters &amp; Execution</h2>
          <span class="card-subtitle">Server-Side Apriori Engine</span>
        </div>
        <div class="card-body">
          <div class="row g-3 align-items-end">
            <div class="col-6 col-md-3">
              <label for="support-input" class="form-label">
                Min Support <span class="text-muted">(0, 1]</span>
              </label>
              <input type="number" id="support-input" class="form-control font-monospace"
                     min="0.000001" max="1" step="0.01" value="0.5">
            </div>
            <div class="col-6 col-md-3">
              <label for="confidence-input" class="form-label">
                Min Confidence <span class="text-muted">[0, 1]</span>
              </label>
              <input type="number" id="confidence-input" class="form-control font-monospace"
                     min="0" max="1" step="0.01" value="0.75">
            </div>
            <div class="col-6 col-md-3">
              <label for="topn-input" class="form-label">
                Top N Views <span class="text-muted">[1, 100]</span>
              </label>
              <input type="number" id="topn-input" class="form-control font-monospace"
                     min="1" max="100" step="1" value="20">
            </div>
            <div class="col-6 col-md-3">
              <button type="button" id="run-mining-btn" class="btn btn-primary w-100 py-2" disabled>
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
      <div class="row g-3">
        <div class="col-6 col-md-4 col-xl">
          <div class="card text-center h-100">
            <div class="card-body py-2">
              <div class="kpi-label">Frequent Itemsets</div>
              <div id="kpi-frequent-itemsets" class="kpi-value font-monospace">—</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
          <div class="card text-center h-100">
            <div class="card-body py-2">
              <div class="kpi-label">Association Rules</div>
              <div id="kpi-rules-count" class="kpi-value font-monospace">—</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
          <div class="card text-center h-100">
            <div class="card-body py-2">
              <div class="kpi-label">Apriori Runtime</div>
              <div id="kpi-runtime" class="kpi-value font-monospace">—</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
          <div class="card text-center h-100">
            <div class="card-body py-2">
              <div class="kpi-label">Rule Gen Runtime</div>
              <div id="kpi-rule-runtime" class="kpi-value font-monospace">—</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
          <div class="card text-center h-100">
            <div class="card-body py-2">
              <div class="kpi-label">Candidates Gen</div>
              <div id="kpi-candidates-generated" class="kpi-value font-monospace">—</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
          <div class="card text-center h-100">
            <div class="card-body py-2">
              <div class="kpi-label">Pruning Ratio</div>
              <div id="kpi-pruning-ratio" class="kpi-value font-monospace">—</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
          <div class="card text-center h-100">
            <div class="card-body py-2">
              <div class="kpi-label">Max Level (k)</div>
              <div id="kpi-max-k" class="kpi-value font-monospace">—</div>
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
        <!-- Frequent Itemset Horizontal Bar -->
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h3>Top Frequent Itemsets</h3>
              <span class="card-subtitle">Support Count Ranking</span>
            </div>
            <div class="card-body p-2">
              <div id="itemset-chart" class="chart-container"></div>
            </div>
          </div>
        </div>

        <!-- Association Rules Scatter -->
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h3>Association Rules</h3>
              <span class="card-subtitle">Support × Confidence (Bubble Size &amp; Color = Lift)</span>
            </div>
            <div class="card-body p-2">
              <div id="rule-chart" class="chart-container"></div>
            </div>
          </div>
        </div>

        <!-- Co-occurrence Heatmap -->
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h3>Singleton Co-occurrence Heatmap</h3>
              <span class="card-subtitle">Top Singletons &amp; Pairwise Counts</span>
            </div>
            <div class="card-body p-2">
              <div id="heatmap-chart" class="chart-container"></div>
            </div>
          </div>
        </div>

        <!-- Apriori Candidate Flow & Pruning -->
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h3>Apriori Candidate Flow &amp; Pruning</h3>
              <span class="card-subtitle">Generated = Pruned + Evaluated, Frequent &le; Evaluated</span>
            </div>
            <div class="card-body p-2">
              <div id="levels-chart" class="chart-container"></div>
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
  <script src="assets/js/app.js"></script>
</body>
</html>
