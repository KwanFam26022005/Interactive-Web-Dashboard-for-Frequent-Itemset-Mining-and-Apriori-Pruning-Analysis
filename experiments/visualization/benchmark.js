/**
 * Core Benchmark Engine for RQ3 Visualization Performance Experiment.
 */
class VisualizationBenchmarkRunner {
    constructor(config, libraryManifest, workloads, adapters, runtimeLineage = {}) {
        this.config = config;
        this.libraryManifest = libraryManifest;
        this.workloads = workloads; // { 100: {...}, 1000: {...}, 5000: {...}, 10000: {...} }
        this.adapters = adapters; // { 'ECharts': EChartsAdapter, 'D3': D3Adapter, 'Chart.js': ChartJsAdapter }
        this.runtimeLineage = runtimeLineage; // { git_revision, config_sha256, library_manifest_sha256, workload_sha256 }
        this.results = [];
        this.isRunning = false;
        this.onProgress = null;
        this.onLog = null;
    }

    log(msg) {
        if (this.onLog) this.onLog(msg);
        console.log(`[Benchmark] ${msg}`);
    }

    /**
     * Computes SHA-256 of an ArrayBuffer or Uint8Array using Web Crypto API.
     */
    static async computeSha256(buffer) {
        const hashBuffer = await crypto.subtle.digest('SHA-256', buffer);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }

