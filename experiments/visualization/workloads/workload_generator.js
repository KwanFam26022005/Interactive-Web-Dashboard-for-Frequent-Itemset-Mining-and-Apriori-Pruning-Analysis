/**
 * In-Browser Deterministic Workload Generator for RQ3 Visualization Benchmark.
 *
 * Implements identical Numerical Recipes 32-bit LCG as generate_workloads.php.
 */
class WorkloadGenerator {
    static SEED = 42;
    static WORKLOAD_SIZES = [100, 1000, 5000, 10000];

    static nextFloat(stateRef) {
        stateRef.state = (Math.imul(1664525, stateRef.state) + 1013904223) >>> 0;
        return Number((stateRef.state / 4294967296).toFixed(6));
    }

    static generate(n) {
        const baseState = { state: ((WorkloadGenerator.SEED * 1009 + n) >>> 0) };
        const updateState = { state: ((Math.imul(baseState.state, 69069) + 1) >>> 0) };

        const basePoints = [];
        for (let i = 1; i <= n; i++) {
            basePoints.push({
                id: i,
                x: WorkloadGenerator.nextFloat(baseState),
                y: WorkloadGenerator.nextFloat(baseState)
            });
        }

        const updatePoints = [];
        for (let i = 1; i <= n; i++) {
            updatePoints.push({
                id: i,
                x: WorkloadGenerator.nextFloat(updateState),
                y: WorkloadGenerator.nextFloat(updateState)
            });
        }

        return {
            schema_version: '1.0.0',
            workload_id: `WORKLOAD-N${n}`,
            size: n,
            seed: WorkloadGenerator.SEED,
            domain: { x: [0, 1], y: [0, 1] },
            base_points: basePoints,
            update_points: updatePoints
        };
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = WorkloadGenerator;
}
