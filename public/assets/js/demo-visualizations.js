/**
 * FIM Dashboard — Interactive Research Demo Visualizations
 * Presentation enhancement only — does not alter mining results or formal experiment evidence.
 */
(function ($, echarts) {
  'use strict';

  // -------------------------------------------------------------------------
  // 1. Module State & Chart Registry
  // -------------------------------------------------------------------------
  var state = {
    initialized: false,
    currentView: 'overview', // 'overview' | 'apriori' | 'rules'
    rulesSubview: 'network', // 'network' | 'rulespace'
    lastMiningResult: null,
    rulesLimit: '10',        // '10' | '20' | 'all'
    rulespaceMode: '2d',     // '2d' | '3d'
    focusMode: '2d',         // '2d' | '3d'
    focusAutoRotate: false,
    focusModalOpen: false,
    selectedRule: null,
    aprioriLevelIndex: 0,
    aprioriTimer: null,
    autoRotate: false,
    glAvailable: null        // boolean or null if not yet checked
  };

  var chartInstances = {
    sankey: null,
    network: null,
    rulespace2d: null,
    rulespace3d: null,
    focus2d: null,
    focus3d: null
  };

  // Check reduced motion preference
  function isReducedMotion() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  // -------------------------------------------------------------------------
  // 2. Public API Interface
  // -------------------------------------------------------------------------
  var FIMDemoVisualizations = {
    /**
     * Initializes the demo visualizations module and binds DOM controls.
     */
    init: function () {
      if (state.initialized) {
        return;
      }
      bindControls();
      state.initialized = true;
    },

    /**
     * Updates all demo visualizations with a new authoritative mining result.
     * @param {Object} miningResult - Complete response from /api/mining.php
     */
    update: function (miningResult) {
      if (!miningResult || !miningResult.summary) {
        return;
      }

      // Stop any running animations/timers before updating
      this.stopAprioriPlayback();
      this.stopAutoRotate();
      this.stopFocusAutoRotate();

      state.lastMiningResult = miningResult;
      state.aprioriLevelIndex = 0;

      // Validate selected rule against new rules list
      var validSelected = null;
      if (state.selectedRule && miningResult.rules) {
        var antStr = formatItemset(state.selectedRule.antecedent);
        var conStr = formatItemset(state.selectedRule.consequent);
        $.each(miningResult.rules, function (i, r) {
          if (formatItemset(r.antecedent) === antStr && formatItemset(r.consequent) === conStr) {
            validSelected = r;
            return false;
          }
        });
      }
      state.selectedRule = validSelected;

      // Reveal the demo panel
      $('#demo-panel').removeClass('d-none');
      $('#demo-overview-prompt').addClass('d-none');
      $('#demo-overview-content').removeClass('d-none');

      // Update overview summary stats
      renderOverview(miningResult);

      // Re-render currently active view
      renderCurrentView();

      // If Focus Mode modal is open, refresh its context and charts
      if (state.focusModalOpen) {
        updateFocusContext(miningResult);
        renderFocusRuleSpace();
      }
    },

    /**
     * Resets the demo module when mining results are cleared.
     */
    reset: function () {
      this.stopAprioriPlayback();
      this.stopAutoRotate();
      this.stopFocusAutoRotate();

      state.lastMiningResult = null;
      state.aprioriLevelIndex = 0;
      state.selectedRule = null;

      $('#demo-panel').addClass('d-none');
      $('#demo-overview-prompt').removeClass('d-none');
      $('#demo-overview-content').addClass('d-none');

      // Clear all chart instances safely
      if (chartInstances.sankey) chartInstances.sankey.clear();
      if (chartInstances.network) chartInstances.network.clear();
      if (chartInstances.rulespace2d) chartInstances.rulespace2d.clear();
      if (chartInstances.rulespace3d) chartInstances.rulespace3d.clear();
      if (chartInstances.focus2d) chartInstances.focus2d.clear();
      if (chartInstances.focus3d) chartInstances.focus3d.clear();

      this.setView('overview');
    },

    /**
     * Resizes any initialized demo charts.
     */
    resize: function () {
      if (chartInstances.sankey && $('#demo-sankey-chart').is(':visible')) {
        chartInstances.sankey.resize();
      }
      if (chartInstances.network && $('#demo-network-chart').is(':visible')) {
        chartInstances.network.resize();
      }
      if (chartInstances.rulespace2d && $('#demo-rulespace-2d-chart').is(':visible')) {
        chartInstances.rulespace2d.resize();
      }
      if (chartInstances.rulespace3d && $('#demo-rulespace-3d-chart').is(':visible')) {
        chartInstances.rulespace3d.resize();
      }
      if (chartInstances.focus2d && $('#demo-focus-rulespace-2d').is(':visible')) {
        chartInstances.focus2d.resize();
      }
      if (chartInstances.focus3d && $('#demo-focus-rulespace-3d').is(':visible')) {
        chartInstances.focus3d.resize();
      }
    },

    /**
     * Switches the active demo view tab.
     * @param {string} view - 'overview' | 'apriori' | 'rules'
     */
    setView: function (view) {
      if (view !== 'overview' && view !== 'apriori' && view !== 'rules') {
        return;
      }

      // Leaving view cleanups
      if (state.currentView === 'apriori' && view !== 'apriori') {
        this.stopAprioriPlayback();
      }
      if (state.currentView === 'rules' && view !== 'rules') {
        this.stopAutoRotate();
      }

      state.currentView = view;

      // Update navigation button states
      $('.demo-nav-btn').removeClass('active').attr('aria-selected', 'false');
      $('.demo-nav-btn[data-demo-view="' + view + '"]').addClass('active').attr('aria-selected', 'true');

      // Toggle views visibility
      $('.demo-view-content').addClass('d-none');
      $('#demo-view-' + view).removeClass('d-none');

      // Render active view if data is loaded
      if (state.lastMiningResult) {
        renderCurrentView();
        // Trigger resize on visible charts after showing container
        setTimeout(function () {
          FIMDemoVisualizations.resize();
        }, 50);
      }
    },

    stopAprioriPlayback: function () {
      if (state.aprioriTimer) {
        clearInterval(state.aprioriTimer);
        state.aprioriTimer = null;
      }
      $('#demo-apriori-play').removeClass('active btn-success').addClass('btn-outline-success');
      $('#demo-apriori-pause').prop('disabled', true);
    },

    stopAutoRotate: function () {
      state.autoRotate = false;
      $('#demo-3d-auto-rotate').text('Auto Rotate: OFF').removeClass('btn-primary').addClass('btn-outline-secondary');
      if (chartInstances.rulespace3d) {
        try {
          chartInstances.rulespace3d.setOption({
            grid3D: {
              viewControl: {
                autoRotate: false
              }
            }
          });
        } catch (e) {
          // Ignore if 3D not available
        }
      }
    },

    stopFocusAutoRotate: function () {
      state.focusAutoRotate = false;
      $('#demo-focus-3d-auto-rotate').text('Auto Rotate: OFF').removeClass('btn-primary').addClass('btn-outline-secondary');
      if (chartInstances.focus3d) {
        try {
          chartInstances.focus3d.setOption({
            grid3D: {
              viewControl: {
                autoRotate: false
              }
            }
          });
        } catch (e) {
          // Ignore if 3D not available
        }
      }
    }
  };

  // -------------------------------------------------------------------------
  // 3. Event Binding & DOM Controls
  // -------------------------------------------------------------------------
  function bindControls() {
    // Top-level demo view navigation
    $('.demo-nav-btn').on('click', function () {
      var view = $(this).attr('data-demo-view');
      FIMDemoVisualizations.setView(view);
    });

    // Overview card CTA jump-to-view buttons
    $('#demo-overview-stats').on('click', '[data-jump-view]', function () {
      var view = $(this).attr('data-jump-view');
      FIMDemoVisualizations.setView(view);
    });

    // Apriori flow navigation controls
    $('#demo-apriori-prev').on('click', onAprioriPrev);
    $('#demo-apriori-next').on('click', onAprioriNext);
    $('#demo-apriori-play').on('click', onAprioriPlay);
    $('#demo-apriori-pause').on('click', onAprioriPause);
    $('#demo-apriori-restart').on('click', onAprioriRestart);

    // Apriori clickable level navigation strip
    $('#demo-apriori-levels-strip').on('click', '.demo-level-pill', function () {
      var idx = Number($(this).attr('data-level-index'));
      if (!isNaN(idx) && state.lastMiningResult && state.lastMiningResult.levels) {
        state.aprioriLevelIndex = idx;
        renderAprioriFlow();
      }
    });

    // Explore rules secondary navigation (Network vs Rule Space)
    $('.demo-rules-subnav').on('click', function () {
      var subview = $(this).attr('data-rules-subview');
      setRulesSubview(subview);
    });

    // Rule network filtering controls
    $('.demo-network-filter').on('click', function () {
      $('.demo-network-filter').removeClass('active');
      $(this).addClass('active');
      state.rulesLimit = $(this).attr('data-limit');
      if (state.lastMiningResult) {
        renderRuleNetwork();
      }
    });

    $('#demo-network-reset').on('click', function () {
      if (chartInstances.network) {
        chartInstances.network.dispatchAction({
          type: 'restore'
        });
      }
    });

    // Rule space mode selection (2D vs 3D)
    $('.demo-rulespace-mode').on('click', function () {
      $('.demo-rulespace-mode').removeClass('active');
      $(this).addClass('active');
      var mode = $(this).attr('data-mode');
      setRulespaceMode(mode);
    });

    $('#demo-3d-reset-view').on('click', function () {
      reset3DCamera();
    });

    $('#demo-3d-auto-rotate').on('click', function () {
      toggle3DAutoRotate();
    });

    // Rule Space Focus Mode Modal Lifecycle
    $('#demo-rulespace-focus-modal').off('show.bs.modal shown.bs.modal hidden.bs.modal');

    $('#demo-rulespace-focus-modal').on('show.bs.modal', function () {
      state.focusMode = state.rulespaceMode || '2d';
    });

    $('#demo-rulespace-focus-modal').on('shown.bs.modal', function () {
      state.focusModalOpen = true;
      updateFocusContext(state.lastMiningResult);
      setFocusMode(state.focusMode);
      setTimeout(function () {
        if (state.focusMode === '3d' && chartInstances.focus3d) {
          chartInstances.focus3d.resize();
        } else if (state.focusMode === '2d' && chartInstances.focus2d) {
          chartInstances.focus2d.resize();
        }
      }, 100);
    });

    $('#demo-rulespace-focus-modal').on('hidden.bs.modal', function () {
      state.focusModalOpen = false;
      FIMDemoVisualizations.stopFocusAutoRotate();
      if (state.selectedRule) {
        displayRuleDetail(state.selectedRule);
      }
      setTimeout(function () {
        FIMDemoVisualizations.resize();
      }, 50);
    });

    // Focus Mode dimension toggle
    $('.demo-focus-mode').on('click', function () {
      var mode = $(this).attr('data-mode');
      setFocusMode(mode);
    });

    // Focus 3D Camera Controls
    $('#demo-focus-3d-reset-view').on('click', function () {
      resetFocus3DCamera();
    });

    $('#demo-focus-3d-auto-rotate').on('click', function () {
      toggleFocus3DAutoRotate();
    });

    // Fallback switch to 2D
    $('#demo-focus-fallback-switch-2d').on('click', function () {
      setFocusMode('2d');
    });

    // Optional keyboard shortcuts when Focus modal is active
    $(document).off('keydown.fimFocus').on('keydown.fimFocus', function (e) {
      if (!state.focusModalOpen) return;
      var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
      if (tag === 'input' || tag === 'textarea' || tag === 'select') return;

      if (e.key === '1') {
        setFocusMode('2d');
      } else if (e.key === '2') {
        setFocusMode('3d');
      } else if (e.key === 'r' || e.key === 'R') {
        if (state.focusMode === '3d') {
          resetFocus3DCamera();
        }
      }
    });
  }

  function setRulesSubview(subview) {
    if (subview !== 'network' && subview !== 'rulespace') {
      return;
    }
    state.rulesSubview = subview;

    $('.demo-rules-subnav').removeClass('active').attr('aria-selected', 'false');
    $('.demo-rules-subnav[data-rules-subview="' + subview + '"]').addClass('active').attr('aria-selected', 'true');

    if (subview === 'network') {
      $('#demo-subview-rulespace').addClass('d-none');
      $('#demo-subview-network').removeClass('d-none');
      FIMDemoVisualizations.stopAutoRotate();
      renderRuleNetwork();
    } else {
      $('#demo-subview-network').addClass('d-none');
      $('#demo-subview-rulespace').removeClass('d-none');
      renderRuleSpace();
    }

    setTimeout(function () {
      FIMDemoVisualizations.resize();
    }, 60);
  }

  // -------------------------------------------------------------------------
  // 4. View Rendering Dispatcher
  // -------------------------------------------------------------------------
  function renderCurrentView() {
    if (!state.lastMiningResult) {
      return;
    }

    if (state.currentView === 'overview') {
      renderOverview(state.lastMiningResult);
    } else if (state.currentView === 'apriori') {
      renderAprioriFlow();
    } else if (state.currentView === 'rules') {
      renderExploreRules();
    }
  }

  function renderOverview(data) {
    var sum = data.summary;
    var $stats = $('#demo-overview-stats');
    $stats.empty();

    var levelCount = data.levels ? data.levels.length : 0;
    var pruningRatioText = sum.pruning_ratio !== null ? (Number(sum.pruning_ratio) * 100).toFixed(2) + '%' : 'N/A';
    var rulesCountText = Number(sum.rules_count).toLocaleString();

    var $row = $('<div>').addClass('row g-3');

    // Card 1: Explain Apriori
    var $col1 = $('<div>').addClass('col-md-6');
    var $card1 = $('<div>').addClass('demo-overview-card');
    $card1.append(
      $('<div>').addClass('d-flex justify-content-between align-items-center mb-2').append(
        $('<h3>').addClass('demo-overview-card-title mb-0').text('Explain Apriori Flow'),
        $('<span>').addClass('badge bg-primary-subtle text-primary border border-primary-subtle').text(levelCount + ' Levels')
      ),
      $('<p>').addClass('text-muted small mb-3').text('Step-by-step interactive inspection of candidate generation, pruning ratios, and mass flow conservation across mining levels.'),
      $('<div>').addClass('d-flex align-items-center gap-3 mb-3 p-2 bg-light rounded border').append(
        $('<div>').append(
          $('<div>').addClass('text-muted small').text('Levels Generated'),
          $('<div>').addClass('fw-bold font-monospace fs-6 text-dark').text(levelCount)
        ),
        $('<div>').addClass('vr mx-2'),
        $('<div>').append(
          $('<div>').addClass('text-muted small').text('Overall Pruning Ratio'),
          $('<div>').addClass('fw-bold font-monospace fs-6 text-primary').text(pruningRatioText)
        )
      ),
      $('<button>').addClass('btn btn-outline-primary btn-sm px-3 fw-semibold w-100')
        .attr('type', 'button')
        .attr('data-jump-view', 'apriori')
        .html('Launch Apriori Explainer &rarr;')
    );
    $col1.append($card1);

    // Card 2: Explore Rules
    var $col2 = $('<div>').addClass('col-md-6');
    var $card2 = $('<div>').addClass('demo-overview-card');
    $card2.append(
      $('<div>').addClass('d-flex justify-content-between align-items-center mb-2').append(
        $('<h3>').addClass('demo-overview-card-title mb-0').text('Explore Association Rules'),
        $('<span>').addClass('badge bg-success-subtle text-success border border-success-subtle').text(rulesCountText + ' Rules')
      ),
      $('<p>').addClass('text-muted small mb-3').text('Multi-perspective analysis of extracted rules through force-directed topology and 2D/3D Support-Confidence-Lift rule space.'),
      $('<div>').addClass('d-flex align-items-center gap-3 mb-3 p-2 bg-light rounded border').append(
        $('<div>').append(
          $('<div>').addClass('text-muted small').text('Rules Extracted'),
          $('<div>').addClass('fw-bold font-monospace fs-6 text-dark').text(rulesCountText)
        ),
        $('<div>').addClass('vr mx-2'),
        $('<div>').append(
          $('<div>').addClass('text-muted small').text('Available Views'),
          $('<div>').addClass('fw-semibold small text-success').text('Network / 2D / 3D')
        )
      ),
      $('<button>').addClass('btn btn-outline-primary btn-sm px-3 fw-semibold w-100')
        .attr('type', 'button')
        .attr('data-jump-view', 'rules')
        .html('Explore Association Rules &rarr;')
    );
    $col2.append($card2);

    $row.append($col1, $col2);
    $stats.append($row);
  }

  // -------------------------------------------------------------------------
  // 5. Apriori Flow Explainer (Stage C)
  // -------------------------------------------------------------------------
  function renderAprioriFlow() {
    var data = state.lastMiningResult;
    var $metrics = $('#demo-apriori-metrics');
    var $label = $('#demo-apriori-level-label');

    if (!data || !data.levels || data.levels.length === 0) {
      $metrics.html('<div class="text-muted text-center py-2">No levels generated in Apriori execution.</div>');
      $label.text('Level —');
      $('#demo-apriori-prev, #demo-apriori-next, #demo-apriori-play, #demo-apriori-pause, #demo-apriori-restart').prop('disabled', true);
      if (chartInstances.sankey) {
        chartInstances.sankey.clear();
      }
      return;
    }

    var levels = data.levels;
    var totalLevels = levels.length;

    // Clamp level index
    if (state.aprioriLevelIndex < 0) state.aprioriLevelIndex = 0;
    if (state.aprioriLevelIndex >= totalLevels) state.aprioriLevelIndex = totalLevels - 1;

    var curIdx = state.aprioriLevelIndex;
    var lvl = levels[curIdx];

    // Update control states
    $('#demo-apriori-prev').prop('disabled', curIdx === 0);
    $('#demo-apriori-next').prop('disabled', curIdx === totalLevels - 1);
    $('#demo-apriori-play').prop('disabled', curIdx === totalLevels - 1 || isReducedMotion());
    $('#demo-apriori-pause').prop('disabled', !state.aprioriTimer);
    $('#demo-apriori-restart').prop('disabled', false);

    $label.text('Level k=' + lvl.k + ' (' + lvl.source + ') [' + (curIdx + 1) + '/' + totalLevels + ']');

    // Authoritative metrics
    var k = lvl.k;
    var source = lvl.source;
    var generated = Number(lvl.generated);
    var pruned = Number(lvl.pruned);
    var evaluated = Number(lvl.evaluated);
    var frequent = Number(lvl.frequent);
    var infrequent = evaluated - frequent;
    var pruningRatio = lvl.pruning_ratio;
    var pruningRatioText = pruningRatio === null ? 'N/A' : (Number(pruningRatio) * 100).toFixed(2) + '%';

    // -----------------------------------------------------------------------
    // Invariant Verification (Do NOT fabricate flow if violated)
    // 1. generated === pruned + evaluated
    // 2. frequent <= evaluated
    // 3. infrequent >= 0
    // -----------------------------------------------------------------------
    var inv1 = (generated === pruned + evaluated);
    var inv2 = (frequent <= evaluated);
    var inv3 = (infrequent >= 0);

    if (!inv1 || !inv2 || !inv3) {
      FIMDemoVisualizations.stopAprioriPlayback();
      var errDiv = $('<div>').addClass('alert alert-danger mb-0 py-2');
      errDiv.append($('<strong>').text('Level integrity check failed. '));
      errDiv.append($('<span>').text(
        'Offending values: k=' + k +
        ', generated=' + generated +
        ', pruned=' + pruned +
        ', evaluated=' + evaluated +
        ', frequent=' + frequent +
        ', infrequent=' + infrequent +
        ' (Violations: ' + (!inv1 ? 'generated != pruned + evaluated; ' : '') +
        (!inv2 ? 'frequent > evaluated; ' : '') +
        (!inv3 ? 'infrequent < 0;' : '') + ')'
      ));
      $metrics.empty().append(errDiv);
      if (chartInstances.sankey) {
        chartInstances.sankey.clear();
      }
      return;
    }

    // Render clickable level navigation strip
    var $strip = $('#demo-apriori-levels-strip');
    $strip.empty();
    $.each(levels, function (i, l) {
      var isActive = (i === curIdx);
      var btnClass = isActive ? 'btn-primary active' : 'btn-outline-secondary';
      var $pill = $('<button>')
        .attr('type', 'button')
        .addClass('btn btn-sm demo-level-pill ' + btnClass)
        .attr('data-level-index', i)
        .attr('aria-pressed', isActive ? 'true' : 'false')
        .text('k=' + l.k);
      $strip.append($pill);
    });

    // Render compact metric chips
    $metrics.empty();
    var $grid = $('<div>').addClass('demo-metrics-grid');
    var chips = [
      { label: 'Level (k)', val: 'k=' + k, cls: 'text-dark' },
      { label: 'Source', val: source, cls: 'text-dark' },
      { label: 'Generated', val: generated.toLocaleString(), cls: 'text-dark' },
      { label: 'Pruned', val: pruned.toLocaleString(), cls: 'text-danger' },
      { label: 'Evaluated', val: evaluated.toLocaleString(), cls: 'text-primary' },
      { label: 'Frequent', val: frequent.toLocaleString(), cls: 'text-success' },
      { label: 'Infrequent', val: infrequent.toLocaleString(), cls: 'text-warning' },
      { label: 'Pruning Ratio', val: pruningRatioText, cls: 'text-dark' }
    ];
    $.each(chips, function (i, c) {
      var $chip = $('<div>').addClass('demo-metric-chip');
      $chip.append(
        $('<div>').addClass('demo-metric-chip-label').text(c.label),
        $('<div>').addClass('demo-metric-chip-val ' + c.cls).text(c.val)
      );
      $grid.append($chip);
    });
    $metrics.append($grid);

    // Initialize or get Sankey chart
    if (!chartInstances.sankey) {
      var container = document.getElementById('demo-sankey-chart');
      if (container) {
        chartInstances.sankey = echarts.init(container);
      }
    }
    if (!chartInstances.sankey) {
      return;
    }

    // Prepare Sankey data and links for this SINGLE level
    // Generated -> Pruned (value: pruned)
    // Generated -> Evaluated (value: evaluated)
    // Evaluated -> Frequent (value: frequent)
    // Evaluated -> Infrequent (value: infrequent)
    // Note: Acutely single level: NO cross-level mass flow across k.
    var nodes = [
      { name: 'Generated', itemStyle: { color: '#64748b' } },
      { name: 'Pruned', itemStyle: { color: '#ef4444' } },
      { name: 'Evaluated', itemStyle: { color: '#3b82f6' } },
      { name: 'Frequent', itemStyle: { color: '#10b981' } },
      { name: 'Infrequent', itemStyle: { color: '#f59e0b' } }
    ];

    var links = [];

    // Only include links with positive value so ECharts Sankey builds valid flow geometry
    if (pruned > 0) {
      links.push({
        source: 'Generated',
        target: 'Pruned',
        value: pruned,
        lineStyle: { color: '#fca5a5' }
      });
    }
    if (evaluated > 0) {
      links.push({
        source: 'Generated',
        target: 'Evaluated',
        value: evaluated,
        lineStyle: { color: '#93c5fd' }
      });
    }
    if (frequent > 0) {
      links.push({
        source: 'Evaluated',
        target: 'Frequent',
        value: frequent,
        lineStyle: { color: '#6ee7b7' }
      });
    }
    if (infrequent > 0) {
      links.push({
        source: 'Evaluated',
        target: 'Infrequent',
        value: infrequent,
        lineStyle: { color: '#fde68a' }
      });
    }

    if (links.length === 0) {
      chartInstances.sankey.setOption({
        title: {
          text: 'No candidate flows at Level k=' + k,
          left: 'center',
          top: 'middle',
          textStyle: { color: '#94a3b8', fontSize: 13 }
        },
        series: []
      }, true);
      return;
    }

    var prefersReduced = isReducedMotion();
    var animDur = prefersReduced ? 0 : 400;

    var option = {
      title: {
        text: 'Apriori Level k=' + k + ' Flow (' + source + ')',
        subtext: 'Mass Invariants: Generated = Pruned + Evaluated | Evaluated = Frequent + Infrequent',
        left: 'center',
        top: '2%',
        textStyle: { fontSize: 13, fontWeight: 600, color: '#1e293b' },
        subtextStyle: { fontSize: 11, color: '#64748b' }
      },
      tooltip: {
        trigger: 'item',
        triggerOn: 'mousemove',
        renderMode: 'richText',
        formatter: function (params) {
          if (params.dataType === 'edge') {
            var src = params.data.source;
            var tgt = params.data.target;
            var val = Number(params.value);
            var labelText = 'Share of Generated';
            var denom = generated;

            // Edges originating from Evaluated must use evaluated as denominator
            if (src === 'Evaluated') {
              labelText = 'Share of Evaluated';
              denom = evaluated;
            }

            var pctText = denom > 0 ? ((val / denom) * 100).toFixed(2) + '%' : '0.00%';

            return 'Flow: ' + src + ' \u2192 ' + tgt + '\n' +
              'Count: ' + val.toLocaleString() + ' candidates\n' +
              labelText + ': ' + pctText;
          }
          return 'Stage: ' + params.name + '\n' +
            'Value: ' + Number(params.value).toLocaleString() + ' candidates';
        }
      },
      animation: !prefersReduced,
      animationDuration: animDur,
      animationEasing: 'cubicOut',
      series: [
        {
          type: 'sankey',
          layout: 'none',
          top: '16%',
          bottom: '10%',
          left: '8%',
          right: '8%',
          nodeWidth: 24,
          nodeGap: 18,
          draggable: false,
          emphasis: {
            focus: 'adjacency'
          },
          label: {
            position: 'right',
            formatter: function (p) {
              return p.name + ' (' + Number(p.value).toLocaleString() + ')';
            },
            color: '#1e293b',
            fontWeight: 600,
            fontSize: 11
          },
          lineStyle: {
            curveness: 0.5,
            opacity: 0.45
          },
          data: nodes,
          links: links
        }
      ]
    };

    chartInstances.sankey.setOption(option, true);
  }

  function onAprioriPrev() {
    if (!state.lastMiningResult || !state.lastMiningResult.levels) return;
    if (state.aprioriLevelIndex > 0) {
      state.aprioriLevelIndex--;
      renderAprioriFlow();
    }
  }

  function onAprioriNext() {
    if (!state.lastMiningResult || !state.lastMiningResult.levels) return;
    if (state.aprioriLevelIndex < state.lastMiningResult.levels.length - 1) {
      state.aprioriLevelIndex++;
      renderAprioriFlow();
    }
  }

  function onAprioriPlay() {
    if (isReducedMotion()) return;
    if (!state.lastMiningResult || !state.lastMiningResult.levels) return;

    var levels = state.lastMiningResult.levels;
    if (levels.length <= 1) return;

    FIMDemoVisualizations.stopAprioriPlayback();

    // If already at end, restart from level 0
    if (state.aprioriLevelIndex >= levels.length - 1) {
      state.aprioriLevelIndex = 0;
      renderAprioriFlow();
    }

    $('#demo-apriori-play').addClass('active btn-success').removeClass('btn-outline-success');
    $('#demo-apriori-pause').prop('disabled', false);

    // Cadence: 1200ms per level
    state.aprioriTimer = setInterval(function () {
      if (!state.lastMiningResult || !state.lastMiningResult.levels) {
        FIMDemoVisualizations.stopAprioriPlayback();
        return;
      }
      var lvls = state.lastMiningResult.levels;
      if (state.aprioriLevelIndex < lvls.length - 1) {
        state.aprioriLevelIndex++;
        renderAprioriFlow();
      } else {
        // At final level: stop automatically, do NOT loop forever
        FIMDemoVisualizations.stopAprioriPlayback();
      }
    }, 1200);
  }

  function onAprioriPause() {
    FIMDemoVisualizations.stopAprioriPlayback();
    $('#demo-apriori-play').removeClass('active btn-success').addClass('btn-outline-success');
    $('#demo-apriori-pause').prop('disabled', true);
  }

  function onAprioriRestart() {
    FIMDemoVisualizations.stopAprioriPlayback();
    $('#demo-apriori-play').removeClass('active btn-success').addClass('btn-outline-success');
    state.aprioriLevelIndex = 0;
    renderAprioriFlow();
  }

  // -------------------------------------------------------------------------
  // 6. Explore Rules — Network & Rule Space Subviews
  // -------------------------------------------------------------------------
  function renderExploreRules() {
    var data = state.lastMiningResult;
    var rules = (data && data.rules) ? data.rules : [];

    if (rules.length === 0) {
      $('#demo-network-chart').addClass('d-none');
      $('#demo-network-empty').removeClass('d-none');
      $('#demo-rulespace-2d-chart').addClass('d-none');
      $('#demo-rulespace-3d-chart').addClass('d-none');
      $('#demo-rulespace-empty').removeClass('d-none');
      $('#demo-3d-rule-detail').addClass('d-none').empty();
      $('#demo-network-side-panel').empty();
      $('.demo-network-filter, #demo-network-reset, .demo-rulespace-mode, #demo-3d-reset-view, #demo-3d-auto-rotate').prop('disabled', true);
      return;
    }

    $('.demo-network-filter, #demo-network-reset, .demo-rulespace-mode, #demo-3d-reset-view, #demo-3d-auto-rotate').prop('disabled', false);
    $('#demo-network-empty').addClass('d-none');
    $('#demo-rulespace-empty').addClass('d-none');

    // Show active subview
    if (state.rulesSubview === 'network') {
      $('#demo-subview-rulespace').addClass('d-none');
      $('#demo-subview-network').removeClass('d-none');
      renderRuleNetwork();
    } else {
      $('#demo-subview-network').addClass('d-none');
      $('#demo-subview-rulespace').removeClass('d-none');
      renderRuleSpace();
    }
  }

  function formatItemset(items) {
    if (!items || !Array.isArray(items)) return '{}';
    return '{' + items.join(', ') + '}';
  }

  function renderRuleDetailCard(rule, $container, headerText) {
    if (!rule || !$container || $container.length === 0) return;
    $container.empty().removeClass('d-none');

    var antStr = formatItemset(rule.antecedent);
    var conStr = formatItemset(rule.consequent);

    var $card = $('<div>').addClass('demo-rule-detail-card');
    if (headerText) {
      $card.append($('<div>').addClass('fw-bold text-primary mb-2 small pb-1 border-bottom').text(headerText));
    }

    var $table = $('<table>').addClass('table table-sm table-borderless demo-rule-detail-table align-middle mb-0');
    var $tbody = $('<tbody>');

    var rows = [
      ['Selected Rule', antStr + ' \u2192 ' + conStr],
      ['Antecedent (LHS)', antStr],
      ['Consequent (RHS)', conStr],
      ['Support', (Number(rule.support) * 100).toFixed(4) + '% (' + Number(rule.support_count).toLocaleString() + ' txns)'],
      ['Confidence', (Number(rule.confidence) * 100).toFixed(2) + '%'],
      ['Lift', Number(rule.lift).toFixed(4)]
    ];

    $.each(rows, function (i, r) {
      var $tr = $('<tr>');
      var $th = $('<th>').addClass('text-muted py-1 small').css('width', '35%').text(r[0]);
      var $td = $('<td>').addClass('py-1 small font-monospace').text(r[1]);
      if (r[0] === 'Selected Rule') {
        $td.addClass('fw-bold text-primary');
      }
      $tr.append($th).append($td);
      $tbody.append($tr);
    });

    $table.append($tbody);
    $card.append($table);
    $container.append($card);
  }

  function renderNetworkGuidance() {
    var $panel = $('#demo-network-side-panel');
    $panel.empty();
    var $guidance = $('<div>').addClass('p-1');
    $guidance.append(
      $('<div>').addClass('fw-bold text-dark small mb-1').text('Network Interpretation'),
      $('<p>').addClass('text-muted small mb-2').text('Nodes represent complete itemset sides (LHS/RHS). Directed arrows represent implication rules.'),
      $('<div>').addClass('mb-3').append(
        $('<div>').addClass('fw-semibold small text-muted mb-1').text('Node Role Palette:'),
        $('<div>').addClass('demo-side-panel-legend mb-1').append(
          $('<span>').addClass('demo-legend-dot').css('background-color', '#3b82f6'),
          $('<span>').text('Antecedent only (LHS)')
        ),
        $('<div>').addClass('demo-side-panel-legend mb-1').append(
          $('<span>').addClass('demo-legend-dot').css('background-color', '#10b981'),
          $('<span>').text('Consequent only (RHS)')
        ),
        $('<div>').addClass('demo-side-panel-legend mb-1').append(
          $('<span>').addClass('demo-legend-dot').css('background-color', '#8b5cf6'),
          $('<span>').text('Both (LHS & RHS)')
        )
      ),
      $('<div>').addClass('border-top pt-2 text-muted small').append(
        $('<div>').addClass('mb-1').html('&bull; <strong>Arrow Width</strong> &prop; Confidence'),
        $('<div>').addClass('mb-1').html('&bull; <strong>Opacity</strong> &prop; Support'),
        $('<div>').html('&bull; <em>Click any node or edge to inspect exact metrics.</em>')
      )
    );
    $panel.append($guidance);
  }

  function renderNodeDetail(itemsetName, catIndex, activeRules) {
    var $panel = $('#demo-network-side-panel');
    $panel.empty();

    var roleNames = ['Antecedent only (LHS)', 'Consequent only (RHS)', 'Both (LHS & RHS)'];
    var roleColors = ['#3b82f6', '#10b981', '#8b5cf6'];
    var catName = roleNames[catIndex] || 'Unknown';
    var catColor = roleColors[catIndex] || '#64748b';

    var incidentRules = [];
    $.each(activeRules, function (i, r) {
      var ant = formatItemset(r.antecedent);
      var con = formatItemset(r.consequent);
      if (ant === itemsetName || con === itemsetName) {
        incidentRules.push(r);
      }
    });

    var $card = $('<div>').addClass('demo-rule-detail-card');
    $card.append($('<div>').addClass('fw-bold text-dark mb-2 small pb-1 border-bottom').text('Selected Itemset Node'));

    var $table = $('<table>').addClass('table table-sm table-borderless demo-rule-detail-table align-middle mb-2');
    var $tbody = $('<tbody>');
    $tbody.append(
      $('<tr>').append($('<th>').text('Itemset'), $('<td>').addClass('fw-bold font-monospace text-primary').text(itemsetName)),
      $('<tr>').append($('<th>').text('Role'), $('<td>').append($('<span>').addClass('badge').css('background-color', catColor).text(catName))),
      $('<tr>').append($('<th>').text('Degree'), $('<td>').addClass('font-monospace').text(incidentRules.length + ' incident rule(s)'))
    );
    $table.append($tbody);
    $card.append($table);

    if (incidentRules.length > 0) {
      $card.append($('<div>').addClass('fw-semibold small text-muted mb-1').text('Connected Rules:'));
      var $list = $('<div>').addClass('list-group list-group-flush small');
      $.each(incidentRules.slice(0, 5), function (i, r) {
        var ruleStr = formatItemset(r.antecedent) + ' \u2192 ' + formatItemset(r.consequent);
        var $item = $('<div>').addClass('list-group-item px-1 py-1 border-0 font-monospace text-truncate');
        $item.attr('title', ruleStr);
        $item.text(ruleStr);
        $list.append($item);
      });
      if (incidentRules.length > 5) {
        $list.append($('<div>').addClass('text-muted font-italic ps-1').text('... and ' + (incidentRules.length - 5) + ' more'));
      }
      $card.append($list);
    }

    $card.append(
      $('<button>').addClass('btn btn-outline-secondary btn-sm w-100 mt-2')
        .attr('type', 'button')
        .text('Show Legend & Guide')
        .on('click', function () {
          renderNetworkGuidance();
        })
    );

    $panel.append($card);
  }

  function renderRuleNetwork() {
    var data = state.lastMiningResult;
    var allRules = (data && data.rules) ? data.rules : [];

    if (allRules.length === 0) {
      $('#demo-network-chart').addClass('d-none');
      $('#demo-network-empty').removeClass('d-none');
      return;
    }

    // Client-side filtering only (no API request)
    var rules;
    if (state.rulesLimit === '10') {
      rules = allRules.slice(0, 10);
    } else if (state.rulesLimit === '20') {
      rules = allRules.slice(0, 20);
    } else {
      rules = allRules;
    }

    if (rules.length === 0) {
      $('#demo-network-chart').addClass('d-none');
      $('#demo-network-empty').removeClass('d-none');
      return;
    }

    $('#demo-network-empty').addClass('d-none');
    $('#demo-network-chart').removeClass('d-none');

    // Build node map and directed edges
    // Derive node roles across all active rules before construction:
    // Categories: 0: Antecedent only (LHS), 1: Consequent only (RHS), 2: Both (LHS & RHS)
    var itemsetRoles = {};
    $.each(rules, function (idx, rule) {
      var antKey = formatItemset(rule.antecedent);
      var conKey = formatItemset(rule.consequent);

      if (!itemsetRoles[antKey]) {
        itemsetRoles[antKey] = { isAntecedent: false, isConsequent: false };
      }
      itemsetRoles[antKey].isAntecedent = true;

      if (!itemsetRoles[conKey]) {
        itemsetRoles[conKey] = { isAntecedent: false, isConsequent: false };
      }
      itemsetRoles[conKey].isConsequent = true;
    });

    var roleCategoryNames = [
      'Antecedent only (LHS)',
      'Consequent only (RHS)',
      'Both (LHS & RHS)'
    ];

    var nodeMap = {};
    var nodes = [];
    var totalUniqueNodes = 0;
    for (var k in itemsetRoles) {
      if (itemsetRoles.hasOwnProperty(k)) totalUniqueNodes++;
    }
    var showNodeLabels = totalUniqueNodes <= 18;

    for (var key in itemsetRoles) {
      if (!itemsetRoles.hasOwnProperty(key)) continue;
      var role = itemsetRoles[key];
      var catIndex = 0;
      var nodeColor = '#3b82f6';

      if (role.isAntecedent && role.isConsequent) {
        catIndex = 2;
        nodeColor = '#8b5cf6'; // Violet for Both
      } else if (role.isConsequent) {
        catIndex = 1;
        nodeColor = '#10b981'; // Green for Consequent only
      } else {
        catIndex = 0;
        nodeColor = '#3b82f6'; // Blue for Antecedent only
      }

      var nodeObj = {
        id: key,
        name: key,
        category: catIndex,
        symbolSize: 26,
        itemStyle: { color: nodeColor },
        label: { show: showNodeLabels, fontSize: 10, color: '#1e293b' }
      };
      nodeMap[key] = nodeObj;
      nodes.push(nodeObj);
    }

    var edges = [];

    $.each(rules, function (idx, rule) {
      var antKey = formatItemset(rule.antecedent);
      var conKey = formatItemset(rule.consequent);

      // Monotonic presentation transforms
      // Width: 1.5px to 6.0px based on confidence
      var conf = Number(rule.confidence);
      var edgeWidth = Math.max(1.5, Math.min(6, 1.5 + conf * 4.5));

      // Opacity: 0.35 to 0.95 based on support
      var supp = Number(rule.support);
      var edgeOpacity = Math.max(0.35, Math.min(0.95, 0.35 + supp * 0.6));

      // Lift is used for subtle line color emphasis (does not imply p-value or causality)
      var liftVal = Number(rule.lift);
      var edgeColor = liftVal >= 2.0 ? '#8b5cf6' : (liftVal >= 1.0 ? '#6366f1' : '#94a3b8');

      edges.push({
        source: antKey,
        target: conKey,
        lineStyle: {
          width: edgeWidth,
          opacity: edgeOpacity,
          color: edgeColor,
          curveness: 0.18
        },
        rawRule: rule
      });
    });

    if (!chartInstances.network) {
      var container = document.getElementById('demo-network-chart');
      if (container) {
        chartInstances.network = echarts.init(container);
      }
    }
    if (!chartInstances.network) {
      return;
    }

    var prefersReduced = isReducedMotion();

    // Adaptive force parameters based on node count
    var nodeCount = nodes.length;
    var repulsionVal = nodeCount <= 15 ? 320 : (nodeCount <= 30 ? 220 : 140);
    var edgeLenMin = nodeCount <= 15 ? 90 : 60;
    var edgeLenMax = nodeCount <= 15 ? 180 : 120;

    var option = {
      title: {
        text: 'Association Rule Network (' + rules.length + ' rules displayed)',
        subtext: 'Node = Complete Itemset Side | Directed Edge = Rule | Width \u221D Confidence | Opacity \u221D Support',
        left: '2%',
        top: '2%',
        textStyle: { fontSize: 13, fontWeight: 600, color: '#1e293b' },
        subtextStyle: { fontSize: 11, color: '#64748b' }
      },
      tooltip: {
        trigger: 'item',
        renderMode: 'richText',
        formatter: function (params) {
          if (params.dataType === 'edge') {
            var r = params.data.rawRule;
            if (!r) return '';
            return 'Rule: ' + formatItemset(r.antecedent) + ' \u2192 ' + formatItemset(r.consequent) + '\n' +
              'Support: ' + (Number(r.support) * 100).toFixed(4) + '% (' + Number(r.support_count).toLocaleString() + ' txns)\n' +
              'Confidence: ' + (Number(r.confidence) * 100).toFixed(2) + '%\n' +
              'Lift: ' + Number(r.lift).toFixed(4);
          }
          var catLabel = params.data && params.data.category !== undefined ?
            roleCategoryNames[params.data.category] : '';
          return 'Itemset Side: ' + params.name + (catLabel ? '\nRole: ' + catLabel : '');
        }
      },
      legend: [
        {
          data: ['Antecedent only (LHS)', 'Consequent only (RHS)', 'Both (LHS & RHS)'],
          right: '2%',
          top: '2%',
          orient: 'horizontal',
          textStyle: { fontSize: 11, color: '#475569' }
        }
      ],
      animation: !prefersReduced,
      animationDuration: prefersReduced ? 0 : 500,
      series: [
        {
          name: 'Association Rules',
          type: 'graph',
          layout: 'force',
          top: '12%',
          bottom: '5%',
          left: '4%',
          right: '4%',
          roam: true,
          draggable: true,
          force: {
            repulsion: repulsionVal,
            gravity: 0.12,
            edgeLength: [edgeLenMin, edgeLenMax],
            friction: 0.6
          },
          edgeSymbol: ['none', 'arrow'],
          edgeSymbolSize: [4, 10],
          emphasis: {
            focus: 'adjacency',
            label: {
              show: true,
              fontSize: 11,
              fontWeight: 'bold',
              color: '#0f172a'
            },
            lineStyle: {
              width: 5
            }
          },
          categories: [
            { name: 'Antecedent only (LHS)', itemStyle: { color: '#3b82f6' } },
            { name: 'Consequent only (RHS)', itemStyle: { color: '#10b981' } },
            { name: 'Both (LHS & RHS)', itemStyle: { color: '#8b5cf6' } }
          ],
          data: nodes,
          links: edges
        }
      ]
    };

    chartInstances.network.setOption(option, true);

    // Initial side panel display: guidance & legend
    renderNetworkGuidance();

    // Click handler to display rule or node inspection
    chartInstances.network.off('click');
    chartInstances.network.on('click', function (params) {
      if (params.dataType === 'edge' && params.data && params.data.rawRule) {
        renderRuleDetailCard(params.data.rawRule, $('#demo-network-side-panel'), 'Selected Rule (Network)');
      } else if (params.dataType === 'node') {
        renderNodeDetail(params.name, params.data ? params.data.category : 0, rules);
      }
    });
  }

  // -------------------------------------------------------------------------
  // 7. Rule Space Explorer — 2D & 3D (Stage E)
  // -------------------------------------------------------------------------
  function renderRuleSpace() {
    var data = state.lastMiningResult;
    var rules = (data && data.rules) ? data.rules : [];

    if (rules.length === 0) {
      $('#demo-rulespace-2d-chart').addClass('d-none');
      $('#demo-rulespace-3d-chart').addClass('d-none');
      $('#demo-rulespace-empty').removeClass('d-none');
      $('#demo-3d-rule-detail').addClass('d-none').empty();
      return;
    }

    $('#demo-rulespace-empty').addClass('d-none');

    if (state.rulespaceMode === '3d') {
      renderRuleSpace3D(rules);
    } else {
      renderRuleSpace2D(rules);
    }
  }

  /**
   * Dedicated Demo 2D Rule Space Scatter Plot
   * X: Support (0..1), Y: Confidence (0..1), Color/Size: Lift
   * Completely isolated from the standard dashboard #rule-chart.
   */
  function renderRuleSpace2D(rules) {
    var container = document.getElementById('demo-rulespace-2d-chart');
    if (!container) return;

    if (!chartInstances.rulespace2d) {
      chartInstances.rulespace2d = echarts.init(container);
    }

    var scatterData = [];
    var maxLift = 1.0;

    $.each(rules, function (idx, rule) {
      var liftVal = Number(rule.lift);
      if (liftVal > maxLift) maxLift = liftVal;
      scatterData.push({
        value: [Number(rule.support), Number(rule.confidence), liftVal],
        rawRule: rule
      });
    });

    var prefersReduced = isReducedMotion();

    var option = {
      title: {
        text: '2D Association Rule Space (Support \u00d7 Confidence)',
        subtext: 'Bubble Size & Color \u221D Lift | Dedicated Presentation Exploration Chart',
        left: '2%',
        top: '2%',
        textStyle: { fontSize: 13, fontWeight: 600, color: '#1e293b' },
        subtextStyle: { fontSize: 11, color: '#64748b' }
      },
      tooltip: {
        trigger: 'item',
        renderMode: 'richText',
        formatter: function (params) {
          var raw = params.data ? params.data.rawRule : null;
          if (!raw) return '';
          return 'Rule: ' + formatItemset(raw.antecedent) + ' \u2192 ' + formatItemset(raw.consequent) + '\n' +
            'Support: ' + (Number(raw.support) * 100).toFixed(4) + '% (' + Number(raw.support_count).toLocaleString() + ' txns)\n' +
            'Confidence: ' + (Number(raw.confidence) * 100).toFixed(2) + '%\n' +
            'Lift: ' + Number(raw.lift).toFixed(4);
        }
      },
      grid: {
        left: '4%',
        right: '12%',
        bottom: '8%',
        top: '14%',
        containLabel: true
      },
      xAxis: {
        type: 'value',
        name: 'Support',
        nameLocation: 'middle',
        nameGap: 26,
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
        text: ['High Lift', 'Low Lift'],
        textStyle: { fontSize: 10, color: '#64748b' },
        inRange: {
          color: ['#38bdf8', '#818cf8', '#ef4444']
        }
      },
      animation: !prefersReduced,
      animationDuration: prefersReduced ? 0 : 500,
      series: [
        {
          name: 'Rules 2D',
          type: 'scatter',
          data: scatterData,
          symbolSize: function (val) {
            var lift = val[2];
            var clamped = Math.max(0.1, Math.min(lift, 20));
            return Math.sqrt(clamped) * 12;
          }
        }
      ]
    };

    chartInstances.rulespace2d.setOption(option, true);

    // Auto-display details for top rule in side panel
    if (rules.length > 0) {
      displayRuleDetail(rules[0]);
    }

    // Click handler to show detail
    chartInstances.rulespace2d.off('click');
    chartInstances.rulespace2d.on('click', function (params) {
      if (params.data && params.data.rawRule) {
        displayRuleDetail(params.data.rawRule);
      }
    });
  }

  /**
   * 3D WebGL Rule Space Explorer (scatter3D)
   * X: Support (0..1), Y: Confidence (0..1), Z: Lift (0..max)
   * Formal safety: Presentation demo enhancement only, not part of formal RQ3 benchmark.
   */
  function renderRuleSpace3D(rules) {
    var container = document.getElementById('demo-rulespace-3d-chart');
    if (!container) return;

    // Check runtime WebGL / ECharts-GL initialization
    try {
      if (!chartInstances.rulespace3d) {
        chartInstances.rulespace3d = echarts.init(container);
      }

      var scatter3DData = [];
      var maxLift = 1.0;

      $.each(rules, function (idx, rule) {
        var liftVal = Number(rule.lift);
        if (liftVal > maxLift) maxLift = liftVal;
        scatter3DData.push({
          value: [Number(rule.support), Number(rule.confidence), liftVal],
          rawRule: rule
        });
      });

      var maxZ = Math.max(2.0, Math.ceil(maxLift * 1.1));
      var prefersReduced = isReducedMotion();

      // Ensure auto-rotate complies with reduced motion
      var effectiveAutoRotate = prefersReduced ? false : state.autoRotate;

      var option = {
        title: {
          text: '3D Association Rule Space (Support \u00d7 Confidence \u00d7 Lift)',
          subtext: 'Interactive WebGL Geometry: Drag to Rotate | Wheel to Zoom | Click/Hover Point for Details',
          left: '2%',
          top: '2%',
          textStyle: { fontSize: 13, fontWeight: 600, color: '#1e293b' },
          subtextStyle: { fontSize: 11, color: '#64748b' }
        },
        tooltip: {
          show: true,
          renderMode: 'richText',
          formatter: function (params) {
            var raw = params.data ? params.data.rawRule : null;
            if (!raw) return '';
            return 'Rule: ' + formatItemset(raw.antecedent) + ' \u2192 ' + formatItemset(raw.consequent) + '\n' +
              'Support: ' + (Number(raw.support) * 100).toFixed(4) + '% (' + Number(raw.support_count).toLocaleString() + ' txns)\n' +
              'Confidence: ' + (Number(raw.confidence) * 100).toFixed(2) + '%\n' +
              'Lift: ' + Number(raw.lift).toFixed(4);
          }
        },
        visualMap: {
          show: true,
          dimension: 2,
          min: 0,
          max: maxZ,
          inRange: {
            color: ['#38bdf8', '#fbbf24', '#ef4444']
          },
          text: ['High', 'Low'],
          textStyle: { color: '#64748b', fontSize: 10 },
          right: '2%',
          top: 'center'
        },
        xAxis3D: {
          type: 'value',
          name: 'Support',
          min: 0,
          max: 1,
          nameTextStyle: { color: '#1e293b', fontSize: 11 }
        },
        yAxis3D: {
          type: 'value',
          name: 'Confidence',
          min: 0,
          max: 1,
          nameTextStyle: { color: '#1e293b', fontSize: 11 }
        },
        zAxis3D: {
          type: 'value',
          name: 'Lift',
          min: 0,
          max: maxZ,
          nameTextStyle: { color: '#1e293b', fontSize: 11 }
        },
        grid3D: {
          boxWidth: 130,
          boxDepth: 105,
          boxHeight: 105,
          viewControl: {
            autoRotate: effectiveAutoRotate,
            autoRotateSpeed: 10,
            alpha: 25,
            beta: 40,
            distance: 160,
            minDistance: 40,
            maxDistance: 400
          },
          light: {
            main: {
              intensity: 1.2,
              shadow: false
            },
            ambient: {
              intensity: 0.6
            }
          }
        },
        series: [
          {
            name: 'Rules 3D',
            type: 'scatter3D',
            data: scatter3DData,
            symbolSize: 10,
            itemStyle: {
              opacity: 0.85
            },
            emphasis: {
              itemStyle: {
                color: '#f59e0b',
                borderColor: '#1e293b',
                borderWidth: 2
              }
            }
          }
        ]
      };

      chartInstances.rulespace3d.setOption(option, true);
      state.glAvailable = true;
      $('#demo-3d-fallback').addClass('d-none');
      $('#demo-rulespace-3d-chart').removeClass('d-none');
      $('#demo-3d-reset-view, #demo-3d-auto-rotate').prop('disabled', false);

      // Rule selection detail panel sync
      chartInstances.rulespace3d.off('click');
      chartInstances.rulespace3d.on('click', function (params) {
        if (params.data && params.data.rawRule) {
          displayRuleDetail(params.data.rawRule);
        }
      });

      // Also display detail for the first rule if not yet displayed
      if (rules.length > 0) {
        displayRuleDetail(rules[0]);
      }

    } catch (e) {
      // Graceful runtime fallback if WebGL fails in this environment
      state.glAvailable = false;
      $('#demo-rulespace-3d-chart').addClass('d-none');
      $('#demo-3d-fallback').text('3D visualization is unavailable in this environment.').removeClass('d-none');
      $('#demo-3d-reset-view, #demo-3d-auto-rotate').prop('disabled', true);
    }
  }

  function displayRuleDetail(rule) {
    if (!rule) return;
    state.selectedRule = rule;

    renderRuleDetailTable(rule, $('#demo-3d-rule-detail'));
    renderRuleDetailTable(rule, $('#demo-focus-rule-detail'));
  }

  function renderRuleDetailTable(rule, $container) {
    if (!$container || $container.length === 0) return;
    $container.empty().removeClass('d-none');

    var antStr = formatItemset(rule.antecedent);
    var conStr = formatItemset(rule.consequent);

    var $table = $('<table>').addClass('table table-sm table-borderless align-middle mb-0');
    var $tbody = $('<tbody>');

    var rows = [
      ['Selected Rule', antStr + ' \u2192 ' + conStr],
      ['Antecedent (LHS)', antStr],
      ['Consequent (RHS)', conStr],
      ['Support', (Number(rule.support) * 100).toFixed(4) + '% (' + Number(rule.support_count).toLocaleString() + ' transactions)'],
      ['Confidence', (Number(rule.confidence) * 100).toFixed(2) + '%'],
      ['Lift', Number(rule.lift).toFixed(4)]
    ];

    $.each(rows, function (i, r) {
      var $tr = $('<tr>');
      var $th = $('<th>').addClass('text-muted py-1 small').css('width', '25%').text(r[0]);
      var $td = $('<td>').addClass('py-1 small font-monospace').text(r[1]);
      if (r[0] === 'Selected Rule') {
        $td.addClass('fw-bold text-primary');
      }
      $tr.append($th).append($td);
      $tbody.append($tr);
    });

    $table.append($tbody);
    $container.append($table);
  }

  function reset3DCamera() {
    if (!chartInstances.rulespace3d || !state.glAvailable) return;
    try {
      chartInstances.rulespace3d.setOption({
        grid3D: {
          viewControl: {
            alpha: 25,
            beta: 40,
            distance: 160
          }
        }
      });
    } catch (e) {
      // Ignore
    }
  }

  function toggle3DAutoRotate() {
    if (isReducedMotion()) {
      FIMDemoVisualizations.stopAutoRotate();
      return;
    }
    if (!chartInstances.rulespace3d || !state.glAvailable) return;

    state.autoRotate = !state.autoRotate;

    if (state.autoRotate) {
      $('#demo-3d-auto-rotate').text('Auto Rotate: ON').removeClass('btn-outline-secondary').addClass('btn-primary');
    } else {
      $('#demo-3d-auto-rotate').text('Auto Rotate: OFF').removeClass('btn-primary').addClass('btn-outline-secondary');
    }

    try {
      chartInstances.rulespace3d.setOption({
        grid3D: {
          viewControl: {
            autoRotate: state.autoRotate
          }
        }
      });
    } catch (e) {
      // Ignore
    }
  }

  function setRulespaceMode(mode) {
    state.rulespaceMode = mode;
    if (mode === '3d') {
      $('#demo-rulespace-2d-chart').addClass('d-none');
      $('#demo-rulespace-3d-chart').removeClass('d-none');
      $('#demo-3d-controls-group').removeClass('d-none');
      $('#demo-3d-disclaimer-row').removeClass('d-none');
      $('#demo-3d-reset-view, #demo-3d-auto-rotate, #demo-3d-disclaimer').removeClass('d-none');
      renderRuleSpace();
    } else {
      FIMDemoVisualizations.stopAutoRotate();
      $('#demo-rulespace-3d-chart').addClass('d-none');
      $('#demo-rulespace-2d-chart').removeClass('d-none');
      $('#demo-3d-controls-group').addClass('d-none');
      $('#demo-3d-disclaimer-row').addClass('d-none');
      $('#demo-3d-reset-view, #demo-3d-auto-rotate, #demo-3d-disclaimer').addClass('d-none');
      $('#demo-3d-fallback').addClass('d-none');
      renderRuleSpace();
    }
    setTimeout(function () {
      FIMDemoVisualizations.resize();
    }, 50);
  }

  // -------------------------------------------------------------------------
  // 8. Rule Space Focus Workspace (Dedicated Fullscreen Analytical Mode)
  // -------------------------------------------------------------------------
  function updateFocusContext(data) {
    var $ctx = $('#demo-focus-context');
    if (!data || !data.summary) {
      $ctx.empty();
      return;
    }
    var datasetName = $('#dataset-select option:selected').text() || 'Active Dataset';
    datasetName = $.trim(datasetName);
    var txnCount = data.summary.total_transactions != null ? Number(data.summary.total_transactions).toLocaleString() : '—';
    var supp = data.parameters && data.parameters.min_support != null ? Number(data.parameters.min_support).toFixed(2) : '—';
    var conf = data.parameters && data.parameters.min_confidence != null ? Number(data.parameters.min_confidence).toFixed(2) : '—';
    var rulesCount = data.rules ? data.rules.length : 0;
    var runId = data.summary.run_id ? ' · Run #' + data.summary.run_id : '';

    $ctx.text(datasetName + ' · ' + txnCount + ' txns · support=' + supp + ' · confidence=' + conf + ' · ' + rulesCount + ' returned rules' + runId);
  }

  function setFocusMode(mode) {
    state.focusMode = mode;
    $('.demo-focus-mode').removeClass('active').attr('aria-pressed', 'false');
    $('.demo-focus-mode[data-mode="' + mode + '"]').addClass('active').attr('aria-pressed', 'true');

    if (mode === '3d') {
      $('#demo-focus-rulespace-2d').addClass('d-none');
      $('#demo-focus-rulespace-3d').removeClass('d-none');
      $('#demo-focus-3d-controls').removeClass('d-none');
      $('#demo-focus-3d-disclaimer').removeClass('d-none');
      $('#demo-focus-axis-info').addClass('d-none');
      renderFocusRuleSpace();
    } else {
      FIMDemoVisualizations.stopFocusAutoRotate();
      $('#demo-focus-rulespace-3d').addClass('d-none');
      $('#demo-focus-rulespace-2d').removeClass('d-none');
      $('#demo-focus-3d-controls').addClass('d-none');
      $('#demo-focus-3d-disclaimer').addClass('d-none');
      $('#demo-focus-axis-info').removeClass('d-none');
      $('#demo-focus-3d-fallback').addClass('d-none');
      renderFocusRuleSpace();
    }
    setTimeout(function () {
      if (mode === '3d' && chartInstances.focus3d) {
        chartInstances.focus3d.resize();
      } else if (mode === '2d' && chartInstances.focus2d) {
        chartInstances.focus2d.resize();
      }
    }, 50);
  }

  function renderFocusRuleSpace() {
    var data = state.lastMiningResult;
    var rules = (data && data.rules) ? data.rules : [];

    if (rules.length === 0) {
      $('#demo-focus-rulespace-2d').addClass('d-none');
      $('#demo-focus-rulespace-3d').addClass('d-none');
      $('#demo-focus-grid').addClass('d-none');
      $('#demo-focus-empty').removeClass('d-none');
      $('#demo-focus-rule-detail').empty();
      $('.demo-focus-mode, #demo-focus-3d-reset-view, #demo-focus-3d-auto-rotate').prop('disabled', true);
      return;
    }

    $('#demo-focus-empty').addClass('d-none');
    $('#demo-focus-grid').removeClass('d-none');
    $('.demo-focus-mode, #demo-focus-3d-reset-view, #demo-focus-3d-auto-rotate').prop('disabled', false);

    if (state.focusMode === '3d') {
      renderFocusRuleSpace3D(rules);
    } else {
      renderFocusRuleSpace2D(rules);
    }

    if (state.selectedRule) {
      displayRuleDetail(state.selectedRule);
    } else if (rules.length > 0) {
      displayRuleDetail(rules[0]);
    }
  }

  function renderFocusRuleSpace2D(rules) {
    var container = document.getElementById('demo-focus-rulespace-2d');
    if (!container) return;

    if (!chartInstances.focus2d) {
      chartInstances.focus2d = echarts.init(container);
    }

    var scatterData = [];
    var maxLift = 1.0;

    $.each(rules, function (idx, rule) {
      var liftVal = Number(rule.lift);
      if (liftVal > maxLift) maxLift = liftVal;
      scatterData.push({
        value: [Number(rule.support), Number(rule.confidence), liftVal],
        rawRule: rule
      });
    });

    var prefersReduced = isReducedMotion();

    var option = {
      title: {
        text: '2D Association Rule Space (Support \u00d7 Confidence)',
        subtext: 'Bubble Size & Color \u221D Lift | Fullscreen Focus Workspace (' + rules.length + ' rules)',
        left: '2%',
        top: '1%',
        textStyle: { fontSize: 14, fontWeight: 600, color: '#1e293b' },
        subtextStyle: { fontSize: 12, color: '#64748b' }
      },
      tooltip: {
        trigger: 'item',
        renderMode: 'richText',
        formatter: function (params) {
          var raw = params.data ? params.data.rawRule : null;
          if (!raw) return '';
          return 'Rule: ' + formatItemset(raw.antecedent) + ' \u2192 ' + formatItemset(raw.consequent) + '\n' +
            'Support: ' + (Number(raw.support) * 100).toFixed(4) + '% (' + Number(raw.support_count).toLocaleString() + ' txns)\n' +
            'Confidence: ' + (Number(raw.confidence) * 100).toFixed(2) + '%\n' +
            'Lift: ' + Number(raw.lift).toFixed(4);
        }
      },
      grid: {
        left: '4%',
        right: '12%',
        bottom: '8%',
        top: '12%',
        containLabel: true
      },
      xAxis: {
        type: 'value',
        name: 'Support',
        nameLocation: 'middle',
        nameGap: 30,
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
        right: '1%',
        top: 'center',
        text: ['High Lift', 'Low Lift'],
        textStyle: { fontSize: 11, color: '#64748b' },
        inRange: {
          color: ['#38bdf8', '#818cf8', '#ef4444']
        }
      },
      animation: !prefersReduced,
      animationDuration: prefersReduced ? 0 : 500,
      series: [
        {
          name: 'Focus Rules 2D',
          type: 'scatter',
          data: scatterData,
          symbolSize: function (val) {
            var lift = val[2];
            var clamped = Math.max(0.1, Math.min(lift, 20));
            return Math.sqrt(clamped) * 16;
          }
        }
      ]
    };

    chartInstances.focus2d.setOption(option, true);

    chartInstances.focus2d.off('click');
    chartInstances.focus2d.on('click', function (params) {
      if (params.data && params.data.rawRule) {
        displayRuleDetail(params.data.rawRule);
      }
    });
  }

  function renderFocusRuleSpace3D(rules) {
    var container = document.getElementById('demo-focus-rulespace-3d');
    if (!container) return;

    try {
      if (!chartInstances.focus3d) {
        chartInstances.focus3d = echarts.init(container);
      }

      var scatter3DData = [];
      var maxLift = 1.0;

      $.each(rules, function (idx, rule) {
        var liftVal = Number(rule.lift);
        if (liftVal > maxLift) maxLift = liftVal;
        scatter3DData.push({
          value: [Number(rule.support), Number(rule.confidence), liftVal],
          rawRule: rule
        });
      });

      var maxZ = Math.max(2.0, Math.ceil(maxLift * 1.1));
      var prefersReduced = isReducedMotion();
      var effectiveAutoRotate = prefersReduced ? false : state.focusAutoRotate;

      var option = {
        title: {
          text: '3D Association Rule Space (Support \u00d7 Confidence \u00d7 Lift)',
          subtext: 'Interactive WebGL Geometry: Drag to Rotate | Wheel to Zoom | Fullscreen Focus Workspace (' + rules.length + ' rules)',
          left: '2%',
          top: '1%',
          textStyle: { fontSize: 14, fontWeight: 600, color: '#1e293b' },
          subtextStyle: { fontSize: 12, color: '#64748b' }
        },
        tooltip: {
          show: true,
          renderMode: 'richText',
          formatter: function (params) {
            var raw = params.data ? params.data.rawRule : null;
            if (!raw) return '';
            return 'Rule: ' + formatItemset(raw.antecedent) + ' \u2192 ' + formatItemset(raw.consequent) + '\n' +
              'Support: ' + (Number(raw.support) * 100).toFixed(4) + '% (' + Number(raw.support_count).toLocaleString() + ' txns)\n' +
              'Confidence: ' + (Number(raw.confidence) * 100).toFixed(2) + '%\n' +
              'Lift: ' + Number(raw.lift).toFixed(4);
          }
        },
        visualMap: {
          show: true,
          dimension: 2,
          min: 0,
          max: maxZ,
          inRange: {
            color: ['#38bdf8', '#fbbf24', '#ef4444']
          },
          text: ['High', 'Low'],
          textStyle: { color: '#64748b', fontSize: 11 },
          right: '2%',
          top: 'center'
        },
        xAxis3D: {
          type: 'value',
          name: 'Support',
          min: 0,
          max: 1,
          nameTextStyle: { color: '#1e293b', fontSize: 12 }
        },
        yAxis3D: {
          type: 'value',
          name: 'Confidence',
          min: 0,
          max: 1,
          nameTextStyle: { color: '#1e293b', fontSize: 12 }
        },
        zAxis3D: {
          type: 'value',
          name: 'Lift',
          min: 0,
          max: maxZ,
          nameTextStyle: { color: '#1e293b', fontSize: 12 }
        },
        grid3D: {
          boxWidth: 150,
          boxDepth: 120,
          boxHeight: 120,
          viewControl: {
            autoRotate: effectiveAutoRotate,
            autoRotateSpeed: 10,
            alpha: 25,
            beta: 40,
            distance: 155,
            minDistance: 40,
            maxDistance: 400
          },
          light: {
            main: {
              intensity: 1.2,
              shadow: false
            },
            ambient: {
              intensity: 0.6
            }
          }
        },
        series: [
          {
            name: 'Focus Rules 3D',
            type: 'scatter3D',
            data: scatter3DData,
            symbolSize: 12,
            itemStyle: {
              opacity: 0.85
            },
            emphasis: {
              itemStyle: {
                color: '#f59e0b',
                borderColor: '#1e293b',
                borderWidth: 2
              }
            }
          }
        ]
      };

      chartInstances.focus3d.setOption(option, true);
      $('#demo-focus-3d-fallback').addClass('d-none');
      $('#demo-focus-rulespace-3d').removeClass('d-none');
      $('#demo-focus-3d-reset-view, #demo-focus-3d-auto-rotate').prop('disabled', false);

      chartInstances.focus3d.off('click');
      chartInstances.focus3d.on('click', function (params) {
        if (params.data && params.data.rawRule) {
          displayRuleDetail(params.data.rawRule);
        }
      });
    } catch (e) {
      $('#demo-focus-rulespace-3d').addClass('d-none');
      $('#demo-focus-3d-fallback').removeClass('d-none');
      $('#demo-focus-3d-reset-view, #demo-focus-3d-auto-rotate').prop('disabled', true);
    }
  }

  function resetFocus3DCamera() {
    if (!chartInstances.focus3d) return;
    try {
      chartInstances.focus3d.setOption({
        grid3D: {
          viewControl: {
            alpha: 25,
            beta: 40,
            distance: 155
          }
        }
      });
    } catch (e) {
      // Ignore
    }
  }

  function toggleFocus3DAutoRotate() {
    if (isReducedMotion()) {
      FIMDemoVisualizations.stopFocusAutoRotate();
      return;
    }
    if (!chartInstances.focus3d) return;

    state.focusAutoRotate = !state.focusAutoRotate;

    if (state.focusAutoRotate) {
      $('#demo-focus-3d-auto-rotate').text('Auto Rotate: ON').removeClass('btn-outline-secondary').addClass('btn-primary');
    } else {
      $('#demo-focus-3d-auto-rotate').text('Auto Rotate: OFF').removeClass('btn-primary').addClass('btn-outline-secondary');
    }

    try {
      chartInstances.focus3d.setOption({
        grid3D: {
          viewControl: {
            autoRotate: state.focusAutoRotate
          }
        }
      });
    } catch (e) {
      // Ignore
    }
  }

  // Expose to global window
  window.FIMDemoVisualizations = FIMDemoVisualizations;

})(jQuery, window.echarts);