    /**
     * Settle browser execution loop with fixed 100 ms delay.
     * Natural GC only — no forced GC invocation.
     */
    static settle(ms = 100) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Measures operation latency with the standard two-frame boundary.
     * Metric: render-to-two-frame-observation latency (ms).
     *
     * @param {Function} action Synchronous chart create/update invocation
     * @returns {Promise<number>} Latency in milliseconds rounded to 3 decimals
     */
    static measureLatency(action) {
        return new Promise(resolve => {
            const t0 = performance.now();
            action();
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    const t1 = performance.now();
                    const duration = Math.max(0, Math.round((t1 - t0) * 1000) / 1000);
                    resolve(duration);
                });
            });
        });
    }

    /**
     * Generates deterministic execution schedule covering library x size x repetition.
     * Uses deterministic Fisher-Yates shuffle with seed 42.
     */
    generateSchedule(isSmoke = false, smokeReps = 2) {
        const schedule = [];
        const libraries = this.config.libraries.map(l => l.name);
        const sizes = isSmoke ? [100] : this.config.workload_sizes;
        const reps = isSmoke ? smokeReps : this.config.formal_repetitions;

        for (const lib of libraries) {
            for (const size of sizes) {
                for (let r = 1; r <= reps; r++) {
                    schedule.push({
                        library: lib,
                        workload_size: size,
                        repeat_index: r
                    });
                }
            }
        }

        // Deterministic Fisher-Yates shuffle with schedule seed 42
        let seed = this.config.run_order.seed;
        for (let i = schedule.length - 1; i > 0; i--) {
            seed = (Math.imul(1664525, seed) + 1013904223) >>> 0;
            const j = Math.floor((seed / 4294967296) * (i + 1));
            const temp = schedule[i];
            schedule[i] = schedule[j];
            schedule[j] = temp;
        }

        // Attach 1-based execution order index
        return schedule.map((item, idx) => ({
            ...item,
            execution_order_index: idx + 1
        }));
    }

    /**
     * Detects browser environment details.
     */
    static detectBrowserEnvironment() {
        const ua = navigator.userAgent;
        let name = 'Unknown';
        let version = 'Unknown';

        if (/Edg\/([0-9.]+)/.test(ua)) {
            name = 'Edge';
            version = RegExp.$1;
        } else if (/Chrome\/([0-9.]+)/.test(ua)) {
            name = 'Chrome';
            version = RegExp.$1;
        } else if (/Firefox\/([0-9.]+)/.test(ua)) {
            name = 'Firefox';
            version = RegExp.$1;
        } else if (/Safari\/([0-9.]+)/.test(ua)) {
            name = 'Safari';
            version = RegExp.$1;
        }

        return {
            browser_name: name,
            browser_version: version,
            user_agent: ua,
            viewport_width: window.innerWidth,
            viewport_height: window.innerHeight,
            device_pixel_ratio: window.devicePixelRatio || 1
        };
    }

    /**
     * Executes the complete benchmark suite.
     */
    async run(containerElement, isSmoke = false, smokeReps = 2) {
        if (this.isRunning) return;
        this.isRunning = true;
        this.results = [];

        const env = VisualizationBenchmarkRunner.detectBrowserEnvironment();
        const schedule = this.generateSchedule(isSmoke, smokeReps);
        const total = schedule.length;
        const warmupCount = isSmoke ? 1 : this.config.warmup_iterations;

        const gitRev = this.runtimeLineage.git_revision || 'UNAVAILABLE';
        const cfgSha = this.runtimeLineage.config_sha256 || 'UNAVAILABLE';
        const workloadSha = this.runtimeLineage.workload_sha256 || 'UNAVAILABLE';

        this.log(`Starting ${isSmoke ? 'SMOKE' : 'FORMAL'} benchmark run (${total} observations scheduled)...`);
        this.log(`Lineage Git: ${gitRev} | Config SHA: ${cfgSha.substring(0, 16)}... | Workload SHA: ${workloadSha.substring(0, 16)}...`);

        // 1. Warmup phase (unrecorded)
        this.log(`Running ${warmupCount} warmup iterations per library/size...`);
        const libraries = this.config.libraries.map(l => l.name);
        const sizes = isSmoke ? [100] : this.config.workload_sizes;

        for (const lib of libraries) {
            const adapter = this.adapters[lib];
            for (const size of sizes) {
                const workload = this.workloads[size];
                for (let w = 1; w <= warmupCount; w++) {
                    await VisualizationBenchmarkRunner.settle(100);
                    let instance = null;
                    try {
                        await VisualizationBenchmarkRunner.measureLatency(() => {
                            instance = adapter.create(containerElement, workload, this.config);
                        });
                        await VisualizationBenchmarkRunner.measureLatency(() => {
                            adapter.update(instance, workload, this.config);
                        });
                    } finally {
                        if (instance) {
                            try { adapter.destroy(instance); } catch (e) {}
                            instance = null;
                        }
                        containerElement.innerHTML = '';
                        await VisualizationBenchmarkRunner.settle(100);
                    }
                }
            }
        }

        this.log(`Warmups complete. Executing scheduled observations...`);

        // 2. Scheduled observations
        let obsIdCounter = 1;
        for (const item of schedule) {
            const adapter = this.adapters[item.library];
            const workload = this.workloads[item.workload_size];
            const obsId = `OBS-VIS-${String(obsIdCounter).padStart(5, '0')}`;
            obsIdCounter++;

            const libMeta = this.config.libraries.find(l => l.name === item.library) || {};

            const obsRecord = {
                observation_id: obsId,
                git_revision: gitRev,
                benchmark_config_sha256: cfgSha,
                workload_sha256: workloadSha,
                library: item.library,
                library_version: libMeta.version || adapter.version,
                renderer: libMeta.renderer || adapter.renderer,
                workload_size: item.workload_size,
                repeat_index: item.repeat_index,
                execution_order_index: item.execution_order_index,
                render_ms: null,
                update_ms: null,
                browser_name: env.browser_name,
                browser_version: env.browser_version,
                viewport_width: env.viewport_width,
                viewport_height: env.viewport_height,
                device_pixel_ratio: env.device_pixel_ratio,
                status: 'PENDING',
                failure_code: ''
            };

            await VisualizationBenchmarkRunner.settle(100);
            let instance = null;

            try {
                // Initial Render Timing
                let createdInstance = null;
                const renderMs = await VisualizationBenchmarkRunner.measureLatency(() => {
                    createdInstance = adapter.create(containerElement, workload, this.config);
                });
                instance = createdInstance;
                obsRecord.render_ms = renderMs;

                // Verification of mark count
                const renderedCount = adapter.getRenderedCount(instance);
                if (renderedCount !== item.workload_size) {
                    obsRecord.status = 'COUNT_MISMATCH';
                    obsRecord.failure_code = `Expected ${item.workload_size} marks, found ${renderedCount}`;
                } else {
                    // In-Place Update Timing (No intermediate settle delay)
                    const updateMs = await VisualizationBenchmarkRunner.measureLatency(() => {
                        adapter.update(instance, workload, this.config);
                    });
                    obsRecord.update_ms = updateMs;
                    obsRecord.status = 'COMPLETED';
                }
            } catch (err) {
                obsRecord.status = 'FAILED';
                obsRecord.failure_code = err.message || String(err);
                this.log(`Error during ${obsId} (${item.library} N=${item.workload_size}): ${err.message}`);
            } finally {
                if (instance) {
                    try {
                        adapter.destroy(instance);
                    } catch (e) {}
                    instance = null;
                }
                containerElement.innerHTML = '';
                await VisualizationBenchmarkRunner.settle(100);
            }

            this.results.push(obsRecord);

            if (this.onProgress) {
                this.onProgress({
                    current: this.results.length,
                    total: total,
                    lastRecord: obsRecord
                });
            }
        }

        this.isRunning = false;
        this.log(`Benchmark completed with ${this.results.length} observations.`);
        return this.results;
    }

    /**
     * Escapes a value for CSV RFC 4180 compliance.
     */
    static escapeCsv(val) {
        if (val === null || val === undefined) return '';
        const str = String(val);
        if (str.includes(',') || str.includes('"') || str.includes('\n') || str.includes('\r')) {
            return `"${str.replace(/"/g, '""')}"`;
        }
        return str;
    }

    /**
     * Converts results into canonical CSV string with robust escaping.
     */
    toCsv() {
        const header = [
            'observation_id',
            'git_revision',
            'benchmark_config_sha256',
            'workload_sha256',
            'library',
            'library_version',
            'renderer',
            'workload_size',
            'repeat_index',
            'execution_order_index',
            'render_ms',
            'update_ms',
            'browser_name',
            'browser_version',
            'viewport_width',
            'viewport_height',
            'device_pixel_ratio',
            'status',
            'failure_code'
        ];

        const rows = this.results.map(r => [
            VisualizationBenchmarkRunner.escapeCsv(r.observation_id),
            VisualizationBenchmarkRunner.escapeCsv(r.git_revision),
            VisualizationBenchmarkRunner.escapeCsv(r.benchmark_config_sha256),
            VisualizationBenchmarkRunner.escapeCsv(r.workload_sha256),
            VisualizationBenchmarkRunner.escapeCsv(r.library),
            VisualizationBenchmarkRunner.escapeCsv(r.library_version),
            VisualizationBenchmarkRunner.escapeCsv(r.renderer),
            VisualizationBenchmarkRunner.escapeCsv(r.workload_size),
            VisualizationBenchmarkRunner.escapeCsv(r.repeat_index),
            VisualizationBenchmarkRunner.escapeCsv(r.execution_order_index),
            VisualizationBenchmarkRunner.escapeCsv(r.render_ms !== null ? r.render_ms : ''),
            VisualizationBenchmarkRunner.escapeCsv(r.update_ms !== null ? r.update_ms : ''),
            VisualizationBenchmarkRunner.escapeCsv(r.browser_name),
            VisualizationBenchmarkRunner.escapeCsv(r.browser_version),
            VisualizationBenchmarkRunner.escapeCsv(r.viewport_width),
            VisualizationBenchmarkRunner.escapeCsv(r.viewport_height),
            VisualizationBenchmarkRunner.escapeCsv(r.device_pixel_ratio),
            VisualizationBenchmarkRunner.escapeCsv(r.status),
            VisualizationBenchmarkRunner.escapeCsv(r.failure_code)
        ].join(','));

        return [header.join(','), ...rows].join('\n') + '\n';
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = VisualizationBenchmarkRunner;
}
