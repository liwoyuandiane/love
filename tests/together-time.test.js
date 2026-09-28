/**
 * together-time.js 单元测试
 * 运行：node tests/together-time.test.js
 */
const assert = require('assert');
const TT = require('../assets/js/together-time.js');

const DAY = 86400000;
const ANN = '2018-06-16';
const ANN_MS = Date.UTC(2018, 5, 16);
const NOW = Date.UTC(2026, 8, 28, 12, 0, 0); // 2026-09-28 12:00 UTC

let passed = 0;
function t(name, fn) {
    fn();
    passed += 1;
    console.log('ok - ' + name);
}

t('parseDateUTC: Y-m-d 解析为 UTC 零点', () => {
    assert.strictEqual(TT.parseDateUTC('2018-06-16'), ANN_MS);
});

t('parseDateUTC: 带时间的字符串取日期部分', () => {
    assert.strictEqual(TT.parseDateUTC('2018-06-16T10:30:00'), ANN_MS);
});

t('parseDateUTC: 非法输入返回 NaN', () => {
    assert.ok(isNaN(TT.parseDateUTC('garbage')));
    assert.ok(isNaN(TT.parseDateUTC('')));
    assert.ok(isNaN(TT.parseDateUTC(null)));
    assert.ok(isNaN(TT.parseDateUTC('2018-13-01')));
    assert.ok(isNaN(TT.parseDateUTC('2019-02-30')));
});

t('无区间：与现网算法逐秒一致', () => {
    const r = TT.computeElapsed(ANN, [], NOW);
    const legacy = Math.floor((NOW - ANN_MS) / 1000);
    assert.strictEqual(r.seconds, legacy);
    assert.strictEqual(r.elapsedMs, NOW - ANN_MS);
    assert.strictEqual(r.excludedMs, 0);
    assert.strictEqual(r.frozen, false);
});

t('闭区间：整月区间全额扣除', () => {
    const r = TT.computeElapsed(ANN, [{ start_date: '2021-01-01', end_date: '2021-02-01' }], NOW);
    const excluded = 31 * DAY;
    assert.strictEqual(r.excludedMs, excluded);
    assert.strictEqual(r.elapsedMs, (NOW - ANN_MS) - excluded);
    assert.strictEqual(r.frozen, false);
});

t('左闭右开：结束日当天算在一起', () => {
    const r = TT.computeElapsed(ANN, [{ start_date: '2021-01-01', end_date: '2021-01-02' }], NOW);
    assert.strictEqual(r.excludedMs, DAY);
});

t('区间在纪念日之前：钳制为零扣除', () => {
    const r = TT.computeElapsed('2020-01-01', [{ start_date: '2018-06-16', end_date: '2019-01-01' }], NOW);
    assert.strictEqual(r.excludedMs, 0);
});

t('区间跨越纪念日起点：只扣交集', () => {
    const r = TT.computeElapsed('2020-06-01', [{ start_date: '2020-01-01', end_date: '2020-07-01' }], NOW);
    assert.strictEqual(r.excludedMs, 30 * DAY);
});

t('进行中区间：计时冻结在起点', () => {
    const r = TT.computeElapsed(ANN, [{ start_date: '2024-01-01', end_date: null }], NOW);
    const frozenAt = Date.UTC(2024, 0, 1);
    assert.strictEqual(r.frozen, true);
    assert.strictEqual(r.elapsedMs, frozenAt - ANN_MS);
    assert.strictEqual(r.seconds, Math.floor((frozenAt - ANN_MS) / 1000));
    assert.strictEqual(r.excludedMs, 0);
});

t('未来才开始的进行中区间：不冻结、正常计时', () => {
    const r = TT.computeElapsed(ANN, [{ start_date: '2027-01-01', end_date: '' }], NOW);
    assert.strictEqual(r.frozen, false);
    assert.strictEqual(r.seconds, Math.floor((NOW - ANN_MS) / 1000));
});

t('多区间：扣除量求和', () => {
    const r = TT.computeElapsed(ANN, [
        { start_date: '2021-01-01', end_date: '2021-01-11' },
        { start_date: '2022-03-01', end_date: '2022-04-01' }
    ], NOW);
    assert.strictEqual(r.excludedMs, 41 * DAY);
});

t('相邻区间首尾相接：无重复扣除', () => {
    const r = TT.computeElapsed(ANN, [
        { start_date: '2021-01-01', end_date: '2021-02-01' },
        { start_date: '2021-02-01', end_date: '2021-03-01' }
    ], NOW);
    assert.strictEqual(r.excludedMs, 59 * DAY);
});

t('进行中 + 历史闭区间：冻结点之前累计，闭区间照扣', () => {
    const r = TT.computeElapsed(ANN, [
        { start_date: '2021-01-01', end_date: '2021-02-01' },
        { start_date: '2024-06-01', end_date: null }
    ], NOW);
    const expected = (Date.UTC(2024, 5, 1) - ANN_MS) - 31 * DAY;
    assert.strictEqual(r.frozen, true);
    assert.strictEqual(r.elapsedMs, expected);
    assert.strictEqual(r.excludedMs, 31 * DAY);
});

t('纪念日在未来：返回零', () => {
    const r = TT.computeElapsed('2027-01-01', [], NOW);
    assert.strictEqual(r.elapsedMs, 0);
    assert.strictEqual(r.seconds, 0);
});

t('非法纪念日：返回零且不抛错', () => {
    const r = TT.computeElapsed('not-a-date', [], NOW);
    assert.strictEqual(r.elapsedMs, 0);
    assert.strictEqual(r.frozen, false);
});

t('异常数据（区间早于纪念日的进行中）：钳制为零不抛错', () => {
    const r = TT.computeElapsed(ANN, [{ start_date: '2017-01-01', end_date: null }], NOW);
    assert.strictEqual(r.elapsedMs, 0);
});

t('异常数据（区间日期非法）：忽略该区间', () => {
    const r = TT.computeElapsed(ANN, [{ start_date: 'xx', end_date: 'yy' }], NOW);
    assert.strictEqual(r.excludedMs, 0);
    assert.strictEqual(r.seconds, Math.floor((NOW - ANN_MS) / 1000));
});

t('扣除提示阈值：floor(扣除毫秒/天) >= 1 才展示', () => {
    const half = TT.computeElapsed(ANN, [{ start_date: '2021-01-01', end_date: '2021-01-01' }], NOW);
    assert.strictEqual(Math.floor(half.excludedMs / DAY), 0);
    const one = TT.computeElapsed(ANN, [{ start_date: '2021-01-01', end_date: '2021-01-02' }], NOW);
    assert.strictEqual(Math.floor(one.excludedMs / DAY), 1);
});

t('periods 为 undefined/null：视为空数组', () => {
    assert.strictEqual(TT.computeElapsed(ANN, undefined, NOW).seconds,
        Math.floor((NOW - ANN_MS) / 1000));
    assert.strictEqual(TT.computeElapsed(ANN, null, NOW).seconds,
        Math.floor((NOW - ANN_MS) / 1000));
});

console.log('\n' + passed + ' passed');
