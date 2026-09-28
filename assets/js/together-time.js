/**
 * TogetherTime - 有效在一起时长计算（纯函数，无 DOM 依赖）
 * 边界约定：日期按 UTC 零点解析，分手区间为左闭右开 [start_date, end_date)
 * end_date 为 null/空 表示进行中，计时冻结在 start_date
 */
(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
    if (root) {
        root.TogetherTime = api;
    }
})(typeof window !== 'undefined' ? window : (typeof globalThis !== 'undefined' ? globalThis : null), function () {
    'use strict';

    var MS_PER_DAY = 86400000;

    /**
     * 解析 Y-m-d（可带 T 时间）为 UTC 零点毫秒；非法返回 NaN
     */
    function parseDateUTC(value) {
        if (typeof value !== 'string' || value === '') {
            return NaN;
        }
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value.split('T')[0]);
        if (!m) {
            return NaN;
        }
        var y = +m[1], mo = +m[2], d = +m[3];
        if (mo < 1 || mo > 12 || d < 1 || d > 31) {
            return NaN;
        }
        var ms = Date.UTC(y, mo - 1, d, 0, 0, 0, 0);
        var dt = new Date(ms);
        if (dt.getUTCFullYear() !== y || dt.getUTCMonth() !== mo - 1 || dt.getUTCDate() !== d) {
            return NaN;
        }
        return ms;
    }

    /**
     * 计算有效在一起时长
     *
     * @param {string} anniversary 纪念日 Y-m-d
     * @param {Array<{start_date: string, end_date: string|null}>} periods 分手区间
     * @param {number} nowMs 当前时间毫秒
     * @returns {{elapsedMs: number, seconds: number, excludedMs: number, frozen: boolean}}
     *   frozen=true 表示存在进行中的分手区间，计时冻结
     */
    function computeElapsed(anniversary, periods, nowMs) {
        var result = { elapsedMs: 0, seconds: 0, excludedMs: 0, frozen: false };
        var annStart = parseDateUTC(anniversary);
        if (isNaN(annStart) || typeof nowMs !== 'number' || isNaN(nowMs) || nowMs < annStart) {
            return result;
        }

        var list = Array.isArray(periods) ? periods : [];

        // 进行中区间（end_date 为空）：有效结束点冻结在区间开始日
        var effectiveEnd = nowMs;
        var frozen = false;
        for (var i = 0; i < list.length; i++) {
            var p = list[i];
            if (p && !p.end_date) {
                var s = parseDateUTC(p.start_date);
                if (!isNaN(s) && s < effectiveEnd) {
                    effectiveEnd = s;
                    frozen = true;
                }
            }
        }

        // 扣除与纪念日的交集，钳制在 [annStart, effectiveEnd)
        var excludedMs = 0;
        for (var j = 0; j < list.length; j++) {
            var period = list[j];
            if (!period) {
                continue;
            }
            var ps = parseDateUTC(period.start_date);
            if (isNaN(ps)) {
                continue;
            }
            var pe = Infinity;
            if (period.end_date) {
                pe = parseDateUTC(period.end_date);
                if (isNaN(pe)) {
                    continue;
                }
            }
            var from = ps > annStart ? ps : annStart;
            var to = pe < effectiveEnd ? pe : effectiveEnd;
            if (to > from) {
                excludedMs += to - from;
            }
        }

        var elapsedMs = (effectiveEnd - annStart) - excludedMs;
        if (elapsedMs < 0) {
            elapsedMs = 0;
        }

        return {
            elapsedMs: elapsedMs,
            seconds: Math.floor(elapsedMs / 1000),
            excludedMs: excludedMs,
            frozen: frozen
        };
    }

    return {
        MS_PER_DAY: MS_PER_DAY,
        parseDateUTC: parseDateUTC,
        computeElapsed: computeElapsed
    };
});
