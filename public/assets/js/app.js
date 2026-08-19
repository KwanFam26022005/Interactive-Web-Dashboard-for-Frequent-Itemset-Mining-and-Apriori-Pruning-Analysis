/**
 * FIM Dashboard — Client Application
 * Frequent Itemset Mining & Apriori Pruning Analysis
 *
 * Implements AJAX orchestration and ECharts rendering.
 * Complies with strict XSS-safe DOM policy and richText chart tooltips.
 */
(function ($) {
  'use strict';

  // -------------------------------------------------------------------------
  // 1. Client State Model
  // -------------------------------------------------------------------------
  var state = {
    datasets: [],            // Array of dataset summary records from API
    selectedDatasetId: null, // Positive integer dataset ID or null
    lastMiningResult: null,  // Authoritative mining response payload or null
    loadingDatasets: false,  // In-flight flag for dataset listing
    importing: false,        // In-flight flag for dataset import
    mining: false            // In-flight flag for mining run
  };

  // ECharts instance registry
  var chartInstances = {
    itemset: null,
    rule: null,
    heatmap: null,
    levels: null,
    initialized: false
  };

  // -------------------------------------------------------------------------
  // 2. Application Bootstrap
  // -------------------------------------------------------------------------
  $(function () {
    initApp();
  });

  function initApp() {
    bindEvents();
    loadDatasets();
    setupResizeHandler();
  }

  function bindEvents() {
    $('#dataset-select').on('change', onDatasetSelectChange);
    $('#upload-form').on('submit', onUploadSubmit);
    $('#run-mining-btn').on('click', onRunMiningClick);
  }

  function setupResizeHandler() {
    var resizeTimer = null;
    $(window).on('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        resizeAllCharts();
      }, 100);
    });
  }

  // -------------------------------------------------------------------------
  // 3. Dataset API Integration
  // -------------------------------------------------------------------------

  /**
   * Loads the list of available datasets from GET /api/datasets.php.
   * Returns a Promise/jqXHR for async sequencing.
   */
  function loadDatasets(selectIdToRestore) {
    state.loadingDatasets = true;
    updateDatasetControlsState();

    return $.ajax({
      url: 'api/datasets.php',
      type: 'GET',
      dataType: 'json'
    }).done(function (response) {
      if (response && Array.isArray(response.datasets)) {
        state.datasets = response.datasets;
        populateDatasetSelect(selectIdToRestore);
      } else {
        showStatus('error', 'Received unexpected dataset list format from server.');
      }
    }).fail(function (jqXHR) {
      var errorMsg = normalizeApiError(jqXHR, 'Failed to load datasets.');
      showStatus('error', errorMsg);
      state.datasets = [];
      populateDatasetSelect(null);
    }).always(function () {
      state.loadingDatasets = false;
      updateDatasetControlsState();
    });
  }

  /**
   * Populates the #dataset-select dropdown.
   */
  function populateDatasetSelect(preferredId) {
    var $select = $('#dataset-select');
    $select.empty();

    if (state.datasets.length === 0) {
      $select.append($('<option>').val('').text('-- No datasets imported --'));
      state.selectedDatasetId = null;
      renderSelectedDataset();
      return;
    }

    var selectedExists = false;
    var targetId = preferredId !== undefined && preferredId !== null ? Number(preferredId) : null;

    $.each(state.datasets, function (index, ds) {
      var dsId = Number(ds.id);
      var label = ds.name + ' (' + ds.format + ', ' + Number(ds.transaction_count).toLocaleString() + ' txns)';
      var $opt = $('<option>').val(String(dsId)).text(label);

      if (targetId !== null && dsId === targetId) {
        $opt.prop('selected', true);
        selectedExists = true;
      }
      $select.append($opt);
    });

    if (!selectedExists) {
      // Default to first dataset in list (API returns newest-first)
      var firstId = Number(state.datasets[0].id);
      $select.val(String(firstId));
      state.selectedDatasetId = firstId;
    } else {
      state.selectedDatasetId = targetId;
    }

    renderSelectedDataset();
  }

  /**
   * Handles dataset select change event.
   */
  function onDatasetSelectChange() {
    var rawVal = $('#dataset-select').val();
    var parsedId = rawVal ? Number(rawVal) : null;

    if (parsedId !== null && Number.isInteger(parsedId) && parsedId > 0) {
      state.selectedDatasetId = parsedId;
    } else {
      state.selectedDatasetId = null;
    }

    renderSelectedDataset();
    clearMiningResult();
  }

  /**
   * Renders metadata table for the currently selected dataset using safe DOM text assignment.
   */
  function renderSelectedDataset() {
    var $meta = $('#dataset-meta');
    $meta.empty();

    var current = getSelectedDatasetRecord();
    if (!current) {
      $meta.append($('<span>').addClass('text-muted small').text('Select a dataset to view its profile metadata.'));
      $('#run-mining-btn').prop('disabled', true);
      return;
    }

    $('#run-mining-btn').prop('disabled', state.mining);

    var $table = $('<table>').addClass('table table-sm table-borderless align-middle mb-0');
    var $tbody = $('<tbody>');

    var rows = [
      ['Dataset ID', String(current.id)],
      ['Name', String(current.name)],
      ['Format Profile', String(current.format)],
      ['Source Filename', String(current.source_filename)],
      ['Transactions', Number(current.transaction_count).toLocaleString()],
      ['Unique Items', Number(current.unique_item_count).toLocaleString()],
      ['Byte Size', Number(current.byte_size).toLocaleString() + ' bytes'],
      ['SHA-256', String(current.sha256)],
      ['Created At (UTC)', String(current.created_at)]
    ];

    $.each(rows, function (i, row) {
      var $tr = $('<tr>');
      var $th = $('<th>').addClass('text-muted py-1 small').text(row[0]);
      var $td = $('<td>').addClass('py-1 small').addClass(row[0] === 'SHA-256' ? 'font-monospace' : '');
      $td.text(row[1]);
      $tr.append($th).append($td);
      $tbody.append($tr);
    });

    $table.append($tbody);
    $meta.append($table);
  }

  function getSelectedDatasetRecord() {
    if (state.selectedDatasetId === null) {
      return null;
    }
    for (var i = 0; i < state.datasets.length; i++) {
      if (Number(state.datasets[i].id) === state.selectedDatasetId) {
        return state.datasets[i];
      }
    }
    return null;
  }

  /**
   * Handles dataset multipart file upload submission.
   */
  function onUploadSubmit(e) {
    e.preventDefault();

    if (state.importing) {
      return;
    }

    var fileInput = document.getElementById('upload-file');
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
      showUploadWarnings([{ code: 'MISSING_FILE', line: 0, message: 'Please select a file to upload.' }]);
      return;
    }

    var formElem = document.getElementById('upload-form');
    var formData = new FormData(formElem);

    var rawName = $('#upload-name').val();
    var trimmedName = typeof rawName === 'string' ? rawName.trim() : '';
    if (trimmedName === '') {
      formData.delete('name');
    } else {
      formData.set('name', trimmedName);
    }

    state.importing = true;
    updateDatasetControlsState();
    clearUploadWarnings();
    showStatus('info', 'Uploading and validating dataset...');

    $.ajax({
      url: 'api/datasets.php',
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json'
    }).done(function (response) {
      clearStatus();
      if (response && response.dataset && response.dataset.id) {
        var newDatasetId = Number(response.dataset.id);
        // Display warnings if any
        if (response.warnings && response.warnings.length > 0) {
          showUploadWarnings(response.warnings, response.total_warnings);
        } else {
          showStatus('success', 'Dataset "' + response.dataset.name + '" imported successfully with 0 warnings.');
        }

        // Reset file input
        $('#upload-file').val('');
        $('#upload-name').val('');

        // Refresh list asynchronously, then select newly created dataset
        loadDatasets(newDatasetId);
      } else {
        showStatus('error', 'Import succeeded but server response was malformed.');
      }
    }).fail(function (jqXHR) {
      clearStatus();
      var errorEnvelope = parseErrorEnvelope(jqXHR);
      var mainMsg = errorEnvelope.message || 'Dataset import failed.';

      if (errorEnvelope.details && Array.isArray(errorEnvelope.details.issues)) {
        showUploadWarnings(errorEnvelope.details.issues, errorEnvelope.details.total_issues);
      } else {
        showStatus('error', mainMsg);
      }
    }).always(function () {
      state.importing = false;
      updateDatasetControlsState();
    });
  }

  function showUploadWarnings(warnings, totalCount) {
    var $box = $('#upload-warnings');
    $box.empty();

    if (!warnings || warnings.length === 0) {
      return;
    }

    var isError = warnings[0] && warnings[0].code && warnings[0].code.indexOf('WARNING') === -1 && warnings[0].code !== 'DUPLICATE_ITEMS_IN_RECORD' && warnings[0].code !== 'BLANK_LINE_IGNORED';
    var alertClass = isError ? 'alert-danger' : 'alert-warning';

    var $alert = $('<div>').addClass('alert ' + alertClass + ' py-2 px-3 mb-0');
    var headerText = (isError ? 'Validation Issues' : 'Import Warnings') + ' (' + (totalCount || warnings.length) + ' total):';
    $alert.append($('<strong>').text(headerText));

    var $ul = $('<ul>').addClass('mb-0 mt-1 ps-3 small');
    $.each(warnings.slice(0, 5), function (i, w) {
      var text = (w.line > 0 ? 'Line ' + w.line + ': ' : '') + (w.message || w.code);
      $ul.append($('<li>').text(text));
    });

    if (warnings.length > 5) {
      $ul.append($('<li>').addClass('text-muted font-italic').text('... and ' + (warnings.length - 5) + ' more.'));
    }

    $alert.append($ul);
    $box.append($alert);
  }

  function clearUploadWarnings() {
    $('#upload-warnings').empty();
  }

  function updateDatasetControlsState() {
    $('#upload-submit').prop('disabled', state.importing || state.loadingDatasets);
    $('#dataset-select').prop('disabled', state.loadingDatasets || state.mining);
  }

  // -------------------------------------------------------------------------
  // 4. Mining API Integration
  // -------------------------------------------------------------------------

  /**
   * Explicit Run Mining action handler.
   */
  function onRunMiningClick() {
    if (state.mining) {
      return;
    }

    if (state.selectedDatasetId === null || !Number.isInteger(state.selectedDatasetId) || state.selectedDatasetId <= 0) {
      showStatus('error', 'Please select a valid dataset before running mining.');
      return;
    }

    // Read and validate numeric parameter types (Mandatory Correction 4)
    var rawSupport = $('#support-input').val();
    var rawConfidence = $('#confidence-input').val();
    var rawTopN = $('#topn-input').val();

    var supportNum = Number(rawSupport);
    var confidenceNum = Number(rawConfidence);
    var topNNum = Number(rawTopN);

    // Client-side convenience range validation
    if (!Number.isFinite(supportNum) || supportNum <= 0 || supportNum > 1) {
      showStatus('error', 'Min Support must be a valid number in the range (0, 1].');
      return;
    }

    if (!Number.isFinite(confidenceNum) || confidenceNum < 0 || confidenceNum > 1) {
      showStatus('error', 'Min Confidence must be a valid number in the range [0, 1].');
      return;
    }

    if (!Number.isInteger(topNNum) || topNNum < 1 || topNNum > 100) {
      showStatus('error', 'Top N must be an integer between 1 and 100.');
      return;
    }

    var payload = {
      dataset_id: state.selectedDatasetId,
      min_support: supportNum,
      min_confidence: confidenceNum,
      top_n: topNNum
    };

    setMiningBusy(true);
    clearStatus();
    showStatus('info', 'Running synchronous Apriori mining on server...');

    $.ajax({
      url: 'api/mining.php',
      type: 'POST',
      data: JSON.stringify(payload),
      contentType: 'application/json; charset=UTF-8',
      dataType: 'json'
    }).done(function (response) {
      clearStatus();
      if (response && response.summary && response.dataset) {
        state.lastMiningResult = response;
        renderMiningResult(response);
        showStatus('success', 'Mining completed successfully (Run ID: ' + response.run_id + ').');
      } else {
        showStatus('error', 'Received unexpected mining response shape from server.');
      }
    }).fail(function (jqXHR) {
      var errorMsg = normalizeApiError(jqXHR, 'Mining computation failed.');
      showStatus('error', errorMsg);
      clearMiningResult();
    }).always(function () {
      setMiningBusy(false);
    });
  }

  function setMiningBusy(busy) {
    state.mining = busy;
    $('#run-mining-btn').prop('disabled', busy || state.selectedDatasetId === null);
    $('#support-input, #confidence-input, #topn-input').prop('disabled', busy);
    $('#dataset-select').prop('disabled', busy || state.loadingDatasets);
  }

  // -------------------------------------------------------------------------
  // 5. Mining Result Rendering & KPI Mapping
  // -------------------------------------------------------------------------

  function renderMiningResult(data) {
    // 1. Reveal panels
    $('#kpi-panel').removeClass('d-none');
    $('#viz-panel').removeClass('d-none');
    $('#run-meta-panel').removeClass('d-none');

    // 2. Populate KPI Cards using exact authoritative summary fields (Mandatory Correction 3 & Blueprint Section O)
    var sum = data.summary;
    $('#kpi-frequent-itemsets').text(Number(sum.frequent_itemsets).toLocaleString());
    $('#kpi-rules-count').text(Number(sum.rules_count).toLocaleString());
    $('#kpi-runtime').text(Number(sum.runtime_ms).toFixed(3) + ' ms');
    $('#kpi-rule-runtime').text(Number(sum.rule_generation_runtime_ms).toFixed(3) + ' ms');
    $('#kpi-candidates-generated').text(Number(sum.candidates_generated).toLocaleString());

    if (sum.pruning_ratio === null || sum.pruning_ratio === undefined) {
      $('#kpi-pruning-ratio').text('N/A');
    } else {
      $('#kpi-pruning-ratio').text((Number(sum.pruning_ratio) * 100).toFixed(2) + '%');
    }

    $('#kpi-max-k').text(String(sum.max_k));

    // 3. Result Limits & Truncation notice
    renderResultLimits(data);

    // 4. Run Metadata
    renderRunMetadata(data);

    // 5. Initialize ECharts if not yet initialized, then render all 4 charts
    ensureChartsInitialized();
    renderItemsetsChart(data.itemsets || []);
    renderRulesChart(data.rules || []);
    renderHeatmapChart(data.heatmap || { items: [], values: [] });
    renderLevelsChart(data.levels || []);

    // 6. Ensure charts have proper dimensions after revealing panel
    resizeAllCharts();
  }

  function clearMiningResult() {
    state.lastMiningResult = null;

    $('#kpi-panel').addClass('d-none');
    $('#viz-panel').addClass('d-none');
    $('#run-meta-panel').addClass('d-none');
    $('#result-limits-info').addClass('d-none').empty();

    $('#kpi-frequent-itemsets, #kpi-rules-count, #kpi-runtime, #kpi-rule-runtime, #kpi-candidates-generated, #kpi-pruning-ratio, #kpi-max-k').text('—');
    $('#run-meta-text').empty();

    if (chartInstances.initialized) {
      if (chartInstances.itemset) chartInstances.itemset.clear();
      if (chartInstances.rule) chartInstances.rule.clear();
      if (chartInstances.heatmap) chartInstances.heatmap.clear();
      if (chartInstances.levels) chartInstances.levels.clear();
    }
  }

  function renderResultLimits(data) {
    var limits = data.result_limits;
    var $info = $('#result-limits-info');
    $info.empty();

    if (!limits) {
      $info.addClass('d-none');
      return;
    }

    var notices = [];
    if (limits.itemsets_truncated) {
      notices.push('Displaying top ' + limits.itemsets_returned + ' of ' + data.summary.frequent_itemsets + ' frequent itemsets.');
    }
    if (limits.rules_truncated) {
      notices.push('Displaying top ' + limits.rules_returned + ' of ' + data.summary.rules_count + ' association rules.');
    }
    if (limits.heatmap_items_truncated) {
      notices.push('Heatmap limited to top ' + limits.heatmap_items_returned + ' of ' + data.dataset.unique_item_count + ' singletons.');
    }

    if (notices.length > 0) {
      $info.removeClass('d-none');
      var $p = $('<div>').addClass('fw-bold mb-1').text('Top-N Display Truncation Active:');
      var $ul = $('<ul>').addClass('mb-0 ps-3');
      $.each(notices, function (i, msg) {
        $ul.append($('<li>').text(msg));
      });
      $info.append($p).append($ul);
    } else {
      $info.addClass('d-none');
    }
  }

  function renderRunMetadata(data) {
    var text = 'Run ID: ' + data.run_id +
      ' | Dataset: ' + data.dataset.name + ' (ID ' + data.dataset.id + ')' +
      ' | Transactions: ' + Number(data.dataset.transaction_count).toLocaleString() +
      ' | Parameters: min_support=' + data.parameters.min_support +
      ', min_confidence=' + data.parameters.min_confidence +
      ', top_n=' + data.parameters.top_n;

    $('#run-meta-text').text(text);
  }

  // -------------------------------------------------------------------------
  // 6. ECharts Lifecycle & Visualizations
  // -------------------------------------------------------------------------

  /**
   * Initializes ECharts instances lazily upon first display (Mandatory Correction 7).
   */
  function ensureChartsInitialized() {
    if (chartInstances.initialized) {
      return;
    }

    var itemsetElem = document.getElementById('itemset-chart');
    var ruleElem = document.getElementById('rule-chart');
    var heatmapElem = document.getElementById('heatmap-chart');
    var levelsElem = document.getElementById('levels-chart');

    if (itemsetElem && ruleElem && heatmapElem && levelsElem) {
      chartInstances.itemset = echarts.init(itemsetElem);
      chartInstances.rule = echarts.init(ruleElem);
      chartInstances.heatmap = echarts.init(heatmapElem);
      chartInstances.levels = echarts.init(levelsElem);
      chartInstances.initialized = true;
    }
  }

  function resizeAllCharts() {
    if (!chartInstances.initialized) {
      return;
    }
    if (chartInstances.itemset) chartInstances.itemset.resize();
    if (chartInstances.rule) chartInstances.rule.resize();
    if (chartInstances.heatmap) chartInstances.heatmap.resize();
    if (chartInstances.levels) chartInstances.levels.resize();
  }

  /**
   * Chart 1: Top Frequent Itemsets (Horizontal Bar)
   * Tooltip: richText plain-text mode (Mandatory Correction 2).
   */
  function renderItemsetsChart(itemsets) {
    if (!chartInstances.itemset) return;

    if (itemsets.length === 0) {
      chartInstances.itemset.setOption({
        title: {
          text: 'No frequent itemsets found meeting min_support',
          left: 'center',
          top: 'middle',
          textStyle: { color: '#94a3b8', fontSize: 13 }
        },
        xAxis: { show: false },
        yAxis: { show: false },
        series: []
      }, true);
      return;
    }

    // Do NOT re-sort itemsets; API order is authoritative.
    var yAxisLabels = [];
    var seriesData = [];

    // For horizontal bar with inverse y-axis: highest rank at top
    $.each(itemsets, function (idx, itemset) {
      var itemLabel = formatItemset(itemset.items);
      var truncatedLabel = itemLabel.length > 28 ? itemLabel.substring(0, 25) + '...' : itemLabel;
      yAxisLabels.push(truncatedLabel);

      seriesData.push({
        value: itemset.support_count,
        rawItemset: itemset
      });
    });

    var option = {
      tooltip: {
        trigger: 'axis',
        renderMode: 'richText', // Mandatory Correction 2
        axisPointer: { type: 'shadow' },
        formatter: function (params) {
          if (!params || !params[0]) return '';
          var raw = params[0].data.rawItemset;
          if (!raw) return '';
          return 'Items: ' + formatItemset(raw.items) + '\n' +
            'Size (k): ' + raw.k + '\n' +
            'Support Count: ' + raw.support_count.toLocaleString() + '\n' +
            'Support: ' + (Number(raw.support) * 100).toFixed(4) + '%';
        }
      },
      grid: {
        left: '3%',
        right: '4%',
        bottom: '3%',
        top: '4%',
        containLabel: true
      },
      xAxis: {
        type: 'value',
        name: 'Support Count',
        axisLine: { lineStyle: { color: '#cbd5e1' } },
        splitLine: { lineStyle: { color: '#f1f5f9' } }
      },
      yAxis: {
        type: 'category',
        data: yAxisLabels,
        inverse: true, // Rank 1 at top
        axisLabel: {
          color: '#334155',
          fontSize: 11
        },
        axisLine: { lineStyle: { color: '#cbd5e1' } }
      },
      series: [
        {
          name: 'Support Count',
          type: 'bar',
          data: seriesData,
          itemStyle: {
            color: '#2563eb',
            borderRadius: [0, 4, 4, 0]
          }
        }
      ]
    };

    chartInstances.itemset.setOption(option, true);
  }

  /**
   * Chart 2: Association Rules (Scatter Plot)
   * x: support, y: confidence, size/color: lift
   */
  function renderRulesChart(rules) {
    if (!chartInstances.rule) return;

    if (rules.length === 0) {
      chartInstances.rule.setOption({
        title: {
          text: 'No association rules found meeting min_confidence',
          left: 'center',
          top: 'middle',
          textStyle: { color: '#94a3b8', fontSize: 13 }
        },
        xAxis: { show: false },
        yAxis: { show: false },
        series: []
      }, true);
      return;
    }

    var scatterData = [];
    var maxLift = 1;

    $.each(rules, function (idx, rule) {
      var liftVal = Number(rule.lift);
      if (liftVal > maxLift) {
        maxLift = liftVal;
      }
      scatterData.push({
        value: [Number(rule.support), Number(rule.confidence), liftVal],
        rawRule: rule
      });
    });

    var option = {
      tooltip: {
        trigger: 'item',
        renderMode: 'richText', // Mandatory Correction 2
        formatter: function (params) {
          var raw = params.data.rawRule;
          if (!raw) return '';
          return 'Rule: ' + formatItemset(raw.antecedent) + ' => ' + formatItemset(raw.consequent) + '\n' +
            'Support: ' + (Number(raw.support) * 100).toFixed(4) + '% (' + raw.support_count.toLocaleString() + ' txns)\n' +
            'Confidence: ' + (Number(raw.confidence) * 100).toFixed(2) + '%\n' +
            'Lift: ' + Number(raw.lift).toFixed(4);
        }
      },
      grid: {
        left: '3%',
        right: '12%',
        bottom: '3%',
        top: '6%',
        containLabel: true
      },
      xAxis: {
        type: 'value',
        name: 'Support',
        nameLocation: 'middle',
        nameGap: 24,
        min: 0,
        max: 1,
        axisLine: { lineStyle: { color: '#cbd5e1' } },
        splitLine: { lineStyle: { color: '#f1f5f9' } }
      },
      yAxis: {
        type: 'value',
        name: 'Confidence',
        min: 0,
        max: 1,
        axisLine: { lineStyle: { color: '#cbd5e1' } },
        splitLine: { lineStyle: { color: '#f1f5f9' } }
      },
      visualMap: {
        dimension: 2,
        min: 0.5,
        max: Math.max(2, Math.ceil(maxLift)),
        calculable: true,
        orient: 'vertical',
        right: '0%',
        top: 'center',
        text: ['High', 'Low'],
        textStyle: { fontSize: 10, color: '#64748b' },
        inRange: {
          color: ['#38bdf8', '#fbbf24', '#ef4444']
        }
      },
      series: [
        {
          name: 'Association Rules',
          type: 'scatter',
          data: scatterData,
          symbolSize: function (val) {
            // Presentation-only bubble size transform
            var lift = val[2];
            var clamped = Math.max(0.1, Math.min(lift, 20));
            return Math.sqrt(clamped) * 12;
          }
        }
      ]
    };

    chartInstances.rule.setOption(option, true);
  }

  /**
   * Chart 3: Co-occurrence Heatmap
   * Uses heatmap.items & heatmap.values directly.
   */
  function renderHeatmapChart(heatmap) {
    if (!chartInstances.heatmap) return;

    var items = heatmap.items || [];
    var values = heatmap.values || [];

    if (items.length === 0) {
      chartInstances.heatmap.setOption({
        title: {
          text: 'No singleton items available for heatmap',
          left: 'center',
          top: 'middle',
          textStyle: { color: '#94a3b8', fontSize: 13 }
        },
        xAxis: { show: false },
        yAxis: { show: false },
        series: []
      }, true);
      return;
    }

    var heatData = [];
    var maxCount = 0;

    for (var i = 0; i < items.length; i++) {
      for (var j = 0; j < items.length; j++) {
        var count = values[i] && values[i][j] !== undefined ? values[i][j] : 0;
        if (count > maxCount) {
          maxCount = count;
        }
        // ECharts heatmap coordinate: [xIndex, yIndex, value]
        heatData.push([j, i, count]);
      }
    }

    var option = {
      tooltip: {
        position: 'top',
        renderMode: 'richText', // Mandatory Correction 2
        formatter: function (params) {
          var xItem = items[params.value[0]];
          var yItem = items[params.value[1]];
          var count = params.value[2];
          return 'Item A: ' + xItem + '\n' +
            'Item B: ' + yItem + '\n' +
            'Co-occurrence Count: ' + Number(count).toLocaleString();
        }
      },
      grid: {
        left: '3%',
        right: '12%',
        bottom: '8%',
        top: '4%',
        containLabel: true
      },
      xAxis: {
        type: 'category',
        data: items,
        splitArea: { show: true },
        axisLabel: {
          interval: 0,
          rotate: items.length > 8 ? 35 : 0,
          fontSize: 10,
          color: '#334155'
        }
      },
      yAxis: {
        type: 'category',
        data: items,
        splitArea: { show: true },
        axisLabel: {
          interval: 0,
          fontSize: 10,
          color: '#334155'
        }
      },
      visualMap: {
        min: 0,
        max: Math.max(1, maxCount),
        calculable: true,
        orient: 'vertical',
        right: '0%',
        top: 'center',
        textStyle: { fontSize: 10, color: '#64748b' },
        inRange: {
          color: ['#f8fafc', '#93c5fd', '#1d4ed8']
        }
      },
      series: [
        {
          name: 'Co-occurrence',
          type: 'heatmap',
          data: heatData,
          label: {
            show: items.length <= 10,
            fontSize: 10,
            color: '#0f172a'
          },
          emphasis: {
            itemStyle: {
              shadowBlur: 10,
              shadowColor: 'rgba(0, 0, 0, 0.5)'
            }
          }
        }
      ]
    };

    chartInstances.heatmap.setOption(option, true);
  }

  /**
   * Chart 4: Apriori Candidate Flow & Pruning
   * Uses levels array: generated, pruned, evaluated, frequent.
   */
  function renderLevelsChart(levels) {
    if (!chartInstances.levels) return;

    if (levels.length === 0) {
      chartInstances.levels.setOption({
        title: {
          text: 'No levels generated in Apriori execution',
          left: 'center',
          top: 'middle',
          textStyle: { color: '#94a3b8', fontSize: 13 }
        },
        xAxis: { show: false },
        yAxis: { show: false },
        series: []
      }, true);
      return;
    }

    var kCategories = [];
    var generatedSeries = [];
    var prunedSeries = [];
    var evaluatedSeries = [];
    var frequentSeries = [];

    $.each(levels, function (idx, lvl) {
      kCategories.push('k=' + lvl.k);
      generatedSeries.push(lvl.generated);
      prunedSeries.push(lvl.pruned);
      evaluatedSeries.push(lvl.evaluated);
      frequentSeries.push(lvl.frequent);
    });

    var option = {
      tooltip: {
        trigger: 'axis',
        renderMode: 'richText', // Mandatory Correction 2
        axisPointer: { type: 'shadow' },
        formatter: function (params) {
          if (!params || !params[0]) return '';
          var lvlIdx = params[0].dataIndex;
          var lvl = levels[lvlIdx];
          if (!lvl) return '';
          var ratioText = lvl.pruning_ratio === null ? 'N/A' : (Number(lvl.pruning_ratio) * 100).toFixed(2) + '%';
          return 'Level k=' + lvl.k + ' (' + lvl.source + ')\n' +
            'Generated: ' + Number(lvl.generated).toLocaleString() + '\n' +
            'Pruned (Apriori): ' + Number(lvl.pruned).toLocaleString() + '\n' +
            'Evaluated: ' + Number(lvl.evaluated).toLocaleString() + '\n' +
            'Frequent: ' + Number(lvl.frequent).toLocaleString() + '\n' +
            'Pruning Ratio: ' + ratioText + '\n' +
            'Note: Generated = Pruned + Evaluated';
        }
      },
      legend: {
        data: ['Generated', 'Pruned', 'Evaluated', 'Frequent'],
        top: '2%',
        textStyle: { fontSize: 11, color: '#475569' }
      },
      grid: {
        left: '3%',
        right: '4%',
        bottom: '3%',
        top: '14%',
        containLabel: true
      },
      xAxis: {
        type: 'category',
        data: kCategories,
        axisLine: { lineStyle: { color: '#cbd5e1' } },
        axisLabel: { color: '#334155', fontWeight: 600 }
      },
      yAxis: {
        type: 'value',
        name: 'Candidate Count',
        axisLine: { lineStyle: { color: '#cbd5e1' } },
        splitLine: { lineStyle: { color: '#f1f5f9' } }
      },
      series: [
        {
          name: 'Generated',
          type: 'bar',
          data: generatedSeries,
          itemStyle: { color: '#94a3b8', borderRadius: [3, 3, 0, 0] }
        },
        {
          name: 'Pruned',
          type: 'bar',
          data: prunedSeries,
          itemStyle: { color: '#ef4444', borderRadius: [3, 3, 0, 0] }
        },
        {
          name: 'Evaluated',
          type: 'bar',
          data: evaluatedSeries,
          itemStyle: { color: '#3b82f6', borderRadius: [3, 3, 0, 0] }
        },
        {
          name: 'Frequent',
          type: 'bar',
          data: frequentSeries,
          itemStyle: { color: '#10b981', borderRadius: [3, 3, 0, 0] }
        }
      ]
    };

    chartInstances.levels.setOption(option, true);
  }

  // -------------------------------------------------------------------------
  // 7. Text & Presentation Formatting Helpers
  // -------------------------------------------------------------------------

  function formatItemset(items) {
    if (!items || !Array.isArray(items)) return '{}';
    return '{' + items.join(', ') + '}';
  }

  // -------------------------------------------------------------------------
  // 8. Notification & Error Normalizer (Safe DOM)
  // -------------------------------------------------------------------------

  function showStatus(type, message) {
    var $region = $('#status-region');
    $region.empty();

    if (!message) return;

    var alertClass = 'alert-info';
    if (type === 'error') alertClass = 'alert-danger';
    if (type === 'success') alertClass = 'alert-success';
    if (type === 'warning') alertClass = 'alert-warning';

    var $alert = $('<div>')
      .addClass('alert ' + alertClass + ' alert-dismissible fade show py-2 px-3')
      .attr('role', 'alert');

    var $msgSpan = $('<span>').text(message); // Mandatory Correction 3: textContent-safe
    var $closeBtn = $('<button>')
      .attr('type', 'button')
      .addClass('btn-close py-2')
      .attr('aria-label', 'Close')
      .on('click', function () {
        $alert.remove();
      });

    $alert.append($msgSpan).append($closeBtn);
    $region.append($alert);
  }

  function clearStatus() {
    $('#status-region').empty();
  }

  function parseErrorEnvelope(jqXHR) {
    var defaultMsg = 'An unexpected communication error occurred.';
    if (!jqXHR) return { message: defaultMsg };

    try {
      if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
        return jqXHR.responseJSON.error;
      }
      if (typeof jqXHR.responseText === 'string' && jqXHR.responseText.trim() !== '') {
        var parsed = JSON.parse(jqXHR.responseText);
        if (parsed && parsed.error) {
          return parsed.error;
        }
      }
    } catch (e) {
      // Non-JSON response
    }

    if (jqXHR.status === 0) {
      return { message: 'Network error or connection refused. Is the server running?' };
    }

    return { message: 'Server returned HTTP ' + jqXHR.status + ': ' + (jqXHR.statusText || defaultMsg) };
  }

  function normalizeApiError(jqXHR, prefix) {
    var envelope = parseErrorEnvelope(jqXHR);
    var msg = envelope.message || 'Unknown error';
    return (prefix ? prefix + ' ' : '') + msg;
  }

})(jQuery);
