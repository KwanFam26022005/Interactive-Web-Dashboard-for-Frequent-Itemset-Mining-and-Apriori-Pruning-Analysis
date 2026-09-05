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
    lastMiningResult: null,
    rulesLimit: '10',        // '10' | '20' | 'all'
    rulespaceMode: '2d',     // '2d' | '3d'
    aprioriLevelIndex: 0,
    aprioriTimer: null,
    autoRotate: false,
    glAvailable: null        // boolean or null if not yet checked
  };

  var chartInstances = {
    sankey: null,
    network: null,
    rulespace2d: null,
    rulespace3d: null
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

      state.lastMiningResult = miningResult;
      state.aprioriLevelIndex = 0;

      // Reveal the demo panel
      $('#demo-panel').removeClass('d-none');
      $('#demo-overview-prompt').addClass('d-none');
      $('#demo-overview-content').removeClass('d-none');

      // Update overview summary stats
      renderOverview(miningResult);

      // Re-render currently active view
      renderCurrentView();
    },

    /**
     * Resets the demo module when mining results are cleared.
     */
    reset: function () {
      this.stopAprioriPlayback();
      this.stopAutoRotate();

      state.lastMiningResult = null;
      state.aprioriLevelIndex = 0;

      $('#demo-panel').addClass('d-none');
      $('#demo-overview-prompt').removeClass('d-none');
      $('#demo-overview-content').addClass('d-none');

      // Clear all chart instances safely
      if (chartInstances.sankey) chartInstances.sankey.clear();
      if (chartInstances.network) chartInstances.network.clear();
      if (chartInstances.rulespace2d) chartInstances.rulespace2d.clear();
      if (chartInstances.rulespace3d) chartInstances.rulespace3d.clear();

      this.setView('overview');
    },

    /**
     * Resizes any initialized demo charts.
     */
    resize: function () {
      if (chartInstances.sankey) chartInstances.sankey.resize();
      if (chartInstances.network) chartInstances.network.resize();
      if (chartInstances.rulespace2d) chartInstances.rulespace2d.resize();
      if (chartInstances.rulespace3d) chartInstances.rulespace3d.resize();
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

    // Apriori flow navigation controls
    $('#demo-apriori-prev').on('click', onAprioriPrev);
    $('#demo-apriori-next').on('click', onAprioriNext);
    $('#demo-apriori-play').on('click', onAprioriPlay);
    $('#demo-apriori-pause').on('click', onAprioriPause);
    $('#demo-apriori-restart').on('click', onAprioriRestart);

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

    var $row = $('<div>').addClass('row g-2 text-center');

    var cards = [
      { label: 'Apriori Levels', value: (data.levels ? data.levels.length : 0) },
      { label: 'Frequent Sets', value: Number(sum.frequent_itemsets).toLocaleString() },
      { label: 'Rules Found', value: Number(sum.rules_count).toLocaleString() },
      { label: 'Pruning Ratio', value: sum.pruning_ratio !== null ? (Number(sum.pruning_ratio) * 100).toFixed(2) + '%' : 'N/A' }
    ];

    $.each(cards, function (i, c) {
      var $col = $('<div>').addClass('col-6 col-md-3');
      var $card = $('<div>').addClass('card bg-light border p-2');
      $card.append($('<div>').addClass('text-muted small fw-semibold').text(c.label));
      $card.append($('<div>').addClass('fs-5 fw-bold font-monospace text-dark').text(c.value));
      $col.append($card);
      $row.append($col);
    });

    $stats.append($row);
  }

  // -------------------------------------------------------------------------
  // 5. Apriori Flow Explainer (Stubs for Stage B, fleshed in Stage C)
  // -------------------------------------------------------------------------
  function renderAprioriFlow() {
    var data = state.lastMiningResult;
    if (!data || !data.levels || data.levels.length === 0) {
      $('#demo-apriori-metrics').html('<span class="text-muted">No levels available in mining result.</span>');
      return;
    }
    // Will be fully implemented in Stage C
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
    // Will be fully implemented in Stage C
  }

  function onAprioriPause() {
    FIMDemoVisualizations.stopAprioriPlayback();
  }

  function onAprioriRestart() {
    FIMDemoVisualizations.stopAprioriPlayback();
    state.aprioriLevelIndex = 0;
    renderAprioriFlow();
  }

  // -------------------------------------------------------------------------
  // 6. Explore Rules (Stubs for Stage B, fleshed in Stage D & E)
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
      $('.demo-network-filter, #demo-network-reset, .demo-rulespace-mode, #demo-3d-reset-view, #demo-3d-auto-rotate').prop('disabled', true);
      return;
    }

    $('.demo-network-filter, #demo-network-reset, .demo-rulespace-mode, #demo-3d-reset-view, #demo-3d-auto-rotate').prop('disabled', false);
    $('#demo-network-empty').addClass('d-none');
    $('#demo-rulespace-empty').addClass('d-none');
    $('#demo-network-chart').removeClass('d-none');

    renderRuleNetwork();
    renderRuleSpace();
  }

  function renderRuleNetwork() {
    // Will be fully implemented in Stage D
  }

  function renderRuleSpace() {
    // Will be fully implemented in Stage E
  }

  function setRulespaceMode(mode) {
    state.rulespaceMode = mode;
    if (mode === '3d') {
      $('#demo-rulespace-2d-chart').addClass('d-none');
      $('#demo-rulespace-3d-chart').removeClass('d-none');
      $('#demo-3d-reset-view, #demo-3d-auto-rotate, #demo-3d-disclaimer').removeClass('d-none');
      renderRuleSpace();
    } else {
      FIMDemoVisualizations.stopAutoRotate();
      $('#demo-rulespace-3d-chart').addClass('d-none');
      $('#demo-rulespace-2d-chart').removeClass('d-none');
      $('#demo-3d-reset-view, #demo-3d-auto-rotate, #demo-3d-disclaimer').addClass('d-none');
      renderRuleSpace();
    }
    setTimeout(function () {
      FIMDemoVisualizations.resize();
    }, 50);
  }

  function reset3DCamera() {
    // Will be implemented in Stage E
  }

  function toggle3DAutoRotate() {
    // Will be implemented in Stage E
  }

  // Expose to global window
  window.FIMDemoVisualizations = FIMDemoVisualizations;

})(jQuery, window.echarts);
