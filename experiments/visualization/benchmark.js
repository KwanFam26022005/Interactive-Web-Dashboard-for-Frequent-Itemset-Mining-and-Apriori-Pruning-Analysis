/**
 * Core Benchmark Engine for RQ3 Visualization Performance Experiment.
 */
class VisualizationBenchmarkRunner {
    constructor(config, libraryManifest, workloads, adapters) {
        this.config = config;
        this.libraryManifest = libraryManifest;
        this.workloads = workloads;
        this.adapters = adapters; // { 'ECharts': EChartsAdapter, 'D3': D3Adapter, 'Chart.js': ChartJsAdapter }
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
     * Settle browser rendering loop by waiting for 2 animation frames.
     */
    static settle() {
        return new Promise(resolve => {
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    setTimeout(resolve, 16);
                });
            });
        });
    }

    /**
     * Measures operation latency with the standard two-frame boundary.
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
     * Uses Mulberry32 / LCG with seed 42 to shuffle items deterministically.
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

        // Deterministic Fisher-Yates shuffle with seed 42
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

        if (/Chrome\/([0-9.]+)/.test(ua) && !/Edg/.test(ua)) {
            name = 'Chrome';
            version = RegExp.$1;
        } else if (/Edg\/([0-9.]+)/.test(ua)) {
            name = 'Edge';
            version = RegExp.$1;
        } else if (/Firefox\/([0-9.]+)/.test(ua)) {
            name = 'Firefox';
            version = RegExp.$1;
        } else if (/Safari\/([0-9.]+)/.test(ua) && !/Chrome/.test(ua)) {
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

        this.log(`Starting ${isSmoke ? 'SMOKE' : 'FORMAL'} benchmark run (${total} observations scheduled)...`);

        // 1. Warmup phase (unrecorded)
        this.log(`Running ${warmupCount} warmup iterations per library/size...`);
        const libraries = this.config.libraries.map(l => l.name);
        const sizes = isSmoke ? [100] : this.config.workload_sizes;

        for (const lib of libraries) {
            const adapter = this.adapters[lib];
            for (const size of sizes) {
                const workload = this.workloads[size];
                for (let w = 1; w <= warmupCount; w++) {
                    await VisualizationBenchmarkRunner.settle();
                    let instance = null;
                    try {
                        instance = adapter.create(containerElement, workload, this.config);
                        await VisualizationBenchmarkRunner.settle();
                        adapter.update(instance, workload, this.config);
                        await VisualizationBenchmarkRunner.settle();
                    } finally {
                        if (instance) adapter.destroy(instance);
                        containerElement.innerHTML = '';
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
            const workloadMeta = this.config.workloads.files[String(item.workload_size)] || {};

            const obsRecord = {
                observation_id: obsId,
                git_revision: this.config.benchmark_id ? 'fd318b3ca0d3829c0849ee2a5ef783caaae72fdb' : 'UNAVAILABLE',
                benchmark_config_sha256: '47861199a9fb4297904fcdf425c8deb97b90666c3ea1d5f9d2b966a5b47a2b31',
                workload_sha256: workloadMeta.sha256 || 'UNAVAILABLE',
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

            await VisualizationBenchmarkRunner.settle();
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
                    await VisualizationBenchmarkRunner.settle();

                    // Update Render Timing
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
                }
                containerElement.innerHTML = '';
                await VisualizationBenchmarkRunner.settle();
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
     * Converts results into canonical CSV string.
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
            r.observation_id,
            r.git_revision,
            r.benchmark_config_sha256,
            r.workload_sha256,
            r.library,
            r.library_version,
            r.renderer,
            r.workload_size,
            r.repeat_index,
            r.execution_order_index,
            r.render_ms !== null ? r.render_ms : '',
            r.update_ms !== null ? r.update_ms : '',
            r.browser_name,
            r.browser_version,
            r.viewport_width,
            r.viewport_height,
            r.device_pixel_ratio,
            r.status,
            r.failure_code
        ].map(val => (typeof val === 'string' && val.includes(',') ? `"${val}"` : val)).join(','));

        return [header.join(','), ...rows].join('\n') + '\n';
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = VisualizationBenchmarkRunner;
}
