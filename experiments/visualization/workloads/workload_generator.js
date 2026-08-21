/**
 * In-Browser Deterministic Workload Generator for RQ3 Visualization Benchmark.
 *
 * Implements Mulberry32 PRNG with seed 0xDEADBEEF (3735928559).
 */
class WorkloadGenerator {
    static SEED = 0xDEADBEEF; // 3735928559
    static WORKLOAD_SIZES = [100, 1000, 5000, 10000];

    static nextFloat(stateRef) {
        stateRef.state = (stateRef.state + 0x6D2B79F5) >>> 0;
        let t = stateRef.state;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        const res = ((t ^ (t >>> 14)) >>> 0) / 4294967296;
        return Number(res.toFixed(6));
    }

    static generateWorkloadForSize(n) {
        const stateRef = { state: ((WorkloadGenerator.SEED ^ Math.imul(n, 2654435761)) >>> 0) };

        const basePoints = [];
        for (let i = 1; i <= n; i++) {
            basePoints.push({
                id: i,
                x: WorkloadGenerator.nextFloat(stateRef),
                y: WorkloadGenerator.nextFloat(stateRef)
            });
        }

        const updatePoints = [];
        for (let i = 0; i < n; i++) {
            const base = basePoints[i];
            if ((i + 1) % 2 === 0) {
                const newY = Number(((base.y + 0.1) % 1.0).toFixed(6));
                updatePoints.push({
                    id: base.id,
                    x: base.x,
                    y: newY
                });
            } else {
                updatePoints.push({
                    id: base.id,
                    x: base.x,
                    y: base.y
                });
            }
        }

        return {
            size: n,
            base_points: basePoints,
            update_points: updatePoints
        };
    }

    static generateCanonicalBundle() {
        const workloads = {};
        for (const size of WorkloadGenerator.WORKLOAD_SIZES) {
            workloads[size] = WorkloadGenerator.generateWorkloadForSize(size);
        }

        return {
            schema_version: '1.0.0',
            benchmark_id: 'BENCHMARK-VIS-SCATTER-V1',
            generator: 'Mulberry32',
            seed: '0xDEADBEEF',
            seed_decimal: WorkloadGenerator.SEED,
            domain: { x: [0, 1], y: [0, 1] },
            displacement_rule: 'y_i <- round(fmod(y_i + 0.1, 1.0), 6) for even point IDs (exactly 50%)',
            workloads: workloads
        };
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = WorkloadGenerator;
}
